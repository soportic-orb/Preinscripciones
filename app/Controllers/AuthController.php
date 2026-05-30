<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\Flash;
use App\Core\Hash;
use App\Core\I18n;
use App\Core\Mailer;
use App\Core\RateLimit;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Token;
use App\Core\Totp;
use App\Core\Validator;
use App\Models\User;

/**
 * Autenticación: login, registro, verificación de email, recuperación y 2FA.
 */
final class AuthController extends Controller
{
    // ----------------------------------------------------------------- Login
    public function showLogin(Request $request): never
    {
        $this->view('auth/login', ['title' => __('auth.login_title')], 'layouts/auth');
    }

    public function login(Request $request): never
    {
        $email = $request->str('email');
        $password = (string) $request->input('password', '');

        $bucket = 'login:' . $request->ip() . ':' . mb_strtolower($email);
        if (!RateLimit::attempt($bucket, 5, 300)) {
            Flash::error(__('common.too_many_requests'));
            $this->redirect('/login');
        }

        $user = Auth::attempt($email, $password);
        if ($user === null) {
            Audit::log('auth.login_failed', null, 'user', null, ['email' => $email], $request->ip());
            Flash::error(__('auth.invalid_credentials'));
            $this->withOld(['email' => $email]);
            $this->redirect('/login');
        }

        if (!$user->isVerified()) {
            Flash::warning(__('auth.email_not_verified'));
            Session::set('_unverified_user', $user->id);
            $this->redirect('/login');
        }

        RateLimit::clear($bucket);

        // Si tiene 2FA activo, exigir segundo factor antes de iniciar sesión.
        if ($user->totp_enabled === 1) {
            Auth::markPending2fa($user->id);
            $this->redirect('/2fa');
        }

        Auth::login($user);
        Audit::log('auth.login', $user->id, 'user', $user->id, [], $request->ip());
        Flash::success(__('auth.welcome_back', ['name' => $user->name]));
        $this->redirect('/panel');
    }

    public function logout(Request $request): never
    {
        $id = Auth::id();
        Auth::logout();
        Audit::log('auth.logout', $id, 'user', $id, [], $request->ip());
        Flash::info(__('auth.logged_out'));
        $this->redirect('/');
    }

    // -------------------------------------------------------------- Registro
    public function showRegister(Request $request): never
    {
        $this->view('auth/register', ['title' => __('auth.register_title')], 'layouts/auth');
    }

    public function register(Request $request): never
    {
        $data = [
            'name' => $request->str('name'),
            'email' => $request->str('email'),
            'password' => (string) $request->input('password', ''),
            'password_confirmation' => (string) $request->input('password_confirmation', ''),
            'terms' => $request->input('terms'),
        ];

        $v = Validator::make($data, [
            'name' => 'required|min:2|max:120',
            'email' => 'required|email|max:190',
            'password' => 'required|password|confirmed',
            'terms' => 'accepted',
        ], [
            'name' => __('auth.field_name'),
            'email' => __('auth.field_email'),
            'password' => __('auth.field_password'),
            'terms' => __('auth.field_terms'),
        ]);

        if ($v->fails()) {
            $this->flashErrors($v->errors());
            $this->withOld(['name' => $data['name'], 'email' => $data['email']]);
            $this->redirect('/registro');
        }

        if (User::emailExists($data['email'])) {
            Flash::error(__('auth.email_taken'));
            $this->withOld(['name' => $data['name'], 'email' => $data['email']]);
            $this->redirect('/login');
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => 'estudiante',
            'locale' => I18n::locale(),
            'is_active' => 1,
        ]);

        Audit::log('auth.register', $user->id, 'user', $user->id, [], $request->ip());

        // Registrar el consentimiento versionado de los textos legales vigentes.
        (new \App\Services\ConsentService())->recordAllCurrent($user->id, $request->ip());

        $this->sendVerificationEmail($user);

        Flash::success(__('auth.registered_check_email'));
        $this->redirect('/login');
    }

    // ----------------------------------------------------- Verificación email
    private function sendVerificationEmail(User $user): void
    {
        $token = Token::issue('email_verifications', $user->id, 86400);
        $link = url('verificar-email?token=' . $token . '&id=' . $user->id);
        $subject = __('emails.verify_subject');
        $body = '<p>' . e(__('emails.verify_greeting', ['name' => $user->name])) . '</p>'
            . '<p>' . e(__('emails.verify_body')) . '</p>'
            . '<p><a href="' . e($link) . '">' . e(__('emails.verify_cta')) . '</a></p>';
        Mailer::send($user->email, $user->name, $subject, $body);
    }

    public function verifyEmail(Request $request): never
    {
        $token = $request->str('token');
        $row = Token::consume('email_verifications', $token);
        if ($row === null) {
            Flash::error(__('auth.verify_invalid'));
            $this->redirect('/login');
        }
        $db = Database::instance();
        $db->update('users', ['email_verified_at' => date('Y-m-d H:i:s')], ['id' => (int) $row['user_id']]);
        Audit::log('auth.email_verified', (int) $row['user_id'], 'user', (int) $row['user_id'], [], $request->ip());
        Flash::success(__('auth.verify_success'));
        $this->redirect('/login');
    }

    public function resendVerification(Request $request): never
    {
        $id = Session::get('_unverified_user');
        if (is_numeric($id)) {
            $user = User::findById((int) $id);
            if ($user !== null && !$user->isVerified()) {
                $this->sendVerificationEmail($user);
                Flash::success(__('auth.verify_resent'));
            }
        }
        $this->redirect('/login');
    }

    // ------------------------------------------------ Recuperación contraseña
    public function showForgot(Request $request): never
    {
        $this->view('auth/forgot', ['title' => __('auth.forgot_title')], 'layouts/auth');
    }

    public function sendReset(Request $request): never
    {
        $email = $request->str('email');
        if (!RateLimit::attempt('reset:' . $request->ip(), 5, 600)) {
            Flash::error(__('common.too_many_requests'));
            $this->redirect('/recuperar');
        }

        $user = User::findByEmail($email);
        if ($user !== null) {
            $token = Token::issue('password_resets', $user->id, 3600);
            $link = url('restablecer?token=' . $token . '&id=' . $user->id);
            $body = '<p>' . e(__('emails.reset_greeting', ['name' => $user->name])) . '</p>'
                . '<p>' . e(__('emails.reset_body')) . '</p>'
                . '<p><a href="' . e($link) . '">' . e(__('emails.reset_cta')) . '</a></p>';
            Mailer::send($user->email, $user->name, __('emails.reset_subject'), $body);
            Audit::log('auth.password_reset_requested', $user->id, 'user', $user->id, [], $request->ip());
        }

        // Respuesta idéntica exista o no la cuenta (evita enumeración de usuarios).
        Flash::info(__('auth.reset_sent_generic'));
        $this->redirect('/login');
    }

    public function showReset(Request $request): never
    {
        $this->view('auth/reset', [
            'title' => __('auth.reset_title'),
            'token' => $request->str('token'),
        ], 'layouts/auth');
    }

    public function resetPassword(Request $request): never
    {
        $token = $request->str('token');
        $data = [
            'password' => (string) $request->input('password', ''),
            'password_confirmation' => (string) $request->input('password_confirmation', ''),
        ];
        $v = Validator::make($data, ['password' => 'required|password|confirmed'], [
            'password' => __('auth.field_password'),
        ]);
        if ($v->fails()) {
            $this->flashErrors($v->errors());
            $this->redirect('/restablecer?token=' . urlencode($token));
        }

        $row = Token::consume('password_resets', $token);
        if ($row === null) {
            Flash::error(__('auth.reset_invalid'));
            $this->redirect('/recuperar');
        }

        Database::instance()->update('users', [
            'password_hash' => Hash::make($data['password']),
        ], ['id' => (int) $row['user_id']]);
        Audit::log('auth.password_reset', (int) $row['user_id'], 'user', (int) $row['user_id'], [], $request->ip());

        Flash::success(__('auth.reset_success'));
        $this->redirect('/login');
    }

    // ------------------------------------------------------------------- 2FA
    public function show2fa(Request $request): never
    {
        if (Auth::pending2faUserId() === null) {
            $this->redirect('/login');
        }
        $this->view('auth/twofactor', ['title' => __('auth.twofa_title')], 'layouts/auth');
    }

    public function verify2fa(Request $request): never
    {
        $userId = Auth::pending2faUserId();
        if ($userId === null) {
            $this->redirect('/login');
        }
        $user = User::findById($userId);
        $code = $request->str('code');

        if ($user === null || $user->totp_secret === null || !Totp::verify($user->totp_secret, $code)) {
            Audit::log('auth.2fa_failed', $userId, 'user', $userId, [], $request->ip());
            Flash::error(__('auth.twofa_invalid'));
            $this->redirect('/2fa');
        }

        Auth::login($user);
        Audit::log('auth.2fa_success', $user->id, 'user', $user->id, [], $request->ip());
        Flash::success(__('auth.welcome_back', ['name' => $user->name]));
        $this->redirect('/panel');
    }
}

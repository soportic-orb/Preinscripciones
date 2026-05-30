<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Env;
use App\Core\EnvWriter;
use App\Core\Flash;
use App\Core\Mailer;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;

/**
 * Configuración de plataforma (solo admin): ajustes generales, formas de pago e
 * integraciones (SMTP/Stripe/Git) editables en caliente.
 */
final class SettingsController extends Controller
{
    public function general(Request $request): never
    {
        $this->view('system/settings/general', [
            'title' => __('settings.general_title'),
            'user' => Auth::user(),
            'locales' => Config::locales(),
            'settings' => Settings::group('general'),
            'payments' => Settings::group('payments'),
        ]);
    }

    public function saveGeneral(Request $request): never
    {
        $default = $request->str('default_locale', 'es');
        Settings::setMany('general', [
            'default_locale' => in_array($default, Config::locales(), true) ? $default : 'es',
            'data_retention_days' => (string) (int) $request->input('data_retention_days', 0),
        ]);
        // Formas de pago admitidas a nivel de plataforma.
        Settings::setMany('payments', [
            'stripe' => $request->input('pay_stripe') ? '1' : '0',
            'bizum' => $request->input('pay_bizum') ? '1' : '0',
            'transfer' => $request->input('pay_transfer') ? '1' : '0',
        ]);
        Audit::log('settings.general_update', Auth::id(), 'settings', null, [], $request->ip());
        Flash::success(__('settings.saved'));
        $this->redirect('/gestion/sistema/ajustes');
    }

    public function integrations(Request $request): never
    {
        $this->view('system/settings/integrations', [
            'title' => __('settings.integrations_title'),
            'user' => Auth::user(),
            'mail' => Config::mail(),
            'stripe' => [
                'public' => Env::get('STRIPE_PUBLIC_KEY', ''),
                'secret_set' => Env::get('STRIPE_SECRET_KEY', '') !== '',
            ],
            'git' => [
                'branch' => Env::get('GIT_BRANCH', 'main'),
                'token_set' => Env::get('GIT_TOKEN', '') !== '',
            ],
        ]);
    }

    public function saveIntegrations(Request $request): never
    {
        $pairs = [
            'MAIL_HOST' => $request->str('mail_host'),
            'MAIL_PORT' => (string) (int) $request->input('mail_port', 587),
            'MAIL_USER' => $request->str('mail_user'),
            'MAIL_ENCRYPTION' => $request->str('mail_encryption', 'tls'),
            'MAIL_FROM_ADDRESS' => $request->str('mail_from'),
            'STRIPE_PUBLIC_KEY' => $request->str('stripe_public'),
            'STRIPE_WEBHOOK_SECRET' => $request->str('stripe_webhook'),
            'GIT_BRANCH' => $request->str('git_branch', 'main'),
        ];
        // Solo sobrescribir secretos si se han escrito (evita borrarlos al guardar).
        if ($request->str('mail_pass') !== '') {
            $pairs['MAIL_PASS'] = $request->str('mail_pass');
        }
        if ($request->str('stripe_secret') !== '') {
            $pairs['STRIPE_SECRET_KEY'] = $request->str('stripe_secret');
        }
        if ($request->str('git_token') !== '') {
            $pairs['GIT_TOKEN'] = $request->str('git_token');
        }

        EnvWriter::update($pairs);
        Audit::log('settings.integrations_update', Auth::id(), 'settings', null, ['keys' => array_keys($pairs)], $request->ip());
        Flash::success(__('settings.saved'));
        $this->redirect('/gestion/sistema/integraciones');
    }

    /** Envía un email de prueba para verificar la configuración SMTP (AJAX). */
    public function testMail(Request $request): never
    {
        $to = $request->str('email');
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            Response::json(['ok' => false, 'message' => __('settings.invalid_email')], 422);
        }
        $ok = Mailer::send($to, $to, __('settings.test_subject'), '<p>' . e(__('settings.test_body')) . '</p>');
        Audit::log('settings.mail_test', Auth::id(), 'settings', null, ['to' => $to, 'ok' => $ok], $request->ip());
        Response::json(['ok' => $ok, 'message' => $ok ? __('settings.test_sent') : __('settings.test_failed')]);
    }
}

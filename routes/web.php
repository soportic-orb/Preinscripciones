<?php

declare(strict_types=1);

use App\Core\Router;

/**
 * Definición de rutas web de la plataforma.
 *
 * Middlewares disponibles: auth, guest, role:owner,admin,gestor, csrf, throttle:n,s.
 */
return static function (Router $r): void {
    // --- Público ---
    $r->get('/', 'HomeController@index')->name('home');
    $r->get('/cambiar-idioma', 'HomeController@switchLocale')->name('locale.switch');

    // --- Autenticación (solo invitados) ---
    $r->group(['guest'], function (Router $r): void {
        $r->get('/login', 'AuthController@showLogin')->name('login');
        $r->post('/login', 'AuthController@login')->name('login.post');
        $r->get('/registro', 'AuthController@showRegister')->name('register');
        $r->post('/registro', 'AuthController@register')->name('register.post');
        $r->get('/recuperar', 'AuthController@showForgot')->name('password.forgot');
        $r->post('/recuperar', 'AuthController@sendReset')->name('password.email');
        $r->get('/restablecer', 'AuthController@showReset')->name('password.reset');
        $r->post('/restablecer', 'AuthController@resetPassword')->name('password.update');
    });

    // --- Verificación de email (token público) ---
    $r->get('/verificar-email', 'AuthController@verifyEmail')->name('verification.verify');
    $r->post('/reenviar-verificacion', 'AuthController@resendVerification')->name('verification.resend');

    // --- Segundo factor (2FA) durante el login ---
    $r->get('/2fa', 'AuthController@show2fa')->name('2fa.show');
    $r->post('/2fa', 'AuthController@verify2fa')->name('2fa.verify');

    // --- Sesión autenticada ---
    $r->group(['auth'], function (Router $r): void {
        $r->post('/logout', 'AuthController@logout')->name('logout');
        $r->get('/panel', 'DashboardController@index')->name('dashboard');
    });

    // --- Panel de gestión (staff) ---
    $r->group(['role:owner,admin,gestor'], function (Router $r): void {
        $r->get('/gestion', 'DashboardController@management')->name('management');
    });

    // --- Configuración de plataforma (solo admin) ---
    $r->group(['role:owner,admin'], function (Router $r): void {
        $r->get('/gestion/sistema', 'SystemController@index')->name('system');
    });
};

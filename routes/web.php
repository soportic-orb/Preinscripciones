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
        $r->get('/registro', 'AuthController@showRegister')->name('register');
        $r->get('/recuperar', 'AuthController@showForgot')->name('password.forgot');
        $r->get('/restablecer', 'AuthController@showReset')->name('password.reset');
    });
    $r->group(['guest', 'csrf'], function (Router $r): void {
        $r->post('/login', 'AuthController@login')->name('login.post');
        $r->post('/registro', 'AuthController@register')->name('register.post');
        $r->post('/recuperar', 'AuthController@sendReset')->name('password.email');
        $r->post('/restablecer', 'AuthController@resetPassword')->name('password.update');
    });

    // --- Verificación de email (token público) ---
    $r->get('/verificar-email', 'AuthController@verifyEmail')->name('verification.verify');
    $r->group(['csrf'], function (Router $r): void {
        $r->post('/reenviar-verificacion', 'AuthController@resendVerification')->name('verification.resend');
    });

    // --- Segundo factor (2FA) durante el login ---
    $r->get('/2fa', 'AuthController@show2fa')->name('2fa.show');
    $r->group(['csrf'], function (Router $r): void {
        $r->post('/2fa', 'AuthController@verify2fa')->name('2fa.verify');
    });

    // --- Sesión autenticada ---
    $r->group(['auth'], function (Router $r): void {
        $r->get('/panel', 'DashboardController@index')->name('dashboard');
    });
    $r->group(['auth', 'csrf'], function (Router $r): void {
        $r->post('/logout', 'AuthController@logout')->name('logout');
    });

    // --- Panel de gestión (staff) ---
    $r->group(['role:owner,admin,gestor'], function (Router $r): void {
        $r->get('/gestion', 'DashboardController@management')->name('management');
    });

    // --- Configuración de plataforma (solo admin) ---
    $r->group(['role:owner,admin'], function (Router $r): void {
        $r->get('/gestion/sistema', 'SystemController@index')->name('system');

        // Campos dinámicos
        $r->get('/gestion/sistema/campos', 'FieldsController@index')->name('fields.index');
        $r->get('/gestion/sistema/campos/nuevo', 'FieldsController@create')->name('fields.create');
        $r->get('/gestion/sistema/campos/{id}', 'FieldsController@edit')->name('fields.edit');

        // Ajustes generales e integraciones
        $r->get('/gestion/sistema/ajustes', 'SettingsController@general')->name('settings.general');
        $r->get('/gestion/sistema/integraciones', 'SettingsController@integrations')->name('settings.integrations');

        // Textos legales
        $r->get('/gestion/sistema/legales', 'LegalController@index')->name('legal.index');
        $r->get('/gestion/sistema/legales/{type}/editar', 'LegalController@edit')->name('legal.edit');
    });

    $r->group(['role:owner,admin', 'csrf'], function (Router $r): void {
        $r->post('/gestion/sistema/campos', 'FieldsController@store')->name('fields.store');
        $r->post('/gestion/sistema/campos/{id}', 'FieldsController@update')->name('fields.update');
        $r->post('/gestion/sistema/campos/{id}/eliminar', 'FieldsController@destroy')->name('fields.destroy');

        $r->post('/gestion/sistema/ajustes', 'SettingsController@saveGeneral')->name('settings.general.save');
        $r->post('/gestion/sistema/integraciones', 'SettingsController@saveIntegrations')->name('settings.integrations.save');
        $r->post('/gestion/sistema/integraciones/test-mail', 'SettingsController@testMail')->name('settings.test_mail');

        $r->post('/gestion/sistema/legales/{type}', 'LegalController@store')->name('legal.store');
    });
};

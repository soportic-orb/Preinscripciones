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

    // --- Catálogo público de preinscripción ---
    $r->get('/preinscripcion', 'PreinscriptionController@catalog')->name('catalog');

    // --- Webhook de Stripe (público, sin CSRF; verifica firma) ---
    $r->post('/webhooks/stripe', 'StripeWebhookController@handle')->name('stripe.webhook');

    // --- Sesión autenticada ---
    $r->group(['auth'], function (Router $r): void {
        $r->get('/panel', 'DashboardController@index')->name('dashboard');

        // Asistente de preinscripción (estudiante)
        $r->get('/preinscripcion/{id}/paso/{step}', 'PreinscriptionController@step')->name('preinscription.step');

        // Panel de estudiante
        $r->get('/panel/preinscripcion/{id}', 'StudentController@show')->name('student.preinscription');
        $r->get('/panel/exportar-datos', 'StudentController@exportData')->name('student.export');
        $r->get('/documento/{id}', 'StudentController@download')->name('document.download');

        // Pagos y facturación (estudiante)
        $r->get('/panel/preinscripcion/{id}/pago', 'PaymentController@show')->name('payment.show');
        $r->get('/panel/facturacion', 'BillingController@profile')->name('billing.profile');
        $r->get('/factura/{id}', 'BillingController@download')->name('invoice.download');
    });
    $r->group(['auth', 'csrf'], function (Router $r): void {
        $r->post('/logout', 'AuthController@logout')->name('logout');
        $r->post('/preinscripcion/iniciar', 'PreinscriptionController@start')->name('preinscription.start');
        $r->post('/preinscripcion/{id}/paso/{step}', 'PreinscriptionController@save')->name('preinscription.save');
        $r->post('/preinscripcion/{id}/documento', 'PreinscriptionController@uploadDocument')->name('preinscription.upload');
        $r->post('/panel/preinscripcion/{id}/documento', 'StudentController@uploadDocument')->name('student.upload');
        $r->post('/panel/solicitar-supresion', 'StudentController@requestDeletion')->name('student.deletion');

        // Pagos (estudiante)
        $r->post('/panel/preinscripcion/{id}/pago/descuento', 'PaymentController@applyDiscount')->name('payment.discount');
        $r->post('/panel/preinscripcion/{id}/pago/stripe', 'PaymentController@payStripe')->name('payment.stripe');
        $r->post('/panel/preinscripcion/{id}/pago/justificante', 'PaymentController@submitProof')->name('payment.proof');
        $r->post('/panel/preinscripcion/{id}/pago/fundae', 'PaymentController@saveFundae')->name('payment.fundae');
        $r->post('/panel/facturacion', 'BillingController@saveProfile')->name('billing.save');
    });

    // --- Gestión del proceso (staff: owner/admin/gestor) ---
    $r->group(['role:owner,admin,gestor'], function (Router $r): void {
        $r->get('/gestion/cursos', 'CoursesController@index')->name('courses.index');
        $r->get('/gestion/cursos/nuevo', 'CoursesController@create')->name('courses.create');
        $r->get('/gestion/cursos/{id}/editar', 'CoursesController@edit')->name('courses.edit');
        $r->get('/gestion/cursos/{course}/ediciones/nueva', 'EditionsController@create')->name('editions.create');
        $r->get('/gestion/ediciones/{id}/editar', 'EditionsController@edit')->name('editions.edit');

        $r->get('/gestion/preinscripciones', 'ManagePreinscriptionsController@index')->name('manage.index');
        $r->get('/gestion/preinscripciones/{id}', 'ManagePreinscriptionsController@show')->name('manage.show');

        // Pagos y facturas (staff)
        $r->get('/gestion/pagos', 'ManagePaymentsController@index')->name('payments.manage');
    });
    $r->group(['role:owner,admin,gestor', 'csrf'], function (Router $r): void {
        $r->post('/gestion/cursos', 'CoursesController@store')->name('courses.store');
        $r->post('/gestion/cursos/{id}', 'CoursesController@update')->name('courses.update');
        $r->post('/gestion/cursos/{course}/ediciones', 'EditionsController@store')->name('editions.store');
        $r->post('/gestion/ediciones/{id}', 'EditionsController@update')->name('editions.update');
        $r->post('/gestion/ediciones/{id}/requisitos', 'EditionsController@addRequirement')->name('editions.req.add');
        $r->post('/gestion/ediciones/{id}/requisitos/{reqId}/eliminar', 'EditionsController@deleteRequirement')->name('editions.req.del');

        $r->post('/gestion/documentos/{id}/validar', 'ManagePreinscriptionsController@validateDocument')->name('manage.doc.validate');
        $r->post('/gestion/preinscripciones/promover-lista', 'ManagePreinscriptionsController@promoteWaitlist')->name('manage.waitlist');
        $r->post('/gestion/preinscripciones/{id}/aceptar', 'ManagePreinscriptionsController@accept')->name('manage.accept');
        $r->post('/gestion/preinscripciones/{id}/rechazar', 'ManagePreinscriptionsController@reject')->name('manage.reject');
        $r->post('/gestion/preinscripciones/{id}/transicion', 'ManagePreinscriptionsController@transition')->name('manage.transition');

        $r->post('/gestion/pagos/{id}/validar', 'ManagePaymentsController@validateProof')->name('payments.validate');
        $r->post('/gestion/pagos/{id}/reembolso', 'ManagePaymentsController@refund')->name('payments.refund');
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

        // Facturación (ajustes) y descuentos
        $r->get('/gestion/sistema/facturacion', 'SettingsController@billing')->name('settings.billing');
        $r->get('/gestion/descuentos', 'DiscountsController@index')->name('discounts.index');
        $r->get('/gestion/descuentos/nuevo', 'DiscountsController@create')->name('discounts.create');
    });

    $r->group(['role:owner,admin', 'csrf'], function (Router $r): void {
        $r->post('/gestion/sistema/campos', 'FieldsController@store')->name('fields.store');
        $r->post('/gestion/sistema/campos/{id}', 'FieldsController@update')->name('fields.update');
        $r->post('/gestion/sistema/campos/{id}/eliminar', 'FieldsController@destroy')->name('fields.destroy');

        $r->post('/gestion/sistema/ajustes', 'SettingsController@saveGeneral')->name('settings.general.save');
        $r->post('/gestion/sistema/integraciones', 'SettingsController@saveIntegrations')->name('settings.integrations.save');
        $r->post('/gestion/sistema/integraciones/test-mail', 'SettingsController@testMail')->name('settings.test_mail');

        $r->post('/gestion/sistema/legales/{type}', 'LegalController@store')->name('legal.store');

        $r->post('/gestion/sistema/facturacion', 'SettingsController@saveBilling')->name('settings.billing.save');
        $r->post('/gestion/descuentos', 'DiscountsController@store')->name('discounts.store');
    });
};

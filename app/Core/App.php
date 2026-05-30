<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Núcleo de la aplicación: arranque, detección de instalación y despacho HTTP.
 */
final class App
{
    private static ?App $instance = null;
    private Router $router;

    private function __construct()
    {
        $this->router = new Router();
    }

    public static function boot(): App
    {
        if (self::$instance === null) {
            require_once APP_PATH . '/Core/helpers.php';
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function router(): Router
    {
        return $this->router;
    }

    public static function isInstalled(): bool
    {
        return is_file(CONFIG_PATH . '/installed.lock');
    }

    /** Procesa la petición web entrante. */
    public function run(): void
    {
        $request = new Request();

        // Si no está instalada, redirigir al instalador (salvo que ya estemos allí).
        if (!self::isInstalled() && !str_starts_with($request->path(), '/install')) {
            Response::redirect('/install/');
        }

        Session::start();
        $this->resolveLocale($request);
        $this->enforceHttps($request);

        // Cargar definición de rutas.
        (require BASE_PATH . '/routes/web.php')($this->router);

        $this->router->dispatch($request);
    }

    private function resolveLocale(Request $request): void
    {
        $locales = Config::locales();
        $default = Config::app()['locale'];
        I18n::setFallback('es');

        // Prioridad: parámetro ?lang -> sesión -> usuario -> defecto.
        $lang = $request->str('lang');
        if ($lang !== '' && in_array($lang, $locales, true)) {
            Session::set('_locale', $lang);
        }

        $locale = Session::get('_locale');
        if (!is_string($locale) || !in_array($locale, $locales, true)) {
            $locale = $default;
        }
        I18n::setLocale($locale);
    }

    private function enforceHttps(Request $request): void
    {
        if (Config::app()['force_https'] && Config::app()['env'] === 'production' && !Session::isHttps()) {
            $host = $request->server['HTTP_HOST'] ?? '';
            if ($host !== '' && preg_match('/^[A-Za-z0-9.\-:]+$/', (string) $host)) {
                http_response_code(301);
                header('Location: https://' . $host . ($request->server['REQUEST_URI'] ?? '/'));
                exit;
            }
        }
    }
}

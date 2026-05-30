<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Página de inicio y utilidades públicas.
 */
final class HomeController extends Controller
{
    public function index(Request $request): never
    {
        $this->view('home/index', [
            'title' => Config::app()['name'],
        ]);
    }

    public function switchLocale(Request $request): never
    {
        $lang = $request->str('lang');
        if (in_array($lang, Config::locales(), true)) {
            Session::set('_locale', $lang);
        }
        $this->back('/');
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Flash;
use App\Core\I18n;
use App\Core\Request;
use App\Models\EmailTemplate;

/**
 * Editor de plantillas de email por evento e idioma (solo admin).
 *
 * Editor HTML con variables {{name}}, {{course}}, etc. (Se puede acoplar
 * GrapesJS/MJML autoalojado más adelante; el almacenamiento ya está listo.)
 */
final class EmailTemplatesController extends Controller
{
    public function index(Request $request): never
    {
        $this->view('system/templates/index', [
            'title' => __('templates.title'),
            'user' => Auth::user(),
            'events' => EmailTemplate::EVENTS,
            'locales' => Config::locales(),
            'templates' => EmailTemplate::all(),
        ]);
    }

    public function edit(Request $request): never
    {
        $event = (string) $request->route('event');
        $locale = (string) $request->route('locale');
        if (!in_array($event, EmailTemplate::EVENTS, true) || !in_array($locale, Config::locales(), true)) {
            Flash::error(__('templates.invalid'));
            $this->redirect('/gestion/sistema/plantillas');
        }
        $tpl = EmailTemplate::findFor($event, $locale);
        // Precargar con el contenido i18n por defecto si no hay plantilla.
        $default = [
            'subject' => I18n::t('notifications.' . $event . '_subject', [], $locale),
            'body_html' => '<p>' . nl2br(I18n::t('notifications.' . $event . '_body', [], $locale)) . '</p>',
        ];
        $this->view('system/templates/edit', [
            'title' => __('templates.edit'),
            'user' => Auth::user(),
            'event' => $event,
            'locale' => $locale,
            'tpl' => $tpl ?? $default,
            'isNew' => $tpl === null,
        ]);
    }

    public function store(Request $request): never
    {
        $event = (string) $request->route('event');
        $locale = (string) $request->route('locale');
        if (!in_array($event, EmailTemplate::EVENTS, true) || !in_array($locale, Config::locales(), true)) {
            Flash::error(__('templates.invalid'));
            $this->redirect('/gestion/sistema/plantillas');
        }
        EmailTemplate::save(
            $event,
            $locale,
            $request->str('subject'),
            (string) $request->input('body_html', ''),
            (bool) $request->input('is_active'),
        );
        Audit::log('email_template.save', Auth::id(), 'email_template', null, ['event' => $event, 'locale' => $locale], $request->ip());
        Flash::success(__('templates.saved'));
        $this->redirect('/gestion/sistema/plantillas');
    }
}

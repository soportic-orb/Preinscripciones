<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Flash;
use App\Core\Request;
use App\Models\LegalDocument;

/**
 * Gestión de textos legales versionados (solo admin): privacidad, términos,
 * política de cancelación. Cada guardado crea una nueva versión publicada.
 */
final class LegalController extends Controller
{
    public function index(Request $request): never
    {
        $this->view('system/legal/index', [
            'title' => __('legal.title'),
            'user' => Auth::user(),
            'types' => LegalDocument::TYPES,
            'versions' => LegalDocument::allVersions(),
            'current' => $this->currentMap(),
        ]);
    }

    public function edit(Request $request): never
    {
        $type = (string) $request->route('type');
        if (!in_array($type, LegalDocument::TYPES, true)) {
            Flash::error(__('legal.invalid_type'));
            $this->redirect('/gestion/sistema/legales');
        }
        // Cargar la versión vigente por idioma para precargar el formulario.
        $existing = [];
        foreach (Config::locales() as $loc) {
            $doc = LegalDocument::current($type, $loc);
            $existing[$loc] = [
                'title' => $doc['title'] ?? '',
                'body' => $doc['body'] ?? '',
            ];
        }
        $this->view('system/legal/edit', [
            'title' => __('legal.edit'),
            'user' => Auth::user(),
            'type' => $type,
            'locales' => Config::locales(),
            'existing' => $existing,
        ]);
    }

    public function store(Request $request): never
    {
        $type = (string) $request->route('type');
        if (!in_array($type, LegalDocument::TYPES, true)) {
            Flash::error(__('legal.invalid_type'));
            $this->redirect('/gestion/sistema/legales');
        }
        $translations = [];
        foreach (Config::locales() as $loc) {
            $translations[$loc] = [
                'title' => trim((string) ($request->post['title'][$loc] ?? '')),
                'body' => trim((string) ($request->post['body'][$loc] ?? '')),
            ];
        }
        $version = LegalDocument::publishNewVersion($type, $translations);
        Audit::log('legal.publish', Auth::id(), 'legal', null, ['type' => $type, 'version' => $version], $request->ip());
        Flash::success(__('legal.published', ['version' => $version]));
        $this->redirect('/gestion/sistema/legales');
    }

    /** @return array<string,int> tipo => versión vigente */
    private function currentMap(): array
    {
        $map = [];
        foreach (LegalDocument::TYPES as $type) {
            $map[$type] = LegalDocument::currentVersion($type);
        }
        return $map;
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Flash;
use App\Core\Request;
use App\Services\UpdateService;

/**
 * Actualizaciones OTA por Git (solo admin).
 */
final class SystemUpdateController extends Controller
{
    public function index(Request $request): never
    {
        $svc = new UpdateService();
        $check = $request->str('check') === '1' && $svc->available() ? $svc->check() : null;
        $this->view('system/updates/index', [
            'title' => __('updates.title'),
            'user' => Auth::user(),
            'available' => $svc->available(),
            'commit' => $svc->currentCommit(),
            'branch' => $svc->branch(),
            'check' => $check,
        ]);
    }

    public function run(Request $request): never
    {
        $result = (new UpdateService())->update((int) Auth::id());
        Flash::add($result['ok'] ? 'success' : 'error', $result['ok'] ? __('updates.updated') : __('updates.failed', ['error' => $result['message']]));
        $this->redirect('/gestion/sistema/actualizaciones');
    }
}

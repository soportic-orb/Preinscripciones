<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Flash;
use App\Core\Rbac;
use App\Core\Request;
use App\Models\MessageThread;
use App\Services\MessageService;

/**
 * Mensajería interna (estudiantes y personal comparten controlador; el acceso
 * se filtra por rol/propiedad del hilo).
 */
final class MessagesController extends Controller
{
    public function index(Request $request): never
    {
        $user = Auth::user();
        $svc = new MessageService();
        $threads = Rbac::isStaff($user) ? MessageThread::all() : MessageThread::forStudent($user->id);
        $this->view('messages/index', [
            'title' => __('messages.title'),
            'user' => $user,
            'isStaff' => Rbac::isStaff($user),
            'threads' => $threads,
            'service' => $svc,
        ]);
    }

    public function show(Request $request): never
    {
        $user = Auth::user();
        $thread = MessageThread::find((int) $request->route('id'));
        $svc = new MessageService();
        if ($thread === null || !$svc->canAccess($thread, $user)) {
            Flash::error(__('messages.not_found'));
            $this->redirect('/panel/mensajes');
        }
        $svc->markRead((int) $thread['id'], $user->id);
        $this->view('messages/thread', [
            'title' => $thread['subject'],
            'user' => $user,
            'isStaff' => Rbac::isStaff($user),
            'thread' => $thread,
            'messages' => MessageThread::messages((int) $thread['id']),
        ]);
    }

    public function start(Request $request): never
    {
        $user = Auth::user();
        $subject = $request->str('subject');
        $body = $request->str('body');
        if ($subject === '' || $body === '') {
            Flash::error(__('messages.fill_fields'));
            $this->redirect('/panel/mensajes');
        }
        $threadId = (new MessageService())->start($user, $subject, $body);
        Flash::success(__('messages.sent'));
        $this->redirect('/panel/mensajes/' . $threadId);
    }

    public function reply(Request $request): never
    {
        $user = Auth::user();
        $thread = MessageThread::find((int) $request->route('id'));
        $svc = new MessageService();
        if ($thread === null || !$svc->canAccess($thread, $user)) {
            Flash::error(__('messages.not_found'));
            $this->redirect('/panel/mensajes');
        }
        $body = $request->str('body');
        if ($body !== '') {
            $svc->post((int) $thread['id'], $user, $body);
            Flash::success(__('messages.sent'));
        }
        $this->redirect('/panel/mensajes/' . $thread['id']);
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Flash;
use App\Core\Rbac;
use App\Core\Request;
use App\Core\Response;
use App\Models\Certificate;
use App\Models\Preinscription;
use App\Services\CertificateService;

/**
 * Certificados/diplomas: emisión (staff), descarga (propietario/staff) y
 * verificación pública por código.
 */
final class CertificatesController extends Controller
{
    /** Verificación pública por código. */
    public function verify(Request $request): never
    {
        $code = strtoupper($request->str('code'));
        $cert = $code !== '' ? Certificate::findByCode($code) : null;
        $this->view('certificates/verify', [
            'title' => __('certificates.verify_title'),
            'cert' => $cert,
            'code' => $code,
        ]);
    }

    /** Emisión por el personal desde una preinscripción matriculada. */
    public function issue(Request $request): never
    {
        $preId = (int) $request->route('id');
        $id = (new CertificateService())->issue($preId, (int) Auth::id());
        if ($id === null) {
            Flash::error(__('certificates.not_eligible'));
        } else {
            Flash::success(__('certificates.issued'));
        }
        $this->redirect('/gestion/preinscripciones/' . $preId);
    }

    /** Descarga del certificado (propietario o staff). */
    public function download(Request $request): never
    {
        $cert = Certificate::find((int) $request->route('id'));
        if ($cert === null) {
            Response::html('<h1>404</h1>', 404);
        }
        $user = Auth::user();
        $isOwner = (int) $cert['user_id'] === (int) Auth::id();
        if (!$isOwner && !($user !== null && Rbac::isStaff($user))) {
            Response::html(\App\Core\View::render('errors/403', [], 'layouts/app'), 403);
        }
        if (empty($cert['pdf_path'])) {
            Flash::error(__('certificates.no_pdf'));
            $this->redirect('/panel');
        }
        $path = (new CertificateService())->absolutePath((string) $cert['pdf_path']);
        if (!is_file($path)) {
            Response::html('<h1>404</h1>', 404);
        }
        $isPdf = str_ends_with($path, '.pdf');
        header('Content-Type: ' . ($isPdf ? 'application/pdf' : 'text/html; charset=utf-8'));
        header('Content-Disposition: ' . ($isPdf ? 'attachment' : 'inline') . '; filename="' . basename($path) . '"');
        readfile($path);
        exit;
    }
}

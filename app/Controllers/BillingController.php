<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Core\Rbac;
use App\Models\BillingProfile;
use App\Models\Invoice;
use App\Services\InvoiceService;

/**
 * Datos fiscales del estudiante y descarga de facturas.
 */
final class BillingController extends Controller
{
    public function profile(Request $request): never
    {
        $this->view('billing/profile', [
            'title' => __('billing.title'),
            'user' => Auth::user(),
            'profile' => BillingProfile::forUser((int) Auth::id()),
            'invoices' => Invoice::forUser((int) Auth::id()),
        ]);
    }

    public function saveProfile(Request $request): never
    {
        $isCompany = $request->input('is_company') ? 1 : 0;
        BillingProfile::save((int) Auth::id(), [
            'is_company' => $isCompany,
            'name' => $request->str('name'),
            'tax_id' => $request->str('tax_id'),
            'address' => $request->str('address'),
            'city' => $request->str('city'),
            'postal_code' => $request->str('postal_code'),
            'country' => $request->str('country'),
            'email' => $request->str('email'),
        ]);
        Audit::log('billing.profile_saved', Auth::id(), 'user', Auth::id(), [], $request->ip());
        Flash::success(__('billing.saved'));
        $this->redirect('/panel/facturacion');
    }

    /** Descarga de una factura (propietario o staff). */
    public function download(Request $request): never
    {
        $invoice = Invoice::find((int) $request->route('id'));
        if ($invoice === null) {
            Response::html('<h1>404</h1>', 404);
        }
        $user = Auth::user();
        $isOwner = (int) $invoice['user_id'] === (int) Auth::id();
        $isStaff = $user !== null && Rbac::isStaff($user);
        if (!$isOwner && !$isStaff) {
            Response::html(\App\Core\View::render('errors/403', [], 'layouts/app'), 403);
        }
        if (empty($invoice['pdf_path'])) {
            Flash::error(__('billing.no_pdf'));
            $this->redirect('/panel/facturacion');
        }
        $path = (new InvoiceService())->absolutePath((string) $invoice['pdf_path']);
        if (!is_file($path)) {
            Response::html('<h1>404</h1>', 404);
        }
        Audit::log('invoice.download', Auth::id(), 'invoice', (int) $invoice['id'], []);
        $isPdf = str_ends_with($path, '.pdf');
        header('Content-Type: ' . ($isPdf ? 'application/pdf' : 'text/html; charset=utf-8'));
        header('Content-Disposition: ' . ($isPdf ? 'attachment' : 'inline') . '; filename="' . basename($path) . '"');
        readfile($path);
        exit;
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Flash;
use App\Core\Request;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\PaymentService;

/**
 * Gestión de pagos y facturas por el personal: validación de justificantes,
 * reembolsos y listado de facturas emitidas.
 */
final class ManagePaymentsController extends Controller
{
    public function index(Request $request): never
    {
        $this->view('management/payments/index', [
            'title' => __('payments.management'),
            'user' => Auth::user(),
            'review' => Payment::inReview(),
            'invoices' => Invoice::all(),
        ]);
    }

    /** Valida o rechaza un justificante de transferencia/Bizum. */
    public function validateProof(Request $request): never
    {
        $id = (int) $request->route('id');
        $approve = $request->str('decision') === 'approve';
        (new PaymentService())->validateProof($id, $approve, (int) Auth::id());
        Flash::success($approve ? __('payments.payment_validated') : __('payments.payment_rejected'));
        $this->redirect('/gestion/pagos');
    }

    /** Reembolso total o parcial. */
    public function refund(Request $request): never
    {
        $id = (int) $request->route('id');
        $payment = Payment::find($id);
        if ($payment === null) {
            $this->redirect('/gestion/pagos');
        }
        $amount = (float) $request->input('amount', Payment::netAmount($payment));
        (new PaymentService())->refund($id, $amount, $request->str('reason'), (int) Auth::id());
        Flash::success(__('payments.refunded'));
        $this->redirect('/gestion/pagos');
    }
}

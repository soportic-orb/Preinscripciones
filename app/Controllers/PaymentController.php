<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Settings;
use App\Models\CourseEdition;
use App\Models\Payment;
use App\Models\Preinscription;
use App\Services\DocumentService;
use App\Services\PaymentService;
use App\Services\StripeGateway;

/**
 * Pagos del estudiante: calendario de cobros, descuentos, Stripe, Bizum y
 * transferencia (con justificante), y datos FUNDAE.
 */
final class PaymentController extends Controller
{
    public function show(Request $request): never
    {
        $pre = $this->loadOwned($request);
        $edition = CourseEdition::findWithCourse((int) $pre['edition_id']);
        $payments = (new PaymentService())->ensurePayments((int) $pre['id']);

        $this->view('payment/index', [
            'title' => __('payments.title'),
            'pre' => $pre,
            'edition' => $edition,
            'payments' => $payments,
            'stripeEnabled' => StripeGateway::isConfigured(),
            'bank' => Settings::get('billing', 'iban', ''),
            'bizum' => Settings::get('billing', 'bizum_number', ''),
            'payMethods' => Settings::group('payments'),
            'fundae' => Database::instance()->fetch('SELECT * FROM {fundae_records} WHERE preinscription_id = ? LIMIT 1', [$pre['id']]),
        ]);
    }

    public function applyDiscount(Request $request): never
    {
        $pre = $this->loadOwned($request);
        $result = (new PaymentService())->applyDiscountCode((int) $pre['id'], $request->str('code'));
        Flash::add($result['ok'] ? 'success' : 'error', $result['message']);
        $this->redirect('/panel/preinscripcion/' . $pre['id'] . '/pago');
    }

    /** Pago con tarjeta (Stripe). En modo simulado marca pagado directamente. */
    public function payStripe(Request $request): never
    {
        $pre = $this->loadOwned($request);
        $payment = $this->loadPendingPayment($request, (int) $pre['id']);
        $gateway = new StripeGateway();
        $base = rtrim((string) (\App\Core\Config::app()['url'] ?: ''), '/');
        $success = ($base ?: '') . '/panel/preinscripcion/' . $pre['id'] . '/pago?stripe=ok';
        $cancel = ($base ?: '') . '/panel/preinscripcion/' . $pre['id'] . '/pago?stripe=cancel';

        $url = $gateway->createCheckout($payment, $success ?: '/', $cancel ?: '/', __('payments.concept_' . $payment['concept']));
        if ($url !== null) {
            header('Location: ' . $url);
            exit;
        }
        // Modo simulado (sin claves): marcar pagado y avisar.
        (new PaymentService())->markPaid((int) $payment['id'], 'stripe', null, 'SIMULATED');
        Flash::success(__('payments.simulated_paid'));
        $this->redirect('/panel/preinscripcion/' . $pre['id'] . '/pago');
    }

    /** Transferencia o Bizum: subir justificante. */
    public function submitProof(Request $request): never
    {
        $pre = $this->loadOwned($request);
        $payment = $this->loadPendingPayment($request, (int) $pre['id']);
        $method = $request->str('method') === 'bizum' ? 'bizum' : 'transfer';

        $result = (new DocumentService())->upload($request->files['proof'] ?? [], (int) $pre['id'], null);
        if (!$result['ok']) {
            Flash::error($result['error'] ?? __('documents.upload_error'));
            $this->redirect('/panel/preinscripcion/' . $pre['id'] . '/pago');
        }
        $doc = Database::instance()->fetch('SELECT file_path FROM {preinscription_documents} WHERE id = ?', [$result['id']]);
        (new PaymentService())->submitProof((int) $payment['id'], $method, (string) $doc['file_path'], $request->str('reference'));
        Flash::success(__('payments.proof_submitted'));
        $this->redirect('/panel/preinscripcion/' . $pre['id'] . '/pago');
    }

    /** Guarda los datos FUNDAE (formación bonificada). */
    public function saveFundae(Request $request): never
    {
        $pre = $this->loadOwned($request);
        $db = Database::instance();
        $db->run('DELETE FROM {fundae_records} WHERE preinscription_id = ?', [$pre['id']]);
        $db->insert('fundae_records', [
            'preinscription_id' => (int) $pre['id'],
            'company_name' => $request->str('company_name'),
            'company_cif' => $request->str('company_cif'),
            'contribution_account' => $request->str('contribution_account'),
            'worker_name' => $request->str('worker_name'),
            'worker_nif' => $request->str('worker_nif'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        Audit::log('fundae.saved', Auth::id(), 'preinscription', (int) $pre['id'], [], $request->ip());
        Flash::success(__('payments.fundae_saved'));
        $this->redirect('/panel/preinscripcion/' . $pre['id'] . '/pago');
    }

    // ------------------------------------------------------------- helpers
    private function loadOwned(Request $request): array
    {
        $pre = Preinscription::find((int) $request->route('id'));
        if ($pre === null || (int) $pre['user_id'] !== (int) Auth::id()) {
            Flash::error(__('preinscription.not_found'));
            $this->redirect('/panel');
        }
        return $pre;
    }

    private function loadPendingPayment(Request $request, int $preId): array
    {
        $payment = Payment::find((int) $request->input('payment_id'));
        if ($payment === null || (int) $payment['preinscription_id'] !== $preId
            || !in_array($payment['status'], [Payment::STATUS_PENDING, Payment::STATUS_REJECTED], true)) {
            Flash::error(__('payments.invalid_payment'));
            $this->redirect('/panel/preinscripcion/' . $preId . '/pago');
        }
        return $payment;
    }
}

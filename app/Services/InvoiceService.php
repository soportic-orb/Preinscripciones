<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Database;
use App\Core\Settings;
use App\Models\BillingProfile;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Preinscription;

/**
 * Emisión de facturas/recibos y abonos en PDF con numeración correlativa.
 *
 * El IVA o su exención (habitual en formación reglada) se configuran en
 * Ajustes → Facturación (grupo settings 'billing'). Los PDF se guardan fuera
 * del webroot y se sirven con control de acceso.
 */
final class InvoiceService
{
    /** Emite la factura correspondiente a un pago ya cobrado. */
    public function issueForPayment(array $payment): ?int
    {
        // Evitar duplicar factura para el mismo pago.
        $exists = Database::instance()->scalar(
            "SELECT id FROM {invoices} WHERE payment_id = ? AND type = 'invoice'",
            [(int) $payment['id']],
        );
        if ($exists) {
            return (int) $exists;
        }

        $series = Settings::get('billing', 'invoice_series', 'A') ?: 'A';
        $exempt = Settings::bool('billing', 'vat_exempt', true);
        $taxRate = $exempt ? 0.0 : (float) (Settings::get('billing', 'tax_rate', '21') ?? 21);

        $net = Payment::netAmount($payment);
        // El importe pagado se considera con impuestos incluidos.
        $subtotal = $taxRate > 0 ? round($net / (1 + $taxRate / 100), 2) : $net;
        $taxAmount = round($net - $subtotal, 2);

        $billing = BillingProfile::forUser((int) $payment['user_id']);
        $number = Invoice::nextNumber($series, Invoice::TYPE_INVOICE);
        $full = sprintf('%s-%s-%04d', $series, date('Y'), $number);

        $invoiceId = Invoice::store([
            'series' => $series,
            'number' => $number,
            'full_number' => $full,
            'type' => Invoice::TYPE_INVOICE,
            'payment_id' => (int) $payment['id'],
            'preinscription_id' => (int) $payment['preinscription_id'],
            'user_id' => (int) $payment['user_id'],
            'billing_snapshot' => json_encode($billing ?: [], JSON_UNESCAPED_UNICODE),
            'subtotal' => $subtotal,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total' => $net,
            'is_exempt' => $exempt ? 1 : 0,
            'status' => 'issued',
            'issued_at' => date('Y-m-d H:i:s'),
        ]);

        $invoice = Invoice::find($invoiceId);
        $pdfPath = $this->renderPdf($invoice, $payment, $billing);
        if ($pdfPath !== null) {
            Database::instance()->update('invoices', ['pdf_path' => $pdfPath], ['id' => $invoiceId]);
        }
        Audit::log('invoice.issued', null, 'invoice', $invoiceId, ['number' => $full]);
        return $invoiceId;
    }

    /** Emite una nota de crédito (abono) por un reembolso. */
    public function issueCreditNote(array $payment, float $amount): int
    {
        $series = Settings::get('billing', 'invoice_series', 'A') ?: 'A';
        $number = Invoice::nextNumber($series, Invoice::TYPE_CREDIT);
        $full = sprintf('%s-A%s-%04d', $series, date('Y'), $number);
        $billing = BillingProfile::forUser((int) $payment['user_id']);

        $invoiceId = Invoice::store([
            'series' => $series,
            'number' => $number,
            'full_number' => $full,
            'type' => Invoice::TYPE_CREDIT,
            'payment_id' => (int) $payment['id'],
            'preinscription_id' => (int) $payment['preinscription_id'],
            'user_id' => (int) $payment['user_id'],
            'billing_snapshot' => json_encode($billing ?: [], JSON_UNESCAPED_UNICODE),
            'subtotal' => -$amount,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'total' => -$amount,
            'is_exempt' => Settings::bool('billing', 'vat_exempt', true) ? 1 : 0,
            'status' => 'issued',
            'issued_at' => date('Y-m-d H:i:s'),
        ]);
        $invoice = Invoice::find($invoiceId);
        $pdfPath = $this->renderPdf($invoice, $payment, $billing, true);
        if ($pdfPath !== null) {
            Database::instance()->update('invoices', ['pdf_path' => $pdfPath], ['id' => $invoiceId]);
        }
        Audit::log('invoice.credit_note', null, 'invoice', $invoiceId, ['number' => $full]);
        return $invoiceId;
    }

    /** Genera el PDF (Dompdf si está disponible; si no, guarda HTML imprimible). */
    private function renderPdf(array $invoice, array $payment, ?array $billing, bool $credit = false): ?string
    {
        $html = $this->html($invoice, $payment, $billing, $credit);
        $dir = STORAGE_PATH . '/uploads/invoices';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        if (class_exists(\Dompdf\Dompdf::class)) {
            try {
                $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false]);
                $dompdf->loadHtml($html, 'UTF-8');
                $dompdf->setPaper('A4');
                $dompdf->render();
                $file = $dir . '/' . $invoice['full_number'] . '.pdf';
                file_put_contents($file, $dompdf->output());
                return 'invoices/' . $invoice['full_number'] . '.pdf';
            } catch (\Throwable $e) {
                \App\Core\Logger::error('PDF factura: ' . $e->getMessage());
            }
        }
        // Fallback: HTML imprimible.
        $file = $dir . '/' . $invoice['full_number'] . '.html';
        file_put_contents($file, $html);
        return 'invoices/' . $invoice['full_number'] . '.html';
    }

    private function html(array $invoice, array $payment, ?array $billing, bool $credit): string
    {
        $academy = [
            'name' => Settings::get('billing', 'academy_name', 'Institut d\'Estudis Mèdics'),
            'taxid' => Settings::get('billing', 'academy_taxid', ''),
            'address' => Settings::get('billing', 'academy_address', ''),
        ];
        $title = $credit ? __('invoices.credit_note') : __('invoices.invoice');
        $b = $billing ?? [];
        $money = static fn ($n) => number_format((float) $n, 2, ',', '.') . ' €';
        $accent = '#A11600';
        $rows = '';
        $rows .= '<tr><td>' . e(__('invoices.concept_line', ['concept' => $payment['concept']])) . '</td><td style="text-align:right">' . $money($invoice['subtotal']) . '</td></tr>';

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'
            . 'body{font-family:Helvetica,Arial,sans-serif;color:#333;font-size:12px}'
            . 'h1{color:' . $accent . ';font-size:20px;margin:0}'
            . '.muted{color:#888}.box{border:1px solid #ddd;padding:10px;border-radius:4px}'
            . 'table{width:100%;border-collapse:collapse;margin-top:16px}'
            . 'th,td{padding:8px;border-bottom:1px solid #eee;text-align:left}'
            . '.totals td{border:none}'
            . '</style></head><body>'
            . '<table style="border:none"><tr style="border:none"><td style="border:none">'
            . '<h1>' . e((string) $academy['name']) . '</h1>'
            . '<div class="muted">' . e((string) $academy['taxid']) . '<br>' . nl2br(e((string) $academy['address'])) . '</div>'
            . '</td><td style="border:none;text-align:right">'
            . '<h2>' . e($title) . '</h2>'
            . '<strong>' . e((string) $invoice['full_number']) . '</strong><br>'
            . '<span class="muted">' . e(substr((string) $invoice['issued_at'], 0, 10)) . '</span>'
            . '</td></tr></table>'
            . '<div class="box" style="margin-top:16px"><strong>' . e(__('invoices.bill_to')) . ':</strong><br>'
            . e((string) ($b['name'] ?? '')) . '<br>'
            . e((string) ($b['tax_id'] ?? '')) . '<br>'
            . e((string) ($b['address'] ?? '')) . ' ' . e((string) ($b['city'] ?? '')) . ' ' . e((string) ($b['postal_code'] ?? ''))
            . '</div>'
            . '<table><thead><tr><th>' . e(__('invoices.description')) . '</th><th style="text-align:right">' . e(__('invoices.amount')) . '</th></tr></thead>'
            . '<tbody>' . $rows . '</tbody></table>'
            . '<table class="totals" style="width:300px;margin-left:auto;margin-top:8px">'
            . '<tr><td>' . e(__('invoices.subtotal')) . '</td><td style="text-align:right">' . $money($invoice['subtotal']) . '</td></tr>'
            . ((int) $invoice['is_exempt'] === 1
                ? '<tr><td>' . e(__('invoices.vat_exempt')) . '</td><td style="text-align:right">—</td></tr>'
                : '<tr><td>IVA (' . e((string) (float) $invoice['tax_rate']) . '%)</td><td style="text-align:right">' . $money($invoice['tax_amount']) . '</td></tr>')
            . '<tr><td><strong>' . e(__('invoices.total')) . '</strong></td><td style="text-align:right"><strong>' . $money($invoice['total']) . '</strong></td></tr>'
            . '</table>'
            . '</body></html>';
    }

    public function absolutePath(string $relative): string
    {
        return STORAGE_PATH . '/uploads/' . $relative;
    }
}

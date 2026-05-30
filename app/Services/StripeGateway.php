<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use App\Core\Logger;
use App\Models\Payment;

/**
 * Integración con Stripe (tarjeta) mediante Checkout.
 *
 * Si hay claves configuradas y el SDK está disponible, crea una sesión de
 * Checkout real y confirma por webhook. Si no, opera en modo simulado para
 * desarrollo/demostración (claramente indicado en la UI).
 */
final class StripeGateway
{
    public static function isConfigured(): bool
    {
        return class_exists(\Stripe\StripeClient::class)
            && (string) Env::get('STRIPE_SECRET_KEY', '') !== '';
    }

    /**
     * Crea una sesión de Checkout y devuelve su URL, o null si no es posible
     * (modo simulado).
     */
    public function createCheckout(array $payment, string $successUrl, string $cancelUrl, string $description): ?string
    {
        if (!self::isConfigured()) {
            return null;
        }
        try {
            $client = new \Stripe\StripeClient((string) Env::get('STRIPE_SECRET_KEY'));
            $session = $client->checkout->sessions->create([
                'mode' => 'payment',
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'client_reference_id' => (string) $payment['id'],
                'metadata' => ['payment_id' => (string) $payment['id']],
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => strtolower((string) ($payment['currency'] ?? 'eur')),
                        'unit_amount' => (int) round(Payment::netAmount($payment) * 100),
                        'product_data' => ['name' => $description],
                    ],
                ]],
            ]);
            Payment::update((int) $payment['id'], ['stripe_intent' => (string) $session->id, 'method' => 'stripe']);
            return $session->url;
        } catch (\Throwable $e) {
            Logger::error('Stripe checkout: ' . $e->getMessage(), [], 'payments');
            return null;
        }
    }

    /**
     * Procesa el webhook de Stripe. Devuelve true si se manejó correctamente.
     */
    public function handleWebhook(string $payload, ?string $signature): bool
    {
        $secret = (string) Env::get('STRIPE_WEBHOOK_SECRET', '');
        try {
            if ($secret !== '' && class_exists(\Stripe\Webhook::class) && $signature !== null) {
                $event = \Stripe\Webhook::constructEvent($payload, $signature, $secret);
                $type = $event->type;
                $object = $event->data->object;
            } else {
                // Sin secreto: aceptar payload JSON (solo recomendable en desarrollo).
                $decoded = json_decode($payload, true);
                $type = $decoded['type'] ?? '';
                $object = (object) ($decoded['data']['object'] ?? []);
            }
        } catch (\Throwable $e) {
            Logger::error('Stripe webhook inválido: ' . $e->getMessage(), [], 'payments');
            return false;
        }

        if ($type === 'checkout.session.completed') {
            $paymentId = (int) ($object->metadata->payment_id ?? $object->client_reference_id ?? 0);
            if ($paymentId > 0) {
                (new PaymentService())->markPaid($paymentId, 'stripe', null, (string) ($object->id ?? ''));
                return true;
            }
        }
        return true; // otros eventos: aceptar sin acción
    }
}

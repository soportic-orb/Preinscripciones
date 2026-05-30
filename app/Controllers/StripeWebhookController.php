<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\StripeGateway;

/**
 * Endpoint público de webhooks de Stripe (sin sesión ni CSRF; se verifica la
 * firma con el secreto del webhook).
 */
final class StripeWebhookController extends Controller
{
    public function handle(Request $request): never
    {
        $payload = file_get_contents('php://input') ?: '';
        $signature = $request->server['HTTP_STRIPE_SIGNATURE'] ?? null;
        $ok = (new StripeGateway())->handleWebhook($payload, is_string($signature) ? $signature : null);
        Response::json(['received' => $ok], $ok ? 200 : 400);
    }
}

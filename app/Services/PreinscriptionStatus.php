<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Estados de la preinscripción y transiciones permitidas (máquina de estados).
 *
 * Flujo (Bloque B): borrador → preinscrito → documentacion_en_revision →
 * aceptado/rechazado/en_lista_de_espera. Los estados de pago/matrícula
 * (pendiente_pago, pago_en_revision, matriculado) se gestionan en el Bloque C.
 */
final class PreinscriptionStatus
{
    public const BORRADOR = 'borrador';
    public const PREINSCRITO = 'preinscrito';
    public const DOC_EN_REVISION = 'documentacion_en_revision';
    public const ACEPTADO = 'aceptado';
    public const RECHAZADO = 'rechazado';
    public const EN_LISTA_ESPERA = 'en_lista_de_espera';
    public const PENDIENTE_PAGO = 'pendiente_pago';
    public const PAGO_EN_REVISION = 'pago_en_revision';
    public const MATRICULADO = 'matriculado';
    public const CANCELADO = 'cancelado';

    /** @return array<int,string> */
    public static function all(): array
    {
        return [
            self::BORRADOR, self::PREINSCRITO, self::DOC_EN_REVISION, self::ACEPTADO,
            self::RECHAZADO, self::EN_LISTA_ESPERA, self::PENDIENTE_PAGO, self::PAGO_EN_REVISION,
            self::MATRICULADO, self::CANCELADO,
        ];
    }

    /** Transiciones permitidas: estado => [estados destino]. */
    public static function transitions(): array
    {
        return [
            self::BORRADOR => [self::PREINSCRITO, self::CANCELADO],
            self::PREINSCRITO => [self::DOC_EN_REVISION, self::ACEPTADO, self::RECHAZADO, self::EN_LISTA_ESPERA, self::CANCELADO],
            self::DOC_EN_REVISION => [self::ACEPTADO, self::RECHAZADO, self::EN_LISTA_ESPERA, self::PREINSCRITO, self::CANCELADO],
            self::EN_LISTA_ESPERA => [self::ACEPTADO, self::DOC_EN_REVISION, self::RECHAZADO, self::CANCELADO],
            self::ACEPTADO => [self::PENDIENTE_PAGO, self::CANCELADO],
            self::PENDIENTE_PAGO => [self::PAGO_EN_REVISION, self::MATRICULADO, self::CANCELADO],
            self::PAGO_EN_REVISION => [self::MATRICULADO, self::PENDIENTE_PAGO, self::CANCELADO],
            self::MATRICULADO => [],
            self::RECHAZADO => [],
            self::CANCELADO => [],
        ];
    }

    public static function canTransition(string $from, string $to): bool
    {
        $map = self::transitions();
        return in_array($to, $map[$from] ?? [], true);
    }

    /** Estados considerados "activos" (no terminales negativos). */
    public static function isOpen(string $status): bool
    {
        return !in_array($status, [self::RECHAZADO, self::CANCELADO], true);
    }

    public static function label(string $status): string
    {
        return __('states.' . $status);
    }
}

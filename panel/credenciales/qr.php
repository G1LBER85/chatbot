<?php
// ═══════════════════════════════════════════════════════════════
// panel/credenciales/qr.php
// ─────────────────────────────────────────────────────────────
// Todo lo relacionado a generar el código QR de una credencial.
// ═══════════════════════════════════════════════════════════════

require_once __DIR__ . '/../../vendor/autoload.php';

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

/**
 * Genera el código QR (PNG, como data URI) para un texto dado.
 * El QR contiene la CURP tal cual, que es el mismo valor que
 * cliente.html envía a api/registrar.php al escanear, así la
 * credencial funciona directo con el sistema de asistencia.
 */
function generarQrDataUri(string $texto): string
{
    $builder = new Builder(
        writer: new PngWriter(),
        data: $texto,
        size: 300,
        margin: 8
    );

    $resultado = $builder->build();

    return $resultado->getDataUri();
}
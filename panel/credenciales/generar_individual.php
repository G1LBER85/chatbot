<?php
// ═══════════════════════════════════════════════════════════════
// panel/credenciales/generar_individual.php
// ─────────────────────────────────────────────────────────────
// Genera el PDF de UN solo alumno, en una página del tamaño
// exacto de una credencial física (sin márgenes), lista para
// imprimir directo sobre una tarjeta pre-cortada.
//
// Se llama así desde panel/credenciales.php:
//   credenciales/generar_individual.php?id=123
// ═══════════════════════════════════════════════════════════════

require '../../conexion.php';
require __DIR__ . '/funciones.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$id = intval($_GET['id'] ?? 0);

// ── Buscar al alumno ──
$stmt = $conn->prepare("SELECT id, nombre, grado, grupo, CURP, foto FROM alumnos WHERE id = ? AND activo = 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$alumno = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$alumno) {
    die('Alumno no encontrado o inactivo.');
}

if (!$alumno['CURP']) {
    die('Este alumno no tiene CURP capturada; agrégala primero en "Registrar / editar" antes de generar su credencial.');
}

// ── Armar el HTML de la tarjeta ──
$logoDataUri = imagenADataUri(RUTA_LOGO);

// El reverso va en una SEGUNDA PÁGINA del mismo tamaño (CR80). El
// salto de página se pone en el SEGUNDO bloque (page-break-before),
// no en el primero (page-break-after) — ponerlo en el primer
// elemento del documento es justo lo que le agregaba una página en
// blanco extra al principio, un bug conocido de Dompdf.
$html = '<html><head><style>' . cssCredencial() . '</style></head><body>'
    . construirHtmlCredencialFrente($alumno, $logoDataUri)
    . '<div style="page-break-before: always;">' . construirHtmlCredencialReverso($alumno, $logoDataUri) . '</div>'
    . '</body></html>';

// ── Generar el PDF ──
$options = new Options();
$options->set('isRemoteEnabled', false);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);

// Página exactamente del tamaño de la tarjeta, sin márgenes,
// para imprimir directo sobre credenciales físicas pre-cortadas.
$dompdf->setPaper([0, 0, ANCHO_TARJETA_MM * MM_A_PT, ALTO_TARJETA_MM * MM_A_PT]);
$dompdf->render();
$dompdf->stream(
    'credencial_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $alumno['nombre']) . '.pdf',
    ['Attachment' => false]
);
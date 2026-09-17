<?php
// ═══════════════════════════════════════════════════════════════
// panel/credenciales/pdf.php
// ─────────────────────────────────────────────────────────────
// Todo lo relacionado a convertir la plantilla (HTML) en un PDF de
// verdad con Dompdf.
// ═══════════════════════════════════════════════════════════════

require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Cuántos alumnos (cada uno con su fila de frente+reverso) caben
// cómodamente en una hoja carta antes de pasar a la siguiente.
const ALUMNOS_POR_HOJA = 4;

/**
 * Lee panel/credenciales/proceso_de_credenciales/estilos.css del
 * disco. Dompdf no puede cargar un CSS externo con un <link> tal
 * cual (isRemoteEnabled está apagado más abajo, a propósito, por
 * seguridad), así que se lee su contenido y se mete dentro de un
 * <style> al armar el HTML.
 */
function cssDeLaCredencial(): string
{
    return file_get_contents(__DIR__ . '/proceso_de_credenciales/estilos.css');
}

/**
 * Genera el PDF completo (todas las hojas necesarias) de UN grupo
 * de alumnos, y devuelve los bytes del PDF ya armado.
 */
function generarPdfDeGrupo(array $alumnosDelArchivo, ?string $logoDataUri): string
{
    $hojasDeAlumnos = array_chunk($alumnosDelArchivo, ALUMNOS_POR_HOJA);

    $paginasHtml = '';
    foreach ($hojasDeAlumnos as $indice => $alumnosDeEstaHoja) {
        // page-break-before, y SOLO a partir de la segunda hoja:
        // ponerlo en la primerísima página del documento es lo que
        // agrega una página en blanco extra al principio (bug
        // conocido de Dompdf).
        $estilo = $indice === 0 ? '' : ' style="page-break-before: always;"';
        $paginasHtml .= '<div class="hoja"' . $estilo . '>'
            . construirHojaConParejas($alumnosDeEstaHoja, $logoDataUri)
            . '</div>';
    }

    $html = '<html><head><style>' . cssDeLaCredencial() . '</style></head><body>'
        . $paginasHtml . '</body></html>';

    $options = new Options();
    $options->set('isRemoteEnabled', false);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('letter', 'portrait');
    $dompdf->render();

    return $dompdf->output();
}

/**
 * Empaqueta varios PDFs (uno por grado+grupo) en un solo archivo
 * ZIP y lo entrega al navegador. Se usa un archivo temporal en
 * disco porque ZipArchive no puede escribir directo a "memoria"
 * (php://memory) — necesita una ruta real de archivo.
 */
function entregarComoZip(array $pdfsGenerados, string $nombreZip): void
{
    $rutaZipTemporal = tempnam(sys_get_temp_dir(), 'credenciales_') . '.zip';

    $zip = new ZipArchive();
    $zip->open($rutaZipTemporal, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    foreach ($pdfsGenerados as $nombreArchivo => $bytesPdf) {
        $zip->addFromString($nombreArchivo, $bytesPdf);
    }

    $zip->close();

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $nombreZip . '"');
    header('Content-Length: ' . filesize($rutaZipTemporal));
    readfile($rutaZipTemporal);
    unlink($rutaZipTemporal);
}

/**
 * Entrega UN solo PDF directo al navegador (sin ZIP).
 */
function entregarPdfDirecto(string $nombreArchivo, string $bytesPdf): void
{
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $nombreArchivo . '"');
    header('Content-Length: ' . strlen($bytesPdf));
    echo $bytesPdf;
}
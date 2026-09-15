<?php
// ═══════════════════════════════════════════════════════════════
// panel/credenciales/generar_lote.php
// ─────────────────────────────────────────────────────────────
// Genera las credenciales de todos los alumnos marcados en
// panel/credenciales.php. Cada FILA de la hoja muestra el FRENTE y
// el REVERSO del MISMO alumno, uno junto al otro — así no hay que
// andar buscando cuál QR (reverso) le corresponde a cuál nombre
// (frente): están pegados en la misma fila.
//
// (Nota: esto ya no es "impresión dúplex" — frente y reverso salen
// del mismo lado de la hoja, uno al lado del otro. Para armar la
// credencial física, se recortan ambos rectángulos y se pegan
// espalda con espalda o se laminan juntos.)
//
// Se sigue agrupando por grado+grupo (cada grado+grupo en su propio
// PDF) para que la memoria no se dispare con lotes grandes y para
// que los archivos salgan ya ordenados; si resulta más de un
// archivo, se entregan en un ZIP.
// ═══════════════════════════════════════════════════════════════

require '../../conexion.php';
require __DIR__ . '/funciones.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Cuántos alumnos (cada uno con su fila de frente+reverso) caben
// cómodamente en una hoja carta antes de pasar a la siguiente.
const ALUMNOS_POR_HOJA = 4;

// Tope de seguridad por ARCHIVO (no por hoja): aunque agrupemos por
// grado/grupo, un grupo inusualmente grande igual podría acumular
// demasiadas hojas en un solo PDF. Ajustable si hace falta.
const MAX_ALUMNOS_POR_ARCHIVO = 60;

ini_set('memory_limit', '1024M');
set_time_limit(180);

$ids = array_map('intval', $_POST['ids'] ?? []);

if (empty($ids)) {
    die('No seleccionaste ningún alumno. Regresa y marca al menos uno.');
}

// ── Buscar a los alumnos seleccionados ──
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$tipos = str_repeat('i', count($ids));

$stmt = $conn->prepare("
    SELECT id, nombre, grado, grupo, CURP, foto
    FROM alumnos
    WHERE id IN ($placeholders) AND activo = 1 AND CURP IS NOT NULL AND CURP != ''
    ORDER BY grado DESC, grupo ASC, nombre ASC
");
$stmt->bind_param($tipos, ...$ids);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows === 0) {
    die('Ninguno de los alumnos seleccionados tiene CURP capturada; agrégala primero para poder generar sus credenciales.');
}

// ── 1. Agrupar por grado+grupo ──────────────────────────────────
$gruposDeAlumnos = [];
while ($alumno = $resultado->fetch_assoc()) {
    $clave = $alumno['grado'] . ($alumno['grupo'] ?: 'SinGrupo');
    $gruposDeAlumnos[$clave][] = $alumno;
}
$stmt->close();

$logoDataUri = imagenADataUri(RUTA_LOGO);

// ─────────────────────────────────────────────────────────────
// [FUNCIONES DE CUADRÍCULA]
// ─────────────────────────────────────────────────────────────

/**
 * Arma el HTML de UNA hoja: una fila por alumno, con su frente y su
 * reverso en las dos columnas de esa fila.
 */
function construirHojaConParejas(array $alumnosDeLaHoja, ?string $logoDataUri): string
{
    $html = '<div class="hoja-grid">';

    foreach ($alumnosDeLaHoja as $alumno) {
        $html .= '<div class="hoja-fila">';
        $html .= '<div class="hoja-celda">' . construirHtmlCredencialFrente($alumno, $logoDataUri) . '</div>';
        $html .= '<div class="hoja-celda">' . construirHtmlCredencialReverso($alumno, $logoDataUri) . '</div>';
        $html .= '</div>';
    }

    $html .= '</div>';
    return $html;
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

    $html = '<html><head><style>'
        . cssCredencial()
        . cssGridHoja()
        . '</style></head><body>' . $paginasHtml . '</body></html>';

    $options = new Options();
    $options->set('isRemoteEnabled', false);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('letter', 'portrait');
    $dompdf->render();

    return $dompdf->output();
}

// ── 2. Armar la lista de archivos a generar ──────────────────────
// [nombreArchivo => array de alumnos de ese archivo]. Si un
// grado+grupo excede MAX_ALUMNOS_POR_ARCHIVO, se parte en "_parteN".
$archivos = [];
foreach ($gruposDeAlumnos as $clave => $alumnosDelGrupo) {
    $bloques = array_chunk($alumnosDelGrupo, MAX_ALUMNOS_POR_ARCHIVO);

    if (count($bloques) === 1) {
        $archivos[$clave . '.pdf'] = $bloques[0];
    } else {
        foreach ($bloques as $indice => $bloque) {
            $archivos[$clave . '_parte' . ($indice + 1) . '.pdf'] = $bloque;
        }
    }
}

// ── 3. Generar cada PDF ──────────────────────────────────────────
// Se genera un archivo a la vez y se libera memoria entre cada uno
// para que un lote con muchos grupos no vaya acumulando memoria de
// los PDFs anteriores.
$pdfsGenerados = []; // [nombreArchivo => bytes del PDF]
foreach ($archivos as $nombreArchivo => $alumnosDelArchivo) {
    $pdfsGenerados[$nombreArchivo] = generarPdfDeGrupo($alumnosDelArchivo, $logoDataUri);
    gc_collect_cycles();
}

// ── 4. Entregar el resultado ──────────────────────────────────────
if (count($pdfsGenerados) === 1) {
    // Un solo archivo: se entrega el PDF directo, sin ZIP.
    $nombreArchivo = array_key_first($pdfsGenerados);
    $bytesPdf = $pdfsGenerados[$nombreArchivo];

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $nombreArchivo . '"');
    header('Content-Length: ' . strlen($bytesPdf));
    echo $bytesPdf;
    exit;
}

// Más de un archivo: se empaquetan en un ZIP. Se usa un archivo
// temporal en disco porque ZipArchive no puede escribir directo a
// "memoria" (php://memory) — necesita una ruta real de archivo.
$rutaZipTemporal = tempnam(sys_get_temp_dir(), 'credenciales_') . '.zip';

$zip = new ZipArchive();
$zip->open($rutaZipTemporal, ZipArchive::CREATE | ZipArchive::OVERWRITE);

foreach ($pdfsGenerados as $nombreArchivo => $bytesPdf) {
    $zip->addFromString($nombreArchivo, $bytesPdf);
}

$zip->close();

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="credenciales_por_grupo.zip"');
header('Content-Length: ' . filesize($rutaZipTemporal));
readfile($rutaZipTemporal);
unlink($rutaZipTemporal);
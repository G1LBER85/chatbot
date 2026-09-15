<?php
// ═══════════════════════════════════════════════════════════════
// panel/credenciales/funciones.php
// ─────────────────────────────────────────────────────────────
// Todo lo que NO depende de si la credencial es individual o por
// lote vive aquí: configuración, helpers de imagen/QR, la
// plantilla HTML de una tarjeta y su CSS. Tanto generar_individual.php
// como generar_lote.php incluyen este archivo para no repetir código.
// ═══════════════════════════════════════════════════════════════

require_once __DIR__ . '/../../vendor/autoload.php';

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

// ─────────────────────────────────────────────────────────────
// [CONFIGURACIÓN] — edita aquí si cambia el nombre de la escuela,
// el logo, o el tamaño físico de la credencial.
// ─────────────────────────────────────────────────────────────
const NOMBRE_ESCUELA = 'Preparatoria Número 3';
const RUTA_LOGO = __DIR__ . '/../../img/logo_chiapas.png'; // se usa si existe; si no, se omite sin error

// Tamaño estándar de credencial tipo tarjeta (CR80), en milímetros.
const ANCHO_TARJETA_MM = 85.6;
const ALTO_TARJETA_MM = 54.0;
const MM_A_PT = 2.83465; // 1 mm en puntos, unidad que usa Dompdf

// ─────────────────────────────────────────────────────────────
// [HELPERS DE IMAGEN] — convertir archivos del disco a data URI
// para incrustarlos directo en el HTML que lee Dompdf.
// ─────────────────────────────────────────────────────────────

/**
 * Convierte una imagen del disco a un data URI base64 TAL CUAL,
 * sin redimensionar. Se usa para el logo de la escuela, que ya es
 * chico de por sí. Para fotos de alumnos usa fotoRedimensionadaDataUri()
 * en su lugar (ver abajo) — esa sí redimensiona.
 * Devuelve null si el archivo no existe o no se puede leer.
 */
function imagenADataUri(string $rutaAbsoluta): ?string
{
    if (!$rutaAbsoluta || !is_file($rutaAbsoluta)) {
        return null;
    }

    $tipo = mime_content_type($rutaAbsoluta) ?: 'image/png';
    $contenido = file_get_contents($rutaAbsoluta);

    if ($contenido === false) {
        return null;
    }

    return 'data:' . $tipo . ';base64,' . base64_encode($contenido);
}

/**
 * Redimensiona y comprime una foto antes de incrustarla en el PDF.
 * Las fotos originales suelen ser mucho más grandes de lo que una
 * credencial necesita (16x20mm impresos); incrustarlas a tamaño
 * completo es lo que agota la memoria de PHP cuando se generan
 * muchas credenciales a la vez. Aquí se reduce a un tamaño de
 * píxeles suficiente para verse nítida impresa, con recorte tipo
 * "cover" (llena el rectángulo sin deformar la imagen).
 * Devuelve null si la imagen no existe o no se puede procesar.
 */
function fotoRedimensionadaDataUri(string $rutaAbsoluta, int $anchoPx = 190, int $altoPx = 240): ?string
{
    if (!is_file($rutaAbsoluta)) {
        return null;
    }

    $info = @getimagesize($rutaAbsoluta);
    if (!$info) {
        return null;
    }

    [$anchoOriginal, $altoOriginal, $tipo] = $info;

    switch ($tipo) {
        case IMAGETYPE_JPEG:
            $origen = @imagecreatefromjpeg($rutaAbsoluta);
            break;
        case IMAGETYPE_PNG:
            $origen = @imagecreatefrompng($rutaAbsoluta);
            break;
        default:
            return null; // formato no soportado (gif, webp, etc.)
    }

    if (!$origen) {
        return null;
    }

    // Recorte tipo "cover": escala hasta cubrir todo el rectángulo
    // destino y recorta el sobrante centrado, igual que el
    // object-fit:cover que se usaba en el HTML.
    $escala = max($anchoPx / $anchoOriginal, $altoPx / $altoOriginal);
    $anchoEscalado = max(1, (int) round($anchoOriginal * $escala));
    $altoEscalado = max(1, (int) round($altoOriginal * $escala));
    $offsetX = (int) round(($anchoEscalado - $anchoPx) / 2);
    $offsetY = (int) round(($altoEscalado - $altoPx) / 2);

    $temporal = imagecreatetruecolor($anchoEscalado, $altoEscalado);
    imagecopyresampled($temporal, $origen, 0, 0, 0, 0, $anchoEscalado, $altoEscalado, $anchoOriginal, $altoOriginal);
    imagedestroy($origen);

    $destino = imagecreatetruecolor($anchoPx, $altoPx);
    imagecopy($destino, $temporal, 0, 0, $offsetX, $offsetY, $anchoPx, $altoPx);
    imagedestroy($temporal);

    ob_start();
    imagejpeg($destino, null, 82); // calidad 82: buen balance tamaño/nitidez
    $contenido = ob_get_clean();
    imagedestroy($destino);

    return 'data:image/jpeg;base64,' . base64_encode($contenido);
}

// ─────────────────────────────────────────────────────────────
// [HELPER DE QR]
// ─────────────────────────────────────────────────────────────

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

// ─────────────────────────────────────────────────────────────
// [PLANTILLAS DE LA TARJETA — FRENTE Y REVERSO] Cada credencial
// ahora se imprime a doble cara: el frente lleva los datos del
// alumno, el reverso lleva el QR en grande (más fácil de leer con
// el lector que el chico que antes iba junto a los datos).
// ─────────────────────────────────────────────────────────────

function construirHtmlCredencialFrente(array $alumno, ?string $logoDataUri): string
{
    $fotoRuta = $alumno['foto'] ? str_replace('\\', '/', $alumno['foto']) : null;
    $fotoAbsoluta = $fotoRuta ? __DIR__ . '/../../' . $fotoRuta : null;
    $fotoDataUri = $fotoAbsoluta ? fotoRedimensionadaDataUri($fotoAbsoluta) : null;

    $nombre = htmlspecialchars($alumno['nombre']);
    $gradoGrupo = htmlspecialchars($alumno['grado'] . '° ' . $alumno['grupo']);
    $curp = htmlspecialchars($alumno['CURP']);
    $escuela = htmlspecialchars(NOMBRE_ESCUELA);

    $fotoHtml = $fotoDataUri
        ? '<img src="' . $fotoDataUri . '" class="foto-alumno">'
        : '<div class="foto-alumno foto-vacia">Sin foto</div>';

    $logoHtml = $logoDataUri
        ? '<img src="' . $logoDataUri . '" class="logo-escuela">'
        : '';

    // NOTA: se usa display:table/table-cell en vez de flexbox para
    // acomodar las columnas. Dompdf tiene soporte muy parcial e
    // inconsistente de flexbox. fila-tabla (table-row) es
    // obligatorio: a diferencia de un navegador normal, Dompdf NO
    // genera automáticamente la fila intermedia entre table y
    // table-cell.
    return <<<HTML
    <div class="credencial">
        <div class="encabezado">
            <div class="fila-tabla">
                <div class="celda-logo">{$logoHtml}</div>
                <div class="celda-nombre-escuela">{$escuela}</div>
            </div>
        </div>
        <div class="cuerpo">
            <div class="fila-tabla">
                <div class="celda-foto">{$fotoHtml}</div>
                <div class="celda-datos">
                    <div class="dato-nombre">{$nombre}</div>
                    <div class="dato-grado">{$gradoGrupo}</div>
                    <div class="dato-curp">{$curp}</div>
                </div>
            </div>
        </div>
        <div class="pie">ChecaBot — Control de Asistencia</div>
    </div>
    HTML;
}

/**
 * Reverso: QR grande + nombre pequeño de referencia. El QR sigue
 * codificando la CURP tal cual, el mismo valor que espera
 * api/registrar.php al escanear.
 */
function construirHtmlCredencialReverso(array $alumno, ?string $logoDataUri): string
{
    $qrDataUri = generarQrDataUri($alumno['CURP']);
    $nombre = htmlspecialchars($alumno['nombre']);
    $escuela = htmlspecialchars(NOMBRE_ESCUELA);

    $logoHtml = $logoDataUri
        ? '<img src="' . $logoDataUri . '" class="logo-escuela">'
        : '';

    return <<<HTML
    <div class="credencial">
        <div class="encabezado">
            <div class="fila-tabla">
                <div class="celda-logo">{$logoHtml}</div>
                <div class="celda-nombre-escuela">{$escuela}</div>
            </div>
        </div>
        <div class="reverso-cuerpo">
            <img src="{$qrDataUri}" class="qr-reverso">
            <div class="reverso-texto">Escanea para registrar entrada/salida</div>
        </div>
        <div class="pie">{$nombre}</div>
    </div>
    HTML;
}

// ─────────────────────────────────────────────────────────────
// [CSS COMPARTIDO] — mismo estilo visual para individual y lote;
// lo único que cambia entre modos es el tamaño/orientación de la
// página en Dompdf y si las tarjetas se acomodan en cuadrícula.
// ─────────────────────────────────────────────────────────────

function cssCredencial(): string
{
    return <<<CSS
    * { box-sizing: border-box; font-family: Arial, sans-serif; margin: 0; padding: 0; }

    .credencial {
        /* 85.6x54mm es el tamaño real de la credencial (CR80), pero
           se deja un pelín menos (85.2x53.6mm, apenas 0.4mm de
           diferencia, invisible al imprimir) para que el contenido
           quede CLARAMENTE por debajo del tamaño exacto de la
           página. Cuando coincidían al milímetro justo, un
           redondeo mínimo de Dompdf al convertir mm a puntos hacía
           que aventara una página en blanco de más después de cada
           tarjeta. */
        width: 85.2mm;
        height: 53.6mm;
        border: 1px solid #d0d7de;
        border-radius: 3mm;
        overflow: hidden;
    }

    .encabezado {
        display: table;
        width: 100%;
        background: #2c3e50;
        color: white;
    }

    .fila-tabla {
        display: table-row;
    }

    .celda-logo,
    .celda-nombre-escuela {
        display: table-cell;
        vertical-align: middle;
        padding: 1.8mm 2mm;
    }

    .celda-logo {
        width: 8mm;
    }

    .logo-escuela {
        height: 6mm;
        width: 6mm;
        object-fit: contain;
    }

    .celda-nombre-escuela {
        font-size: 8pt;
        font-weight: bold;
    }

    .cuerpo {
        display: table;
        width: 100%;
        table-layout: fixed;
    }

    .celda-foto,
    .celda-datos {
        display: table-cell;
        vertical-align: middle;
        padding: 2mm;
    }

    .celda-foto {
        width: 20mm;
    }

    .foto-alumno {
        width: 16mm;
        height: 20mm;
        object-fit: cover;
        border: 1px solid #d0d7de;
        border-radius: 1mm;
    }

    .foto-vacia {
        display: block;
        width: 16mm;
        height: 20mm;
        background: #f3f6f9;
        color: #9aa5b1;
        font-size: 6pt;
        text-align: center;
        padding-top: 8mm;
        border: 1px solid #d0d7de;
        border-radius: 1mm;
    }

    .dato-nombre {
        font-size: 9.5pt;
        font-weight: bold;
        color: #243b53;
        line-height: 1.3;
    }

    .dato-grado {
        font-size: 8.5pt;
        color: #048A81;
        font-weight: bold;
        margin-top: 2mm;
    }

    .dato-curp {
        /* Antes #627d98 — un gris-azul demasiado claro que a
           tamaño chico se veía casi invisible impreso. Se oscurece
           y se le sube un poco el peso/tamaño para que sea
           legible sin lupa. */
        font-size: 8pt;
        font-weight: 600;
        color: #1e293b;
        font-family: monospace;
        margin-top: 2mm;
    }

    /* [REVERSO] — QR grande centrado. El tamaño se ajustó para
       caber holgado dentro de los 54mm de alto de la tarjeta junto
       con el encabezado y el pie; una versión anterior (32mm) se
       pasaba unos milímetros y esa cola terminaba en una página
       extra. */
    .reverso-cuerpo {
        text-align: center;
        padding: 2mm;
    }

    .qr-reverso {
        width: 28mm;
        height: 28mm;
    }

    .reverso-texto {
        font-size: 7pt;
        font-weight: 600;
        color: #334e68;
        margin-top: 1.5mm;
    }

    .pie {
        background: #f3f6f9;
        color: #7b8794;
        font-size: 6pt;
        text-align: center;
        padding: 1.2mm;
    }
    CSS;
}

// ─────────────────────────────────────────────────────────────
// [CSS DE LA CUADRÍCULA] — solo lo usa el lote (generar_lote.php),
// para acomodar varias tarjetas por hoja en filas y columnas.
// ─────────────────────────────────────────────────────────────

function cssGridHoja(): string
{
    return <<<CSS
    .hoja-grid {
        display: table;
        width: 100%;
        border-collapse: collapse;
    }

    .hoja-fila {
        display: table-row;
        page-break-inside: avoid;
    }

    .hoja-celda {
        display: table-cell;
        padding: 4mm;
        vertical-align: top;
    }
    CSS;
}
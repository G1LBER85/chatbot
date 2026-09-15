<?php
require '../conexion.php';
require '../vendor/autoload.php';

// Red de seguridad adicional: aunque redimensionamos las fotos
// (lo cual reduce el consumo real drásticamente), un lote muy
// grande de alumnos igual puede necesitar más memoria de la que
// PHP permite por defecto. Esto SOLO aplica a este script, no
// afecta el límite del resto del sitio.
ini_set('memory_limit', '1024M');
set_time_limit(180);

use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

// ═══════════════════════════════════════════════════════════════
// CONFIGURACIÓN DE LA CREDENCIAL — edita aquí el nombre de la
// escuela o la ruta del logo si cambian más adelante.
// ═══════════════════════════════════════════════════════════════
const NOMBRE_ESCUELA = 'Preparatoria Número 3';
const RUTA_LOGO = __DIR__ . '/../img/logo_chiapas.png'; // se usa si existe; si no, se omite sin error

// Tamaño estándar de credencial tipo tarjeta (CR80), en milímetros.
const ANCHO_TARJETA_MM = 85.6;
const ALTO_TARJETA_MM = 54.0;
const MM_A_PT = 2.83465; // 1 mm en puntos, unidad que usa Dompdf

// ═══════════════════════════════════════════════════════════════
// FUNCIONES AUXILIARES
// ═══════════════════════════════════════════════════════════════

/**
 * Convierte una imagen del disco a un data URI base64, para
 * incrustarla directamente en el HTML sin depender de rutas
 * relativas (Dompdf a veces falla resolviendo rutas de archivo).
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

/**
 * Construye el HTML de UNA credencial. Se reutiliza tanto para
 * el modo individual como para el modo por lote.
 */
function construirHtmlCredencial(array $alumno, ?string $logoDataUri): string
{
    $fotoRuta = $alumno['foto'] ? str_replace('\\', '/', $alumno['foto']) : null;
    $fotoAbsoluta = $fotoRuta ? __DIR__ . '/../' . $fotoRuta : null;
    $fotoDataUri = $fotoAbsoluta ? fotoRedimensionadaDataUri($fotoAbsoluta) : null;

    $qrDataUri = generarQrDataUri($alumno['CURP']);

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
    // acomodar las columnas (logo+nombre en el encabezado; foto,
    // datos y QR en el cuerpo). Dompdf tiene soporte muy parcial e
    // inconsistente de flexbox — con frecuencia recorta o desborda
    // el contenido (como el QR que se veía cortado). Las celdas de
    // tabla sí se calculan de forma confiable en Dompdf.
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
                <div class="celda-qr"><img src="{$qrDataUri}" class="qr-credencial"></div>
            </div>
        </div>
        <div class="pie">ChecaBot — Control de Asistencia</div>
    </div>
    HTML;
}

// ═══════════════════════════════════════════════════════════════
// CSS COMPARTIDO — mismo estilo visual para individual y lote,
// solo cambia el tamaño/orientación de la página en Dompdf.
// ═══════════════════════════════════════════════════════════════
function cssCredencial(): string
{
    return <<<CSS
    * { box-sizing: border-box; font-family: Arial, sans-serif; margin: 0; padding: 0; }

    .credencial {
        width: 85.6mm;
        height: 54mm;
        border: 1px solid #d0d7de;
        border-radius: 3mm;
        overflow: hidden;
        page-break-inside: avoid;
    }

    /* [ENCABEZADO] — display:table en vez de flex, más confiable en Dompdf.
       fila-tabla (table-row) es obligatorio: a diferencia de un
       navegador normal, Dompdf NO genera automáticamente la fila
       intermedia entre table y table-cell; si falta, truena con
       "Frame not found in cellmap". */
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

    /* [CUERPO] — misma técnica: tabla para foto + datos + QR */
    .cuerpo {
        display: table;
        width: 100%;
        table-layout: fixed;
    }

    .celda-foto,
    .celda-datos,
    .celda-qr {
        display: table-cell;
        vertical-align: middle;
        padding: 2mm;
    }

    .celda-foto {
        width: 20mm;
    }

    .celda-qr {
        width: 18mm;
        text-align: right;
    }

    .foto-alumno {
        width: 16mm;
        height: 20mm;
        object-fit: cover;
        border: 1px solid #d0d7de;
        border-radius: 1mm;
    }

    .foto-vacia {
        /* Antes tenía display:table-cell, pero al estar anidada
           dentro de .celda-foto (que ya es una celda) sin su propia
           tabla/fila, formaba una tabla inválida y volvía a tronar
           el cellmap de Dompdf. Con block + padding-top se centra
           el texto verticalmente sin necesidad de otra tabla. */
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
        font-size: 8.5pt;
        font-weight: bold;
        color: #243b53;
        line-height: 1.25;
    }

    .dato-grado {
        font-size: 8pt;
        color: #048A81;
        font-weight: bold;
        margin-top: 1.5mm;
    }

    .dato-curp {
        font-size: 7pt;
        color: #627d98;
        font-family: monospace;
        margin-top: 1.5mm;
    }

    .qr-credencial {
        width: 16mm;
        height: 16mm;
    }

    /* [PIE] — bloque normal, sin problema aquí */
    .pie {
        background: #f3f6f9;
        color: #7b8794;
        font-size: 6pt;
        text-align: center;
        padding: 1.2mm;
    }
    CSS;
}

// ═══════════════════════════════════════════════════════════════
// PROCESO PRINCIPAL
// ═══════════════════════════════════════════════════════════════
// $_REQUEST cubre tanto GET (usado por el enlace de credencial
// individual, que solo lleva un id) como POST (usado por el
// formulario de lote, que ahora es POST para no toparse con el
// límite de longitud de URL de Apache al marcar muchos alumnos).
$modo = $_REQUEST['modo'] ?? '';
$logoDataUri = imagenADataUri(RUTA_LOGO);

if ($modo === 'individual') {

    $id = intval($_REQUEST['id'] ?? 0);

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

    $html = '<html><head><style>' . cssCredencial() . '</style></head><body>'
        . construirHtmlCredencial($alumno, $logoDataUri)
        . '</body></html>';

    $options = new Options();
    $options->set('isRemoteEnabled', false);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    // Página exactamente del tamaño de la tarjeta, sin márgenes,
    // para imprimir directo sobre credenciales físicas pre-cortadas.
    $dompdf->setPaper([0, 0, ANCHO_TARJETA_MM * MM_A_PT, ALTO_TARJETA_MM * MM_A_PT]);
    $dompdf->render();
    $dompdf->stream('credencial_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $alumno['nombre']) . '.pdf', ['Attachment' => false]);
    exit;

} elseif ($modo === 'lote') {

    // Ahora viene por POST (ver el cambio de method en credenciales.php),
    // así que se lee de $_POST, no de $_GET.
    $ids = array_map('intval', $_POST['ids'] ?? []);

    if (empty($ids)) {
        die('No seleccionaste ningún alumno. Regresa y marca al menos uno.');
    }

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

    $tarjetasHtml = '';
    while ($alumno = $resultado->fetch_assoc()) {
        $tarjetasHtml .= construirHtmlCredencial($alumno, $logoDataUri);
    }
    $stmt->close();

    // Hoja carta con varias tarjetas en cuadrícula, pensada para
    // imprimir y cortar con guillotina o tijeras. Se usa
    // display:inline-block en vez de flex-wrap (mismo motivo que
    // en cssCredencial: Dompdf maneja inline-block de forma mucho
    // más predecible que flexbox para acomodar varios bloques).
    $html = '<html><head><style>'
        . cssCredencial()
        . '.credencial { display: inline-block; vertical-align: top; margin: 2mm; }'
        . '</style></head><body>' . $tarjetasHtml . '</body></html>';

    $options = new Options();
    $options->set('isRemoteEnabled', false);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('letter', 'portrait');
    $dompdf->render();
    $dompdf->stream('credenciales_lote.pdf', ['Attachment' => false]);
    exit;

} else {
    die('Modo no válido. Regresa a la página de Credencialización e inténtalo de nuevo.');
}
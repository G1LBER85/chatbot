<?php
// ═══════════════════════════════════════════════════════════════
// panel/plantilla_credenciales.php
// ─────────────────────────────────────────────────────────────
// El DISEÑO de la credencial vive aquí, y solo aquí. Si mañana hay
// que cambiar cómo se ve una credencial (colores, qué datos
// aparecen, el acomodo), este es el único archivo que hay que
// tocar — todas las credenciales que se generen después usan esta
// misma plantilla automáticamente.
// ═══════════════════════════════════════════════════════════════

// ─────────────────────────────────────────────────────────────
// [CONFIGURACIÓN] — edita aquí si cambia el nombre de la escuela
// o el logo.
// ─────────────────────────────────────────────────────────────
const NOMBRE_ESCUELA = 'Preparatoria Número 3';
const RUTA_LOGO = __DIR__ . '/../img/logo_chiapas.png'; // se usa si existe; si no, se omite sin error

/**
 * Convierte una imagen del disco a un data URI base64 TAL CUAL,
 * sin redimensionar. Se usa para el logo de la escuela, que ya es
 * chico de por sí. Para fotos de alumnos se usa fotoAlumnoDataUri()
 * en panel/credenciales/alumnos.php — esa sí redimensiona.
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

// ─────────────────────────────────────────────────────────────
// [FRENTE DE LA TARJETA]
// ─────────────────────────────────────────────────────────────
function construirHtmlCredencialFrente(array $alumno, ?string $logoDataUri): string
{
    $fotoDataUri = $alumno['foto'] ? fotoAlumnoDataUri($alumno['foto']) : null;

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

// ─────────────────────────────────────────────────────────────
// [REVERSO DE LA TARJETA] — QR grande + nombre pequeño de
// referencia. El QR sigue codificando la CURP tal cual.
// ─────────────────────────────────────────────────────────────
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
// [HOJA COMPLETA] — una fila por alumno, con su frente y su
// reverso lado a lado (así no hay que andar buscando cuál QR le
// corresponde a cuál nombre).
// ─────────────────────────────────────────────────────────────
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
<?php
// ═══════════════════════════════════════════════════════════════
// panel/credenciales/proceso_de_credenciales/plantilla_credenciales.php
// ─────────────────────────────────────────────────────────────
// El DISEÑO de la credencial vive aquí, y solo aquí. Si mañana hay
// que cambiar cómo se ve una credencial (colores, qué datos
// aparecen, el acomodo), este es el único archivo que hay que
// tocar — todas las credenciales que se generen después usan esta
// misma plantilla automáticamente.
// ═══════════════════════════════════════════════════════════════

// ─────────────────────────────────────────────────────────────
// [CONFIGURACIÓN] — edita aquí si cambia el nombre de la escuela,
// el turno, o el logo.
// ─────────────────────────────────────────────────────────────
const NOMBRE_ESCUELA = 'Escuela Preparatoria Núm. 3 del Estado';
const TURNO_ESCUELA = 'Turno Matutino';
const RUTA_LOGO = __DIR__ . '/../../../img/logo_escuela_sinfondo.png'; // se usa si existe; si no, se omite sin error
const RUTA_LOGO_CHIAPAS = __DIR__ . '/../../../img/logo_chiapas.png';

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

function logoChiapasDataUri(): ?string
{
    static $uri = false;   // false = todavía no se ha leído

    if ($uri === false) {
        $uri = imagenADataUri(RUTA_LOGO_CHIAPAS);
    }

    return $uri;
}

/**
 * Arma el encabezado (logo + nombre de la escuela + turno) que
 * comparten el frente y el reverso, para no repetir el HTML dos
 * veces. Incluye la franja roja debajo, que también es compartida.
 */
function construirEncabezado(?string $logoDataUri): string
{
    $escuela = htmlspecialchars(NOMBRE_ESCUELA);
    $turno = htmlspecialchars(TURNO_ESCUELA);

    $logoHtml = $logoDataUri
        ? '<img src="' . $logoDataUri . '" class="logo-escuela">'
        : '';

    $chiapasUri = logoChiapasDataUri();
    $logoChiapasHtml = $chiapasUri
        ? '<img src="' . $chiapasUri . '" class="logo-chiapas">'
        : '';

    return <<<HTML
    <div class="encabezado">
        <div class="fila-tabla">
            <div class="celda-logo">{$logoHtml}</div>
            <div class="celda-nombre-escuela">
                <div class="nombre-institucion">{$escuela}</div>
                <div class="turno-institucion">{$turno}</div>
            </div>
            <div class="celda-logo-derecha">{$logoChiapasHtml}</div>
        </div>
    </div>
    <div class="franja-rosa"></div>
    HTML;
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

    $fotoHtml = $fotoDataUri
        ? '<img src="' . $fotoDataUri . '" class="foto-alumno">'
        : '<div class="foto-alumno foto-vacia">Sin foto</div>';

    $encabezadoHtml = construirEncabezado($logoDataUri);

    // NOTA: se usa display:table/table-cell en vez de flexbox para
    // acomodar las columnas. Dompdf tiene soporte muy parcial e
    // inconsistente de flexbox. fila-tabla (table-row) es
    // obligatorio: a diferencia de un navegador normal, Dompdf NO
    // genera automáticamente la fila intermedia entre table y
    // table-cell.
    return <<<HTML
    <div class="credencial">
        {$encabezadoHtml}
        <div class="cuerpo">
            <div class="fila-tabla">
                <div class="celda-foto">{$fotoHtml}</div>
                <div class="celda-datos">
                    <span style="font-size: 8px; font-weight: bold;">Nombre Completo:</span>
                    <div class="dato-nombre">{$nombre}</div>

                    <span style="font-size: 8px; font-weight: bold;">Semestre y Grupo:</span>
                    <div class="dato-grado">{$gradoGrupo}</div>

                    <span style="font-size: 8px; font-weight: bold;">CURP:</span>
                    <div class="dato-curp">{$curp}</div>

                    <span style="font-size: 8px; font-weight: bold;">CCT:</span>
                    <div class="dato-nombre">07EBH0072Z</div>

                    <span style="font-size: 8px; font-weight: bold;">Nivel Educativo:</span>
                    <div class="dato-nombre">Bachillerato</div>
                </div>
            </div>
        </div>
        <div class="pie">
            Uso exclusivo para acceso a la institución.
            <span class="leyenda-uso-unico">Sin validez oficial.</span>
        </div>
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

    $encabezadoHtml = construirEncabezado($logoDataUri);

    return <<<HTML
    <div class="credencial">
        {$encabezadoHtml}
        <div class="reverso-cuerpo">
            <img src="{$qrDataUri}" class="qr-reverso">
            <div class="reverso-texto">Escanea para registrar entrada/salida</div>
        </div>
        <div class="pie">{$nombre}
            <span class="leyenda-uso-unico">Sin validez oficial.</span>
        </div>
    </div>
    HTML;
}

// ─────────────────────────────────────────────────────────────
// [HOJA COMPLETA] — una fila por alumno, con su frente y su
// reverso lado a lado (así no hay que andar buscando cuál QR le
// corresponde a cuál nombre).
//
// Después de armar cada alumno, se avisa el progreso llamando a
// registrarProgresoCredencial() — esa función la define
// panel/credenciales/proceso_de_credenciales/generar.php, no este
// archivo. Se llama con function_exists() de por medio para que
// esta plantilla no dependa obligatoriamente de generar.php: si en
// algún momento se usa la plantilla desde otro lado sin esa
// función definida, simplemente no se reporta progreso, sin
// romperse.
// ─────────────────────────────────────────────────────────────
function construirHojaConParejas(array $alumnosDeLaHoja, ?string $logoDataUri): string
{
    $html = '<div class="hoja-grid">';

    foreach ($alumnosDeLaHoja as $alumno) {
        $html .= '<div class="hoja-fila">';
        $html .= '<div class="hoja-celda">' . construirHtmlCredencialFrente($alumno, $logoDataUri) . '</div>';
        $html .= '<div class="hoja-celda">' . construirHtmlCredencialReverso($alumno, $logoDataUri) . '</div>';
        $html .= '</div>';

        if (function_exists('registrarProgresoCredencial')) {
            registrarProgresoCredencial();
        }
    }

    $html .= '</div>';
    return $html;
}
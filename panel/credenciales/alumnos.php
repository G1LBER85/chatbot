<?php
// ═══════════════════════════════════════════════════════════════
// panel/credenciales/alumnos.php
// ─────────────────────────────────────────────────────────────
// Todo lo relacionado a obtener y preparar los DATOS del alumno:
// la consulta a la base de datos, agruparlos por grado+grupo, y
// convertir su foto a un formato listo para incrustar en el PDF.
// ═══════════════════════════════════════════════════════════════

/**
 * Busca en la base de datos los alumnos seleccionados que SÍ tienen
 * CURP capturada (sin CURP no se puede generar su QR, así que no
 * tiene caso traerlos).
 */
function obtenerAlumnosSeleccionados(mysqli $conn, array $ids): array
{
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

    $alumnos = [];
    while ($fila = $resultado->fetch_assoc()) {
        $alumnos[] = $fila;
    }
    $stmt->close();

    return $alumnos;
}

/**
 * Agrupa una lista de alumnos por grado+grupo. Cada grado+grupo
 * termina en su propio archivo PDF (ver panel/generar.php), para
 * que los archivos salgan ya ordenados y para no meter cientos de
 * alumnos en un solo PDF gigante.
 */
function agruparPorGradoYGrupo(array $alumnos): array
{
    $grupos = [];
    foreach ($alumnos as $alumno) {
        $clave = $alumno['grado'] . ($alumno['grupo'] ?: 'SinGrupo');
        $grupos[$clave][] = $alumno;
    }
    return $grupos;
}

/**
 * Redimensiona y comprime la foto de un alumno antes de
 * incrustarla en el PDF. Las fotos originales suelen ser mucho más
 * grandes de lo que una credencial necesita (16x20mm impresos);
 * incrustarlas a tamaño completo es lo que agota la memoria de PHP
 * cuando se generan muchas credenciales a la vez. Aquí se reduce a
 * un tamaño de píxeles suficiente para verse nítida impresa, con
 * recorte tipo "cover" (llena el rectángulo sin deformar la
 * imagen). Devuelve null si la imagen no existe o no se puede
 * procesar.
 */
function fotoAlumnoDataUri(string $rutaRelativa, int $anchoPx = 190, int $altoPx = 240): ?string
{
    $rutaLimpia = str_replace('\\', '/', $rutaRelativa);
    $rutaAbsoluta = __DIR__ . '/../../' . $rutaLimpia;

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
    // destino y recorta el sobrante centrado, igual que un
    // object-fit:cover en CSS.
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

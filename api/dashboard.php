<?php

require '../conexion.php';

header('Content-Type: application/json; charset=utf-8');

date_default_timezone_set('America/Mexico_City');

$nuevo_registro = false;
$ultimo = [];

try {

    // ── Obtener el último registro ──
    $sqlUltimo = "
        SELECT
            r.id,
            a.nombre,
            a.grado,
            a.grupo,
            a.foto,
            r.tipo,
            DATE_FORMAT(r.fecha_hora, '%h:%i %p') AS hora,
            DATE_FORMAT(r.fecha_hora, '%e de %M de %Y') AS fecha,
            r.mostrado
        FROM registros r
        INNER JOIN alumnos a
            ON a.id = r.alumno_id
        WHERE DATE(r.fecha_hora) = CURDATE()
        ORDER BY r.id DESC
        LIMIT 1
    ";

    $res = $conn->query($sqlUltimo);

    if ($res && $fila = $res->fetch_assoc()) {

        if ((int)$fila['mostrado'] === 0) {

            $nuevo_registro = true;

            $stmtMostrar = $conn->prepare("
                UPDATE registros
                SET mostrado = 1
                WHERE id = ?
            ");

            $stmtMostrar->bind_param('i', $fila['id']);
            $stmtMostrar->execute();
            $stmtMostrar->close();
        }

        $ultimo = [
            'nombre'   => $fila['nombre'],
            'semestre' => $fila['grado'],
            'grupo'    => $fila['grupo'],
            'hora'     => $fila['hora'],
            'fecha'    => $fila['fecha'],
            'tipo'     => $fila['tipo'],
            'foto'     => $fila['foto'],
        ];
    }

    // ── Estadísticas del día ──
    $hoy = date('Y-m-d');

    $sqlEstadisticas = "
        SELECT
            SUM(CASE WHEN tipo = 'entrada' THEN 1 ELSE 0 END) AS entradas,
            SUM(CASE WHEN tipo = 'salida' THEN 1 ELSE 0 END) AS salidas
        FROM registros
        WHERE DATE(fecha_hora) = ?
    ";

    $stmtEstadisticas = $conn->prepare($sqlEstadisticas);
    $stmtEstadisticas->bind_param('s', $hoy);
    $stmtEstadisticas->execute();

    $estadisticas = $stmtEstadisticas
        ->get_result()
        ->fetch_assoc();

    $stmtEstadisticas->close();

    // ── Total de alumnos activos ──
    $sqlTotal = "
        SELECT COUNT(*) AS total
        FROM alumnos
        WHERE activo = 1
    ";

    $resTotal = $conn->query($sqlTotal);
    $filaTotal = $resTotal->fetch_assoc();

    $entradas = (int)($estadisticas['entradas'] ?? 0);
    $salidas = (int)($estadisticas['salidas'] ?? 0);
    $totalAlumnos = (int)($filaTotal['total'] ?? 0);
    $enPlantel = max(0, $entradas - $salidas);

    echo json_encode([
        'ok' => true,
        'nuevo_registro' => $nuevo_registro,
        'ultimo' => $ultimo,
        'estadisticas' => [
            'entradas' => $entradas,
            'salidas' => $salidas,
            'enPlantel' => $enPlantel,
            'totalAlumnos' => $totalAlumnos
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $error) {

    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'error' => $error->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>
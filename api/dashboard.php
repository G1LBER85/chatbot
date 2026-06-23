<?php
require '../conexion.php';
header('Content-Type: application/json');

// ── Verificar si hay un registro nuevo pendiente de mostrar ──
$nuevo_registro = false;
$ultimo         = [];

$sqlUltimo = "
    SELECT
        a.nombre,
        a.grado,
        a.foto,
        r.tipo,
        DATE_FORMAT(r.fecha_hora, '%h:%i %p') AS hora,
        DATE_FORMAT(r.fecha_hora, '%e de %M de %Y') AS fecha,
        r.mostrado
    FROM registros r
    JOIN alumnos a ON a.id = r.alumno_id
    ORDER BY r.id DESC
    LIMIT 1
";

$res = $conn->query($sqlUltimo);

if($res && $fila = $res->fetch_assoc()){

    // Si el registro aún no se ha mostrado en pantalla
    if($fila['mostrado'] == 0){
        $nuevo_registro = true;
        // Marcarlo como mostrado para no repetirlo
        $conn->query("UPDATE registros SET mostrado = 1 ORDER BY id DESC LIMIT 1");
    }

    $ultimo = [
        'nombre'   => $fila['nombre'],
        'semestre' => $fila['grado'],
        'grupo'    => '',
        'hora'     => $fila['hora'],
        'tipo'     => $fila['tipo'],
        'foto'     => $fila['foto'] ?? ''
    ];
}

// ── Estadísticas del día ──
$hoy = date('Y-m-d');

$sqlEntradas = "SELECT COUNT(*) AS total FROM registros WHERE tipo = 'entrada' AND DATE(fecha_hora) = '$hoy'";
$sqlSalidas  = "SELECT COUNT(*) AS total FROM registros WHERE tipo = 'salida'  AND DATE(fecha_hora) = '$hoy'";
$sqlTotal    = "SELECT COUNT(*) AS total FROM alumnos WHERE activo = 1";

$entradas = $conn->query($sqlEntradas)->fetch_assoc()['total'];
$salidas  = $conn->query($sqlSalidas)->fetch_assoc()['total'];
$total    = $conn->query($sqlTotal)->fetch_assoc()['total'];
$enPlantel = $entradas - $salidas;

echo json_encode([
    'nuevo_registro' => $nuevo_registro,
    'ultimo'         => $ultimo,
    'estadisticas'   => [
        'entradas'     => (int)$entradas,
        'salidas'      => (int)$salidas,
        'enPlantel'    => (int)max(0, $enPlantel),
        'totalAlumnos' => (int)$total
    ]
]);
?>

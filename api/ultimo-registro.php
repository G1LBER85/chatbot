<?php
require '../conexion.php';
header('Content-Type: application/json');

$sql = "
    SELECT
        a.nombre,
        a.grado,
        a.foto,
        r.tipo,
        DATE_FORMAT(r.fecha_hora, '%h:%i %p |')        AS hora,
        DATE_FORMAT(r.fecha_hora, '%e de %M del %Y')  AS fecha
    FROM registros r
    JOIN alumnos a ON a.id = r.alumno_id
    ORDER BY r.id DESC
    LIMIT 1
";

$res  = $conn->query($sql);
$fila = $res ? $res->fetch_assoc() : null;

if($fila){
    echo json_encode([
        'tipo'   => $fila['tipo'],
        'nombre' => $fila['nombre'],
        'grupo'  => $fila['grado'],
        'hora'   => $fila['hora'],
        'fecha'  => $fila['fecha'],
        'foto'   => $fila['foto'] ?? ''
    ]);
} else {
    echo json_encode([
        'tipo'   => 'entrada',
        'nombre' => '—',
        'grupo'  => '—',
        'hora'   => '—',
        'fecha'  => '—',
        'foto'   => ''
    ]);
}
?>

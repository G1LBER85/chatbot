<?php
require 'conexion.php';

$alumno_id = $_POST['alumno_id'];
$tipo      = $_POST['tipo'];

// 1. Obtener datos del alumno
$sql    = "SELECT * FROM alumnos WHERE id = ?";
$stmt   = $conn->prepare($sql);
$stmt->bind_param("i", $alumno_id);
$stmt->execute();
$alumno = $stmt->get_result()->fetch_assoc();

// 2. Guardar registro en la base de datos
$sql2  = "INSERT INTO registros (alumno_id, tipo) VALUES (?, ?)";
$stmt2 = $conn->prepare($sql2);
$stmt2->bind_param("is", $alumno_id, $tipo);
$stmt2->execute();

// 3. Preparar datos para WhatsApp
$hora  = date("h:i A");
$datos = json_encode([
    "nombre" => $alumno['nombre'],
    "tipo"   => $tipo,
    "hora"   => $hora,
    "tutor"  => $alumno['tutor_chat_id']
]);

// 4. Mandar datos al webhook de n8n
$opciones = [
    "http" => [
        "header"  => "Content-Type: application/json\r\n",
        "method"  => "POST",
        "content" => $datos
    ]
];

$context = stream_context_create($opciones);
file_get_contents(
    "http://localhost:5678/webhook/registro-alumno",
    false,
    $context
);

// 5. Regresar a la página principal
header("Location: index.php?ok=1&tipo=$tipo&nombre=" . urlencode($alumno['nombre']));
exit;
?>
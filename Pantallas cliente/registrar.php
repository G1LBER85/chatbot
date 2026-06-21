<?php
require '../conexion.php';
header('Content-Type: application/json');

$datos     = json_decode(file_get_contents('php://input'), true);
$matricula = $conn->real_escape_string($datos['matricula'] ?? '');

if(!$matricula){
    echo json_encode(['ok' => false, 'error' => 'Matrícula vacía']);
    exit;
}

// ── 1. Buscar alumno por código QR ──
$stmt = $conn->prepare("SELECT * FROM alumnos WHERE codigo = ? AND activo = 1");
$stmt->bind_param("s", $matricula);
$stmt->execute();
$alumno = $stmt->get_result()->fetch_assoc();

if(!$alumno){
    echo json_encode(['ok' => false, 'error' => 'Alumno no encontrado']);
    exit;
}

// ── 2. Determinar si es entrada o salida ──
// Si el último registro del día fue entrada → ahora es salida, y viceversa
$hoy     = date('Y-m-d');
$sqlUlt  = "SELECT tipo FROM registros WHERE alumno_id = ? AND DATE(fecha_hora) = ? ORDER BY id DESC LIMIT 1";
$stmt2   = $conn->prepare($sqlUlt);
$stmt2->bind_param("is", $alumno['id'], $hoy);
$stmt2->execute();
$ultimo  = $stmt2->get_result()->fetch_assoc();

$tipo = (!$ultimo || $ultimo['tipo'] === 'salida') ? 'entrada' : 'salida';

// ── 3. Insertar registro ──
// mostrado = 0 para que dashboard.php lo detecte como nuevo
$stmt3 = $conn->prepare("INSERT INTO registros (alumno_id, tipo, mostrado) VALUES (?, ?, 0)");
$stmt3->bind_param("is", $alumno['id'], $tipo);
$stmt3->execute();

// ── 4. Notificar al tutor por Telegram ──
if($alumno['tutor_chat_id']){
    $hora    = date("h:i A");
    $mensaje = $tipo === 'entrada'
        ? "✅ *{$alumno['nombre']}* acaba de registrar su *entrada* a las {$hora} 🏫"
        : "🚪 *{$alumno['nombre']}* acaba de registrar su *salida* a las {$hora}";

    $token   = '8922581761:AAH_SEeEAOjedu18YI2BiWwcJghXv7i3vJE';
    $url     = "https://api.telegram.org/bot{$token}/sendMessage";
    $payload = json_encode([
        'chat_id'    => $alumno['tutor_chat_id'],
        'text'       => $mensaje,
        'parse_mode' => 'Markdown'
    ]);

    $opciones = [
        'http' => [
            'header'  => "Content-Type: application/json\r\n",
            'method'  => 'POST',
            'content' => $payload
        ]
    ];
    $context = stream_context_create($opciones);
    file_get_contents($url, false, $context);
}

echo json_encode(['ok' => true, 'tipo' => $tipo, 'nombre' => $alumno['nombre']]);
?>

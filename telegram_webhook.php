<?php
require 'conexion.php';

$token = '8922581761:AAH_SEeEAOjedu18YI2BiWwcJghXv7i3vJE';

$update = json_decode(file_get_contents('php://input'), true);

if (!$update) exit;

$chat_id  = $update['message']['chat']['id'];
$texto    = $update['message']['text'];
$nombre   = $update['message']['chat']['first_name'];

if (strpos($texto, '/start') === 0) {
    $partes = explode(' ', $texto);
    
    if (isset($partes[1])) {
        $codigo = strtoupper(trim($partes[1]));
        
        $stmt = $conn->prepare("SELECT * FROM alumnos WHERE codigo = ?");
        $stmt->bind_param("s", $codigo);
        $stmt->execute();
        $alumno = $stmt->get_result()->fetch_assoc();
        
        if ($alumno) {
            $stmt2 = $conn->prepare("UPDATE alumnos SET tutor_chat_id = ? WHERE codigo = ?");
            $stmt2->bind_param("is", $chat_id, $codigo);
            $stmt2->execute();
            
            $mensaje = "✅ ¡Listo, {$nombre}!\n\nAhora recibirás notificaciones de:\n👤 *{$alumno['nombre']}*\n🎓 Grado: {$alumno['grado']}\n\nCada vez que entre o salga de la escuela te avisaré. 🏫";
        } else {
            $mensaje = "❌ Código no encontrado. Verifica el código con la escuela.\n\nEjemplo: /start ALU001";
        }
    } else {
        $mensaje = "👋 ¡Hola {$nombre}! Bienvenido a ChecaBot.\n\nPara recibir notificaciones escribe:\n/start CODIGO\n\nEjemplo:\n/start ALU001\n\n📞 Solicita tu código a la escuela.";
    }
    
    $url  = "https://api.telegram.org/bot{$token}/sendMessage";
    $data = [
        "chat_id"    => $chat_id,
        "text"       => $mensaje,
        "parse_mode" => "Markdown"
    ];
    
    $opciones = [
        "http" => [
            "header"  => "Content-Type: application/json\r\n",
            "method"  => "POST",
            "content" => json_encode($data)
        ]
    ];
    
    $context = stream_context_create($opciones);
    file_get_contents($url, false, $context);
}

http_response_code(200);
echo "ok";
?>
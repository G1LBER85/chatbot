<?php
$t0 = microtime(true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/redis.php';

$update = json_decode(file_get_contents('php://input'), true);

if (!$update) {
    http_response_code(200);
    echo 'ok';
    exit;
}

// Responder de inmediato a Telegram para que el webhook no expire
http_response_code(200);
header('Content-Type: text/plain; charset=utf-8');
echo 'ok';
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

// ========== MENSAJES DE TEXTO ==========
if (isset($update['message']['text'])) {
    $chat_id = $update['message']['chat']['id'];
    $texto   = trim($update['message']['text']);
    $nombre  = $update['message']['chat']['first_name'] ?? 'tutor';

    if (strtoupper($texto) === '/REGISTRO') {
        encolarMensaje($chat_id, obtenerTextoRegistro());
    } elseif (strpos($texto, '/start') === 0) {
        procesarRegistroCurp($chat_id, $texto, $nombre, $conn);
    } else {
        encolarMenu($chat_id, $conn);
    }
}

// ========== CALLBACK QUERY (BOTONES) ==========
if (isset($update['callback_query'])) {
    $callback = $update['callback_query'];
    $query_id = $callback['id'];
    $chat_id  = $callback['message']['chat']['id'] ?? $callback['from']['id'];
    $data_btn = $callback['data'] ?? '';

    // 1) Responder PRIMERO al callback (así el ID no caduca)
    responderCallback($query_id, '✅ Enviado');

    // 2) Luego procesar y encolar el contenido
    $stmt = $conn->prepare("SELECT CURP FROM alumnos WHERE tutor_chat_id = ?");
    $stmt->bind_param("i", $chat_id);
    $stmt->execute();
    $alumno = $stmt->get_result()->fetch_assoc();

    if (!$alumno) {
        encolarMensaje($chat_id, "⚠️ *Aún no estás registrado*\n\n" . obtenerTextoRegistro());

    } elseif ($data_btn === 'registro_alumno') {
        // Opción dinámica (no está en la tabla telegram_respuestas): consulta
        // en vivo las entradas y salidas del alumno de ESTE tutor.
        encolarRegistroAlumno($chat_id, $conn);

    } elseif (!preg_match('/^opcion_(\d+)$/', $data_btn, $coincidencia)) {
        encolarMenu($chat_id, $conn);

    } else {
        $numero_opcion = (int) $coincidencia[1];
        $respuesta     = obtenerRespuesta($numero_opcion, $conn);

        if ($respuesta && !empty($respuesta['titulo']) && (int) $respuesta['activo'] === 1) {
            encolarMensaje(
                $chat_id,
                "📌 *" . escaparMd($respuesta['titulo']) . "*\n\n" . escaparMd($respuesta['respuesta_texto'])
            );

            if (!empty($respuesta['ruta_imagen'])) {
                encolarFoto($chat_id, URL_BASE . $respuesta['ruta_imagen'], "📸");
            }
        } else {
            encolarMensaje($chat_id, "⚠️ Esa opción ya no está disponible. Aquí tienes el menú actualizado:");
            encolarMenu($chat_id, $conn);
        }
    }
}

// ========== FUNCIONES AUXILIARES ==========

function escaparMd($texto) {
    return str_replace(
        ['_', '*', '`', '['],
        ['\_', '\*', '\`', '\['],
        (string) $texto
    );
}

function encolarMensaje($chat_id, $texto) {
    global $redis;
    $redis->agregarACola([
        'tipo'    => 'mensaje',
        'chat_id' => $chat_id,
        'texto'   => $texto
    ]);
}

function encolarFoto($chat_id, $url_imagen, $caption) {
    global $redis;
    $redis->agregarACola([
        'tipo'       => 'foto',
        'chat_id'    => $chat_id,
        'url_imagen' => $url_imagen,
        'caption'    => $caption
    ]);
}

function encolarMenu($chat_id, $conn) {
    global $redis;
    $redis->agregarACola([
        'tipo'    => 'menu',
        'chat_id' => $chat_id,
        'texto'   => "👋 ¿Qué necesitas?\n\nSelecciona una opción:",
        'botones' => obtenerBotonesMenu($conn, $chat_id)
    ]);
}

function obtenerBotonesMenu($conn, $chat_id) {
    $botones = [];

    // Pregunta fija DINÁMICA: solo aparece si este tutor ya está registrado
    // (tiene al menos un alumno con su chat_id). Va primero en la lista.
    // Para quitarla de arriba y ponerla al final, mover este bloque
    // debajo del while de más abajo.
    if (tutorRegistrado($chat_id, $conn)) {
        $botones[] = [[
            "text"          => "🕐 Registro de entrada y salida de tu alumno",
            "callback_data" => "registro_alumno"
        ]];
    }

    $resultado = $conn->query(
        "SELECT numero, titulo FROM telegram_respuestas
         WHERE activo = 1 AND titulo != '' ORDER BY numero ASC"
    );
    while ($fila = $resultado->fetch_assoc()) {
        $botones[] = [[
            "text"          => $fila['titulo'],
            "callback_data" => "opcion_" . $fila['numero']
        ]];
    }
    return $botones;
}

// ========== REGISTRO DE ENTRADA Y SALIDA DEL ALUMNO ==========

// ¿Este chat ya está vinculado a algún alumno?
// El chat_id viene de Telegram (no se puede falsificar desde el texto del
// mensaje), así que un tutor nunca ve información de alumnos que no son suyos.
function tutorRegistrado($chat_id, $conn) {
    $stmt = $conn->prepare("SELECT 1 FROM alumnos WHERE tutor_chat_id = ? LIMIT 1");
    $stmt->bind_param("i", $chat_id);
    $stmt->execute();
    $existe = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $existe;
}

// Rango de los "registros semanales", calculado en MySQL para que coincida
// con el reloj con el que se guardaron los registros:
//   desde = el LUNES DE LA SEMANA PASADA
//   hasta = hoy
//   lunes = el lunes de ESTA semana (separa "esta semana" de "semana pasada")
// No importa qué día lo soliciten, siempre llega hasta el lunes anterior:
//   lunes   02/11/2026 -> desde 26/10/2026
//   viernes 06/11/2026 -> desde 26/10/2026
// (WEEKDAY: lunes = 0 ... domingo = 6. La semana empieza en lunes.)
function obtenerRangoSemanal($conn) {
    return $conn->query(
        "SELECT DATE_SUB(CURDATE(), INTERVAL (WEEKDAY(CURDATE()) + 7) DAY) AS desde,
                DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)       AS lunes,
                CURDATE()                                                  AS hasta"
    )->fetch_assoc();
}

// Manda los registros de cada alumno vinculado a este chat (por ejemplo,
// hermanos con el mismo Telegram). Cada alumno va en su propio mensaje, y si
// un alumno tiene tantos registros que no caben en uno, se parte en varios.
function encolarRegistroAlumno($chat_id, $conn) {
    $stmt = $conn->prepare(
        "SELECT id, nombre, grado, grupo FROM alumnos
         WHERE tutor_chat_id = ? ORDER BY nombre ASC"
    );
    $stmt->bind_param("i", $chat_id);
    $stmt->execute();
    $alumnos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (!$alumnos) {
        encolarMensaje($chat_id, "⚠️ *Aún no estás registrado*\n\n" . obtenerTextoRegistro());
        return;
    }

    $rango = obtenerRangoSemanal($conn);

    foreach ($alumnos as $alumno) {
        $registros = obtenerRegistrosDelPeriodo($alumno['id'], $rango['desde'], $conn);

        foreach (armarTextosRegistros($alumno, $registros, $rango) as $texto) {
            encolarMensaje($chat_id, $texto);
        }
    }
}

// Los registros del alumno desde $desde (AAAA-MM-DD) hasta ahora, del más
// reciente al más viejo. "es_hoy" se calcula en MySQL (CURDATE) para que
// "hoy" sea el mismo día con el que se guardaron los registros, sin depender
// de la zona horaria de PHP. El LIMIT es solo un tope de seguridad.
function obtenerRegistrosDelPeriodo($alumno_id, $desde, $conn) {
    $desde_hora = $desde . ' 00:00:00';

    $stmt = $conn->prepare(
        "SELECT tipo, fecha_hora, (DATE(fecha_hora) = CURDATE()) AS es_hoy
         FROM registros
         WHERE alumno_id = ? AND fecha_hora >= ?
         ORDER BY fecha_hora DESC, id DESC
         LIMIT 300"
    );
    $stmt->bind_param("is", $alumno_id, $desde_hora);
    $stmt->execute();
    $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $filas;
}

// Da formato a los registros: "Esta semana" y "Semana pasada", cada día con
// sus entradas y salidas de la mañana a la tarde, el día más reciente primero.
// Devuelve una LISTA de textos: casi siempre uno solo, pero si no cabe en un
// mensaje de Telegram (tope de 4096 caracteres) se parte por días.
// No toca la base de datos (solo da formato).
function armarTextosRegistros($alumno, $registros, $rango) {
    $cabecera = "🕐 *Registro de entrada y salida*\n"
              . "👤 *" . escaparMd($alumno['nombre']) . "*\n"
              . "🎓 Grado: " . escaparMd($alumno['grado'] . $alumno['grupo']) . "\n"
              . "📅 Del " . date('d/m/Y', strtotime($rango['desde']))
              . " al " . date('d/m/Y', strtotime($rango['hasta'])) . "\n";

    // Agrupar por día (llegan del más reciente al más viejo)
    $dias = [];
    foreach ($registros as $r) {
        $dia = substr($r['fecha_hora'], 0, 10);
        if (!isset($dias[$dia])) {
            $dias[$dia] = ['hoy' => ((int) $r['es_hoy']) === 1, 'filas' => []];
        }
        $dias[$dia]['filas'][] = $r;
    }

    if (!$dias) {
        return [$cabecera . "\nNo hay registros de entrada o salida en este periodo."];
    }

    $aviso = reset($dias)['hoy'] ? '' : "\n📍 Hoy aún no hay registros.\n";

    // Un bloque de texto por día, con la sección a la que pertenece
    $nombresDia = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
    $bloques = [];

    foreach ($dias as $dia => $info) {
        $fecha = date('d/m/Y', strtotime($dia));
        $etiqueta = $info['hoy']
            ? "Hoy · " . $fecha
            : $nombresDia[(int) date('w', strtotime($dia))] . " " . $fecha;

        $texto = "\n*" . $etiqueta . "*\n";

        // Dentro del día, de la primera hora a la última
        foreach (array_reverse($info['filas']) as $r) {
            $hora = date('h:i A', strtotime($r['fecha_hora']));
            $texto .= ($r['tipo'] === 'entrada' ? "✅ Entrada" : "🚪 Salida") . " — " . $hora . "\n";
        }

        $bloques[] = [
            // Las fechas AAAA-MM-DD se comparan bien como texto
            'seccion' => ($dia >= $rango['lunes']) ? "📆 *Esta semana*" : "📆 *Semana pasada*",
            'texto'   => $texto
        ];
    }

    // Juntar los días en mensajes de máximo ~3500 caracteres (con margen
    // bajo el tope de 4096 de Telegram)
    $mensajes = [];
    $actual = $cabecera . $aviso;
    $conDias = false;          // ¿el mensaje que se está armando ya trae algún día?
    $seccionActual = '';

    foreach ($bloques as $b) {
        $titulo = ($b['seccion'] !== $seccionActual) ? "\n" . $b['seccion'] . "\n" : '';

        if ($conDias && mb_strlen($actual . $titulo . $b['texto'], 'UTF-8') > 3500) {
            $mensajes[] = rtrim($actual);
            $actual = "🕐 *Registro de entrada y salida* (continuación)\n"
                    . "👤 *" . escaparMd($alumno['nombre']) . "*\n";
            $conDias = false;
            $titulo = "\n" . $b['seccion'] . "\n";
        }

        $actual .= $titulo . $b['texto'];
        $seccionActual = $b['seccion'];
        $conDias = true;
    }

    $mensajes[] = rtrim($actual);

    return $mensajes;
}

function obtenerRespuesta($numero, $conn) {
    global $redis;

    $respuesta = $redis->getRespuesta($numero);
    if ($respuesta) {
        return $respuesta;
    }

    $stmt = $conn->prepare("SELECT * FROM telegram_respuestas WHERE numero = ?");
    $stmt->bind_param("i", $numero);
    $stmt->execute();
    $respuesta = $stmt->get_result()->fetch_assoc();

    if ($respuesta) {
        $redis->setRespuesta($numero, $respuesta);
    }
    return $respuesta;
}

function responderCallback($query_id, $texto) {
    $url = "https://api.telegram.org/bot" . TELEGRAM_TOKEN . "/answerCallbackQuery";
    $data = [
        "callback_query_id" => $query_id,
        "text"              => $texto,
        "show_alert"        => false
    ];
    $contexto = stream_context_create([
        "http" => [
            "header"        => "Content-Type: application/json\r\n",
            "method"        => "POST",
            "content"       => json_encode($data),
            "ignore_errors" => true,
            "timeout"       => 5
        ]
    ]);
    $resp = @file_get_contents($url, false, $contexto);
    if ($resp === false || empty(json_decode($resp, true)['ok'])) {
        error_log("ERROR answerCallbackQuery: " . var_export($resp, true));
    }
}

function obtenerTextoRegistro() {
    return "📝 *Registro de Tutor*\n\n"
         . "Para registrarte y recibir notificaciones, envía:\n\n"
         . "*/start CURP*\n\n"
         . "Ejemplo:\n"
         . "`/start JPXM900115HDFXXX00`\n\n"
         . "⚠️ Usa el CURP del alumno (18 caracteres)\n"
         . "📞 Solicítalo a la escuela si no lo tienes.";
}

function procesarRegistroCurp($chat_id, $texto, $nombre, $conn) {
    global $redis;

    $partes = preg_split('/\s+/', $texto);

    if (!isset($partes[1]) || $partes[1] === '') {
        encolarMensaje($chat_id, obtenerTextoRegistro());
        return;
    }

    $curp = strtoupper(trim($partes[1]));

    $stmt = $conn->prepare("SELECT * FROM alumnos WHERE CURP = ?");
    $stmt->bind_param("s", $curp);
    $stmt->execute();
    $alumno = $stmt->get_result()->fetch_assoc();

    if (!$alumno) {
        encolarMensaje(
            $chat_id,
            "❌ *CURP no encontrado*\n\nVerifica el CURP e intenta de nuevo.\n\nEjemplo:\n`/start JPXM900115HDFXXX00`"
        );
        return;
    }

    $stmt2 = $conn->prepare("UPDATE alumnos SET tutor_chat_id = ? WHERE CURP = ?");
    $stmt2->bind_param("is", $chat_id, $curp);
    $stmt2->execute();

    $redis->setChatId($curp, $chat_id);

    encolarMensaje(
        $chat_id,
        "✅ *¡Listo, " . escaparMd($nombre) . "!*\n\n"
        . "Ahora recibirás notificaciones de:\n"
        . "*" . escaparMd($alumno['nombre']) . "*\n"
        . "🎓 Grado: " . escaparMd($alumno['grado'] . $alumno['grupo'])
    );

    encolarMenu($chat_id, $conn);
}       
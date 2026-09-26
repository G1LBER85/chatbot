<?php
// ═══════════════════════════════════════════════════════════════
// panel/credenciales/proceso_de_credenciales/progreso.php
// ─────────────────────────────────────────────────────────────
// Se llama así: progreso.php?token=xxxx . Devuelve en JSON cuántas
// credenciales lleva hechas la generación identificada por ese
// token (el mismo que se mandó en el POST a generar.php), mientras
// esa generación sigue en curso. panel/credenciales.php le
// pregunta a este archivo cada cierto tiempo (con setInterval) para
// ir actualizando el contador que se ve en el modal.
//
// Si el archivo de progreso todavía no existe (por ejemplo, apenas
// se está armando la consulta a la base de datos) o ya se borró
// (porque la generación ya terminó), simplemente se responde
// "0 de 0" — no es un error, el JavaScript lo interpreta como "aún
// no hay nada que mostrar".
// ═══════════════════════════════════════════════════════════════

header('Content-Type: application/json; charset=utf-8');

// Mismo filtro que en generar.php: el token solo sirve para nombrar
// un archivo, así que se limpia por seguridad antes de usarlo en
// una ruta.
$token = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['token'] ?? '');

if ($token === '') {
    echo json_encode(['hechos' => 0, 'total' => 0]);
    exit;
}

$ruta = __DIR__ . '/progreso/' . $token . '.json';

if (is_file($ruta)) {
    // generar.php ya escribió ahí JSON válido — se entrega tal cual,
    // sin necesidad de leerlo y re-codificarlo.
    readfile($ruta);
} else {
    echo json_encode(['hechos' => 0, 'total' => 0]);
}
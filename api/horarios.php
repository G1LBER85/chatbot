<?php
header('Content-Type: application/json; charset=utf-8');
// Horarios permitidos del plantel

$HORARIOS = [
    'entrada' => ['inicio' => 6, 'fin' => 12],
    'salida'  => ['inicio' => 12, 'fin' => 15]
];

echo json_encode($HORARIOS, JSON_UNESCAPED_UNICODE);
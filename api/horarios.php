<?php
header('Content-Type: application/json; charset=utf-8');
// Horarios permitidos del plantel

$HORARIOS = [
    'entrada' => ['inicio' => 18,  'fin' => 20],
    'salida'  => ['inicio' => 21, 'fin' => 23]
];

echo json_encode($HORARIOS, JSON_UNESCAPED_UNICODE);
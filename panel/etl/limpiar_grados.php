<?php

date_default_timezone_set('America/Mexico_City');

require_once __DIR__ . '/../../conexion.php';

header(
    'Content-Type: application/json; charset=utf-8'
);


/* =====================================================
   FUNCIÓN DE RESPUESTA
===================================================== */

function responder(
    bool $ok,
    string $mensaje,
    array $datos = []
) {

    echo json_encode(
        array_merge(
            [
                'ok' => $ok,
                'mensaje' => $mensaje
            ],
            $datos
        ),
        JSON_UNESCAPED_UNICODE
    );

    exit;

}


/* =====================================================
   VALIDAR MÉTODO
===================================================== */

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {

    responder(
        false,
        'Método no permitido.'
    );

}


/* =====================================================
   OBTENER ACCIÓN
===================================================== */

$accion =
    $_POST['accion'] ?? '';


if (
    !in_array(
        $accion,
        [
            'contar',
            'eliminar'
        ],
        true
    )
) {

    responder(
        false,
        'Acción no válida.'
    );

}


/* =====================================================
   VALIDAR GRADOS
===================================================== */

if (
    !isset($_POST['grados'])
    ||
    !is_array($_POST['grados'])
    ||
    empty($_POST['grados'])
) {

    responder(
        false,
        'No se seleccionó ningún grado.'
    );

}


$gradosPermitidos = [
    '1',
    '2',
    '3',
    '4',
    '5',
    '6'
];


$grados = [];


foreach (
    $_POST['grados']
    as $grado
) {

    $grado =
        trim((string)$grado);


    if (
        in_array(
            $grado,
            $gradosPermitidos,
            true
        )
    ) {

        $grados[] =
            $grado;

    }

}


/* =====================================================
   ELIMINAR DUPLICADOS
===================================================== */

$grados =
    array_values(
        array_unique($grados)
    );


if (
    empty($grados)
) {

    responder(
        false,
        'Los grados seleccionados no son válidos.'
    );

}


/* =====================================================
   CREAR PLACEHOLDERS
===================================================== */

$placeholders =
    implode(
        ',',
        array_fill(
            0,
            count($grados),
            '?'
        )
    );


$tipos =
    str_repeat(
        's',
        count($grados)
    );


/* =====================================================
   CONTAR ALUMNOS
===================================================== */

$sql = "

    SELECT
        grado,
        COUNT(*) AS cantidad

    FROM alumnos

    WHERE grado IN ($placeholders)

    GROUP BY grado

    ORDER BY grado

";


$stmt =
    $conn->prepare($sql);


if (!$stmt) {

    responder(
        false,
        'Error preparando la consulta: '
        . $conn->error
    );

}


$stmt->bind_param(
    $tipos,
    ...$grados
);


$stmt->execute();


$resultado =
    $stmt->get_result();


$detalle = [];

$total = 0;


while (
    $fila =
    $resultado->fetch_assoc()
) {

    $cantidad =
        (int)$fila['cantidad'];


    $detalle[] = [

        'grado' =>
            (int)$fila['grado'],

        'total' =>
            $cantidad

    ];


    $total +=
        $cantidad;

}


$stmt->close();


/* =====================================================
   ACCIÓN: CONTAR
===================================================== */

if (
    $accion === 'contar'
) {

    responder(
        true,
        'Conteo realizado correctamente.',
        [

            'total' =>
                $total,

            'grados' =>
                $detalle

        ]
    );

}


/* =====================================================
   VALIDAR CONFIRMACIÓN
===================================================== */

if (
    ($_POST['confirmacion'] ?? '')
    !==
    'ELIMINAR'
) {

    responder(
        false,
        'La eliminación no fue confirmada.'
    );

}


/* =====================================================
   SI NO HAY ALUMNOS
===================================================== */

if (
    $total === 0
) {

    responder(
        true,
        'No existen alumnos en los grados seleccionados.',
        [

            'total' => 0,

            'grados' =>
                $grados

        ]
    );

}


/* =====================================================
   INICIAR TRANSACCIÓN
===================================================== */

$conn->begin_transaction();


try {


    /* ================================================
       ELIMINAR ALUMNOS
    ================================================= */

    $sqlDelete = "

        DELETE FROM alumnos

        WHERE grado IN ($placeholders)

    ";


    $stmtDelete =
        $conn->prepare(
            $sqlDelete
        );


    if (!$stmtDelete) {

        throw new Exception(
            'Error preparando eliminación: '
            . $conn->error
        );

    }


    $stmtDelete->bind_param(
        $tipos,
        ...$grados
    );


    if (
        !$stmtDelete->execute()
    ) {

        throw new Exception(
            'No fue posible eliminar los alumnos: '
            . $stmtDelete->error
        );

    }


    $eliminados =
        $stmtDelete->affected_rows;


    $stmtDelete->close();


    /* ================================================
       CONFIRMAR TRANSACCIÓN
    ================================================= */

    $conn->commit();


    responder(
        true,
        "Se eliminaron correctamente {$eliminados} alumnos.",
        [

            'total' =>
                $eliminados,

            'grados' =>
                $grados

        ]
    );


} catch (
    Throwable $e
) {


    /* ================================================
       CANCELAR TRANSACCIÓN
    ================================================= */

    $conn->rollback();


    responder(
        false,
        'No se pudo completar la eliminación: '
        . $e->getMessage()
    );

}
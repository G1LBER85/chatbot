<?php

date_default_timezone_set('America/Mexico_City');

require_once __DIR__ . '/../../conexion.php';


// ==========================================
// VALIDAR GRADOS
// ==========================================

if (
    !isset($_POST['grados'])
    ||
    !is_array($_POST['grados'])
    ||
    empty($_POST['grados'])
) {
    die('No se seleccionaron grados.');
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


foreach ($_POST['grados'] as $grado) {

    $grado = trim((string)$grado);

    if (
        in_array(
            $grado,
            $gradosPermitidos,
            true
        )
    ) {

        $grados[] = $grado;

    }

}


$grados = array_unique($grados);


if (empty($grados)) {
    die('Grados inválidos.');
}


// ==========================================
// ELIMINAR
// ==========================================

$eliminados = 0;

$conn->begin_transaction();


try {

    foreach ($grados as $grado) {

        $stmt = $conn->prepare(
            "
            DELETE FROM alumnos
            WHERE grado = ?
            "
        );


        if (!$stmt) {

            throw new Exception(
                $conn->error
            );

        }


        $stmt->bind_param(
            's',
            $grado
        );


        if (!$stmt->execute()) {

            throw new Exception(
                $stmt->error
            );

        }


        $eliminados +=
            $stmt->affected_rows;


        $stmt->close();

    }


    $conn->commit();


} catch (Exception $e) {

    $conn->rollback();

    die(
        'No se pudieron eliminar los alumnos: '
        . $e->getMessage()
    );

}


$conn->close();

?>

<!DOCTYPE html>

<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Eliminación completada — ChecaBot
    </title>

    <link
        rel="stylesheet"
        href="../css/panel.css"
    >

    <style>

        body {

            margin: 0;

            background: #f3f6f9;

            font-family: Arial, sans-serif;

        }

        .contenedor {

            max-width: 650px;

            margin: 70px auto;

            padding: 20px;

        }

        .card {

            background: white;

            padding: 40px;

            border-radius: 15px;

            text-align: center;

            box-shadow:
                0 4px 16px rgba(0,0,0,.08);

        }

        .icono {

            font-size: 55px;

        }

        h1 {

            color: #287a51;

        }

        .cantidad {

            font-size: 40px;

            font-weight: bold;

            color: #287a51;

            margin: 20px 0;

        }

        .btn {

            display: inline-block;

            margin-top: 25px;

            padding: 13px 22px;

            border-radius: 8px;

            background: #109b94;

            color: white;

            text-decoration: none;

            font-weight: bold;

        }

    </style>

</head>


<body>

<div class="contenedor">

    <div class="card">

        <div class="icono">
            ✅
        </div>

        <h1>
            Limpieza completada
        </h1>

        <p>
            Los alumnos de los grados seleccionados
            fueron eliminados correctamente.
        </p>


        <div class="cantidad">

            <?= $eliminados ?>

        </div>


        <p>
            alumnos eliminados
        </p>


        <a
            href="../importar_alumnos.php"
            class="btn"
        >
            ← Regresar
        </a>

    </div>

</div>

</body>

</html>
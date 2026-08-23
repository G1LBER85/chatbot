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
    die('No se seleccionó ningún grado.');
}


// ==========================================
// PERMITIR ÚNICAMENTE 1 A 6
// ==========================================

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


// Quitar duplicados
$grados = array_unique($grados);


// Si después de validar no queda ninguno
if (empty($grados)) {
    die('Los grados seleccionados no son válidos.');
}


// ==========================================
// OBTENER CANTIDAD POR GRADO
// ==========================================

$conteos = [];

$total = 0;


foreach ($grados as $grado) {

    $stmt = $conn->prepare(
        "
        SELECT COUNT(*) AS cantidad
        FROM alumnos
        WHERE grado = ?
        "
    );

    if (!$stmt) {
        die(
            'Error preparando consulta: '
            . $conn->error
        );
    }


    $stmt->bind_param(
        's',
        $grado
    );


    $stmt->execute();


    $resultado =
        $stmt->get_result();


    $fila =
        $resultado->fetch_assoc();


    $cantidad =
        (int)$fila['cantidad'];


    $conteos[$grado] =
        $cantidad;


    $total += $cantidad;


    $stmt->close();
}


// ==========================================
// SI NO HAY ALUMNOS
// ==========================================

if ($total === 0) {

    ?>

    <!DOCTYPE html>

    <html lang="es">

    <head>

        <meta charset="UTF-8">

        <title>
            Limpiar alumnos — ChecaBot
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
                max-width: 700px;
                margin: 60px auto;
                padding: 20px;
            }

            .card {
                background: white;
                padding: 35px;
                border-radius: 15px;
                text-align: center;
                box-shadow:
                    0 4px 15px rgba(0,0,0,.07);
            }

            .icono {
                font-size: 50px;
            }

            .btn {
                display: inline-block;
                margin-top: 25px;
                padding: 12px 20px;
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
                ℹ️
            </div>

            <h1>
                No hay alumnos para eliminar
            </h1>

            <p>
                Los grados seleccionados
                no tienen alumnos registrados.
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

    <?php

    exit;
}


// ==========================================
// MOSTRAR CONFIRMACIÓN
// ==========================================

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
        Confirmar eliminación — ChecaBot
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

            color: #334e68;

        }

        .contenedor {

            max-width: 750px;

            margin: 50px auto;

            padding: 20px;

        }

        .card {

            background: white;

            padding: 35px;

            border-radius: 15px;

            box-shadow:
                0 4px 16px rgba(0,0,0,.08);

        }

        .titulo {

            display: flex;

            align-items: center;

            gap: 15px;

            margin-bottom: 25px;

        }

        .icono {

            font-size: 42px;

        }

        .titulo h1 {

            margin: 0;

            color: #9b3d3d;

        }

        .advertencia {

            padding: 18px;

            background: #fff5f5;

            border: 1px solid #f1b7b7;

            border-radius: 10px;

            color: #8f3434;

            margin-bottom: 25px;

        }

        table {

            width: 100%;

            border-collapse: collapse;

            margin-top: 20px;

        }

        th,
        td {

            padding: 13px;

            border-bottom:
                1px solid #e5eaee;

            text-align: left;

        }

        th {

            background: #f3f6f8;

        }

        .total {

            font-size: 18px;

            font-weight: bold;

        }

        .acciones {

            display: flex;

            justify-content: space-between;

            margin-top: 30px;

            gap: 15px;

        }

        .btn {

            padding: 12px 20px;

            border-radius: 8px;

            border: none;

            text-decoration: none;

            font-weight: bold;

            cursor: pointer;

        }

        .cancelar {

            background: #e7edf1;

            color: #334e68;

        }

        .eliminar {

            background: #d64545;

            color: white;

        }

        .eliminar:hover {

            background: #bd3737;

        }

    </style>

</head>


<body>

<div class="contenedor">

    <div class="card">

        <div class="titulo">

            <div class="icono">
                ⚠️
            </div>

            <div>

                <h1>
                    Confirmar eliminación
                </h1>

                <p>
                    Revisa cuidadosamente
                    antes de continuar.
                </p>

            </div>

        </div>


        <div class="advertencia">

            <strong>
                Atención:
            </strong>

            Los alumnos de los grados seleccionados
            serán eliminados permanentemente de
            la base de datos.

        </div>


        <table>

            <thead>

                <tr>

                    <th>
                        Grado
                    </th>

                    <th>
                        Alumnos
                    </th>

                </tr>

            </thead>


            <tbody>

            <?php foreach ($conteos as $grado => $cantidad): ?>

                <tr>

                    <td>

                        <strong>
                            <?= htmlspecialchars($grado) ?>°
                        </strong>

                    </td>

                    <td>
                        <?= $cantidad ?>
                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>


            <tfoot>

                <tr>

                    <td class="total">
                        TOTAL
                    </td>

                    <td class="total">
                        <?= $total ?>
                    </td>

                </tr>

            </tfoot>

        </table>


        <!-- ==================================
             FORMULARIO FINAL
        =================================== -->

        <form
            action="eliminar_grados.php"
            method="POST"
        >

            <?php foreach ($grados as $grado): ?>

                <input
                    type="hidden"
                    name="grados[]"
                    value="<?= htmlspecialchars($grado) ?>"
                >

            <?php endforeach; ?>


            <div class="acciones">

                <a
                    href="../importar_alumnos.php"
                    class="btn cancelar"
                >
                    ← Cancelar
                </a>


                <button
                    type="submit"
                    class="btn eliminar"
                >
                    🗑️ Eliminar <?= $total ?> alumnos
                </button>

            </div>

        </form>

    </div>

</div>

</body>

</html>
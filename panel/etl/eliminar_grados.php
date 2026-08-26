<?php

date_default_timezone_set('America/Mexico_City');

require_once __DIR__ . '/../../conexion.php';


// =====================================================
// VALIDAR QUE SE RECIBIERON GRADOS
// =====================================================

if (
    !isset($_POST['grados']) ||
    !is_array($_POST['grados']) ||
    empty($_POST['grados'])
) {

    die('No se seleccionaron grados.');

}


// =====================================================
// GRADOS PERMITIDOS
// =====================================================

$gradosPermitidos = [
    '1',
    '2',
    '3',
    '4',
    '5',
    '6'
];


// =====================================================
// LIMPIAR Y VALIDAR GRADOS
// =====================================================

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


// =====================================================
// VERIFICAR QUE QUEDEN GRADOS VÁLIDOS
// =====================================================

if (empty($grados)) {

    die('Los grados seleccionados no son válidos.');

}


// =====================================================
// VARIABLES
// =====================================================

$eliminados = 0;

$conteosEliminados = [];


// =====================================================
// INICIAR TRANSACCIÓN
// =====================================================

$conn->begin_transaction();


try {


    // =================================================
    // ELIMINAR CADA GRADO
    // =================================================

    foreach ($grados as $grado) {


        // ---------------------------------------------
        // 1. Eliminar registros relacionados a los
        //    alumnos de este grado
        //
        //    IMPORTANTE: esto debe ejecutarse ANTES de
        //    borrar los alumnos, porque "registros"
        //    tiene una foreign key hacia "alumnos".
        // ---------------------------------------------

        $stmtRegistros = $conn->prepare(
            "
            DELETE r FROM registros r
            INNER JOIN alumnos a ON a.id = r.alumno_id
            WHERE a.grado = ?
            "
        );


        if (!$stmtRegistros) {

            throw new Exception(
                'Error preparando eliminación de registros: '
                . $conn->error
            );

        }


        $stmtRegistros->bind_param(
            's',
            $grado
        );


        if (!$stmtRegistros->execute()) {

            throw new Exception(
                'Error eliminando registros del grado '
                . $grado
                . ': '
                . $stmtRegistros->error
            );

        }


        $stmtRegistros->close();


        // ---------------------------------------------
        // 2. Eliminar alumnos del grado
        // ---------------------------------------------

        $stmt = $conn->prepare(
            "
            DELETE FROM alumnos
            WHERE grado = ?
            "
        );


        if (!$stmt) {

            throw new Exception(
                'Error preparando la eliminación: '
                . $conn->error
            );

        }


        // ---------------------------------------------
        // Vincular grado
        // ---------------------------------------------

        $stmt->bind_param(
            's',
            $grado
        );


        // ---------------------------------------------
        // Ejecutar
        // ---------------------------------------------

        if (!$stmt->execute()) {

            throw new Exception(
                'Error eliminando el grado '
                . $grado
                . ': '
                . $stmt->error
            );

        }


        // ---------------------------------------------
        // Cantidad eliminada
        // ---------------------------------------------

        $cantidad =
            $stmt->affected_rows;


        $conteosEliminados[$grado] =
            $cantidad;


        $eliminados +=
            $cantidad;


        $stmt->close();

    }


    // =================================================
    // CONFIRMAR TRANSACCIÓN
    // =================================================

    $conn->commit();


} catch (Exception $e) {


    // =================================================
    // DESHACER TODO SI OCURRE UN ERROR
    // =================================================

    $conn->rollback();


    die(
        'No se pudieron eliminar los alumnos: '
        . htmlspecialchars(
            $e->getMessage(),
            ENT_QUOTES,
            'UTF-8'
        )
    );

}


// =====================================================
// CERRAR CONEXIÓN
// =====================================================

$conn->close();


// =====================================================
// NOMBRES DE LOS GRADOS
// =====================================================

$nombresGrados = [

    '1' => 'Primer grado',
    '2' => 'Segundo grado',
    '3' => 'Tercer grado',
    '4' => 'Cuarto grado',
    '5' => 'Quinto grado',
    '6' => 'Sexto grado'

];

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

            color: #334e68;

        }


        .contenedor {

            max-width: 700px;

            margin: 60px auto;

            padding: 20px;

        }


        .card {

            background: white;

            padding: 40px;

            border-radius: 16px;

            text-align: center;

            box-shadow:
                0 4px 16px rgba(0,0,0,.08);

        }


        .icono {

            font-size: 60px;

            margin-bottom: 10px;

        }


        h1 {

            color: #287a51;

            margin-bottom: 10px;

        }


        .descripcion {

            color: #64748b;

            line-height: 1.6;

        }


        /* =================================================
           CANTIDAD TOTAL
        ================================================= */

        .cantidad {

            font-size: 46px;

            font-weight: bold;

            color: #287a51;

            margin: 20px 0 5px;

        }


        .cantidad-label {

            color: #64748b;

            margin-bottom: 30px;

        }


        /* =================================================
           RESUMEN
        ================================================= */

        .resumen {

            text-align: left;

            margin-top: 25px;

            border: 1px solid #e2e8f0;

            border-radius: 12px;

            overflow: hidden;

        }


        .resumen-titulo {

            padding: 14px 18px;

            background: #f5f7f9;

            font-weight: bold;

            color: #334e68;

        }


        .resumen-fila {

            display: flex;

            justify-content: space-between;

            align-items: center;

            padding: 13px 18px;

            border-top:
                1px solid #e5eaee;

        }


        .resumen-grado {

            display: flex;

            flex-direction: column;

            gap: 3px;

        }


        .resumen-grado strong {

            color: #29465f;

        }


        .resumen-grado span {

            font-size: 13px;

            color: #718096;

        }


        .resumen-cantidad {

            font-weight: bold;

            color: #287a51;

        }


        /* =================================================
           MENSAJE SIN ALUMNOS
        ================================================= */

        .sin-alumnos {

            margin-top: 25px;

            padding: 15px;

            background: #f8fafc;

            border: 1px solid #e2e8f0;

            border-radius: 10px;

            color: #64748b;

        }


        /* =================================================
           BOTÓN
        ================================================= */

        .btn {

            display: inline-block;

            margin-top: 30px;

            padding: 13px 22px;

            border-radius: 9px;

            background: #109b94;

            color: white;

            text-decoration: none;

            font-weight: bold;

            transition: .2s;

        }


        .btn:hover {

            background: #087f78;

        }


    </style>

</head>


<body>


<div class="contenedor">


    <div class="card">


        <!-- =============================================
             ICONO
        ============================================== -->

        <div class="icono">
            ✅
        </div>


        <!-- =============================================
             TITULO
        ============================================== -->

        <h1>
            Limpieza completada
        </h1>


        <p class="descripcion">

            Los alumnos de los grados seleccionados
            fueron procesados correctamente.

        </p>


        <!-- =============================================
             TOTAL
        ============================================== -->

        <div class="cantidad">

            <?= $eliminados ?>

        </div>


        <div class="cantidad-label">

            alumnos eliminados

        </div>


        <!-- =============================================
             RESUMEN POR GRADO
        ============================================== -->

        <div class="resumen">


            <div class="resumen-titulo">

                Detalle de la eliminación

            </div>


            <?php foreach ($conteosEliminados as $grado => $cantidad): ?>


                <div class="resumen-fila">


                    <div class="resumen-grado">

                        <strong>

                            <?= htmlspecialchars(
                                $grado,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>°

                        </strong>


                        <span>

                            <?= htmlspecialchars(
                                $nombresGrados[$grado],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </span>

                    </div>


                    <div class="resumen-cantidad">

                        <?= $cantidad ?>

                        <?= $cantidad === 1
                            ? 'alumno'
                            : 'alumnos'
                        ?>

                    </div>


                </div>


            <?php endforeach; ?>


        </div>


        <!-- =============================================
             SI NO SE ELIMINÓ NINGÚN REGISTRO
        ============================================== -->

        <?php if ($eliminados === 0): ?>

            <div class="sin-alumnos">

                ℹ️ Los grados seleccionados
                no tenían alumnos registrados.

            </div>

        <?php endif; ?>


        <!-- =============================================
             REGRESAR
        ============================================== -->

        <a
            href="../importar_alumnos.php"
            class="btn"
        >

            ← Regresar a Importar alumnos

        </a>


    </div>


</div>


</body>

</html>
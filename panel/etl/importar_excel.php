<?php

date_default_timezone_set('America/Mexico_City');

session_start();

require_once __DIR__ . '/../../conexion.php';


// =====================================================
// 1. VALIDAR DATOS EN SESIÓN
// =====================================================
//
// Ya no leemos $_POST['datos_alumnos']. Los datos fueron
// guardados por analizar_excel.php directamente en la
// sesión, lo cual evita depender de post_max_size y de
// mandar un JSON enorme de ida y vuelta al navegador.
// =====================================================

if (
    !isset($_SESSION['importacion_alumnos'])
    ||
    !is_array($_SESSION['importacion_alumnos'])
) {

    die(
        'No hay datos de importación pendientes. '
        . 'Por favor analiza el archivo Excel nuevamente.'
    );

}


$alumnos = $_SESSION['importacion_alumnos'];

$nombreArchivo =
    $_SESSION['importacion_archivo']
    ?? 'archivo desconocido';


// =====================================================
// 2. CONTADORES
// =====================================================

$insertados = 0;

$actualizados = 0;

$sinCambios = 0;

$omitidos = 0;

$errores = [];


// =====================================================
// 3. INICIAR TRANSACCIÓN
// =====================================================

$conn->begin_transaction();


try {


    // =================================================
    // PREPARAR INSERT
    // =================================================

    $insertar = $conn->prepare(
        "
        INSERT INTO alumnos
        (
            nombre,
            grado,
            grupo,
            activo,
            CURP
        )
        VALUES
        (
            ?,
            ?,
            ?,
            1,
            ?
        )
        "
    );


    if (!$insertar) {

        throw new Exception(
            'Error preparando INSERT: '
            . $conn->error
        );

    }


    // =================================================
    // PREPARAR BUSCAR CURP
    // =================================================
    //
    // NOTA: aquí SÍ se mantiene una búsqueda individual
    // porque estamos dentro de una transacción de
    // escritura y necesitamos el dato más reciente justo
    // antes de decidir INSERT o UPDATE. La optimización
    // fuerte (la consulta en lote) ya se hizo en
    // analizar_excel.php, que es donde de verdad pesaba
    // con 1,200+ alumnos porque ahí se hacía dos veces
    // (una para comparar y otra aquí). Ahora solo se hace
    // una vez, aquí, al momento real de guardar.
    // =================================================

    $buscar = $conn->prepare(
        "
        SELECT
            id,
            nombre,
            grado,
            grupo,
            activo

        FROM alumnos

        WHERE CURP = ?

        LIMIT 1
        "
    );


    if (!$buscar) {

        throw new Exception(
            'Error preparando búsqueda: '
            . $conn->error
        );

    }


    // =================================================
    // PREPARAR UPDATE
    // =================================================

    $actualizar = $conn->prepare(
        "
        UPDATE alumnos

        SET
            nombre = ?,
            grado = ?,
            grupo = ?,
            activo = 1

        WHERE CURP = ?
        "
    );


    if (!$actualizar) {

        throw new Exception(
            'Error preparando UPDATE: '
            . $conn->error
        );

    }


    // =================================================
    // 4. RECORRER ALUMNOS
    // =================================================

    foreach ($alumnos as $alumno) {


        // =============================================
        // IGNORAR REGISTROS INVÁLIDOS
        // =============================================

        if (
            !isset($alumno['valido'])
            ||
            !$alumno['valido']
        ) {

            $omitidos++;

            continue;

        }


        // =============================================
        // OBTENER DATOS
        // =============================================

        $nombre = trim(
            $alumno['nombre'] ?? ''
        );


        $grado = trim(
            $alumno['grado'] ?? ''
        );


        $grupo = strtoupper(
            trim(
                $alumno['grupo'] ?? ''
            )
        );


        $curp = strtoupper(
            trim(
                $alumno['curp'] ?? ''
            )
        );


        // =============================================
        // VALIDACIÓN FINAL
        // =============================================

        if (
            $nombre === ''
            ||
            $grado === ''
            ||
            $grupo === ''
            ||
            strlen($curp) !== 18
        ) {

            $omitidos++;

            continue;

        }


        // =============================================
        // BUSCAR CURP
        // =============================================

        $buscar->bind_param(
            's',
            $curp
        );


        $buscar->execute();


        $resultado =
            $buscar->get_result();


        // =============================================
        // CURP NUEVA
        // =============================================

        if (
            $resultado->num_rows === 0
        ) {


            $insertar->bind_param(
                'ssss',
                $nombre,
                $grado,
                $grupo,
                $curp
            );


            if (!$insertar->execute()) {

                throw new Exception(
                    'Error insertando '
                    . $nombre
                    . ': '
                    . $insertar->error
                );

            }


            $insertados++;

        }


        // =============================================
        // CURP EXISTENTE
        // =============================================

        else {


            $existente =
                $resultado->fetch_assoc();


            // -----------------------------------------
            // COMPROBAR SI CAMBIÓ
            // -----------------------------------------

            $cambio = false;


            // GRADO

            if (
                (string)$existente['grado']
                !==
                (string)$grado
            ) {

                $cambio = true;

            }


            // GRUPO

            if (
                strtoupper(
                    trim(
                        (string)$existente['grupo']
                    )
                )
                !==
                $grupo
            ) {

                $cambio = true;

            }


            // NOMBRE

            if (
                trim(
                    (string)$existente['nombre']
                )
                !==
                $nombre
            ) {

                $cambio = true;

            }


            // -----------------------------------------
            // ACTUALIZAR
            // -----------------------------------------

            if ($cambio) {


                $actualizar->bind_param(
                    'ssss',
                    $nombre,
                    $grado,
                    $grupo,
                    $curp
                );


                if (!$actualizar->execute()) {

                    throw new Exception(
                        'Error actualizando '
                        . $nombre
                        . ': '
                        . $actualizar->error
                    );

                }


                $actualizados++;

            }


            // -----------------------------------------
            // SIN CAMBIOS
            // -----------------------------------------

            else {

                $sinCambios++;

            }

        }


        $resultado->free();

    }


    // =================================================
    // 5. CERRAR CONSULTAS
    // =================================================

    $buscar->close();

    $insertar->close();

    $actualizar->close();


    // =================================================
    // 6. CONFIRMAR TRANSACCIÓN
    // =================================================

    $conn->commit();


} catch (Exception $e) {


    // =================================================
    // SI HAY ERROR, DESHACER TODO
    // =================================================

    $conn->rollback();


    die(
        'No se pudo completar la importación: '
        .
        htmlspecialchars(
            $e->getMessage()
        )
    );

}


$conn->close();


// =====================================================
// 7. LIMPIAR SESIÓN
// =====================================================
//
// Ya se usaron los datos, se eliminan para no dejarlos
// pegados en la sesión (evita que un F5 en esta misma
// página, u otra importación posterior sin pasar por
// analizar_excel.php, vuelva a insertar lo mismo).
// =====================================================

unset($_SESSION['importacion_alumnos']);

unset($_SESSION['importacion_archivo']);

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
        Resultado de importación — ChecaBot
    </title>


    <link
        rel="stylesheet"
        href="../css/panel.css"
    >


    <style>

        body {

            margin: 0;

            background: #f3f6f9;

            font-family:
                Arial,
                sans-serif;

            color: #334e68;

        }


        .contenedor {

            max-width: 900px;

            margin: 50px auto;

            padding: 20px;

        }


        .card {

            background: white;

            padding: 35px;

            border-radius: 15px;

            box-shadow:
                0 4px 16px
                rgba(0,0,0,.08);

        }


        .encabezado {

            display: flex;

            align-items: center;

            gap: 15px;

        }


        .icono {

            font-size: 45px;

        }


        .encabezado h1 {

            margin: 0;

        }


        .encabezado p {

            color: #7b8794;

        }


        .resumen {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 15px;

            margin-top: 30px;

        }


        .dato {

            padding: 20px;

            border-radius: 10px;

            background: #f5f8fa;

        }


        .dato span {

            display: block;

            color: #7b8794;

            font-size: 13px;

        }


        .dato strong {

            display: block;

            margin-top: 7px;

            font-size: 28px;

        }


        .verde {

            color: #1d8755;

        }


        .azul {

            color: #2474a8;

        }


        .amarillo {

            color: #a66c00;

        }


        .gris {

            color: #687785;

        }


        .explicacion {

            margin-top: 30px;

            padding: 18px;

            background: #eef8f7;

            border-radius: 10px;

            color: #356c68;

        }


        .acciones {

            display: flex;

            gap: 12px;

            margin-top: 30px;

        }


        .btn {

            display: inline-block;

            padding: 12px 20px;

            border-radius: 8px;

            text-decoration: none;

            font-weight: 600;

        }


        .principal {

            background: #109b94;

            color: white;

        }


        .secundario {

            background: #e7edf1;

            color: #334e68;

        }


        @media (max-width: 800px) {

            .resumen {

                grid-template-columns:
                    1fr 1fr;

            }

        }


        @media (max-width: 550px) {

            .resumen {

                grid-template-columns:
                    1fr;

            }

        }

    </style>

</head>


<body>


<div class="contenedor">


    <div class="card">


        <div class="encabezado">

            <div class="icono">
                ✅
            </div>


            <div>

                <h1>
                    Importación completada
                </h1>


                <p>
                    El archivo
                    <strong>
                        <?= htmlspecialchars(
                            $nombreArchivo
                        ) ?>
                    </strong>
                    fue procesado correctamente.
                </p>

            </div>

        </div>


        <!-- =========================================
             RESUMEN
        ========================================== -->

        <div class="resumen">


            <div class="dato">

                <span>
                    🆕 Alumnos nuevos
                </span>

                <strong class="verde">
                    <?= $insertados ?>
                </strong>

            </div>


            <div class="dato">

                <span>
                    🔄 Alumnos actualizados
                </span>

                <strong class="azul">
                    <?= $actualizados ?>
                </strong>

            </div>


            <div class="dato">

                <span>
                    ✓ Sin cambios
                </span>

                <strong class="amarillo">
                    <?= $sinCambios ?>
                </strong>

            </div>


            <div class="dato">

                <span>
                    ⚠️ Omitidos
                </span>

                <strong class="gris">
                    <?= $omitidos ?>
                </strong>

            </div>


        </div>


        <!-- =========================================
             EXPLICACIÓN
        ========================================== -->

        <div class="explicacion">


            <strong>
                🔄 Resultado del proceso ETL
            </strong>


            <p>

                Las CURP que no existían fueron
                registradas como alumnos nuevos.

            </p>


            <p>

                Las CURP existentes que tenían
                cambios en nombre, grado o grupo
                fueron actualizadas.

            </p>


            <p>

                Los alumnos que ya tenían exactamente
                los mismos datos no fueron modificados.

            </p>


        </div>


        <!-- =========================================
             BOTONES
        ========================================== -->

        <div class="acciones">


            <a
                href="../importar_alumnos.php"
                class="btn principal"
            >
                📄 Importar otro Excel
            </a>


            <a
                href="../alumnos.php"
                class="btn secundario"
            >
                🎓 Ver alumnos
            </a>


        </div>


    </div>


</div>


</body>

</html>
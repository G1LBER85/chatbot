<?php

date_default_timezone_set('America/Mexico_City');

session_start();

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../conexion.php';

use PhpOffice\PhpSpreadsheet\IOFactory;


// =====================================================
// 1. VALIDAR ARCHIVO
// =====================================================

if (!isset($_FILES['archivo_excel'])) {
    die('No se recibió ningún archivo Excel.');
}

$archivo = $_FILES['archivo_excel'];

if ($archivo['error'] !== UPLOAD_ERR_OK) {
    die('Ocurrió un error al subir el archivo.');
}


// =====================================================
// 2. VALIDAR EXTENSIÓN
// =====================================================

$extension = strtolower(
    pathinfo(
        $archivo['name'],
        PATHINFO_EXTENSION
    )
);

$extensionesPermitidas = [
    'xlsx',
    'xls'
];

if (!in_array(
    $extension,
    $extensionesPermitidas,
    true
)) {
    die(
        'Solo se permiten archivos .xlsx o .xls'
    );
}


// =====================================================
// 3. LEER EXCEL
// =====================================================

try {

    $spreadsheet = IOFactory::load(
        $archivo['tmp_name']
    );

    $hoja = $spreadsheet->getActiveSheet();

    $filas = $hoja->toArray(
        null,
        true,
        true,
        false
    );

} catch (Exception $e) {

    die(
        'Error al leer el Excel: '
        . $e->getMessage()
    );
}


// =====================================================
// 4. EXTRAER Y TRANSFORMAR
// =====================================================

$alumnos = [];

$curpsExcel = [];


// -----------------------------------------------------
// IMPORTANTE
//
// Según el Excel que mostraste:
//
// A = Grado y grupo
// D = CURP
// E = Nombre
//
// PHP:
// A = índice 0
// D = índice 3
// E = índice 4
// -----------------------------------------------------

foreach ($filas as $indice => $fila) {


    // ================================================
    // OBTENER DATOS
    // ================================================

    $gradoGrupoOriginal = trim(
        (string)($fila[0] ?? '')
    );


    $curp = strtoupper(
        trim(
            (string)($fila[3] ?? '')
        )
    );


    $nombre = trim(
        (string)($fila[4] ?? '')
    );


    // ================================================
    // IGNORAR FILAS VACÍAS
    // ================================================

    if (
        $gradoGrupoOriginal === ''
        &&
        $curp === ''
        &&
        $nombre === ''
    ) {
        continue;
    }


    // ================================================
    // SEPARAR GRADO Y GRUPO
    //
    // Ejemplo:
    // 1A → grado 1 / grupo A
    // 2B → grado 2 / grupo B
    // ================================================

    $grado = '';

    $grupo = '';


    if (
        preg_match(
            '/^(\d+)\s*([A-Za-z]+)$/',
            $gradoGrupoOriginal,
            $coincidencias
        )
    ) {

        $grado =
            $coincidencias[1];

        $grupo =
            strtoupper(
                $coincidencias[2]
            );

    }


    // ================================================
    // LIMPIAR NOMBRE
    // ================================================

    $nombre = preg_replace(
        '/\s+/',
        ' ',
        $nombre
    );


    // ================================================
    // ERRORES
    // ================================================

    $errores = [];


    if ($grado === '') {

        $errores[] =
            'Grado inválido';

    }


    if ($grupo === '') {

        $errores[] =
            'Grupo inválido';

    }


    if ($nombre === '') {

        $errores[] =
            'Nombre vacío';

    }


    if ($curp === '') {

        $errores[] =
            'CURP vacía';

    } elseif (strlen($curp) !== 18) {

        $errores[] =
            'La CURP debe tener 18 caracteres';

    }


    // ================================================
    // DETECTAR CURP REPETIDA EN EL MISMO EXCEL
    // ================================================

    if ($curp !== '') {

        if (
            isset($curpsExcel[$curp])
        ) {

            $errores[] =
                'CURP repetida en el Excel ' .
                '(fila ' .
                $curpsExcel[$curp] .
                ')';

        } else {

            $curpsExcel[$curp] =
                $indice + 1;

        }

    }


    // ================================================
    // CREAR REGISTRO
    // ================================================

    $alumnos[] = [

        'fila' =>
            $indice + 1,

        'grado_grupo_original' =>
            $gradoGrupoOriginal,

        'grado' =>
            $grado,

        'grupo' =>
            $grupo,

        'nombre' =>
            $nombre,

        'curp' =>
            $curp,

        'errores' =>
            $errores,

        'valido' =>
            empty($errores),

        'accion' =>
            null,

        'id_existente' =>
            null,

        'grado_anterior' =>
            null,

        'grupo_anterior' =>
            null,

        'nombre_anterior' =>
            null,

        'cambios' =>
            []

    ];

}


// =====================================================
// 5. COMPARAR CONTRA MYSQL — CONSULTA EN LOTE
// =====================================================
//
// En vez de hacer un SELECT por cada alumno (lo cual con
// 1,200+ alumnos serían 1,200+ consultas), recolectamos
// TODAS las CURPs válidas del Excel y hacemos UNA sola
// consulta con WHERE CURP IN (...).
// =====================================================

// -----------------------------------------------------
// Recolectar CURPs únicas y válidas (18 caracteres)
// -----------------------------------------------------

$curpsParaBuscar = [];

foreach ($alumnos as $alumno) {

    if (
        $alumno['valido']
        &&
        $alumno['curp'] !== ''
    ) {

        $curpsParaBuscar[$alumno['curp']] = true;

    }

}

$curpsParaBuscar = array_keys($curpsParaBuscar);


// -----------------------------------------------------
// Buscar todos los existentes de una sola vez
// -----------------------------------------------------

$existentesPorCurp = [];

if (!empty($curpsParaBuscar)) {

    $placeholders = implode(
        ',',
        array_fill(0, count($curpsParaBuscar), '?')
    );

    $tipos = str_repeat('s', count($curpsParaBuscar));

    $sqlBuscar = "
        SELECT
            id,
            nombre,
            grado,
            grupo,
            activo,
            CURP

        FROM alumnos

        WHERE CURP IN ($placeholders)
    ";

    $stmtBuscar = $conn->prepare($sqlBuscar);

    if (!$stmtBuscar) {

        die(
            'Error preparando comparación: '
            . $conn->error
        );

    }

    $stmtBuscar->bind_param(
        $tipos,
        ...$curpsParaBuscar
    );

    $stmtBuscar->execute();

    $resultadoBuscar =
        $stmtBuscar->get_result();

    while (
        $filaExistente =
        $resultadoBuscar->fetch_assoc()
    ) {

        $existentesPorCurp[$filaExistente['CURP']] =
            $filaExistente;

    }

    $stmtBuscar->close();

}


$nuevos = [];

$actualizados = [];

$sinCambios = [];

$erroresImportacion = [];


// =====================================================
// 6. COMPARAR CADA ALUMNO (SIN CONSULTAS ADICIONALES)
// =====================================================

foreach ($alumnos as &$alumno) {


    // -------------------------------------------------
    // SI TIENE ERROR NO SE COMPARA
    // -------------------------------------------------

    if (!$alumno['valido']) {

        $erroresImportacion[] =
            $alumno;

        continue;

    }


    $curp =
        strtoupper(
            trim(
                $alumno['curp']
            )
        );


    // =================================================
    // CURP NO EXISTE
    // =================================================

    if (
        !isset($existentesPorCurp[$curp])
    ) {

        $alumno['accion'] =
            'nuevo';

        $nuevos[] =
            $alumno;

        continue;

    }


    // =================================================
    // CURP EXISTE
    // =================================================

    $existente =
        $existentesPorCurp[$curp];


    $alumno['id_existente'] =
        $existente['id'];


    $alumno['grado_anterior'] =
        $existente['grado'];


    $alumno['grupo_anterior'] =
        $existente['grupo'];


    $alumno['nombre_anterior'] =
        $existente['nombre'];


    // -------------------------------------------------
    // DETECTAR CAMBIOS
    // -------------------------------------------------

    $cambios = [];


    // GRADO

    if (
        (string)$existente['grado']
        !==
        (string)$alumno['grado']
    ) {

        $cambios[] =
            'grado';

    }


    // GRUPO

    if (
        strtoupper(
            trim(
                (string)$existente['grupo']
            )
        )
        !==
        strtoupper(
            trim(
                $alumno['grupo']
            )
        )
    ) {

        $cambios[] =
            'grupo';

    }


    // NOMBRE

    if (
        trim(
            (string)$existente['nombre']
        )
        !==
        trim(
            $alumno['nombre']
        )
    ) {

        $cambios[] =
            'nombre';

    }


    $alumno['cambios'] =
        $cambios;


    // =================================================
    // HAY CAMBIOS
    // =================================================

    if (!empty($cambios)) {

        $alumno['accion'] =
            'actualizar';

        $actualizados[] =
            $alumno;

    }


    // =================================================
    // SIN CAMBIOS
    // =================================================

    else {

        $alumno['accion'] =
            'sin_cambios';

        $sinCambios[] =
            $alumno;

    }

}

unset($alumno);


$conn->close();


// =====================================================
// 7. GUARDAR EN SESIÓN
// =====================================================
//
// En vez de mandar el JSON completo por un input hidden
// (lo cual con 1,200+ alumnos puede pesar demasiado y
// depende de post_max_size), guardamos el arreglo en
// $_SESSION. La página de confirmación solo necesita
// mandar la orden de "importar", no los datos.
// =====================================================

$_SESSION['importacion_alumnos'] = $alumnos;

$_SESSION['importacion_archivo'] = $archivo['name'];


// =====================================================
// 8. CONTADORES
// =====================================================

$total =
    count($alumnos);

$totalNuevos =
    count($nuevos);

$totalActualizados =
    count($actualizados);

$totalSinCambios =
    count($sinCambios);

$totalErrores =
    count($erroresImportacion);


// =====================================================
// 9. TEXTO DE CAMBIOS
// =====================================================

function textoCambios($cambios)
{

    $resultado = [];

    foreach ($cambios as $cambio) {

        if ($cambio === 'grado') {
            $resultado[] =
                'Grado';
        }

        if ($cambio === 'grupo') {
            $resultado[] =
                'Grupo';
        }

        if ($cambio === 'nombre') {
            $resultado[] =
                'Nombre';
        }

    }

    return implode(
        ', ',
        $resultado
    );
}

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
        Vista previa de importación — ChecaBot
    </title>


    <link
        rel="stylesheet"
        href="../css/panel.css"
    >


    <style>

        /* =====================================================
   MODAL DE CONFIRMACIÓN
===================================================== */

.modal-overlay {

    display: none;

    position: fixed;

    inset: 0;

    background:
        rgba(15, 30, 45, 0.65);

    backdrop-filter: blur(3px);

    z-index: 9999;

    align-items: center;

    justify-content: center;

    padding: 20px;

}


.modal-overlay.mostrar {

    display: flex;

}


.modal-importacion {

    position: relative;

    width: 100%;

    max-width: 520px;

    background: white;

    border-radius: 18px;

    padding: 35px;

    box-sizing: border-box;

    box-shadow:
        0 20px 60px
        rgba(0, 0, 0, 0.25);

    animation:
        aparecerModal
        .2s
        ease-out;

}


@keyframes aparecerModal {

    from {

        opacity: 0;

        transform:
            translateY(-20px)
            scale(.96);

    }

    to {

        opacity: 1;

        transform:
            translateY(0)
            scale(1);

    }

}


.modal-cerrar {

    position: absolute;

    top: 15px;

    right: 18px;

    width: 35px;

    height: 35px;

    border: none;

    background: transparent;

    font-size: 28px;

    color: #7b8794;

    cursor: pointer;

    border-radius: 50%;

}


.modal-cerrar:hover {

    background: #f0f3f5;

    color: #334e68;

}


.modal-icono {

    width: 65px;

    height: 65px;

    display: flex;

    align-items: center;

    justify-content: center;

    margin: 0 auto 15px;

    border-radius: 50%;

    background: #fff4df;

    font-size: 32px;

}


.modal-importacion h2 {

    margin: 0;

    text-align: center;

    color: #263f57;

    font-size: 25px;

}


.modal-texto {

    text-align: center;

    color: #718096;

    margin: 10px 0 25px;

}


.modal-resumen {

    display: grid;

    grid-template-columns: 1fr 1fr;

    gap: 12px;

}


.modal-dato {

    padding: 15px;

    border-radius: 10px;

    background: #f5f7f9;

    text-align: center;

}


.modal-dato span {

    display: block;

    font-size: 13px;

    color: #718096;

}


.modal-dato strong {

    display: block;

    margin-top: 5px;

    font-size: 25px;

}


.modal-advertencia {

    margin-top: 20px;

    padding: 13px 15px;

    border-radius: 9px;

    background: #fff4df;

    border: 1px solid #f1d09c;

    color: #8a5a12;

    font-size: 14px;

}


.modal-pregunta {

    text-align: center;

    margin: 22px 0;

    color: #334e68;

    font-weight: 600;

}


.modal-acciones {

    display: flex;

    gap: 12px;

    justify-content: center;

}


.modal-btn {

    border: none;

    padding: 12px 25px;

    border-radius: 9px;

    font-size: 14px;

    font-weight: 600;

    cursor: pointer;

    transition: .2s;

}


.modal-btn.cancelar {

    background: #e8edf1;

    color: #334e68;

}


.modal-btn.cancelar:hover {

    background: #dce3e8;

}


.modal-btn.confirmar {

    background: #109b94;

    color: white;

}


.modal-btn.confirmar:hover {

    background: #0d817b;

}


.verde {

    color: #1d8755;

}


.azul {

    color: #2474a8;

}


.gris {

    color: #687785;

}


.rojo {

    color: #c44343;

}


@media (max-width: 500px) {

    .modal-importacion {

        padding: 25px 20px;

    }


    .modal-resumen {

        grid-template-columns: 1fr;

    }


    .modal-acciones {

        flex-direction: column-reverse;

    }


    .modal-btn {

        width: 100%;

    }

}

        body {

            margin: 0;

            background: #f3f6f9;

            font-family:
                Arial,
                sans-serif;

            color: #334e68;

        }


        .contenedor {

            max-width: 1250px;

            margin: 40px auto;

            padding: 20px;

        }


        .header {

            margin-bottom: 25px;

        }


        .header h1 {

            margin-bottom: 7px;

        }


        .header p {

            color: #7b8794;

        }


        .archivo {

            background: #edf8f7;

            padding: 15px;

            border-radius: 9px;

            margin-bottom: 25px;

        }


        /* =========================================
           RESUMEN
        ========================================= */

        .resumen-grid {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 15px;

            margin-bottom: 25px;

        }


        .resumen-card {

            background: white;

            padding: 22px;

            border-radius: 12px;

            box-shadow:
                0 3px 12px
                rgba(0,0,0,.06);

        }


        .resumen-card span {

            display: block;

            color: #7b8794;

            font-size: 13px;

        }


        .resumen-card strong {

            display: block;

            margin-top: 7px;

            font-size: 30px;

        }


        .verde {
            color: #1d8755;
        }


        .azul {
            color: #2474a8;
        }


        .gris {
            color: #687785;
        }


        .rojo {
            color: #c44343;
        }


        /* =========================================
           TARJETAS
        ========================================= */

        .tabla-card {

            background: white;

            border-radius: 14px;

            padding: 25px;

            margin-bottom: 25px;

            box-shadow:
                0 3px 14px
                rgba(0,0,0,.07);

        }


        .tabla-card h2 {

            margin-top: 0;

        }


        .descripcion {

            color: #7b8794;

        }


        .tabla-scroll {

            overflow-x: auto;

        }


        table {

            width: 100%;

            border-collapse:
                collapse;

        }


        th,
        td {

            padding: 12px;

            text-align: left;

            border-bottom:
                1px solid #0a131b;

        }


        th {

            background:
                #090d11;

        }


        /* =========================================
           BADGES
        ========================================= */

        .badge {

            display: inline-block;

            padding: 5px 9px;

            border-radius: 20px;

            font-size: 12px;

            font-weight: bold;

        }


        .badge-nuevo {

            background: #e5f7ed;

            color: #18794e;

        }


        .badge-actualizar {

            background: #e5f1fa;

            color: #23638c;

        }


        .badge-ok {

            background: #edf0f2;

            color: #65727d;

        }


        .badge-error {

            background: #ffe8e8;

            color: #a63838;

        }


        /* =========================================
           ALERTA
        ========================================= */

        .advertencia {

            padding: 18px;

            background: #fff5e8;

            border: 1px solid #f2c58d;

            border-radius: 10px;

            color: #8d550d;

            margin-bottom: 25px;

        }


        .confirmacion {

            background: white;

            padding: 25px;

            border-radius: 14px;

            box-shadow:
                0 3px 14px
                rgba(0,0,0,.07);

        }


        .confirmacion p {

            margin: 7px 0;

        }


        /* =========================================
           BOTONES
        ========================================= */

        .acciones {

            display: flex;

            justify-content:
                space-between;

            align-items: center;

            margin-top: 25px;

            gap: 15px;

        }


        .btn {

            display: inline-block;

            padding: 12px 20px;

            border-radius: 8px;

            border: none;

            cursor: pointer;

            text-decoration: none;

            font-size: 14px;

            font-weight: 600;

        }


        .btn-volver {

            background: #e7edf1;

            color: #334e68;

        }


        .btn-importar {

            background: #109b94;

            color: white;

        }


        .btn-importar:hover {

            background: #0d847e;

        }


        .btn-importar:disabled {

            background: #aeb8bd;

            cursor: not-allowed;

        }


        /* =========================================
           RESPONSIVE
        ========================================= */

        @media (max-width: 850px) {

            .resumen-grid {

                grid-template-columns:
                    repeat(2, 1fr);

            }

        }


        @media (max-width: 600px) {

            .resumen-grid {

                grid-template-columns: 1fr;

            }

        }

    </style>

</head>


<body>


<div class="contenedor">


    <!-- =========================================
         ENCABEZADO
    ========================================= -->

    <div class="header">

        <h1>
            🔎 Vista previa de importación
        </h1>

        <p>
            Revisa los cambios detectados antes
            de modificar la base de datos.
        </p>

    </div>


    <div class="archivo">

        📄 Archivo:

        <strong>

            <?= htmlspecialchars(
                $archivo['name']
            ) ?>

        </strong>

    </div>


    <!-- =========================================
         RESUMEN
    ========================================= -->

    <div class="resumen-grid">


        <div class="resumen-card">

            <span>
                🆕 Alumnos nuevos
            </span>

            <strong class="verde">
                <?= $totalNuevos ?>
            </strong>

        </div>


        <div class="resumen-card">

            <span>
                🔄 Se actualizarán
            </span>

            <strong class="azul">
                <?= $totalActualizados ?>
            </strong>

        </div>


        <div class="resumen-card">

            <span>
                ✓ Sin cambios
            </span>

            <strong class="gris">
                <?= $totalSinCambios ?>
            </strong>

        </div>


        <div class="resumen-card">

            <span>
                ⚠️ Con errores
            </span>

            <strong class="rojo">
                <?= $totalErrores ?>
            </strong>

        </div>


    </div>


    <!-- =========================================
         CAMBIOS
    ========================================= -->

    <?php if ($totalActualizados > 0): ?>


    <div class="tabla-card">

        <h2>
            🔄 Cambios detectados
        </h2>

        <p class="descripcion">

            Estos alumnos ya existen en la base de datos,
            pero el Excel contiene información diferente.

        </p>


        <div class="tabla-scroll">

            <table>

                <thead>

                    <tr>

                        <th>
                            Nombre
                        </th>

                        <th>
                            CURP
                        </th>

                        <th>
                            Antes
                        </th>

                        <th>
                            Después
                        </th>

                        <th>
                            Cambio
                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php foreach (
                    $actualizados
                    as $alumno
                ): ?>


                    <tr>


                        <td>

                            <?= htmlspecialchars(
                                $alumno['nombre']
                            ) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $alumno['curp']
                            ) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $alumno['grado_anterior']
                            ) ?>°

                            <?= htmlspecialchars(
                                $alumno['grupo_anterior']
                            ) ?>

                        </td>


                        <td>

                            <strong>

                                <?= htmlspecialchars(
                                    $alumno['grado']
                                ) ?>°

                                <?= htmlspecialchars(
                                    $alumno['grupo']
                                ) ?>

                            </strong>

                        </td>


                        <td>

                            <span
                                class="badge badge-actualizar"
                            >

                                <?= htmlspecialchars(
                                    textoCambios(
                                        $alumno['cambios']
                                    )
                                ) ?>

                            </span>

                        </td>


                    </tr>


                <?php endforeach; ?>


                </tbody>

            </table>

        </div>

    </div>


    <?php endif; ?>


    <!-- =========================================
         NUEVOS
    ========================================= -->

    <?php if ($totalNuevos > 0): ?>


    <div class="tabla-card">

        <h2>
            🆕 Alumnos nuevos
        </h2>

        <p class="descripcion">

            Estos alumnos no existen actualmente
            en la base de datos.

        </p>


        <div class="tabla-scroll">

            <table>

                <thead>

                    <tr>

                        <th>
                            Fila
                        </th>

                        <th>
                            Nombre
                        </th>

                        <th>
                            CURP
                        </th>

                        <th>
                            Grado
                        </th>

                        <th>
                            Grupo
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php foreach (
                    $nuevos
                    as $alumno
                ): ?>


                    <tr>

                        <td>
                            <?= $alumno['fila'] ?>
                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $alumno['nombre']
                            ) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $alumno['curp']
                            ) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $alumno['grado']
                            ) ?>°

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $alumno['grupo']
                            ) ?>

                        </td>

                    </tr>


                <?php endforeach; ?>


                </tbody>

            </table>

        </div>

    </div>


    <?php endif; ?>


    <!-- =========================================
         ERRORES
    ========================================= -->

    <?php if ($totalErrores > 0): ?>


    <div class="tabla-card">

        <h2>
            ⚠️ Registros con errores
        </h2>

        <div class="tabla-scroll">

            <table>

                <thead>

                    <tr>

                        <th>
                            Fila
                        </th>

                        <th>
                            Nombre
                        </th>

                        <th>
                            CURP
                        </th>

                        <th>
                            Error
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php foreach (
                    $erroresImportacion
                    as $alumno
                ): ?>


                    <tr>

                        <td>
                            <?= $alumno['fila'] ?>
                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $alumno['nombre']
                            ) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $alumno['curp']
                            ) ?>

                        </td>


                        <td>

                            <span
                                class="badge badge-error"
                            >

                                <?= htmlspecialchars(
                                    implode(
                                        ', ',
                                        $alumno['errores']
                                    )
                                ) ?>

                            </span>

                        </td>

                    </tr>


                <?php endforeach; ?>


                </tbody>

            </table>

        </div>

    </div>


    <?php endif; ?>


    <!-- =========================================
         CONFIRMACIÓN
    ========================================= -->

    <div class="confirmacion">

        <h2>
            📋 Resumen de importación
        </h2>


        <?php if ($totalErrores > 0): ?>

            <div class="advertencia">

                ⚠️

                Hay
                <strong>
                    <?= $totalErrores ?>
                </strong>
                registros con errores.

                Estos registros
                <strong>
                    NO
                </strong>
                serán importados.

            </div>

        <?php endif; ?>


        <p>

            🆕 Se agregarán:

            <strong>
                <?= $totalNuevos ?>
            </strong>

            alumnos nuevos.

        </p>


        <p>

            🔄 Se actualizarán:

            <strong>
                <?= $totalActualizados ?>
            </strong>

            alumnos existentes.

        </p>


        <p>

            ✓ Permanecerán sin cambios:

            <strong>
                <?= $totalSinCambios ?>
            </strong>

            alumnos.

        </p>


        <!-- =====================================
             FORMULARIO
             (Ya NO se envía el JSON completo por
             POST. Los datos ya están guardados en
             $_SESSION['importacion_alumnos']. El
             formulario solo dispara la acción.)
        ====================================== -->

        <form
            id="formImportacion"
            action="importar_excel.php"
            method="POST"
        >


            <div class="acciones">


                <a
                    href="../importar_alumnos.php"
                    class="btn btn-volver"
                >
                    ← Cancelar
                </a>


                <button
                    type="button"
                    class="btn btn-importar"
                    onclick="abrirModalImportacion()"
                >
                    ⚠️ Confirmar e importar
                </button>


            </div>


        </form>


    </div>


</div>
<!-- =====================================================
     MODAL CONFIRMAR IMPORTACIÓN
====================================================== -->

<div
    id="modalImportacion"
    class="modal-overlay"
>

    <div class="modal-importacion">

        <button
            type="button"
            class="modal-cerrar"
            onclick="cerrarModalImportacion()"
        >
            ×
        </button>


        <div class="modal-icono">
            ⚠️
        </div>


        <h2>
            Confirmar importación
        </h2>


        <p class="modal-texto">

            Estás a punto de realizar cambios
            en la base de datos.

        </p>


        <div class="modal-resumen">


            <div class="modal-dato">

                <span>
                    🆕 Nuevos
                </span>

                <strong class="verde">
                    <?= $totalNuevos ?>
                </strong>

            </div>


            <div class="modal-dato">

                <span>
                    🔄 Actualizar
                </span>

                <strong class="azul">
                    <?= $totalActualizados ?>
                </strong>

            </div>


            <div class="modal-dato">

                <span>
                    ✓ Sin cambios
                </span>

                <strong class="gris">
                    <?= $totalSinCambios ?>
                </strong>

            </div>


            <div class="modal-dato">

                <span>
                    ⚠️ Errores
                </span>

                <strong class="rojo">
                    <?= $totalErrores ?>
                </strong>

            </div>


        </div>


        <?php if ($totalErrores > 0): ?>

            <div class="modal-advertencia">

                ⚠️

                Los

                <strong>
                    <?= $totalErrores ?>
                </strong>

                registros con errores
                serán omitidos.

            </div>

        <?php endif; ?>


        <p class="modal-pregunta">

            ¿Deseas continuar con la importación?

        </p>


        <div class="modal-acciones">

            <button
                type="button"
                class="modal-btn cancelar"
                onclick="cerrarModalImportacion()"
            >
                Cancelar
            </button>


            <button
                type="button"
                class="modal-btn confirmar"
                onclick="confirmarImportacion()"
            >
                ✓ Sí, importar
            </button>

        </div>


    </div>

</div>
<script>

function abrirModalImportacion() {

    const modal =
        document.getElementById(
            'modalImportacion'
        );

    modal.classList.add('mostrar');

}


function cerrarModalImportacion() {

    const modal =
        document.getElementById(
            'modalImportacion'
        );

    modal.classList.remove('mostrar');

}


function confirmarImportacion() {

    const formulario =
        document.getElementById(
            'formImportacion'
        );

    formulario.submit();

}


// ================================================
// CERRAR AL HACER CLIC FUERA DEL MODAL
// ================================================

document
    .getElementById('modalImportacion')
    .addEventListener(
        'click',
        function(event) {

            if (
                event.target === this
            ) {

                cerrarModalImportacion();

            }

        }
    );


// ================================================
// CERRAR CON ESC
// ================================================

document.addEventListener(
    'keydown',
    function(event) {

        if (
            event.key === 'Escape'
        ) {

            cerrarModalImportacion();

        }

    }
);

</script>


</body>

</html>
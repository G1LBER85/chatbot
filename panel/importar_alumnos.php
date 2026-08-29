<?php

require '../conexion.php';

$paginaActual = 'importar_alumnos';


// =====================================================
// OBTENER CANTIDAD DE ALUMNOS POR GRADO
// =====================================================

$conteosGrados = [
    '1' => 0,
    '2' => 0,
    '3' => 0,
    '4' => 0,
    '5' => 0,
    '6' => 0
];

$sqlConteos = "
    SELECT grado, COUNT(*) AS cantidad
    FROM alumnos
    WHERE grado IN ('1','2','3','4','5','6')
    GROUP BY grado
";

$resultadoConteos = $conn->query($sqlConteos);

if ($resultadoConteos) {

    while ($fila = $resultadoConteos->fetch_assoc()) {

        $grado = (string)$fila['grado'];

        if (isset($conteosGrados[$grado])) {

            $conteosGrados[$grado] =
                (int)$fila['cantidad'];

        }

    }

}


// =====================================================
// TOTAL GENERAL
// =====================================================

$totalAlumnos = array_sum($conteosGrados);

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
        Importar alumnos — Panel de Administrador ChecaBot
    </title>

    <link
        rel="stylesheet"
        href="css/panel.css"
    >

    <link
        rel="stylesheet"
        href="css/importar_alumnos.css"
    >

    <style>

        /* =====================================================
           CONTENEDOR DE GRADOS
        ===================================================== */

        .grados-grid {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 15px;

            margin-top: 20px;

        }


        /* =====================================================
           OPCIÓN DE GRADO
        ===================================================== */

        .grado-opcion {

            display: flex;

            align-items: center;

            gap: 15px;

            padding: 18px;

            background: #f7f9fb;

            border: 2px solid #e1e7ec;

            border-radius: 12px;

            cursor: pointer;

            transition: .2s;

        }


        .grado-opcion:hover {

            border-color: #109b94;

            background: #f0fbfa;

        }


        .grado-opcion.seleccionado {

            border-color: #109b94;

            background: #e8f8f6;

        }


        .grado-opcion input {

            width: 20px;

            height: 20px;

            cursor: pointer;

            accent-color: #109b94;

        }


        .grado-info {

            display: flex;

            flex-direction: column;

            gap: 4px;

        }


        .grado-numero {

            font-size: 24px;

            font-weight: bold;

            color: #29465f;

        }


        .grado-nombre {

            font-size: 13px;

            color: #718096;

        }


        .grado-cantidad {

            font-size: 13px;

            font-weight: bold;

            color: #109b94;

        }


        /* =====================================================
           ALERTA
        ===================================================== */

        .alerta-peligro {

            display: flex;

            align-items: flex-start;

            gap: 12px;

            margin-top: 25px;

            padding: 15px;

            background: #fff5f5;

            border: 1px solid #f3c2c2;

            border-radius: 10px;

            color: #8f3434;

        }


        .alerta-peligro span {

            font-size: 22px;

        }


        .alerta-peligro p {

            margin: 0;

        }


        /* =====================================================
           RESUMEN DE SELECCIÓN
        ===================================================== */

        .resumen-limpieza {

            display: none;

            margin-top: 20px;

            padding: 18px;

            background: #eef8f7;

            border: 1px solid #b9e4df;

            border-radius: 10px;

        }


        .resumen-limpieza.visible {

            display: block;

        }


        .resumen-limpieza strong {

            color: #087f78;

        }


        /* =====================================================
           MODAL
        ===================================================== */

        .modal-overlay {

            display: none;

            position: fixed;

            inset: 0;

            background: rgba(20, 35, 50, .65);

            z-index: 9999;

            align-items: center;

            justify-content: center;

            padding: 20px;

        }


        .modal-overlay.activo {

            display: flex;

        }


        .modal {

            width: 100%;

            max-width: 600px;

            background: white;

            border-radius: 18px;

            box-shadow:
                0 20px 60px rgba(0,0,0,.25);

            overflow: hidden;

            animation: aparecer .2s ease;

        }


        @keyframes aparecer {

            from {

                opacity: 0;

                transform: translateY(-15px)
                           scale(.98);

            }

            to {

                opacity: 1;

                transform: translateY(0)
                           scale(1);

            }

        }


        .modal-header {

            padding: 25px;

            background: #fff5f5;

            border-bottom: 1px solid #f0d0d0;

            display: flex;

            align-items: center;

            gap: 15px;

        }


        .modal-icono {

            font-size: 40px;

        }


        .modal-header h2 {

            margin: 0;

            color: #8f3434;

        }


        .modal-header p {

            margin: 5px 0 0;

            color: #6b7280;

        }


        .modal-body {

            padding: 25px;

        }


        .modal-body h3 {

            margin-top: 0;

            color: #29465f;

        }


        .tabla-confirmacion {

            width: 100%;

            border-collapse: collapse;

            margin-top: 15px;

        }


        .tabla-confirmacion th,
        .tabla-confirmacion td {

            padding: 12px;

            border-bottom: 1px solid #e5eaee;

            text-align: left;

        }


        .tabla-confirmacion th {

            background: #f5f7f9;

            color: #52697d;

        }


        .modal-total {

            margin-top: 18px;

            padding: 15px;

            border-radius: 10px;

            background: #fff1f1;

            color: #8f3434;

            font-size: 18px;

            text-align: center;

        }


        .modal-footer {

            display: flex;

            justify-content: flex-end;

            gap: 12px;

            padding: 20px 25px;

            background: #f8fafb;

            border-top: 1px solid #e5eaee;

        }


        .btn-modal {

            border: none;

            padding: 12px 20px;

            border-radius: 9px;

            font-weight: bold;

            cursor: pointer;

            font-size: 15px;

        }


        .btn-modal-cancelar {

            background: #e5ebef;

            color: #334e68;

        }


        .btn-modal-eliminar {

            background: #d64545;

            color: white;

        }


        .btn-modal-eliminar:hover {

            background: #bd3737;

        }


        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 800px) {

            .grados-grid {

                grid-template-columns:
                    repeat(2, 1fr);

            }

        }


        @media (max-width: 500px) {

            .grados-grid {

                grid-template-columns: 1fr;

            }

            .modal-footer {

                flex-direction: column;

            }

            .btn-modal {

                width: 100%;

            }

        }

    </style>

</head>


<body>


<div class="layout">


    <!-- =====================================================
         SIDEBAR
    ====================================================== -->

    <?php include '../sidebar/sidebar.php'; ?>


    <!-- =====================================================
         CONTENIDO PRINCIPAL
    ====================================================== -->

    <main class="contenido">


        <!-- =================================================
             ENCABEZADO
        ================================================== -->

        <header class="pagina-header">

            <div>

                <h1>
                    Importar alumnos
                </h1>

                <p>
                    Carga alumnos desde un archivo Excel
                    y administra los registros existentes.
                </p>

            </div>

        </header>


        <!-- =================================================
             RESUMEN ETL
        ================================================== -->

        <section class="etl-resumen">


            <div class="etl-paso">

                <div class="etl-icono">
                    📥
                </div>

                <div>

                    <strong>
                        Extracción
                    </strong>

                    <span>
                        Leer archivo Excel
                    </span>

                </div>

            </div>


            <div class="etl-flecha">
                →
            </div>


            <div class="etl-paso">

                <div class="etl-icono">
                    🔄
                </div>

                <div>

                    <strong>
                        Transformación
                    </strong>

                    <span>
                        Limpiar y validar datos
                    </span>

                </div>

            </div>


            <div class="etl-flecha">
                →
            </div>


            <div class="etl-paso">

                <div class="etl-icono">
                    🗄️
                </div>

                <div>

                    <strong>
                        Carga
                    </strong>

                    <span>
                        Guardar en MySQL
                    </span>

                </div>

            </div>


        </section>



        <!-- =================================================
             IMPORTAR EXCEL
        ================================================== -->

        <section class="panel-card">


            <div class="card-header">

                <div>

                    <h2>
                        📄 Importar archivo Excel
                    </h2>

                    <p>
                        El sistema utilizará únicamente
                        grado, grupo, nombre y CURP.
                    </p>

                </div>

            </div>



            <form
                action="etl/analizar_excel.php"
                method="POST"
                enctype="multipart/form-data"
                id="formExcel"
            >


                <div
                    class="drop-zone"
                    id="dropZone"
                >


                    <div class="drop-icono">
                        📊
                    </div>


                    <h3>
                        Arrastra tu archivo Excel aquí
                    </h3>


                    <p>
                        o selecciona un archivo
                        desde tu computadora
                    </p>


                    <label
                        for="archivo_excel"
                        class="btn-seleccionar"
                    >
                        Seleccionar archivo
                    </label>


                    <input
                        type="file"
                        name="archivo_excel"
                        id="archivo_excel"
                        accept=".xlsx,.xls"
                        required
                    >


                    <div
                        class="archivo-seleccionado"
                        id="archivoSeleccionado"
                    >
                    </div>


                </div>



                <div class="datos-importados">


                    <div>

                        <span>
                            Se importará
                        </span>

                        <strong>
                            Grado
                        </strong>

                    </div>


                    <div>

                        <span>
                            Se importará
                        </span>

                        <strong>
                            Grupo
                        </strong>

                    </div>


                    <div>

                        <span>
                            Se importará
                        </span>

                        <strong>
                            Nombre
                        </strong>

                    </div>


                    <div>

                        <span>
                            Se importará
                        </span>

                        <strong>
                            CURP
                        </strong>

                    </div>


                </div>



                <div class="acciones">

                    <button
                        type="submit"
                        class="btn btn-principal"
                    >
                        🔍 Analizar archivo
                    </button>

                </div>


            </form>


        </section>



        <!-- =================================================
             LIMPIAR BASE DE DATOS
        ================================================== -->

        <section class="panel-card peligro">


            <div class="card-header">

                <div>

                    <h2>
                        🗑️ Limpiar alumnos por grado
                    </h2>

                    <p>
                        Selecciona uno o varios grados
                        para eliminar únicamente esos alumnos.
                    </p>

                </div>

            </div>



            <form
                action="etl/eliminar_grados.php"
                method="POST"
                id="formLimpiar"
            >


                <!-- =========================================
                     GRADOS
                ========================================== -->

                <div class="grados-grid">


                    <!-- 1 -->
                    <label class="grado-opcion">

                        <input
                            type="checkbox"
                            name="grados[]"
                            value="1"
                        >

                        <div class="grado-info">

                            <strong class="grado-numero">
                                1°
                            </strong>

                            <span class="grado-nombre">
                                Primer grado
                            </span>

                            <span class="grado-cantidad">

                                <?= $conteosGrados['1'] ?>

                                alumnos

                            </span>

                        </div>

                    </label>



                    <!-- 2 -->
                    <label class="grado-opcion">

                        <input
                            type="checkbox"
                            name="grados[]"
                            value="2"
                        >

                        <div class="grado-info">

                            <strong class="grado-numero">
                                2°
                            </strong>

                            <span class="grado-nombre">
                                Segundo grado
                            </span>

                            <span class="grado-cantidad">

                                <?= $conteosGrados['2'] ?>

                                alumnos

                            </span>

                        </div>

                    </label>



                    <!-- 3 -->
                    <label class="grado-opcion">

                        <input
                            type="checkbox"
                            name="grados[]"
                            value="3"
                        >

                        <div class="grado-info">

                            <strong class="grado-numero">
                                3°
                            </strong>

                            <span class="grado-nombre">
                                Tercer grado
                            </span>

                            <span class="grado-cantidad">

                                <?= $conteosGrados['3'] ?>

                                alumnos

                            </span>

                        </div>

                    </label>



                    <!-- 4 -->
                    <label class="grado-opcion">

                        <input
                            type="checkbox"
                            name="grados[]"
                            value="4"
                        >

                        <div class="grado-info">

                            <strong class="grado-numero">
                                4°
                            </strong>

                            <span class="grado-nombre">
                                Cuarto grado
                            </span>

                            <span class="grado-cantidad">

                                <?= $conteosGrados['4'] ?>

                                alumnos

                            </span>

                        </div>

                    </label>



                    <!-- 5 -->
                    <label class="grado-opcion">

                        <input
                            type="checkbox"
                            name="grados[]"
                            value="5"
                        >

                        <div class="grado-info">

                            <strong class="grado-numero">
                                5°
                            </strong>

                            <span class="grado-nombre">
                                Quinto grado
                            </span>

                            <span class="grado-cantidad">

                                <?= $conteosGrados['5'] ?>

                                alumnos

                            </span>

                        </div>

                    </label>



                    <!-- 6 -->
                    <label class="grado-opcion">

                        <input
                            type="checkbox"
                            name="grados[]"
                            value="6"
                        >

                        <div class="grado-info">

                            <strong class="grado-numero">
                                6°
                            </strong>

                            <span class="grado-nombre">
                                Sexto grado
                            </span>

                            <span class="grado-cantidad">

                                <?= $conteosGrados['6'] ?>

                                alumnos

                            </span>

                        </div>

                    </label>


                </div>



                <!-- =========================================
                     RESUMEN DINÁMICO
                ========================================== -->

                <div
                    class="resumen-limpieza"
                    id="resumenLimpieza"
                >

                    <strong>
                        Selección actual:
                    </strong>

                    <span id="textoSeleccion">
                        Ningún grado seleccionado.
                    </span>

                </div>



                <!-- =========================================
                     ALERTA
                ========================================== -->

                <div class="alerta-peligro">

                    <span>
                        ⚠️
                    </span>

                    <p>

                        Esta acción eliminará permanentemente
                        los alumnos pertenecientes a los grados
                        seleccionados.

                    </p>

                </div>



                <!-- =========================================
                     BOTÓN
                ========================================== -->

                <div class="acciones">

                    <button
                        type="submit"
                        class="btn btn-peligro"
                    >

                        🗑️
                        Eliminar alumnos seleccionados

                    </button>

                </div>


            </form>


        </section>


    </main>


</div>



<!-- =====================================================
     MODAL DE CONFIRMACIÓN
====================================================== -->

<div
    class="modal-overlay"
    id="modalConfirmacion"
>


    <div class="modal">


        <!-- HEADER -->

        <div class="modal-header">

            <div class="modal-icono">
                ⚠️
            </div>

            <div>

                <h2>
                    Confirmar eliminación
                </h2>

                <p>
                    Esta acción no se puede deshacer.
                </p>

            </div>

        </div>



        <!-- BODY -->

        <div class="modal-body">


            <h3>
                Alumnos que serán eliminados
            </h3>


            <table class="tabla-confirmacion">


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


                <tbody
                    id="tablaModal"
                >
                </tbody>


            </table>


            <div class="modal-total">

                Se eliminarán

                <strong id="totalModal">
                    0
                </strong>

                alumnos en total.

            </div>


        </div>



        <!-- FOOTER -->

        <div class="modal-footer">


            <button
                type="button"
                class="btn-modal btn-modal-cancelar"
                id="btnCancelarModal"
            >

                ← Cancelar

            </button>


            <button
                type="button"
                class="btn-modal btn-modal-eliminar"
                id="btnConfirmarModal"
            >

                🗑️ Sí, eliminar alumnos

            </button>


        </div>


    </div>

</div>



<script>


// =====================================================
// ARCHIVO EXCEL
// =====================================================

const inputArchivo =
    document.getElementById('archivo_excel');


const archivoSeleccionado =
    document.getElementById('archivoSeleccionado');


const dropZone =
    document.getElementById('dropZone');


inputArchivo.addEventListener(
    'change',
    function () {

        if (this.files.length > 0) {

            const archivo =
                this.files[0];

            archivoSeleccionado.innerHTML =
                '✅ Archivo seleccionado: <strong>'
                + archivo.name
                + '</strong>';

        }

    }
);


dropZone.addEventListener(
    'dragover',
    function (e) {

        e.preventDefault();

        dropZone.classList.add(
            'drag-activo'
        );

    }
);


dropZone.addEventListener(
    'dragleave',
    function () {

        dropZone.classList.remove(
            'drag-activo'
        );

    }
);


dropZone.addEventListener(
    'drop',
    function (e) {

        e.preventDefault();

        dropZone.classList.remove(
            'drag-activo'
        );

        const archivos =
            e.dataTransfer.files;

        if (archivos.length > 0) {

            inputArchivo.files =
                archivos;

            archivoSeleccionado.innerHTML =
                '✅ Archivo seleccionado: <strong>'
                + archivos[0].name
                + '</strong>';

        }

    }
);



// =====================================================
// LIMPIAR GRADOS
// =====================================================

const formLimpiar =
    document.getElementById('formLimpiar');


const checkboxes =
    document.querySelectorAll(
        'input[name="grados[]"]'
    );


const resumenLimpieza =
    document.getElementById(
        'resumenLimpieza'
    );


const textoSeleccion =
    document.getElementById(
        'textoSeleccion'
    );


const modal =
    document.getElementById(
        'modalConfirmacion'
    );


const tablaModal =
    document.getElementById(
        'tablaModal'
    );


const totalModal =
    document.getElementById(
        'totalModal'
    );


const btnCancelarModal =
    document.getElementById(
        'btnCancelarModal'
    );


const btnConfirmarModal =
    document.getElementById(
        'btnConfirmarModal'
    );



// =====================================================
// DATOS DE LOS GRADOS
// =====================================================

const cantidades = {

    1: <?= $conteosGrados['1'] ?>,

    2: <?= $conteosGrados['2'] ?>,

    3: <?= $conteosGrados['3'] ?>,

    4: <?= $conteosGrados['4'] ?>,

    5: <?= $conteosGrados['5'] ?>,

    6: <?= $conteosGrados['6'] ?>

};



// =====================================================
// ACTUALIZAR SELECCIÓN
// =====================================================

function actualizarSeleccion() {


    const seleccionados =
        document.querySelectorAll(
            'input[name="grados[]"]:checked'
        );


    let grados = [];

    let total = 0;


    seleccionados.forEach(
        function (checkbox) {

            const grado =
                checkbox.value;

            grados.push(
                grado + '°'
            );

            total +=
                cantidades[grado];

        }
    );


    // Actualizar estilos

    checkboxes.forEach(
        function (checkbox) {

            const tarjeta =
                checkbox.closest(
                    '.grado-opcion'
                );

            if (checkbox.checked) {

                tarjeta.classList.add(
                    'seleccionado'
                );

            } else {

                tarjeta.classList.remove(
                    'seleccionado'
                );

            }

        }
    );


    // Mostrar resumen

    if (grados.length === 0) {

        resumenLimpieza.classList.remove(
            'visible'
        );

        textoSeleccion.textContent =
            'Ningún grado seleccionado.';

        return;

    }


    resumenLimpieza.classList.add(
        'visible'
    );


    textoSeleccion.innerHTML =
        '<strong>'
        + grados.join(', ')
        + '</strong>'
        + ' — '
        + total
        + ' alumnos seleccionados.';

}



// =====================================================
// EVENTO DE CHECKBOX
// =====================================================

checkboxes.forEach(
    function (checkbox) {

        checkbox.addEventListener(
            'change',
            actualizarSeleccion
        );

    }
);



// =====================================================
// ENVIAR FORMULARIO
// =====================================================

formLimpiar.addEventListener(
    'submit',
    function (e) {

        e.preventDefault();


        const seleccionados =
            document.querySelectorAll(
                'input[name="grados[]"]:checked'
            );


        // No hay selección

        if (seleccionados.length === 0) {

            alert(
                'Selecciona al menos un grado.'
            );

            return;

        }


        // Limpiar tabla del modal

        tablaModal.innerHTML = '';


        let total = 0;


        // Crear filas

        seleccionados.forEach(
            function (checkbox) {

                const grado =
                    checkbox.value;

                const cantidad =
                    cantidades[grado];


                total += cantidad;


                const fila =
                    document.createElement(
                        'tr'
                    );


                fila.innerHTML = `

                    <td>

                        <strong>
                            ${grado}°
                        </strong>

                    </td>

                    <td>
                        ${cantidad}
                    </td>

                `;


                tablaModal.appendChild(
                    fila
                );

            }
        );


        // Mostrar total

        totalModal.textContent =
            total;


        // Mostrar modal

        modal.classList.add(
            'activo'
        );

    }
);



// =====================================================
// CANCELAR MODAL
// =====================================================

btnCancelarModal.addEventListener(
    'click',
    function () {

        modal.classList.remove(
            'activo'
        );

    }
);



// =====================================================
// CERRAR HACIENDO CLICK FUERA
// =====================================================

modal.addEventListener(
    'click',
    function (e) {

        if (e.target === modal) {

            modal.classList.remove(
                'activo'
            );

        }

    }
);



// =====================================================
// CONFIRMAR ELIMINACIÓN
// =====================================================

btnConfirmarModal.addEventListener(
    'click',
    function () {


        /*
         * IMPORTANTE:
         *
         * Aquí ya no mostramos otro confirm().
         *
         * El formulario se envía directamente
         * a eliminar_grados.php
         */

        formLimpiar.submit();

    }
);


</script>


</body>

</html>
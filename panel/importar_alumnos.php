<?php
require '../conexion.php';
$paginaActual = 'importar_alumnos';

?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>Importar alumnos — Panel de Administrador ChecaBot</title>

  <link rel="stylesheet" href="css/panel.css">
  <link rel="stylesheet" href="css/importar_alumnos.css">
</head>

<body>

<div class="layout">

  <?php include '../sidebar/sidebar.php'; ?>


  <!-- CONTENIDO PRINCIPAL -->
  <main class="contenido">

    <header class="pagina-header">

      <div>
        <h1>Importar alumnos</h1>

        <p>
          Carga alumnos desde un archivo Excel y administra
          los registros existentes.
        </p>
      </div>

    </header>


    <!-- RESUMEN DEL ETL -->
    <section class="etl-resumen">

      <div class="etl-paso">

        <div class="etl-icono">
          📥
        </div>

        <div>
          <strong>Extracción</strong>
          <span>Leer archivo Excel</span>
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
          <strong>Transformación</strong>
          <span>Limpiar y validar datos</span>
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
          <strong>Carga</strong>
          <span>Guardar en MySQL</span>
        </div>

      </div>

    </section>


    <!-- IMPORTAR EXCEL -->
    <section class="panel-card">

      <div class="card-header">

        <div>
          <h2>📄 Importar archivo Excel</h2>

          <p>
            El sistema utilizará únicamente grado,
            grupo, nombre y CURP.
          </p>
        </div>

      </div>


      <form
        action="etl/analizar_excel.php"
        method="POST"
        enctype="multipart/form-data"
        id="formExcel"
      >

        <div class="drop-zone" id="dropZone">

          <div class="drop-icono">
            📊
          </div>

          <h3>
            Arrastra tu archivo Excel aquí
          </h3>

          <p>
            o selecciona un archivo desde tu computadora
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
            <span>Se importará</span>
            <strong>Grado</strong>
          </div>

          <div>
            <span>Se importará</span>
            <strong>Grupo</strong>
          </div>

          <div>
            <span>Se importará</span>
            <strong>Nombre</strong>
          </div>

          <div>
            <span>Se importará</span>
            <strong>CURP</strong>
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


    <!-- LIMPIAR BASE DE DATOS -->
    <section class="panel-card peligro">

      <div class="card-header">

        <div>

          <h2>
            🗑️ Limpiar alumnos por grado
          </h2>

          <p>
            Selecciona uno o varios grados para eliminar
            únicamente esos alumnos.
          </p>

        </div>

      </div>


      <form
        action="etl/limpiar_grados.php"
        method="POST"
        id="formLimpiar"
      >

        <div class="grados-grid">

    <label class="grado-opcion">

        <input
            type="checkbox"
            name="grados[]"
            value="1"
        >

        <div>
            <strong>1°</strong>
            <span>Primer grado</span>
        </div>

    </label>


    <label class="grado-opcion">

        <input
            type="checkbox"
            name="grados[]"
            value="2"
        >

        <div>
            <strong>2°</strong>
            <span>Segundo grado</span>
        </div>

    </label>


    <label class="grado-opcion">

        <input
            type="checkbox"
            name="grados[]"
            value="3"
        >

        <div>
            <strong>3°</strong>
            <span>Tercer grado</span>
        </div>

    </label>


    <label class="grado-opcion">

        <input
            type="checkbox"
            name="grados[]"
            value="4"
        >

        <div>
            <strong>4°</strong>
            <span>Cuarto grado</span>
        </div>

    </label>


    <label class="grado-opcion">

        <input
            type="checkbox"
            name="grados[]"
            value="5"
        >

        <div>
            <strong>5°</strong>
            <span>Quinto grado</span>
        </div>

    </label>


    <label class="grado-opcion">

        <input
            type="checkbox"
            name="grados[]"
            value="6"
        >

        <div>
            <strong>6°</strong>
            <span>Sexto grado</span>
        </div>

    </label>

</div>


        <div class="alerta-peligro">

          <span>
            ⚠️
          </span>

          <p>
            Esta acción eliminará permanentemente los alumnos
            pertenecientes a los grados seleccionados.
          </p>

        </div>


        <div class="acciones">

          <button
            type="submit"
            class="btn btn-peligro"
          >
            🗑️ Eliminar alumnos seleccionados
          </button>

        </div>

      </form>

    </section>

  </main>

</div>


<script>

const inputArchivo =
  document.getElementById('archivo_excel');

const archivoSeleccionado =
  document.getElementById('archivoSeleccionado');

const dropZone =
  document.getElementById('dropZone');


inputArchivo.addEventListener('change', function () {

  if (this.files.length > 0) {

    const archivo = this.files[0];

    archivoSeleccionado.innerHTML =
      '✅ Archivo seleccionado: <strong>'
      + archivo.name
      + '</strong>';

  }

});


dropZone.addEventListener('dragover', function (e) {

  e.preventDefault();

  dropZone.classList.add('drag-activo');

});


dropZone.addEventListener('dragleave', function () {

  dropZone.classList.remove('drag-activo');

});


dropZone.addEventListener('drop', function (e) {

  e.preventDefault();

  dropZone.classList.remove('drag-activo');

  const archivos = e.dataTransfer.files;

  if (archivos.length > 0) {

    inputArchivo.files = archivos;

    archivoSeleccionado.innerHTML =
      '✅ Archivo seleccionado: <strong>'
      + archivos[0].name
      + '</strong>';

  }

});


document
  .getElementById('formLimpiar')
  .addEventListener('submit', function (e) {

    const seleccionados =
      document.querySelectorAll(
        'input[name="grados[]"]:checked'
      );

    if (seleccionados.length === 0) {

      e.preventDefault();

      alert(
        'Selecciona al menos un grado.'
      );

      return;
    }

    const confirmar = confirm(
      '¿Seguro que deseas eliminar los alumnos de los grados seleccionados?'
    );

    if (!confirmar) {
      e.preventDefault();
    }

  });

</script>

</body>
</body>
</html>
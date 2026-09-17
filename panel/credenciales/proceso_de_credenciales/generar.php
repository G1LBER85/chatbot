<?php
// ═══════════════════════════════════════════════════════════════
// panel/credenciales/proceso_de_credenciales/generar.php
// ─────────────────────────────────────────────────────────────
// Orquesta todo el proceso de generar credenciales:
//   1. Trae los datos de los alumnos seleccionados (alumnos.php)
//   2. Los agrupa por grado+grupo (alumnos.php)
//   3. Arma y renderiza el PDF de cada grupo (plantilla_credenciales.php
//      + qr.php + estilos.css, todo unido por pdf.php)
//   4. Entrega el resultado: un PDF directo si es un solo archivo,
//      o un ZIP si son varios (pdf.php)
//
// Se llama desde panel/credenciales.php (formulario POST con los
// ids[] de los alumnos marcados). Es POST y no GET porque con
// muchos alumnos marcados la URL sería demasiado larga para el
// servidor ("Request-URI Too Long").
// ═══════════════════════════════════════════════════════════════

// Esta carpeta ("proceso de credenciales") vive DENTRO de
// panel/credenciales/, así que hay que subir 3 niveles para llegar
// a la raíz del proyecto (donde está conexion.php):
// proceso de credenciales/ → credenciales/ → panel/ → raíz.
require __DIR__ . '/../../../conexion.php';

// alumnos.php, qr.php y pdf.php se quedaron un nivel arriba (en
// panel/credenciales/), así que se busca con '/../'.
require __DIR__ . '/../alumnos.php';
require __DIR__ . '/../qr.php';

// plantilla_credenciales.php vive en esta MISMA carpeta.
require __DIR__ . '/plantilla_credenciales.php';

require __DIR__ . '/../pdf.php';

// Tope de seguridad por ARCHIVO (no por hoja): aunque agrupemos por
// grado/grupo, un grupo inusualmente grande igual podría acumular
// demasiadas hojas en un solo PDF. Ajustable si hace falta.
const MAX_ALUMNOS_POR_ARCHIVO = 60;

ini_set('memory_limit', '1024M');
set_time_limit(180);

$ids = array_map('intval', $_POST['ids'] ?? []);

if (empty($ids)) {
    die('No seleccionaste ningún alumno. Regresa y marca al menos uno.');
}

// ── 1 y 2. Traer y agrupar a los alumnos ──────────────────────────
$alumnosSeleccionados = obtenerAlumnosSeleccionados($conn, $ids);

if (empty($alumnosSeleccionados)) {
    die('Ninguno de los alumnos seleccionados tiene CURP capturada; agrégala primero para poder generar sus credenciales.');
}

$gruposDeAlumnos = agruparPorGradoYGrupo($alumnosSeleccionados);
$logoDataUri = imagenADataUri(RUTA_LOGO);

// ── 3. Armar la lista de archivos a generar ───────────────────────
// [nombreArchivo => array de alumnos de ese archivo]. Si un
// grado+grupo excede MAX_ALUMNOS_POR_ARCHIVO, se parte en "_parteN".
$archivos = [];
foreach ($gruposDeAlumnos as $clave => $alumnosDelGrupo) {
    $bloques = array_chunk($alumnosDelGrupo, MAX_ALUMNOS_POR_ARCHIVO);

    if (count($bloques) === 1) {
        $archivos[$clave . '.pdf'] = $bloques[0];
    } else {
        foreach ($bloques as $indice => $bloque) {
            $archivos[$clave . '_parte' . ($indice + 1) . '.pdf'] = $bloque;
        }
    }
}

// ── 4. Generar cada PDF ────────────────────────────────────────────
// Se genera un archivo a la vez y se libera memoria entre cada uno
// para que un lote con muchos grupos no vaya acumulando memoria de
// los PDFs anteriores.
$pdfsGenerados = []; // [nombreArchivo => bytes del PDF]
foreach ($archivos as $nombreArchivo => $alumnosDelArchivo) {
    $pdfsGenerados[$nombreArchivo] = generarPdfDeGrupo($alumnosDelArchivo, $logoDataUri);
    gc_collect_cycles();
}

// ── 5. Entregar el resultado ───────────────────────────────────────
if (count($pdfsGenerados) === 1) {
    $nombreArchivo = array_key_first($pdfsGenerados);
    entregarPdfDirecto($nombreArchivo, $pdfsGenerados[$nombreArchivo]);
} else {
    entregarComoZip($pdfsGenerados, 'credenciales_por_grupo.zip');
}
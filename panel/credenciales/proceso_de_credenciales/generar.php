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
//
// CONTADOR DE PROGRESO: el navegador manda un "token" (un texto al
// azar que genera con JavaScript) junto con los ids[]. Mientras
// este archivo genera las credenciales UNA POR UNA, va escribiendo
// cuántas lleva en un archivito
// panel/credenciales/proceso_de_credenciales/progreso/<token>.json.
// panel/credenciales.php pregunta ese progreso cada cierto tiempo
// (con progreso.php) mientras espera la respuesta de esta misma
// petición, para mostrar "Generando credencial X de Y…" en el
// modal. No se usa $_SESSION para esto a propósito: PHP bloquea el
// archivo de sesión mientras un script la tiene abierta, así que
// una segunda petición (la que pregunta el progreso) se quedaría
// esperando a que ESTA termine — justo lo contrario de lo que
// queremos. Un archivo aparte, sin sesión de por medio, no tiene
// ese problema.
// ═══════════════════════════════════════════════════════════════

// Esta carpeta ("proceso_de_credenciales") vive DENTRO de
// panel/credenciales/, así que hay que subir 3 niveles para llegar
// a la raíz del proyecto (donde está conexion.php):
// proceso_de_credenciales/ → credenciales/ → panel/ → raíz.
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

// 180 segundos (3 minutos) se quedaba corto: un grado completo
// puede tardar más de eso, y PHP mataba el proceso a la mitad sin
// avisar (por eso se quedaba "cargando" para siempre y nunca
// llegaba nada). Se sube a 10 minutos de margen.
set_time_limit(600);

$ids = array_map('intval', $_POST['ids'] ?? []);

if (empty($ids)) {
    die('No seleccionaste ningún alumno. Regresa y marca al menos uno.');
}

// ─────────────────────────────────────────────────────────────
// [CONTADOR DE PROGRESO] — preparación
// ─────────────────────────────────────────────────────────────
// Solo se aceptan letras, números, guiones y guion bajo en el
// token — es un identificador para nombrar un archivo, así que se
// limpia por seguridad antes de usarlo en una ruta.
$tokenProgreso = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['token'] ?? '');
$carpetaProgreso = __DIR__ . '/progreso';

/**
 * Escribe cuántas credenciales van hechas hasta ahorita, en un
 * archivo JSON pequeño y de vida corta (se borra solo al terminar
 * la generación, ver el final de este archivo).
 */
function escribirProgreso(string $carpeta, string $token, int $hechos, int $total): void
{
    if ($token === '') {
        return; // el navegador no mandó token: no hay a quién avisarle
    }
    if (!is_dir($carpeta)) {
        mkdir($carpeta, 0777, true);
    }
    file_put_contents($carpeta . '/' . $token . '.json', json_encode(['hechos' => $hechos, 'total' => $total]));
}

$alumnosProcesados = 0;
$totalAlumnosAProcesar = 0; // se define abajo, en cuanto se sabe cuántos son

/**
 * Limpieza automática de archivos de progreso "huérfanos": si una
 * generación anterior se cortó a medias (se cerró XAMPP, se fue la
 * luz, un error inesperado) y nunca llegó a borrar su archivo, este
 * mismo se quedaría tirado en disco para siempre. Cada vez que se
 * inicia una generación nueva, se aprovecha para barrer y borrar
 * cualquier archivo de progreso con más de 30 minutos de
 * antigüedad (ninguna generación real debería tardar tanto, ya que
 * el límite de tiempo del script es de 10 minutos). Así la carpeta
 * nunca acumula basura, sin necesidad de nada externo (cron, tareas
 * programadas, etc.) — funciona igual en tu XAMPP que en el
 * servidor real de la escuela.
 */
function limpiarProgresoHuerfano(string $carpeta): void
{
    if (!is_dir($carpeta)) {
        return;
    }

    $treintaMinutos = 30 * 60;
    foreach (glob($carpeta . '/*.json') as $archivo) {
        if (time() - filemtime($archivo) > $treintaMinutos) {
            @unlink($archivo);
        }
    }
}

limpiarProgresoHuerfano($carpetaProgreso);

/**
 * Esta es la función que llama plantilla_credenciales.php una vez
 * por cada alumno ya armado (frente + reverso) — así el contador
 * avanza en tiempo real conforme se van generando, y no solo al
 * terminar cada archivo completo.
 */
function registrarProgresoCredencial(): void
{
    global $alumnosProcesados, $totalAlumnosAProcesar, $carpetaProgreso, $tokenProgreso;
    $alumnosProcesados++;
    escribirProgreso($carpetaProgreso, $tokenProgreso, $alumnosProcesados, $totalAlumnosAProcesar);
}

// ── 1 y 2. Traer y agrupar a los alumnos ──────────────────────────
$alumnosSeleccionados = obtenerAlumnosSeleccionados($conn, $ids);

if (empty($alumnosSeleccionados)) {
    die('Ninguno de los alumnos seleccionados tiene CURP capturada; agrégala primero para poder generar sus credenciales.');
}

$totalAlumnosAProcesar = count($alumnosSeleccionados);
escribirProgreso($carpetaProgreso, $tokenProgreso, 0, $totalAlumnosAProcesar);

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
// los PDFs anteriores. El contador de progreso avanza SOLO
// (llamado desde dentro de construirHojaConParejas en
// plantilla_credenciales.php), no hace falta tocarlo aquí.
$pdfsGenerados = []; // [nombreArchivo => bytes del PDF]
foreach ($archivos as $nombreArchivo => $alumnosDelArchivo) {
    $pdfsGenerados[$nombreArchivo] = generarPdfDeGrupo($alumnosDelArchivo, $logoDataUri);
    gc_collect_cycles();
}

// Ya terminó: se borra el archivito de progreso, no hace falta
// dejarlo tirado en disco.
if ($tokenProgreso !== '') {
    @unlink($carpetaProgreso . '/' . $tokenProgreso . '.json');
}

// ── 5. Entregar el resultado ───────────────────────────────────────
if (count($pdfsGenerados) === 1) {
    $nombreArchivo = array_key_first($pdfsGenerados);
    entregarPdfDirecto($nombreArchivo, $pdfsGenerados[$nombreArchivo]);
} else {
    entregarComoZip($pdfsGenerados, 'credenciales_por_grupo.zip');
}
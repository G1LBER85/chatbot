<?php

// =====================================================
// config_sesion.php
// =====================================================
// Configura las banderas de seguridad de la cookie de
// sesión ANTES de iniciarla, y arranca la sesión.
//
// Inclúyelo en CUALQUIER archivo que use $_SESSION
// (login.php, verificar_sesion.php, logout.php,
// refrescar_sesion.php, recuperar.php...) EN VEZ de
// llamar session_start() directamente.
// =====================================================

// Minutos de inactividad antes de cerrar sesión automáticamente.
define('MINUTOS_INACTIVIDAD', 15);
define('SEGUNDOS_INACTIVIDAD', MINUTOS_INACTIVIDAD * 60);

// Detecta si el sitio ya corre bajo HTTPS. Mientras estés
// probando en local sobre http://, esto será false y la
// cookie seguirá funcionando; en tu servidor real con SSL
// activado, se pondrá en true automáticamente.
$sitioUsaHttps = (
    !empty($_SERVER['HTTPS'])
    && $_SERVER['HTTPS'] !== 'off'
);

session_set_cookie_params([

    'lifetime' => 0,              // la cookie muere al cerrar el navegador

    'path'     => '/',

    'domain'   => '',

    'secure'   => $sitioUsaHttps, // solo viaja por HTTPS cuando el sitio ya tiene SSL

    'httponly' => true,           // JavaScript (y por lo tanto un ataque XSS) no puede leerla

    'samesite' => 'Strict',       // no se envía en peticiones desde otros sitios

]);

session_start();

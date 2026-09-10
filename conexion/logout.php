<?php
// ============================================================
// logout.php
// Cierra la sesión del owner y vuelve al login.
// ============================================================

session_start();

// Vaciar todas las variables de sesión
$_SESSION = [];

// Invalidar la cookie de sesión en el navegador
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Destruir la sesión en el servidor
session_destroy();

header('Location: beta_login.php');
exit;

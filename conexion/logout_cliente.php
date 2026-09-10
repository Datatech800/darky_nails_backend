<?php
// ============================================================
// logout_cliente.php
// Cierra la sesión del cliente (página pública) y redirige a la
// página de inicio. No requiere CSRF porque solo elimina la sesión.
// ============================================================

session_start();

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

session_destroy();

header('Location: ' . (isset($_GET['vuelve']) ? $_GET['vuelve'] : 'beta_darky.php'));
exit;
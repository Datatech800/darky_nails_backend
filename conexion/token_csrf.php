<?php
// ============================================================
// token_csrf.php
// Devuelve el token CSRF de la sesión en JSON.
// Lo pide el formulario público "Crear cuenta" de beta_darky.php
// para usarlo en la llamada AJAX a conexion/registro.php.
// ============================================================

session_start();

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

echo json_encode(['token' => $_SESSION['csrf_token']]);
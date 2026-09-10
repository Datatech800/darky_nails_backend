<?php
// ============================================================
// csrf_token.php
// Devuelve el token CSRF de la sesión en JSON.
// Lo pide el dashboard (HTML estático) para luego usarlo
// en las llamadas AJAX a buscar_cliente.php y guardar_cliente.php
// ============================================================

session_start();

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

echo json_encode(['token' => $_SESSION['csrf_token']]);

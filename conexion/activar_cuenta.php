<?php
// ============================================================
// activar_cuenta.php
// Activa la cuenta de un cliente usando el token del link del email.
// Endpoint público: /conexion/activar_cuenta.php?token=...
//
// Reglas:
//   - El token debe existir y no estar expirado (24h).
//   - Una vez usado, el token se limpia (no reutilizable).
//   - Si la cuenta ya está activa, simplemente se informa.
// ============================================================

require_once __DIR__ . '/conexion.php';

$token = isset($_GET['token']) ? (string) $_GET['token'] : '';

function redirigir(string $ruta): void
{
    header('Location: ' . rtrim(BASE_URL, '/') . '/' . ltrim($ruta, '/'));
    exit;
}

if ($token === '' || strlen($token) !== 64) {
    redirigir('beta_darky.php');
}

try {
    $pdo = pdo();

    $stmt = $pdo->prepare(
        'SELECT id_nombre, estado, token_expira
           FROM clientes
          WHERE token_verificacion = :token
          LIMIT 1'
    );
    $stmt->execute([':token' => $token]);
    $cliente = $stmt->fetch();

    // Token inválido
    if (!$cliente) {
        redirigir('beta_darky.php');
    }

    // Ya activa → sin cambios
    if ($cliente['estado'] === 'activo') {
        redirigir('beta_darky.php?cuenta=activa');
    }

    // Expirado
    if (strtotime($cliente['token_expira']) < time()) {
        redirigir('beta_darky.php?cuenta=expirado');
    }

    // Activar y limpiar el token
    $stmt = $pdo->prepare(
        'UPDATE clientes
            SET estado = :estado, token_verificacion = NULL, token_expira = NULL
          WHERE id_nombre = :id'
    );
    $stmt->execute([':estado' => 'activo', ':id' => $cliente['id_nombre']]);

    redirigir('beta_darky.php?cuenta=ok');
} catch (PDOException $e) {
    error_log('activar_cuenta: ' . $e->getMessage());
    redirigir('beta_darky.php');
}
<?php
// ============================================================
// verificar_login.php
// Procesa el POST del formulario de login.
// - Prepared statements contra la tabla owners
// - password_verify() para comparar el hash
// - Límite de intentos (protección básica contra fuerza bruta)
// ============================================================

session_start();
require __DIR__ . '/conexion.php';

// ── Estado de intentos para fuerza bruta ──
if (!isset($_SESSION['intentos'])) {
    $_SESSION['intentos'] = 0;
}

// Si ya superó el límite, bloqueamos por 5 minutos
if ($_SESSION['intentos'] >= 5) {
    $bloqueoHasta = isset($_SESSION['bloqueo_hasta']) ? $_SESSION['bloqueo_hasta'] : 0;
    if (time() < $bloqueoHasta) {
        header('Location: beta_login.php?error=2');
        exit;
    }
    // Pasó el tiempo de bloqueo: se reinicia el contador
    $_SESSION['intentos'] = 0;
    unset($_SESSION['bloqueo_hasta']);
}

// Solo se acepta POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: beta_login.php?error=1');
    exit;
}

$usuario  = isset($_POST['usuario']) ? trim($_POST['usuario']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

// ── Consulta preparada: el usuario va como parámetro (anti SQL injection) ──
try {
    $stmt = pdo()->prepare('SELECT id, contrasena_hash FROM owners WHERE usuario = ? LIMIT 1');
    $stmt->execute([$usuario]);
    $owner = $stmt->fetch();
} catch (PDOException $e) {
    error_log('verificar_login: error consulta: ' . $e->getMessage());
    header('Location: beta_login.php?error=1');
    exit;
}

// ── Verificación del hash (nunca se compara la contraseña en texto plano) ──
if ($owner && password_verify($password, $owner['contrasena_hash'])) {
    // Éxito: regenerar el ID de sesión evita session fixation
    session_regenerate_id(true);

    $_SESSION['owner_id']      = $owner['id'];
    $_SESSION['owner_usuario'] = $usuario;

    // Resetear contador de intentos
    $_SESSION['intentos'] = 0;
    unset($_SESSION['bloqueo_hasta']);

    header('Location: ../beta_dashboard.php');
    exit;
}

// ── Login fallido: se registra el intento ──
$_SESSION['intentos']++;

if ($_SESSION['intentos'] >= 5) {
    // 5 fallos = bloqueo de 5 minutos
    $_SESSION['bloqueo_hasta'] = time() + 300;
}

header('Location: beta_login.php?error=1');
exit;

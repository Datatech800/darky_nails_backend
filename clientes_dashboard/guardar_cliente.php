<?php
// ============================================================
// guardar_cliente.php
// Endpoint AJAX: registra un nuevo cliente en la BD.
// Devuelve JSON. Misma lógica que guardar_datos.php de TEST_CLIENTES
// + validación de token CSRF.
// ============================================================

session_start();

error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');

// ---------- 1) Solo aceptar POST ----------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Método no permitido
    echo json_encode(['error' => 'Método no permitido.']);
    exit;
}

// ---------- 2) Validar token CSRF ----------
$tokenEnviado = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $tokenEnviado)) {
    http_response_code(403); // Prohibido
    echo json_encode(['error' => 'Token CSRF inválido.']);
    exit;
}

// ---------- 3) Conexión PDO segura ----------
require_once __DIR__ . '/../conexion/conexion.php';
require_once __DIR__ . '/../conexion/palabras_prohibidas.php';
require_once __DIR__ . '/../conexion/validaciones.php';

try {
    $pdo = pdo();
} catch (PDOException $e) {
    error_log('Error de conexión: ' . $e->getMessage());
    echo json_encode(['error' => 'No se pudo conectar con la base de datos.']);
    exit;
}

// ---------- 4) Recuperar y limpiar datos ----------
$nombre           = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
$numero           = isset($_POST['numero']) ? trim($_POST['numero']) : '';
$correo           = isset($_POST['correo']) ? trim($_POST['correo']) : '';
$usuario          = isset($_POST['usuario']) ? trim($_POST['usuario']) : '';
$password         = isset($_POST['password']) ? $_POST['password'] : '';
$confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

$errores = [];

// ---------- 5) Validación servidor: mínimos y máximos ----------
$errores = array_merge($errores, validarNombreApellido($nombre));

if (strlen($numero) < 8 || strlen($numero) > 20 || !ctype_digit($numero)) {
    $errores[] = 'El número debe tener entre 8 y 20 caracteres numéricos.';
}

if (mb_strlen($correo) < 5 || mb_strlen($correo) > 30) {
    $errores[] = 'El correo debe tener entre 5 y 30 caracteres.';
}

if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    $errores[] = 'El correo no es válido.';
}

if (mb_strlen($usuario) < 5 || mb_strlen($usuario) > 30) {
    $errores[] = 'El usuario debe tener entre 5 y 30 caracteres.';
}

if (mb_strlen($password) < 5 || mb_strlen($password) > 20) {
    $errores[] = 'La contraseña debe tener entre 5 y 20 caracteres.';
}

if (mb_strlen($confirm_password) < 5 || mb_strlen($confirm_password) > 20) {
    $errores[] = 'La confirmación de contraseña debe tener entre 5 y 20 caracteres.';
}

if ($password !== $confirm_password) {
    $errores[] = 'Las contraseñas no coinciden.';
}

// ---------- 6) Verificar que el email no esté registrado ----------
if (empty($errores)) {
    $stmt = $pdo->prepare('SELECT id_nombre FROM clientes WHERE correo = :correo LIMIT 1');
    $stmt->execute([':correo' => $correo]);

    if ($stmt->fetch()) {
        $errores[] = 'El correo electrónico ya está registrado.';
    }
}

// ---------- 7) Registrar ----------
if (empty($errores)) {
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare(
        'INSERT INTO clientes (nombre, numero, correo, usuario, contrasena) VALUES (:nombre, :numero, :correo, :usuario, :password)'
    );

    $stmt->execute([
        ':nombre'   => $nombre,
        ':numero'   => $numero,
        ':correo'   => $correo,
        ':usuario'  => $usuario,
        ':password' => $passwordHash,
    ]);

    echo json_encode(['ok' => true]);
} else {
    echo json_encode(['ok' => false, 'errores' => $errores]);
}

<?php
// ============================================================
// login_cliente.php
// Endpoint AJAX (POST + CSRF): inicio de sesión del cliente para
// poder sacar turnos. Acepta clientes en estado 'activo'
// (aprobados) y también 'pendiente' (pueden reservar, pero la
// cuenta la aprueba/rechaza la dueña desde el dashboard).
// No ingresa a clientes 'rechazado'.
// Guarda la sesión del cliente (cliente_id + nombre).
// ============================================================

session_start();

error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');

// ---------- 1) Solo aceptar POST ----------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.']);
    exit;
}

// ---------- 2) Validar token CSRF ----------
$tokenEnviado = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $tokenEnviado)) {
    http_response_code(403);
    echo json_encode(['error' => 'Token CSRF inválido.']);
    exit;
}

// ---------- 3) Conexión PDO segura ----------
require_once __DIR__ . '/conexion.php';

try {
    $pdo = pdo();
} catch (PDOException $e) {
    error_log('login_cliente: conexión: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo conectar con la base de datos.']);
    exit;
}

// ---------- 4) Obtener y validar credenciales ----------
$usuario   = isset($_POST['usuario']) ? trim($_POST['usuario']) : '';
$password  = isset($_POST['password']) ? (string) $_POST['password'] : '';

if ($usuario === '' || $password === '') {
    echo json_encode(['error' => 'Ingresá tu usuario y contraseña.']);
    exit;
}

// ---------- 5) Buscar y autenticar ----------
try {
    $stmt = $pdo->prepare(
        "SELECT id_nombre, nombre, usuario, contrasena, estado
         FROM clientes
         WHERE usuario = :usuario
         LIMIT 1"
    );
    $stmt->execute([':usuario' => $usuario]);
    $cliente = $stmt->fetch();

    if (!$cliente || !password_verify($password, $cliente['contrasena'])) {
        echo json_encode(['error' => 'Usuario o contraseña incorrectos.']);
        exit;
    }

    if ($cliente['estado'] === 'rechazado') {
        echo json_encode(['error' => 'Tu cuenta fue rechazada. Contactate con Darky Nails.']);
        exit;
    }

    // La sesión solo con el id (no guardamos datos sensibles)
    $_SESSION['cliente_id']  = (int) $cliente['id_nombre'];
    $_SESSION['cliente_nombre'] = $cliente['nombre'];

    echo json_encode([
        'ok'     => true,
        'nombre' => $cliente['nombre'],
        'estado' => $cliente['estado'],
    ]);
} catch (PDOException $e) {
    error_log('login_cliente: consulta: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Ocurrió un error al iniciar sesión.']);
}
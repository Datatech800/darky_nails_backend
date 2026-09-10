<?php
// ============================================================
// agregar_servicio.php
// Endpoint AJAX (POST): agrega un servicio nuevo a la tabla
// `servicios`. Valida token CSRF y sesión de owner. Devuelve JSON.
// ============================================================

session_start();

error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');

// ---------- 1) Owner con sesión ----------
if (!isset($_SESSION['owner_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado.']);
    exit;
}

// ---------- 2) Solo POST ----------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.']);
    exit;
}

// ---------- 3) Validar token CSRF ----------
$tokenEnviado = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $tokenEnviado)) {
    http_response_code(403);
    echo json_encode(['error' => 'Token CSRF inválido.']);
    exit;
}

// ---------- 4) Datos ----------
$categoriasPermitidas = ['semis', 'capping', 'esculpidas', 'garras', 'cejas'];

$categoria = isset($_POST['categoria']) ? trim($_POST['categoria']) : '';
$nombre    = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
$precio    = isset($_POST['precio']) ? trim($_POST['precio']) : '';
$duracion  = isset($_POST['duracion']) ? trim($_POST['duracion']) : '';

if (!in_array($categoria, $categoriasPermitidas, true)) {
    echo json_encode(['error' => 'Categoría inválida.']);
    exit;
}

if ($nombre === '' || mb_strlen($nombre, 'UTF-8') > 50) {
    echo json_encode(['error' => 'Ingresá un nombre de servicio válido (máx. 50 caracteres).']);
    exit;
}

// Acepta entero o decimal, ej: 22000 / 22000,50
$precioLimpio = str_replace(['.', ','], ['', '.'], $precio);
if (!is_numeric($precioLimpio) || $precioLimpio < 0 || $precioLimpio > 999999) {
    echo json_encode(['error' => 'El precio debe ser un número entre 0 y 999.999.']);
    exit;
}
$precioFinal = round((float) $precioLimpio, 2);

if (!ctype_digit((string) $duracion) || (int) $duracion < 1 || (int) $duracion > 1440) {
    echo json_encode(['error' => 'La duración debe ser un número de minutos entre 1 y 1440.']);
    exit;
}
$duracionFinal = (int) $duracion;

// ---------- 5) Conexión PDO segura ----------
require_once __DIR__ . '/../conexion/conexion.php';

try {
    $pdo = pdo();
} catch (PDOException $e) {
    error_log('Error de conexión: ' . $e->getMessage());
    echo json_encode(['error' => 'No se pudo conectar con la base de datos.']);
    exit;
}

try {
    // No permitir nombres duplicados dentro de la misma categoría
    $check = $pdo->prepare('SELECT id_servicio FROM servicios WHERE servicio = :nombre AND categoria = :categoria');
    $check->execute([':nombre' => $nombre, ':categoria' => $categoria]);
    if ($check->fetch()) {
        echo json_encode(['error' => 'Ya existe un servicio con ese nombre en esta categoría.']);
        exit;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO servicios (servicio, categoria, precio, duracion) VALUES (:nombre, :categoria, :precio, :duracion)'
    );
    $stmt->execute([
        ':nombre'    => $nombre,
        ':categoria' => $categoria,
        ':precio'    => $precioFinal,
        ':duracion'  => $duracionFinal,
    ]);

    echo json_encode(['ok' => true, 'id_servicio' => (int) $pdo->lastInsertId()]);

} catch (PDOException $e) {
    error_log('Error al agregar servicio: ' . $e->getMessage());
    echo json_encode(['error' => 'Ocurrió un error al agregar el servicio.']);
}

<?php
// ============================================================
// actualizar_servicio.php
// Endpoint AJAX (POST): actualiza el precio y la duración de un
// servicio. Valida token CSRF y sesión de owner. Devuelve JSON.
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
$idServicio = isset($_POST['id_servicio']) ? (int) $_POST['id_servicio'] : 0;
$precio     = isset($_POST['precio']) ? trim($_POST['precio']) : '';
$duracion   = isset($_POST['duracion']) ? trim($_POST['duracion']) : '';

if ($idServicio <= 0) {
    echo json_encode(['error' => 'Servicio inválido.']);
    exit;
}

// ---------- 5) Validar precio ----------
// Acepta entero o decimal, ej: 22000 / 22000,50
$precioLimpio = str_replace(['.', ','], ['', '.'], $precio);

if (!is_numeric($precioLimpio) || $precioLimpio < 0 || $precioLimpio > 999999) {
    echo json_encode(['error' => 'El precio debe ser un número entre 0 y 999.999.']);
    exit;
}

$precioFinal = round((float) $precioLimpio, 2);

// ---------- 6) Validar duración ----------
if (!ctype_digit((string) $duracion) || (int) $duracion < 1 || (int) $duracion > 1440) {
    echo json_encode(['error' => 'La duración debe ser un número de minutos entre 1 y 1440.']);
    exit;
}

$duracionFinal = (int) $duracion;

// ---------- 7) Conexión PDO segura ----------
require_once __DIR__ . '/../conexion/conexion.php';

try {
    $pdo = pdo();
} catch (PDOException $e) {
    error_log('Error de conexión: ' . $e->getMessage());
    echo json_encode(['error' => 'No se pudo conectar con la base de datos.']);
    exit;
}

// ---------- 8) Actualizar ----------
try {
    $stmt = $pdo->prepare('UPDATE servicios SET precio = :precio, duracion = :duracion WHERE id_servicio = :id');
    $stmt->execute([':precio' => $precioFinal, ':duracion' => $duracionFinal, ':id' => $idServicio]);

    // Si no cambió ninguna fila puede ser porque los valores eran los mismos:
    // verificamos que el servicio exista de todos modos.
    if ($stmt->rowCount() === 0) {
        $check = $pdo->prepare('SELECT id_servicio FROM servicios WHERE id_servicio = :id');
        $check->execute([':id' => $idServicio]);
        if (!$check->fetch()) {
            echo json_encode(['error' => 'El servicio no existe.']);
            exit;
        }
    }

    echo json_encode(['ok' => true, 'precio' => $precioFinal, 'duracion' => $duracionFinal]);

} catch (PDOException $e) {
    error_log('Error al actualizar servicio: ' . $e->getMessage());
    echo json_encode(['error' => 'Ocurrió un error al actualizar el servicio.']);
}

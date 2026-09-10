<?php
// ============================================================
// pendientes_cliente.php
// Endpoint AJAX (POST + CSRF): devuelve los clientes en estado
// 'pendiente' (autoregistrados en beta_darky.php y aún no
// aprobados por la dueña).
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
require_once __DIR__ . '/../conexion/conexion.php';

try {
    $pdo = pdo();
} catch (PDOException $e) {
    error_log('Error de conexión: ' . $e->getMessage());
    echo json_encode(['error' => 'No se pudo conectar con la base de datos.']);
    exit;
}

// ---------- 4) Consultar pendientes ----------
try {
    $sql = "SELECT id_nombre, nombre, numero, correo, usuario, fecha_registro
            FROM clientes
            WHERE estado = 'pendiente'
            ORDER BY fecha_registro DESC";

    $stmt = $pdo->query($sql);
    $pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ---------- 5) Sanitizar la salida ----------
    $pendientesSanitizados = array_map(function ($fila) {
        return [
            'id_nombre'      => htmlspecialchars($fila['id_nombre'], ENT_QUOTES, 'UTF-8'),
            'nombre'         => htmlspecialchars($fila['nombre'], ENT_QUOTES, 'UTF-8'),
            'numero'         => htmlspecialchars($fila['numero'], ENT_QUOTES, 'UTF-8'),
            'correo'         => htmlspecialchars($fila['correo'], ENT_QUOTES, 'UTF-8'),
            'usuario'        => htmlspecialchars($fila['usuario'], ENT_QUOTES, 'UTF-8'),
            'fecha_registro' => htmlspecialchars($fila['fecha_registro'], ENT_QUOTES, 'UTF-8'),
        ];
    }, $pendientes);

    echo json_encode(['pendientes' => $pendientesSanitizados]);

} catch (PDOException $e) {
    error_log('Error en la consulta de pendientes: ' . $e->getMessage());
    echo json_encode(['error' => 'Ocurrió un error al cargar los pendientes.']);
}
<?php
// ============================================================
// aprobar_cliente.php
// Endpoint AJAX (POST + CSRF): la dueña decide sobre un cliente
// en estado 'pendiente'. Acepta dos acciones:
//   aprobar   -> estado 'activo' (se limpia token de verificación)
//   rechazar  -> estado 'rechazado'
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

// ---------- 4) Parámetros ----------
$id     = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$accion = isset($_POST['accion']) ? $_POST['accion'] : '';

if ($id <= 0 || !in_array($accion, ['aprobar', 'rechazar'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Parámetros inválidos.']);
    exit;
}

// ---------- 5) Actualizar estado ----------
$estadoNuevo = ($accion === 'aprobar') ? 'activo' : 'rechazado';

try {
    // La dueña es la única fuente de verdad en este paso: se aprobar
    // aunque haya expirado el token de email.
    $sql = "UPDATE clientes
            SET estado = :estado,
                token_verificacion = NULL,
                token_expira        = NULL
            WHERE id_nombre = :id
              AND estado   = 'pendiente'";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':estado' => $estadoNuevo,
        ':id'     => $id,
    ]);

    if ($stmt->rowCount() === 0) {
        http_response_code(409);
        echo json_encode(['error' => 'El cliente ya no está pendiente.']);
        exit;
    }

    echo json_encode(['ok' => true, 'estado' => $estadoNuevo]);

} catch (PDOException $e) {
    error_log('Error al decidir cliente pendiente: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Ocurrió un error al actualizar el cliente.']);
}
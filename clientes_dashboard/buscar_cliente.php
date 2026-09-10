<?php
// ============================================================
// buscar_cliente.php
// Endpoint AJAX: recibe la búsqueda por POST, consulta la BD
// con sentencias preparadas y devuelve un JSON.
// ============================================================

session_start();

// En producción NO se muestran errores al usuario.
// El detalle va al log de PHP (error_log) para el desarrollador.
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

try {
    $pdo = pdo();
} catch (PDOException $e) {
    // Detalle solo en el log, mensaje genérico para el cliente
    error_log('Error de conexión: ' . $e->getMessage());
    echo json_encode(['error' => 'No se pudo conectar con la base de datos.']);
    exit;
}

// ---------- 4) Obtener y limpiar el término de búsqueda ----------
$busqueda = isset($_POST['busqueda']) ? trim($_POST['busqueda']) : '';

try {
    // Búsqueda parcial con LIKE (el término va como parámetro: anti SQL injection)
    $like = '%' . $busqueda . '%';

    $sql = "SELECT id_nombre, nombre, numero, correo, usuario
            FROM clientes
            WHERE nombre LIKE :q1
               OR numero LIKE :q2
               OR correo LIKE :q3
               OR usuario LIKE :q4
            ORDER BY nombre ASC
            LIMIT 20"; // Límite de resultados

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':q1', $like, PDO::PARAM_STR);
    $stmt->bindValue(':q2', $like, PDO::PARAM_STR);
    $stmt->bindValue(':q3', $like, PDO::PARAM_STR);
    $stmt->bindValue(':q4', $like, PDO::PARAM_STR);
    $stmt->execute();

    $resultados = $stmt->fetchAll();

    // ---------- 5) Sanitizar la salida (htmlspecialchars) ----------
    // Cada valor se escapa antes de viajar en el JSON
    $resultadosSanitizados = array_map(function ($fila) {
        return [
            'id_nombre' => htmlspecialchars($fila['id_nombre'], ENT_QUOTES, 'UTF-8'),
            'nombre'    => htmlspecialchars($fila['nombre'], ENT_QUOTES, 'UTF-8'),
            'numero'    => htmlspecialchars($fila['numero'], ENT_QUOTES, 'UTF-8'),
            'correo'    => htmlspecialchars($fila['correo'], ENT_QUOTES, 'UTF-8'),
            'usuario'   => htmlspecialchars($fila['usuario'], ENT_QUOTES, 'UTF-8'),
        ];
    }, $resultados);

    echo json_encode(['resultados' => $resultadosSanitizados]);

} catch (PDOException $e) {
    // Error genérico al cliente; detalle solo en el log
    error_log('Error en la consulta: ' . $e->getMessage());
    echo json_encode(['error' => 'Ocurrió un error al buscar.']);
}

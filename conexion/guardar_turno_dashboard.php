<?php
// ============================================================
// guardar_turno_dashboard.php
// Endpoint AJAX (POST + CSRF): el owner crea un turno a mano
// desde el dashboard.
// - Busca el cliente por nombre exacto (case-insensitive):
//   si existe lo asocia (cliente_id), si no guarda nombre libre
//   en nombre_cliente.
// - La duración se toma de la tabla `servicios` según el servicio
//   elegido (no se confía en lo enviado por el cliente).
// Solo para owner con sesión activa.
// ============================================================

session_start();

error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');

// ---------- 1) Solo owner con sesión ----------
if (!isset($_SESSION['owner_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado.']);
    exit;
}

// ---------- 2) Solo POST con CSRF ----------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.']);
    exit;
}

if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Token CSRF inválido.']);
    exit;
}

// ---------- 3) Datos básicos ----------
$nombre    = trim($_POST['nombre'] ?? '');
$servicio  = trim($_POST['servicio'] ?? '');
$variante  = trim($_POST['variante'] ?? '');
$fecha     = trim($_POST['fecha'] ?? '');
$hora      = trim($_POST['hora'] ?? '');

$errores = [];

if ($nombre === '') { $errores[] = 'Ingresá el nombre del cliente.'; }
if ($fecha === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
    $errores[] = 'La fecha es inválida.';
} elseif (strtotime($fecha) < strtotime(date('Y-m-d'))) {
    $errores[] = 'No se puede agendar un turno en el pasado.';
}
if ($hora === '' || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $hora)) {
    $errores[] = 'La hora es inválida.';
}

require_once __DIR__ . '/conexion.php';

try {
    $pdo = pdo();
} catch (PDOException $e) {
    error_log('guardar_turno_dashboard: conexión: ' . $e->getMessage());
    echo json_encode(['error' => 'No se pudo conectar con la base de datos.']);
    exit;
}

// ---------- 4) Validar servicio + duracion desde la BD ----------
try {
    $stmt = $pdo->prepare("SELECT duracion FROM servicios WHERE categoria = ? AND servicio = ? LIMIT 1");
    $stmt->execute([$servicio, $variante]);
    $fila = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('guardar_turno_dashboard: servicio: ' . $e->getMessage());
    echo json_encode(['error' => 'Ocurrió un error al validar el servicio.']);
    exit;
}

if (!$fila) {
    $errores[] = 'Seleccioná un servicio válido.';
}

if ($errores) {
    http_response_code(400);
    echo json_encode(['errores' => $errores]);
    exit;
}

$duracion = (int) $fila['duracion'];

// ---------- 5) Buscar cliente por nombre ----------
try {
    $stmt = $pdo->prepare("SELECT id_nombre FROM clientes WHERE LOWER(nombre) = LOWER(?) LIMIT 1");
    $stmt->execute([$nombre]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('guardar_turno_dashboard: cliente: ' . $e->getMessage());
    echo json_encode(['error' => 'Ocurrió un error al buscar el cliente.']);
    exit;
}

$clienteId = $cliente ? (int) $cliente['id_nombre'] : null;
$nombreLibre = $cliente ? null : $nombre;

// ---------- 6) Evitar superposición con otro turno ----------
try {
    $finStr = date('H:i', strtotime($fecha . ' ' . $hora) + $duracion * 60);

    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS n
        FROM turnos
        WHERE fecha   = ?
          AND estado  != 'cancelado'
          AND hora    < ?
          AND TIME(ADDTIME(hora, SEC_TO_TIME(duracion * 60))) > ?
    ");
    $stmt->execute([$fecha, $finStr, $hora]);
    $solapa = (int) $stmt->fetch(PDO::FETCH_ASSOC)['n'];
} catch (PDOException $e) {
    error_log('guardar_turno_dashboard: overlap: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Ocurrió un error al verificar el horario.']);
    exit;
}

if ($solapa > 0) {
    http_response_code(409);
    echo json_encode(['error' => 'Ese horario ya está ocupado. Elegí otra hora.']);
    exit;
}

// ---------- 7) Guardar turno ----------
try {
    $stmt = $pdo->prepare("
        INSERT INTO turnos (cliente_id, nombre_cliente, servicio, variante, fecha, hora, duracion, estado)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'reservado')
    ");
    $stmt->execute([$clienteId, $nombreLibre, $servicio, $variante, $fecha, $hora, $duracion]);

    echo json_encode(['ok' => true, 'mensaje' => 'Turno guardado con éxito!', 'duracion' => $duracion]);

} catch (PDOException $e) {
    error_log('guardar_turno_dashboard: insert: ' . $e->getMessage());
    echo json_encode(['error' => 'No se pudo guardar el turno.']);
}
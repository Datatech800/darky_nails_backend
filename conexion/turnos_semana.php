<?php
// ============================================================
// turnos_semana.php
// Endpoint AJAX (POST + CSRF): devuelve los turnos ocupados de
// una semana (Lun a Sáb) para que el calendario marque horas no
// disponibles. No requiere sesión de cliente (solo lectura).
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
    error_log('turnos_semana: conexión: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo conectar con la base de datos.']);
    exit;
}

// ---------- 4) Rangos ----------
// Se recibe lunes (Y-m-d) y se consulta hasta el sábado siguiente
$lunes = isset($_POST['lunes']) ? trim($_POST['lunes']) : '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $lunes)) {
    echo json_encode(['error' => 'Fecha inicial inválida.']);
    exit;
}
$from = date('Y-m-d', strtotime($lunes));
$to   = date('Y-m-d', strtotime($lunes . ' +5 days'));

// ---------- 5) Consultar turnos ocupados ----------
try {
    $sql = "SELECT fecha, hora, duracion, servicio, variante
            FROM turnos
            WHERE fecha BETWEEN :from AND :to
              AND estado != 'cancelado'
            ORDER BY fecha, hora";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':from' => $from, ':to' => $to]);
    $turnos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $resultado = array_map(function ($t) {
        return [
            'fecha'    => $t['fecha'],
            'hora'     => $t['hora'],
            'duracion' => (int) $t['duracion'],
            'servicio' => htmlspecialchars($t['servicio'], ENT_QUOTES, 'UTF-8'),
            'variante' => htmlspecialchars($t['variante'], ENT_QUOTES, 'UTF-8'),
        ];
    }, $turnos);

    echo json_encode(['turnos' => $resultado]);
} catch (PDOException $e) {
    error_log('turnos_semana: consulta: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Ocurrió un error al cargar los turnos.']);
}
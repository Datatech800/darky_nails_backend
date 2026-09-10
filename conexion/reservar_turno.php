<?php
// ============================================================
// reservar_turno.php
// Endpoint AJAX (POST + CSRF): el cliente logueado guarda su turno.
// - Valida que haya una sesión de cliente activa.
// - Valida fecha/hora/servicio/variante.
// - Evita superposición con otro turno en la misma franja.
// - Devuelve JSON con resultado.
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

// ---------- 3) Requiere sesión de cliente ----------
if (!isset($_SESSION['cliente_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Iniciá sesión para reservar un turno.']);
    exit;
}

require_once __DIR__ . '/conexion.php';

try {
    $pdo = pdo();
} catch (PDOException $e) {
    error_log('reservar_turno: conexión: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo conectar con la base de datos.']);
    exit;
}

// ---------- 4) Recuperar y validar datos ----------
$servicio  = isset($_POST['servicio']) ? trim($_POST['servicio']) : '';
$variante  = isset($_POST['variante']) ? trim($_POST['variante']) : '';
$fechaStr  = isset($_POST['fecha']) ? trim($_POST['fecha']) : '';
$horaStr   = isset($_POST['hora']) ? trim($_POST['hora']) : '';

$errores = [];

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaStr)) {
    $errores[] = 'Fecha inválida.';
} else {
    $fecha = DateTime::createFromFormat('Y-m-d', $fechaStr);
    if (!$fecha || $fecha->format('Y-m-d') !== $fechaStr) {
        $errores[] = 'Fecha inválida.';
    }
}

if (!preg_match('/^\d{2}:\d{2}$/', $horaStr)) {
    $errores[] = 'Hora inválida.';
}

// Servicio y variante: texto corto permitido (los mismos del formulario)
$serviciosValidos = ['semis', 'capping', 'esculpidas', 'garras', 'cejas'];
if (!in_array($servicio, $serviciosValidos, true)) {
    $errores[] = 'Servicio inválido.';
}

if (mb_strlen($variante) < 1 || mb_strlen($variante) > 60) {
    $errores[] = 'Variante inválida.';
}

if (!empty($errores)) {
    echo json_encode(['error' => implode(' ', $errores)]);
    exit;
}

// ---------- 5) Buscar duración del servicio según la variante en la BD ----------
// La variante llega como texto visible (ej. "Semi + NailArt"); se traduce a la
// clave interna de la tabla `servicios` (ej. semi_nailart) para su duración.
$mapaVariantes = [
    'semis'      => ['Semi liso' => 'semi_liso', 'Semi + NailArt' => 'semi_nailart', 'Semi + Full NailArt' => 'semi_fullnailart'],
    'capping'    => ['Capping gel liso' => 'capping_gel', 'Capping + NailArt' => 'capping_nailart', 'Capping + Full NailArt' => 'capping_fullnailart'],
    'esculpidas' => ['Esculpidas lisas' => 'esculpida_lisa', 'Esculpidas + NailArt' => 'esculpida_nailart', 'Esculpidas + Full NailArt' => 'esculpida_fullnailart'],
    'garras'     => ['Garras cortas' => 'garra_corta', 'Garras XL' => 'garra_xl'],
    'cejas'      => ['Cejas' => 'ceja', 'Pestañas' => 'pestania', 'Combo Cejas + Pestañas' => 'ceja_pestania'],
];
$claveInterna = $mapaVariantes[$servicio][$variante] ?? null;

try {
    if ($claveInterna !== null) {
        $stmt = $pdo->prepare('SELECT duracion FROM servicios WHERE categoria = :categoria AND servicio = :servicio LIMIT 1');
        $stmt->execute([':categoria' => $servicio, ':servicio' => $claveInterna]);
        $fila = $stmt->fetch();
    }
    $duracion = ($claveInterna !== null && $fila) ? (int) $fila['duracion'] : 60;
} catch (PDOException $e) {
    $duracion = 60;
}

// ---------- 6) Chequear superposición ----------
// Franja: [fecha hora, fecha hora+duración). Se rechaza si un turno
// existente (estado != 'cancelado') ocupa el rango.
try {
    $finStr = date('H:i', strtotime($fechaStr . ' ' . $horaStr) + $duracion * 60);

    $sql = "SELECT COUNT(*) AS n
            FROM turnos
            WHERE fecha   = :fecha
              AND estado  != 'cancelado'
              AND hora    < :fin
              AND TIME(ADDTIME(hora, SEC_TO_TIME(duracion * 60))) > :inicio";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':fecha'  => $fechaStr,
        ':fin'    => $finStr,
        ':inicio' => $horaStr,
    ]);
    $n = (int) $stmt->fetch()['n'];

    if ($n > 0) {
        echo json_encode(['error' => 'Ese horario ya está ocupado. Elegí otra hora.']);
        exit;
    }
} catch (PDOException $e) {
    error_log('reservar_turno: overlap: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Ocurrió un error al verificar el turno.']);
    exit;
}

// ---------- 7) Guardar turno ----------
try {
    $stmt = $pdo->prepare(
        'INSERT INTO turnos (cliente_id, servicio, variante, fecha, hora, duracion)
         VALUES (:cliente, :servicio, :variante, :fecha, :hora, :duracion)'
    );
    $stmt->execute([
        ':cliente'  => (int) $_SESSION['cliente_id'],
        ':servicio' => $servicio,
        ':variante' => $variante,
        ':fecha'    => $fechaStr,
        ':hora'     => $horaStr,
        ':duracion' => $duracion,
    ]);

    // Confirmación por email/WhatsApp (modo ejemplo: log)
    $link = [
        'Fecha'   => $fechaStr,
        'Hora'    => $horaStr,
        'Servicio' => ucfirst($servicio),
        'Variante' => ucfirst($variante),
    ];

    // TODO: enviar_email_verificacion / WhatsApp. Por ahora sólo verificación:
    error_log('Turno reservado: ' . json_encode($link, JSON_UNESCAPED_UNICODE));

    echo json_encode(['ok' => true, 'mensaje' => 'Turno sacado con éxito!']);
} catch (PDOException $e) {
    error_log('reservar_turno: insert: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo guardar el turno.']);
}
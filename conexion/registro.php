<?php
// ============================================================
// registro.php
// Endpoint AJAX (POST + CSRF): da de alta una nueva cuenta de cliente.
//
// Flujo:
//   1. Método POST + token CSRF.
//   2. Capa 1 anti-spam:
//      a. Honeypot: campo oculto que los bots completan.
//      b. Time-trap: rechaza envíos en menos de 3 segundos.
//      c. Rate limit por IP (máx. 3 registros por hora por IP).
//   3. Validaciones de campos + filtro de palabras prohibidas.
//   4. Insertar cliente con estado 'pendiente', token de verificación
//      con expiración (24h) e IP de origen.
//   5. intentar enviar email de verificación (enviar_email_verificacion);
//      si el envío no está disponible se conserva pendiente (la dueña
//      puede aprobarlo desde el dashboard).
// ============================================================

session_start();

error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/palabras_prohibidas.php';
require_once __DIR__ . '/validaciones.php';

// ── Utilidad de respuesta JSON ──
function responderJson($data, int $codigo = 200): void
{
    http_response_code($codigo);
    echo json_encode($data);
    exit;
}

// ---------- 1) Solo POST ----------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJson(['error' => 'Método no permitido.'], 405);
}

// ---------- 2) Token CSRF ----------
$tokenEnviado = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $tokenEnviado)) {
    responderJson(['error' => 'Token CSRF inválido.'], 403);
}

// ---------- 3a) Honeypot: si está lleno es un bot ----------
if (!empty($_POST['website'])) {
    // Respondemos éxito falso para no avisarle al bot.
    responderJson(['ok' => true]);
}

// ---------- 3b) Time-trap: menos de 3 segundos = bot ----------
$tiempoInicio = isset($_POST['tiempo_inicio']) ? (float) $_POST['tiempo_inicio'] : 0.0;
if ($tiempoInicio <= 0 || (microtime(true) - $tiempoInicio) < 3.0) {
    responderJson(['error' => 'El formulario se envió demasiado rápido.'], 400);
}

// ---------- 3c) Rate limit por IP ----------
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$maxRegistrosPorHora = 3;

try {
    $pdo = pdo();

    // Ventana de 1 hora: borramos intentos viejos y contamos los recientes
    $stmt = $pdo->prepare('DELETE FROM intentos_registro WHERE ventana < (NOW() - INTERVAL 1 HOUR)');
    $stmt->execute();

    $stmt = $pdo->prepare('SELECT registros FROM intentos_registro WHERE ip = :ip AND ventana >= (NOW() - INTERVAL 1 HOUR) LIMIT 1');
    $stmt->execute([':ip' => $ip]);
    $fila = $stmt->fetch();

    if ($fila && (int) $fila['registros'] >= $maxRegistrosPorHora) {
        responderJson(['error' => 'Demasiados registros desde esta IP. Intentá más tarde.'], 429);
    }
} catch (PDOException $e) {
    error_log('registro: error rate limit: ' . $e->getMessage());
    responderJson(['error' => 'No se pudo conectar con la base de datos.'], 500);
}

// ---------- 4) Datos + validaciones ----------
$nombre   = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
$numero   = isset($_POST['numero']) ? trim($_POST['numero']) : '';
$correo   = isset($_POST['correo']) ? trim($_POST['correo']) : '';
$usuario  = isset($_POST['usuario']) ? trim($_POST['usuario']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
$confirm  = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

$errores = [];

// Nombre
$errores = array_merge($errores, validarNombreApellido($nombre));

// Número
if (strlen($numero) < 8 || strlen($numero) > 30 || !ctype_digit($numero)) {
    $errores[] = 'El número debe tener entre 8 y 30 caracteres numéricos.';
}

// Correo
if (mb_strlen($correo, 'UTF-8') < 5 || mb_strlen($correo, 'UTF-8') > 30) {
    $errores[] = 'El correo debe tener entre 5 y 30 caracteres.';
}
if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    $errores[] = 'El correo no es válido.';
}

// Usuario (alfanumérico) + palabras prohibidas
if (mb_strlen($usuario, 'UTF-8') < 5 || mb_strlen($usuario, 'UTF-8') > 20) {
    $errores[] = 'El usuario debe tener entre 5 y 20 caracteres.';
}
if (!preg_match('/^[a-zA-Z0-9_]+$/', $usuario)) {
    $errores[] = 'El usuario solo puede tener letras, números y guion bajo.';
}
if (contienePalabraProhibida($usuario)) {
    $errores[] = 'El usuario contiene una palabra no permitida. Elegí otro.';
}
if (in_array(mb_strtolower($usuario, 'UTF-8'), ['admin', 'root', 'soporte', 'darky', 'darkynails', 'sistema'], true)) {
    $errores[] = 'Ese nombre de usuario no está disponible.';
}

// Contraseña
if (mb_strlen($password, 'UTF-8') < 8 || mb_strlen($password, 'UTF-8') > 20) {
    $errores[] = 'La contraseña debe tener entre 8 y 20 caracteres.';
}
if (!preg_match('/[a-zA-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
    $errores[] = 'La contraseña debe incluir letras y números.';
}
if ($password !== $confirm) {
    $errores[] = 'Las contraseñas no coinciden.';
}

// ---------- 5) Unicidad en BD ----------
if (empty($errores)) {
    $stmt = $pdo->prepare('SELECT id_nombre FROM clientes WHERE correo = :correo OR usuario = :usuario LIMIT 1');
    $stmt->execute([':correo' => $correo, ':usuario' => $usuario]);
    if ($stmt->fetch()) {
        $errores[] = 'Ese correo o usuario ya está registrado.';
    }
}

if (!empty($errores)) {
    responderJson(['ok' => false, 'errores' => $errores], 422);
}

// ---------- 6) Insertar cliente (pendiente + token de verificación) ----------
$token = bin2hex(random_bytes(32)); // 64 caracteres

try {
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare(
        'INSERT INTO clientes
            (nombre, numero, correo, usuario, contrasena, estado, token_verificacion, token_expira, ip_registro, fecha_registro)
         VALUES
            (:nombre, :numero, :correo, :usuario, :contrasena, :estado, :token, :token_expira, :ip, :fecha)'
    );
    $stmt->execute([
        ':nombre'       => $nombre,
        ':numero'       => $numero,
        ':correo'       => $correo,
        ':usuario'      => $usuario,
        ':contrasena'   => $passwordHash,
        ':estado'       => 'pendiente',
        ':token'        => $token,
        ':token_expira' => date('Y-m-d H:i:s', time() + 86400), // 24 horas
        ':ip'           => $ip,
        ':fecha'        => date('Y-m-d H:i:s'),
    ]);

    $idCliente = (int) $pdo->lastInsertId();

    // Registrar el intento para el rate limit
    if ($fila) {
        $stmt = $pdo->prepare('UPDATE intentos_registro SET registros = registros + 1 WHERE ip = :ip AND ventana >= (NOW() - INTERVAL 1 HOUR)');
        $stmt->execute([':ip' => $ip]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO intentos_registro (ip, registros, ventana) VALUES (:ip, 1, NOW())');
        $stmt->execute([':ip' => $ip]);
    }
} catch (PDOException $e) {
    error_log('registro: error al insertar cliente: ' . $e->getMessage());
    responderJson(['error' => 'Ocurrió un error al crear la cuenta.'], 500);
}

// ---------- 7) Email de verificación (modo ejemplo: guarda el link en un log) ----------
$link = rtrim(BASE_URL, '/') . '/conexion/activar_cuenta.php?token=' . $token;
enviar_email_verificacion($correo, $link);

// Respuesta al cliente: siempre genérica, no confirmamos si el email llegó.
responderJson([
    'ok'        => true,
    'mensaje'   => 'Cuenta creada correctamente. Te enviamos un link de verificación a tu correo.',
]);

// ============================================================
// enviar_email_verificacion($destino, $link)
// Envío del link de verificación.
//
// En este repo de ejemplo NO se cablea ningún SMTP: el link se
// registra en un archivo de log para poder probar el flujo.
//
// En producción, reemplazá el cuerpo de esta función por un envío
// real con PHPMailer + SMTP (Gmail o Brevo). El resto del sistema
// no cambia: solo esta función.
// ============================================================
function enviar_email_verificacion(string $destino, string $link): void
{
    $log = __DIR__ . '/../debug_link.txt';
    $contenido = '[' . date('Y-m-d H:i:s') . "] Verificación para {$destino}\n  Link: {$link}\n";
    file_put_contents($log, $contenido, FILE_APPEND | LOCK_EX);

    // ── PRODUCCIÓN: reemplazá lo de arriba por algo como: ──
    // require __DIR__ . '/../lib/phpmailer/PHPMailer.php';
    // $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    // $mail->isSMTP();
    // $mail->Host       = 'smtp.gmail.com';
    // $mail->SMTPAuth   = true;
    // $mail->Username   = 'miempresa@gmail.com';
    // $mail->Password   = 'contraseña-de-aplicación';
    // $mail->SMTPSecure = 'tls';
    // $mail->Port       = 587;
    // $mail->setFrom('miempresa@gmail.com', 'DARKY NAILS');
    // $mail->addAddress($destino);
    // $mail->Subject = 'Verificá tu cuenta en DARKY NAILS';
    // $mail->Body    = "Para activar tu cuenta hacé clic acá: {$link}";
    // $mail->send();
}
<?php
// ============================================================
// obtener_servicios.php
// Endpoint AJAX (GET): devuelve en JSON las categorías de
// servicios con sus ítems (servicio, precio, duración) desde
// la tabla `servicios`, agrupados por la columna `categoria`.
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

// ---------- 2) Solo GET ----------
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.']);
    exit;
}

// ---------- 3) Categorías visibles en el dashboard (orden fijo) ----------
$categorias = [
    'semis'      => 'Semis',
    'capping'    => 'Capping',
    'esculpidas' => 'Esculpidas',
    'garras'     => 'Garras',
    'cejas'      => 'Cejas y Pestañas',
];

// ---------- 4) Conexión PDO segura ----------
require_once __DIR__ . '/../conexion/conexion.php';

try {
    $pdo = pdo();
} catch (PDOException $e) {
    error_log('Error de conexión: ' . $e->getMessage());
    echo json_encode(['error' => 'No se pudo conectar con la base de datos.']);
    exit;
}

try {
    $plazas = implode(',', array_fill(0, count($categorias), '?'));
    $sql = "SELECT id_servicio, servicio, precio, duracion, categoria
            FROM servicios
            WHERE categoria IN ($plazas)
            ORDER BY id_servicio ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_keys($categorias));

    // Índice por categoría para armar el JSON en el orden fijo
    $porCategoria = [];
    foreach ($stmt->fetchAll() as $fila) {
        $porCategoria[$fila['categoria']][] = [
            'id_servicio' => (int) $fila['id_servicio'],
            'servicio'    => $fila['servicio'],
            'precio'      => (float) $fila['precio'],
            'duracion'    => (int) $fila['duracion'],
        ];
    }

    $resultado = [];
    foreach ($categorias as $clave => $titulo) {
        $resultado[] = [
            'clave'  => $clave,
            'titulo' => $titulo,
            'items'  => isset($porCategoria[$clave]) ? $porCategoria[$clave] : [],
        ];
    }

    echo json_encode(['servicios' => $resultado]);

} catch (PDOException $e) {
    error_log('Error en la consulta: ' . $e->getMessage());
    echo json_encode(['error' => 'Ocurrió un error al obtener los servicios.']);
}

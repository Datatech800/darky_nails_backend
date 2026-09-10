<?php
// ============================================================
// turnos_dashboard.php
// Endpoint AJAX (GET): devuelve los turnos guardados como eventos
// de FullCalendar para el dashboard del owner.
// - JOIN con clientes para el nombre (turnos reservados online).
// - nombre_cliente para turnos creados a mano por el owner.
// - start/end calculados con la duración de cada turno.
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

require_once __DIR__ . '/conexion.php';

try {
    $pdo = pdo();
} catch (PDOException $e) {
    error_log('turnos_dashboard: conexión: ' . $e->getMessage());
    echo json_encode(['error' => 'No se pudo conectar con la base de datos.']);
    exit;
}

try {
    $sql = "SELECT t.id_turno,
                   t.cliente_id,
                   t.nombre_cliente,
                   COALESCE(NULLIF(t.nombre_cliente, ''), c.nombre) AS nombre,
                   t.servicio,
                   t.variante,
                   t.fecha,
                   t.hora,
                   t.duracion,
                   t.estado
            FROM turnos t
            LEFT JOIN clientes c ON c.id_nombre = t.cliente_id
            ORDER BY t.fecha ASC, t.hora ASC";

    $stmt = $pdo->query($sql);
    $eventos = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
        $inicio = new DateTime($fila['fecha'] . ' ' . $fila['hora']);
        $fin = (clone $inicio)->modify('+' . (int) $fila['duracion'] . ' minutes');

        $eventos[] = [
            'id'      => 't-' . $fila['id_turno'],
            'title'   => $fila['nombre'] ?: 'Sin nombre',
            'start'   => $inicio->format('Y-m-d\TH:i:s'),
            'end'     => $fin->format('Y-m-d\TH:i:s'),
            'duracion' => (int) $fila['duracion'],
            'estado'  => $fila['estado'],
            'extendedProps' => [
                'servicio' => $fila['servicio'],
                'variante' => $fila['variante'],
            ],
        ];
    }

    echo json_encode($eventos);

} catch (PDOException $e) {
    error_log('turnos_dashboard: consulta: ' . $e->getMessage());
    echo json_encode(['error' => 'Ocurrió un error al cargar los turnos.']);
}
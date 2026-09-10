<?php
// ============================================================
// conexion.php
// Conexión centralizada a la base "darky" con PDO.
// Incluí este archivo con require __DIR__ . '/../conexion/conexion.php';
// y usá la función pdo() en cada script.
// ============================================================

require_once dirname(__DIR__) . '/config.php';

/**
 * Devuelve una conexión PDO reutilizable (una sola por request).
 *
 * @throws PDOException si no se puede conectar.
 */
function pdo(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }

    return $pdo;
}
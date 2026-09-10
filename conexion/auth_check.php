<?php
// ============================================================
// auth_check.php
// Protección para páginas del dashboard.
// Incluí este archivo al principio de cada página protegida:
//
//   require __DIR__ . '/conexion/auth_check.php';
//
// Si el owner no tiene sesión activa, redirige al login.
// La URL del login se calcula desde BASE_URL (config.php).
// ============================================================

require_once dirname(__DIR__) . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['owner_id'])) {
    header('Location: ' . rtrim(BASE_URL, '/') . '/conexion/beta_login.php');
    exit;
}
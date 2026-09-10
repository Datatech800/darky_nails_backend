<?php
// ============================================================
// beta_login.php
// Formulario de login del owner.
// - Si ya hay sesión activa, redirige directo al dashboard.
// - Muestra mensaje de error cuando llega ?error=1 (o ?error=2 bloqueado).
// ============================================================

session_start();

// Si ya está logueado, no volver a mostrar el login
if (isset($_SESSION['owner_id'])) {
    header('Location: ../beta_dashboard.php');
    exit;
}

// Mensajes de error según el parámetro de la URL
$mensajeError = '';
if (isset($_GET['error'])) {
    if ($_GET['error'] === '2') {
        $mensajeError = 'Demasiados intentos. Esperá unos minutos e intentá de nuevo.';
    } else {
        $mensajeError = 'Usuario o contraseña incorrectos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login dashboard - DARKY NAILS</title>
<link rel="stylesheet" href="../css_darky/cssdashboard.css">
<style>
.error-msg {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
    border-radius: 6px;
    padding: 10px 14px;
    font-size: 14px;
    font-weight: 600;
    text-align: center;
}
</style>
</head>
<body>

    <div class="login-brand">
        <h1>Darky <span class="brand-nails">Nails</span></h1>
    </div>

    <?php if ($mensajeError !== ''): ?>
        <p class="error-msg"><?php echo htmlspecialchars($mensajeError, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>

    <form method="post" action="verificar_login.php">
        <h2>Iniciar Sesión</h2>

        <label for="usuario">Usuario</label>
        <input type="text" id="usuario" name="usuario" placeholder="Tu usuario" required minlength="5" maxlength="15">

        <label for="password">Contraseña</label>
        <input type="password" id="password" name="password" placeholder="Tu contraseña" required minlength="5" maxlength="20">

        <button type="submit">Ingresar</button>

        <p><a href="#">¿Olvidaste tu contraseña?</a></p>
    </form>

</body>
</html>

<?php
// ============================================================
// beta_darky.php
// Página pública del cliente: muestra los servicios y precios.
// Los precios se leen de la base de datos, por lo que cualquier
// cambio hecho desde el dashboard se ve reflejado aquí.
// ============================================================

require_once __DIR__ . '/conexion/conexion.php';
session_start();

$clienteLogueado = isset($_SESSION['cliente_id']);

// Precios por defecto (si la BD no está disponible se usan estos)
$precios = [
    'semi_liso'             => ['p' => 22000, 'm' => 60],
    'semi_nailart'          => ['p' => 24000, 'm' => 80],
    'semi_fullnailart'      => ['p' => 28000, 'm' => 90],
    'capping_gel'           => ['p' => 28000, 'm' => 90],
    'capping_nailart'       => ['p' => 30000, 'm' => 105],
    'capping_fullnailart'   => ['p' => 34000, 'm' => 120],
    'esculpida_lisa'        => ['p' => 35000, 'm' => 90],
    'esculpida_nailart'     => ['p' => 37000, 'm' => 120],
    'esculpida_fullnailart' => ['p' => 40000, 'm' => 120],
    'garra_corta'           => ['p' => 45000, 'm' => 120],
    'garra_xl'              => ['p' => 60000, 'm' => 180],
    'ceja'                  => ['p' => 30000, 'm' => 60],
    'pestania'              => ['p' => 25000, 'm' => 30],
    'ceja_pestania'         => ['p' => 50000, 'm' => 90],
];

// Sobrescribir con los valores reales de la BD
try {
    $stmt = pdo()->query('SELECT servicio, precio, duracion FROM servicios');
    foreach ($stmt as $fila) {
        if (isset($precios[$fila['servicio']])) {
            $precios[$fila['servicio']] = [
                'p' => (float) $fila['precio'],
                'm' => (int) $fila['duracion'],
            ];
        }
    }
} catch (PDOException $e) {
    // Se mantienen los valores por defecto
}

function precioConFormato($precio) {
    $precio = round((float) $precio, 2);
    $dec = ($precio == floor($precio)) ? 0 : 2;
    return '$' . number_format($precio, $dec, ',', '.');
}

function duracionTexto($min) {
    $min = (int) $min;
    if ($min < 60) return $min . ' min';
    $h = intdiv($min, 60);
    $m = $min % 60;
    if ($m === 0) return ($h === 1 ? '1 hs' : $h . ':00 hs');
    return $h . ':' . str_pad($m, 2, '0', STR_PAD_LEFT) . ' hs';
}

function precioSpan($clave) {
    global $precios;
    $svc = $precios[$clave];
    echo '<span class="servicio-precio">' . precioConFormato($svc['p']) . '</span>' .
         '<span class="servicio-duracion">(' . duracionTexto($svc['m']) . ')</span>';
}

function reservarPrecio($claves) {
    global $precios;
    $min = null;
    foreach ($claves as $c) {
        if (isset($precios[$c])) {
            $v = $precios[$c]['p'];
            if ($min === null || $v < $min) $min = $v;
        }
    }
    echo precioConFormato($min);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>DARKY NAILS 🖤🦇</title>
<meta name="description" content="Manicuría y nail art: semis, capping, esculpidas, garras, cejas y pestañas. Reservá tu turno online.">
<meta property="og:type" content="website">
<meta property="og:title" content="DARKY NAILS 🖤🦇 | Semis, Esculpidas y Nail Art">
<meta property="og:description" content="Lujo y estilo para tus uñas. Semis, capping, esculpidas, garras, cejas y pestañas. Reservá tu cita online.">
<meta property="og:url" content="https://datatech800.github.io/darky_nails/">
<meta property="og:image" content="https://datatech800.github.io/darky_nails/image_darky/og-image.jpg">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:site_name" content="DARKY NAILS">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="DARKY NAILS 🖤🦇 | Semis, Esculpidas y Nail Art">
<meta name="twitter:description" content="Lujo y estilo para tus uñas. Reservá tu cita online.">
<meta name="twitter:image" content="https://datatech800.github.io/darky_nails/image_darky/og-image.jpg">
<link rel="stylesheet" href="css_darky/css_darky.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
@media (min-width:769px) { .map-mobile { display:none !important; } }
@media (max-width:768px) { .map-desktop { display:none !important; } }
</style>
</head>
<body>

<!-- ─── HEADER ─── -->
<nav class="nav-bar">
<div class="nav-bar-top">
<div class="nav-logo">
<span>Darky  </span>
<span class="brand-nails">Nails</span>
</div>
<ul class="nav-links">
<li><a href="#">Inicio</a></li>
<li><a href="#servicios">Servicios</a></li>
<li><a href="galeria.html">Galería</a></li>
<li><a href="#">Blog</a></li>
<li><a href="#contacto">Contacto</a></li>
</ul>
<div class="nav-cta">
<a href="#" class="nav-btn nav-btn-cuenta" id="abre-registro">Crear Cuenta</a>
<a href="#" class="nav-btn abre-reserva">Reservar Ahora</a>
</div>
<button class="nav-hamburger" aria-label="Abrir menú">
<span></span><span></span><span></span>
</button>
</div>
<div class="nav-btn-flotantes">
<a href="#" class="nav-btn-abajo abre-reserva">Reservar Ahora</a>
<a href="#" class="nav-btn-abajo" id="abre-registro-mobile">Crear Cuenta</a>
</div>
</nav>

<div class="menu-overlay">
<div class="menu-overlay-header">
<div class="nav-logo">
<span>Darky  </span>
<span class="brand-nails">Nails</span>
</div>
<button class="menu-overlay-close" aria-label="Cerrar menú">&times;</button>
</div>
<h2 class="menu-overlay-title">MENÚ</h2>
<ul class="menu-overlay-links">
<li><a href="#">Inicio</a></li>
<li><a href="#servicios">Servicios</a></li>
<li><a href="galeria.html">Galería</a></li>
<li><a href="#">Blog</a></li>
<li><a href="#contacto">Contacto</a></li>
</ul>
<a href="#" class="nav-btn menu-overlay-btn" id="abre-registro-overlay">Crear Cuenta</a>
<a href="#" class="nav-btn menu-overlay-btn abre-reserva">Reservar Ahora</a>
</div>

<!-- ─── HERO ─── -->
<section class="hero">
<div class="hero-slides-track">
<div class="hero-slide hero-slide-1 hero-slide-activo">
<div class="hero-slide-fondo"></div>
<div class="hero-slide-overlay"></div>
<div class="hero-slide-content">
<h1>DALE <span class="highlight">PODER</span> <br> A TUS<span class="highlight"> NAILS </span></h1>
<p class="sub">Lujo y estilo para tus manos.</p>
<a href="#" class="hero-btn">Agendar Cita</a>
</div>
</div>
<div class="hero-slide hero-slide-2">
<div class="hero-slide-fondo"></div>
<div class="hero-slide-overlay"></div>
<div class="hero-slide-content">
<h1>DISEÑADAS PARA <span class="highlight">VOS</span></h1>
<p class="sub">Lujo y estilo para tus manos.</p>
<a href="#" class="hero-btn">Agendar Cita</a>
</div>
</div>
<div class="hero-slide hero-slide-3">
<div class="hero-slide-fondo"></div>
<div class="hero-slide-overlay"></div>
<div class="hero-slide-content">
<h1>EL DETALLE TE <span class="highlight">DEFINE</span></h1>
<p class="sub">Lujo y estilo para tus manos.</p>
<a href="#" class="hero-btn">Agendar Cita</a>
</div>
</div>
</div>
<button class="hero-flecha hero-flecha-prev" type="button" aria-label="Anterior">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
</button>
<button class="hero-flecha hero-flecha-next" type="button" aria-label="Siguiente">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
</button>
<div class="hero-dots">
<button class="hero-dot activo" type="button" aria-label="Ir a la imagen 1"></button>
<button class="hero-dot" type="button" aria-label="Ir a la imagen 2"></button>
<button class="hero-dot" type="button" aria-label="Ir a la imagen 3"></button>
</div>
</section>

<!-- ─── SERVICES ─── -->
<section class="services-section" id="servicios">
<h2>Nuestros Servicios</h2>
<div class="services-grid">
<div class="service-item servicio-abre" data-modal="semis">
<img src="image_darky/semi.webp" alt="Semis">
<h3>Semis</h3>
<p>Esmaltado semipermanente con acabado profesional y duradero.</p>
</div>
<div class="service-item servicio-abre" data-modal="capping">
<img src="image_darky/capping.webp" alt="Capping">
<h3>Capping</h3>
<p>Capa de gel que fortalece y da brillo a tus uñas naturales.</p>
</div>
<div class="service-item servicio-abre" data-modal="esculpidas">
<img src="image_darky/esculpida.webp" alt="Esculpidas">
<h3>Esculpidas</h3>
<p>Extensiones de uñas con forma y largo personalizado.</p>
</div>
<div class="service-item servicio-abre" data-modal="garras">
<img src="image_darky/garra.webp" alt="Garras">
<h3>Garras</h3>
<p>Nail art único y creativo para cada estilo.</p>
</div>
<div class="service-item servicio-abre" data-modal="cejas">
<img src="image_darky/ceja.webp" alt="Cejas y Pestañas">
<h3>Cejas y Pestañas</h3>
<p>Combo completo de cejas y pestañas para una mirada única.</p>
</div>
<div class="service-item">
<img src="image_darky/accesorio.webp" alt="Accesorios">
<h3>Accesorios</h3>
<p>Detalles y accesorios para completar tu look.</p>
</div>
</div>
<div class="brands-marquee">
<div class="brands-track">
<img src="image_darky/brands/brand_1.png" alt="Marca">
<img src="image_darky/brands/brand_2.png" alt="Canni">
<img src="image_darky/brands/brand_3.png" alt="Cherimoya">
<img src="image_darky/brands/brand_4.png" alt="OPI">
<img src="image_darky/brands/brand_5.png" alt="Pink Mask">
<img src="image_darky/brands/brand_6.png" alt="Marca">
<img src="image_darky/brands/brand_7.png" alt="Marca">
<img src="image_darky/brands/brand_8.png" alt="Thuya">
<img src="image_darky/brands/brand_1.png" alt="Marca">
<img src="image_darky/brands/brand_2.png" alt="Canni">
<img src="image_darky/brands/brand_3.png" alt="Cherimoya">
<img src="image_darky/brands/brand_4.png" alt="OPI">
<img src="image_darky/brands/brand_5.png" alt="Pink Mask">
<img src="image_darky/brands/brand_6.png" alt="Marca">
<img src="image_darky/brands/brand_7.png" alt="Marca">
<img src="image_darky/brands/brand_8.png" alt="Thuya">
</div>
</div>
</section>

<!-- ─── CONTACTO + FORM + MAPA (side‑by‑side on desktop) ─── -->
<section class="cta-section" id="contacto">
<div class="cta-left">
<div class="contacto-row">
<div class="visitarnos-card">
<p class="contacto-titulo">Vení a visitarnos</p>
<p class="contacto-sub">Nuestra Dirección</p>
<p class="contacto-text">📍 Avenida Colón 0000 piso 20 depto A</p>
<p class="contacto-sub">Nuestro Horario</p>
<div class="contacto-grid">
<span>Lunes a Viernes</span><span>10:00 – 18:00 hs</span>
<span>Sábado</span><span>10:00 – 15:00 hs</span>
<span>Domingo</span><span class="contacto-closed">Cerrado</span>
</div>
</div>
<div class="map-desktop map-box">
<iframe src="https://www.google.com/maps?q=-37.995555,-57.552260&z=15&output=embed" width="100%" allowfullscreen loading="lazy"></iframe>
</div>
</div>
</div>
<div class="cta-right">
<div class="map-mobile map-box">
<iframe src="https://www.google.com/maps?q=-37.995555,-57.552260&z=15&output=embed" width="100%" height="250" allowfullscreen loading="lazy"></iframe>
</div>
<form class="form-dark">
<h3>SUSCRÍBETE Y OBTENÉ NOVEDADES Y BENEFICIOS</h3>
<p class="benefits">Promociones y novedades para suscriptores.</p>

<div class="input-group">
<input type="text" required>
<label>Nombre</label>
</div>

<div class="input-group">
<input type="tel" required>
<label>Teléfono</label>
</div>

<div class="input-group">
<input type="email" required>
<label>Email</label>
</div>

<button type="submit">Suscribirme</button>
</form>
<div class="card-body redes-card">
<h5 class="contacto-titulo">Nuestras redes</h5>
<div class="redes-icons">
<a href="#" class="red-ico" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
<a href="#" class="red-ico" aria-label="WhatsApp"><i class="bi bi-whatsapp"></i></a>
<a href="#" class="red-ico" aria-label="Pinterest"><i class="bi bi-pinterest"></i></a>
<a href="#" class="red-ico" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
</div>
</div>
</div>
</section>

<!-- ─── MODAL SEMIS (glassmorphism) ─── -->
<div class="modal-glass" id="modal-semis">
<div class="modal-glass-box">
<button class="modal-glass-close" type="button" aria-label="Cerrar">&times;</button>
<h3 class="modal-glass-titulo">SEMIPERMANENTE</h3>
<ul class="modal-lista">
<li><span class="servicio-nombre">Semi liso <small>(manos o pies)</small></span><?php precioSpan('semi_liso'); ?></li>
<li><span class="servicio-nombre">Semi + NailArt <small>(en 2 uñas)</small></span><?php precioSpan('semi_nailart'); ?></li>
<li><span class="servicio-nombre">Semi + Full NailArt <small>(todas las uñas)</small></span><?php precioSpan('semi_fullnailart'); ?></li>
</ul>
<div class="modal-incluye">
<p class="modal-incluye-titulo">ESTE SERVICIO INCLUYE</p>
<ul>
<li>Retirado del material</li>
<li>Manicura combinada</li>
<li>Doble capa de base como refuerzo</li>
<li>Esmaltado profundo</li>
<li>Más de 100 colores</li>
<li>Crema de manos hidratante</li>
</ul>
</div>
</div>
</div>

<!-- ─── MODAL CAPPING (glassmorphism) ─── -->
<div class="modal-glass" id="modal-capping">
<div class="modal-glass-box">
<button class="modal-glass-close" type="button" aria-label="Cerrar">&times;</button>
<h3 class="modal-glass-titulo">CAPPING</h3>
<ul class="modal-lista">
<li><span class="servicio-nombre">Capping gel liso</span><?php precioSpan('capping_gel'); ?></li>
<li><span class="servicio-nombre">Capping + NailArt <small>(2 uñas)</small></span><?php precioSpan('capping_nailart'); ?></li>
<li><span class="servicio-nombre">Capping + Full NailArt <small>(todas las uñas)</small></span><?php precioSpan('capping_fullnailart'); ?></li>
</ul>
<div class="modal-incluye">
<p class="modal-incluye-titulo">ESTE SERVICIO INCLUYE</p>
<ul>
<li>Retirado del material</li>
<li>Manicura combinada</li>
<li>Doble capa de base como refuerzo</li>
<li>Esmaltado profundo</li>
<li>Más de 200 colores</li>
<li>Crema de manos hidratante</li>
<li>Un aduración de hasta 3 semanas</li>
<li>Se incluye un arreglo en caso de rotura de uña</li>
</ul>
</div>
</div>
</div>

<!-- ─── MODAL ESCULPIDAS (glassmorphism) ─── -->
<div class="modal-glass" id="modal-esculpidas">
<div class="modal-glass-box">
<button class="modal-glass-close" type="button" aria-label="Cerrar">&times;</button>
<h3 class="modal-glass-titulo">ESCULPIDAS</h3>
<ul class="modal-lista">
<li><span class="servicio-nombre">Esculpidas lisas</span><?php precioSpan('esculpida_lisa'); ?></li>
<li><span class="servicio-nombre">Esculpidas + NailArt <small>(2 uñas)</small></span><?php precioSpan('esculpida_nailart'); ?></li>
<li><span class="servicio-nombre">Esculpidas + Full NailArt</span><?php precioSpan('esculpida_fullnailart'); ?></li>
</ul>
<div class="modal-incluye">
<p class="modal-incluye-titulo">ESTE SERVICIO INCLUYE</p>
<ul>
<li>Retirado del material</li>
<li>Manicura combinada</li>
<li>Doble capa de base como refuerzo</li>
<li>Esmaltado profundo</li>
<li>Más de 200 colores</li>
<li>Crema de manos hidratante</li>
</ul>
<p class="modal-nota">Indicadas para quienes desean uñas largas y no quieren esperar el crecimiento propio.</p>
</div>
</div>
</div>

<!-- ─── MODAL GARRAS (glassmorphism) ─── -->
<div class="modal-glass" id="modal-garras">
<div class="modal-glass-box">
<button class="modal-glass-close" type="button" aria-label="Cerrar">&times;</button>
<h3 class="modal-glass-titulo">GARRAS ESCULPIDAS</h3>
<ul class="modal-lista">
<li><span class="servicio-nombre">Garras cortas</span><?php precioSpan('garra_corta'); ?></li>
<li><span class="servicio-nombre">Garras largas, XL</span><?php precioSpan('garra_xl'); ?></li>
</ul>
</div>
</div>

<!-- ─── MODAL CEJAS Y PESTAÑAS (glassmorphism) ─── -->
<div class="modal-glass" id="modal-cejas">
<div class="modal-glass-box">
<button class="modal-glass-close" type="button" aria-label="Cerrar">&times;</button>
<h3 class="modal-glass-titulo">CEJAS Y PESTAÑAS</h3>
<ul class="modal-lista">
<li><span class="servicio-nombre">Cejas</span><?php precioSpan('ceja'); ?></li>
<li><span class="servicio-nombre">Pestañas</span><?php precioSpan('pestania'); ?></li>
<li><span class="servicio-nombre">Combo Cejas + Pestañas</span><?php precioSpan('ceja_pestania'); ?></li>
</ul>
<div class="modal-incluye">
<p class="modal-incluye-titulo">ESTE SERVICIO INCLUYE</p>
<ul>
<li>Los mejores productos para el cuidado de las cejas y las pestañas</li>
<li>Alta durabilidad y consistencia de los productos</li>
</ul>
</div>
</div>
</div>

<!-- ─── MODAL RESERVAR AHORA (glassmorphism) ─── -->
<div class="modal-glass" id="modal-reservar">
<div class="modal-glass-box modal-glass-box-reservar">
<button class="modal-glass-close" type="button" aria-label="Cerrar">&times;</button>
<h3 class="modal-glass-titulo">RESERVAR AHORA</h3>
<p class="modal-reservar-sub">Elegí un servicio para ver sus precios y detalle</p>
<div class="modal-reservar-grid">
<button class="reservar-item" type="button" data-abre="semis"><span class="reservar-nombre">Semis</span><span class="reservar-precio"><?php reservarPrecio(['semi_liso','semi_nailart','semi_fullnailart']); ?></span><span class="reservar-flecha">›</span></button>
<button class="reservar-item" type="button" data-abre="capping"><span class="reservar-nombre">Capping</span><span class="reservar-precio"><?php reservarPrecio(['capping_gel','capping_nailart','capping_fullnailart']); ?></span><span class="reservar-flecha">›</span></button>
<button class="reservar-item" type="button" data-abre="esculpidas"><span class="reservar-nombre">Esculpidas</span><span class="reservar-precio"><?php reservarPrecio(['esculpida_lisa','esculpida_nailart','esculpida_fullnailart']); ?></span><span class="reservar-flecha">›</span></button>
<button class="reservar-item" type="button" data-abre="garras"><span class="reservar-nombre">Garras</span><span class="reservar-precio"><?php reservarPrecio(['garra_corta','garra_xl']); ?></span><span class="reservar-flecha">›</span></button>
<button class="reservar-item" type="button" data-abre="cejas"><span class="reservar-nombre">Cejas y Pestañas</span><span class="reservar-precio"><?php reservarPrecio(['ceja','pestania','ceja_pestania']); ?></span><span class="reservar-flecha">›</span></button>
</div>
</div>
</div>

<!-- ─── MODAL CREAR CUENTA (glassmorphism) ─── -->
<div class="modal-glass" id="modal-registro">
<div class="modal-glass-box modal-glass-box-registro">
<button class="modal-glass-close" type="button" aria-label="Cerrar">&times;</button>
<h3 class="modal-glass-titulo">CREAR CUENTA</h3>
<p class="modal-registro-sub">Registrate para poder reservar tus turnos.</p>

<p id="mensaje-registro" class="registro-mensaje"></p>

<form id="form-registro" autocomplete="off" novalidate>
<div class="input-group">
<input type="text" id="reg-nombre" name="nombre" minlength="2" maxlength="50" required autocomplete="off">
<label>Nombre y apellido</label>
</div>
<div class="input-group">
<input type="tel" id="reg-numero" name="numero" minlength="8" maxlength="30" required autocomplete="off">
<label>Teléfono</label>
</div>
<div class="input-group">
<input type="email" id="reg-correo" name="correo" minlength="5" maxlength="30" required autocomplete="off">
<label>Email</label>
</div>
<div class="input-group">
<input type="text" id="reg-usuario" name="usuario" minlength="5" maxlength="20" required autocomplete="off">
<label>Usuario</label>
</div>
<div class="input-group">
<input type="password" id="reg-password" name="password" minlength="8" maxlength="20" required autocomplete="new-password">
<label>Contraseña</label>
</div>
<div class="input-group">
<input type="password" id="reg-confirm" name="confirm_password" minlength="8" maxlength="20" required autocomplete="new-password">
<label>Repetir contraseña</label>
</div>

<!-- Honeypot: los bots llenan este campo oculto, los humanos no lo ven -->
<div class="honeypot" aria-hidden="true">
<input type="text" id="reg-website" name="website" tabindex="-1" autocomplete="off">
</div>

<input type="hidden" id="reg-tiempo" name="tiempo_inicio" value="0">
<input type="hidden" name="csrf_token" id="reg-csrf">

<button type="submit">Crear mi cuenta</button>
</form>
</div>
</div>

<!-- ─── MODAL LOGIN CLIENTE (glassmorphism) ─── -->
<div class="modal-glass" id="modal-login">
<div class="modal-glass-box modal-glass-box-registro">
<button class="modal-glass-close" type="button" aria-label="Cerrar">&times;</button>
<h3 class="modal-glass-titulo">INICIAR SESIÓN</h3>
<p class="modal-registro-sub">Ingresá tus datos para reservar un turno.</p>

<p id="mensaje-login" class="registro-mensaje"></p>

<form id="form-login" autocomplete="off" novalidate>
<div class="input-group">
<input type="text" id="login-usuario" name="usuario" minlength="5" maxlength="20" required autocomplete="username">
<label>Usuario</label>
</div>
<div class="input-group">
<input type="password" id="login-password" name="password" minlength="8" maxlength="20" required autocomplete="current-password">
<label>Contraseña</label>
</div>
<input type="hidden" name="csrf_token" id="login-csrf">
<button type="submit">Ingresar y reservar</button>
</form>

<p class="modal-login-info">¿No tenés cuenta? <a href="#" id="login-va-registro" class="modal-login-link">Creala acá</a></p>
</div>
</div>

<!-- ─── MODAL CALENDARIO TURNOS (glassmorphism) ─── -->
<div class="modal-glass modal-glass-turnos" id="modal-turnos">
<div class="modal-glass-box modal-glass-box-turnos">
<button class="modal-glass-close" type="button" aria-label="Cerrar">&times;</button>
<h3 class="modal-glass-titulo">ELEGÍ TU TURNO</h3>
<p id="turnos-servicio-elegido" class="modal-registro-sub"></p>

<div class="calendario-turnos">
<div class="barra-semana">
<button id="tp-prev" class="btn-nav btn-nav-dark" type="button" aria-label="Semana anterior">&#8249;</button>
<div class="titulos-semana">
<div id="tp-titulo-semana" class="tp-titulo">…</div>
<div id="tp-subtitulo-semana" class="tp-subtitulo">…</div>
</div>
<button id="tp-next" class="btn-nav btn-nav-dark" type="button" aria-label="Semana siguiente">&#8250;</button>
</div>
<div class="tp-hoy-wrap">
<button id="tp-hoy" class="btn-nav btn-nav-dark" type="button">Hoy</button>
</div>
<table id="tp-tabla" class="tp-tabla">
<thead>
<tr>
<th>Día</th>
<th>Fecha</th>
<th>Horarios disponibles</th>
</tr>
</thead>
<tbody id="tp-cuerpo"></tbody>
</table>
</div>

<p id="mensaje-turnos" class="registro-mensaje"></p>
</div>
</div>

<!-- ─── MODAL CONFIRMAR TURNO (glassmorphism) ─── -->
<div class="modal-glass" id="modal-confirmar-turno">
<div class="modal-glass-box modal-glass-box-registro">
<button class="modal-glass-close" type="button" aria-label="Cerrar">&times;</button>
<h3 class="modal-glass-titulo">CONFIRMAR TURNO</h3>

<div class="turno-resumen">
<p class="turno-resumen-fila"><span>Servicio</span><strong id="cf-servicio">—</strong></p>
<p class="turno-resumen-fila"><span>Día</span><strong id="cf-fecha">—</strong></p>
<p class="turno-resumen-fila"><span>Hora</span><strong id="cf-hora">—</strong></p>
</div>

<div class="input-group">
<select id="cf-variante" class="modal-glass-select" required>
<option value="">Elegí la variante…</option>
</select>
</div>

<p id="mensaje-confirmar" class="registro-mensaje"></p>

<input type="hidden" id="cf-csrf">
<input type="hidden" id="cf-servicio-input">
<input type="hidden" id="cf-fecha-input">
<input type="hidden" id="cf-hora-input">

<button type="button" id="cf-confirmar" class="turno-confirmar-btn">Confirmar turno</button>
</div>
</div>

<a href="#" class="whatsapp-float" target="_blank"></a>

<footer>
&copy; 2026 DARKY NAILS — Todos los derechos reservados
</footer>

<script>
window.CLIENTE_LOGEADO = <?php echo $clienteLogueado ? 'true' : 'false'; ?>;
</script>
<script src="jsvalk/modales.js"></script>
<script src="jsvalk/hero.js"></script>
<script src="jsvalk/nav-observer.js"></script>
<script src="jsvalk/nav.js"></script>
<script src="jsvalk/registro.js"></script>
<script src="jsvalk/login_cliente.js"></script>
<script src="jsvalk/turnos.js"></script>
</body>
</html>
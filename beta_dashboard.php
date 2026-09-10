<?php
   require 'conexion/auth_check.php';
   require 'conexion/conexion.php';
   ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Panel - DARKY NAILS</title>
<link rel="stylesheet" href="css_darky/css_beta_dashboard.css">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@7.0.1/all/global.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@7.0.1/themes/monarch/global.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@7.0.1/locales-all/global.js"></script>
<link href='https://cdn.jsdelivr.net/npm/fullcalendar@7.0.1/skeleton.css' rel='stylesheet' />
<link href='https://cdn.jsdelivr.net/npm/fullcalendar@7.0.1/themes/monarch/theme.css' rel='stylesheet' />
<link href='https://cdn.jsdelivr.net/npm/fullcalendar@7.0.1/themes/monarch/palettes/purple.css' rel='stylesheet' />
</head>
<body>

<!-- ─── TOP BAR ─── -->
<header class="top-bar">
<div class="logo">Darky <span class="brand-nails">Nails</span></div>
<button class="btn-logout">Cerrar Sesión</button>
</header>

<nav class="nav-panel">
<button class="nav-panel-item active" data-section="turnos">Turnos</button>
<button class="nav-panel-item" data-section="clientes">Clientes</button>
<button class="nav-panel-item" data-section="multimedia">Multimedia</button>
<button class="nav-panel-item" data-section="servicios">Servicios y Precios</button>
</nav>

<!-- ─── MAIN CONTENT ─── -->
<main class="main-content">

<!-- ─── TURNOS ─── -->
<div class="seccion" id="seccion-turnos">
<div class="calendar-wrapper" id="calendar"></div>
</div>

<!-- ─── CLIENTES ─── -->
<div class="seccion" id="seccion-clientes" style="display:none">
<div class="clientes-panel">
<div class="pendientes-panel">
<h3 class="pendientes-titulo">Clientes pendientes de aprobación</h3>
<p id="mensaje-pendientes" class="clientes-mensaje"></p>
<div class="pendientes-lista" id="lista-pendientes"></div>
</div>
<div class="search-group">
<span class="search-label">Buscador</span>
<input class="search-input" id="buscador" type="search" placeholder="Nombre, teléfono o email..." autocomplete="off">
</div>
<p id="mensaje-busqueda" class="clientes-mensaje"></p>
<div class="table-wrapper">
<table class="tabla-clientes">
<thead>
<tr>
<th>Nombre</th>
<th>Número</th>
<th>Correo</th>
</tr>
</thead>
<tbody id="tabla-resultados"></tbody>
</table>
</div>
<!--FORMULARIO DE REGISTRO DE CLIENTES-->
<form class="clientes-form" id="form-cliente" autocomplete="off">
<h3>Registrar Nuevo Cliente</h3>
<div class="input-group">
<label for="nombre">Nombre</label>
<input type="text" id="nombre" name="nombre" minlength="2" maxlength="30" required>
</div>
<div class="input-group">
<label for="numero">Número</label>
<input type="number" id="numero" name="numero" minlength="8" maxlength="20" required>
</div>
<div class="input-group">
<label for="correo">Correo</label>
<input type="email" id="correo" name="correo" minlength="5" maxlength="30" required>
</div>
<div class="input-group">
<label for="usuario">Usuario</label>
<input type="text" id="usuario" name="usuario" minlength="5" maxlength="15" required>
</div>
<div class="input-group">
<label for="password">Contraseña</label>
<input type="password" id="password" name="password" minlength="5" maxlength="20" required>
</div>
<div class="input-group">
<label for="confirm_password">Repetir contraseña</label>
<input type="password" id="confirm_password" name="confirm_password" minlength="5" maxlength="20" required>
</div>
<button type="submit">Guardar datos</button>
<p id="mensaje-form" class="form-mensaje"></p>
</form>
</div>
</div>

<!-- ─── MULTIMEDIA ─── -->
<div class="seccion" id="seccion-multimedia" style="display:none">
<div class="cards-grid">
<div class="card">
<p class="card-subtitle">tendencias</p>
</div>
<div class="card">
<p class="card-subtitle">lo último</p>
</div>
<div class="card">
<p class="card-subtitle">ilustrativas de servicios</p>
</div>
</div>
</div>

<!-- ─── SERVICIOS Y PRECIOS ─── -->
<div class="seccion" id="seccion-servicios" style="display:none">
<div class="servicios-panel">
<div class="action-bar">
<button class="btn-action" id="btn-agregar-servicio" type="button">Agregar un servicio</button>
</div>
<p id="mensaje-servicios" class="servicios-mensaje"></p>
<div class="cuadro-servicios" id="cuadro-servicios"></div>
</div>
</div>

<!-- ─── MODAL NUEVO TURNO ─── -->
<div class="modal-overlay" id="modal-reserva" hidden>
<div class="modal">
<h2 class="modal-titulo">Nuevo Turno</h2>
<div class="modal-campo">
<div class="modal-fecha-turno" id="modal-fecha">—</div>
</div>
<div class="modal-campo">
<label for="sel-servicio">Servicio</label>
<select id="sel-servicio" class="modal-input">
<option value="">Seleccioná un servicio…</option>
</select>
</div>
<div class="modal-campo">
<label for="sel-variante">Variante</label>
<select id="sel-variante" class="modal-input" disabled>
<option value="">Primero elegí el servicio</option>
</select>
</div>
<div class="modal-campo">
<label for="input-nombre">Nombre del cliente</label>
<input type="text" id="input-nombre" class="modal-input" placeholder="Nombre del cliente" autocomplete="off">
</div>
<div class="modal-acciones">
<button type="button" class="btn-modal-cerrar" id="btn-cancelar">Cancelar</button>
<button type="button" class="btn-action" id="btn-guardar">Guardar Turno</button>
</div>
<p id="mensaje-modal-turno" class="servicios-mensaje"></p>
</div>
</div>

<!-- ─── MODAL AGREGAR SERVICIO ─── -->
<div class="modal-overlay" id="modal-agregar-servicio" hidden>
<div class="modal">
<h2 class="modal-titulo">Agregar un servicio</h2>
<p id="mensaje-modal-servicio" class="servicios-mensaje"></p>
<form id="form-agregar-servicio" novalidate>
<div class="modal-campo">
<label for="campo-categoria">Categoría</label>
<select id="campo-categoria" class="modal-input" name="categoria">
<option value="semis">Semis</option>
<option value="capping">Capping</option>
<option value="esculpidas">Esculpidas</option>
<option value="garras">Garras</option>
<option value="cejas">Cejas y Pestañas</option>
</select>
</div>
<div class="modal-campo">
<label for="campo-nombre">Nombre del servicio</label>
<input id="campo-nombre" class="modal-input" type="text" name="nombre" maxlength="50" placeholder="Ej: Semi francesita" autocomplete="off">
</div>
<div class="modal-fila">
<div class="modal-campo">
<label for="campo-precio">Precio</label>
<input id="campo-precio" class="modal-input" type="number" name="precio" min="0" step="0.01" placeholder="22000">
</div>
<div class="modal-campo">
<label for="campo-duracion">Duración (min)</label>
<input id="campo-duracion" class="modal-input" type="number" name="duracion" min="1" max="1440" step="1" placeholder="90">
</div>
</div>
<div class="modal-acciones">
<button class="btn-action" id="btn-confirmar-servicio" type="submit">Guardar servicio</button>
<button class="btn-modal-cerrar" id="btn-cerrar-modal-servicio" type="button">Cancelar</button>
</div>
</form>
</div>
</div>

</main>

<script src="jsvalk/dashboard.js"></script>

</body>
</html>
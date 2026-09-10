/* ============================================================
   modales.js — Apertura/cierre de modales (glassmorphism)
   Se usa en: beta_darky.php
   - Modales de servicios (semis, capping, esculpidas, garras,
     cejas y pestañas)
   - Modal "Reservar Ahora": al elegir un servicio, avisa al
     calendario de turnos (turnos.js) para que se abra
   ============================================================ */

/* ── Modal de servicios (desde las tarjetas del hero) ── */
document.querySelectorAll('[data-modal]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var modal = document.getElementById('modal-' + btn.getAttribute('data-modal'));
    if (modal) modal.classList.add('abierto');
  });
});

document.querySelectorAll('.modal-glass-close').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var modal = btn.closest('.modal-glass');
    if (modal) modal.classList.remove('abierto');
  });
});

document.querySelectorAll('.modal-glass').forEach(function (modal) {
  modal.addEventListener('click', function (e) {
    if (e.target === modal) modal.classList.remove('abierto');
  });
});

/* ── Modal Reservar Ahora (botones del header/overlay) ── */
document.querySelectorAll('.abre-reserva').forEach(function (btn) {
  btn.addEventListener('click', function (e) {
    e.preventDefault();
    var modal = document.getElementById('modal-reservar');
    if (modal) modal.classList.add('abierto');
  });
});

/* ── Reserva: elegir servicio de la lista → abrir calendario ── */
document.querySelectorAll('.reservar-item').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var reservar = document.getElementById('modal-reservar');
    if (reservar) reservar.classList.remove('abierto');

    var servicioId = btn.getAttribute('data-abre');
    var nombreServicio = btn.querySelector('.reservar-nombre');
    var titulo = nombreServicio ? nombreServicio.textContent.trim() : servicioId;

    // Disparar evento para que turnos.js lo recoja y abra el calendario
    document.dispatchEvent(new CustomEvent('darky:seleccionar-servicio', {
      detail: { servicioId: servicioId, nombre: titulo }
    }));
  });
});

/* ── Botón "Reservar este servicio" en cada modal de detalle ── */
var modalesServicio = {
  'modal-semis': 'semis',
  'modal-capping': 'capping',
  'modal-esculpidas': 'esculpidas',
  'modal-garras': 'garras',
  'modal-cejas': 'cejas'
};

Object.keys(modalesServicio).forEach(function (modalId) {
  var modal = document.getElementById(modalId);
  if (!modal) return;

  var btn = document.createElement('button');
  btn.type = 'button';
  btn.className = 'servicio-reservar-btn';
  btn.textContent = 'Reservar este servicio';

  btn.addEventListener('click', function () {
    var servicioId = modalesServicio[modalId];
    var titulo = modal.querySelector('.modal-glass-titulo');
    modal.classList.remove('abierto');

    document.dispatchEvent(new CustomEvent('darky:seleccionar-servicio', {
      detail: { servicioId: servicioId, nombre: titulo ? titulo.textContent.trim() : servicioId }
    }));
  });

  modal.querySelector('.modal-glass-box').appendChild(btn);
});
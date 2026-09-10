/* ============================================================
   registro.js — Formulario "Crear cuenta" (beta_darky.php)
   - Abre/cierra el modal desde el header y el menú overlay
   - Registra el tiempo de inicio (time-trap anti-bots)
   - Obtiene el token CSRF y envía por AJAX a conexion/registro.php
   - Muestra errores/éxito dentro del modal
   ============================================================ */

(function () {
  var modal = document.getElementById("modal-registro");
  if (!modal) return;

  var btnHeader = document.getElementById("abre-registro");
  var btnOverlay = document.getElementById("abre-registro-overlay");
  var btnMobile = document.getElementById("abre-registro-mobile");
  var campoWebsite = document.getElementById("reg-website");
  var campoCsrf = document.getElementById("reg-csrf");
  var campoTiempo = document.getElementById("reg-tiempo");
  var btnCerrar = modal.querySelector(".modal-glass-close");

  function abrir() {
    campoTiempo.value = (Date.now() / 1000).toFixed(3);
    modal.classList.add("abierto");
    document.body.style.overflow = "hidden";
  }

  function cerrar() {
    modal.classList.remove("abierto");
    document.body.style.overflow = "";
  }

  if (btnHeader) btnHeader.addEventListener("click", function (e) { e.preventDefault(); abrir(); });
  if (btnMobile) btnMobile.addEventListener("click", function (e) { e.preventDefault(); abrir(); });
  if (btnOverlay) btnOverlay.addEventListener("click", function (e) {
    e.preventDefault();
    var overlay = document.querySelector(".menu-overlay");
    if (overlay) overlay.classList.remove("active");
    document.body.style.overflow = "";
    abrir();
  });
  if (btnCerrar) btnCerrar.addEventListener("click", cerrar);

  /* Permite abrir el modal desde cualquier parte del sitio
     (p. ej. el enlace dentro del login) sin saltarse abrir() */
  document.addEventListener("darky:abrir-registro", function () { abrir(); });
  modal.addEventListener("click", function (e) {
    if (e.target === modal) cerrar();
  });

  /* ── Obtener token CSRF ── */
  function obtenerToken() {
    return fetch("conexion/token_csrf.php")
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.token) campoCsrf.value = data.token;
        return data.token;
      });
  }

  /* ── Envío ── */
  var form = document.getElementById("form-registro");
  var mensaje = document.getElementById("mensaje-registro");
  if (!form) return;

  form.addEventListener("submit", function (e) {
    e.preventDefault();

    var webside = (campoWebsite && campoWebsite.value.trim()) ? true : false;
    if (webside) {
      /* Honeypot: responder éxito falso, no registrar nada */
      if (mensaje) {
        mensaje.textContent = "Cuenta creada.";
        mensaje.className = "registro-mensaje ok";
      }
      return;
    }

    var tokenPromise = campoCsrf.value
      ? Promise.resolve(campoCsrf.value)
      : obtenerToken();

    tokenPromise.then(function () {
      var datos = new FormData(form);
      if (mensaje) {
        mensaje.textContent = "Creando cuenta...";
        mensaje.className = "registro-mensaje";
      }

      fetch("conexion/registro.php", {
        method: "POST",
        body: datos
      })
      .then(function (response) { return response.json(); })
      .then(function (data) {
        if (data.ok) {
          if (mensaje) {
            mensaje.textContent = data.mensaje || "Cuenta creada correctamente.";
            mensaje.className = "registro-mensaje ok";
          }
          form.reset();
          setTimeout(cerrar, 2500);
        } else {
          var texto = Array.isArray(data.errores) ? data.errores.join(" ") : (data.error || "Error al crear la cuenta.");
          if (mensaje) {
            mensaje.textContent = texto;
            mensaje.className = "registro-mensaje error";
          }
        }
      })
      .catch(function (err) {
        if (mensaje) {
          mensaje.textContent = "Ocurrió un error al crear la cuenta.";
          mensaje.className = "registro-mensaje error";
        }
        console.error(err);
      });
    });
  });
})();
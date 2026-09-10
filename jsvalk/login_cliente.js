/* ============================================================
   login_cliente.js — Login del cliente para sacar turnos
   - Abre el modal de login cuando se toca "Reservar Ahora" o
     cualquier acceso a servicios
   - Envía usuario+contraseña a conexion/login_cliente.php
   - Al éxito, cierra login y abre la selección de servicios
   ============================================================ */

(function () {
  var modalLogin = document.getElementById("modal-login");
  if (!modalLogin) return;

  var form = document.getElementById("form-login");
  var mensaje = document.getElementById("mensaje-login");
  var campoCsrf = document.getElementById("login-csrf");

  /* Token CSRF (reutiliza el endpoint público) */
  function obtenerToken() {
    return fetch("conexion/token_csrf.php")
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.token) campoCsrf.value = data.token;
        return data.token;
      });
  }

  function abrirLogin() {
    var tokenPromise = campoCsrf.value
      ? Promise.resolve()
      : obtenerToken();
    tokenPromise.then(function () {
      modalLogin.classList.add("abierto");
      document.body.style.overflow = "hidden";
    });
  }

  function cerrarLogin() {
    modalLogin.classList.remove("abierto");
    document.body.style.overflow = "";
  }

  /* ── Abrir login desde cualquier "Reservar Ahora" ── */
  function abrirServicios() {
    document.body.style.overflow = "hidden";
    var reservar = document.getElementById("modal-reservar");
    if (reservar) {
      reservar.classList.add("abierto");
      var seccion = document.getElementById("servicios");
      if (seccion) seccion.scrollIntoView({ behavior: "smooth", block: "start" });
    }
  }

  document.querySelectorAll('.abre-reserva, .servicio-abre').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      // Si ya está logueado, va directo a los servicios sin pedir login
      if (window.CLIENTE_LOGEADO) {
        abrirServicios();
      } else {
        abrirLogin();
      }
    });
  });

  /* ── "¿No tenés cuenta? Creala acá" ── */
  var vsRegistro = document.getElementById("login-va-registro");
  if (vsRegistro) {
    vsRegistro.addEventListener("click", function (e) {
      e.preventDefault();
      cerrarLogin();
      document.dispatchEvent(new CustomEvent("darky:abrir-registro"));
    });
  }

  /* ── Servicio pendiente de reserva (si vino de un detalle) ── */
  var servicioPendiente = null;

  function setServicioPendiente(s) { servicioPendiente = s; }
  function getServicioPendiente() { return servicioPendiente; }

  /* Cuando se elige un servicio y no hay sesión, se guarda el pendiente
     (el handler de turnos.js abrirá el login). Al loguearse se retoma. */
  document.addEventListener("darky:seleccionar-servicio", function (e) {
    if (!window.CLIENTE_LOGEADO) {
      servicioPendiente = e.detail;
    }
  });

  /* ── Submit del login ── */
  form.addEventListener("submit", function (e) {
    e.preventDefault();

    var tokenPromise = campoCsrf.value
      ? Promise.resolve(campoCsrf.value)
      : obtenerToken();

    tokenPromise.then(function () {
      var datos = new FormData(form);

      if (mensaje) {
        mensaje.textContent = "Ingresando...";
        mensaje.className = "registro-mensaje";
      }

      fetch("conexion/login_cliente.php", {
        method: "POST",
        body: datos
      })
      .then(function (response) { return response.json(); })
      .then(function (data) {
        if (data.ok) {
          cerrarLogin();
          form.reset();
          if (mensaje) { mensaje.textContent = ""; mensaje.className = "registro-mensaje"; }
          window.CLIENTE_LOGEADO = true;

          // Si vino de un botón "Reservar este servicio" (servicio ya elegido),
          // abrir el calendario directamente; si no, ir a la selección de servicios
          var pendiente = getServicioPendiente();
          if (pendiente) {
            document.dispatchEvent(new CustomEvent('darky:seleccionar-servicio', {
              detail: pendiente
            }));
            setServicioPendiente(null);
          } else {
            abrirServicios();
          }
        } else {
          if (mensaje) {
            mensaje.textContent = data.error || "No se pudo iniciar sesión.";
            mensaje.className = "registro-mensaje error";
          }
        }
      })
      .catch(function (err) {
        if (mensaje) {
          mensaje.textContent = "Ocurrió un error al iniciar sesión.";
          mensaje.className = "registro-mensaje error";
        }
        console.error(err);
      });
    });
  });
})();
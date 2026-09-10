/* ============================================================
   turnos.js — Calendario de turnos (adaptado de test_turnos)
   Se usa en: beta_darky.php
   - Tabla semanal Lun–Sáb, horas de 10 a 17
   - Carga los turnos ocupados desde la BD (conexion/turnos_semana.php)
   - Al elegir una hora, abre el modal de confirmación con la
     variante del servicio (cliente ya logueado con su cuenta)
   ============================================================ */

(function () {
  var modalTurnos = document.getElementById("modal-turnos");
  if (!modalTurnos) return;

  var SERVICE_LABELS = {
    semis: "Semis",
    capping: "Capping",
    esculpidas: "Esculpidas",
    garras: "Garras",
    cejas: "Cejas y Pestañas"
  };

  /* Variantes por servicio (texto como en el repo del index) */
  var VARIANTES = {
    semis: ["Semi liso", "Semi + NailArt", "Semi + Full NailArt"],
    capping: ["Capping gel liso", "Capping + NailArt", "Capping + Full NailArt"],
    esculpidas: ["Esculpidas lisas", "Esculpidas + NailArt", "Esculpidas + Full NailArt"],
    garras: ["Garras cortas", "Garras XL"],
    cejas: ["Cejas", "Pestañas", "Combo Cejas + Pestañas"]
  };

  var meses = ["ENERO", "FEBRERO", "MARZO", "ABRIL", "MAYO", "JUNIO", "JULIO", "AGOSTO", "SEPTIEMBRE", "OCTUBRE", "NOVIEMBRE", "DICIEMBRE"];
  var dias = ["Lunes", "Martes", "Miércoles", "Jueves", "Viernes", "Sábado"];

  var servicioSeleccionadoId = null;
  var servicioSeleccionadoNombre = null;
  var lunesActual = null;

  var csrfToken = null;

  var parrafoTitulo = document.getElementById("tp-titulo-semana");
  var parrafoSubtitulo = document.getElementById("tp-subtitulo-semana");
  var cuerpo = document.getElementById("tp-cuerpo");
  var parrafoServicio = document.getElementById("turnos-servicio-elegido");
  var mensaje = document.getElementById("mensaje-turnos");

  /* ============================ UTILIDADES ============================ */
  function pad(n) { return (n < 10 ? "0" : "") + n; }

  function cerrarTodos() {
    document.querySelectorAll('.modal-glass').forEach(function (m) {
      m.classList.remove('abierto');
    });
    document.body.style.overflow = "";
  }

  function aISODate(d) {
    return d.getFullYear() + "-" + pad(d.getMonth() + 1) + "-" + pad(d.getDate());
  }

  function lunesDeSemana(fecha) {
    var d = new Date(fecha.getFullYear(), fecha.getMonth(), fecha.getDate());
    while (d.getDay() !== 1) {
      d = new Date(d.getFullYear(), d.getMonth(), d.getDate() - 1);
    }
    return d;
  }

  function fechaNumerica(str) {
    var p = str.split("-");
    return new Date(+p[0], +p[1] - 1, +p[2]);
  }

  /* Ocupa un rango [inicio, inicio+duracion) ? */
  function ocupadoEn(horaInicio, desde, hasta) {
    var i = horaInicio;
    while (i < hasta) {
      if (i >= desde && i < hasta) return true;
      i++;
    }
    return false;
  }

  /* ============================ TOKEN CSRF ============================ */
  function obtenerToken() {
    if (csrfToken) return Promise.resolve(csrfToken);
    return fetch("conexion/token_csrf.php")
      .then(function (r) { return r.json(); })
      .then(function (data) {
        csrfToken = data.token;
        return csrfToken;
      });
  }

  /* ============================ MODAL CONFIRMAR ============================ */
  var modalConfirmar = document.getElementById("modal-confirmar-turno");
  var cfFecha = document.getElementById("cf-fecha");
  var cfHora = document.getElementById("cf-hora");
  var cfServicio = document.getElementById("cf-servicio");
  var cfVariante = document.getElementById("cf-variante");
  var cfMensaje = document.getElementById("mensaje-confirmar");
  var turnoSeleccion = null;

  function llenarVariantes() {
    cfVariante.innerHTML = "";
    var op = document.createElement("option");
    op.value = "";
    op.textContent = "Elegí la variante…";
    cfVariante.appendChild(op);
    (VARIANTES[servicioSeleccionadoId] || []).forEach(function (v) {
      var o = document.createElement("option");
      o.value = v;
      o.textContent = v;
      cfVariante.appendChild(o);
    });
  }

  function abrirConfirmar(dia, hora) {
    turnoSeleccion = { fecha: dia, hora: hora };
    cfFecha.textContent = dia.getDate() + "/" + pad(dia.getMonth() + 1);
    cfHora.textContent = pad(hora) + ":00";
    cfServicio.textContent = servicioSeleccionadoNombre || SERVICE_LABELS[servicioSeleccionadoId] || "";
    llenarVariantes();
    if (cfMensaje) { cfMensaje.textContent = ""; cfMensaje.className = "registro-mensaje"; }

    obtenerToken().then(function (token) {
      document.getElementById("cf-csrf").value = token;
      document.getElementById("cf-servicio-input").value = servicioSeleccionadoId;
      document.getElementById("cf-fecha-input").value = aISODate(dia);
      document.getElementById("cf-hora-input").value = pad(hora) + ":00";

      modalConfirmar.classList.add("abierto");
      modalTurnos.classList.remove("abierto");
    });
  }

  /* ── Confirmar turno → reservar_turno.php ── */
  document.getElementById("cf-confirmar").addEventListener("click", function () {
    var variante = cfVariante.value;
    if (!variante) {
      if (cfMensaje) { cfMensaje.textContent = "Elegí la variante del servicio."; cfMensaje.className = "registro-mensaje error"; }
      return;
    }

    var datos = new FormData();
    datos.append("csrf_token", document.getElementById("cf-csrf").value);
    datos.append("servicio", document.getElementById("cf-servicio-input").value);
    datos.append("variante", variante);
    datos.append("fecha", document.getElementById("cf-fecha-input").value);
    datos.append("hora", document.getElementById("cf-hora-input").value);

    if (cfMensaje) { cfMensaje.textContent = "Reservando..."; cfMensaje.className = "registro-mensaje"; }

    fetch("conexion/reservar_turno.php", {
      method: "POST",
      body: datos
    })
    .then(function (response) { return response.json(); })
    .then(function (data) {
      if (data.ok) {
        alert("TURNO TOMADO CON ÉXITO!");
        modalConfirmar.classList.remove("abierto");
        modalTurnos.classList.remove("abierto");
        document.body.style.overflow = "";
        render(); // refrescar ocupados
      } else {
        if (cfMensaje) { cfMensaje.textContent = data.error || "No se pudo reservar."; cfMensaje.className = "registro-mensaje error"; }
      }
    })
    .catch(function (err) {
      if (cfMensaje) { cfMensaje.textContent = "Ocurrió un error al reservar."; cfMensaje.className = "registro-mensaje error"; }
      console.error(err);
    });
  });

  /* ============================ RENDER SEMANAL ============================ */
  function render() {
    if (!lunesActual) return;
    var fin = new Date(lunesActual.getFullYear(), lunesActual.getMonth(), lunesActual.getDate() + 5);

    parrafoTitulo.textContent = meses[lunesActual.getMonth()] + ": semana del " + lunesActual.getDate() + " al " + fin.getDate();
    parrafoSubtitulo.textContent =
      lunesActual.toLocaleDateString("es-AR", { weekday: "long", day: "2-digit", month: "long" }) +
      " – " +
      fin.toLocaleDateString("es-AR", { weekday: "long", day: "2-digit", month: "long", year: "numeric" });

    obtenerToken().then(function (token) {
      var datos = new FormData();
      datos.append("csrf_token", token);
      datos.append("lunes", aISODate(lunesActual));

      return fetch("conexion/turnos_semana.php", {
        method: "POST",
        body: datos
      });
    })
    .then(function (response) { return response.json(); })
    .then(function (data) {
      cuerpo.innerHTML = "";

      if (data.error) {
        if (mensaje) { mensaje.textContent = data.error; mensaje.className = "registro-mensaje error"; }
        return;
      }

      var ocupados = (data.turnos || []).reduce(function (acc, t) {
        if (!acc[t.fecha]) acc[t.fecha] = [];
        var inicio = parseInt(t.hora.slice(0, 2), 10);
        var duracion = t.duracion || 60;
        acc[t.fecha].push({ inicio: inicio, fin: inicio + Math.ceil(duracion / 60) });
        return acc;
      }, {});

      for (var i = 0; i < 6; i++) {
        var dia = new Date(lunesActual.getFullYear(), lunesActual.getMonth(), lunesActual.getDate() + i);
        var fechaKey = aISODate(dia);
        var slotsDia = ocupados[fechaKey] || [];

        var tr = document.createElement("tr");

        var tdDia = document.createElement("td");
        tdDia.textContent = dias[i];
        tr.appendChild(tdDia);

        var tdFecha = document.createElement("td");
        tdFecha.textContent = pad(dia.getDate()) + "/" + pad(dia.getMonth() + 1);
        tr.appendChild(tdFecha);

        var tdHoras = document.createElement("td");
        for (var h = 10; h <= 17; h++) {
          var span = document.createElement("span");
          span.className = "hora";
          span.textContent = pad(h) + ":00";

          var bloqueado = slotsDia.some(function (s) { return h >= s.inicio && h < s.fin; });

          if (bloqueado) {
            span.classList.add("ocupado");
            span.title = "Horario ocupado";
          } else {
            span.classList.add("disponible");
            (function (d, hora) {
              span.addEventListener("click", function () {
                abrirConfirmar(d, hora);
              });
            })(dia, h);
          }
          tdHoras.appendChild(span);
        }
        tr.appendChild(tdHoras);
        cuerpo.appendChild(tr);
      }
    })
    .catch(function (err) {
      if (mensaje) { mensaje.textContent = "No se pudo cargar la semana."; mensaje.className = "registro-mensaje error"; }
      console.error(err);
    });
  }

  function irASemana(lunes) {
    lunesActual = lunesDeSemana(lunes);
    render();
  }

  /* ============================ ESCUCHA SERVICIO ELEGIDO ============================ */
  document.addEventListener("darky:seleccionar-servicio", function (e) {
    servicioSeleccionadoId = e.detail.servicioId;
    servicioSeleccionadoNombre = e.detail.nombre || SERVICE_LABELS[e.detail.servicioId] || e.detail.servicioId;

    if (parrafoServicio) parrafoServicio.textContent = "Servicio elegido: " + servicioSeleccionadoNombre;
    if (mensaje) { mensaje.textContent = ""; mensaje.className = "registro-mensaje"; }

    // Si el cliente no inició sesión, primero pedir login
    if (!window.CLIENTE_LOGEADO) {
      cerrarTodos();
      var login = document.getElementById("modal-login");
      if (login) { login.classList.add("abierto"); document.body.style.overflow = "hidden"; }
      return;
    }

    // Abrir el calendario en la semana actual
    lunesActual = lunesDeSemana(new Date());
    modalTurnos.classList.add("abierto");
    document.body.style.overflow = "hidden";
    render();
  });

  /* ============================ NAVEGACIÓN SEMANA ============================ */
  document.getElementById("tp-prev").addEventListener("click", function () {
    irASemana(new Date(lunesActual.getFullYear(), lunesActual.getMonth(), lunesActual.getDate() - 7));
  });
  document.getElementById("tp-next").addEventListener("click", function () {
    irASemana(new Date(lunesActual.getFullYear(), lunesActual.getMonth(), lunesActual.getDate() + 7));
  });
  document.getElementById("tp-hoy").addEventListener("click", function () {
    irASemana(new Date());
  });

  /* Cerrar modal-confirmar al soltar el cuerpo del modal turnos etc. */
  modalConfirmar.addEventListener("click", function (e) {
    if (e.target === modalConfirmar) modalConfirmar.classList.remove("abierto");
  });
})();
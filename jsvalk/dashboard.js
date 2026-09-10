/* ============================================================
   dashboard.js — Lógica completa del panel del owner
   Se usa en: beta_dashboard.php
   - FullCalendar (sección Turnos)
   - Clientes: CSRF + buscador en tiempo real + registro
   - Servicios: acordeón con precios/duración editables + alta
   - Navegación entre secciones y logout
   ============================================================ */

document.addEventListener("DOMContentLoaded", function () {

/* ── Cache para lazy load ── */
var cache = {};
var calendarRendered = false;

/* ── MODAL RESERVA: servicios y variantes desde la BD ── */
var cacheServicios = null;

function cargarServicios() {
  if (cacheServicios) return Promise.resolve(cacheServicios);
  return fetch("servicios_dashboard/csrf_token.php")
    .then(function (r) { return r.json(); })
    .then(function (data) {
      return fetch("servicios_dashboard/obtener_servicios.php");
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (data.error) throw new Error(data.error);
      cacheServicios = data.servicios;
      return cacheServicios;
    });
}

function llenarVariantes() {
  var selVariante = document.getElementById("sel-variante");
  selVariante.innerHTML = "";
  if (!cacheServicios) return;
  var svc = document.getElementById("sel-servicio").value;
  var grupo = cacheServicios.filter(function (g) { return g.clave === svc; })[0];
  if (!grupo || !grupo.items.length) {
    selVariante.disabled = true;
    var op = document.createElement("option");
    op.value = "";
    op.textContent = "Primero elegí el servicio";
    selVariante.appendChild(op);
    return;
  }
  // Nombres legibles para las claves internas de servicio (mismos que el público)
  var NOMBRES_VARIANTE = {
    semi_liso: "Semi liso", semi_nailart: "Semi + NailArt", semi_fullnailart: "Semi + Full NailArt",
    capping_gel: "Capping gel liso", capping_nailart: "Capping + NailArt", capping_fullnailart: "Capping + Full NailArt",
    esculpida_lisa: "Esculpidas lisas", esculpida_nailart: "Esculpidas + NailArt", esculpida_fullnailart: "Esculpidas + Full NailArt",
    garra_corta: "Garras cortas", garra_xl: "Garras XL",
    ceja: "Cejas", pestania: "Pestañas", ceja_pestania: "Combo Cejas + Pestañas"
  };

  selVariante.disabled = false;
  grupo.items.forEach(function (item) {
    var o = document.createElement("option");
    o.value = item.servicio;
    o.textContent = (NOMBRES_VARIANTE[item.servicio] || item.servicio) + " (" + item.duracion + " min)";
    selVariante.appendChild(o);
  });
}

/* ── FullCalendar (Turnos) ── */
function initCalendar() {
  if (calendarRendered) return;
  var calendarEl = document.getElementById("calendar");
  var calendar = new FullCalendar.Calendar(calendarEl, {
    initialView: "timeGridWeek",
    locale: "es",
    selectable: true,
    selectMirror: true,
    selectConstraint: "businessHours",
    selectOverlap: false,
    eventOverlap: false,
    // ── HORARIO DE ATENCION: LUNES A SABADO, DE 10:00 A 18:00 ──
    businessHours: {
      daysOfWeek: [1, 2, 3, 4, 5, 6],
      startTime: "10:00",
      endTime: "18:00"
    },
    // ── TURNOS DESDE LA BD ──
    events: function (info, success, failure) {
      fetch("conexion/turnos_dashboard.php", { credentials: "same-origin" })
        .then(function (r) {
          if (!r.ok) throw new Error("No autorizado");
          return r.json();
        })
        .then(function (data) {
          if (data.error) throw new Error(data.error);
          success(data);
        })
        .catch(function (err) {
          console.error("Error cargando turnos:", err);
          failure(err);
        });
    },
    views: {
      // VISTA MES: SOLO contador numerico de turnos por dia (sin preview)
      dayGridMonth: {
        dayMaxEvents: 0,
        rowMoreLinkClass: "contador-turnos",
        moreLinkContent: function (arg) {
          return { html: "+" + arg.num };
        }
      },
      // VISTAS SEMANA/DIA: comportamiento normal con preview de turnos
      timeGridWeek: { dayMaxEvents: 3 },
      timeGridDay: { dayMaxEvents: 3 }
    },
    headerToolbar: {
      start: "prev,next today",
      center: "title",
      end: "dayGridMonth,timeGridWeek,timeGridDay"
    },
    eventContent: function (arg) {
      var div = document.createElement("div");
      div.style.cssText = "overflow:hidden; line-height:1.3; padding:2px 4px;";
      var n1 = document.createElement("div");
      n1.textContent = arg.event.title;
      n1.style.fontSize = "13px";
      n1.style.fontWeight = "bold";
      n1.style.whiteSpace = "nowrap";
      n1.style.overflow = "hidden";
      n1.style.textOverflow = "ellipsis";
      div.appendChild(n1);
      var p = arg.event.extendedProps || {};
      if (p.servicio) {
        var n2 = document.createElement("div");
        n2.textContent = p.servicio + (p.variante ? " · " + p.variante : "");
        n2.style.fontSize = "11px";
        n2.style.whiteSpace = "nowrap";
        n2.style.overflow = "hidden";
        n2.style.textOverflow = "ellipsis";
        div.appendChild(n2);
      }
      return { domNodes: [div] };
    },
    select: function (info) {
      if (info.view.type !== "timeGridWeek" && info.view.type !== "timeGridDay") return;
      var inicio = info.start;
      var fin = info.end;
      var fecha = inicio.toLocaleDateString("es-AR", { weekday: "short", day: "2-digit", month: "2-digit" });
      var modalFecha = document.getElementById("modal-fecha");
      modalFecha.textContent = fecha + " · " + formatoHora(inicio) + " – " + formatoHora(fin);
      document.getElementById("sel-servicio").value = "";
      llenarVariantes();
      document.getElementById("input-nombre").value = "";
      var msg = document.getElementById("mensaje-modal-turno");
      if (msg) { msg.textContent = ""; msg.className = "servicios-mensaje"; }
      document.getElementById("modal-reserva").hidden = false;
      seleccion = { start: inicio, end: fin };
    }
  });
  calendar.render();
  calendarRendered = true;

  // Modal de reserva del owner
  initModalReserva(calendar);
}

function formatoHora(d) {
  return d.getHours() + ":" + ("0" + d.getMinutes()).slice(-2);
}

var seleccion = null;

function initModalReserva(calendar) {
  var modal = document.getElementById("modal-reserva");
  var selServicio = document.getElementById("sel-servicio");
  var inputNombre = document.getElementById("input-nombre");
  var btnGuardar = document.getElementById("btn-guardar");
  var btnCancelar = document.getElementById("btn-cancelar");
  if (!modal || !btnGuardar) return;

  // Cargar servicios/variantes desde la BD al primer uso
  cargarServicios().then(function () {
    if (!selServicio.options.length) {
      cacheServicios.forEach(function (grupo) {
        var o = document.createElement("option");
        o.value = grupo.clave;
        o.textContent = grupo.titulo;
        selServicio.appendChild(o);
      });
    }
  }).catch(function (err) { console.error(err); });

  selServicio.addEventListener("change", llenarVariantes);

  function cerrar() {
    modal.hidden = true;
    seleccion = null;
    selServicio.value = "";
    llenarVariantes();
    inputNombre.value = "";
    if (calendar) calendar.unselect();
  }

  btnCancelar.addEventListener("click", cerrar);
  modal.addEventListener("click", function (e) { if (e.target === modal) cerrar(); });

  btnGuardar.addEventListener("click", function () {
    var msg = document.getElementById("mensaje-modal-turno");
    if (!seleccion) { cerrar(); return; }
    var nombre = inputNombre.value.trim();
    var svc = selServicio.value;
    var variante = document.getElementById("sel-variante").value;
    if (!nombre) { msg.textContent = "Ingresá el nombre del cliente."; msg.className = "servicios-mensaje error"; inputNombre.focus(); return; }
    if (!svc) { msg.textContent = "Elegí un servicio."; msg.className = "servicios-mensaje error"; selServicio.focus(); return; }
    if (!variante) { msg.textContent = "Elegí la variante del servicio."; msg.className = "servicios-mensaje error"; document.getElementById("sel-variante").focus(); return; }

    var fechaStr = seleccion.start.getFullYear() + "-" + ("0" + (seleccion.start.getMonth() + 1)).slice(-2) + "-" + ("0" + seleccion.start.getDate()).slice(-2);
    var horaStr = ("0" + seleccion.start.getHours()).slice(-2) + ":" + ("0" + seleccion.start.getMinutes()).slice(-2);

    fetch("clientes_dashboard/csrf_token.php")
      .then(function (r) { return r.json(); })
      .then(function (data) {
        var datos = new URLSearchParams();
        datos.append("csrf_token", data.token);
        datos.append("nombre", nombre);
        datos.append("servicio", svc);
        datos.append("variante", variante);
        datos.append("fecha", fechaStr);
        datos.append("hora", horaStr);
        return fetch("conexion/guardar_turno_dashboard.php", {
          method: "POST",
          body: datos
        });
      })
      .then(function (r) { return r.json().then(function (d) { return { status: r.status, body: d }; }); })
      .then(function (res) {
        if (res.body.ok) {
          cerrar();
          calendar.refetchEvents();
        } else if (res.body.errores) {
          msg.textContent = res.body.errores.join(" ");
          msg.className = "servicios-mensaje error";
        } else {
          msg.textContent = res.body.error || "No se pudo guardar el turno.";
          msg.className = "servicios-mensaje error";
        }
      })
      .catch(function (err) {
        msg.textContent = "Error de conexión: " + err.message;
        msg.className = "servicios-mensaje error";
      });
  });
}

/* ── Inicializar sección por defecto ── */
initCalendar();

/* ── Mapeo sección → función de carga ── */
var loaders = {
turnos: function () { initCalendar(); },
clientes: function () { initClientes(); },
multimedia: function () {
if (cache.multimedia) return;
fetch("api/multimedia.php")
.then(function (r) { return r.json(); })
.then(function (data) {
cache.multimedia = data;
/* TODO: renderizar multimedia en #seccion-multimedia */
});
},
servicios: function () {
initServicios();
}
};

/* ── Clientes: token CSRF + buscador en tiempo real + formulario ── */
var csrfToken = null;

function initClientes() {
  if (cache.clientes) return;
  cache.clientes = true;

  fetch("clientes_dashboard/csrf_token.php")
  .then(function (r) { return r.json(); })
  .then(function (data) {
    csrfToken = data.token;

    /* Escape en JS contra XSS (segunda capa junto al htmlspecialchars de PHP) */
    function escapeHtml(cadena) {
      return String(cadena)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
    }

    var inputBusqueda = document.getElementById("buscador");
    var tbody = document.getElementById("tabla-resultados");
    var mensaje = document.getElementById("mensaje-busqueda");
    var debounceTimer = null;

    /* ── Panel de clientes pendientes (aprobación de la dueña) ── */
    var contenedorPend = document.getElementById("lista-pendientes");
    var mensajePend = document.getElementById("mensaje-pendientes");

    function renderizarPendientes(lista) {
      if (!contenedorPend) return;
      contenedorPend.innerHTML = "";
      if (lista.length === 0) {
        mensajePend.textContent = "No hay clientes en espera.";
        mensajePend.className = "clientes-mensaje";
        return;
      }
      mensajePend.textContent = "";
      mensajePend.className = "clientes-mensaje";
      lista.forEach(function (cliente) {
        var tarjeta = document.createElement("div");
        tarjeta.className = "pendiente-item";
        tarjeta.innerHTML =
          "<div class='pendiente-info'>" +
            "<strong>" + escapeHtml(cliente.nombre) + "</strong> " +
            "<span class='pendiente-detalle'>(" + escapeHtml(cliente.usuario) + ")</span><br>" +
            "<small>" + escapeHtml(cliente.correo) + " &middot; " + escapeHtml(cliente.numero) + "</small><br>" +
            "<small class='pendiente-fecha'>Registrado: " + escapeHtml(cliente.fecha_registro) + "</small>" +
          "</div>" +
          "<div class='pendiente-acciones'>" +
            "<button type='button' class='btn-pendiente aprobar' data-id='" + escapeHtml(cliente.id_nombre) + "'>Aprobar</button>" +
            "<button type='button' class='btn-pendiente rechazar' data-id='" + escapeHtml(cliente.id_nombre) + "'>Rechazar</button>" +
          "</div>";
        contenedorPend.appendChild(tarjeta);
      });

      contenedorPend.querySelectorAll(".btn-pendiente").forEach(function (btn) {
        btn.addEventListener("click", function () {
          decidirPendiente(btn.getAttribute("data-id"), btn.classList.contains("aprobar") ? "aprobar" : "rechazar");
        });
      });
    }

    function cargarPendientes() {
      var datos = new FormData();
      datos.append("csrf_token", csrfToken);

      fetch("clientes_dashboard/pendientes_cliente.php", {
        method: "POST",
        body: datos
      })
      .then(function (response) {
        if (!response.ok) throw new Error("Error de red");
        return response.json();
      })
      .then(function (data) {
        if (data.error) {
          if (mensajePend) { mensajePend.textContent = data.error; mensajePend.className = "clientes-mensaje error"; }
          return;
        }
        renderizarPendientes(data.pendientes);
      })
      .catch(function (err) {
        if (mensajePend) { mensajePend.textContent = "No se pudieron cargar los pendientes."; mensajePend.className = "clientes-mensaje error"; }
        console.error(err);
      });
    }

    function decidirPendiente(id, accion) {
      var datos = new FormData();
      datos.append("id", id);
      datos.append("accion", accion);
      datos.append("csrf_token", csrfToken);

      fetch("clientes_dashboard/aprobar_cliente.php", {
        method: "POST",
        body: datos
      })
      .then(function (response) { return response.json(); })
      .then(function (data) {
        if (data.ok) {
          cargarPendientes();
        } else {
          if (mensajePend) { mensajePend.textContent = data.error || "No se pudo actualizar."; mensajePend.className = "clientes-mensaje error"; }
        }
      })
      .catch(function (err) {
        if (mensajePend) { mensajePend.textContent = "Ocurrió un error al actualizar."; mensajePend.className = "clientes-mensaje error"; }
        console.error(err);
      });
    }

    cargarPendientes();

    function renderizarResultados(resultados) {
      tbody.innerHTML = "";
      if (resultados.length === 0) {
        mensaje.textContent = "No se encontraron resultados.";
        mensaje.className = "clientes-mensaje";
        return;
      }
      mensaje.textContent = "";
      mensaje.className = "clientes-mensaje";
      resultados.forEach(function (cliente) {
        var fila = document.createElement("tr");
        fila.innerHTML =
          "<td>" + escapeHtml(cliente.nombre) + "</td>" +
          "<td>" + escapeHtml(cliente.numero) + "</td>" +
          "<td>" + escapeHtml(cliente.correo) + "</td>";
        tbody.appendChild(fila);
      });
    }

    function buscar(termino) {
      if (termino.trim() === "") {
        tbody.innerHTML = "";
        mensaje.textContent = "";
        mensaje.className = "clientes-mensaje";
        return;
      }

      var datos = new FormData();
      datos.append("busqueda", termino);
      datos.append("csrf_token", csrfToken);

      fetch("clientes_dashboard/buscar_cliente.php", {
        method: "POST",
        body: datos
      })
      .then(function (response) {
        if (!response.ok) throw new Error("Error de red");
        return response.json();
      })
      .then(function (data) {
        if (data.error) {
          mensaje.textContent = data.error;
          mensaje.className = "clientes-mensaje error";
          tbody.innerHTML = "";
          return;
        }
        renderizarResultados(data.resultados);
      })
      .catch(function (err) {
        mensaje.textContent = "Ocurrió un error al buscar.";
        mensaje.className = "clientes-mensaje error";
        console.error(err);
      });
    }

    /* Debounce de 300ms */
    inputBusqueda.addEventListener("input", function () {
      clearTimeout(debounceTimer);
      var termino = this.value;
      debounceTimer = setTimeout(function () {
        buscar(termino);
      }, 300);
    });

    /* Validación de contraseñas en el cliente */
    var password = document.getElementById("password");
    var confirmPassword = document.getElementById("confirm_password");

    function validarContrasena() {
      if (password.value !== confirmPassword.value) {
        confirmPassword.setCustomValidity("Las contraseñas no coinciden");
      } else {
        confirmPassword.setCustomValidity("");
      }
    }
    password.addEventListener("change", validarContrasena);
    confirmPassword.addEventListener("keyup", validarContrasena);

    /* Formulario de registro */
    var form = document.getElementById("form-cliente");
    var formMensaje = document.getElementById("mensaje-form");

    form.addEventListener("submit", function (e) {
      e.preventDefault();

      var datos = new FormData(form);
      datos.append("csrf_token", csrfToken);

      fetch("clientes_dashboard/guardar_cliente.php", {
        method: "POST",
        body: datos
      })
      .then(function (response) { return response.json(); })
      .then(function (data) {
        if (data.ok) {
          formMensaje.textContent = "Datos guardados correctamente.";
          formMensaje.className = "form-mensaje ok";
          form.reset();
        } else {
          var texto = data.error || (data.errores ? data.errores.join(" ") : "Error al guardar.");
          formMensaje.textContent = texto;
          formMensaje.className = "form-mensaje error";
        }
      })
      .catch(function (err) {
        formMensaje.textContent = "Ocurrió un error al guardar.";
        formMensaje.className = "form-mensaje error";
        console.error(err);
      });
    });
  })
  .catch(function (err) {
    console.error("No se pudo obtener el token CSRF:", err);
  });
}

/* ── Servicios y Precios: acordeón con precios editables ── */
var csrfTokenServicios = null;

function mostrarMensajeServicios(texto, tipo) {
  var el = document.getElementById("mensaje-servicios");
  if (!el) return;
  el.textContent = texto;
  el.className = "servicios-mensaje" + (tipo === "error" ? " error" : (tipo === "ok" ? " ok" : ""));
}

function formatearPrecio(valor) {
  return "$" + Number(valor).toLocaleString("es-AR");
}

function nombreLegible(servicio) {
  return String(servicio)
    .replace(/_/g, " ")
    .replace(/\b\w/g, function (c) { return c.toUpperCase(); });
}

function renderCuadro(categorias) {
  var cuadro = document.getElementById("cuadro-servicios");
  cuadro.innerHTML = "";

  categorias.forEach(function (cat) {
    var catDiv = document.createElement("div");
    catDiv.className = "servicio-categoria";
    catDiv.setAttribute("data-categoria", cat.clave);

    var boton = document.createElement("button");
    boton.type = "button";
    boton.className = "servicio-categoria-titulo";

    var nombreSpan = document.createElement("span");
    nombreSpan.className = "servicio-categoria-nombre";
    nombreSpan.textContent = cat.titulo;

    var flechaSpan = document.createElement("span");
    flechaSpan.className = "servicio-categoria-flecha";
    flechaSpan.textContent = "\u25BE";

    boton.appendChild(nombreSpan);
    boton.appendChild(flechaSpan);

    var cuerpo = document.createElement("div");
    cuerpo.className = "servicio-categoria-cuerpo";

    var tabla = document.createElement("table");
    tabla.className = "tabla-servicios";
    tabla.innerHTML =
      "<thead><tr>" +
      "<th>Servicio</th><th>Precio</th><th>Duración</th><th class=\"celda-accion-th\"></th>" +
      "</tr></thead>";

    var tbody = document.createElement("tbody");
    cat.items.forEach(function (item) {
      var fila = document.createElement("tr");
      fila.setAttribute("data-id", item.id_servicio);

      var tdNombre = document.createElement("td");
      tdNombre.textContent = nombreLegible(item.servicio);

      var tdPrecio = document.createElement("td");
      tdPrecio.className = "celda-precio";
      var precioTexto = document.createElement("span");
      precioTexto.className = "precio-texto";
      precioTexto.textContent = formatearPrecio(item.precio);
      var precioInput = document.createElement("input");
      precioInput.type = "number";
      precioInput.min = "0";
      precioInput.step = "0.01";
      precioInput.className = "precio-input";
      precioInput.value = item.precio;
      precioInput.hidden = true;
      tdPrecio.appendChild(precioTexto);
      tdPrecio.appendChild(precioInput);

      var tdDuracion = document.createElement("td");
      tdDuracion.className = "celda-duracion";
      var duracionTexto = document.createElement("span");
      duracionTexto.className = "duracion-texto";
      duracionTexto.textContent = item.duracion + " min";
      var duracionInput = document.createElement("input");
      duracionInput.type = "number";
      duracionInput.min = "1";
      duracionInput.max = "1440";
      duracionInput.step = "1";
      duracionInput.className = "duracion-input";
      duracionInput.value = item.duracion;
      duracionInput.hidden = true;
      tdDuracion.appendChild(duracionTexto);
      tdDuracion.appendChild(duracionInput);

      var tdAccion = document.createElement("td");
      tdAccion.className = "celda-accion";
      var btnEditar = document.createElement("button");
      btnEditar.type = "button";
      btnEditar.className = "btn-accion-fila btn-editar";
      btnEditar.textContent = "Editar";
      var btnGuardar = document.createElement("button");
      btnGuardar.type = "button";
      btnGuardar.className = "btn-accion-fila btn-guardar";
      btnGuardar.textContent = "Guardar";
      btnGuardar.hidden = true;
      var btnCancelar = document.createElement("button");
      btnCancelar.type = "button";
      btnCancelar.className = "btn-accion-fila btn-cancelar";
      btnCancelar.textContent = "Cancelar";
      btnCancelar.hidden = true;
      tdAccion.appendChild(btnEditar);
      tdAccion.appendChild(btnGuardar);
      tdAccion.appendChild(btnCancelar);

      fila.appendChild(tdNombre);
      fila.appendChild(tdPrecio);
      fila.appendChild(tdDuracion);
      fila.appendChild(tdAccion);
      tbody.appendChild(fila);
    });

    tabla.appendChild(tbody);
    cuerpo.appendChild(tabla);
    catDiv.appendChild(boton);
    catDiv.appendChild(cuerpo);
    cuadro.appendChild(catDiv);
  });
}

function entrarEdicion(fila) {
  var texto = fila.querySelector(".precio-texto");
  var input = fila.querySelector(".precio-input");
  var dTexto = fila.querySelector(".duracion-texto");
  var dInput = fila.querySelector(".duracion-input");
  texto.hidden = true;
  input.hidden = false;
  dTexto.hidden = true;
  dInput.hidden = false;
  fila.classList.add("editando");
  fila.querySelector(".btn-editar").hidden = true;
  fila.querySelector(".btn-guardar").hidden = false;
  fila.querySelector(".btn-cancelar").hidden = false;
  input.focus();
  input.select();
}

function salirEdicion(fila, restaurar) {
  if (restaurar) {
    var textoViejo = fila.querySelector(".precio-texto").textContent;
    fila.querySelector(".precio-input").value = textoViejo.replace(/[^0-9.,]/g, "").replace(",", ".");
    var durVieja = fila.querySelector(".duracion-texto").textContent;
    fila.querySelector(".duracion-input").value = durVieja.replace(/[^0-9]/g, "");
  }
  var texto = fila.querySelector(".precio-texto");
  var input = fila.querySelector(".precio-input");
  var dTexto = fila.querySelector(".duracion-texto");
  var dInput = fila.querySelector(".duracion-input");
  texto.hidden = false;
  input.hidden = true;
  dTexto.hidden = false;
  dInput.hidden = true;
  fila.classList.remove("editando");
  fila.querySelector(".btn-editar").hidden = false;
  fila.querySelector(".btn-guardar").hidden = true;
  fila.querySelector(".btn-cancelar").hidden = true;
}

function guardarServicio(fila) {
  var input = fila.querySelector(".precio-input");
  var valor = input.value;
  if (valor === "" || isNaN(valor) || Number(valor) <= 0) {
    mostrarMensajeServicios("Ingresá un precio válido (mayor a 0).", "error");
    return;
  }

  var dInput = fila.querySelector(".duracion-input");
  var duracion = dInput.value;
  if (duracion === "" || isNaN(duracion) || Number(duracion) < 1 || Number(duracion) > 1440) {
    mostrarMensajeServicios("Ingresá una duración válida (1 a 1440 minutos).", "error");
    return;
  }

  var datos = new FormData();
  datos.append("id_servicio", fila.getAttribute("data-id"));
  datos.append("precio", valor);
  datos.append("duracion", duracion);
  datos.append("csrf_token", csrfTokenServicios);

  var texto = fila.querySelector(".precio-texto");
  var dTexto = fila.querySelector(".duracion-texto");
  fila.classList.add("guardando");
  fila.querySelector(".btn-guardar").disabled = true;

  fetch("servicios_dashboard/actualizar_servicio.php", {
    method: "POST",
    body: datos
  })
  .then(function (response) { return response.json(); })
  .then(function (data) {
    if (data.ok) {
      texto.textContent = formatearPrecio(valor);
      dTexto.textContent = duracion + " min";
      salirEdicion(fila, false);
      mostrarMensajeServicios("Servicio actualizado.", "ok");
    } else {
      mostrarMensajeServicios(data.error || "Error al actualizar el servicio.", "error");
      fila.classList.remove("guardando");
      fila.querySelector(".btn-guardar").disabled = false;
    }
  })
  .catch(function (err) {
    mostrarMensajeServicios("Ocurrió un error al guardar.", "error");
    fila.classList.remove("guardando");
    fila.querySelector(".btn-guardar").disabled = false;
    console.error(err);
  });
}

function initServicios() {
  if (cache.servicios) return;
  cache.servicios = true;
  mostrarMensajeServicios("Cargando servicios...", "");

  fetch("servicios_dashboard/csrf_token.php")
  .then(function (r) { return r.json(); })
  .then(function (data) {
    csrfTokenServicios = data.token;
    return fetch("servicios_dashboard/obtener_servicios.php");
  })
  .then(function (r) { return r.json(); })
  .then(function (data) {
    if (data.error) {
      mostrarMensajeServicios(data.error, "error");
      return;
    }
    renderCuadro(data.servicios);
    mostrarMensajeServicios("", "");

    var cuadro = document.getElementById("cuadro-servicios");

    /* Acordeón: abrir/cerrar categorías */
    cuadro.addEventListener("click", function (e) {
      var botonCat = e.target.closest(".servicio-categoria-titulo");
      if (botonCat) {
        var cat = botonCat.parentElement;
        var estabaAbierto = cat.classList.contains("abierto");
        cuadro.querySelectorAll(".servicio-categoria.abierto").forEach(function (c) {
          c.classList.remove("abierto");
        });
        if (!estabaAbierto) cat.classList.add("abierto");
        return;
      }

      /* Edición de precios por fila */
      var fila = e.target.closest("tr[data-id]");
      if (!fila) return;
      if (e.target.classList.contains("btn-editar")) {
        entrarEdicion(fila);
      } else if (e.target.classList.contains("btn-guardar")) {
        guardarServicio(fila);
      } else if (e.target.classList.contains("btn-cancelar")) {
        salirEdicion(fila, true);
      }
    });
  })
  .catch(function (err) {
    mostrarMensajeServicios("No se pudieron cargar los servicios.", "error");
    console.error(err);
  });
}

/* ── Agregar un servicio: modal + envío ── */
var btnAgregarServicio = document.getElementById("btn-agregar-servicio");
var modalAgregarServicio = document.getElementById("modal-agregar-servicio");
var formAgregarServicio = document.getElementById("form-agregar-servicio");
var btnCerrarModal = document.getElementById("btn-cerrar-modal-servicio");

function abrirModalAgregarServicio() {
  if (formAgregarServicio) formAgregarServicio.reset();
  var msg = document.getElementById("mensaje-modal-servicio");
  if (msg) { msg.textContent = ""; msg.className = "servicios-mensaje"; }
  if (modalAgregarServicio) modalAgregarServicio.hidden = false;
}

function cerrarModalAgregarServicio() {
  if (modalAgregarServicio) modalAgregarServicio.hidden = true;
}

if (btnAgregarServicio) {
  btnAgregarServicio.addEventListener("click", abrirModalAgregarServicio);
}
if (btnCerrarModal) {
  btnCerrarModal.addEventListener("click", cerrarModalAgregarServicio);
}

function recargarCuadroServicios() {
  cache.servicios = false;
  var cuadro = document.getElementById("cuadro-servicios");
  if (cuadro) cuadro.innerHTML = "";
  initServicios();
}

if (formAgregarServicio) {
  formAgregarServicio.addEventListener("submit", function (e) {
    e.preventDefault();

    var msg = document.getElementById("mensaje-modal-servicio");
    var nombre = document.getElementById("campo-nombre").value.trim();
    var precio = document.getElementById("campo-precio").value.trim();
    var duracion = document.getElementById("campo-duracion").value.trim();

    if (nombre === "") {
      msg.textContent = "Ingresá un nombre para el servicio.";
      msg.className = "servicios-mensaje error";
      return;
    }
    if (precio === "" || isNaN(precio) || Number(precio) < 0) {
      msg.textContent = "Ingresá un precio válido.";
      msg.className = "servicios-mensaje error";
      return;
    }
    if (duracion === "" || isNaN(duracion) || Number(duracion) < 1) {
      msg.textContent = "Ingresá una duración válida (minutos).";
      msg.className = "servicios-mensaje error";
      return;
    }

    var enviar = function (token) {
      var datos = new FormData();
      datos.append("categoria", document.getElementById("campo-categoria").value);
      datos.append("nombre", nombre);
      datos.append("precio", precio);
      datos.append("duracion", duracion);
      datos.append("csrf_token", token);

      msg.textContent = "Guardando...";
      msg.className = "servicios-mensaje";

      fetch("servicios_dashboard/agregar_servicio.php", {
        method: "POST",
        body: datos
      })
      .then(function (response) { return response.json(); })
      .then(function (data) {
        if (data.ok) {
          cerrarModalAgregarServicio();
          mostrarMensajeServicios("Servicio agregado.", "ok");
          recargarCuadroServicios();
        } else {
          msg.textContent = data.error || "Error al agregar el servicio.";
          msg.className = "servicios-mensaje error";
        }
      })
      .catch(function (err) {
        msg.textContent = "Ocurrió un error al guardar.";
        msg.className = "servicios-mensaje error";
        console.error(err);
      });
    };

    if (csrfTokenServicios) {
      enviar(csrfTokenServicios);
    } else {
      fetch("servicios_dashboard/csrf_token.php")
        .then(function (r) { return r.json(); })
        .then(function (data) {
          csrfTokenServicios = data.token;
          enviar(csrfTokenServicios);
        })
        .catch(function (err) {
          msg.textContent = "No se pudo obtener el token de seguridad.";
          msg.className = "servicios-mensaje error";
          console.error(err);
        });
    }
  });
}

/* ── Navegación por delegación de eventos ── */
var nav = document.querySelector(".nav-panel");

nav.addEventListener("click", function (e) {
var btn = e.target.closest("[data-section]");
if (!btn) return;

var section = btn.getAttribute("data-section");

/* Toggle secciones */
var secciones = document.querySelectorAll(".seccion");
for (var i = 0; i < secciones.length; i++) {
secciones[i].style.display = "none";
}
var target = document.getElementById("seccion-" + section);
if (target) target.style.display = "";

/* Toggle active en nav */
var items = nav.querySelectorAll(".nav-panel-item");
for (var j = 0; j < items.length; j++) {
items[j].classList.remove("active");
}
btn.classList.add("active");

/* Lazy load */
if (loaders[section]) loaders[section]();
});

/* ── Cerrar sesión: redirige a logout.php (destruye sesión y vuelve al login) ── */
var btnLogout = document.querySelector(".btn-logout");
if (btnLogout) {
btnLogout.addEventListener("click", function () {
window.location.href = "conexion/logout.php";
});
}

});
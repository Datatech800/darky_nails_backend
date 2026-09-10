/* ─── IntersectionObserver para activar link del nav cuando la sección #servicios es visible ─── */

document.addEventListener("DOMContentLoaded", function () {

  // Seleccionar la sección a observar y el link del nav que le corresponde
  var section = document.getElementById("servicios");
  var link   = document.querySelector('a[href="#servicios"]');

  // Si falta algún elemento, salir sin hacer nada
  if (!section || !link) return;

  // Crear observer con threshold 0.5 (50% visible)
  var observer = new IntersectionObserver(function (entries) {

    // entries[0] es el único elemento observado
    var entry = entries[0];

    if (entry.isIntersecting) {
      // Agregar clase "active" cuando la sección está al menos 50% visible
      link.classList.add("active");
    } else {
      // Remover clase "active" cuando la sección deja de estar visible
      link.classList.remove("active");
    }

  }, { threshold: 0.5 });

  // Empezar a observar la sección
  observer.observe(section);

});

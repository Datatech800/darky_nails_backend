/* ============================================================
   hero.js — Carrusel del hero (bucle infinito sin rebote)
   Se usa en: beta_darky.php
   - Clona la primera slide al final para loop continuo
   - Flechas prev/next + dots + autoplay cada 10 segundos
   ============================================================ */

var heroSlides = document.querySelectorAll('.hero-slide');
var heroTrack = document.querySelector('.hero-slides-track');
if (heroSlides.length > 1 && heroTrack) {
  var heroDots = document.querySelectorAll('.hero-dot');
  var heroN = heroSlides.length;
  var heroActual = 0;       /* slide lógica visible (0..heroN-1) */
  var heroPos = 0;          /* posición física del track (0..heroN) */
  var heroTimer = null;
  var heroWrapTimer = null;

  /* Clon de la primera slide al final para el bucle infinito */
  heroTrack.appendChild(heroSlides[0].cloneNode(true));

  function heroIrFisico(pos) {
    heroTrack.style.transform = 'translateX(-' + (pos * 100) + '%)';
  }

  function heroSaltar(pos) {
    heroTrack.style.transition = 'none';
    heroIrFisico(pos);
    void heroTrack.offsetWidth; /* forzar reflow para aplicar el salto sin animar */
    heroTrack.style.transition = '';
  }

  function heroActualizarDots() {
    heroDots.forEach(function (d, i) {
      d.classList.toggle('activo', i === heroActual);
    });
  }

  function heroSiguiente() {
    clearTimeout(heroWrapTimer);
    heroPos += 1;
    heroActual = (heroActual + 1) % heroN;
    heroIrFisico(heroPos);
    heroActualizarDots();
    if (heroPos >= heroN) {
      /* Llegó al clon (misma imagen que la primera): al terminar la
         animación, salta sin transición a la primera real y sigue
         avanzando como si el loop fuera continuo. */
      heroWrapTimer = setTimeout(function () {
        heroPos = 0;
        heroSaltar(0);
      }, 800);
    }
  }

  function heroAnterior() {
    clearTimeout(heroWrapTimer);
    if (heroPos === 0) {
      /* Desde la primera real, saltar sin animar al clon (misma imagen)
         para poder retroceder al último slide con transición normal. */
      heroPos = heroN;
      heroSaltar(heroPos);
    }
    heroPos -= 1;
    heroActual = (heroActual - 1 + heroN) % heroN;
    heroIrFisico(heroPos);
    heroActualizarDots();
  }

  function heroIr(index) {
    clearTimeout(heroWrapTimer);
    heroPos = index;
    heroActual = index;
    heroIrFisico(heroPos);
    heroActualizarDots();
  }

  function heroAuto() {
    clearInterval(heroTimer);
    heroTimer = setInterval(heroSiguiente, 10000);
  }

  document.querySelector('.hero-flecha-prev').addEventListener('click', function () { heroAnterior(); heroAuto(); });
  document.querySelector('.hero-flecha-next').addEventListener('click', function () { heroSiguiente(); heroAuto(); });
  heroDots.forEach(function (d, i) {
    d.addEventListener('click', function () { heroIr(i); heroAuto(); });
  });

  heroAuto();
}
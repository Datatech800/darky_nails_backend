/* ============================================================
   nav.js — Menú overlay + scroll del header
   Se usa en: beta_darky.php y galeria.html
   - Abre/cierra el overlay al tocar el botón hamburguesa
   - En mobile, desvanece el header al hacer scroll y el botón
     "Reservar Ahora" prevalece al deslizar hacia abajo
   ============================================================ */

(function() {
var hamburger = document.querySelector('.nav-hamburger');
var overlay = document.querySelector('.menu-overlay');
var closeBtn = document.querySelector('.menu-overlay-close');
var overlayLinks = document.querySelectorAll('.menu-overlay-links a');

function openMenu() { overlay.classList.add('active'); document.body.style.overflow = 'hidden'; }
function closeMenu() { overlay.classList.remove('active'); document.body.style.overflow = ''; }

hamburger.addEventListener('click', openMenu);
closeBtn.addEventListener('click', closeMenu);
overlayLinks.forEach(function(link) { link.addEventListener('click', closeMenu); });

/* Scroll: header se desvanece (solo responsive) - botón prevalece */
(function() {
var bar = document.querySelector('.nav-bar');
var btns = document.querySelectorAll('.nav-btn-abajo');
if (!bar) return;
var maxScroll = 250;
function onScroll() {
if (window.innerWidth > 768) {
bar.classList.remove('scrolled');
btns.forEach(function(b) { b.classList.remove('scrolled'); });
return;
}
var scrollY = window.scrollY || window.pageYOffset;
var p = Math.min(scrollY / maxScroll, 1);
var scrolled = p >= 1;
bar.classList.toggle('scrolled', scrolled);
btns.forEach(function(b) { b.classList.toggle('scrolled', scrolled); });
}
window.addEventListener('scroll', onScroll);
onScroll();
})();
})();
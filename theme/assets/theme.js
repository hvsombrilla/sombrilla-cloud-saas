/* Tema Sombrilla Cloud: sombra de la cabecera al desplazar y animación de entrada de [data-reveal]. */
(function () {
  var root = document.documentElement, header = document.getElementById('scHeader');
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  if (header) {
    var onScroll = function () { header.classList.toggle('is-scrolled', window.scrollY > 4); };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  var items = document.querySelectorAll('[data-reveal]');
  if (!items.length || reduce || !('IntersectionObserver' in window)) { return; }
  root.classList.add('sc-js');
  // Escalonado suave entre hermanos (tarjetas de una misma rejilla).
  items.forEach(function (el) {
    var i = Array.prototype.indexOf.call(el.parentNode.children, el);
    el.style.setProperty('--rd', Math.min(i, 6) * 70 + 'ms');
  });
  var io = new IntersectionObserver(function (entries) {
    entries.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('is-in'); io.unobserve(e.target); } });
  }, { rootMargin: '0px 0px -8% 0px', threshold: .08 });
  items.forEach(function (el) { io.observe(el); });
})();

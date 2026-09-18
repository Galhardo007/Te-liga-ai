/* Filtro de categorias — tudo resolvido em JS (barra de botões, estilos e eventos). */
(function () {
  'use strict';

  var CARD_SELECTOR = '[data-category]';
  var GRID_SELECTOR = '.partners-grid';
  var TODOS = 'todos';

  function slug(texto) {
    return (texto || '')
      .toString()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .trim()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '');
  }

  function rotulo(valor) {
    return valor
      .split('-')
      .map(function (p) { return p.charAt(0).toUpperCase() + p.slice(1); })
      .join(' ');
  }

  function injetarEstilos() {
    if (document.getElementById('filtros-css')) return;
    var css = [
      '.filtros-bar{display:flex;flex-wrap:wrap;justify-content:center;gap:10px;margin:0 auto 28px;padding:0 16px;max-width:1200px}',
      '.filtro-btn{background:transparent;color:#e6e6e6;border:1px solid rgba(255,215,0,.45);border-radius:999px;padding:8px 18px;font-size:14px;letter-spacing:.5px;cursor:pointer;transition:background .25s,color .25s,border-color .25s}',
      '.filtro-btn:hover{border-color:#ffd700;color:#ffd700}',
      '.filtro-btn.ativo{background:#ffd700;border-color:#ffd700;color:#111;font-weight:700}',
      '.filtro-oculto{display:none !important}',
      '.filtro-vazio{text-align:center;color:#bbb;padding:24px 16px}'
    ].join('');
    var style = document.createElement('style');
    style.id = 'filtros-css';
    style.textContent = css;
    document.head.appendChild(style);
  }

  function categoriaDoCard(card) {
    return slug(card.getAttribute('data-category'));
  }

  function criarFiltro(grid) {
    var cards = Array.prototype.slice.call(grid.querySelectorAll(CARD_SELECTOR));
    if (!cards.length) return null;

    var categorias = [];
    cards.forEach(function (card) {
      var cat = categoriaDoCard(card);
      if (cat && categorias.indexOf(cat) === -1) categorias.push(cat);
    });
    if (categorias.length < 2) return null;
    categorias.sort();

    var bar = document.createElement('div');
    bar.className = 'filtros-bar';
    bar.setAttribute('role', 'group');
    bar.setAttribute('aria-label', 'Filtrar por categoria');

    var vazio = document.createElement('p');
    vazio.className = 'filtro-vazio filtro-oculto';
    vazio.textContent = 'Nenhum parceiro nesta categoria.';

    function aplicar(cat) {
      var visiveis = 0;
      cards.forEach(function (card) {
        var mostra = cat === TODOS || categoriaDoCard(card) === cat;
        card.classList.toggle('filtro-oculto', !mostra);
        if (mostra) visiveis++;
      });
      vazio.classList.toggle('filtro-oculto', visiveis > 0);
      Array.prototype.forEach.call(bar.children, function (btn) {
        var ativo = btn.dataset.filtro === cat;
        btn.classList.toggle('ativo', ativo);
        btn.setAttribute('aria-pressed', String(ativo));
      });
    }

    [TODOS].concat(categorias).forEach(function (cat) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'filtro-btn';
      btn.dataset.filtro = cat;
      btn.textContent = cat === TODOS ? 'Todos' : rotulo(cat);
      btn.addEventListener('click', function () { aplicar(cat); });
      bar.appendChild(btn);
    });

    grid.parentNode.insertBefore(bar, grid);
    grid.parentNode.insertBefore(vazio, grid.nextSibling);
    aplicar(TODOS);

    return { grid: grid, categorias: categorias, aplicar: aplicar };
  }

  function ligarMenu(filtros) {
    document.addEventListener('click', function (ev) {
      if (!ev.target.closest) return;
      var item = ev.target.closest('a, summary');
      if (!item || item.closest(GRID_SELECTOR) || item.classList.contains('filtro-btn')) return;

      var cat = slug(item.getAttribute('data-category') || item.textContent);
      var alvo = filtros.filter(function (f) { return f.categorias.indexOf(cat) !== -1; })[0];
      if (!alvo) return;

      if (item.tagName === 'A') ev.preventDefault();
      alvo.aplicar(cat);
      alvo.grid.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }

  function init() {
    injetarEstilos();
    var filtros = Array.prototype.slice
      .call(document.querySelectorAll(GRID_SELECTOR))
      .map(criarFiltro)
      .filter(Boolean);
    if (filtros.length) ligarMenu(filtros);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

/* ═══ LOGIN — POST /php/login.php { email, senha } ═══ */
(function () {
'use strict';
var $ = TLA.$;

function erro(campo, texto) {
  $('erro-' + campo).textContent = texto || '';
  var caixa = $('campo-' + campo);
  if (caixa) caixa.classList.toggle('invalido', !!texto);
}

function aoEntrar(ev) {
  ev.preventDefault();
  ['email', 'senha', 'geral'].forEach(function (c) { erro(c, ''); });
  var email = $('email').value.trim().toLowerCase();
  var senha = $('senha').value;
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) { erro('email', 'Digite um e-mail válido.'); $('email').focus(); return; }
  if (!senha) { erro('senha', 'Digite sua senha.'); $('senha').focus(); return; }

  var botao = $('btn-entrar');
  TLA.ocupado(botao, true, 'Entrar', 'Entrando…');
  TLA.post('/php/login.php', { email: email, senha: senha }).then(function (res) {
    TLA.ocupado(botao, false, 'Entrar', 'Entrando…');
    var c = res.corpo || {};
    if (res.ok && c.sucesso) {
      TLA.guardarUsuario(c.usuario);
      window.location.replace(TLA.destinoSeguro('/perfil.html'));
      return;
    }
    if (res.status === 403 && c.confirmacao) {
      window.location.href = '/cadastro.html?confirmar=' + encodeURIComponent(email);
      return;
    }
    erro(c.campo === 'senha' ? 'senha' : 'geral', TLA.textoDeFalha(res, 'Não foi possível entrar agora.'));
  });
}

function iniciar() {
  TLA.sessao().then(function (s) { if (s.logado) window.location.replace(TLA.destinoSeguro('/perfil.html')); });
  $('form-login').addEventListener('submit', aoEntrar);
  $('ver-senha').addEventListener('click', function (e) {
    var b = e.currentTarget, mostrar = b.getAttribute('aria-pressed') !== 'true';
    $('senha').type = mostrar ? 'text' : 'password';
    b.setAttribute('aria-pressed', String(mostrar));
    b.textContent = mostrar ? 'Ocultar' : 'Mostrar';
  });
}
iniciar();
})();

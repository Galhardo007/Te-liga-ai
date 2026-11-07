/* ═══ ESQUECI A SENHA
   POST /php/esqueci-senha.php   { email }
   POST /php/redefinir-senha.php { email, codigo, senha } ═══ */
(function () {
'use strict';
var $ = TLA.$;
var email = '';
var ESPERA_S = 60, timer = null;

function erro(campo, texto) {
  var e = $('erro-' + campo); if (e) e.textContent = texto || '';
  var caixa = $('campo-' + campo); if (caixa) caixa.classList.toggle('invalido', !!texto);
}
function painel(id) {
  ['painel-email', 'painel-codigo', 'painel-pronto'].forEach(function (p) { $(p).hidden = p !== id; });
  window.scrollTo(0, 0);
}
function espera() {
  var b = $('btn-reenviar'), r = ESPERA_S;
  clearInterval(timer); b.disabled = true; b.textContent = 'reenviar o código (' + r + 's)';
  timer = setInterval(function () {
    r--;
    if (r <= 0) { clearInterval(timer); b.disabled = false; b.textContent = 'reenviar o código'; }
    else b.textContent = 'reenviar o código (' + r + 's)';
  }, 1000);
}

function pedirCodigo(botao, textoNormal) {
  return TLA.post('/php/esqueci-senha.php', { email: email }).then(function (res) {
    if (botao) TLA.ocupado(botao, false, textoNormal, '');
    if (res.ok) return true;
    erro(res.corpo && res.corpo.campo === 'email' ? 'email' : 'geral', TLA.textoDeFalha(res, 'Não foi possível enviar agora.'));
    return false;
  });
}

function aoEnviarEmail(ev) {
  ev.preventDefault();
  erro('email', '');
  email = $('email').value.trim().toLowerCase();
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) { erro('email', 'Digite um e-mail válido.'); return; }
  var b = $('btn-enviar');
  TLA.ocupado(b, true, 'Enviar código', 'Enviando…');
  pedirCodigo(b, 'Enviar código').then(function (ok) {
    if (!ok) return;
    $('codigo-email').textContent = email;
    painel('painel-codigo'); espera(); $('codigo').focus();
  });
}

function aoSalvar(ev) {
  ev.preventDefault();
  ['codigo', 'senha', 'senha2', 'geral'].forEach(function (c) { erro(c, ''); });
  var codigo = TLA.soNumeros($('codigo').value), senha = $('senha').value;
  if (codigo.length !== 6) return erro('codigo', 'Digite os 6 números do código.');
  if (senha.length < 8) return erro('senha', 'A senha precisa ter pelo menos 8 caracteres.');
  if (senha !== $('senha2').value) return erro('senha2', 'As senhas não são iguais.');
  var b = $('btn-salvar');
  TLA.ocupado(b, true, 'Trocar senha', 'Salvando…');
  TLA.post('/php/redefinir-senha.php', { email: email, codigo: codigo, senha: senha }).then(function (res) {
    TLA.ocupado(b, false, 'Trocar senha', 'Salvando…');
    if (res.ok && res.corpo && res.corpo.sucesso) { try { localStorage.removeItem('colab_logado'); } catch (_) {} return painel('painel-pronto'); }
    var c = res.corpo || {};
    erro(c.campo && $('erro-' + c.campo) ? c.campo : 'geral', TLA.textoDeFalha(res, 'Não foi possível trocar a senha.'));
  });
}

$('form-email').addEventListener('submit', aoEnviarEmail);
$('form-nova').addEventListener('submit', aoSalvar);
$('codigo').addEventListener('input', function (e) { e.target.value = TLA.soNumeros(e.target.value).slice(0, 6); });
$('btn-reenviar').addEventListener('click', function () {
  $('ok-codigo').textContent = '';
  pedirCodigo().then(function (ok) { if (ok) { $('ok-codigo').textContent = 'Enviamos um novo código.'; espera(); } });
});
$('ver-senha').addEventListener('click', function (e) {
  var b = e.currentTarget, m = b.getAttribute('aria-pressed') !== 'true';
  $('senha').type = $('senha2').type = m ? 'text' : 'password';
  b.setAttribute('aria-pressed', String(m)); b.textContent = m ? 'Ocultar' : 'Mostrar';
});
var pre = new URLSearchParams(location.search).get('email');
if (pre) $('email').value = pre;
})();

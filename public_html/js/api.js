/* ═══ TE LIGA AÍ — utilitários das páginas de conta ═══
   TLA.sessao()              -> Promise<{ logado, usuario, csrf }>
   TLA.post(url, dados)      -> Promise<{ ok, status, corpo }>  (manda JSON + X-CSRF-Token)
   TLA.enviarArquivo(url, formData)
   TLA.exigirLogin()         -> redireciona para o login se não estiver logado */
(function () {
'use strict';

var csrf = '';

function tratar(r) {
  return r.json().catch(function () { return null; }).then(function (corpo) {
    if (corpo && corpo.csrf) csrf = corpo.csrf;
    return { ok: r.ok, status: r.status, corpo: corpo };
  });
}
function semRede() { return { ok: false, status: 0, corpo: null }; }

function sessao() {
  return fetch('/php/sessao.php', { credentials: 'same-origin' }).then(tratar).then(function (res) {
    var c = res.corpo || {};
    if (c.logado && c.usuario) guardarUsuario(c.usuario); else esquecerUsuario();
    return c;
  }).catch(function () { return { logado: false }; });
}

function post(url, dados) {
  return fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
    body: JSON.stringify(dados || {}),
    credentials: 'same-origin'
  }).then(tratar).catch(semRede);
}

function enviarArquivo(url, formData) {
  return fetch(url, {
    method: 'POST',
    headers: { 'X-CSRF-Token': csrf },
    body: formData,
    credentials: 'same-origin'
  }).then(tratar).catch(semRede);
}

function textoDeFalha(res, padrao) {
  if (res.corpo && res.corpo.erro) return res.corpo.erro;
  if (res.status === 0) return 'Sem conexão. Confira sua internet e tente de novo.';
  if (res.status === 429) return 'Muitas tentativas seguidas. Aguarde um pouco e tente de novo.';
  return padrao;
}

// compatível com o que o cadastro já grava (colab_logado). Só para mostrar nome/foto; quem manda é o servidor.
function guardarUsuario(u) {
  try {
    var t = JSON.stringify(u);
    localStorage.setItem('colab_logado', t);
    sessionStorage.setItem('colab_logado', t);
  } catch (_) {}
}
function esquecerUsuario() {
  try { localStorage.removeItem('colab_logado'); sessionStorage.removeItem('colab_logado'); } catch (_) {}
}

function exigirLogin() {
  return sessao().then(function (s) {
    if (!s.logado) {
      window.location.replace('/php-pages/auth.html?voltar=' + encodeURIComponent(location.pathname + location.search));
      return new Promise(function () {});
    }
    return s;
  });
}

function sair() {
  return post('/php/logout.php').then(function () {
    esquecerUsuario();
    window.location.replace('/');
  });
}

function destinoSeguro(padrao) {
  var v = new URLSearchParams(window.location.search).get('voltar') || '';
  if (v.charAt(0) === '/' && v.charAt(1) !== '/' && v.charAt(1) !== '\\' && v.indexOf('://') === -1) return v;
  return padrao || '/';
}

function $(id) { return document.getElementById(id); }

function ocupado(botao, sim, textoNormal, textoOcupado) {
  botao.disabled = !!sim;
  botao.textContent = sim ? textoOcupado : textoNormal;
}

function soNumeros(v) { return String(v || '').replace(/\D+/g, ''); }

function formatarWhatsapp(v) {
  var n = soNumeros(v);
  if (n.indexOf('55') === 0 && n.length > 11) n = n.slice(2);
  n = n.slice(0, 11);
  if (n.length <= 2) return n.length ? '(' + n : '';
  if (n.length <= 6) return '(' + n.slice(0, 2) + ') ' + n.slice(2);
  if (n.length <= 10) return '(' + n.slice(0, 2) + ') ' + n.slice(2, 6) + '-' + n.slice(6);
  return '(' + n.slice(0, 2) + ') ' + n.slice(2, 7) + '-' + n.slice(7);
}

window.TLA = {
  sessao: sessao, post: post, enviarArquivo: enviarArquivo, textoDeFalha: textoDeFalha,
  exigirLogin: exigirLogin, sair: sair, destinoSeguro: destinoSeguro, guardarUsuario: guardarUsuario,
  $: $, ocupado: ocupado, soNumeros: soNumeros, formatarWhatsapp: formatarWhatsapp
};
})();

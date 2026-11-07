/* ═══ CADASTRO — TE LIGA AÍ ═══
   Fala com o servidor em JSON:
     POST /php/cadastro.php         { nome, email, whatsapp, cidade, senha, tipo, aceite, website_hp }
        -> { sucesso, confirmacao, usuario?, erro?, campo? }
     POST /php/confirmar.php        { email, codigo }
        -> { sucesso, usuario?, erro? }
     POST /php/reenviar-codigo.php  { email }
        -> { sucesso, erro? }
   `campo` (opcional) diz qual campo deu erro: nome, email, whatsapp, cidade, senha, tipo. */
(function () {
'use strict';


var URL_CADASTRO = '/php/cadastro.php';
var URL_CONFIRMAR = '/php/confirmar.php';
var URL_REENVIAR = '/php/reenviar-codigo.php';
var ESPERA_REENVIO_S = 60;


function $(id) { return document.getElementById(id); }


var form = $('form-cadastro');
var painelCadastro = $('painel-cadastro');
var painelCodigo = $('painel-codigo');
var painelPronto = $('painel-pronto');
var emailCadastrado = '';
var timerReenvio = null;


/* ─── mensagens de erro ─── */
function mostrarErro(campo, texto) {
var erro = $('erro-' + campo);
var caixa = $('campo-' + campo);
if (erro) erro.textContent = texto || '';
if (caixa) caixa.classList.toggle('invalido', !!texto);
var entrada = $(campo);
if (entrada && entrada.setAttribute) {
if (texto) entrada.setAttribute('aria-invalid', 'true'); else entrada.removeAttribute('aria-invalid');
  }
}


function limparErros() {
  ['nome', 'email', 'whatsapp', 'cidade', 'senha', 'senha2', 'tipo', 'aceite', 'geral'].forEach(function (c) {
var erro = $('erro-' + c);
if (erro) erro.textContent = '';
var caixa = $('campo-' + c);
if (caixa) caixa.classList.remove('invalido');
var entrada = $(c);
if (entrada && entrada.removeAttribute) entrada.removeAttribute('aria-invalid');
  });
}


/* ─── máscara do WhatsApp: (51) 99999-9999 ─── */
function soNumeros(v) { return String(v || '').replace(/\D+/g, ''); }


function formatarWhatsapp(v) {
var n = soNumeros(v);
if (n.indexOf('55') === 0 && n.length > 11) n = n.slice(2);   // colou com +55
n = n.slice(0, 11);
if (n.length <= 2) return n.length ? '(' + n : '';
if (n.length <= 6) return '(' + n.slice(0, 2) + ') ' + n.slice(2);
if (n.length <= 10) return '(' + n.slice(0, 2) + ') ' + n.slice(2, 6) + '-' + n.slice(6);
return '(' + n.slice(0, 2) + ') ' + n.slice(2, 7) + '-' + n.slice(7);
}


/* ─── força da senha (só uma dica visual; a regra é o mínimo de 8) ─── */
function forcaSenha(s) {
if (!s) return 0;
var pontos = 0;
if (s.length >= 8) pontos++;
if (s.length >= 12) pontos++;
if (/[a-z]/.test(s) && /[A-Z]/.test(s)) pontos++;
if (/\d/.test(s)) pontos++;
if (/[^A-Za-z0-9]/.test(s)) pontos++;
if (s.length < 8) return 1;
return pontos >= 4 ? 3 : (pontos >= 3 ? 2 : 1);
}


function atualizarForca() {
var nivel = forcaSenha($('senha').value);
$('forca-barra').setAttribute('data-nivel', String(nivel));
$('forca-texto').textContent = nivel === 0 ? '' : (nivel === 1 ? 'Força: fraca' : (nivel === 2 ? 'Força: boa' : 'Força: forte'));
}


/* ─── validação ─── */
function lerCampos() {
var tipoMarcado = form.querySelector('input[name="tipo"]:checked');
return {
nome: $('nome').value.replace(/\s+/g, ' ').trim(),
email: $('email').value.trim().toLowerCase(),
whatsapp: soNumeros($('whatsapp').value),
cidade: $('cidade').value.replace(/\s+/g, ' ').trim(),
senha: $('senha').value,
senha2: $('senha2').value,
tipo: tipoMarcado ? tipoMarcado.value : '',
aceite: $('aceite').checked,
website_hp: $('website_hp').value
  };
}


function validar(d) {
var erros = [];
function erro(campo, texto) { erros.push([campo, texto]); }


if (d.nome.length < 2) erro('nome', 'Digite seu nome.');
else if (!/[A-Za-zÀ-ÿ]/.test(d.nome)) erro('nome', 'O nome precisa ter letras.');


if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(d.email)) erro('email', 'Digite um e-mail válido, como nome@email.com.');


if (d.whatsapp.length < 10 || d.whatsapp.length > 11 || d.whatsapp.charAt(0) === '0') {
erro('whatsapp', 'Digite o WhatsApp com DDD, por exemplo (51) 99999-9999.');
  }


if (d.cidade.length < 2) erro('cidade', 'Digite sua cidade.');


if (d.senha.length < 8) erro('senha', 'A senha precisa ter pelo menos 8 caracteres.');
if (d.senha2 !== d.senha) erro('senha2', 'As senhas não são iguais.');


if (!d.tipo) erro('tipo', 'Escolha o tipo de conta para continuar.');


if (!d.aceite) erro('aceite', 'Para criar a conta, aceite os termos de uso e a política de privacidade.');


return erros;
}


/* ─── envio ao servidor ─── */
function postJSON(url, dados) {
return fetch(url, {
method: 'POST',
headers: { 'Content-Type': 'application/json' },
body: JSON.stringify(dados),
credentials: 'same-origin'
  }).then(function (r) {
return r.json().catch(function () { return null; }).then(function (corpo) {
return { ok: r.ok, status: r.status, corpo: corpo };
    });
  }).catch(function () {
return { ok: false, status: 0, corpo: null };
  });
}


function textoDeFalha(res, padrao) {
if (res.corpo && res.corpo.erro) return res.corpo.erro;
if (res.status === 0) return 'Sem conexão. Confira sua internet e tente de novo.';
if (res.status === 429) return 'Muitas tentativas seguidas. Aguarde um pouco e tente de novo.';
return padrao;
}


function ocupado(botao, sim, textoNormal, textoOcupado) {
botao.disabled = !!sim;
botao.textContent = sim ? textoOcupado : textoNormal;
}


function irParaPainel(painel) {
  [painelCadastro, painelCodigo, painelPronto].forEach(function (p) { p.hidden = (p !== painel); });
window.scrollTo(0, 0);
}


/* ─── etapa 1: criar conta ─── */
function aoEnviarCadastro(ev) {
ev.preventDefault();
limparErros();
var d = lerCampos();
var erros = validar(d);
if (erros.length) {
erros.forEach(function (e) { mostrarErro(e[0], e[1]); });
var primeiro = $(erros[0][0]) || $('campo-' + erros[0][0]);
var alvo = erros[0][0] === 'tipo' ? form.querySelector('input[name="tipo"]') : primeiro;
if (alvo && alvo.focus) alvo.focus();
return;
  }


var botao = $('btn-criar');
ocupado(botao, true, 'Criar minha conta', 'Criando sua conta…');


postJSON(URL_CADASTRO, {
nome: d.nome, email: d.email, whatsapp: d.whatsapp, cidade: d.cidade,
senha: d.senha, tipo: d.tipo, aceite: true, website_hp: d.website_hp
  }).then(function (res) {
ocupado(botao, false, 'Criar minha conta', 'Criando sua conta…');
var c = res.corpo;
if (res.ok && c && c.sucesso) {
emailCadastrado = d.email;
if (c.confirmacao === false) return entrarNoSite(c.usuario);
return mostrarCodigo(d.email);
    }
var campo = c && c.campo && $('erro-' + c.campo) ? c.campo : 'geral';
var texto = textoDeFalha(res, 'Não foi possível criar a conta agora. Tente de novo em instantes.');
if (res.status === 409 && campo === 'geral') campo = 'email';
mostrarErro(campo, texto);
var foco = $(campo);
if (foco && foco.focus) foco.focus();
  });
}


/* ─── etapa 2: código do e-mail ─── */
function mostrarCodigo(email) {
$('codigo-email').textContent = email;
$('codigo').value = '';
$('erro-codigo').textContent = '';
$('ok-codigo').textContent = '';
irParaPainel(painelCodigo);
iniciarEsperaReenvio();
$('codigo').focus();
}


function iniciarEsperaReenvio() {
var botao = $('btn-reenviar');
var restante = ESPERA_REENVIO_S;
clearInterval(timerReenvio);
botao.disabled = true;
botao.textContent = 'reenviar o código (' + restante + 's)';
timerReenvio = setInterval(function () {
restante--;
if (restante <= 0) {
clearInterval(timerReenvio);
botao.disabled = false;
botao.textContent = 'reenviar o código';
    } else {
botao.textContent = 'reenviar o código (' + restante + 's)';
    }
  }, 1000);
}


function aoEnviarCodigo(ev) {
if (ev) ev.preventDefault();
var codigo = soNumeros($('codigo').value);
$('erro-codigo').textContent = '';
if (codigo.length !== 6) {
$('erro-codigo').textContent = 'Digite os 6 números do código.';
$('codigo').focus();
return;
  }
var botao = $('btn-confirmar');
ocupado(botao, true, 'Confirmar', 'Confirmando…');
postJSON(URL_CONFIRMAR, { email: emailCadastrado, codigo: codigo }).then(function (res) {
ocupado(botao, false, 'Confirmar', 'Confirmando…');
if (res.ok && res.corpo && res.corpo.sucesso) return entrarNoSite(res.corpo.usuario);
$('erro-codigo').textContent = textoDeFalha(res, 'Código incorreto ou vencido. Confira e tente de novo.');
$('codigo').focus();
  });
}


function aoReenviar() {
var botao = $('btn-reenviar');
if (botao.disabled) return;
$('erro-codigo').textContent = '';
$('ok-codigo').textContent = '';
botao.disabled = true;
postJSON(URL_REENVIAR, { email: emailCadastrado }).then(function (res) {
if (res.ok && res.corpo && res.corpo.sucesso) {
$('ok-codigo').textContent = 'Enviamos um novo código. Pode levar alguns instantes para chegar.';
iniciarEsperaReenvio();
    } else {
$('erro-codigo').textContent = textoDeFalha(res, 'Não foi possível reenviar agora. Tente de novo em instantes.');
botao.disabled = false;
    }
  });
}


/* ─── etapa 3: conta pronta ─── */
function destinoSeguro() {
var v = new URLSearchParams(window.location.search).get('voltar') || '';
// só caminhos do próprio site (bloqueia //outro-site.com e barras invertidas)
if (v.charAt(0) === '/' && v.charAt(1) !== '/' && v.charAt(1) !== '\\' && v.indexOf('://') === -1) return v;
return '/';
}


function entrarNoSite(usuario) {
if (usuario && typeof usuario === 'object') {
try {
var texto = JSON.stringify(usuario);
localStorage.setItem('colab_logado', texto);
sessionStorage.setItem('colab_logado', texto);
sessionStorage.setItem('tla_sessao_t', String(Date.now()));
    } catch (_) {}
  }
irParaPainel(painelPronto);
setTimeout(function () { window.location.replace(destinoSeguro()); }, 1500);
}


/* ─── ligações ─── */
function iniciar() {
if (!form) return;


// vindo do login com conta ainda não confirmada: /cadastro.html?confirmar=email
var pendente = new URLSearchParams(window.location.search).get('confirmar');
if (pendente && /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(pendente)) {
emailCadastrado = pendente.trim().toLowerCase();
mostrarCodigo(emailCadastrado);
postJSON(URL_REENVIAR, { email: emailCadastrado }).then(function (res) {
if (res.ok) $('ok-codigo').textContent = 'Enviamos um novo código para o seu e-mail.';
    });
  }


form.addEventListener('submit', aoEnviarCadastro);
$('form-codigo').addEventListener('submit', aoEnviarCodigo);
$('btn-reenviar').addEventListener('click', aoReenviar);


$('whatsapp').addEventListener('input', function (e) {
e.target.value = formatarWhatsapp(e.target.value);
  });


$('senha').addEventListener('input', atualizarForca);


$('ver-senha').addEventListener('click', function (e) {
var botao = e.currentTarget;
var mostrar = botao.getAttribute('aria-pressed') !== 'true';
$('senha').type = mostrar ? 'text' : 'password';
$('senha2').type = mostrar ? 'text' : 'password';
botao.setAttribute('aria-pressed', String(mostrar));
botao.textContent = mostrar ? 'Ocultar' : 'Mostrar';
botao.setAttribute('aria-label', mostrar ? 'Ocultar senhas' : 'Mostrar senhas');
  });


$('codigo').addEventListener('input', function (e) {
e.target.value = soNumeros(e.target.value).slice(0, 6);
if (e.target.value.length === 6) aoEnviarCodigo();
  });


// tira a mensagem de erro de cada campo assim que a pessoa volta a mexer nele
  ['nome', 'email', 'whatsapp', 'cidade', 'senha', 'senha2'].forEach(function (c) {
$(c).addEventListener('input', function () { mostrarErro(c, ''); mostrarErro('geral', ''); });
  });
$('aceite').addEventListener('change', function () { mostrarErro('aceite', ''); });
Array.prototype.forEach.call(form.querySelectorAll('input[name="tipo"]'), function (r) {
r.addEventListener('change', function () { mostrarErro('tipo', ''); });
  });
}


if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar);
else iniciar();
})();

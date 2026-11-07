/* ═══ MEU PERFIL
   GET/POST /php/perfil.php · POST /php/fotos.php · POST /php/alterar-senha.php ═══ */
(function () {
'use strict';
var $ = TLA.$;
var usuario = null;

function erro(campo, texto) {
  var e = $('erro-' + campo); if (e) e.textContent = texto || '';
  var caixa = $('campo-' + campo); if (caixa) caixa.classList.toggle('invalido', !!texto);
}

function desenharAvatar(u) {
  var a = $('avatar');
  a.textContent = '';
  if (u.foto) {
    var img = document.createElement('img');
    img.src = u.foto; img.alt = ''; img.className = 'avatar';
    a.replaceWith(img); img.id = 'avatar';
  } else {
    a.textContent = (u.nome || '?').trim().charAt(0).toUpperCase();
  }
}

function desenharGaleria(fotos) {
  var g = $('galeria');
  g.textContent = '';
  fotos.forEach(function (f) {
    var fig = document.createElement('figure');
    var img = document.createElement('img');
    img.src = f.url; img.alt = 'Foto'; img.loading = 'lazy';
    var b = document.createElement('button');
    b.type = 'button'; b.textContent = '×'; b.setAttribute('aria-label', 'Remover foto');
    b.addEventListener('click', function () {
      if (!confirm('Remover esta foto?')) return;
      TLA.post('/php/fotos.php', { acao: 'remover', id: f.id }).then(function (res) {
        if (res.ok) carregar(); else erro('foto-galeria', TLA.textoDeFalha(res, 'Não foi possível remover.'));
      });
    });
    fig.appendChild(img); fig.appendChild(b); g.appendChild(fig);
  });
  $('galeria-vazia').hidden = fotos.length > 0;
}

function preencher(dados) {
  var u = usuario = dados.usuario;
  TLA.guardarUsuario(u);
  $('p-nome').textContent = u.nome;
  var selo = $('p-tipo');
  selo.textContent = u.tipo === 'anunciante' ? 'Anunciante' : (u.tipo === 'admin' ? 'Administrador' : 'Usuário');
  selo.className = 'selo ' + u.tipo;
  desenharAvatar(u);
  $('nome').value = u.nome;
  $('email').value = u.email;
  $('whatsapp').value = TLA.formatarWhatsapp(u.whatsapp);
  $('cidade').value = u.cidade;
  $('bio').value = u.bio || '';
  $('bloco-negocio').hidden = u.tipo !== 'anunciante';
  if (u.negocio) {
    $('negocio_nome').value = u.negocio.nome || '';
    $('negocio_categoria').value = u.negocio.categoria || '';
  }
  desenharGaleria(dados.fotos || []);
  var ul = $('secoes');
  ul.textContent = '';
  (dados.secoes || []).forEach(function (s) {
    var li = document.createElement('li'); li.textContent = s.nome; ul.appendChild(li);
  });
  if (!dados.secoes || !dados.secoes.length) ul.innerHTML = '<li>Nenhuma seção por enquanto.</li>';
}

function carregar() {
  return fetch('/php/perfil.php', { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (c) { if (c && c.sucesso) preencher(c); });
}

function salvarDados(ev) {
  ev.preventDefault();
  ['nome', 'whatsapp', 'cidade', 'geral'].forEach(function (c) { erro(c, ''); });
  $('ok-dados').textContent = '';
  var b = $('btn-salvar');
  TLA.ocupado(b, true, 'Salvar', 'Salvando…');
  TLA.post('/php/perfil.php', {
    nome: $('nome').value, whatsapp: TLA.soNumeros($('whatsapp').value), cidade: $('cidade').value,
    bio: $('bio').value, negocio_nome: $('negocio_nome').value, negocio_categoria: $('negocio_categoria').value
  }).then(function (res) {
    TLA.ocupado(b, false, 'Salvar', 'Salvando…');
    if (res.ok && res.corpo && res.corpo.sucesso) { preencher(res.corpo); $('ok-dados').textContent = 'Dados salvos.'; return; }
    var c = res.corpo || {};
    erro(c.campo && $('erro-' + c.campo) ? c.campo : 'geral', TLA.textoDeFalha(res, 'Não foi possível salvar.'));
  });
}

function enviarFoto(input, destino) {
  var campoErro = 'foto-' + destino;
  erro(campoErro, '');
  var arq = input.files && input.files[0];
  if (!arq) return;
  if (arq.size > 5 * 1024 * 1024) { erro(campoErro, 'A imagem pode ter no máximo 5 MB.'); input.value = ''; return; }
  var fd = new FormData();
  fd.append('foto', arq);
  fd.append('destino', destino);
  TLA.enviarArquivo('/php/fotos.php', fd).then(function (res) {
    input.value = '';
    if (res.ok) carregar(); else erro(campoErro, TLA.textoDeFalha(res, 'Não foi possível enviar a foto.'));
  });
}

function trocarSenha(ev) {
  ev.preventDefault();
  erro('senha_atual', ''); erro('senha_nova', ''); $('ok-senha').textContent = '';
  if ($('senha_nova').value.length < 8) return erro('senha_nova', 'A senha precisa ter pelo menos 8 caracteres.');
  TLA.post('/php/alterar-senha.php', { senha_atual: $('senha_atual').value, senha_nova: $('senha_nova').value }).then(function (res) {
    if (res.ok) { $('form-senha').reset(); $('ok-senha').textContent = 'Senha trocada. Saímos da conta nos outros aparelhos.'; return; }
    var c = res.corpo || {};
    erro(c.campo === 'senha_atual' ? 'senha_atual' : 'senha_nova', TLA.textoDeFalha(res, 'Não foi possível trocar a senha.'));
  });
}

TLA.exigirLogin().then(function () {
  return carregar();
}).then(function () {
  $('perfil').hidden = false;
  $('form-dados').addEventListener('submit', salvarDados);
  $('form-senha').addEventListener('submit', trocarSenha);
  $('whatsapp').addEventListener('input', function (e) { e.target.value = TLA.formatarWhatsapp(e.target.value); });
  $('foto-perfil').addEventListener('change', function (e) { enviarFoto(e.target, 'perfil'); });
  $('foto-galeria').addEventListener('change', function (e) { enviarFoto(e.target, 'galeria'); });
  $('btn-sair').addEventListener('click', TLA.sair);
});
})();

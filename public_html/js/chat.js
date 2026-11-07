/* ═══ CHAT (Firebase Firestore)
   1. O PHP confere a sessão e devolve um token personalizado (/php/firebase-token.php).
   2. O navegador entra no Firebase com esse token; uid = "u" + id do usuário no MySQL.
   3. Conversas: conversas/{uidA_uidB} { participantes, nomes, ultimaMensagem, atualizadoEm }
      Mensagens: conversas/{id}/mensagens/{auto} { autor, texto, criadoEm }
   Abrir conversa com alguém: /chat.html?com=123 (id do usuário). Regras em firebase/firestore.rules. */
import { initializeApp } from 'https://www.gstatic.com/firebasejs/10.12.2/firebase-app.js';
import { getAuth, signInWithCustomToken } from 'https://www.gstatic.com/firebasejs/10.12.2/firebase-auth.js';
import {
  getFirestore, collection, doc, getDoc, setDoc, addDoc, updateDoc, query, where, orderBy, limit,
  onSnapshot, serverTimestamp
} from 'https://www.gstatic.com/firebasejs/10.12.2/firebase-firestore.js';

const $ = (id) => document.getElementById(id);
let db, meuUid, meuNome, pararMensagens = null, conversaAtual = null;

function idConversa(a, b) { return [a, b].sort().join('_'); }

function falha(texto) { $('erro-geral').textContent = texto; }

async function entrar() {
  await TLA.exigirLogin();
  const res = await TLA.post('/php/firebase-token.php');
  if (!res.ok) throw new Error(TLA.textoDeFalha(res, 'Não foi possível abrir o chat.'));
  const app = initializeApp(res.corpo.config);
  await signInWithCustomToken(getAuth(app), res.corpo.token);
  db = getFirestore(app);
  meuUid = res.corpo.uid;
  meuNome = res.corpo.nome;
}

async function abrirComUsuario(idMysql) {
  const outroUid = 'u' + parseInt(idMysql, 10);
  if (outroUid === meuUid) return;
  const r = await fetch('/php/usuario-publico.php?id=' + encodeURIComponent(idMysql)).then((x) => x.json());
  if (!r.sucesso) return falha('Perfil não encontrado.');
  const id = idConversa(meuUid, outroUid);
  const ref = doc(db, 'conversas', id);
  const snap = await getDoc(ref).catch(() => null);
  if (!snap || !snap.exists()) {
    await setDoc(ref, {
      participantes: [meuUid, outroUid].sort(),
      nomes: { [meuUid]: meuNome, [outroUid]: (r.perfil.negocio && r.perfil.negocio.nome) || r.perfil.nome },
      ultimaMensagem: '',
      atualizadoEm: serverTimestamp()
    });
  }
  abrirConversa(id);
}

function listarConversas() {
  const q = query(collection(db, 'conversas'), where('participantes', 'array-contains', meuUid),
    orderBy('atualizadoEm', 'desc'), limit(50));
  onSnapshot(q, (snap) => {
    const ul = $('conversas');
    ul.textContent = '';
    snap.forEach((d) => {
      const c = d.data();
      const outro = c.participantes.find((p) => p !== meuUid);
      const li = document.createElement('li');
      const b = document.createElement('button');
      b.type = 'button';
      b.textContent = (c.nomes && c.nomes[outro]) || 'Conversa';
      b.dataset.nome = b.textContent;
      if (d.id === conversaAtual) b.setAttribute('aria-current', 'true');
      b.addEventListener('click', () => abrirConversa(d.id));
      li.appendChild(b);
      ul.appendChild(li);
    });
    $('sem-conversas').hidden = !snap.empty;
  }, (e) => falha('Erro ao carregar conversas: ' + e.message));
}

function abrirConversa(id) {
  conversaAtual = id;
  if (pararMensagens) pararMensagens();
  $('mensagens').textContent = '';
  $('form-msg').hidden = false;
  document.querySelectorAll('#conversas button').forEach((b) => b.removeAttribute('aria-current'));
  getDoc(doc(db, 'conversas', id)).then((s) => {
    const c = s.data(); const outro = c.participantes.find((p) => p !== meuUid);
    $('titulo-conversa').textContent = (c.nomes && c.nomes[outro]) || 'Conversa';
  });
  const q = query(collection(db, 'conversas', id, 'mensagens'), orderBy('criadoEm', 'asc'), limit(200));
  pararMensagens = onSnapshot(q, (snap) => {
    const box = $('mensagens');
    box.textContent = '';
    snap.forEach((d) => {
      const m = d.data();
      const div = document.createElement('div');
      div.className = 'msg' + (m.autor === meuUid ? ' minha' : '');
      div.textContent = m.texto; // textContent: nunca interpreta HTML vindo de outra pessoa
      const hora = document.createElement('small');
      hora.textContent = m.criadoEm ? m.criadoEm.toDate().toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : '…';
      div.appendChild(hora);
      box.appendChild(div);
    });
    box.scrollTop = box.scrollHeight;
  }, (e) => falha('Erro ao carregar mensagens: ' + e.message));
  $('texto').focus();
}

async function enviar(ev) {
  ev.preventDefault();
  const texto = $('texto').value.trim();
  if (!texto || !conversaAtual) return;
  $('texto').value = '';
  try {
    await addDoc(collection(db, 'conversas', conversaAtual, 'mensagens'), { autor: meuUid, texto, criadoEm: serverTimestamp() });
    await updateDoc(doc(db, 'conversas', conversaAtual), { ultimaMensagem: texto.slice(0, 100), atualizadoEm: serverTimestamp() });
  } catch (e) {
    falha('Mensagem não enviada: ' + e.message);
    $('texto').value = texto;
  }
}

(async function iniciar() {
  try {
    await entrar();
    $('chat').hidden = false;
    $('form-msg').addEventListener('submit', enviar);
    listarConversas();
    const com = new URLSearchParams(location.search).get('com');
    if (com && /^\d+$/.test(com)) await abrirComUsuario(com);
  } catch (e) {
    falha(e.message || 'Não foi possível abrir o chat.');
  }
})();

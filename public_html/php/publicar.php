<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/private/bootstrap.php';

// GET  ?secao=lojas            -> { publicacoes: [...] }  (público)
// POST { secao, titulo, texto } -> { sucesso, id }        (exige a permissão "publicar:<secao>")
exigir_metodo('GET', 'POST');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $secao = texto($_GET['secao'] ?? '', 40);
    $lista = consulta(
        "SELECT p.id, p.titulo, p.texto, p.criado_em, u.id autor_id, u.nome autor, u.tipo autor_tipo, u.foto_perfil autor_foto
           FROM publicacoes p JOIN secoes s ON s.id = p.secao_id JOIN usuarios u ON u.id = p.usuario_id
          WHERE s.slug = ? AND p.status = 'publicada' AND u.status = 'ativo'
          ORDER BY p.id DESC LIMIT 50", [$secao])->fetchAll();
    responder(['sucesso' => true, 'publicacoes' => $lista]);
}

$u = exigir_login();
exigir_csrf();
$d = ler_json();
$secao = linha('SELECT id, slug FROM secoes WHERE slug = ?', [texto($d['secao'] ?? '', 40)]);
if (!$secao) falhar('Seção inexistente.', 404, 'secao');
exigir_permissao($u, 'publicar:' . $secao['slug']);
exigir_limite('publicar', (string)$u['id'], 20, 3600);

$titulo = texto($d['titulo'] ?? '', 120);
$txt = trim(mb_substr(is_string($d['texto'] ?? null) ? $d['texto'] : '', 0, 5000));
if (mb_strlen($titulo) < 3) falhar('Escreva um título.', 422, 'titulo');
if (mb_strlen($txt) < 3) falhar('Escreva o texto.', 422, 'texto');

consulta('INSERT INTO publicacoes (usuario_id, secao_id, titulo, texto) VALUES (?, ?, ?, ?)', [$u['id'], $secao['id'], $titulo, $txt]);
responder(['sucesso' => true, 'id' => (int)db()->lastInsertId()], 201);

<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/private/bootstrap.php';

// Curtir, comentar e compartilhar (só contas de usuário, conforme as permissões).
// GET  ?publicacao=ID[&antes=ID]                                   -> { comentarios: [...] }  (público)
// POST { acao: "curtir"|"descurtir", publicacao }                  -> { curtidas, curti }
// POST { acao: "comentar", publicacao, texto }                     -> { comentario, comentarios }
// POST { acao: "apagar_comentario", comentario }                   -> { sucesso }  (autor ou admin)
// POST { acao: "compartilhar", publicacao, destino: whatsapp|facebook|link|outro } -> { compartilhamentos }
exigir_metodo('GET', 'POST');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $antes = (int)($_GET['antes'] ?? 0);
    $lista = consulta(
        "SELECT c.id, c.texto, c.criado_em, u.id autor_id, u.nome autor, u.foto_perfil autor_foto
           FROM comentarios c JOIN usuarios u ON u.id = c.usuario_id
          WHERE c.publicacao_id = ? AND c.status = 'publicado' AND u.status = 'ativo' AND (? = 0 OR c.id < ?)
          ORDER BY c.id DESC LIMIT 50", [(int)($_GET['publicacao'] ?? 0), $antes, $antes])->fetchAll();
    responder(['sucesso' => true, 'comentarios' => $lista]);
}

$u = exigir_login();
exigir_csrf();
$d = ler_json();
$acao = $d['acao'] ?? '';

function publicacao_visivel(int $id): array
{
    $p = linha("SELECT id, total_curtidas, total_comentarios, total_compartilhamentos FROM publicacoes WHERE id = ? AND status = 'publicada'", [$id]);
    if (!$p) falhar('Publicação não encontrada.', 404);
    return $p;
}

if ($acao === 'apagar_comentario') {
    $c = linha("SELECT id, usuario_id, publicacao_id FROM comentarios WHERE id = ? AND status = 'publicado'", [(int)($d['comentario'] ?? 0)]);
    if (!$c) falhar('Comentário não encontrado.', 404);
    if (!($u['tipo'] === 'usuario' && (int)$c['usuario_id'] === $u['id']) && !pode($u, 'admin:usuarios')) falhar('Sua conta não tem permissão para isso.', 403);
    $pdo = db();
    $pdo->beginTransaction();
    if (consulta("UPDATE comentarios SET status = 'removido' WHERE id = ? AND status = 'publicado'", [$c['id']])->rowCount()) {
        consulta('UPDATE publicacoes SET total_comentarios = GREATEST(total_comentarios, 1) - 1 WHERE id = ?', [$c['publicacao_id']]);
    }
    $pdo->commit();
    responder(['sucesso' => true]);
}

$permissao = ['curtir' => 'curtir', 'descurtir' => 'curtir', 'comentar' => 'comentar', 'compartilhar' => 'compartilhar'][$acao] ?? null;
if (!$permissao) falhar('Ação inválida.');
if ($u['tipo'] !== 'usuario') falhar('Curtir, comentar e compartilhar é só para contas de usuário.', 403);
exigir_permissao($u, $permissao);
$p = publicacao_visivel((int)($d['publicacao'] ?? 0));
$pdo = db();

if ($acao === 'curtir' || $acao === 'descurtir') {
    exigir_limite('curtir', $u['ref'], 300, 3600);
    $pdo->beginTransaction();
    if ($acao === 'curtir') {
        if (consulta('INSERT IGNORE INTO curtidas (publicacao_id, usuario_id) VALUES (?, ?)', [$p['id'], $u['id']])->rowCount()) {
            consulta('UPDATE publicacoes SET total_curtidas = total_curtidas + 1 WHERE id = ?', [$p['id']]);
        }
    } elseif (consulta('DELETE FROM curtidas WHERE publicacao_id = ? AND usuario_id = ?', [$p['id'], $u['id']])->rowCount()) {
        consulta('UPDATE publicacoes SET total_curtidas = GREATEST(total_curtidas, 1) - 1 WHERE id = ?', [$p['id']]);
    }
    $pdo->commit();
    responder(['sucesso' => true, 'curti' => $acao === 'curtir', 'curtidas' => (int)publicacao_visivel((int)$p['id'])['total_curtidas']]);
}

if ($acao === 'comentar') {
    exigir_limite('comentar', $u['ref'], 30, 3600);
    $txt = trim(mb_substr(is_string($d['texto'] ?? null) ? $d['texto'] : '', 0, 1000));
    if ($txt === '') falhar('Escreva o comentário.', 422, 'texto');
    $pdo->beginTransaction();
    consulta('INSERT INTO comentarios (publicacao_id, usuario_id, texto) VALUES (?, ?, ?)', [$p['id'], $u['id'], $txt]);
    $id = (int)$pdo->lastInsertId();
    consulta('UPDATE publicacoes SET total_comentarios = total_comentarios + 1 WHERE id = ?', [$p['id']]);
    $pdo->commit();
    responder(['sucesso' => true,
        'comentario' => ['id' => $id, 'texto' => $txt, 'autor_id' => $u['id'], 'autor' => $u['nome'], 'autor_foto' => $u['foto_perfil']],
        'comentarios' => (int)$p['total_comentarios'] + 1], 201);
}

// compartilhar
exigir_limite('compartilhar', $u['ref'], 60, 3600);
$destino = in_array($d['destino'] ?? '', ['whatsapp', 'facebook', 'link', 'outro'], true) ? $d['destino'] : 'link';
$pdo->beginTransaction();
consulta('INSERT INTO compartilhamentos (publicacao_id, usuario_id, destino) VALUES (?, ?, ?)', [$p['id'], $u['id'], $destino]);
consulta('UPDATE publicacoes SET total_compartilhamentos = total_compartilhamentos + 1 WHERE id = ?', [$p['id']]);
$pdo->commit();
responder(['sucesso' => true, 'compartilhamentos' => (int)$p['total_compartilhamentos'] + 1]);

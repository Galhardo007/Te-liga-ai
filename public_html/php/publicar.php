<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/private/bootstrap.php';

// GET  ?secao=lojas[&antes=ID]  -> { publicacoes: [...] }  (público; 30 por página, "antes" pagina)
// POST { secao, titulo, texto } -> { sucesso, id }        (exige a permissão "publicar:<secao>")
exigir_metodo('GET', 'POST');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $antes = (int)($_GET['antes'] ?? 0);
    $linhas = consulta(
        "SELECT p.id, p.autor_tipo, p.usuario_id, p.comerciante_id, p.titulo, p.texto, p.criado_em,
                p.total_curtidas, p.total_comentarios, p.total_compartilhamentos,
                u.nome u_nome, u.foto_perfil u_foto, u.status u_status
           FROM publicacoes p
           JOIN secoes s ON s.id = p.secao_id
           LEFT JOIN usuarios u ON u.id = p.usuario_id
          WHERE s.slug = ? AND p.status = 'publicada' AND (? = 0 OR p.id < ?)
          ORDER BY p.id DESC LIMIT 30",
        [texto($_GET['secao'] ?? '', 40), $antes, $antes]
    )->fetchAll();

    $eu = usuario_logado();
    $curti = [];
    if ($eu && $eu['tipo'] === 'usuario' && $linhas) {
        $ids = array_column($linhas, 'id');
        $curti = array_flip(consulta('SELECT publicacao_id FROM curtidas WHERE usuario_id = ? AND publicacao_id IN ('
            . implode(',', array_fill(0, count($ids), '?')) . ')', array_merge([$eu['id']], $ids))->fetchAll(PDO::FETCH_COLUMN));
    }

    $comerciantes = [];
    $lista = [];
    foreach ($linhas as $p) {
        if ($p['autor_tipo'] === 'anunciante') {
            $c = $comerciantes[$p['comerciante_id']] ??= comerciante_por_id((string)$p['comerciante_id']);
            if (!$c || $c['status'] !== 'ativo') continue;
            $autor = ['id' => $c['id'], 'tipo' => 'anunciante', 'nome' => $c['negocio']['nome'] ?: $c['nome'], 'foto' => $c['foto_perfil']];
        } else {
            if ($p['u_status'] !== 'ativo') continue;
            $autor = ['id' => (int)$p['usuario_id'], 'tipo' => 'usuario', 'nome' => $p['u_nome'], 'foto' => $p['u_foto']];
        }
        $lista[] = [
            'id' => (int)$p['id'], 'titulo' => $p['titulo'], 'texto' => $p['texto'], 'criado_em' => $p['criado_em'],
            'autor' => $autor,
            'curtidas' => (int)$p['total_curtidas'], 'comentarios' => (int)$p['total_comentarios'],
            'compartilhamentos' => (int)$p['total_compartilhamentos'],
            'curti' => isset($curti[$p['id']]),
        ];
    }
    responder(['sucesso' => true, 'publicacoes' => $lista]);
}

$u = exigir_login();
exigir_csrf();
$d = ler_json();
$secao = linha('SELECT id, slug FROM secoes WHERE slug = ?', [texto($d['secao'] ?? '', 40)]);
if (!$secao) falhar('Seção inexistente.', 404, 'secao');
exigir_permissao($u, 'publicar:' . $secao['slug']);
exigir_limite('publicar', $u['ref'], 20, 3600);

$titulo = texto($d['titulo'] ?? '', 120);
$txt = trim(mb_substr(is_string($d['texto'] ?? null) ? $d['texto'] : '', 0, 5000));
if (mb_strlen($titulo) < 3) falhar('Escreva um título.', 422, 'titulo');
if (mb_strlen($txt) < 3) falhar('Escreva o texto.', 422, 'texto');

if ($u['tipo'] === 'anunciante') {
    $porDia = limite_publicacoes_dia($u);
    $hoje = (int)linha('SELECT COUNT(*) n FROM publicacoes WHERE comerciante_id = ? AND criado_em >= NOW() - INTERVAL 1 DAY', [$u['id']])['n'];
    if ($porDia !== null && $hoje >= $porDia) falhar("Você atingiu o limite de $porDia publicações por dia.", 429);
    consulta("INSERT INTO publicacoes (autor_tipo, comerciante_id, secao_id, titulo, texto) VALUES ('anunciante', ?, ?, ?, ?)",
        [$u['id'], $secao['id'], $titulo, $txt]);
} else {
    consulta("INSERT INTO publicacoes (autor_tipo, usuario_id, secao_id, titulo, texto) VALUES ('usuario', ?, ?, ?, ?)",
        [$u['id'], $secao['id'], $titulo, $txt]);
}
responder(['sucesso' => true, 'id' => (int)db()->lastInsertId()], 201);

<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/private/bootstrap.php';

// GET ?id=123 -> perfil público (sem e-mail; WhatsApp só de anunciante)
exigir_metodo('GET');
$u = linha("SELECT * FROM usuarios WHERE id = ? AND status = 'ativo'", [(int)($_GET['id'] ?? 0)]);
if (!$u) falhar('Perfil não encontrado.', 404);

$p = [
    'id'     => (int)$u['id'],
    'nome'   => $u['nome'],
    'tipo'   => $u['tipo'],
    'cidade' => $u['cidade'],
    'bio'    => $u['bio'],
    'foto'   => $u['foto_perfil'],
    'fotos'  => consulta('SELECT arquivo FROM usuario_fotos WHERE usuario_id = ? ORDER BY id', [$u['id']])->fetchAll(PDO::FETCH_COLUMN),
];
if ($u['tipo'] === 'anunciante') {
    $a = linha('SELECT nome_negocio, categoria, verificado_em FROM anunciantes WHERE usuario_id = ?', [$u['id']]);
    $p['whatsapp'] = $u['whatsapp'];
    $p['negocio'] = ['nome' => $a['nome_negocio'] ?? null, 'categoria' => $a['categoria'] ?? null, 'verificado' => !empty($a['verificado_em'])];
}
responder(['sucesso' => true, 'perfil' => $p]);

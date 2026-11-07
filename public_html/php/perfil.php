<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/private/bootstrap.php';

// GET  -> { usuario, fotos, secoes }   (secoes = onde esta conta pode publicar)
// POST { nome, whatsapp, cidade, bio, negocio_nome?, negocio_categoria? } (X-CSRF-Token)
exigir_metodo('GET', 'POST');
$u = exigir_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf();
    exigir_permissao($u, 'perfil:editar');
    $d = ler_json();
    $nome     = texto($d['nome'] ?? '', 80);
    $whatsapp = so_numeros($d['whatsapp'] ?? '');
    $cidade   = texto($d['cidade'] ?? '', 80);
    $bio      = trim(mb_substr(is_string($d['bio'] ?? null) ? $d['bio'] : '', 0, 500));

    if ($e = validar_nome($nome))         falhar($e, 422, 'nome');
    if ($e = validar_whatsapp($whatsapp)) falhar($e, 422, 'whatsapp');
    if (mb_strlen($cidade) < 2)           falhar('Digite sua cidade.', 422, 'cidade');

    consulta('UPDATE usuarios SET nome = ?, whatsapp = ?, cidade = ?, bio = ? WHERE id = ?',
        [$nome, $whatsapp, $cidade, $bio !== '' ? $bio : null, $u['id']]);

    if ($u['tipo'] === 'anunciante') {
        $negocio = texto($d['negocio_nome'] ?? '', 120);
        $categoria = texto($d['negocio_categoria'] ?? '', 60);
        consulta('INSERT INTO anunciantes (usuario_id, nome_negocio, categoria) VALUES (?, ?, ?)
                  ON DUPLICATE KEY UPDATE nome_negocio = VALUES(nome_negocio), categoria = VALUES(categoria)',
            [$u['id'], $negocio ?: null, $categoria ?: null]);
    }
    $u = linha('SELECT * FROM usuarios WHERE id = ?', [$u['id']]);
}

$fotos = consulta('SELECT id, arquivo FROM usuario_fotos WHERE usuario_id = ? ORDER BY id', [$u['id']])->fetchAll();
$perms = permissoes_do_usuario((int)$u['id'], $u['tipo']);
$secoes = array_values(array_filter(
    consulta('SELECT slug, nome FROM secoes ORDER BY nome')->fetchAll(),
    fn($s) => in_array('publicar:' . $s['slug'], $perms, true)
));

responder([
    'sucesso' => true,
    'usuario' => usuario_publico($u),
    'fotos'   => array_map(fn($f) => ['id' => (int)$f['id'], 'url' => $f['arquivo']], $fotos),
    'secoes'  => $secoes,
]);

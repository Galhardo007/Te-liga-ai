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

    $campos = ['nome' => $nome, 'whatsapp' => $whatsapp, 'cidade' => $cidade, 'bio' => $bio !== '' ? $bio : null];
    if ($u['tipo'] === 'anunciante') {
        $negocio = texto($d['negocio_nome'] ?? '', 120);
        $categoria = texto($d['negocio_categoria'] ?? '', 60);
        $u = conta_de_comerciante(comerciante_atualizar($u['id'], function (array &$c) use ($campos, $negocio, $categoria) {
            foreach ($campos as $k => $v) $c[$k] = $v;
            $c['negocio']['nome'] = $negocio ?: null;
            $c['negocio']['categoria'] = $categoria ?: null;
        }));
    } else {
        $u = conta_atualizar($u, $campos);
    }
}

$perms = permissoes_da_conta($u);
$secoes = array_values(array_filter(
    consulta('SELECT slug, nome FROM secoes ORDER BY nome')->fetchAll(),
    fn($s) => in_array('publicar:' . $s['slug'], $perms, true)
));

responder([
    'sucesso' => true,
    'usuario' => usuario_publico($u),
    'fotos'   => fotos_da_conta($u),
    'secoes'  => $secoes,
]);

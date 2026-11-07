<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/private/bootstrap.php';

// GET ?id=123 (usuário) ou ?id=c… (anunciante) -> perfil público (sem e-mail; WhatsApp só de anunciante)
exigir_metodo('GET');
$id = (string)($_GET['id'] ?? '');
$u = (ctype_digit($id) || eh_id_comerciante($id)) ? conta_por_id($id) : null;
if (!$u || $u['status'] !== 'ativo') falhar('Perfil não encontrado.', 404);

$p = [
    'id'     => $u['id'],
    'nome'   => $u['nome'],
    'tipo'   => $u['tipo'],
    'cidade' => $u['cidade'],
    'bio'    => $u['bio'],
    'foto'   => $u['foto_perfil'],
    'fotos'  => array_column(fotos_da_conta($u), 'url'),
];
if ($u['tipo'] === 'anunciante') {
    $p['whatsapp'] = $u['whatsapp'];
    $p['negocio'] = [
        'nome'       => $u['negocio']['nome'] ?? null,
        'categoria'  => $u['negocio']['categoria'] ?? null,
        'verificado' => !empty($u['negocio']['verificado_em']),
    ];
}
responder(['sucesso' => true, 'perfil' => $p]);

<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/private/bootstrap.php';

// POST multipart: foto=<arquivo>, destino=perfil|galeria   -> { sucesso, foto: {id?, url} }
// POST JSON { acao: "remover", id }                         -> { sucesso }
exigir_metodo('POST');
$u = exigir_login();
exigir_csrf();
exigir_permissao($u, 'perfil:editar');

$pasta = rtrim((string)cfg('uploads.dir'), '/') . '/' . (int)$u['id'];
$urlBase = rtrim((string)cfg('uploads.url'), '/') . '/' . (int)$u['id'];

function apagar_arquivo_publico(string $url, string $urlBase, string $pasta): void
{
    if (strpos($url, $urlBase . '/') !== 0) return;
    $arq = $pasta . '/' . basename($url);
    if (is_file($arq)) @unlink($arq);
}

if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') === 0) {
    $d = ler_json();
    if (($d['acao'] ?? '') !== 'remover') falhar('Ação inválida.');
    $f = linha('SELECT id, arquivo FROM usuario_fotos WHERE id = ? AND usuario_id = ?', [(int)($d['id'] ?? 0), $u['id']]);
    if (!$f) falhar('Foto não encontrada.', 404);
    consulta('DELETE FROM usuario_fotos WHERE id = ?', [$f['id']]);
    apagar_arquivo_publico($f['arquivo'], $urlBase, $pasta);
    responder(['sucesso' => true]);
}

exigir_limite('upload', (string)$u['id'], 30, 3600);
$destino = ($_POST['destino'] ?? 'galeria') === 'perfil' ? 'perfil' : 'galeria';
$arq = $_FILES['foto'] ?? null;
if (!$arq || !is_array($arq) || ($arq['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($arq['tmp_name'])) {
    falhar('Escolha uma imagem.', 400, 'foto');
}
if ($arq['size'] > (int)cfg('uploads.max_bytes')) falhar('A imagem pode ter no máximo 5 MB.', 413, 'foto');

if ($destino === 'galeria') {
    $total = (int)linha('SELECT COUNT(*) n FROM usuario_fotos WHERE usuario_id = ?', [$u['id']])['n'];
    if ($total >= (int)cfg('uploads.max_fotos')) falhar('Você atingiu o limite de fotos. Remova uma para enviar outra.', 422, 'foto');
}

// confere o conteúdo real (não a extensão) e regrava a imagem: remove EXIF/GPS e qualquer código escondido
$info = @getimagesize($arq['tmp_name']);
$tipos = [IMAGETYPE_JPEG => 'imagecreatefromjpeg', IMAGETYPE_PNG => 'imagecreatefrompng', IMAGETYPE_WEBP => 'imagecreatefromwebp'];
if (!$info || !isset($tipos[$info[2]])) falhar('Formato não aceito. Envie JPG, PNG ou WEBP.', 415, 'foto');
if ($info[0] * $info[1] > 40000000) falhar('Imagem grande demais.', 413, 'foto');
$origem = @$tipos[$info[2]]($arq['tmp_name']);
if (!$origem) falhar('Não foi possível ler a imagem.', 415, 'foto');

if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
    $o = (int)(@exif_read_data($arq['tmp_name'])['Orientation'] ?? 1);
    if ($o === 3) $origem = imagerotate($origem, 180, 0);
    elseif ($o === 6) $origem = imagerotate($origem, -90, 0);
    elseif ($o === 8) $origem = imagerotate($origem, 90, 0);
}

$maxLado = $destino === 'perfil' ? 512 : (int)cfg('uploads.max_lado', 1600);
$w = imagesx($origem); $h = imagesy($origem);
$escala = min(1, $maxLado / max($w, $h));
$nw = max(1, (int)round($w * $escala)); $nh = max(1, (int)round($h * $escala));
$final = imagecreatetruecolor($nw, $nh);
imagefill($final, 0, 0, imagecolorallocate($final, 255, 255, 255));
imagecopyresampled($final, $origem, 0, 0, 0, 0, $nw, $nh, $w, $h);

if (!is_dir($pasta) && !mkdir($pasta, 0755, true)) throw new RuntimeException("Sem permissão para criar $pasta");
$nomeArq = bin2hex(random_bytes(16)) . '.jpg';
if (!imagejpeg($final, "$pasta/$nomeArq", 85)) throw new RuntimeException('Falha ao salvar imagem');
imagedestroy($origem); imagedestroy($final);
$url = "$urlBase/$nomeArq";

if ($destino === 'perfil') {
    if ($u['foto_perfil']) apagar_arquivo_publico($u['foto_perfil'], $urlBase, $pasta);
    consulta('UPDATE usuarios SET foto_perfil = ? WHERE id = ?', [$url, $u['id']]);
    responder(['sucesso' => true, 'foto' => ['url' => $url]]);
}
consulta('INSERT INTO usuario_fotos (usuario_id, arquivo) VALUES (?, ?)', [$u['id'], $url]);
responder(['sucesso' => true, 'foto' => ['id' => (int)db()->lastInsertId(), 'url' => $url]]);

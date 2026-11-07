<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/private/bootstrap.php';

exigir_metodo('POST');
encerrar_sessao();
responder(['sucesso' => true]);

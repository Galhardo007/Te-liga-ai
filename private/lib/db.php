<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        cfg('db.host'), (int)cfg('db.porta', 3306), cfg('db.nome'));
    $pdo = new PDO($dsn, cfg('db.usuario'), cfg('db.senha'), [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    $pdo->exec("SET time_zone = '" . date('P') . "'");
    return $pdo;
}

function consulta(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function linha(string $sql, array $params = []): ?array
{
    $r = consulta($sql, $params)->fetch();
    return $r === false ? null : $r;
}

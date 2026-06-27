<?php
namespace RestBinder\Demo\Grid;

use PDO;

final class GridDatabase
{
    public static function connect(array $config): PDO
    {
        $driver = $config['driver'] ?? 'mysql';

        if ($driver === 'sqlite') {
            $dsn = sprintf('sqlite:%s', $config['database']);
            $pdo = new PDO($dsn);
        } else {
            $dsn = sprintf(
                '%s:host=%s;port=%d;dbname=%s;charset=%s',
                $driver,
                $config['host'] ?? '127.0.0.1',
                (int) ($config['port'] ?? 3306),
                $config['database'] ?? 'restbinder',
                $config['charset'] ?? 'utf8mb4'
            );
            $pdo = new PDO($dsn, $config['username'] ?? 'root', $config['password'] ?? '');
        }

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $pdo;
    }
}

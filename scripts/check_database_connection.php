<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/database.php';

try {
    $connection = (new Database())->connect();
    $result = $connection->query('SELECT VERSION() AS server_version, DATABASE() AS database_name')
        ->fetch();

    echo "Conexión MySQL/MariaDB correcta." . PHP_EOL;
    echo 'Servidor: ' . $result['server_version'] . PHP_EOL;
    echo 'Base de datos: ' . ($result['database_name'] ?? '(ninguna)') . PHP_EOL;
} catch (PDOException $exception) {
    $errorCode = $exception->getCode();
    fwrite(
        STDERR,
        'Error PDO: no se pudo conectar o consultar MySQL/MariaDB. '
        . 'Revisa la disponibilidad del túnel y las variables DB_HOST, DB_PORT, DB_NAME y DB_USER.'
        . ($errorCode !== '' ? ' Código: ' . $errorCode . '.' : '')
        . PHP_EOL
    );
    exit(1);
}

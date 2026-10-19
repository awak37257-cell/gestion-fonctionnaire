<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 2) . '/config.php';
$sql = file_get_contents(__DIR__ . '/create_password_reset_tokens.sql');
if ($sql === false) {
    throw new RuntimeException('Impossible de lire la migration des jetons de réinitialisation.');
}

$db->exec($sql);
echo "La table PASSWORD_RESET_TOKENS est prête.\n";
<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 2) . '/config.php';
$sql = file_get_contents(__DIR__ . '/create_notifications.sql');
if ($sql === false) {
    throw new RuntimeException('Impossible de lire la migration des notifications.');
}

$db->exec($sql);
echo "La table NOTIFICATIONS est prête.\n";

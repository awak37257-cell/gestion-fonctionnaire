<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 2) . '/config.php';

$indexUnique = $db->query("SHOW INDEX FROM UTILISATEURS WHERE Column_name = 'iden' AND Non_unique = 0")
    ->fetch(PDO::FETCH_ASSOC);
if ($indexUnique) {
    $nomIndex = str_replace('`', '``', (string)$indexUnique['Key_name']);
    $db->exec("ALTER TABLE UTILISATEURS DROP INDEX `$nomIndex`");
}

$indexPartage = $db->query("SHOW INDEX FROM UTILISATEURS WHERE Key_name = 'idx_utilisateurs_iden'")
    ->fetch(PDO::FETCH_ASSOC);
if (!$indexPartage) {
    $db->exec('ALTER TABLE UTILISATEURS ADD INDEX idx_utilisateurs_iden (iden)');
}

echo "Les identifiants e-mail partagés sont activés.\n";
<?php
session_start();
require_once __DIR__ . '/../config.php';

$idUser = (int)($_SESSION['id_user'] ?? $_SESSION['user_id'] ?? 0);
$mode = (string)($_POST['mode'] ?? '');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$idUser
    || !hash_equals((string)($_SESSION['csrf_mode'] ?? ''), (string)($_POST['csrf'] ?? ''))
    || !in_array($mode, ['fonctionnaire', 'admin_local'], true)) {
    http_response_code(403);
    exit('Demande de changement de mode invalide.');
}

$stmt = $db->prepare("SELECT u.id_user, u.rol, u.id_fonc, u.id_org,
        f.nom_fonc, f.prenom_fonc, r.id_org AS org_affectation_active
    FROM UTILISATEURS u
    LEFT JOIN FONCTIONNAIRE f ON f.id_fonc = u.id_fonc
    LEFT JOIN RECEVOIR r ON r.id_fonc = f.id_fonc AND r.date_fin IS NULL
    WHERE u.id_user = ? AND u.rol = 'admin_local'");
$stmt->execute([$idUser]);
$compte = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$compte || (int)($compte['id_fonc'] ?? 0) < 1) {
    http_response_code(403);
    exit('Ce compte ne possède pas de profil fonctionnaire associé.');
}

session_regenerate_id(true);
$idOrg = (int)($compte['org_affectation_active'] ?? $compte['id_org'] ?? 0);
if ($idOrg > 0 && $idOrg !== (int)$compte['id_org']) {
    $stmtSyncOrg = $db->prepare('UPDATE UTILISATEURS SET id_org = ? WHERE id_user = ?');
    $stmtSyncOrg->execute([$idOrg, (int)$compte['id_user']]);
}
$_SESSION['id_user'] = (int)$compte['id_user'];
$_SESSION['user_id'] = (int)$compte['id_user'];
$_SESSION['id_fonc'] = (int)$compte['id_fonc'];
$_SESSION['id_org'] = $idOrg;
$_SESSION['nom'] = trim(($compte['prenom_fonc'] ?? '') . ' ' . ($compte['nom_fonc'] ?? ''));

if ($mode === 'fonctionnaire') {
    $_SESSION['rol'] = 'AGENT';
    header('Location: profil_agent.php?id=' . (int)$compte['id_fonc']);
} else {
    $_SESSION['rol'] = 'admin_local';
    header('Location: admin_dashboard.php');
}
exit();
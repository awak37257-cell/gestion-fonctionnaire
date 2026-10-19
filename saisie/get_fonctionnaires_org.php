<?php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: text/html; charset=UTF-8');

if (($_SESSION['rol'] ?? '') !== 'admin' || empty($_SESSION['id_user'])) {
    http_response_code(403);
    exit('<option value="">Accès refusé</option>');
}

$idOrg = (int)($_GET['id_org'] ?? 0);
if ($idOrg < 1) {
    http_response_code(400);
    exit('<option value="">Organisation invalide</option>');
}

$stmt = $db->prepare("SELECT f.id_fonc, f.matri, f.nom_fonc, f.prenom_fonc, f.email_perso
    FROM FONCTIONNAIRE f
    INNER JOIN RECEVOIR r ON r.id_fonc = f.id_fonc AND r.date_fin IS NULL
    WHERE r.id_org = ?
      AND NOT EXISTS (
          SELECT 1 FROM UTILISATEURS u
          WHERE u.id_fonc = f.id_fonc AND u.rol = 'admin_local'
      )
    ORDER BY f.nom_fonc, f.prenom_fonc");
$stmt->execute([$idOrg]);
$fonctionnaires = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$fonctionnaires) {
    echo '<option value="">Aucun fonctionnaire disponible dans cette organisation</option>';
    exit();
}

echo '<option value="">Choisir un fonctionnaire</option>';
$fonctionnairesAvecEmail = array_filter($fonctionnaires, static function ($fonctionnaire) {
    return filter_var(trim((string)($fonctionnaire['email_perso'] ?? '')), FILTER_VALIDATE_EMAIL) !== false;
});

if (!$fonctionnairesAvecEmail) {
    echo '<option value="">Aucun fonctionnaire avec une adresse e-mail valide</option>';
    exit();
}

foreach ($fonctionnairesAvecEmail as $fonctionnaire) {
    $nom = trim($fonctionnaire['prenom_fonc'] . ' ' . $fonctionnaire['nom_fonc']);
    $label = $fonctionnaire['matri'] . ' - ' . $nom;
    $email = trim((string)$fonctionnaire['email_perso']);
    echo '<option value="' . (int)$fonctionnaire['id_fonc'] . '" data-login="'
        . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '">'
        . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
}
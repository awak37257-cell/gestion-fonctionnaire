<?php
session_start();
require_once '../config.php';
require_once '../includes/notifications.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Accès interdit.");
}

$id_fonc        = $_POST['id_fonc'] ?? null;
$id_affec_dest  = $_POST['id_affec_dest'] ?? null; 
$id_org_dest    = $_POST['id_org_dest'] ?? null;
$date_effet     = $_POST['date_effet'] ?? date('Y-m-d'); 
$motif          = trim($_POST['motif'] ?? 'Mutation');
$ville_affec    = trim($_POST['ville_affec'] ?? ''); // Récupération de la ville
$id_fonc = (int)$id_fonc;
$id_org_dest = (int)$id_org_dest;
$role = strtolower(trim((string)($_SESSION['rol'] ?? '')));
$idSessionFonctionnaire = (int)($_SESSION['id_fonc'] ?? 0);
if ((!$idSessionFonctionnaire && !in_array($role, ['admin', 'admin_local'], true))
    || ($idSessionFonctionnaire && $idSessionFonctionnaire !== $id_fonc && !in_array($role, ['admin', 'admin_local'], true))) {
    http_response_code(403);
    exit('Accès refusé.');
}
if (!hash_equals((string)($_SESSION['csrf_mut'] ?? ''), (string)($_POST['csrf'] ?? ''))) {
    http_response_code(400);
    exit('La session du formulaire a expiré. Rechargez la page.');
}

// Ajout de $ville_affec dans la vérification
if (!$id_fonc || !$id_affec_dest || !$id_org_dest || !$ville_affec) {
    die("Erreur : Informations de mutation incomplètes, veuillez vérifier la ville.");
}

try {
    $db->beginTransaction();
    $stmtOrg = $db->prepare('SELECT id_org FROM RECEVOIR WHERE id_fonc = ? AND date_fin IS NULL FOR UPDATE');
    $stmtOrg->execute([$id_fonc]);
    $id_org_origine = $stmtOrg->fetchColumn();
    if ($role === 'admin_local' && (int)$id_org_origine !== (int)($_SESSION['id_org'] ?? 0)) {
        throw new RuntimeException('Vous ne pouvez pas modifier cet agent.');
    }
    $stmtOrgDest = $db->prepare('SELECT nom_org FROM ORGANISATION WHERE id_org = ?');
    $stmtOrgDest->execute([$id_org_dest]);
    $nom_org_dest = $stmtOrgDest->fetchColumn();
    if (!$nom_org_dest) {
        throw new RuntimeException('Organisation de destination introuvable.');
    }

    // 1. ÉTAPE A : Clôturer l'ancienne affectation
    $sqlCloture = "UPDATE RECEVOIR 
                   SET date_fin = :date_fin 
                   WHERE id_fonc = :id_f AND date_fin IS NULL";
    
    $stmtCloture = $db->prepare($sqlCloture);
    $stmtCloture->execute([
        ':date_fin' => $date_effet,
        ':id_f'     => $id_fonc
    ]);

    // 2. ÉTAPE B : Insérer la nouvelle affectation avec la ville
    $sqlInsert = "INSERT INTO RECEVOIR (id_fonc, id_org, id_affec, date_debut, motif, ville_affec) 
                  VALUES (:id_f, :id_o, :id_a, :date_deb, :motif, :ville)";
    
    $stmtInsert = $db->prepare($sqlInsert);
    $stmtInsert->execute([
        ':id_f'    => $id_fonc,
        ':id_o'    => $id_org_dest,
        ':id_a'    => $id_affec_dest,
        ':date_deb'=> $date_effet,
        ':motif'   => $motif,
        ':ville'   => $ville_affec
    ]);
    $id_rec = (int)$db->lastInsertId();

    $organisationsNotifiees = array_values(array_filter([(int)$id_org_origine, $id_org_dest]));
    notifierAdministrateurs(
        $db,
        $id_fonc,
        'mutation',
        $id_rec,
        'Mutation déclarée',
        'Destination : ' . $nom_org_dest . ' · ' . $motif . ' · ' . $ville_affec . ' · effet le ' . $date_effet,
        $organisationsNotifiees
    );

    $db->commit();
    
    header("Location: ../views/profil_agent.php?id=$id_fonc&success=mutation_ok");
    exit();

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    die("Erreur technique lors de la mutation : " . $e->getMessage());
}
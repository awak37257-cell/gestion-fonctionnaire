<?php
session_start();
require_once '../config.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Accès interdit.");
}

$id_fonc        = $_POST['id_fonc'] ?? null;
$id_affec_dest  = $_POST['id_affec_dest'] ?? null; 
$id_org_dest    = $_POST['id_org_dest'] ?? null;
$date_effet     = $_POST['date_effet'] ?? date('Y-m-d'); 
$motif          = trim($_POST['motif'] ?? 'Mutation');
$ville_affec    = trim($_POST['ville_affec'] ?? ''); // Récupération de la ville

// Ajout de $ville_affec dans la vérification
if (!$id_fonc || !$id_affec_dest || !$id_org_dest || !$ville_affec) {
    die("Erreur : Informations de mutation incomplètes, veuillez vérifier la ville.");
}

try {
    $db->beginTransaction();

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

    $db->commit();
    
    header("Location: ../views/profil_agent.php?id=$id_fonc&success=mutation_ok");
    exit();

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    die("Erreur technique lors de la mutation : " . $e->getMessage());
}
<?php
session_start();
require_once '../config.php';
// DEBUG : Affiche tout ce que le formulaire envoie et arrête le script
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Récupération des données (id_org n'est plus nécessaire ici)
    $titre  = trim($_POST['tit_post']);
    $ville  = trim($_POST['vil_affec']);
    $grade  = trim($_POST['gra_post']); 
    $statut = $_POST['sta_post'];

    try {
        // SQL épuré : on insère uniquement dans la table AFFECTATION
        $sql = "INSERT INTO AFFECTATION (
                    gra_post, 
                    tit_post, 
                    sta_post, 
                    vil_affec
                ) VALUES (:grade, :titre, :statut, :ville)";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':grade'  => $grade,
            ':titre'  => $titre,
            ':statut' => $statut,
            ':ville'  => $ville
        ]);

        echo "<script>alert('Poste créé avec succès !'); window.location.href='../views/admin_dashboard.php';</script>";

    } catch (PDOException $e) {
        echo "Erreur lors du traitement : " . $e->getMessage();
    }
}
?>
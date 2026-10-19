<?php
session_start();
require_once '../config.php';
// DEBUG : Affiche tout ce que le formulaire envoie et arrête le script
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Récupération des données (id_org n'est plus nécessaire ici)
    $titre  = trim((string)($_POST['tit_post'] ?? ''));
    $ville  = trim((string)($_POST['vil_affec'] ?? ''));
    $grade  = trim((string)($_POST['gra_post'] ?? ''));
    $statut = trim((string)($_POST['sta_post'] ?? ''));
    $statutsAutorises = [
        'fonctionnaire_titulaire',
        'fonctionnaire_stagiaire',
        'agent_contractuel',
        'agent_temporaire',
        'agent_vacataire',
        'personnel_local',
        'personnel_international',
        'consultant',
        'volontaire',
    ];

    if ($titre === '' || $ville === '' || $grade === '' || !in_array($statut, $statutsAutorises, true)) {
        header('Location: ../saisie/ajouter_direction.php?error=' . urlencode('Vérifiez le titre, la ville, le grade et le statut d’emploi.'));
        exit();
    }

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
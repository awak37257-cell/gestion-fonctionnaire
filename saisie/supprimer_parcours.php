<?php
session_start();
require_once '../config.php';

// Vérification basique de connexion (optionnel mais recommandé pour éviter les requêtes anonymes)
if (!isset($_SESSION['utilisateur']) && !isset($_SESSION['rol'])) {
    die("Accès refusé. Vous devez être connecté.");
}

$id_rec = (int)($_GET['id_rec'] ?? 0);
$id_agent = (int)($_GET['id_agent'] ?? 0);

if ($id_rec > 0 && $id_agent > 0) {
    try {
        $db->beginTransaction();

        // 1. On vérifie si la ligne qu'on supprime est l'affectation ACTUELLE (date_fin IS NULL)
        $stmtCheck = $db->prepare("SELECT date_fin FROM RECEVOIR WHERE id_rec = ?");
        $stmtCheck->execute([$id_rec]);
        $parcours_a_supprimer = $stmtCheck->fetch();

        // 2. Supprimer la ligne erronée
        $stmtDel = $db->prepare("DELETE FROM RECEVOIR WHERE id_rec = ?");
        $stmtDel->execute([$id_rec]);

        // 3. Si on vient de supprimer le poste actuel, on réactive le poste précédent
        if ($parcours_a_supprimer && $parcours_a_supprimer['date_fin'] === null) {
            $stmtRestore = $db->prepare("
                UPDATE RECEVOIR 
                SET date_fin = NULL 
                WHERE id_fonc = ? 
                ORDER BY date_debut DESC 
                LIMIT 1
            ");
            $stmtRestore->execute([$id_agent]);
        }

        $db->commit();
        // Redirection vers le profil de l'agent avec un message de succès
        header("Location: ../views/profil_agent.php?id=" . $id_agent . "&msg=parcours_corrige");
        exit();

    } catch (PDOException $e) {
        $db->rollBack();
        die("Erreur lors de la suppression : " . $e->getMessage());
    }
} else {
    // Si les identifiants sont invalides, on redirige vers l'accueil ou la liste des agents
    header("Location: ../views/liste_agents.php");
    exit();
}
<?php
session_start();
require_once 'config.php';

// 1. SÉCURITÉ : Admin uniquement
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header("Location: saisie/login.php");
    exit();
}

// 2. VÉRIFICATION DE L'ID
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $id = $_GET['id'];

    try {
        $db->beginTransaction();

        // A. Récupérer les infos de l'agent avant suppression (pour le log)
        $stmtInfo = $db->prepare("SELECT nom_fonc, matri FROM FONCTIONNAIRE WHERE id_fonc = ?");
        $stmtInfo->execute([$id]);
        $agent = $stmtInfo->fetch();

        if ($agent) {
            // B. Suppression de l'agent
            // Rappel : Assure-toi que tes clés étrangères sont en ON DELETE CASCADE 
            // pour supprimer l'UTILISATEUR et les DOCUMENTS liés.
            $stmt = $db->prepare("DELETE FROM FONCTIONNAIRE WHERE id_fonc = ?");
            $stmt->execute([$id]);

            // C. Enregistrement dans l'HISTORIQUE
            $id_admin = $_SESSION['id_user'];
            $action = "Suppression définitive de l'agent : " . $agent['nom_fonc'] . " (Matricule: " . $agent['matri'] . ")";
            
            $log = $db->prepare("INSERT INTO HISTORIQUE (id_user, action_desc, dat_hist) VALUES (?, ?, NOW())");
            $log->execute([$id_admin, $action]);

            $db->commit();
            header("Location: views/admin_dashboard.php?msg=delete_success");
            exit();
        } else {
            $db->rollBack();
            header("Location: views/admin_dashboard.php?msg=not_found");
            exit();
        }

    } catch (PDOException $e) {
        $db->rollBack();
        // Si erreur de contrainte (ex: l'agent a des documents sans CASCADE)
        header("Location: views/admin_dashboard.php?msg=error_constrainte");
        exit();
    }
} else {
    header("Location: views/admin_dashboard.php");
    exit();
}
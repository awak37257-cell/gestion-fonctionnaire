<?php
session_start();
require_once '../config.php';

// 1. VÉRIFICATION DE LA SESSION
if (!isset($_SESSION['id_user'])) {
    header("Location: ../saisie/login.php");
    exit();
}

// 2. RÉCUPÉRATION SÉCURISÉE DES DONNÉES
// On utilise les noms exacts de ton formulaire HTML
$old_password = $_POST['old_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';
$user_id = $_SESSION['id_user'];

// 3. VÉRIFICATION DES CHAMPS VIDES
if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
    header("Location: ../views/modifier_mdp.php?error=Veuillez remplir tous les champs.");
    exit();
}

// 4. VÉRIFICATION DE LA CORRESPONDANCE DU NOUVEAU MDP
if ($new_password !== $confirm_password) {
    header("Location: ../views/modifier_mdp.php?error=Les nouveaux mots de passe ne correspondent pas.");
    exit();
}

try {
    // 5. RÉCUPÉRATION DU HASH ACTUEL DANS LA BASE
    // Note : Vérifie bien que ta table s'appelle UTILISATEURS et ta colonne mdp
    $stmt = $db->prepare("SELECT mot_de_pas FROM UTILISATEURS WHERE id_user = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if ($user) {
        // 6. VÉRIFICATION DU MOT DE PASSE ACTUEL
        if (password_verify($old_password, $user['mot_de_pas'])) {
            
            // 7. HACHAGE DU NOUVEAU MOT DE PASSE
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            
            // 8. MISE À JOUR EN BASE DE DONNÉES
            $update = $db->prepare("UPDATE UTILISATEURS SET mot_de_pas = ? WHERE id_user = ?");
            if ($update->execute([$new_hash, $user_id])) {
                // Succès : redirection vers le dashboard
                header("Location: ../views/admin_dashboard.php?msg=success_mdp");
                exit();
            } else {
                header("Location: ../views/modifier_mdp.php?error=Erreur technique lors de la mise à jour.");
                exit();
            }
            
        } else {
            // L'ancien mot de passe saisi ne correspond pas au hash en BDD
            header("Location: ../views/modifier_mdp.php?error=L'ancien mot de passe est incorrect.");
            exit();
        }
    } else {
        header("Location: ../views/modifier_mdp.php?error=Utilisateur introuvable.");
        exit();
    }

} catch (PDOException $e) {
    // Affiche l'erreur réelle pour le débogage
    die("Erreur SQL : " . $e->getMessage()); 
}
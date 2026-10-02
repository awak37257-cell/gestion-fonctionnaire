<?php
session_start();
require_once '../config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $identifiant = trim($_POST['identifiant']);
    $password = $_POST['password'];

    try {
        // Jointure pour récupérer les infos utilisateur ET son organisation
        $sql = "SELECT u.*, o.sigle_org 
                FROM UTILISATEURS u
                LEFT JOIN ORGANISATION o ON u.id_org = o.id_org 
                WHERE u.iden = ?";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$identifiant]);
        $user = $stmt->fetch();

        // Vérification mot de passe
        if ($user && password_verify($password, $user['mot_de_pas'])) {
            
            // SÉCURITÉ : Régénérer l'ID de session après login réussi
            session_regenerate_id(true);

            // Stockage complet en SESSION pour usage global dans l'app
            $_SESSION['user_id']   = $user['id_user'];   // Clé primaire
            $_SESSION['user_iden'] = $user['iden'];      // Login
            $_SESSION['rol']       = $user['rol'];       // Rôle (admin, admin_local, etc.)
            $_SESSION['id_org']    = $user['id_org'];    // Lien organisation
            $_SESSION['sigle_org'] = $user['sigle_org']; // Affichage facilitée
            $_SESSION['id_fonc']   = $user['id_fonc'];   // Utile pour charger le profil agent

            // Redirection selon le rôle
            // On peut maintenant gérer plus de rôles si nécessaire
            switch ($user['rol']) {
                case 'admin':
                    header("Location: ../views/admin_dashboard.php");
                    break;
                case 'admin_local':
                    header("Location: ../views/gestionnaire_dashboard.php");
                    break;
                default:
                    header("Location: ../views/dashboard.php");
                    break;
            }
            exit();

        } else {
            // Identifiants incorrects
            header("Location: ../views/login.php?error=1");
            exit();
        }

    } catch (PDOException $e) {
        die("Erreur système : " . $e->getMessage());
    }
}
?>
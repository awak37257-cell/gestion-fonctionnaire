<?php
session_start();
require_once '../config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $identifiant = trim($_POST['identifiant']);
    $password = $_POST['password'];

    try {
        // Jointure pour récupérer les infos utilisateur ET son organisation
        $sql = "SELECT u.*, o.sigle_org, f.matri, f.nom_fonc, f.prenom_fonc,
                       r.id_org AS org_affectation_active
                FROM UTILISATEURS u
                LEFT JOIN ORGANISATION o ON u.id_org = o.id_org 
            LEFT JOIN FONCTIONNAIRE f ON f.id_fonc = u.id_fonc
            LEFT JOIN RECEVOIR r ON r.id_fonc = f.id_fonc AND r.date_fin IS NULL
            WHERE u.iden = ? OR (u.rol = 'admin_local' AND f.matri = ?)
            ORDER BY CASE WHEN u.iden = ? THEN 0 ELSE 1 END";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$identifiant, $identifiant, $identifiant]);
        $comptes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $comptesValides = [];
        foreach ($comptes as $compte) {
            if (password_verify($password, $compte['mot_de_pas'])) {
                $comptesValides[] = $compte;
            }
        }
        $user = count($comptesValides) === 1 ? $comptesValides[0] : null;

        // Vérification mot de passe
        if ($user) {
            $connexionParMatricule = strtolower((string)$user['rol']) === 'admin_local'
                && strcasecmp(trim((string)($user['matri'] ?? '')), $identifiant) === 0;
            $roleSession = $connexionParMatricule ? 'AGENT' : $user['rol'];
            $idOrgSession = $user['id_org'];
            if (!empty($user['org_affectation_active'])) {
                $idOrgSession = (int)$user['org_affectation_active'];
                $stmtSyncOrg = $db->prepare('UPDATE UTILISATEURS SET id_org = ? WHERE id_user = ?');
                $stmtSyncOrg->execute([$idOrgSession, (int)$user['id_user']]);
            }
            
            // SÉCURITÉ : Régénérer l'ID de session après login réussi
            session_regenerate_id(true);

            // Stockage complet en SESSION pour usage global dans l'app
            $_SESSION['user_id']   = $user['id_user'];   // Clé primaire
            $_SESSION['user_iden'] = $user['iden'];      // Login
            $_SESSION['rol']       = $roleSession;       // Le matricule ouvre le mode fonctionnaire pour un admin local
            $_SESSION['id_org']    = $idOrgSession;      // Organisation courante du fonctionnaire
            $_SESSION['sigle_org'] = $user['sigle_org']; // Affichage facilitée
            $_SESSION['id_fonc']   = $user['id_fonc'];   // Utile pour charger le profil agent
            $_SESSION['id_user']   = $user['id_user'];
            $_SESSION['nom']       = $connexionParMatricule
                ? trim(($user['prenom_fonc'] ?? '') . ' ' . ($user['nom_fonc'] ?? ''))
                : $user['iden'];

            // Redirection selon le rôle
            // On peut maintenant gérer plus de rôles si nécessaire
            switch ($roleSession) {
                case 'admin':
                    header("Location: ../views/admin_dashboard.php");
                    break;
                case 'admin_local':
                    header("Location: ../views/gestionnaire_dashboard.php");
                    break;
                case 'AGENT':
                    header("Location: ../views/profil_agent.php?id=" . (int)$user['id_fonc']);
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
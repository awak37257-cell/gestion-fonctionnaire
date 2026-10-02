<?php
/*
session_start();
require_once '../config.php';

// --- CHARGEMENT MANUEL DE PHPMAILER (Chemin corrigé) ---
require '../libs/phpmailer/Exception.php';
require '../libs/phpmailer/PHPMailer.php';
require '../libs/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. Récupération des données
    $sigle = strtoupper(trim($_POST['sigle'])); 
    $email_gestionnaire = trim($_POST['email']);
    $password_brut = $_POST['password']; 
    
    // Identifiant automatique (ex: gestionnaire_onu)
    $identifiant = "gestionnaire_" . strtolower($sigle);
    $password_clair = $_POST['password'];
    $mdp_hash = password_hash($password_brut, PASSWORD_DEFAULT);

    try {
        // --- ÉTAPE A : Vérifier si le gestionnaire existe déjà ---
        $check = $db->prepare("SELECT iden FROM UTILISATEURS WHERE iden = ?");
        $check->execute([$identifiant]);
        
        if ($check->fetch()) {
            echo "<script>alert('Erreur : Ce compte gestionnaire existe déjà.'); window.history.back();</script>";
            exit();
        }

        // --- ÉTAPE B : Gestion de l'Organisation ---
        // On vérifie si l'organisation existe déjà pour éviter le "Duplicate entry"
       // ... (Après tes vérifications PHPMailer et mot de passe)

// ÉTAPE B : On prépare l'organisation
// --- ÉTAPE B : Gestion de l'Organisation ---

// 1. On cherche si l'organisation existe déjà par son sigle ou son ID
$checkOrg = $db->prepare("SELECT id_org FROM ORGANISATION WHERE id_org = ? OR sigle_org = ?");
$checkOrg->execute([$sigle, $sigle]);
$org = $checkOrg->fetch();

if (!$org) {
    // 2. Elle n'existe pas : on l'insère proprement
    $insOrg = $db->prepare("INSERT INTO ORGANISATION (id_org, sigle_org) VALUES (?, ?)");
    $insOrg->execute([$sigle, $sigle]);
    $id_org_final = $sigle; 
    error_log("Nouvelle organisation créée : " . $sigle);
} else {
    // 3. Elle existe déjà : on récupère simplement son ID existant
    $id_org_final = $org['id_org'];
    error_log("L'organisation existe déjà, récupération de l'ID : " . $id_org_final);
}

// --- ÉTAPE C : Insertion de l'utilisateur ---
// On utilise $id_org_final pour être sûr qu'il correspond à ce qui est en base
$sql = "INSERT INTO UTILISATEURS (iden, mot_de_pas, rol, id_org) VALUES (?, ?, ?, ?)";
$stmt = $db->prepare($sql);
$stmt->execute([$identifiant, $mdp_hash, 'admin_local', $id_org_final]);
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'awak37257@gmail.com'; 
            $mail->Password   = 'igbqjtuujphlryrf'; // <--- Ton code 16 caractères
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom('awak37257@gmail.com', 'Ministère de la Fonction Publique');
            $mail->addAddress($email_gestionnaire);

            $mail->isHTML(true);
            $mail->Subject = "Activation Espace Gestionnaire - " . $sigle;
            
            $mail->Body = "
    <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: auto; border: 1px solid #eee; padding: 20px; border-radius: 10px;'>
        <div style='background: #0A2540; padding: 10px; text-align: center; border-radius: 8px 8px 0 0;'>
            <h1 style='color: #D4AF37; margin: 0;'>Accès Gestionnaire</h1>
        </div>
        <div style='padding: 20px;'>
            <p>Bonjour,</p>
            <p>Votre compte de gestionnaire pour l'organisation <strong>{$sigle}</strong> a été créé avec succès.</p>
            <p style='background: #f4f4f4; padding: 15px; border-radius: 5px;'>
                <strong>Votre Identifiant :</strong> <span style='color: #0A2540; font-weight: bold;'>{$identifiant}</span><br>
                <strong>Votre Mot de passe :</strong> <span style='color: #0A2540; font-weight: bold;'>{$password_clair}</span>
            </p>
            <p style='color: #e67e22; font-size: 0.9rem;'><em>Note : Pour des raisons de sécurité, nous vous conseillons de ne pas partager cet email.</em></p>
            <div style='text-align: center; margin-top: 20px;'>
                <a href='http://localhost/ton_projet/views/login.php' style='display: inline-block; padding: 12px 25px; background: #D4AF37; color: #fff; text-decoration: none; border-radius: 5px; font-weight: bold;'>Accéder à l'Espace Sécurisé</a>
            </div>
        </div>
        <footer style='font-size: 12px; color: #777; text-align: center; margin-top: 20px; border-top: 1px solid #eee; padding-top: 10px;'>
            Ministère de la Fonction Publique - Projet Soutenance 2026
        </footer>
    </div>";

            $mail->send();
        } catch (Exception $e_mail) {
            error_log("Email non envoyé : " . $mail->ErrorInfo);
        }

        // --- ÉTAPE E : Succès ---
        echo "<script>
                alert('Compte créé avec succès ! Le gestionnaire de $sigle a été notifié.');
                window.location.href='../views/admin_dashboard.php';
              </script>";

    } catch (PDOException $e) {
        die("Erreur critique Base de données : " . $e->getMessage());
    }
}
?>
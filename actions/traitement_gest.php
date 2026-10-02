<?php
session_start();
require_once '../config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../libs/PHPMailer/Exception.php';
require '../libs/PHPMailer/PHPMailer.php';
require '../libs/PHPMailer/SMTP.php';

// Sécurité : seul l'admin central peut exécuter ce traitement
if (!isset($_SESSION['id_user']) || $_SESSION['rol'] !== 'admin') {
    header("Location: ../saisie/login.php");
    exit();
}

// Récupération et nettoyage des données du formulaire
$nom_gest = trim($_POST['nom_gest']);
$prenom_gest = trim($_POST['prenom_gest']);
$telephone_gest = trim($_POST['telephone_gest']);
$email_gest = trim($_POST['email_gest']);
$id_org = $_POST['id_org'];
$mdp = $_POST['mdp'];

// MODIFICATION 1 : L'identifiant utilisateur devient directement l'adresse email professionnelle
$iden = $email_gest; 

// Validation des données obligatoires
if (empty($nom_gest) || empty($email_gest) || empty($mdp) || empty($id_org)) {
    header("Location: ../saisie/ajouter_gestionnaire.php?error=Veuillez remplir tous les champs obligatoires.");
    exit();
}

try {
    $db->beginTransaction();

    // Hachage du mot de passe de manière sécurisée
    $mdp_hash = password_hash($mdp, PASSWORD_DEFAULT);

    // Insertion dans la table UTILISATEURS (Le rôle attribué est 'admin_local' ou 'gestionnaire' selon ta logique)
    $sql1 = "INSERT INTO UTILISATEURS (iden, mot_de_pas, rol, id_org, id_fonc) 
             VALUES (:iden, :mdp, 'admin_local', :id_o, NULL)";
    
    $stmt1 = $db->prepare($sql1);
    $stmt1->execute([
        ':iden' => $iden, // Contient l'email du gestionnaire
        ':mdp'  => $mdp_hash,
        ':id_o' => $id_org
    ]);

    // MODIFICATION 2 : Récupération de l'ID généré automatiquement par auto-incrément
    $id_user_reel = $db->lastInsertId();

    // Insertion dans la table GESTIONNAIRE liée à l'utilisateur
    $sql2 = "INSERT INTO GESTIONNAIRE (nom_gest, prenom_gest, telephone_gest, email_gest, id_user) 
             VALUES (:nom, :pre, :tel, :mail, :id_u)";
    
    $stmt2 = $db->prepare($sql2);
    $stmt2->execute([
        ':nom'  => $nom_gest,
        ':pre'  => $prenom_gest,
        ':tel'  => $telephone_gest,
        ':mail' => $email_gest,
        ':id_u' => $id_user_reel // Utilisation stricte de l'ID numérique
    ]);

    $db->commit();

    // --- ENVOI DE L'EMAIL VIA PHPMAILER ---
    $mail = new PHPMailer(true);

    try {
        // Configuration du serveur SMTP
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; 
        $mail->SMTPAuth   = true;
        $mail->Username   = 'awak37257@gmail.com';
        $mail->Password   = 'igbqjtuujphlryrf';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        // Destinataires
        $mail->setFrom('awak37257@gmail.com', 'Ministère des Affaires Étrangères');
        $mail->addAddress($email_gest, "$prenom_gest $nom_gest");

        // Contenu du courriel
        $mail->isHTML(true);
        $mail->Subject = "Création de votre compte gestionnaire";
        $mail->Body    = "
            <div style='font-family: sans-serif; color: #333;'>
                <h2 style='color: #0A2540;'>Bienvenue sur la plateforme du Ministère</h2>
                <p>Bonjour <strong>$prenom_gest $nom_gest</strong>,</p>
                <p>Votre compte gestionnaire a été créé avec succès par l'administrateur central.</p>
                <div style='background: #f4f4f4; padding: 15px; border-radius: 8px;'>
                    <p style='margin: 0;'><strong>Identifiant (Email) :</strong> $iden</p>
                    <p style='margin: 5px 0 0;'><strong>Mot de passe provisoire :</strong> $mdp</p>
                </div>
                <p>Veuillez vous connecter dès maintenant pour modifier votre mot de passe provisoire.</p>
                <br>
                <p style='font-size: 0.9rem;'>Cordialement,<br>L'Administration Centrale.</p>
            </div>";

        $mail->send();
    } catch (Exception $e) {
        // L'email échoue mais la transaction BDD est déjà validée (pas de blocage utilisateur)
        // error_log("Erreur d'envoi PHPMailer: {$mail->ErrorInfo}");
    }

    header("Location: ../views/admin_dashboard.php?msg=success");
    exit();

} catch (Exception $e) {
    if($db->inTransaction()) $db->rollBack();
    die("Erreur système : " . $e->getMessage()); 
}
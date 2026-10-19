<?php
session_start();
require_once '../config.php';
require_once '../includes/phone_numbers.php';

require '../libs/PHPMailer/Exception.php';
require '../libs/PHPMailer/PHPMailer.php';
require '../libs/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. RÉCUPÉRATION ET NETTOYAGE
    $matricule = strtoupper(trim($_POST['matricule']));
    $nom       = strtoupper(trim($_POST['nom']));
    $prenom    = ucwords(strtolower(trim($_POST['prenoms'])));
    
    $regionTelephone = strtoupper(trim((string)($_POST['indicatif'] ?? '')));
    $numeroTelephone = numero_telephone_e164((string)($_POST['telephone'] ?? ''), $regionTelephone);
    if ($numeroTelephone === null) {
        header('Location: ../saisie/ajouter_agent.php?error=' . urlencode('Le numéro de téléphone n’est pas valide pour le pays sélectionné.'));
        exit();
    }
    $contact_complet = $numeroTelephone;

    $date_naiss = $_POST['date_naissance']; 
    $genre      = $_POST['genre'];           
    $email      = trim($_POST['email']);
    $adresse    = trim($_POST['adresse']);

    $id_affec   = $_POST['id_affec'];  
    $id_org     = $_POST['id_org']; 

    $date_debut_auto = date('Y-m-d');

    // --- GESTION DE LA PHOTO ---
    $photo_nom = "default.png"; // Photo par défaut
    if (isset($_FILES['photo_agent']) && $_FILES['photo_agent']['error'] === 0) {
        $upload_dir = '../uploads/photos/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $ext = pathinfo($_FILES['photo_agent']['name'], PATHINFO_EXTENSION);
        $photo_nom = "IMG_" . $matricule . "." . $ext;
        move_uploaded_file($_FILES['photo_agent']['tmp_name'], $upload_dir . $photo_nom);
    }

    // 2. GÉNÉRATION DES ACCÈS
    $mdp_clair = bin2hex(random_bytes(4)); 
    $mdp_hash  = password_hash($mdp_clair, PASSWORD_DEFAULT);
    $id_user_gen = "USR-" . $matricule; 

    try {
        $db->beginTransaction();

        // 3. INSERTION DANS FONCTIONNAIRE
        // Note : J'ajoute le champ 'photo' s'il existe dans ta table
        $sqlFonc = "INSERT INTO FONCTIONNAIRE (
                        matri, nom_fonc, prenom_fonc, cont, 
                        date_naiss, gen, email_perso, adre, date_debut, photo_fonc
                    ) VALUES (
                        :mat, :nom, :pre, :con, :dob, :gen, :mail, :adr, :debut, :photo
                    )"; 

        $stmtFonc = $db->prepare($sqlFonc);
        $stmtFonc->execute([
            ':mat'   => $matricule,
            ':nom'   => $nom,
            ':pre'   => $prenom,
            ':con'   => $contact_complet,
            ':dob'   => $date_naiss,
            ':gen'   => $genre,
            ':mail'  => $email,
            ':adr'   => $adresse,
            ':debut' => $date_debut_auto,
            ':photo' => $photo_nom
        ]);

        // On récupère l'ID généré pour les liaisons suivantes
        $lastIdFonc = $db->lastInsertId();

        // 4. INSERTION DANS UTILISATEURS 
        $sqlUser = "INSERT INTO UTILISATEURS (id_user, iden, mot_de_pas, rol, id_org, id_fonc) 
                    VALUES (:id_u, :iden, :mdp, :rol, :id_o, :id_f)";
        
        $stmtUser = $db->prepare($sqlUser);
        $stmtUser->execute([
            ':id_u' => $id_user_gen,
            ':iden' => $matricule, 
            ':mdp'  => $mdp_hash,
            ':rol'  => 'AGENT',
            ':id_o' => $id_org,
            ':id_f' => $lastIdFonc
        ]);

        // 5. INSERTION DANS RECEVOIR
        // 5. INSERTION DANS RECEVOIR (Version mise à jour avec la ville)
        
        // A. On récupère d'abord la ville par défaut associée au poste choisi
        $stmtAff = $db->prepare("SELECT vil_affec FROM AFFECTATION WHERE id_affec = ?");
        $stmtAff->execute([$id_affec]);
        $infoPoste = $stmtAff->fetch();
        
        // On définit la ville (si elle n'est pas trouvée, on met une valeur par défaut)
        $villeParDefaut = $infoPoste['vil_affec'] ?? 'Non définie';

        // B. On insère la ligne dans RECEVOIR avec la colonne ville_affec
        $sqlRec = "INSERT INTO RECEVOIR (id_fonc, id_org, id_affec, date_debut, motif, ville_affec) 
                   VALUES (:id_f, :id_o, :id_a, :debut, :motif, :ville)";
        
        $stmtRec = $db->prepare($sqlRec);
        $stmtRec->execute([
            ':id_f'   => $lastIdFonc,
            ':id_o'   => $id_org,
            ':id_a'   => $id_affec,
            ':debut'  => $date_debut_auto,
            ':motif'  => 'Affectation initiale',
            ':ville'  => $villeParDefaut
        ]);

        $db->commit();

        // 6. ENVOI DU MAIL (Respect de la règle : Nom complet pour le ministère)
        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'awak37257@gmail.com'; 
                $mail->Password   = 'igbqjtuujphlryrf'; 
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;
                $mail->CharSet    = 'UTF-8';

                $mail->setFrom('drh@ministere.ci', 'Ministère des Affaires Étrangères');
                $mail->addAddress($email, "$nom $prenom");

                $mail->isHTML(true);
                $mail->Subject = "Vos accès au Portail Fonctionnaire";
                $mail->Body    = "
                <div style='font-family: sans-serif; max-width: 600px; margin: auto; border: 1px solid #eee; padding: 20px;'>
                    <h2 style='color: #0A2540;'>Bienvenue au Ministère</h2>
                    <p>Bonjour <strong>$prenom</strong>,</p>
                    <p>Votre compte a été créé. Voici vos accès pour vous connecter :</p>
                    <div style='background: #f4f4f4; padding: 15px; text-align: center;'>
                        <p>Identifiant : <strong>$matricule</strong></p>
                        <p>Mot de passe provisoire : <strong>$mdp_clair</strong></p>
                    </div>
                    <p>Veuillez changer votre mot de passe dès votre première connexion.</p>
                </div>";

                $mail->send();
            } catch (Exception $e) {
                // Erreur mail silencieuse pour l'utilisateur mais logguée
                error_log("Erreur mail : " . $mail->ErrorInfo);
            }
        }

        echo "<script>alert('Agent enregistré avec succès !'); window.location.href='../views/admin_dashboard.php';</script>";
    
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        // Gestion spécifique des doublons
        if (strpos($e->getMessage(), 'UNIQUE') !== false) {
            echo "<script>alert('Le matricule $matricule est déjà utilisé.'); window.history.back();</script>";
        } else {
            die("Erreur technique : " . $e->getMessage());
        }
    }
}
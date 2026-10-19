<?php
session_start();
require_once '../config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once '../includes/mailer.php';

// Sécurité : seul l'admin central peut exécuter ce traitement
if (!isset($_SESSION['id_user']) || $_SESSION['rol'] !== 'admin') {
    header("Location: ../saisie/login.php");
    exit();
}

if (!hash_equals((string)($_SESSION['csrf_gest'] ?? ''), (string)($_POST['csrf'] ?? ''))) {
    header('Location: ../saisie/ajouter_gestionnaire.php?error=' . urlencode('La session a expiré. Rechargez le formulaire.'));
    exit();
}

$idOrg = (int)($_POST['id_org'] ?? 0);
$idFonc = (int)($_POST['id_fonc'] ?? 0);
$mdp = (string)($_POST['mdp'] ?? '');

if (!$idOrg || !$idFonc || strlen($mdp) < 8) {
    header('Location: ../saisie/ajouter_gestionnaire.php?error=' . urlencode('Choisissez une organisation, un fonctionnaire et un mot de passe d’au moins 8 caractères.'));
    exit();
}

try {
    $db->beginTransaction();

    $stmtAgent = $db->prepare("SELECT f.id_fonc, f.matri, f.nom_fonc, f.prenom_fonc, f.email_perso, f.cont, r.id_org AS org_affectation
        FROM FONCTIONNAIRE f
        INNER JOIN RECEVOIR r ON r.id_fonc = f.id_fonc AND r.date_fin IS NULL
        WHERE f.id_fonc = ? AND r.id_org = ?
        LIMIT 1 FOR UPDATE");
    $stmtAgent->execute([$idFonc, $idOrg]);
    $agent = $stmtAgent->fetch(PDO::FETCH_ASSOC);
    if (!$agent) {
        throw new RuntimeException('Ce fonctionnaire n’est pas actuellement affecté à l’organisation choisie.');
    }
    $idOrg = (int)$agent['org_affectation'];
    $emailAgent = trim((string)($agent['email_perso'] ?? ''));
    if (!filter_var($emailAgent, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('La fiche de ce fonctionnaire doit contenir une adresse e-mail valide avant la création de son accès.');
    }

    $stmtCompte = $db->prepare('SELECT id_user, iden, rol, id_fonc
        FROM UTILISATEURS
        WHERE id_fonc = ?
        LIMIT 1 FOR UPDATE');
    $stmtCompte->execute([$idFonc]);
    $compte = $stmtCompte->fetch(PDO::FETCH_ASSOC);
    if ($compte && strtolower((string)$compte['rol']) !== 'agent') {
        throw new RuntimeException('Ce fonctionnaire possède déjà un compte avec un autre rôle.');
    }

    $stmtComptesEmail = $db->prepare('SELECT id_user, mot_de_pas FROM UTILISATEURS WHERE iden = ?');
    $stmtComptesEmail->execute([$emailAgent]);
    foreach ($stmtComptesEmail->fetchAll(PDO::FETCH_ASSOC) as $compteEmail) {
        if ((int)$compteEmail['id_user'] !== (int)($compte['id_user'] ?? 0)
            && password_verify($mdp, (string)$compteEmail['mot_de_pas'])) {
            throw new RuntimeException('Cette adresse e-mail est partagée. Choisissez un mot de passe provisoire différent de ceux des autres comptes utilisant cette adresse.');
        }
    }

    $mdp_hash = password_hash($mdp, PASSWORD_DEFAULT);
    $iden = $emailAgent;
    if ($compte) {
        $idUserLocal = (int)$compte['id_user'];
        $stmt = $db->prepare("UPDATE UTILISATEURS SET iden = ?, mot_de_pas = ?, rol = 'admin_local', id_org = ?, id_fonc = ? WHERE id_user = ?");
        $stmt->execute([$iden, $mdp_hash, $idOrg, $idFonc, $idUserLocal]);
    } else {
        $stmt = $db->prepare("INSERT INTO UTILISATEURS (iden, mot_de_pas, rol, id_org, id_fonc)
                              VALUES (?, ?, 'admin_local', ?, ?)");
        $stmt->execute([$iden, $mdp_hash, $idOrg, $idFonc]);
        $idUserLocal = (int)$db->lastInsertId();
    }

    $stmtGestionnaire = $db->prepare('SELECT id_gest FROM GESTIONNAIRE WHERE id_user = ? ORDER BY id_gest LIMIT 1');
    $stmtGestionnaire->execute([$idUserLocal]);
    $idGestionnaire = $stmtGestionnaire->fetchColumn();
    if ($idGestionnaire) {
        $stmtGestionnaire = $db->prepare('UPDATE GESTIONNAIRE
            SET nom_gest = ?, prenom_gest = ?, telephone_gest = ?, email_gest = ?
            WHERE id_gest = ?');
        $stmtGestionnaire->execute([
            $agent['nom_fonc'],
            $agent['prenom_fonc'],
            $agent['cont'] ?? null,
            $emailAgent,
            (int)$idGestionnaire,
        ]);
    } else {
        $stmtGestionnaire = $db->prepare('INSERT INTO GESTIONNAIRE
            (nom_gest, prenom_gest, telephone_gest, email_gest, id_user)
            VALUES (?, ?, ?, ?, ?)');
        $stmtGestionnaire->execute([
            $agent['nom_fonc'],
            $agent['prenom_fonc'],
            $agent['cont'] ?? null,
            $emailAgent,
            $idUserLocal,
        ]);
    }

    $db->commit();

    $mail = creerMailerMinistere();
    try {
        $mail->addAddress($emailAgent, trim($agent['prenom_fonc'] . ' ' . $agent['nom_fonc']));
        $mail->isHTML(true);
        $mail->Subject = 'Activation de votre accès administrateur local';
        $mail->Body = '<p>Bonjour ' . htmlspecialchars(trim($agent['prenom_fonc'] . ' ' . $agent['nom_fonc']), ENT_QUOTES, 'UTF-8') . ',</p>'
            . '<p>Votre compte fonctionnaire a été habilité comme administrateur local.</p>'
            . '<p>Identifiant : <strong>' . htmlspecialchars($iden, ENT_QUOTES, 'UTF-8') . '</strong></p>'
            . '<p>Mot de passe provisoire : <strong>' . htmlspecialchars($mdp, ENT_QUOTES, 'UTF-8') . '</strong></p>'
            . '<p>Veuillez modifier ce mot de passe dès votre première connexion.</p>';
        $mail->send();
    } catch (Exception $e) {
        error_log('Erreur mail admin local : ' . $mail->ErrorInfo);
    }

    header('Location: ../views/admin_dashboard.php?msg=success');
    exit();

} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('traitement_gest.php : ' . $e->getMessage());
    $message = $e instanceof RuntimeException
        ? $e->getMessage()
        : 'Impossible de créer cet accès. Vérifiez les informations et réessayez.';
    header('Location: ../saisie/ajouter_gestionnaire.php?error=' . urlencode($message));
    exit();
}
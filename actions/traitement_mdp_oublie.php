<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/mailer.php';

use PHPMailer\PHPMailer\Exception;

function retour_demande_reset(string $query = 'sent=1'): void
{
    header('Location: ../saisie/mot_de_passe_oublie.php?' . $query);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || !hash_equals((string)($_SESSION['csrf_password_reset_request'] ?? ''), (string)($_POST['csrf'] ?? ''))) {
    retour_demande_reset('error=1');
}

$identifier = trim((string)($_POST['identifier'] ?? ''));
if ($identifier === '' || strlen($identifier) > 255) {
    retour_demande_reset();
}

try {
    // Les anciennes fiches fonctionnaires peuvent ne pas encore avoir de compte utilisateur.
    // Un matricule exact, vérifié par e-mail, permet alors de créer le compte AGENT à réinitialiser.
    $stmtAgent = $db->prepare('SELECT f.id_fonc, f.matri, f.email_perso, r.id_org
        FROM FONCTIONNAIRE f
        LEFT JOIN RECEVOIR r ON r.id_fonc = f.id_fonc AND r.date_fin IS NULL
        WHERE f.matri = ?
        ORDER BY r.date_debut DESC
        LIMIT 1');
    $stmtAgent->execute([$identifier]);
    $agentSansCompte = $stmtAgent->fetch(PDO::FETCH_ASSOC);
    $matriculeExact = (bool)$agentSansCompte;
    if ($agentSansCompte) {
        $emailAgent = trim((string)($agentSansCompte['email_perso'] ?? ''));
        if (filter_var($emailAgent, FILTER_VALIDATE_EMAIL)) {
            $stmtCompteAgent = $db->prepare('SELECT id_user FROM UTILISATEURS WHERE id_fonc = ? LIMIT 1');
            $stmtCompteAgent->execute([(int)$agentSansCompte['id_fonc']]);
            if (!$stmtCompteAgent->fetchColumn()) {
                $db->beginTransaction();
                $motDePasseAleatoire = bin2hex(random_bytes(32));
                $motDePasseInutilisable = password_hash($motDePasseAleatoire, PASSWORD_DEFAULT);
                $stmtCreerCompte = $db->prepare("INSERT INTO UTILISATEURS (iden, mot_de_pas, rol, id_fonc, id_org)
                    VALUES (?, ?, 'AGENT', ?, ?)");
                $stmtCreerCompte->execute([
                    (string)$agentSansCompte['matri'],
                    $motDePasseInutilisable,
                    (int)$agentSansCompte['id_fonc'],
                    $agentSansCompte['id_org'] === null ? null : (int)$agentSansCompte['id_org'],
                ]);
                $idNouveauCompte = (int)$db->lastInsertId();
                $db->prepare('INSERT INTO HISTORIQUE (id_user, action_desc, dat_hist) VALUES (?, ?, NOW())')
                    ->execute([$idNouveauCompte, 'Création du compte fonctionnaire lors d’une demande de réinitialisation']);
                $db->commit();
            }
        }
    }

    $sqlComptes = "SELECT u.id_user, u.iden, u.rol, u.id_fonc,
            f.email_perso, f.nom_fonc, f.prenom_fonc,
            (SELECT g.email_gest FROM GESTIONNAIRE g WHERE g.id_user = u.id_user ORDER BY g.id_gest LIMIT 1) AS email_gest,
            (SELECT g.nom_gest FROM GESTIONNAIRE g WHERE g.id_user = u.id_user ORDER BY g.id_gest LIMIT 1) AS nom_gest,
            (SELECT g.prenom_gest FROM GESTIONNAIRE g WHERE g.id_user = u.id_user ORDER BY g.id_gest LIMIT 1) AS prenom_gest
        FROM UTILISATEURS u
        LEFT JOIN FONCTIONNAIRE f ON f.id_fonc = u.id_fonc";
    if ($matriculeExact) {
        $sqlComptes .= ' WHERE f.matri = :matricule ORDER BY u.id_user';
        $stmt = $db->prepare($sqlComptes);
        $stmt->execute([':matricule' => $identifier]);
    } else {
        $sqlComptes .= " WHERE u.iden = :login_email
            OR f.email_perso = :profile_email
            OR EXISTS (
                SELECT 1 FROM GESTIONNAIRE gx
                WHERE gx.id_user = u.id_user AND gx.email_gest = :manager_email
            )
            ORDER BY u.id_user";
        $stmt = $db->prepare($sqlComptes);
        $stmt->execute([
            ':login_email' => $identifier,
            ':profile_email' => $identifier,
            ':manager_email' => $identifier,
        ]);
    }
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($accounts as $account) {
        $email = trim((string)($account['email_perso'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = trim((string)($account['email_gest'] ?? ''));
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) && filter_var($account['iden'], FILTER_VALIDATE_EMAIL)) {
            $email = trim((string)$account['iden']);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            continue;
        }

        $idUser = (int)$account['id_user'];
        $stmtLimit = $db->prepare('SELECT COUNT(*) FROM PASSWORD_RESET_TOKENS WHERE id_user = ? AND date_creation >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)');
        $stmtLimit->execute([$idUser]);
        if ((int)$stmtLimit->fetchColumn() >= 3) {
            continue;
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $db->beginTransaction();
        $db->prepare('DELETE FROM PASSWORD_RESET_TOKENS WHERE id_user = ? AND date_creation < DATE_SUB(NOW(), INTERVAL 30 DAY)')
            ->execute([$idUser]);
        $db->prepare('UPDATE PASSWORD_RESET_TOKENS SET date_utilisation = NOW()
            WHERE id_user = ? AND date_utilisation IS NULL')
            ->execute([$idUser]);
        $db->prepare('INSERT INTO PASSWORD_RESET_TOKENS (id_user, token_hash, date_expiration) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))')
            ->execute([$idUser, $tokenHash]);
        $db->commit();

        $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        if (!preg_match('/\A[a-zA-Z0-9.-]+(?::\d{1,5})?\z/', $host)) {
            $host = 'localhost';
        }
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $scriptPath = str_replace('\\', '/', dirname(dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/fonctionnaire_ministere/actions/traitement_mdp_oublie.php'))));
        $basePath = rtrim($scriptPath, '/');
        $resetUrl = $scheme . '://' . $host . $basePath . '/saisie/reinitialiser_mot_de_passe.php?token=' . rawurlencode($token);

        $nom = trim((string)($account['prenom_fonc'] ?? '') . ' ' . (string)($account['nom_fonc'] ?? ''));
        if ($nom === '') {
            $nom = trim((string)($account['prenom_gest'] ?? '') . ' ' . (string)($account['nom_gest'] ?? ''));
        }
        $nom = $nom !== '' ? $nom : 'Utilisateur';

        try {
            $mail = creerMailerMinistere();
            $mail->addAddress($email, $nom);
            $mail->isHTML(true);
            $mail->Subject = 'Réinitialisation de votre mot de passe';
            $mail->Body = '<p>Bonjour ' . htmlspecialchars($nom, ENT_QUOTES, 'UTF-8') . ',</p>'
                . '<p>Une demande de réinitialisation du mot de passe de votre compte a été reçue.</p>'
                . '<p><a href="' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '">Choisir un nouveau mot de passe</a></p>'
                . '<p>Ce lien expire dans 30 minutes et ne peut servir qu’une seule fois.</p>'
                . '<p>Si vous n’êtes pas à l’origine de cette demande, ignorez ce message.</p>';
            $mail->AltBody = "Bonjour $nom,\n\nRéinitialisez votre mot de passe avec ce lien (valable 30 minutes, une seule utilisation) :\n$resetUrl\n\nSi vous n'êtes pas à l'origine de cette demande, ignorez ce message.";
            $mail->send();
        } catch (Exception $e) {
            error_log('Echec envoi réinitialisation mot de passe : ' . $e->getMessage());
        }
    }
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('traitement_mdp_oublie.php : ' . $e->getMessage());
}

retour_demande_reset();

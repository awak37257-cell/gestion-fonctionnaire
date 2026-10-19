<?php
session_start();
require_once __DIR__ . '/../config.php';

$token = trim((string)($_POST['token'] ?? ''));
$tokenUrl = preg_match('/\A[a-f0-9]{64}\z/i', $token) ? '?token=' . rawurlencode($token) : '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || !hash_equals((string)($_SESSION['csrf_password_reset'] ?? ''), (string)($_POST['csrf'] ?? ''))) {
    http_response_code(403);
    exit('Demande invalide. Rouvrez le lien reçu par e-mail.');
}

$password = (string)($_POST['password'] ?? '');
$confirmation = (string)($_POST['confirm_password'] ?? '');
if (strlen($password) < 8 || !hash_equals($password, $confirmation)) {
    header('Location: ../saisie/reinitialiser_mot_de_passe.php' . $tokenUrl . (str_contains($tokenUrl, '?') ? '&' : '?') . 'error=password');
    exit();
}
if (!preg_match('/\A[a-f0-9]{64}\z/i', $token)) {
    header('Location: ../saisie/reinitialiser_mot_de_passe.php?error=invalid');
    exit();
}

try {
    $db->beginTransaction();
    $stmt = $db->prepare('SELECT id_reset, id_user FROM PASSWORD_RESET_TOKENS
        WHERE token_hash = ? AND date_utilisation IS NULL AND date_expiration > NOW()
        LIMIT 1 FOR UPDATE');
    $stmt->execute([hash('sha256', $token)]);
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$reset) {
        $db->rollBack();
        header('Location: ../saisie/reinitialiser_mot_de_passe.php?error=invalid');
        exit();
    }

    $idUser = (int)$reset['id_user'];
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $db->prepare('UPDATE UTILISATEURS SET mot_de_pas = ? WHERE id_user = ?')->execute([$hash, $idUser]);
    $db->prepare('UPDATE PASSWORD_RESET_TOKENS SET date_utilisation = NOW()
        WHERE id_user = ? AND date_utilisation IS NULL')->execute([$idUser]);
    $db->prepare('INSERT INTO HISTORIQUE (id_user, action_desc, dat_hist) VALUES (?, ?, NOW())')
        ->execute([$idUser, 'Réinitialisation du mot de passe']);
    $db->commit();

    unset($_SESSION['csrf_password_reset']);
    header('Location: ../saisie/login.php?reset=success');
    exit();
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('traitement_reinitialiser_mdp.php : ' . $e->getMessage());
    header('Location: ../saisie/reinitialiser_mot_de_passe.php' . $tokenUrl . (str_contains($tokenUrl, '?') ? '&' : '?') . 'error=invalid');
    exit();
}

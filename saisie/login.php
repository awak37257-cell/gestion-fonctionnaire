<?php
session_start();
require_once '../config.php';

$error = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_input = isset($_POST['iden']) ? trim($_POST['iden']) : '';
    $pass_input = isset($_POST['password']) ? $_POST['password'] : '';

    try {
        $stmt = $db->prepare("
            SELECT u.*, o.sigle_org 
            FROM UTILISATEURS u
            LEFT JOIN ORGANISATION o ON u.id_org = o.id_org 
            WHERE u.iden = ?
        ");
        $stmt->execute([$user_input]);
        $comptes_trouves = $stmt->fetchAll();

        $u = null;

        foreach ($comptes_trouves as $compte) {
            $colonne_pwd = $compte['mot_de_pas'] ?? $compte['password'] ?? null;
            
            if ($colonne_pwd && password_verify($pass_input, $colonne_pwd)) {
                $u = $compte;
                break;
            }
        }

        if ($u) {
            session_regenerate_id(true);

            $_SESSION['id_user'] = $u['id_user'] ?? $u['id'];
            $_SESSION['rol']     = $u['rol'];
            $_SESSION['nom']     = $u['iden'];
            $_SESSION['id_fonc'] = $u['id_fonc'] ?? null;
            $_SESSION['id_org']  = $u['id_org']; 
            $_SESSION['sigle']   = $u['sigle_org'];

            $action = "Connexion à la plateforme";
            $log = $db->prepare("INSERT INTO HISTORIQUE (id_user, action_desc, dat_hist) VALUES (?, ?, NOW())");
            $log->execute([$_SESSION['id_user'], $action]);

            if (in_array($u['rol'], ['admin', 'admin_local', 'gestionnaire'])) {
                header("Location: ../views/admin_dashboard.php");
            } else {
                header("Location: ../views/profil_agent.php?id=" . ($u['id_fonc'] ?? $u['iden']));
            }
            exit();

        } else { 
            sleep(1); 
            $error = "Identifiants invalides."; 
        }
    } catch (Exception $e) {
        $error = "Erreur système : " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Sécurisée | Ministère de la Fonction Publique</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../public/css/style.css">
</head>
<body style="min-height: 100vh; display: flex; align-items: center; justify-content: center; background: radial-gradient(circle at top left, #0A2540 0%, #051a2c 60%, #0A2540 100%); padding: 1.5rem; position: relative;">

    <!-- Back Home Link -->
    <a href="../index.php" class="btn-custom btn-outline" style="position: absolute; top: 24px; left: 24px; color: white; border-color: rgba(255,255,255,0.2); background: rgba(255,255,255,0.05);">
        <i class="fa-solid fa-arrow-left"></i> Retour à l'accueil
    </a>

    <div class="glass-card animate-fade-in" style="width: 100%; max-width: 440px; padding: 2.8rem 2.2rem; background: rgba(255, 255, 255, 0.96); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35); text-align: center;">
        
        <div style="width: 76px; height: 76px; margin: 0 auto 1.2rem; border-radius: 50%; background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%); display: flex; align-items: center; justify-content: center; color: var(--secondary); font-size: 2rem; border: 3px solid var(--secondary); box-shadow: var(--shadow-glow);">
            <i class="fa-solid fa-shield-halved"></i>
        </div>

        <h2 style="color: var(--primary); font-size: 1.6rem; font-weight: 800; margin-bottom: 0.2rem;">Authentification</h2>
        <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 2rem; font-weight: 500;">
            Ministère des Affaires Étrangères
        </p>

        <?php if($error): ?>
            <div class="toast toast-error" style="position: static; margin-bottom: 1.5rem; animation: none;">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group" style="text-align: left;">
                <label class="form-label" for="iden">Identifiant ou Matricule</label>
                <div style="position: relative;">
                    <i class="fa-solid fa-user" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                    <input type="text" id="iden" name="iden" class="form-control-custom" style="padding-left: 45px;" placeholder="Ex: ADM-2026" required>
                </div>
            </div>

            <div class="form-group" style="text-align: left; margin-bottom: 1.8rem;">
                <label class="form-label" for="password">Mot de passe</label>
                <div style="position: relative;">
                    <i class="fa-solid fa-lock" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                    <input type="password" id="password" name="password" class="form-control-custom" style="padding-left: 45px; padding-right: 45px;" placeholder="••••••••" required>
                    <button type="button" data-toggle-password="password" style="position: absolute; right: 14px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-muted); cursor: pointer; padding: 4px;">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-custom btn-primary" style="width: 100%; justify-content: center; padding: 0.85rem; font-size: 0.95rem;">
                <i class="fa-solid fa-right-to-bracket"></i> Connexion au Portail
            </button>
        </form>

        <div style="margin-top: 2rem; padding-top: 1.2rem; border-top: 1px solid var(--border-color); font-size: 0.8rem; color: var(--text-muted);">
            <i class="fa-solid fa-lock" style="color: var(--success); margin-right: 4px;"></i> Connexion sécurisée SSL/TLS (256-bit)
        </div>
    </div>

    <script src="../public/js/app.js"></script>
</body>
</html>
<?php
session_start();
require_once '../config.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Création de Compte Administration | Ministère</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../public/css/style.css">
</head>
<body style="min-height: 100vh; display: flex; align-items: center; justify-content: center; background: radial-gradient(circle at top right, #0A2540 0%, #051a2c 60%, #0A2540 100%); padding: 1.5rem; position: relative;">

    <a href="../saisie/login.php" class="btn-custom btn-outline" style="position: absolute; top: 24px; left: 24px; color: white; border-color: rgba(255,255,255,0.2); background: rgba(255,255,255,0.05);">
        <i class="fa-solid fa-arrow-left"></i> Retour Connexion
    </a>

    <div class="glass-card animate-fade-in" style="width: 100%; max-width: 480px; padding: 0; background: rgba(255, 255, 255, 0.96); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35); overflow: hidden;">
        
        <div style="background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%); padding: 2.2rem; text-align: center; color: white;">
            <div style="width: 65px; height: 65px; margin: 0 auto 0.8rem; border-radius: 50%; background: rgba(255,255,255,0.1); border: 2px solid var(--secondary); display: flex; align-items: center; justify-content: center; color: var(--secondary); font-size: 1.6rem;">
                <i class="fa-solid fa-user-shield"></i>
            </div>
            <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0;">Création de Compte</h2>
            <p style="font-size: 0.85rem; color: rgba(255,255,255,0.8); margin: 0;">Espace Administrateur Local & Gestionnaire</p>
        </div>

        <div style="padding: 2.2rem;">
            <?php if(isset($_GET['error'])): ?>
                <div class="toast toast-error" style="position: static; margin-bottom: 1.5rem; animation: none;">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span>
                        <?php 
                            switch($_GET['error']) {
                                case 'exists':   echo "Cet identifiant possède déjà un compte."; break;
                                case 'wrongkey': echo "Clé d'activation incorrecte."; break;
                                case 'format':   echo "Format d'identifiant incorrect (gestionnaire_NOM)."; break;
                                default:         echo "Une erreur est survenue lors de l'enregistrement.";
                            }
                        ?>
                    </span>
                </div>
            <?php endif; ?>

            <form action="../actions/traitement_register.php" method="POST">
                
                <div class="form-group" style="text-align: left;">
                    <label class="form-label" for="sigle">Sigle de l'Organisation</label>
                    <div style="position: relative;">
                        <i class="fa-solid fa-building" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                        <input type="text" id="sigle" name="sigle" class="form-control-custom" style="padding-left: 45px;" placeholder="Ex: MAE ou DGI" required>
                    </div>
                </div>

                <div class="form-group" style="text-align: left;">
                    <label class="form-label" for="email">Email du Gestionnaire Local</label>
                    <div style="position: relative;">
                        <i class="fa-solid fa-at" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                        <input type="email" id="email" name="email" class="form-control-custom" style="padding-left: 45px;" placeholder="destinataire@ministere.ci" required>
                    </div>
                </div>

                <div class="form-group" style="text-align: left; margin-bottom: 1.8rem;">
                    <label class="form-label" for="password">Mot de passe</label>
                    <div style="position: relative;">
                        <i class="fa-solid fa-key" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                        <input type="password" id="password" name="password" class="form-control-custom" style="padding-left: 45px; padding-right: 45px;" placeholder="Minimum 8 caractères" required>
                        <button type="button" data-toggle-password="password" style="position: absolute; right: 14px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-muted); cursor: pointer;">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-custom btn-primary" style="width: 100%; justify-content: center; padding: 0.85rem; font-size: 0.95rem;">
                    <i class="fa-solid fa-paper-plane"></i> Enregistrer et Notifier le Gestionnaire
                </button>
            </form>
        </div>
    </div>

    <script src="../public/js/app.js"></script>
</body>
</html>

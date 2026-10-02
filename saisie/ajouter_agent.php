<?php
session_start();
require_once '../config.php';

// Protection classique
if (!isset($_SESSION['rol'])) {
    header("Location:../saisie/login.php");
    exit();
}

$user_role = $_SESSION['rol'];
$user_org_id = $_SESSION['id_org'] ?? null;

// Si c'est l'admin global, on récupère toutes les organisations pour le select
$organisations = [];
if ($user_role === 'admin') {
    $organisations = $db->query("SELECT id_org, nom_org FROM ORGANISATION ORDER BY nom_org ASC")->fetchAll();
} else {
    // Si c'est un admin local, on récupère juste le nom de la sienne pour l'affichage
    $stmt = $db->prepare("SELECT nom_org FROM ORGANISATION WHERE id_org = ?");
    $stmt->execute([$user_org_id]);
    $ma_structure = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ministère | Nouvel Agent</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #0A2540;
            --primary-dark: #051a2c;
            --primary-light: #1a3d5c;
            --secondary: #C6A43F;
            --secondary-dark: #a8892c;
            --success: #27ae60;
            --bg: #F8FAFC;
            --text: #1E293B;
            --text-light: #64748B;
            --border: #E2E8F0;
            --white: #FFFFFF;
            --shadow: 0 20px 35px -10px rgba(0, 0, 0, 0.08);
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.04);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body { 
            font-family: 'Poppins', sans-serif; 
            background: linear-gradient(135deg, var(--bg) 0%, #F1F5F9 100%);
            padding: 40px 20px;
            min-height: 100vh;
        }

        .form-container { 
            background: var(--white); 
            width: 100%;
            max-width: 720px; 
            margin: 0 auto;
            border-radius: 28px; 
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .form-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            padding: 28px 32px;
            text-align: center;
            position: relative;
        }

        .form-header::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0; height: 4px;
            background: linear-gradient(90deg, var(--secondary), var(--secondary-dark), var(--secondary));
        }

        h2 { 
            color: var(--white); 
            margin: 0;
            font-weight: 600;
            font-size: 1.6rem;
            display: flex; align-items: center; justify-content: center; gap: 14px;
        }

        h2 i { color: var(--secondary); font-size: 1.8rem; }

        .form-subtitle {
            color: rgba(255,255,255,0.8);
            font-size: 0.8rem; margin-top: 8px; letter-spacing: 0.5px;
        }

        .form-body { padding: 32px; }

        .form-group { margin-bottom: 22px; }

        label { 
            display: block;
            font-weight: 500; color: var(--text);
            margin-bottom: 8px; font-size: 0.8rem; letter-spacing: 0.3px;
        }

        label i { color: var(--secondary); width: 20px; margin-right: 6px; }

        input, select { 
            width: 100%; padding: 12px 16px; 
            border: 2px solid var(--border); 
            border-radius: 14px; 
            transition: all 0.3s ease;
            font-size: 0.9rem;
            font-family: 'Poppins', sans-serif;
            background: var(--white);
        }

        input:focus, select:focus { 
            border-color: var(--secondary);
            outline: none;
            box-shadow: 0 0 0 4px rgba(198, 164, 63, 0.1);
        }

        .flex-group { display: flex; gap: 20px; }
        .flex-group div { flex: 1; }

        .tel-wrapper {
            display: flex; border: 2px solid var(--border);
            border-radius: 14px; overflow: hidden; background: var(--white);
        }

        .indicatif-select {
            width: 110px; border: none !important; background: #F8FAFC !important;
            border-right: 2px solid var(--border) !important; border-radius: 0 !important;
            padding: 12px 8px !important; font-weight: 500;
        }

        .tel-input { flex: 1; border: none !important; padding: 12px !important; }

        button { 
            width: 100%; padding: 16px; 
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white; border: none; border-radius: 16px; 
            cursor: pointer; font-size: 1rem; font-weight: 600;
            transition: all 0.3s ease; margin-top: 15px;
            display: flex; align-items: center; justify-content: center; gap: 12px;
        }

        button:hover { 
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(10, 37, 64, 0.3);
        }

        .footer-text { 
            text-align: center; padding: 20px 32px;
            font-size: 0.7rem; color: var(--text-light);
            border-top: 1px solid var(--border); background: var(--bg);
        }

        small { display: block; margin-top: 6px; font-size: 0.7rem; color: var(--text-light); }
        
        /* Style spécifique pour le champ fichier */
        input[type="file"] {
            padding: 8px;
            background: #fdfdfd;
            cursor: pointer;
        }

        @media (max-width: 640px) { 
            .flex-group { flex-direction: column; gap: 0; }
        }
    </style>
</head>
<body>

<?php include '../views/includes/navbar.php'; ?>

<div class="form-container">
    <div class="form-header">
        <h2><i class="fa-solid fa-user-plus"></i> Nouvel Agent</h2>
        <div class="form-subtitle">Remplissez le formulaire ci-dessous</div>
    </div>
    
    <div class="form-body">
        <!-- Ajout de enctype pour permettre l'envoi de la photo -->
        <form action="../actions/traitement_agent.php" method="POST" enctype="multipart/form-data">
            
            <div class="flex-group">
                <div class="form-group">
                    <label><i class="fa-solid fa-id-card"></i> Numéro de Matricule</label>
                    <input type="text" name="matricule" placeholder="Ex: 584930Z" required>
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-camera"></i> Photo de profil</label>
                    <input type="file" name="photo_agent" accept="image/*">
                    <small>JPG, PNG (Max 2Mo)</small>
                </div>
            </div>

            <div class="flex-group">
                <div class="form-group">
                    <label><i class="fa-solid fa-user"></i> Nom</label>
                    <input type="text" name="nom" placeholder="KOFFI" required>
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-user"></i> Prénoms</label>
                    <input type="text" name="prenoms" placeholder="Jean-Marc" required>
                </div>
            </div>

            <div class="form-group">
                <label><i class="fa-solid fa-sitemap"></i> Organisation d'affectation :</label>
                <?php if ($user_role === 'admin'): ?>
                    <select name="id_org" id="id_org" required>
                        <option value="">-- Choisir une direction --</option>
                        <?php foreach ($organisations as $org): ?>
                            <option value="<?= $org['id_org'] ?>"><?= htmlspecialchars($org['nom_org']) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <div style="padding:12px 16px; background:#F1F5F9; border-radius:14px; border: 2px solid var(--border); font-size: 0.9rem; font-weight: 600;">
                        <?= htmlspecialchars($ma_structure['nom_org']) ?>
                    </div>
                    <input type="hidden" name="id_org" id="id_org" value="<?= $user_org_id ?>">
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label><i class="fa-solid fa-graduation-cap"></i> Grade :</label>
                <select name="id_affec" id="id_grade" required>
                    <option value="">-- Sélectionnez d'abord une direction --</option>
                </select>
            </div>

            <div class="form-group">
                <label><i class="fa-solid fa-phone"></i> Contact Téléphonique</label>
                <div class="tel-wrapper">
                    <select name="indicatif" id="indicatif" class="indicatif-select">
                        <option value="+225" selected>🇨🇮 (+225)</option>
                        <option value="+221">🇸🇳 (+221)</option>
                        <option value="+223">🇲🇱 (+223)</option>
                        <option value="+226">🇧🇫 (+226)</option>
                    </select>
                    <input type="text" name="telephone" id="telephone" class="tel-input" placeholder="0708091011" required>
                </div>
            </div>

            <div class="form-group">
                <label><i class="fa-solid fa-envelope"></i> Email Pro</label>
                <input type="email" name="email" placeholder="agent@ministere.ci">
            </div>

            <div class="flex-group">
                <div class="form-group">
                    <label><i class="fa-solid fa-cake-candles"></i> Date de Naissance</label>
                    <input type="date" name="date_naissance" required>
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-venus-mars"></i> Genre</label>
                    <select name="genre" required>
                        <option value="">-- Choisir --</option>
                        <option value="M">Masculin</option>
                        <option value="F">Féminin</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label><i class="fa-solid fa-location-dot"></i> Adresse de Résidence</label>
                <input type="text" name="adresse" placeholder="Abidjan, Cocody" required>
            </div>

            <button type="submit">
                <i class="fa-solid fa-save"></i> Enregistrer l'Agent
            </button>
        </form>
    </div>
    
    <div class="footer-text">
        © 2026 Ministère de la Fonction Publique
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const orgInput = document.getElementById('id_org');
    const gradeSelect = document.getElementById('id_grade');

    function chargerGrades(id_org) {
        if (!id_org) return;
        gradeSelect.innerHTML = '<option value="">Chargement...</option>';
        const formData = new FormData();
        formData.append('id_org', id_org);

        fetch('get_grades.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            gradeSelect.innerHTML = data;
        })
        .catch(error => {
            console.error('Erreur:', error);
            gradeSelect.innerHTML = '<option value="">Erreur de chargement</option>';
        });
    }

    if (orgInput.tagName === 'SELECT') {
        orgInput.addEventListener('change', function() {
            chargerGrades(this.value);
        });
    }

    if (orgInput.value) {
        chargerGrades(orgInput.value);
    }
});
</script>
</body>
</html>
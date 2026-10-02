<?php 
require_once '../config.php'; // ← DÉPLACÉ ICI : AVANT TOUT CODE HTML
$id_agent_retour = $_GET['id_agent'] ?? 0; 
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Saisie Diplôme | Ministère</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { 
            --primary: #0A2540;
            --primary-dark: #051a2c;
            --primary-light: #1a3d5c;
            --success: #10B981;
            --success-dark: #059669;
            --accent: #F59E0B;
            --bg: #F8FAFC;
            --text: #1E293B;
            --text-light: #64748B;
            --border: #E2E8F0;
            --white: #FFFFFF;
            --shadow: 0 20px 35px -10px rgba(0, 0, 0, 0.08);
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.04);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body { 
            font-family: 'Poppins', sans-serif; 
            background: linear-gradient(135deg, var(--bg) 0%, #F1F5F9 100%);
            margin: 0;
            padding-top: 100px;
            min-height: 100vh;
        }

        .container { 
            display: flex; 
            justify-content: center; 
            align-items: center;
            padding: 20px;
        }

        .form-container { 
            background: var(--white); 
            width: 100%; 
            max-width: 680px; 
            padding: 0;
            border-radius: 28px; 
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .form-container:hover {
            box-shadow: 0 25px 45px -12px rgba(0, 0, 0, 0.15);
        }

        .form-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            padding: 28px 32px;
            text-align: center;
            position: relative;
        }

        .form-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--success), var(--accent), var(--success));
        }

        h2 { 
            color: var(--white); 
            margin: 0;
            font-weight: 600;
            font-size: 1.6rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 14px;
        }

        h2 i {
            color: var(--accent);
            font-size: 1.8rem;
        }

        .form-subtitle {
            color: rgba(255,255,255,0.8);
            font-size: 0.8rem;
            margin-top: 8px;
            letter-spacing: 0.5px;
        }

        .form-body {
            padding: 32px;
        }

        .form-group { 
            margin-bottom: 24px; 
        }

        /* Style pour masquer le champ personnalisé au départ */
        .form-group-custom {
            display: none;
            margin-top: 12px;
            animation: fadeIn 0.4s ease forwards;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        label { 
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 0.8rem;
            color: var(--text);
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }

        label i {
            color: var(--accent);
            width: 20px;
            margin-right: 6px;
        }

        input, select { 
            width: 100%; 
            padding: 12px 16px; 
            border: 2px solid var(--border); 
            border-radius: 14px; 
            font-size: 0.9rem;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
            background: var(--white);
        }

        input:hover, select:hover {
            border-color: var(--accent);
        }

        input:focus, select:focus { 
            outline: none; 
            border-color: var(--success);
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .file-drop-area {
            position: relative;
            display: flex;
            align-items: center;
            padding: 18px 20px;
            border: 2px dashed var(--border);
            border-radius: 16px;
            background: var(--bg);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .file-drop-area:hover { 
            border-color: var(--accent); 
            background: #FFFBEB;
            transform: translateY(-2px);
        }

        .file-drop-area i { 
            font-size: 1.8rem; 
            color: var(--accent); 
            margin-right: 15px; 
        }

        .file-msg { 
            font-size: 0.85rem; 
            color: var(--text-light);
            font-weight: 500;
        }

        input[type="file"] { 
            position: absolute; 
            left: 0; 
            top: 0; 
            height: 100%; 
            width: 100%;
            opacity: 0; 
            cursor: pointer;
        }

        button { 
            width: 100%; 
            padding: 16px; 
            background: linear-gradient(135deg, var(--success) 0%, var(--success-dark) 100%);
            color: white; 
            border: none; 
            border-radius: 16px; 
            cursor: pointer; 
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            position: relative;
            overflow: hidden;
        }

        button::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        button:hover::before {
            width: 300px;
            height: 300px;
        }

        button:hover { 
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(16, 185, 129, 0.3);
        }

        button:active {
            transform: translateY(0);
        }

        @media (max-width: 640px) { 
            .grid-2 {
                grid-template-columns: 1fr;
                gap: 0;
            }
            .form-body {
                padding: 24px;
            }
            .form-header {
                padding: 22px 20px;
            }
            h2 {
                font-size: 1.3rem;
            }
            h2 i {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>

<?php include '../views/includes/navbar.php'; ?>

<div class="container">
    <div class="form-container">
        <div class="form-header">
            <h2>
                <i class="fa-solid fa-graduation-cap"></i> 
                Nouveau Diplôme
            </h2>
            <div class="form-subtitle">Enregistrement des qualifications académiques</div>
        </div>

        <div class="form-body">
            <form action="../actions/traitement_diplome.php" method="POST" enctype="multipart/form-data">
                
                <div class="form-group">
    <label><i class="fa-solid fa-user-tie"></i> Agent Titulaire</label>
    
    <?php
    // Détection automatique de l'ID (regarde 'id', puis 'id_agent', puis 'id_fonc')
    $id_agent = $_GET['id'] ?? $_GET['id_agent'] ?? $_GET['id_fonc'] ?? 0;
    $id_agent = (int)$id_agent;

    $agent_diplome = null;
    if ($id_agent > 0) {
        // Récupération ciblée de l'agent actuel
        $stmt_agent = $db->prepare("SELECT id_fonc, nom_fonc, prenom_fonc, matri FROM FONCTIONNAIRE WHERE id_fonc = ?");
        $stmt_agent->execute([$id_agent]);
        $agent_diplome = $stmt_agent->fetch();
    }
    ?>

    <?php if ($agent_diplome): ?>
        <input type="hidden" name="id_fonc" value="<?= $agent_diplome['id_fonc']; ?>">
        
        <div style="background-color: #E2E8F0; padding: 12px 14px; border-radius: 10px; font-weight: 600; color: #0A2540; border: 2px solid #CBD5E1; display: flex; align-items: center; gap: 10px;">
            <i class="fa-solid fa-id-card" style="color: #D4AF37;"></i>
            <?= strtoupper($agent_diplome['nom_fonc']) . " " . $agent_diplome['prenom_fonc'] . " (" . $agent_diplome['matri'] . ")"; ?>
        </div>
    <?php else: ?>
        <div style="color: #EF4444; font-weight: 600; padding: 12px; background: #FEE2E2; border-radius: 10px;">
            <i class="fa-solid fa-exclamation-triangle"></i> Erreur : Impossible de lier le diplôme. L'identifiant de l'agent est manquant dans l'URL.
            <br><span style="font-size: 0.8rem; font-weight: normal; color: #7F1D1D;">(Vérifiez que votre bouton ressemble à : `ajouter_diplome.php?id=<?= $_GET['id'] ?? 'ID' ?>`)</span>
        </div>
    <?php endif; ?>
</div>

                <div class="form-group">
                    <label><i class="fa-solid fa-certificate"></i> Désignation du Diplôme</label>
                    <input type="text" name="libelle" placeholder="Ex: Master Professionnel en Informatique" required>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label><i class="fa-solid fa-layer-group"></i> Niveau</label>
                        <select name="niveau" id="select-niveau" required>
                            <option value="CEPC">CEPC</option>
                            <option value="BEPC">BEPC</option>
                            <option value="BAC">BAC</option>
                            <option value="BAC+2">BTS / BAC+2</option>
                            <option value="BAC+3">Licence / BAC+3</option>
                            <option value="BAC+5">Master / BAC+5</option>
                            <option value="Doctorat">Doctorat</option>
                            <option value="Autre">Autre...</option>
                        </select>

                        <div class="form-group-custom" id="custom-niveau-container">
                            <input type="text" name="niveau_custom" id="niveau-custom" placeholder="Précisez votre niveau (Ex: BAC+4)">
                        </div>
                    </div>
                    <div class="form-group">
                        <label><i class="fa-solid fa-calendar-alt"></i> Année d'Obtention</label>
                        <input type="number" name="annee" value="2026" required>
                    </div>
                </div>

                <div class="form-group">
                    <label><i class="fa-solid fa-file-pdf"></i> Scan du diplôme</label>
                    <div class="file-drop-area">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        <span class="file-msg" id="txt-fich">Cliquez ou glissez pour charger le fichier...</span>
                        <input type="file" name="scan_diplome" accept=".pdf,image/*">
                    </div>
                </div>

                <button type="submit">
                    <i class="fa-solid fa-check-double"></i> Enregistrer et Archiver
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    // Écouteur sur la liste déroulante Niveau
    const selectNiveau = document.getElementById('select-niveau');
    const customContainer = document.getElementById('custom-niveau-container');
    const inputCustom = document.getElementById('niveau-custom');

    selectNiveau.addEventListener('change', function() {
        if (this.value === 'Autre') {
            customContainer.style.display = 'block';
            inputCustom.setAttribute('required', 'required');
            inputCustom.focus();
        } else {
            customContainer.style.display = 'none';
            inputCustom.removeAttribute('required');
            inputCustom.value = ''; // Réinitialise la valeur si l'utilisateur change d'avis
        }
    });

    // Gestion visuelle du fichier chargé
    const fileInput = document.querySelector('input[type="file"]');
    const fileMsg = document.getElementById('txt-fich');
    
    if(fileInput) {
        fileInput.addEventListener('change', function() {
            if(this.files.length > 0) {
                const fileName = this.files[0].name;
                const fileSize = (this.files[0].size / 1024).toFixed(1);
                fileMsg.innerHTML = `<i class="fa-solid fa-check-circle" style="color:#10B981"></i> ${fileName} (${fileSize} Ko)`;
            } else {
                fileMsg.innerHTML = '<i class="fa-solid fa-cloud-arrow-up"></i> Cliquez ou glissez pour charger le fichier...';
            }
        });
    }
</script>

</body>
</html>
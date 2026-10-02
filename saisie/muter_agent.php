<?php
require_once '../config.php';

// 1. Récupération de l'ID de l'agent (on accepte 'id' ou 'id_agent')
$id_a_muter = $_GET['id'] ?? $_GET['id_agent'] ?? null;

if (!$id_a_muter) {
    die("Erreur : Aucun agent sélectionné.");
}

// 2. Récupération des infos de l'agent et son poste actuel
$stmt = $db->prepare("
    SELECT f.id_fonc,matri, f.nom_fonc, f.prenom_fonc, a.gra_post, a.vil_affec, o.nom_org 
FROM FONCTIONNAIRE f 
LEFT JOIN RECEVOIR r ON f.id_fonc = r.id_fonc
LEFT JOIN AFFECTATION a ON r.id_affec = a.id_affec
LEFT JOIN ORGANISATION o ON r.id_org = o.id_org
WHERE f.id_fonc = ?
");
$stmt->execute([$id_a_muter]);
$agent = $stmt->fetch();

if (!$agent) {
    die("Erreur : Agent introuvable.");
}

// 3. Récupération de la liste des directions pour le choix de la mutation
$query_org = $db->query("SELECT id_org, nom_org FROM ORGANISATION ORDER BY nom_org ASC");
$organisations = $query_org->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>SICP | Mutation - <?= htmlspecialchars($agent['nom_fonc'] ?? 'Agent'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { 
            --primary: #0A2540; 
            --accent: #D4AF37; 
            --bg: #F8FAFC; 
            --white: #FFFFFF;
            --text: #1E293B;
            --border: #E2E8F0;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Poppins', sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px 20px; 
        }
        
        .container { 
            background: var(--white); 
            border-radius: 28px; 
            width: 100%;
            max-width: 700px; 
            overflow: hidden; 
            box-shadow: 0 30px 60px rgba(0,0,0,0.2); 
        }

        /* -- BRAND HEADER INSTITUTIONNEL -- */
        .form-header { 
            background: var(--primary); 
            padding: 30px; 
            text-align: center; 
            color: white; 
            border-bottom: 3px solid var(--accent);
        }
        .brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.1);
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: var(--accent);
            margin-bottom: 12px;
        }
        .form-header h2 {
            font-size: 1.5rem;
            margin-bottom: 5px;
        }

        .form-body { padding: 35px; display: flex; flex-direction: column; gap: 20px; }

        /* Bloc Récapitulatif Situation Actuelle */
        .current-info { 
            background: #F1F5F9; 
            padding: 18px 20px; 
            border-radius: 16px; 
            border-left: 4px solid var(--accent);
            font-size: 0.88rem; 
            color: var(--text);
        }
        .current-info-title {
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.75rem;
            color: #64748B;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .current-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .form-group { margin-bottom: 18px; display: flex; flex-direction: column; }
        label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 0.8rem; text-transform: uppercase; color: #64748B; }
        label i { color: var(--accent); margin-right: 5px; }
        
        select, input, textarea { 
            width: 100%; 
            padding: 12px 14px; 
            border: 2px solid var(--border); 
            border-radius: 10px; 
            font-family: inherit; 
            font-size: 0.9rem; 
            background-color: var(--white);
        }
        select:focus, input:focus, textarea:focus { 
            outline: none; 
            border-color: var(--accent); 
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.15); 
        }

        .btn-box { display: flex; gap: 15px; justify-content: flex-end; margin-top: 10px; }
        .btn-submit { 
            flex: 1;
            padding: 14px; 
            background: var(--primary); 
            color: white; 
            border: none; 
            border-radius: 12px; 
            font-weight: 700; 
            font-size: 0.95rem;
            cursor: pointer; 
            transition: all 0.3s; 
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-submit:hover { opacity: 0.95; transform: translateY(-2px); }
        .btn-cancel { 
            padding: 14px 24px; 
            background: #E2E8F0; 
            color: #64748B; 
            border-radius: 12px; 
            font-weight: 700; 
            text-decoration: none; 
            text-align: center; 
            transition: 0.3s; 
        }
        .btn-cancel:hover { background: #CBD5E1; }
    </style>
</head>
<body>

<div class="container">
    <!-- EN-TÊTE D'IDENTITÉ APPLICATIVE -->
    <div class="form-header">
        <div class="brand-badge">
            <i class="fa-solid fa-building-columns"></i>
            <span>Ministère des Affaires Etrangères</span>
        </div>
        <h2><i class="fa-solid fa-route"></i> Ordre de Mutation</h2>
    </div>

    <div class="form-body">
        <!-- RAPPEL DE LA SITUATION ACTUELLE -->
        <div class="current-info">
            <div class="current-info-title">
                <i class="fa-solid fa-circle-info"></i> Situation Actuelle de l'Agent
            </div>
            <div class="current-info-grid">
                <div><strong>Agent :</strong> <?= htmlspecialchars($agent['nom_fonc'] . ' ' . $agent['prenom_fonc']); ?></div>
                <div><strong>Matricule :</strong> <?= htmlspecialchars($agent['matri'] ?? 'N/A'); ?></div>
                <div><strong>Poste actuel :</strong> <?= htmlspecialchars($agent['gra_post'] ?? 'N/A'); ?></div>
                <div><strong>Structure :</strong> <?= htmlspecialchars($agent['nom_org'] ?? 'N/A'); ?></div>
            </div>
        </div>

        <!-- FORMULAIRE DE MUTATION -->
        <form action="../actions/traitement_mutation.php" method="POST">
            <input type="hidden" name="id_fonc" value="<?= $agent['id_fonc']; ?>">

            <div class="form-group">
                <label><i class="fa-solid fa-sitemap"></i> Nouvelle Organisation de destination *</label>
                <select name="id_org_dest" required id="org_select">
                    <option value="">-- Choisir la nouvelle organisation --</option>
                    <?php foreach($organisations as $org): ?>
                        <option value="<?= $org['id_org']; ?>"><?= htmlspecialchars($org['nom_org']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label><i class="fa-solid fa-award"></i> Nouveau Grade / Poste *</label>
                <select name="id_affec_dest" id="grade_select" required>
                    <option value="">Sélectionnez d'abord une organisation</option>
                </select>
            </div>

            <div class="form-group">
                <label><i class="fa-solid fa-city"></i> Ville d'affectation *</label>
                <input type="text" name="ville_affec" placeholder="Ex: Abidjan, Bouaké, Yamoussoukro..." required>
            </div>

            <div class="form-group">
                <label><i class="fa-solid fa-calendar-day"></i> Date d'effet *</label>
                <input type="date" name="date_effet" value="<?= date('Y-m-d'); ?>" required>
            </div>

            <div class="form-group">
                <label><i class="fa-solid fa-comment-dots"></i> Motif de la mutation</label>
                <textarea name="motif" rows="3" placeholder="Ex: Rapprochement familial, Nécessité de service, Promotion..."></textarea>
            </div>

            <div class="btn-box">
                <a href="javascript:history.back()" class="btn-cancel">Annuler</a>
                <button type="submit" class="btn-submit">
                    <i class="fa-solid fa-check"></i> Valider la mutation
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('org_select').addEventListener('change', function() {
    const idOrg = this.value;
    const gradeSelect = document.getElementById('grade_select');

    if(idOrg) {
        fetch('get_grades.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'id_org=' + encodeURIComponent(idOrg)
        })
        .then(response => response.text())
        .then(data => {
            gradeSelect.innerHTML = data;
        })
        .catch(error => {
            console.error('Erreur:', error);
            gradeSelect.innerHTML = '<option value="">Erreur de chargement</option>';
        });
    } else {
        gradeSelect.innerHTML = '<option value="">Sélectionnez d\'abord une organisation</option>';
    }
});
</script>

</body>
</html>
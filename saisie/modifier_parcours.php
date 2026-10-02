<?php
session_start();
require_once '../config.php';

// Vérification de connexion
if (!isset($_SESSION['id_fonc']) && !isset($_SESSION['rol'])) {
    die("Accès refusé. Vous devez être connecté.");
}

$id_rec = (int)($_GET['id_rec'] ?? 0);
$id_agent = (int)($_GET['id_agent'] ?? 0);

if ($id_rec <= 0 || $id_agent <= 0) {
    header("Location: ../views/liste_agents.php");
    exit();
}

// 1. TRAITEMENT DE LA MISE À JOUR (POST)
$erreur = "";
$succes = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_org = (int)($_POST['id_org'] ?? 0);
    $id_affec = (int)($_POST['id_affec'] ?? 0);
    $date_debut = trim($_POST['date_debut'] ?? '');
    $date_fin = trim($_POST['date_fin'] ?? '');

    if (empty($date_fin)) {
        $date_fin = null; // Gérer le cas "En cours"
    }

    if ($id_org > 0 && $id_affec > 0 && !empty($date_debut)) {
        try {
            $stmtUpdate = $db->prepare("
                UPDATE RECEVOIR 
                SET id_org = :id_org, 
                    id_affec = :id_affec, 
                    date_debut = :date_debut, 
                    date_fin = :date_fin 
                WHERE id_rec = :id_rec
            ");
            
            $stmtUpdate->execute([
                ':id_org' => $id_org,
                ':id_affec' => $id_affec,
                ':date_debut' => $date_debut,
                ':date_fin' => $date_fin,
                ':id_rec' => $id_rec
            ]);

            // Redirection vers le profil avec un message de succès
            header("Location: ../views/profil_agent.php?id=" . $id_agent . "&section=mutations&msg=parcours_modifie");
            exit();

        } catch (PDOException $e) {
            $erreur = "Erreur lors de la modification : " . $e->getMessage();
        }
    } else {
        $erreur = "Veuillez remplir tous les champs obligatoires (Organisation, Poste et Date de début).";
    }
}

// 2. RÉCUPÉRATION DES DONNÉES POUR LE FORMULAIRE (GET)
try {
    // Récupérer les infos actuelles du parcours à modifier
    $stmtParcours = $db->prepare("SELECT * FROM RECEVOIR WHERE id_rec = ?");
    $stmtParcours->execute([$id_rec]);
    $current = $stmtParcours->fetch();

    if (!$current) {
        die("Parcours introuvable.");
    }

    // Récupérer le nom de l'agent pour le titre
    $stmtAgent = $db->prepare("SELECT nom_fonc, prenom_fonc FROM FONCTIONNAIRE WHERE id_fonc = ?");
    $stmtAgent->execute([$id_agent]);
    $agent = $stmtAgent->fetch();

    // Liste des organisations pour le select
    $organisations = $db->query("SELECT id_org, nom_org, sigle_org FROM ORGANISATION ORDER BY nom_org ASC")->fetchAll();

    // Liste des affectations pour le select
    $affectations = $db->query("SELECT id_affec, gra_post, vil_affec FROM AFFECTATION ORDER BY gra_post ASC")->fetchAll();

} catch (PDOException $e) {
    die("Erreur de base de données : " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier le Parcours | Fonction Publique</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #0A2540;
            --secondary: #D4AF37;
            --bg: #F8FAFC;
            --white: #FFFFFF;
            --text: #1E293B;
            --text-light: #64748B;
            --border: #E2E8F0;
            --danger: #EF4444;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }

        body {
            background: linear-gradient(135deg, var(--bg) 0%, #F1F5F9 100%);
            color: var(--text);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .form-container {
            background: var(--white);
            max-width: 600px;
            width: 100%;
            padding: 40px;
            border-radius: 24px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08);
            border: 1px solid var(--border);
        }

        .form-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .form-header i {
            font-size: 2.5rem;
            color: var(--secondary);
            margin-bottom: 10px;
        }

        .form-header h2 {
            color: var(--primary);
            font-size: 1.5rem;
            font-weight: 700;
        }

        .form-header p {
            color: var(--text-light);
            font-size: 0.85rem;
            margin-top: 5px;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 20px;
        }
        .alert-danger { background: #FEE2E2; color: #991B1B; border: 1px solid #FCA5A5; }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--text-light);
            margin-bottom: 8px;
            letter-spacing: 0.5px;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border-radius: 12px;
            border: 2px solid var(--border);
            font-size: 0.9rem;
            color: var(--primary);
            font-weight: 500;
            transition: all 0.3s ease;
            background-color: #F8FAFC;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--secondary);
            background-color: var(--white);
            box-shadow: 0 0 0 4px rgba(212, 175, 55, 0.1);
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .btn-actions {
            display: flex;
            gap: 12px;
            margin-top: 30px;
        }

        .btn-submit {
            flex: 2;
            padding: 12px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--secondary) 0%, #b8960c 100%);
            color: var(--primary);
            font-weight: 600;
            font-size: 0.9rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(212, 175, 55, 0.3);
        }

        .btn-cancel {
            flex: 1;
            padding: 12px;
            border-radius: 12px;
            background: transparent;
            border: 2px solid var(--border);
            color: var(--text-light);
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s ease;
        }

        .btn-cancel:hover {
            border-color: var(--danger);
            color: var(--danger);
            background: #FEF2F2;
        }
    </style>
</head>
<body>

<div class="form-container">
    <div class="form-header">
        <i class="fa-solid fa-route"></i>
        <h2>Modifier une étape du parcours</h2>
        <p>Agent : <strong><?php echo htmlspecialchars(($agent['nom_fonc'] ?? '') . ' ' . ($agent['prenom_fonc'] ?? '')); ?></strong></p>
    </div>

    <?php if (!empty($erreur)): ?>
        <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation"></i> <?php echo $erreur; ?></div>
    <?php endif; ?>

    <form action="" method="POST">
        <!-- Sélection Organisation -->
        <div class="form-group">
            <label for="id_org">Organisation / Service</label>
            <select name="id_org" id="id_org" class="form-control" required>
                <option value="">-- Choisir une organisation --</option>
                <?php foreach ($organisations as $org): ?>
                    <option value="<?php echo $org['id_org']; ?>" <?php echo ($current['id_org'] == $org['id_org']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($org['nom_org']); ?> <?php echo !empty($org['sigle_org']) ? '('.htmlspecialchars($org['sigle_org']).')' : ''; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Sélection Affectation / Grade -->
        <div class="form-group">
            <label for="id_affec">Poste / Grade cible</label>
            <select name="id_affec" id="id_affec" class="form-control" required>
                <option value="">-- Choisir un poste --</option>
                <?php foreach ($affectations as $aff): ?>
                    <option value="<?php echo $aff['id_affec']; ?>" <?php echo ($current['id_affec'] == $aff['id_affec']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($aff['gra_post']); ?> (<?php echo htmlspecialchars($aff['vil_affec']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Dates de début et fin -->
        <div class="form-grid">
            <div class="form-group">
                <label for="date_debut">Date de Début</label>
                <input type="date" name="date_debut" id="date_debut" class="form-control" 
                       value="<?php echo htmlspecialchars($current['date_debut']); ?>" required>
            </div>

            <div class="form-group">
                <label for="date_fin">Date de Fin <small style="text-transform:lowercase; color:var(--text-light)">(Optionnel)</small></label>
                <input type="date" name="date_fin" id="date_fin" class="form-control" 
                       value="<?php echo htmlspecialchars($current['date_fin'] ?? ''); ?>">
            </div>
        </div>

        <!-- Boutons d'action -->
        <div class="btn-actions">
            <a href="../views/profil_agent.php?id=<?php echo $id_agent; ?>&section=mutations" class="btn-cancel">Annuler</a>
            <button type="submit" class="btn-submit">
                <i class="fa-solid fa-floppy-disk"></i> Enregistrer les modifications
            </button>
        </div>
    </form>
</div>

</body>
</html>
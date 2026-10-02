<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config.php';

// On récupère l'ID de l'agent ET l'ID du message s'il existe
$id = $_GET['id'] ?? $_POST['id_fonc'] ?? null;
$id_msg = $_GET['id_msg'] ?? $_POST['id_msg'] ?? null;

if (!$id) {
    header("Location: ../views/admin_dashboard.php");
    exit();
}

// 1. SÉCURITÉ MISE À JOUR
$role = $_SESSION['rol'] ?? '';
$id_session = $_SESSION['id_fonc'] ?? null;

$est_admin = ($role === 'admin' || $role === 'admin_local');
$est_proprietaire = ($id_session == $id);

if (!$est_admin && !$est_proprietaire && !$est_proprietaire) {
    header("Location: ../saisie/login.php");
    exit();
}

// 2. RÉCUPÉRATION DE L'AGENT AVEC SON AFFECTATION ACTUELLE
$stmt = $db->prepare("
    SELECT f.*, r.id_org, a.gra_post
    FROM FONCTIONNAIRE f
    LEFT JOIN RECEVOIR r ON f.id_fonc = r.id_fonc AND r.date_fin IS NULL
    LEFT JOIN AFFECTATION a ON r.id_affec = a.id_affec
    LEFT JOIN ORGANISATION o ON r.id_org = o.id_org
    WHERE f.id_fonc = ?
");
$stmt->execute([$id]);
$agent = $stmt->fetch();

if (!$agent) { 
    die("Agent introuvable."); 
}

// 3. TRAITEMENT DE LA MISE À JOUR
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id_fonc'])) {
    $nom = strtoupper(htmlspecialchars($_POST['nom'])); 
    $prenoms = htmlspecialchars($_POST['prenoms']);
    $contact = htmlspecialchars($_POST['contact']);
    $email = htmlspecialchars($_POST['email']);
    $date_naiss = $_POST['date_naissance'];
    $genre = $_POST['genre'];
    $adresse = htmlspecialchars($_POST['adresse']);
    
    // Récupération des données pro (L'admin modifie, l'agent garde ses valeurs actuelles)
    $id_org = ($est_admin && !empty($_POST['id_org'])) ? (int)$_POST['id_org'] : $agent['id_org'];
    $gra_post = ($est_admin && !empty($_POST['gra_post'])) ? trim($_POST['gra_post']) : $agent['gra_post'];

    try {
        $db->beginTransaction();

        // Gestion de l'upload photo
        $photo_sql = "";
        $params = [$nom, $prenoms, $contact, $email, $date_naiss, $genre, $adresse];
        
        if (!empty($_FILES['photo_agent']['name'])) {
            $nom_photo = time() . '_' . $_FILES['photo_agent']['name'];
            if (!is_dir("../uploads/photos/")) { mkdir("../uploads/photos/", 0777, true); }
            move_uploaded_file($_FILES['photo_agent']['tmp_name'], "../uploads/photos/" . $nom_photo);
            $photo_sql = ", photo_fonc = ?";
            $params[] = $nom_photo;
        }

        $params[] = $id; 
        
        // Mise à jour de la table principale FONCTIONNAIRE
        $sql = "UPDATE FONCTIONNAIRE 
                SET nom_fonc = ?, prenom_fonc = ?, cont = ?, email_perso = ?, 
                    date_naiss = ?, gen = ?, adre = ? $photo_sql
                WHERE id_fonc = ?";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        // MISE À JOUR PROFESSIONNELLE (Seulement si l'admin a soumis des données valides)
        if ($est_admin && !empty($id_org) && !empty($gra_post)) {
            // A. Trouver ou créer le grade correspondant dans la table AFFECTATION
            $stmtAff = $db->prepare("SELECT id_affec FROM AFFECTATION WHERE gra_post = ? LIMIT 1");
            $stmtAff->execute([$gra_post]);
            $affectation = $stmtAff->fetch();

            if ($affectation) {
                $id_affec_trouve = $affectation['id_affec'];
            } else {
                $stmtInsAff = $db->prepare("INSERT INTO AFFECTATION (gra_post, sta_post) VALUES (?, 'Actif')");
                $stmtInsAff->execute([$gra_post]);
                $id_affec_trouve = $db->lastInsertId();
            }

            // B. Gérer l'affectation dans la table pivot RECEVOIR
            $stmtCheck = $db->prepare("SELECT id_rec FROM RECEVOIR WHERE id_fonc = ? AND date_fin IS NULL");
            $stmtCheck->execute([$id]);
            $rec_actuel = $stmtCheck->fetch();

            if ($rec_actuel) {
                $stmtUpRec = $db->prepare("UPDATE RECEVOIR SET id_org = ?, id_affec = ? WHERE id_rec = ?");
                $stmtUpRec->execute([$id_org, $id_affec_trouve, $rec_actuel['id_rec']]);
            } else {
                $stmtInsRec = $db->prepare("INSERT INTO RECEVOIR (id_fonc, id_org, id_affec, date_debut) VALUES (?, ?, ?, CURRENT_DATE)");
                $stmtInsRec->execute([$id, $id_org, $id_affec_trouve]);
            }
        }

        // Gestion des messages de demande de modification
        if ($id_msg && $est_admin) {
            $updateMsg = $db->prepare("UPDATE MESSAGES SET stat = 'lu' WHERE id_mess = ?");
            $updateMsg->execute([$id_msg]);
        }

        // HISTORIQUE
        $id_user_action = $_SESSION['id_user'] ?? $id;
        $action_desc = ($est_admin ? "Admin" : "Agent") . " a modifié le profil : " . $nom;
        $log_stmt = $db->prepare("INSERT INTO HISTORIQUE (id_user, action_desc, dat_hist) VALUES (?, ?, NOW())");
        $log_stmt->execute([$id_user_action, $action_desc]);

        $db->commit(); 

        // REDIRECTION
        if ($est_admin) {
            header("Location: ../views/admin_dashboard.php?msg=update_success");
        } else {
            header("Location: profil_agent.php?id=$id&msg=update_success");
        }
        exit();
    } catch (PDOException $e) {
        $db->rollBack();
        $erreur = "Erreur lors de la mise à jour : " . $e->getMessage();
    }
}

// Récupération des listes pour les éléments <select>
$organisations = $db->query("SELECT id_org, nom_org, sigle_org FROM ORGANISATION ORDER BY nom_org ASC")->fetchAll();
$grades = $db->query("SELECT DISTINCT gra_post FROM AFFECTATION WHERE gra_post IS NOT NULL AND gra_post != '' ORDER BY gra_post ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <!-- Nom de l'application ajouté dans le title -->
    <title>SICP | Modifier Profil - <?= htmlspecialchars($agent['matri'] ?? 'Agent'); ?></title>
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
        
        .form-container { 
            background: var(--white); 
            border-radius: 28px; 
            width: 100%; 
            max-width: 800px; 
            overflow: hidden; 
            box-shadow: 0 30px 60px rgba(0,0,0,0.2); 
        }
        
        /* -- NOUVEL EN-TÊTE D'IDENTITÉ INSTITUTIONNELLE -- */
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
        .form-header .sub-info {
            color: #cbd5e1;
            font-size: 0.85rem;
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 8px;
        }
        .form-header .sub-info span {
            background: rgba(0,0,0,0.2);
            padding: 2px 10px;
            border-radius: 6px;
        }

        .form-body { padding: 35px; display: flex; flex-direction: column; gap: 25px; }
        
        /* Blocs thématiques structurés */
        .form-section { background: #F8FAFC; padding: 20px; border-radius: 16px; border: 1px solid var(--border); }
        .form-section h3 { font-size: 1rem; color: var(--primary); margin-bottom: 20px; border-bottom: 2px solid var(--border); padding-bottom: 8px; display: flex; align-items: center; gap: 8px; }
        
        /* Grille alignée */
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group.full-width { grid-column: span 2; }
        
        label { display: block; font-weight: 600; font-size: 0.75rem; text-transform: uppercase; margin-bottom: 8px; color: #64748B; }
        label i { color: var(--accent); margin-right: 5px; }
        
        input, select { width: 100%; padding: 12px 14px; border: 2px solid var(--border); border-radius: 10px; font-family: inherit; font-size: 0.9rem; background-color: var(--white); }
        input:focus, select:focus { outline: none; border-color: var(--accent); }
        input:disabled, select:disabled { background-color: #E2E8F0; cursor: not-allowed; color: #64748B; }
        
        .btn-box { display: flex; gap: 15px; justify-content: flex-end; margin-top: 10px; }
        .btn-submit { padding: 14px 28px; background: var(--primary); color: white; border: none; border-radius: 12px; font-weight: 700; cursor: pointer; transition: 0.3s; }
        .btn-submit:hover { opacity: 0.9; transform: translateY(-2px); }
        .btn-cancel { padding: 14px 28px; background: #E2E8F0; color: #64748B; border-radius: 12px; font-weight: 700; text-decoration: none; text-align: center; transition: 0.3s; }
        .btn-cancel:hover { background: #CBD5E1; }
        
        .current-photo { width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 2px solid var(--accent); }
    </style>
</head>
<body>

<div class="form-container">
    <!-- EN-TÊTE AVEC IDENTITÉ CLAIRE DE L'APPLICATION -->
    <div class="form-header">
        <!-- Badge / Logo de l'application (Exemple: République / Ministère / Système de Gestion) -->
        <div class="brand-badge">
            <i class="fa-solid fa-building-columns"></i>
            <span>Ministère des Affaires Etrangères </span>
        </div>
        
        <h2><i class="fa-solid fa-user-pen"></i> <?= $est_proprietaire ? "Mon Profil Agent" : "Edition de la Fiche Agent" ?></h2>
        
        <div class="sub-info">
            <span><i class="fa-solid fa-hashtag"></i> Matricule: <strong><?= htmlspecialchars($agent['matri'] ?? 'N/A'); ?></strong></span>
            <?php if(!empty($agent['nom_org'])): ?>
                <span><i class="fa-solid fa-sitemap"></i> <?= htmlspecialchars($agent['nom_org']); ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="form-body">
        <?php if(isset($erreur)): ?>
            <div style="background: #fee2e2; color: #b91c1c; padding: 15px; border-radius: 10px;">
                <i class="fa-solid fa-triangle-exclamation"></i> <?= $erreur; ?>
            </div>
        <?php endif; ?>

        <form id="editForm" method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="id_fonc" value="<?= $id ?>">
            <input type="hidden" name="id_msg" value="<?= htmlspecialchars($id_msg ?? '') ?>">
            
            <div class="form-section">
                <h3><i class="fa-solid fa-user"></i> Informations Personnelles</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label><i class="fa-solid fa-id-card"></i> Nom de famille</label>
                        <input type="text" name="nom" value="<?= htmlspecialchars($agent['nom_fonc'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fa-solid fa-user"></i> Prénoms</label>
                        <input type="text" name="prenoms" value="<?= htmlspecialchars($agent['prenom_fonc'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fa-solid fa-calendar"></i> Date de Naissance</label>
                        <input type="date" name="date_naissance" value="<?= $agent['date_naiss'] ?? ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fa-solid fa-venus-mars"></i> Genre</label>
                        <select name="genre" required>
                            <option value="M" <?= (($agent['gen'] ?? '') == 'M') ? 'selected' : ''; ?>>Masculin</option>
                            <option value="F" <?= (($agent['gen'] ?? '') == 'F') ? 'selected' : ''; ?>>Féminin</option>
                        </select>
                    </div>
                    <div class="form-group full-width">
                        <label><i class="fa-solid fa-camera"></i> Photo de profil</label>
                        <div style="display: flex; align-items: center; gap: 15px; margin-top: 5px;">
                            <?php $img_src = !empty($agent['photo_fonc']) ? "../uploads/photos/".$agent['photo_fonc'] : "../public/default_avatar.png"; ?>
                            <img src="<?= $img_src ?>" class="current-photo" alt="Photo de l'agent">
                            <input type="file" name="photo_agent" accept="image/*">
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3><i class="fa-solid fa-address-book"></i> Coordonnées & Contacts</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label><i class="fa-solid fa-phone"></i> Téléphone</label>
                        <input type="tel" name="contact" value="<?= htmlspecialchars($agent['cont'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fa-solid fa-envelope"></i> Email Personnel</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($agent['email_perso'] ?? ''); ?>">
                    </div>
                    <div class="form-group full-width">
                        <label><i class="fa-solid fa-house"></i> Adresse de résidence</label>
                        <input type="text" name="adresse" value="<?= htmlspecialchars($agent['adre'] ?? ''); ?>" required>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3><i class="fa-solid fa-briefcase"></i> Situation Professionnelle</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="id_org"><i class="fa-solid fa-sitemap"></i> Organisation / Structure *</label>
                        <select name="id_org" id="id_org" <?= !$est_admin ? 'disabled' : '' ?> required>
                            <option value="">-- Sélectionner --</option>
                            <?php foreach ($organisations as $org): ?>
                                <?php $selected = (isset($agent['id_org']) && $agent['id_org'] == $org['id_org']) ? 'selected' : ''; ?>
                                <option value="<?php echo $org['id_org']; ?>" <?php echo $selected; ?>>
                                    <?php echo htmlspecialchars($org['nom_org'] . " (" . $org['sigle_org'] . ")"); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="gra_post"><i class="fa-solid fa-award"></i> Grade de l'Agent *</label>
                        <select name="gra_post" id="gra_post" <?= !$est_admin ? 'disabled' : '' ?> required>
                            <option value="">-- Sélectionner --</option>
                            <?php foreach ($grades as $g): ?>
                                <?php $selected = (isset($agent['gra_post']) && $agent['gra_post'] == $g['gra_post']) ? 'selected' : ''; ?>
                                <option value="<?php echo htmlspecialchars($g['gra_post']); ?>" <?php echo $selected; ?>>
                                    <?php echo htmlspecialchars($g['gra_post']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php if(!$est_admin): ?>
                    <p style="font-size: 0.75rem; color: #64748B; margin-top: 10px; font-style: italic;">
                        <i class="fa-solid fa-info-circle"></i> Seul un administrateur peut modifier l'organisation et le grade.
                    </p>
                <?php endif; ?>
            </div>

            <div class="btn-box">
                <a href="javascript:history.back()" class="btn-cancel">Annuler</a>
                <button type="submit" class="btn-submit">
                    <i class="fa-solid fa-save"></i> Enregistrer les modifications
                </button>
            </div>
        </form>
    </div>
</div>

</body>
</html>
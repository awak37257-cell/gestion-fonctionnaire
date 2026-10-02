<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config.php';

$role = isset($_SESSION['rol']) ? trim($_SESSION['rol']) : '';
$id_session = isset($_SESSION['id_fonc']) ? (int)$_SESSION['id_fonc'] : 0;

$id_agent = isset($_GET['id_agent']) ? (int)$_GET['id_agent'] : 0;
$id_comp = isset($_GET['id_comp']) ? (int)$_GET['id_comp'] : 0;

$est_mon_profil = ($id_session === $id_agent);
$est_admin = in_array($role, ['admin', 'admin_local']);

if (!$est_admin && !$est_mon_profil) {
    die("Accès refusé.");
}

// Récupération de l'ensemble des informations de la compétence
$stmt = $db->prepare("
    SELECT c.id_comp, c.libelle_comp, d.niveau, d.justificatif_path 
    FROM competences c
    JOIN detenir d ON c.id_comp = d.id_comp
    WHERE d.id_comp = :id_comp AND d.id_fonc = :id_agent
");
$stmt->execute([':id_comp' => $id_comp, ':id_agent' => $id_agent]);
$competence = $stmt->fetch();

if (!$competence) {
    die("Compétence introuvable pour cet agent.");
}

$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $libelle_comp = trim($_POST['libelle_comp']);
    $niveau = trim($_POST['niveau']);
    $justificatif_path = $competence['justificatif_path'];

    if (!empty($libelle_comp) && !empty($niveau)) {
        try {
            $db->beginTransaction();

            // Gestion de l'upload si un nouveau fichier PDF est fourni
            if (isset($_FILES['justif_comp']) && $_FILES['justif_comp']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['justif_comp']['tmp_name'];
                $fileName = $_FILES['justif_comp']['name'];
                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                if ($fileExtension === 'pdf') {
                    $newFileName = 'comp_' . $id_agent . '_' . time() . '.pdf';
                    $uploadFileDir = '../uploads/competences/';
                    
                    if(!is_dir($uploadFileDir)){
                        mkdir($uploadFileDir, 0755, true);
                    }

                    if(move_uploaded_file($fileTmpPath, $uploadFileDir . $newFileName)) {
                        $justificatif_path = 'competences/' . $newFileName;
                    }
                } else {
                    $erreur = "Seuls les fichiers PDF sont autorisés.";
                }
            }

            if (empty($erreur)) {
                // 1. Mise à jour du libellé dans la table `competences`
                $stmt1 = $db->prepare("UPDATE competences SET libelle_comp = ? WHERE id_comp = ?");
                $stmt1->execute([$libelle_comp, $id_comp]);

                // 2. Mise à jour du niveau et du justificatif dans la table associative `detenir`
                $stmt2 = $db->prepare("UPDATE detenir SET niveau = ?, justificatif_path = ? WHERE id_comp = ? AND id_fonc = ?");
                $stmt2->execute([$niveau, $justificatif_path, $id_comp, $id_agent]);

                $db->commit();
                header("Location: ../views/profil_agent.php?id=" . $id_agent . "&section=competences");
                exit;
            }
        } catch (Exception $e) {
            $db->rollBack();
            $erreur = "Erreur lors de la modification : " . $e->getMessage();
        }
    } else {
        $erreur = "Veuillez remplir tous les champs obligatoires.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Modifier la Compétence</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; background: #F8FAFC; padding: 50px; color: #1E293B; }
        .form-card { max-width: 600px; background: white; margin: 0 auto; padding: 30px; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); border: 1px solid #E2E8F0; }
        h2 { color: #0A2540; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-size: 1.4rem; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; margin-bottom: 8px; color: #64748B; }
        input[type="text"], select, input[type="file"] { width: 100%; padding: 12px; border-radius: 10px; border: 1px solid #E2E8F0; font-family: inherit; font-size: 0.9rem; box-sizing: border-box; }
        input[type="text"]:focus, select:focus { outline: none; border-color: #D4AF37; }
        .btn-box { display: flex; gap: 10px; margin-top: 30px; }
        .btn-save { background: linear-gradient(135deg, #D4AF37, #b8960c); color: #0A2540; font-weight: 600; padding: 12px 20px; border-radius: 10px; border: none; cursor: pointer; flex: 1; transition: all 0.3s; }
        .btn-cancel { background: #E2E8F0; color: #64748B; font-weight: 600; padding: 12px 20px; border-radius: 10px; text-decoration: none; text-align: center; flex: 1; transition: all 0.3s; }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(212,175,55,0.3); }
        .alert { padding: 12px; border-radius: 10px; margin-bottom: 20px; font-size: 0.85rem; font-weight: 500; }
        .alert-danger { background: #FEE2E2; color: #991B1B; }
    </style>
</head>
<body>

<div class="form-card">
    <h2><i class="fa-solid fa-award" style="color: #D4AF37;"></i> Modifier la Compétence</h2>
    
    <?php if(!empty($erreur)): ?>
        <div class="alert alert-danger"><?php echo $erreur; ?></div>
    <?php endif; ?>

    <form action="" method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label>Intitulé de la compétence *</label>
            <input type="text" name="libelle_comp" value="<?php echo htmlspecialchars($competence['libelle_comp']); ?>" required>
        </div>

        <div class="form-group">
            <label>Niveau de maîtrise *</label>
            <select name="niveau" required>
                <option value="Débutant" <?php echo ($competence['niveau'] == 'Débutant') ? 'selected' : ''; ?>>Débutant</option>
                <option value="Intermédiaire" <?php echo ($competence['niveau'] == 'Intermédiaire') ? 'selected' : ''; ?>>Intermédiaire</option>
                <option value="Avancé" <?php echo ($competence['niveau'] == 'Avancé') ? 'selected' : ''; ?>>Avancé</option>
                <option value="Expert" <?php echo ($competence['niveau'] == 'Expert') ? 'selected' : ''; ?>>Expert</option>
            </select>
        </div>

        <div class="form-group">
            <label>Nouveau justificatif / Certification (PDF - Optionnel)</label>
            <input type="file" name="justif_comp" accept=".pdf">
            <?php if(!empty($competence['justificatif_path'])): ?>
                <p style="font-size: 0.8rem; color: #64748B; margin-top: 5px;">
                    <i class="fa-solid fa-file-pdf"></i> Fichier actuel : <?php echo htmlspecialchars($competence['justificatif_path']); ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="btn-box">
            <a href="../views/profil_agent.php?id=<?php echo $id_agent; ?>&section=competences" class="btn-cancel">Annuler</a>
            <button type="submit" class="btn-save">Enregistrer les modifications</button>
        </div>
    </form>
</div>

</body>
</html>
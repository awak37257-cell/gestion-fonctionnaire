<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config.php';

// Protection de la page : Vérification des droits
$role = isset($_SESSION['rol']) ? trim($_SESSION['rol']) : '';
$id_session = isset($_SESSION['id_fonc']) ? (int)$_SESSION['id_fonc'] : 0;

$id_agent = isset($_GET['id_agent']) ? (int)$_GET['id_agent'] : 0;
$id_dipl = isset($_GET['id_dipl']) ? (int)$_GET['id_dipl'] : 0;

$est_mon_profil = ($id_session === $id_agent);
$est_admin = in_array($role, ['admin', 'admin_local']);

if (!$est_admin && !$est_mon_profil) {
    die("Accès refusé : Vous n'avez pas les droits pour modifier ces informations.");
}

// Récupération des infos actuelles du diplôme
$stmt = $db->prepare("
    SELECT d.*, o.ann_obte 
    FROM DIPLOME d
    JOIN OBTENIR o ON d.id_dipl = o.id_dipl
    WHERE d.id_dipl = :id_dipl AND o.id_fonc = :id_agent
");
$stmt->execute([':id_dipl' => $id_dipl, ':id_agent' => $id_agent]);
$diplome = $stmt->fetch();

if (!$diplome) {
    die("Diplôme introuvable.");
}

// Traitement du formulaire
$erreur = '';
$succes = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lib_dipl = trim($_POST['lib_dipl']);
    $niv_etu = trim($_POST['niv_etu']);
    $ann_obte = trim($_POST['ann_obte']);
    $fich_url = $diplome['fich_url']; // Par défaut on garde l'ancien fichier

    if (!empty($lib_dipl) && !empty($niv_etu) && !empty($ann_obte)) {
        try {
            $db->beginTransaction();

            // Gestion de l'upload du nouveau PDF si fourni
            if (isset($_FILES['pdf_diplome']) && $_FILES['pdf_diplome']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['pdf_diplome']['tmp_name'];
                $fileName = $_FILES['pdf_diplome']['name'];
                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                if ($fileExtension === 'pdf') {
                    $newFileName = 'diplome_' . $id_agent . '_' . time() . '.pdf';
                    $uploadFileDir = '../uploads/diplomes/';
                    
                    if(!is_dir($uploadFileDir)){
                        mkdir($uploadFileDir, 0755, true);
                    }

                    if(move_uploaded_file($fileTmpPath, $uploadFileDir . $newFileName)) {
                        $fich_url = $newFileName;
                    }
                } else {
                    $erreur = "Seuls les fichiers PDF sont autorisés.";
                }
            }

            if (empty($erreur)) {
                // 1. Update table DIPLOME
                $stmt = $db->prepare("UPDATE DIPLOME SET lib_dipl = ?, niv_etu = ?, fich_url = ? WHERE id_dipl = ?");
                $stmt->execute([$lib_dipl, $niv_etu, $fich_url, $id_dipl]);

                // 2. Update table OBTENIR
                $stmt = $db->prepare("UPDATE OBTENIR SET ann_obte = ? WHERE id_dipl = ? AND id_fonc = ?");
                $stmt->execute([$ann_obte, $id_dipl, $id_agent]);

                $db->commit();
                header("Location: ../views/profil_agent.php?id=" . $id_agent . "&section=diplomes");
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
    <title>Modifier le Diplôme</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; background: #F8FAFC; padding: 50px; color: #1E293B; }
        .form-card { max-width: 600px; background: white; margin: 0 auto; padding: 30px; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); border: 1px solid #E2E8F0; }
        h2 { color: #0A2540; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-size: 1.4rem; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; margin-bottom: 8px; color: #64748B; }
        input, select { width: 100%; padding: 12px; border-radius: 10px; border: 1px solid #E2E8F0; font-family: inherit; font-size: 0.9rem; box-sizing: border-box; }
        input:focus, select:focus { outline: none; border-color: #D4AF37; }
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
    <h2><i class="fa-solid fa-graduation-cap" style="color: #D4AF37;"></i> Modifier le Diplôme</h2>
    
    <?php if(!empty($erreur)): ?>
        <div class="alert alert-danger"><?php echo $erreur; ?></div>
    <?php endif; ?>

    <form action="" method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label>Libellé du Diplôme *</label>
            <input type="text" name="lib_dipl" value="<?php echo htmlspecialchars($diplome['lib_dipl']); ?>" required>
        </div>

        <div class="form-group">
            <label>Niveau d'étude *</label>
            <input type="text" name="niv_etu" value="<?php echo htmlspecialchars($diplome['niv_etu']); ?>" required>
        </div>

        <div class="form-group">
            <label>Année d'obtention *</label>
            <input type="number" name="ann_obte" min="1960" max="<?php echo date('Y'); ?>" value="<?php echo htmlspecialchars($diplome['ann_obte']); ?>" required>
        </div>

        <div class="form-group">
            <label>Nouveau justificatif (PDF uniquement - optionnel)</label>
            <input type="file" name="pdf_diplome" accept=".pdf">
            <?php if(!empty($diplome['fich_url'])): ?>
                <p style="font-size: 0.8rem; color: #64748B; margin-top: 5px;">
                    <i class="fa-solid fa-file-pdf"></i> Fichier actuel : <?php echo htmlspecialchars($diplome['fich_url']); ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="btn-box">
            <a href="../views/profil_agent.php?id=<?php echo $id_agent; ?>&section=diplomes" class="btn-cancel">Annuler</a>
            <button type="submit" class="btn-save">Enregistrer les modifications</button>
        </div>
    </form>
</div>

</body>
</html>
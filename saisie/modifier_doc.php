<?php
session_start();
require_once '../config.php';

// 1. Vérification de l'ID
$id_doc = $_GET['id'] ?? null;
if (!$id_doc) {
    die("Erreur : Aucun identifiant de document.");
}

// 2. Récupération des données pour l'affichage
$stmt = $db->prepare("SELECT * FROM DOCUMENT WHERE id_doc = ?");
$stmt->execute([$id_doc]);
$document = $stmt->fetch();

if (!$document) {
    die("Erreur : Document introuvable.");
}

// 3. Traitement lors du clic sur Enregistrer
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nouveau_libelle = htmlspecialchars($_POST['lib_doc']);
    $chemin_final = $document['fich_url']; 

    if (isset($_FILES['nouveau_fichier']) && $_FILES['nouveau_fichier']['error'] == 0) {
        $target_dir = "../actions/uploads/pieces_jointes/";
        if (!file_exists($target_dir)) { mkdir($target_dir, 0777, true); }

        $extension = strtolower(pathinfo($_FILES["nouveau_fichier"]["name"], PATHINFO_EXTENSION));
        $nom_unique = "doc_" . time() . "." . $extension;
        $target_file = $target_dir . $nom_unique;

        if (move_uploaded_file($_FILES["nouveau_fichier"]["tmp_name"], $target_file)) {
            if (!empty($document['fich_url']) && file_exists($document['fich_url'])) {
                unlink($document['fich_url']);
            }
            $chemin_final = $target_file;
        }
    }

    try {
        $update = $db->prepare("UPDATE DOCUMENT SET lib_doc = ?, fich_url = ? WHERE id_doc= ?");
        $update->execute([$nouveau_libelle, $chemin_final, $id_doc]);
        header("Location: ../views/gestion_documents.php?msg=updated");
        exit();
    } catch (PDOException $e) {
        die("Erreur : " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Édition Documentaire | Ministère</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #0A2540;
            --primary-dark: #051a2c;
            --primary-light: #1a3d5c;
            --accent: #F59E0B;
            --accent-dark: #D97706;
            --success: #10B981;
            --success-dark: #059669;
            --danger: #EF4444;
            --bg: #F8FAFC;
            --text: #1E293B;
            --text-light: #64748B;
            --border: #E2E8F0;
            --white: #FFFFFF;
            --shadow: 0 20px 35px -10px rgba(0, 0, 0, 0.08);
            --shadow-md: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body { 
            font-family: 'Poppins', sans-serif; 
            background: linear-gradient(135deg, var(--bg) 0%, #F1F5F9 100%);
            display: flex; 
            justify-content: center; 
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }

        /* Carte principale */
        .edit-card { 
            background: var(--white); 
            padding: 0;
            border-radius: 32px; 
            box-shadow: var(--shadow);
            width: 100%; 
            max-width: 520px; 
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .edit-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 45px -12px rgba(0, 0, 0, 0.15);
        }

        /* Header */
        .card-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            padding: 28px 32px;
            text-align: center;
            position: relative;
        }

        .card-header::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--accent), var(--success), var(--accent));
        }

        .card-header h2 { 
            color: var(--white); 
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }

        .card-header h2 i {
            color: var(--accent);
            font-size: 1.6rem;
        }

        .card-header p {
            color: rgba(255,255,255,0.8);
            font-size: 0.8rem;
            margin-top: 8px;
        }

        /* Corps du formulaire */
        .card-body {
            padding: 32px;
        }

        label { 
            display: block; 
            margin-bottom: 8px; 
            font-weight: 600; 
            font-size: 0.75rem;
            color: var(--text);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        label i {
            margin-right: 6px;
            color: var(--accent);
        }

        input[type="text"] { 
            width: 100%; 
            padding: 12px 16px; 
            border: 2px solid var(--border); 
            border-radius: 14px; 
            box-sizing: border-box; 
            margin-bottom: 24px;
            font-family: 'Poppins', sans-serif;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            background: var(--bg);
        }

        input[type="text"]:hover {
            border-color: var(--accent);
        }

        input[type="text"]:focus {
            border-color: var(--accent);
            outline: none;
            background: var(--white);
            box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.1);
        }

        /* Boîte du fichier actuel */
        .current-file-box {
            background: linear-gradient(135deg, #F0FDF4 0%, #DCFCE7 100%);
            border: 1px solid #BBF7D0;
            padding: 14px 16px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
        }

        .current-file-box i { 
            color: var(--success); 
            font-size: 1.3rem; 
        }

        .current-file-box span { 
            font-size: 0.85rem; 
            color: var(--text);
            font-weight: 500;
            word-break: break-all;
            flex: 1;
        }

        .file-badge {
            background: var(--success);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        /* Zone d'upload */
        .file-input-wrapper {
            background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%);
            border: 2px dashed var(--accent);
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            position: relative;
            margin-bottom: 28px;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .file-input-wrapper:hover {
            border-color: var(--accent-dark);
            background: #FFFBEB;
            transform: translateY(-2px);
        }

        .file-input-wrapper i {
            font-size: 2rem;
            color: var(--accent);
            margin-bottom: 10px;
            display: block;
        }

        .file-input-wrapper p { 
            margin: 0; 
            font-size: 0.85rem; 
            color: #92400E;
            font-weight: 500;
        }

        .file-input-wrapper small {
            display: block;
            font-size: 0.7rem;
            color: var(--text-light);
            margin-top: 8px;
        }

        .file-input-wrapper input[type="file"] {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            opacity: 0;
            cursor: pointer;
        }

        /* Bouton principal */
        .btn-save { 
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white; 
            border: none; 
            padding: 14px 20px; 
            border-radius: 16px; 
            cursor: pointer; 
            width: 100%; 
            font-weight: 600;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-save::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .btn-save:hover::before {
            width: 300px;
            height: 300px;
        }

        .btn-save:hover { 
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(10, 37, 64, 0.3);
        }

        .btn-save:active {
            transform: translateY(0);
        }

        /* Lien annulation */
        .cancel-link { 
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-align: center; 
            margin-top: 20px; 
            color: var(--text-light); 
            text-decoration: none; 
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s ease;
            padding: 8px;
            border-radius: 10px;
        }

        .cancel-link:hover { 
            color: var(--danger);
            background: #FEE2E2;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .card-header {
                padding: 22px 20px;
            }
            .card-header h2 {
                font-size: 1.2rem;
            }
            .card-body {
                padding: 24px;
            }
        }
    </style>
</head>
<body>
<?php include '../views/includes/navbar.php'; ?>
<div class="edit-card">
    <div class="card-header">
        <h2>
            <i class="fa-solid fa-file-pen"></i>
            Modifier l'archive
        </h2>
        <p>Mettez à jour les informations du document</p>
    </div>
    
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <label>
                <i class="fa-solid fa-tag"></i> Libellé du document
            </label>
            <input type="text" name="lib_doc" value="<?= htmlspecialchars($document['lib_doc']) ?>" required>
            
            <label>
                <i class="fa-solid fa-file"></i> Fichier actuellement stocké
            </label>
            <div class="current-file-box">
                <i class="fa-solid fa-file-shield"></i>
                <span><?= htmlspecialchars(basename($document['fich_url'])) ?></span>
                <span class="file-badge">Actuel</span>
            </div>
            
            <label>
                <i class="fa-solid fa-arrow-up"></i> Remplacer par un nouveau fichier
            </label>
            <div class="file-input-wrapper">
                <i class="fa-solid fa-cloud-arrow-up"></i>
                <p id="file-status">Cliquez pour choisir un fichier</p>
                <small>PDF, JPG, PNG - Taille max: 5MB</small>
                <input type="file" name="nouveau_fichier" accept=".pdf,image/*" onchange="document.getElementById('file-status').innerHTML = '<i class=\'fa-solid fa-check-circle\' style=\'color:#10B981\'></i> ' + this.files[0].name; document.getElementById('file-status').style.color = '#065F46';">
            </div>
            
            <button type="submit" class="btn-save">
                <i class="fa-solid fa-floppy-disk"></i> Enregistrer les modifications
            </button>
            
            <a href="../views/gestion_documents.php" class="cancel-link">
                <i class="fa-solid fa-xmark"></i> Annuler et retourner aux archives
            </a>
        </form>
    </div>
</div>

</body>
</html>
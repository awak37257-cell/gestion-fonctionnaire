<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config.php';

// Correction de la sécurité : Autoriser si on a un ID de fonctionnaire OU si on est admin
$id_session = $_SESSION['id_fonc'] ?? null;
$role_session = $_SESSION['rol'] ?? '';

// On récupère l'ID de l'agent concerné via l'URL (si passé) ou via la session
$id_agent = isset($_GET['id']) ? (int)$_GET['id'] : $id_session;

if (!$id_session && !in_array($role_session, ['admin', 'admin_local'])) {
    header('Location: ../index.php');
    exit();
}

// Si on n'a toujours pas d'ID d'agent cible, on arrête
if (!$id_agent) {
    header('Location: ../index.php');
    exit();
}

$message = "";
// ... reste du code ...
// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom_comp = trim($_POST['competence']);
    $niveau = $_POST['niveau'];
    
    if (!empty($nom_comp) && !empty($niveau)) {
        try {
            $db->beginTransaction();

            // 1. Gérer ou créer la compétence dans le référentiel
            $stmt = $db->prepare("SELECT id_comp FROM competences WHERE libelle_comp = :libelle");
            $stmt->execute([':libelle' => $nom_comp]);
            $comp_existante = $stmt->fetch();

            if ($comp_existante) {
                $id_comp = $comp_existante['id_comp'];
            } else {
                $stmt = $db->prepare("INSERT INTO competences (libelle_comp) VALUES (:libelle)");
                $stmt->execute([':libelle' => $nom_comp]);
                $id_comp = $db->lastInsertId();
            }

            // 2. Gérer l'upload du justificatif (PDF, JPG, PNG)
            $nom_fichier = null;
            if (isset($_FILES['justificatif']) && $_FILES['justificatif']['error'] === 0) {
                $ext = pathinfo($_FILES['justificatif']['name'], PATHINFO_EXTENSION);
                $nom_fichier = "comp_" . $id_agent . "_" . time() . "." . $ext;
                
                if (!is_dir('../uploads')) { mkdir('../uploads', 0777, true); }
                move_uploaded_file($_FILES['justificatif']['tmp_name'], "../uploads/" . $nom_fichier);
            }

            // 3. Lier au fonctionnaire (Mise à jour si déjà existante)
            $stmt = $db->prepare("INSERT INTO detenir (id_fonc, id_comp, niveau, justificatif_path) 
                                 VALUES (:id_f, :id_c, :niv, :path)
                                 ON DUPLICATE KEY UPDATE niveau = :niv, 
                                 justificatif_path = IFNULL(:path, justificatif_path)");
            $stmt->execute([
                ':id_f' => $id_agent,
                ':id_c' => $id_comp,
                ':niv' => $niveau,
                ':path' => $nom_fichier
            ]);

            $db->commit();
            header("Location: ../views/profil_agent.php?id=" . $id_agent . "&success=1");
            exit();

        } catch (Exception $e) {
            $db->rollBack();
            $message = "Erreur : " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ajouter une Compétence</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { 
            --primary: #0A2540; --secondary: #D4AF37; --bg: #F8FAFC; 
            --white: #FFFFFF; --border: #E2E8F0; --text: #1E293B;
        }
        body { font-family: 'Poppins', sans-serif; background: var(--bg); padding: 40px 20px; color: var(--text); }
        .card { 
            background: var(--white); max-width: 550px; margin: auto; 
            border-radius: 24px; box-shadow: 0 15px 35px rgba(0,0,0,0.08); overflow: hidden; 
        }
        
        /* En-tête de branding de l'application / site */
        .site-branding {
            background: #061727;
            color: #94A3B8;
            padding: 10px 20px;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .site-branding i { color: var(--secondary); }

        .card-header { background: var(--primary); color: white; padding: 25px 30px; text-align: center; }
        .card-header i { font-size: 2rem; color: var(--secondary); margin-bottom: 8px; }
        .card-header h2 { margin: 0; font-size: 1.5rem; }
        .card-body { padding: 30px; }

        .form-group { margin-bottom: 20px; }
        label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 8px; color: var(--primary); }
        input, select { 
            width: 100%; padding: 12px; border: 2px solid var(--border); border-radius: 12px;
            font-family: inherit; transition: 0.3s; box-sizing: border-box;
        }
        input:focus { border-color: var(--secondary); outline: none; }
        
        .btn-submit {
            background: linear-gradient(135deg, var(--primary) 0%, #1a3d5c 100%);
            color: white; border: none; width: 100%; padding: 14px; border-radius: 12px;
            font-weight: 600; cursor: pointer; transition: 0.3s; margin-top: 10px;
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(10,37,64,0.2); }
        .btn-cancel {
            display: block; text-align: center; margin-top: 15px; color: #64748B;
            text-decoration: none; font-size: 0.9rem;
        }
    </style>
</head>
<body>
<?php include '../views/includes/navbar.php'; ?>

<div class="card">
    <!-- BANNIÈRE DU SITE / PLATEFORME -->
    <div class="site-branding">
        <i class="fa-solid fa-landmark"></i>
        <span>Ministère des Affaires Etrangères </span>
    </div>

    <div class="card-header">
        <i class="fa-solid fa-award"></i>
        <h2>Gestion des Compétences</h2>
        <p style="opacity: 0.8; font-size: 0.85rem; margin-top: 4px;">Portail des Agents Fonctionnaires</p>
    </div>
    
    <div class="card-body">
        <?php if(!empty($message)): ?>
            <div style="background: #FEE2E2; color: #B91C1C; padding: 12px; border-radius: 8px; margin-bottom: 20px;">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>Nom de la compétence</label>
                <input type="text" name="competence" placeholder="Ex: Gestion de projet, PHP, Audit..." required>
            </div>

            <div class="form-group">
                <label>Niveau de maîtrise</label>
                <select name="niveau" required>
                    <option value="Débutant">Débutant (Notions de base)</option>
                    <option value="Intermédiaire" selected>Intermédiaire (Pratique régulière)</option>
                    <option value="Avancé">Avancé (Maîtrise complète)</option>
                    <option value="Expert">Expert (Référent technique)</option>
                </select>
            </div>

            <div class="form-group">
                <label>Justificatif (Optionnel - PDF, Image)</label>
                <input type="file" name="justificatif" accept=".pdf,.jpg,.jpeg,.png">
            </div>

            <button type="submit" class="btn-submit">
                <i class="fa-solid fa-check-circle"></i> Enregistrer la compétence
            </button>
            
            <a href="../views/profil_agent.php?id=<?php echo $id_agent; ?>" class="btn-cancel">Annuler et retourner au profil</a>
        </form>
    </div>
</div>

</body>
</html>
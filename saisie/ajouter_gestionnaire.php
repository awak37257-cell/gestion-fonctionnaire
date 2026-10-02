<?php
session_start();
require_once '../config.php';

// Sécurité : seul l'admin central peut accéder à cette page
if (!isset($_SESSION['id_user']) || $_SESSION['rol'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$directions = $db->query("SELECT id_org, nom_org FROM ORGANISATION")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer un Accès Local | Ministère</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght=300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { 
            --primary: #0A2540;
            --primary-light: #1a3d5c;
            --secondary: #D4AF37;
            --white: #FFFFFF;
            --bg-gradient: linear-gradient(135deg, #0A2540 0%, #051a2c 100%);
            --border: #E2E8F0;
        }

        body { 
            font-family: 'Poppins', sans-serif; 
            background: var(--bg-gradient);
            margin: 0; min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            padding: 20px;
        }

        .form-container { 
            width: 100%; max-width: 650px; 
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 24px; 
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            overflow: hidden;
            animation: slideUp 0.5s ease;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .header {
            background: var(--primary);
            padding: 30px; text-align: center; color: white;
            border-bottom: 4px solid var(--secondary);
        }

        .header h2 { margin: 0; font-size: 1.5rem; letter-spacing: 1px; }
        .header p { margin: 5px 0 0; opacity: 0.8; font-size: 0.9rem; }

        .content { padding: 35px; }

        .form-section { 
            margin-bottom: 30px; 
            padding: 20px;
            border: 1px solid var(--border);
            border-radius: 16px;
            background: #F8FAFC;
        }

        .form-section h3 { 
            color: var(--primary); 
            font-size: 1rem; 
            margin-top: 0;
            margin-bottom: 20px; 
            display: flex; 
            align-items: center; 
            gap: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .grid-fields { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 20px; 
        }

        .input-group { margin-bottom: 18px; }

        label { 
            display: block; 
            margin-bottom: 8px; 
            font-size: 0.85rem; 
            font-weight: 600; 
            color: var(--primary);
        }

        input, select { 
            width: 100%; 
            padding: 12px 15px; 
            border: 2px solid var(--border); 
            border-radius: 10px; 
            font-family: inherit;
            transition: 0.3s;
            background: white;
            box-sizing: border-box;
        }

        input:focus, select:focus { 
            border-color: var(--secondary); 
            outline: none; 
            box-shadow: 0 0 0 4px rgba(212, 175, 55, 0.1);
        }

        .btn-submit { 
            width: 100%; 
            padding: 16px; 
            background: var(--primary); 
            color: white; 
            border: none; 
            border-radius: 12px; 
            cursor: pointer; 
            font-weight: 700; 
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: 0.3s;
        }

        .btn-submit:hover { 
            background: var(--primary-light);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }

        .btn-back {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #64748B;
            text-decoration: none;
            font-size: 0.9rem;
            transition: 0.3s;
        }

        .btn-back:hover { color: var(--primary); }

        @media (max-width: 600px) {
            .grid-fields { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="form-container">
    <div class="header">
        <i class="fa-solid fa-user-gear fa-2x" style="color: var(--secondary); margin-bottom: 10px;"></i>
        <h2>CRÉER UN ACCÈS LOCAL</h2>
        <p>Ministère des Affaires Etrangère</p>
    </div>

    <div class="content">
        <form action="../actions/traitement_gest.php" method="POST" novalidate>
            
            <div class="form-section">
                <h3><i class="fa-solid fa-id-card-clip"></i> Identité du Gestionnaire</h3>
                
                <div class="grid-fields">
                    <div class="input-group">
                        <label>Nom de famille</label>
                        <input type="text" name="nom_gest" placeholder="KOUASSI" required>
                    </div>
                    <div class="input-group">
                        <label>Prénoms</label>
                        <input type="text" name="prenom_gest" placeholder="Bryan" required>
                    </div>
                </div>

                <div class="grid-fields">
                    <div class="input-group">
                        <label>Téléphone</label>
                        <input type="text" name="telephone_gest" placeholder="07 01 02 03 04">
                    </div>
                    <div class="input-group">
                        <label>Email Professionnel </label>
                        <input type="email" name="email_gest" placeholder="exemple@ministere.ci" required>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3><i class="fa-solid fa-key"></i> Paramètres de sécurité</h3>
                
                <div class="input-group">
                    <label>Organisation Affectée</label>
                    <select name="id_org" required>
                        <option value="">-- Sélectionner l'organisation --</option>
                        <?php foreach($directions as $dir): ?>
                            <option value="<?= $dir['id_org'] ?>"><?= htmlspecialchars($dir['nom_org']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="input-group">
                    <label>Mot de passe provisoire</label>
                    <input type="password" name="mdp" placeholder="********" required>
                </div>
            </div>

            <button type="submit" name="valider" class="btn-submit">
                <i class="fa-solid fa-check-circle"></i>
                Valider et Activer le Compte
            </button>
            
            <a href="../views/admin_dashboard.php" class="btn-back">
                <i class="fa-solid fa-arrow-left"></i> Retour au tableau de bord
            </a>
        </form>
    </div>
</div>

</body>
</html>
<?php 
session_start();
require_once '../config.php'; 

// Sécurité Admin
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header("Location: saisie/login.php"); exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ministère | Directions & Affectations</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { 
            --primary: #0A2540;
            --primary-dark: #051a2c;
            --primary-light: #1a3d5c;
            --accent: #10B981;
            --accent-dark: #059669;
            --secondary: #F59E0B;
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
            min-height: 100vh;
        }

        .container { 
            max-width: 1300px; 
            margin: 0 auto; 
            padding: 30px 20px;
        }

        /* Header de page */
        .page-header {
            text-align: center;
            margin-bottom: 45px;
        }

        .page-header h1 { 
            color: var(--primary); 
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }

        .page-header h1 i {
            color: var(--secondary);
            font-size: 2rem;
        }

        .page-header p {
            color: var(--text-light);
            font-size: 0.95rem;
        }

        /* Grid layout */
        .grid-layout { 
            display: grid; 
            grid-template-columns: repeat(2, 1fr); 
            gap: 35px; 
        }

        /* Cartes modernisées */
        .card { 
            background: var(--white); 
            padding: 0;
            border-radius: 28px; 
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 45px -12px rgba(0, 0, 0, 0.15);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            padding: 20px 28px;
            position: relative;
        }

        .card-header::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--secondary), var(--accent), var(--secondary));
        }

        .card-header h2 { 
            color: var(--white); 
            font-size: 1.2rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .card-header h2 i {
            color: var(--secondary);
            font-size: 1.3rem;
        }

        .card-body {
            padding: 28px;
        }

        /* Badge d'info */
        .badge-info { 
            background: linear-gradient(135deg, #FEF3C7 0%, #FFFBEB 100%);
            padding: 12px 16px; 
            border-radius: 14px; 
            font-size: 0.8rem; 
            margin-bottom: 25px; 
            color: #92400E;
            border-left: 4px solid var(--secondary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .badge-info i {
            font-size: 1.1rem;
            color: var(--secondary);
        }

        /* Formulaires */
        label { 
            display: block; 
            font-weight: 600; 
            margin-bottom: 8px; 
            font-size: 0.75rem;
            color: var(--text);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        label i {
            margin-right: 6px;
            color: var(--secondary);
        }

        input, select { 
            width: 100%; 
            padding: 12px 14px; 
            margin-bottom: 18px; 
            border: 2px solid var(--border); 
            border-radius: 12px; 
            box-sizing: border-box; 
            font-family: 'Poppins', sans-serif;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            background: var(--white);
        }

        input:hover, select:hover {
            border-color: var(--secondary);
        }

        input:focus, select:focus { 
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
        }

        /* Flex pour les dates */
        .flex-row {
            display: flex;
            gap: 15px;
        }

        .flex-row > div {
            flex: 1;
        }

        /* Boutons */
        button { 
            width: 100%; 
            padding: 14px; 
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white; 
            border: none; 
            border-radius: 14px; 
            cursor: pointer; 
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
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
            box-shadow: 0 10px 25px -5px rgba(10, 37, 64, 0.3);
        }

        button.btn-accent {
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
        }

        button.btn-accent:hover {
            box-shadow: 0 10px 25px -5px rgba(16, 185, 129, 0.3);
        }

        /* Responsive */
        @media (max-width: 900px) {
            .grid-layout { 
                grid-template-columns: 1fr; 
                gap: 25px;
            }
            .container {
                padding: 20px;
            }
            .page-header h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>

<?php include '../views/includes/navbar.php'; ?>

<div class="container">
    <div class="page-header">
        <h1>
            <i class="fa-solid fa-building"></i>
            Configuration des Affectations
        </h1>
        <p>Gestion des organisations et des postes d'affectation</p>
    </div>

    <div class="grid-layout">
        
        <!-- Carte 1 : Ajouter une Organisation -->
        <div class="card">
            <div class="card-header">
                <h2>
                    <i class="fa-solid fa-building-columns"></i>
                    1. Ajouter une Organisation
                </h2>
            </div>
            <div class="card-body">
                <div class="badge-info">
                    <i class="fa-solid fa-circle-info"></i>
                    Inscrire le nom complet et le sigle de l'organisation
                </div>
                
                <form action="../actions/traitement_direction.php" method="POST">
                    <label><i class="fa-solid fa-landmark"></i> Nom de l'Organisation</label>
                    <input type="text" name="nom_org" placeholder="Ex: Organisation des Nations Unies" required>

                    <label><i class="fa-solid fa-abacus"></i> Sigle (Acronyme)</label>
                    <input type="text" name="sigle_org" placeholder="Ex: ONU" required>

                    <label><i class="fa-solid fa-location-dot"></i> Siège Social / Adresse</label>
                    <input type="text" name="siege" placeholder="Ex: New York, USA">

                    <button type="submit">
                        <i class="fa-solid fa-save"></i> Enregistrer l'Organisation
                    </button>
                </form>
            </div>
        </div>

        <!-- Carte 2 : Détails du Poste & Grade -->
        <div class="card">
    <div class="card-header">
        <h2>
            <i class="fa-solid fa-briefcase"></i>
            2. Créer une Affectation (Poste Type)
        </h2>
    </div>
    <div class="card-body">
        <div class="badge-info">
            <i class="fa-solid fa-circle-info"></i>
            Définissez ici un poste type. Le lien avec l'organisation se fera lors de l'affectation de l'agent.
        </div>
        
        <form action="../actions/traitement_affectation.php" method="POST">
            <label><i class="fa-solid fa-bullhorn"></i> Titre du poste</label>
            <input type="text" name="tit_post" placeholder="Ex: Premier Secrétaire, Consultant" required>

            <label><i class="fa-solid fa-city"></i> Ville de la mission</label>
            <input type="text" name="vil_affec" placeholder="Ex: Paris, New York, Abidjan" required>

            <label><i class="fa-solid fa-tag"></i> Grade du poste (Sigle)</label>
            <input type="text" name="gra_post" placeholder="Ex: A4, P3, HC" required>

            <label><i class="fa-solid fa-chart-line"></i> Statut du poste</label>
            <select name="sta_post" required>
                <optgroup label="Staff Régulier">
                    <option value="International (P)">International Professional (P)</option>
                    <option value="Local (GS)">Local General Service (GS)</option>
                    <option value="Fixed-Term">Fixed-Term Appointment</option>
                </optgroup>
                <optgroup label="Autre personnel">
                    <option value="Consultant">International Consultant</option>
                    <option value="Volunteer">Volunteer</option>
                    <option value="Short-Term">Short-Term Contract</option>
                </optgroup>
            </select>

            <button type="submit" class="btn-accent">
                <i class="fa-solid fa-plus-circle"></i> Enregistrer le Modèle de Poste
            </button>
        </form>
    </div>
</div>

    </div>
</div>

</body>
</html>
<?php 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config.php'; 

// Récupération des paramètres et gestion du rôle
$role = isset($_SESSION['rol']) ? trim($_SESSION['rol']) : '';
$id_session = isset($_SESSION['id_fonc']) ? (int)$_SESSION['id_fonc'] : 0;
$id_agent = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Valeur par défaut de la section
$section = isset($_GET['section']) ? trim($_GET['section']) : 'profil'; 

// Requête principale pour les informations de base de l'agent
// Requête principale pour les informations de base de l'agent
$agent = null;
if ($id_agent) {
    // Ajout de ORDER BY r.date_debut DESC pour avoir la plus récente 
    // et LIMIT 1 pour n'en garder qu'une seule
    $sql = "SELECT f.*, o.nom_org, o.sigle_org, a.gra_post, a.vil_affec, r.ville_affec
FROM FONCTIONNAIRE f
JOIN RECEVOIR r ON f.id_fonc = r.id_fonc
JOIN ORGANISATION o ON r.id_org = o.id_org
JOIN AFFECTATION a ON r.id_affec = a.id_affec
WHERE f.id_fonc = :id_f
  AND r.date_fin IS NULL  -- <--- C'EST CETTE LIGNE QUI MANQUE"; 

    $stmt = $db->prepare($sql);
    $stmt->execute([':id_f' => $id_agent]); 
    $agent = $stmt->fetch();
}

// Vérification des droits
$est_mon_profil = ($id_session === $id_agent);
$est_admin = in_array($role, ['admin', 'admin_local']);
$peut_modifier = ($est_admin || $est_mon_profil);
// Statistiques pour l'agent
if ($agent) {
    $stats = [];
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM DIPLOME d JOIN OBTENIR o ON d.id_dipl = o.id_dipl WHERE o.id_fonc = ?");
    $stmt->execute([$id_agent]);
    $stats['diplomes'] = $stmt->fetchColumn();
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM competences c JOIN detenir d ON c.id_comp = d.id_comp WHERE d.id_fonc = ?");
    $stmt->execute([$id_agent]);
    $stats['competences'] = $stmt->fetchColumn();
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM recevoir WHERE id_fonc = ?");
    $stmt->execute([$id_agent]);
    $stats['mutations'] = $stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion Agent | <?php echo htmlspecialchars($agent['nom_fonc'] ?? 'Introuvable'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { 
            --primary: #0A2540;
            --primary-dark: #051a2c;
            --primary-light: #1a3d5c;
            --secondary: #D4AF37;
            --secondary-dark: #b8960c;
            --bg: #F8FAFC;
            --white: #FFFFFF;
            --text: #1E293B;
            --text-light: #64748B;
            --border: #E2E8F0;
            --sidebar-width: 280px;
            --success: #10B981;
            --danger: #EF4444;
            --info: #3B82F6;
            --warning: #F59E0B;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 10px 25px -5px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 20px 35px -10px rgba(0, 0, 0, 0.15);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: 'Poppins', sans-serif; 
            background: linear-gradient(135deg, var(--bg) 0%, #F1F5F9 100%);
            color: var(--text); 
            display: flex; 
            min-height: 100vh;
        }

        /* --- SIDEBAR --- */
        .sidebar { 
            width: var(--sidebar-width); 
            background: linear-gradient(180deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white; 
            position: fixed; 
            height: 100vh; 
            padding: 30px 20px; 
            display: flex; 
            flex-direction: column;
            box-shadow: 4px 0 20px rgba(0,0,0,0.1); 
            z-index: 100;
            transition: all 0.3s ease;
        }
        
        .logo-box { 
            text-align: center; 
            margin-bottom: 40px; 
        }
        
        .logo-box img { 
            width: 70px; 
            border-radius: 50%; 
            margin-bottom: 15px; 
            border: 3px solid var(--secondary);
            transition: transform 0.3s ease;
        }
        
        .logo-box:hover img {
            transform: scale(1.05);
        }
        
        .logo-box h1 { 
            font-size: 0.7rem; 
            letter-spacing: 2px; 
            color: var(--secondary); 
            line-height: 1.4;
            font-weight: 700;
        }

        .nav-links { 
            list-style: none; 
            flex-grow: 0; 
        }
        
        .nav-links li { 
            margin-bottom: 8px; 
        }
        
        .nav-links a { 
            color: rgba(255,255,255,0.7); 
            text-decoration: none; 
            display: flex; 
            align-items: center; 
            gap: 15px; 
            padding: 12px 18px; 
            border-radius: 12px; 
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 0.85rem;
            font-weight: 500;
            position: relative;
            overflow: hidden;
        }
        
        .nav-links a::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 0;
            height: 100%;
            background: rgba(212, 175, 55, 0.1);
            transition: width 0.3s ease;
        }
        
        .nav-links a:hover::before,
        .nav-links a.active::before {
            width: 100%;
        }
        
        .nav-links a:hover, 
        .nav-links a.active { 
            background: rgba(255,255,255,0.08); 
            color: var(--secondary);
            transform: translateX(5px);
        }
        
        .nav-links a i {
            width: 20px;
            transition: transform 0.3s ease;
        }
        
        .nav-links a:hover i {
            transform: scale(1.1);
        }
        
        hr { 
            opacity: 0.1; 
            margin: 15px 0; 
        }
        
        .logout-btn { 
            color: var(--danger) !important; 
        }
        
        .logout-btn:hover {
            background: rgba(239, 68, 68, 0.1) !important;
            color: #FCA5A5 !important;
        }

        /* --- CONTENT --- */
        .main-content { 
            margin-left: var(--sidebar-width); 
            width: calc(100% - var(--sidebar-width)); 
            padding: 40px 50px;
            animation: fadeIn 0.5s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .page-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 35px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .header-title h2 { 
            font-size: 1.6rem; 
            color: var(--primary); 
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        
        .header-title p { 
            color: var(--text-light); 
            font-size: 0.85rem; 
            margin-top: 5px;
        }
        
        .header-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        /* Boutons améliorés */
        .btn-primary, .btn-secondary, .btn-danger, .btn-outline {
            padding: 10px 22px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: none;
            cursor: pointer;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--secondary) 0%, var(--secondary-dark) 100%);
            color: var(--primary);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(212, 175, 55, 0.3);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, var(--info) 0%, #2563EB 100%);
            color: white;
        }
        
        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.3);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, var(--danger), #DC2626);
            color: white;
        }
        .btn-warning-edit {
    padding: 6px 14px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.8rem;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: none;
    cursor: pointer;
    background: #FFFBEB; /* Fond jaune très clair et doux */
    color: #D97706;      /* Texte orange/ambre foncé pour le contraste */
    border: 1px solid #FDE68A;
}

.btn-warning-edit:hover {
    background: linear-gradient(135deg, var(--warning) 0%, #D97706 100%);
    color: var(--white);
    border-color: transparent;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.2);
}

.btn-warning-edit i {
    font-size: 0.85rem;
}
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(239, 68, 68, 0.3);
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid var(--border);
            color: var(--text-light);
        }
        
        .btn-outline:hover {
            border-color: var(--secondary);
            color: var(--secondary);
            transform: translateY(-2px);
        }

        /* Cartes */
        .card { 
            background: var(--white); 
            border-radius: 24px; 
            padding: 30px; 
            box-shadow: var(--shadow-md);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid var(--border);
        }
        
        .card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border);
        }
        
        .card-header h3 {
            font-size: 1.2rem;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-header i {
            color: var(--secondary);
        }
        
        .info-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); 
            gap: 20px; 
        }
        
        .info-item { 
            background: linear-gradient(135deg, #F8FAFC, #F1F5F9);
            padding: 20px; 
            border-radius: 20px; 
            transition: all 0.3s ease;
            border: 1px solid var(--border);
        }
        
        .info-item:hover {
            transform: translateX(5px);
            border-color: var(--secondary);
        }
        
        .info-item label { 
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.7rem; 
            font-weight: 700; 
            color: var(--text-light); 
            text-transform: uppercase; 
            letter-spacing: 0.5px;
            margin-bottom: 10px; 
        }
        
        .info-item label i {
            color: var(--secondary);
        }
        
        .info-item p { 
            font-weight: 600; 
            color: var(--primary); 
            font-size: 1rem; 
        }
        
        /* Tables modernes */
        .table-wrapper {
            overflow-x: auto;
            border-radius: 16px;
        }
        
        table { 
            width: 100%; 
            border-collapse: separate; 
            border-spacing: 0 12px; 
        }
        
        th { 
            text-align: left; 
            padding: 12px 20px;
            background: var(--bg);
            color: var(--text-light); 
            font-size: 0.75rem; 
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        
        td { 
            padding: 16px 20px; 
            background: var(--white);
            border-bottom: 1px solid var(--border);
            transition: all 0.2s ease;
        }
        
        tr:hover td {
            background: #F8FAFC;
            transform: scale(1.01);
        }
        
        /* Badges */
        .badge { 
            padding: 5px 12px; 
            border-radius: 20px; 
            font-weight: 600; 
            font-size: 0.7rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .badge-primary { background: #FEF3C7; color: #92400E; }
        .badge-info { background: #DBEAFE; color: #1E40AF; }
        .badge-success { background: #D1FAE5; color: #065F46; }
        .badge-warning { background: #EDE9FE; color: #6D28D9; }
        .badge-danger { background: #FEE2E2; color: #991B1B; }

        /* Profil Container */
        .profile-container { 
            display: grid; 
            grid-template-columns: 320px 1fr; 
            gap: 35px; 
        }
        /* --- Boutons d'Action du Tableau --- */
.btn-warning-edit, .btn-danger-delete {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s ease-in-out;
}

/* Bouton Modifier (Teinte dorée/jaune discrète) */
.btn-warning-edit {
    background-color: #FEF3C7; /* Fond jaune très clair */
    color: #D97706;            /* Texte orange foncé/doré */
    border: 1px solid #FCD34D;
}

.btn-warning-edit:hover {
    background-color: #FDE68A;
    transform: translateY(-1px);
}

/* Bouton Supprimer (Teinte rouge doux) */
.btn-danger-delete {
    background-color: #FEE2E2; /* Fond rouge très clair */
    color: #EF4444;            /* Texte rouge */
    border: 1px solid #FCA5A5;
}

.btn-danger-delete:hover {
    background-color: #FCA5A5;
    color: #B91C1C;
    transform: translateY(-1px);
}

/* Conteneur d'actions pour bien aligner */
td style {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
}
        .avatar-card {
            text-align: center;
            position: sticky;
            top: 20px;
            align-self: start;   /* Dit à la carte : "Ne t'étire pas par rapport à la droite" */
    height: fit-content;
        }
        /* Style de base du bouton */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px; /* Espace entre l'icône + et le texte */
    background: linear-gradient(135deg, #D4AF37, #b8960c); /* Dégradé doré */
    color: #0A2540; /* Texte bleu nuit pour un excellent contraste */
    font-family: 'Poppins', sans-serif;
    font-size: 0.9rem;
    font-weight: 600;
    padding: 10px 20px;
    border-radius: 10px;
    text-decoration: none; /* Supprime le soulignement du lien */
    box-shadow: 0 4px 10px rgba(212, 175, 55, 0.2);
    border: 1px solid rgba(212, 175, 55, 0.4);
    cursor: pointer;
    transition: all 0.3s ease;
}

/* Effet au survol (Hover) */
.btn:hover {
    transform: translateY(-2px); /* Légère impulsion vers le haut */
    box-shadow: 0 6px 20px rgba(212, 175, 55, 0.4);
    background: linear-gradient(135deg, #e5c158, #D4AF37); /* Doré légèrement plus lumineux */
    color: #0A2540;
}

/* Effet au clic (Active) */
.btn:active {
    transform: translateY(1px); /* Effet d'enfoncement */
    box-shadow: 0 2px 5px rgba(212, 175, 55, 0.2);
}

/* Style de l'icône à l'intérieur du bouton */
.btn i {
    font-size: 0.95rem;
    transition: transform 0.3s ease;
}

/* Optionnel : Petite rotation de l'icône + au survol pour donner de la vie */
.btn:hover i {
    transform: rotate(90deg);
}
        .avatar-circle { 
            width: 150px; 
            height: 150px; 
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            border-radius: 50%; 
            margin: 0 auto 20px; 
            display: flex; 
            align-items: center; 
            justify-content: center;
            color: var(--secondary); 
            font-size: 3.5rem;
            transition: transform 0.3s ease;
            overflow: hidden;
            border: 4px solid var(--white);
            box-shadow: var(--shadow-md);
        }
        
        .avatar-card:hover .avatar-circle {
            transform: scale(1.05);
        }
        
        .avatar-circle img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .stats-mini {
            display: flex;
            justify-content: space-around;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 2px solid var(--border);
        }
        
        .stat-mini {
            text-align: center;
            flex: 1;
        }
        
        .stat-mini .number {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--secondary);
        }
        
        .stat-mini .label {
            font-size: 0.7rem;
            color: var(--text-light);
            text-transform: uppercase;
            font-weight: 600;
        }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px;
            color: var(--text-light);
        }
        .back-nav-inline {
    margin-bottom: 25px; /* Donne de l'air avant le titre */
    display: flex;
    justify-content: flex-start;
}

.btn-back-inline {
    display: inline-flex;
    align-items: center;
    text-decoration: none;
    color: #003366;
    font-family: 'Poppins', sans-serif;
    font-weight: 600;
    background: #ffffff;
    padding: 10px 20px;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    border: 1px solid rgba(0,51,102,0.15);
    transition: all 0.2s ease-in-out;
}

.btn-back-inline:hover {
    background: #003366;
    color: #ffffff;
    transform: translateX(-4px); /* Petit glissement dynamique vers la gauche */
    box-shadow: 0 6px 15px rgba(0,51,102,0.15);
}

.btn-back-inline i {
    margin-right: 10px;
    font-size: 1rem;
}
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        /* Liens de document */
        .btn-view {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            background: #E3F2FD;
            color: #1976D2;
            text-decoration: none;
            border-radius: 8px;
            font-size: 0.85rem;
            transition: all 0.3s ease;
        }
        
        .btn-view:hover {
            background: #1976D2;
            color: white;
            transform: translateY(-1px);
        }
        
        .text-muted {
            color: var(--text-light);
            font-style: italic;
            font-size: 0.85rem;
        }
        
        /* Menu toggle mobile */
        #menuToggle {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 200;
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 12px;
            display: none;
            cursor: pointer;
            box-shadow: var(--shadow-md);
        }
        
        /* Print styles */
        @media print {
            .sidebar, .btn-primary, .btn-secondary, .btn-danger, .btn-outline, #menuToggle, .header-actions {
                display: none !important;
            }
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 20px;
            }
            .card {
                box-shadow: none;
                border: 1px solid #ddd;
                break-inside: avoid;
            }
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .profile-container {
               display: grid; 
    grid-template-columns: 320px 1fr; 
    gap: 35px; 
    align-items: start; /* Empêche l'avatar de s'étirer */
    
    /* --- LA CORRECTION POUR LA HAUTEUR --- */
    height: fit-content;
    align-self: start;
            }
            .main-content {
                padding: 30px;
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            .sidebar.open {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 20px;
                padding-top: 70px;
            }
            .info-grid {
                grid-template-columns: 1fr;
            }
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            #menuToggle {
                display: block;
            }
        }
    </style>
</head>
<body>

<aside class="sidebar" id="sidebar">
    <div class="logo-box">
        <img src="../public/images.jpg" alt="Logo">
        <h1>MINISTÈRE DE LA<br>FONCTION PUBLIQUE</h1>
    </div>

    <ul class="nav-links">
        
        <li>
            <a href="?id=<?php echo $id_agent; ?>&section=profil" class="<?php echo ($section == 'profil') ? 'active' : ''; ?>">
                <i class="fa-solid fa-id-card"></i> Fiche Agent
            </a>
        </li>
        <li>
            <a href="?id=<?php echo $id_agent; ?>&section=diplomes" class="<?php echo ($section == 'diplomes') ? 'active' : ''; ?>">
                <i class="fa-solid fa-graduation-cap"></i> Diplômes
                <span class="badge badge-info" style="margin-left: auto;"><?php echo $stats['diplomes'] ?? 0; ?></span>
            </a>
        </li>
        <li>
            <a href="?id=<?php echo $id_agent; ?>&section=competences" class="<?php echo ($section == 'competences') ? 'active' : ''; ?>">
                <i class="fa-solid fa-award"></i> Compétences
                <span class="badge badge-warning" style="margin-left: auto;"><?php echo $stats['competences'] ?? 0; ?></span>
            </a>
        </li>
        <li>
            <a href="?id=<?php echo $id_agent; ?>&section=mutations" class="<?php echo ($section == 'mutations') ? 'active' : ''; ?>">
                <i class="fa-solid fa-route"></i> Parcours
                <span class="badge badge-success" style="margin-left: auto;"><?php echo $stats['mutations'] ?? 0; ?></span>
            </a>
        </li>
        <hr>
        <li><a href="modifier_mdp.php"><i class="fa-solid fa-lock"></i> Sécurité</a></li>
        <li><a href="../logout.php" class="logout-btn"><i class="fa-solid fa-power-off"></i> Déconnexion</a></li>
    </ul>
</aside>

<main class="main-content">
    <button id="menuToggle">
        <i class="fa-solid fa-bars"></i>
    </button>
  <?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Récupération et nettoyage du rôle
$role = isset($_SESSION['rol']) ? strtolower(trim($_SESSION['rol'])) : ''; 

// 2. Détermination du lien de retour UNIQUEMENT si c'est un admin ou admin_local
$afficher_bouton = false;
$lien_retour = "";

if ($role === 'admin' || $role === 'admin_local') {
    $afficher_bouton = true;
    $lien_retour = "/fonctionnaire_ministere/views/admin_dashboard.php";
}
?>

<?php if ($afficher_bouton): ?>
    <div class="back-nav-inline">
        <a href="<?= htmlspecialchars($lien_retour) ?>" class="btn-back-inline">
            <i class="fa-solid fa-arrow-left"></i> Retour au Dashboard
        </a>
    </div>
<?php endif; ?>

    <?php if ($agent) : ?>
        <?php switch($section): 
            case 'diplomes':
                $stmt = $db->prepare("
                    SELECT d.id_dipl, d.lib_dipl, d.niv_etu, d.fich_url, o.ann_obte 
                    FROM DIPLOME d 
                    JOIN OBTENIR o ON d.id_dipl = o.id_dipl 
                    WHERE o.id_fonc = :id
                    ORDER BY o.ann_obte DESC
                ");
                $stmt->execute([':id' => $id_agent]);
                $diplomes = $stmt->fetchAll();
            ?> 
                <div class="page-header">
                    <div class="header-title">
                        <h2><i class="fa-solid fa-graduation-cap"></i> Diplômes et Certifications</h2>
                        <p>Liste de tous les diplômes obtenus par l'agent</p>
                    </div>
                    <div class="header-actions">
                        <a href="../saisie/ajouter_diplome.php?id=<?= $agent['id_fonc']; ?>" class="btn">
    <i class="fa-solid fa-plus"></i> Ajouter un diplôme
</a>
                    </div>
                </div>
                
                <div class="card">
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Libellé du Diplôme</th>
                                    <th>Niveau d'étude</th>
                                    <th>Année d'obtention</th>
                                    <th style="text-align: center;">Justificatif</th>
                                    <?php if ($peut_modifier): ?>
                                        <th style="text-align: center;">Actions</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($diplomes)): 
                                    foreach ($diplomes as $diplome): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($diplome['lib_dipl']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($diplome['niv_etu']); ?></td>
                                            <td><?php echo htmlspecialchars($diplome['ann_obte']); ?></td>
                                            <td style="text-align: center;">
                                                <?php if (!empty($diplome['fich_url'])): ?>
                                                    <a href="../uploads/diplomes/<?php echo htmlspecialchars($diplome['fich_url']); ?>" 
                                                       target="_blank" 
                                                       class="btn-view">
                                                        <i class="fa-solid fa-file-pdf"></i> Voir le PDF
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">Non fourni</span>
                                                <?php endif; ?>
                                            </td>
                                            <?php if ($peut_modifier): ?>
                                                <td style="text-align: center;">
                                                    <a href="../saisie/modifier_diplome.php?id_agent=<?php echo $id_agent; ?>&id_dipl=<?php echo $diplome['id_dipl']; ?>" class="btn-warning-edit">
                                                        <i class="fa-solid fa-pen-to-square"></i> Modifier
                                                    </a>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; 
                                else: ?>
                                    <tr>
                                        <td colspan="4">
                                            <div class="empty-state">
                                                <i class="fa-solid fa-folder-open"></i>
                                                <p>Aucun diplôme enregistré pour cet agent</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php break; ?>

            <?php case 'competences': ?>
                <div class="page-header">
                    <div class="header-title">
                        <h2><i class="fa-solid fa-award"></i> Expertises Techniques</h2>
                        <p>Compétences validées par l'agent</p>
                    </div>
                   <?php if($peut_modifier): ?>
    <div class="header-actions">
        <a href="../saisie/ajouter_competence.php?id=<?php echo $id_agent; ?>" class="btn-primary">
            <i class="fa-solid fa-plus"></i> Ajouter une compétence
        </a>
    </div>
<?php endif; ?>
                </div>
                
                <div class="card">
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Compétence</th>
                                    <th>Niveau de maîtrise</th>
                                    <th>Justificatif</th>
                                    <?php if ($peut_modifier): ?>
                                        <th style="text-align: center;">Actions</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                try {
                                    $stmt = $db->prepare("SELECT c.id_comp, c.libelle_comp, d.niveau, d.justificatif_path 
                                                         FROM competences c 
                                                         JOIN detenir d ON c.id_comp = d.id_comp 
                                                         WHERE d.id_fonc = :id");
                                    $stmt->execute([':id' => $id_agent]);
                                    $comps = $stmt->fetchAll();
                                    
                                    if($comps): foreach($comps as $c): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($c['libelle_comp']); ?></strong></td>
                                            <td><span class="badge badge-primary"><?php echo htmlspecialchars($c['niveau']); ?></span></td>
                                            <td>
                                                <?php if(!empty($c['justificatif_path'])): ?>
                                                    <a href="../uploads/<?php echo $c['justificatif_path']; ?>" target="_blank" class="btn-view">
                                                        <i class="fa-solid fa-file-pdf"></i> Voir
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <?php if ($peut_modifier): ?>
                                                <td style="text-align: center;">
                                                    <a href="../saisie/modifier_competence.php?id_agent=<?php echo $id_agent; ?>&id_comp=<?php echo $c['id_comp']; ?>" class="btn-warning-edit">
                                                        <i class="fa-solid fa-pen-to-square"></i> Modifier
                                                    </a>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; else: ?>
                                        <tr>
                                            <td colspan="<?php echo ($est_admin || $est_mon_profil) ? 4 : 3; ?>">
                                                <div class="empty-state">
                                                    <i class="fa-solid fa-award"></i>
                                                    <p>Aucune compétence enregistrée</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; 
                                } catch (PDOException $e) {
                                    echo "<tr><td colspan='4' style='color: red;'>Erreur : " . $e->getMessage() . "</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php break; ?>

            <?php case 'mutations': ?>
    <div class="page-header">
        <div class="header-title">
            <h2><i class="fa-solid fa-route"></i> Historique de Carrière</h2>
            <p>Suivi des mouvements et affectations</p>
        </div>
        <?php if($est_admin || $est_mon_profil): ?>
            <div class="header-actions">
                <a href="../saisie/muter_agent.php?id=<?php echo $id_agent; ?>" class="btn-primary">
                    <i class="fa-solid fa-paper-plane"></i> Nouvelle mutation
                </a>
            </div>
        <?php endif; ?>
    </div>
    
   <div class="card">
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Organisation</th>
                    <th>Poste / Grade</th>
                    <th>Ville</th>
                    <th>Période</th>
                    <th style="text-align: center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Requête SQLite compatible (sans DATE_FORMAT)
                $stmt = $db->prepare("
                    SELECT r.id_rec, o.nom_org, a.gra_post, a.tit_post, a.vil_affec, r.date_debut, r.date_fin
                    FROM RECEVOIR r 
                    JOIN AFFECTATION a ON r.id_affec = a.id_affec 
                    JOIN ORGANISATION o ON r.id_org = o.id_org 
                    WHERE r.id_fonc = :id
                    ORDER BY r.date_debut DESC
                ");
                $stmt->execute([':id' => $id_agent]);
                $historique = $stmt->fetchAll();
                
                if($historique): 
                    foreach($historique as $h): 
                        // Logique PHP pour la date de fin
                        $date_fin_affichable = (empty($h['date_fin'])) ? 'En cours' : date('d/m/Y', strtotime($h['date_fin']));
                ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($h['nom_org']); ?></strong></td>
                            <td>
                                <?php echo htmlspecialchars($h['gra_post']); ?>
                                <?php if($h['tit_post']): ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($h['tit_post']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($h['vil_affec'] ?? 'Non spécifiée'); ?></td>
                            <td>
                                <i class="fa-regular fa-calendar"></i>
                                <?php echo date('d/m/Y', strtotime($h['date_debut'])); ?> 
                                <i class="fa-solid fa-arrow-right"></i> 
                                <?php if ($date_fin_affichable === 'En cours'): ?>
                                    <span class="badge badge-success">En cours</span>
                                <?php else: ?>
                                    <?php echo htmlspecialchars($date_fin_affichable); ?>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center; white-space: nowrap; gap: 8px;">
                                <!-- Bouton Modifier -->
                                <a href="../saisie/modifier_parcours.php?id_rec=<?= $h['id_rec'] ?>&id_agent=<?= $id_agent ?>" class="btn-warning-edit" title="Modifier">
                                    <i class="fa-solid fa-pen-to-square"></i> Modifier
                                </a>
                                
                                <!-- Bouton Supprimer -->
                                <a href="../saisie/supprimer_parcours.php?id_rec=<?= $h['id_rec'] ?>&id_agent=<?= $id_agent ?>" class="btn-danger-delete" title="Supprimer" onclick="return confirm('Confirmez-vous la suppression de ce parcours pour corriger une erreur ?');">
                                    <i class="fa-solid fa-trash"></i> Supprimer
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; 
                else: ?>
                    <tr>
                        <td colspan="5">
                            <div class="empty-state">
                                <i class="fa-solid fa-route"></i>
                                <p>Aucun historique de carrière trouvé.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php break; ?>

            <?php default: ?>
                <div class="page-header">
                    <div class="header-title">
                        <h2><i class="fa-solid fa-user-tie"></i> Espace Fonctionnaire</h2>
                        <p>Consultez vos informations personnelles et professionnelles</p>
                    </div>
                    <div class="header-actions">
                        <button onclick="window.print()" class="btn-outline">
                            <i class="fa-solid fa-print"></i> Imprimer
                        </button>
                        <?php if($est_admin || $est_mon_profil): ?>
                            <a href="modifier_agent.php?id=<?php echo $id_agent; ?>" class="btn-primary">
                                <i class="fa-solid fa-pen-to-square"></i> Modifier le profil
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="profile-container">
                    <!-- Colonne gauche : Avatar et statistiques -->
                    <div class="avatar-card card">
                        <div class="avatar-circle">
                            <?php if (!empty($agent['photo_fonc'])): ?>
                                <img src="../uploads/photos/<?php echo htmlspecialchars($agent['photo_fonc']); ?>" alt="Photo de profil">
                            <?php else: ?>
                                <i class="fa-solid fa-user"></i>
                            <?php endif; ?>
                        </div>
                        <h3 style="color: var(--primary); margin-bottom: 5px;">
                            <?php echo htmlspecialchars($agent['prenom_fonc'] . ' ' . $agent['nom_fonc']); ?>
                        </h3>
                        <p style="color: var(--text-light); font-size: 0.85rem;">
                            Matricule : <strong><?php echo htmlspecialchars($agent['matri']); ?></strong>
                        </p>
                        
                        <div class="stats-mini">
                            <div class="stat-mini">
                                <div class="number"><?php echo $stats['diplomes'] ?? 0; ?></div>
                                <div class="label">Diplômes</div>
                            </div>
                            <div class="stat-mini">
                                <div class="number"><?php echo $stats['competences'] ?? 0; ?></div>
                                <div class="label">Compétences</div>
                            </div>
                            <div class="stat-mini">
                                <div class="number"><?php echo $stats['mutations'] ?? 0; ?></div>
                                <div class="label">Mutations</div>
                            </div>
                        </div>
                    </div>

                    <!-- Colonne droite : Informations détaillées -->
                    <div style="display: flex; flex-direction: column; gap: 25px;">
                        <!-- Données personnelles -->
                        <div class="card">
                            <div class="card-header">
                                <h3>
                                    <i class="fa-solid fa-address-card"></i>
                                    Données Personnelles
                                </h3>
                            </div>
                            <div class="info-grid">
                                <div class="info-item">
                                    <label><i class="fa-solid fa-user"></i> Nom complet</label>
                                    <p><?php echo htmlspecialchars($agent['nom_fonc'] . ' ' . $agent['prenom_fonc']); ?></p>
                                </div>
                                <div class="info-item">
                                    <label><i class="fa-solid fa-envelope"></i> Email</label>
                                    <p><?php echo htmlspecialchars($agent['email_perso'] ?? 'Non renseigné'); ?></p>
                                </div>
                                <div class="info-item">
                                    <label><i class="fa-solid fa-phone"></i> Téléphone</label>
                                    <p><?php echo htmlspecialchars($agent['cont'] ?? 'Non renseigné'); ?></p>
                                </div>
                                <div class="info-item">
                                    <label><i class="fa-solid fa-calendar"></i> Date de naissance</label>
                                    <p><?php echo isset($agent['date_naiss']) ? date('d/m/Y', strtotime($agent['date_naiss'])) : 'Non renseignée'; ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- Informations professionnelles -->
                        <div class="card">
                            <div class="card-header">
                                <h3>
                                    <i class="fa-solid fa-briefcase"></i>
                                    Situation Professionnelle
                                </h3>
                            </div>
                            <div class="info-grid">
                                <div class="info-item">
                                    <label><i class="fa-solid fa-building"></i> Organisation</label>
                                    <p><?php echo htmlspecialchars($agent['nom_org'] ?? 'Non affecté'); ?></p>
                                    <?php if($agent['sigle_org']): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars($agent['sigle_org']); ?></small>
                                    <?php endif; ?>
                                </div>
                                <div class="info-item">
                                    <label><i class="fa-solid fa-chart-line"></i> Grade</label>
                                    <p><span class="badge badge-primary"><?php echo htmlspecialchars($agent['gra_post'] ?? 'Non défini'); ?></span></p>
                                </div>
                                <div class="info-item">
                                    <label><i class="fa-solid fa-location-dot"></i> Ville d'affectation</label>
                                    <p><?php echo htmlspecialchars($agent['ville_affec'] ?? 'Non spécifiée'); ?></p>
                                </div>
                                <div class="info-item">
                                    <label><i class="fa-solid fa-calendar-check"></i> Date d'entrée</label>
                                    <p><?php echo isset($agent['date_debut']) ? date('d/m/Y', strtotime($agent['date_debut'])) : 'Non renseignée'; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php break; ?>
        <?php endswitch; ?>
    <?php else: ?>
        <div class="card" style="text-align: center; padding: 80px;">
            <i class="fa-solid fa-user-slash" style="font-size: 4rem; color: var(--text-light); margin-bottom: 20px;"></i>
            <h2 style="margin-bottom: 15px; color: var(--primary);">Agent introuvable</h2>
            <p style="margin-bottom: 30px; color: var(--text-light);">
                L'agent que vous recherchez n'existe pas ou a été supprimé.
            </p>
           
        </div>
    <?php endif; ?>
</main>

<script>
const searchInput = document.getElementById('globalSearch');
const filterSelect = document.getElementById('filterCategory');
const tableBody = document.querySelector('#agentTable tbody'); // Assurez-vous d'avoir cet ID sur votre <tbody>

if (searchInput) {
    searchInput.addEventListener('keyup', function() {
        const query = this.value;
        const category = filterSelect.value;

        // On n'interroge la base que si on a au moins 2 caractères (optionnel, pour la performance)
        if (query.length >= 2 || query.length === 0) {
            fetch(`recherche_ajax.php?q=${query}&cat=${category}`)
                .then(response => response.json())
                .then(data => {
                    // On vide le tableau actuel
                    tableBody.innerHTML = "";

                    // On reconstruit les lignes avec les résultats de la base
                    data.forEach(f => {
                        const row = `
                            <tr>
                                <td>${f.matricule}</td>
                                <td>${f.nom} ${f.prenom}</td>
                                <td class="col-competence">${f.competence}</td>
                                <td class="col-diplome">${f.diplome}</td>
                                <td>
                                    <button class="btn-view">Voir</button>
                                </td>
                            </tr>`;
                        tableBody.innerHTML += row;
                    });
                });
        }
    });
}
</script>
</body>
</html>
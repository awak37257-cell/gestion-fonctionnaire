<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config.php';

// 1. VÉRIFICATION DE LA SESSION ET DES DROITS
if (!isset($_SESSION['rol'])) {
    header("Location: ../saisie/login.php");
    exit();
}

$user_role = $_SESSION['rol'];
$user_org_id = $_SESSION['id_org'] ?? null;

// Vérification de sécurité : Seuls les admins (global ou local) peuvent accéder
if (!in_array($user_role, ['admin', 'admin_local'])) {
    header("Location: ../saisie/login.php");
    exit();
}

// 2. RÉCUPÉRATION DES PARAMÈTRES DE RECHERCHE, FILTRE ET PAGINATION
$search_query   = isset($_GET['search']) ? trim($_GET['search']) : '';
$org_filter     = isset($_GET['org']) ? trim($_GET['org']) : '';
$org_search     = isset($_GET['org_search']) ? trim($_GET['org_search']) : '';
$page           = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page       = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], [5, 10, 25, 50, 100]) ? (int)$_GET['per_page'] : 10;

// Nom de l'organisation pour l'affichage de la barre supérieure
$nom_organisation_affichage = "Administration Centrale";
if ($user_role === 'admin_local' && $user_org_id) {
    $stmtOrg = $db->prepare("SELECT nom_org FROM ORGANISATION WHERE id_org = ?");
    $stmtOrg->execute([$user_org_id]);
    $resOrg = $stmtOrg->fetch();
    if ($resOrg) $nom_organisation_affichage = $resOrg['nom_org'];
    // Un admin local ne peut filtrer que sur sa propre organisation
    $org_filter = $user_org_id;
}

// 3. RÉCUPÉRATION DE TOUTES LES ORGANISATIONS POUR LE SÉLECTEUR DE FILTRE
$liste_organisations = $db->query("
    SELECT o.id_org, o.nom_org, o.sigle_org, COUNT(r.id_fonc) as nb_agents
    FROM ORGANISATION o
    LEFT JOIN RECEVOIR r ON o.id_org = r.id_org AND r.date_fin IS NULL
    GROUP BY o.id_org, o.nom_org, o.sigle_org
    ORDER BY o.nom_org ASC
")->fetchAll();

// 4. CONSTRUCTION DYNAMIQUE DE LA REQUÊTE SQL AVEC PRÉPARATION
$where_clauses = ["1=1"];
$params = [];

// Restriction si admin local
if ($user_role === 'admin_local' && $user_org_id) {
    $where_clauses[] = "r.id_org = ?";
    $params[] = $user_org_id;
} elseif ($user_role === 'admin') {
    // Filtre par ID d'organisation sélectionné
    if (!empty($org_filter)) {
        if ($org_filter === 'none') {
            $where_clauses[] = "r.id_org IS NULL";
        } else {
            $where_clauses[] = "r.id_org = ?";
            $params[] = $org_filter;
        }
    }
    // Barre de recherche dédiée aux organisations (nom ou sigle)
    if (!empty($org_search)) {
        $where_clauses[] = "(o.nom_org LIKE ? OR o.sigle_org LIKE ?)";
        $params[] = "%" . $org_search . "%";
        $params[] = "%" . $org_search . "%";
    }
}

// Recherche générale (nom, prénom, matricule, grade, poste, contact, ville)
if (!empty($search_query)) {
    $where_clauses[] = "(
        f.nom_fonc LIKE ? OR 
        f.prenom_fonc LIKE ? OR 
        f.matri LIKE ? OR 
        a.gra_post LIKE ? OR 
        a.tit_post LIKE ? OR 
        a.vil_affec LIKE ? OR 
        f.cont LIKE ? OR 
        f.email_perso LIKE ?
    )";
    $term = "%" . $search_query . "%";
    for ($i = 0; $i < 8; $i++) {
        $params[] = $term;
    }
}

$where_sql = implode(" AND ", $where_clauses);

// 5. CALCUL DU NOMBRE TOTAL DE FONCTIONNAIRES CORRESPONDANTS
$count_sql = "
    SELECT COUNT(DISTINCT f.id_fonc) 
    FROM FONCTIONNAIRE f
    LEFT JOIN RECEVOIR r ON f.id_fonc = r.id_fonc AND r.date_fin IS NULL
    LEFT JOIN AFFECTATION a ON r.id_affec = a.id_affec
    LEFT JOIN ORGANISATION o ON r.id_org = o.id_org
    WHERE $where_sql
";
$stmt_count = $db->prepare($count_sql);
$stmt_count->execute($params);
$total_items = (int)$stmt_count->fetchColumn();

// Calculs de pagination
$total_pages = max(1, (int)ceil($total_items / $per_page));
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $per_page;
$start_item = $total_items > 0 ? $offset + 1 : 0;
$end_item = min($offset + $per_page, $total_items);

// 6. RÉCUPÉRATION DES FONCTIONNAIRES POUR LA PAGE COURANTE
$data_sql = "
    SELECT f.*, a.tit_post, a.gra_post, a.vil_affec, o.id_org, o.nom_org, o.sigle_org, r.date_debut as date_affectation
    FROM FONCTIONNAIRE f
    LEFT JOIN RECEVOIR r ON f.id_fonc = r.id_fonc AND r.date_fin IS NULL
    LEFT JOIN AFFECTATION a ON r.id_affec = a.id_affec
    LEFT JOIN ORGANISATION o ON r.id_org = o.id_org
    WHERE $where_sql
    ORDER BY f.nom_fonc ASC, f.prenom_fonc ASC
    LIMIT $per_page OFFSET $offset
";
$stmt_data = $db->prepare($data_sql);
$stmt_data->execute($params);
$fonctionnaires = $stmt_data->fetchAll();

// Statistiques globales d'en-tête
$total_global_agents = $db->query("SELECT COUNT(*) FROM FONCTIONNAIRE")->fetchColumn();
$total_global_orgs   = $db->query("SELECT COUNT(*) FROM ORGANISATION")->fetchColumn();

if (!function_exists('getPaginationUrl')) {
    function getPaginationUrl($target_page) {
        $params = $_GET;
        $params['page'] = $target_page;
        return '?' . http_build_query($params);
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tous les Fonctionnaires | Ministère des Affaires Étrangères</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../public/css/style.css">
    <style>
        .dashboard-layout {
            display: flex;
            min-height: 100vh;
        }

        .sidebar-admin {
            width: 280px;
            background: linear-gradient(180deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            position: fixed;
            height: 100vh;
            display: flex;
            flex-direction: column;
            z-index: 100;
            overflow-y: auto;
        }

        .sidebar-admin-header {
            padding: 2rem 1.5rem;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-admin-menu {
            flex: 1;
            padding: 1.5rem 1rem;
        }

        .menu-cat-title {
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255, 255, 255, 0.45);
            margin: 1rem 0.8rem 0.4rem;
            font-weight: 700;
        }

        .menu-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0.75rem 1rem;
            color: rgba(255, 255, 255, 0.75);
            border-radius: var(--radius-md);
            font-size: 0.88rem;
            font-weight: 500;
            transition: var(--transition-fast);
        }

        .menu-link:hover, .menu-link.active {
            background: rgba(255, 255, 255, 0.12);
            color: white;
            transform: translateX(4px);
        }

        .menu-link.active {
            background: rgba(212, 175, 55, 0.2);
            border-left: 3px solid var(--secondary);
            color: white;
        }

        .main-admin-content {
            margin-left: 280px;
            width: calc(100% - 280px);
            min-height: 100vh;
            background: var(--bg-main);
        }

        /* Filter Box Styles */
        .filter-section {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
        }

        .filter-grid {
            display: grid;
            grid-template-columns: 2fr 2fr 1fr auto;
            gap: 1rem;
            align-items: flex-end;
        }

        .filter-label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 0.4rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input-icon-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon-wrapper i {
            position: absolute;
            left: 14px;
            color: var(--secondary);
            font-size: 0.95rem;
            pointer-events: none;
        }

        .input-icon-wrapper input,
        .input-icon-wrapper select {
            width: 100%;
            padding: 0.65rem 0.85rem 0.65rem 2.4rem;
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-md);
            font-family: inherit;
            font-size: 0.88rem;
            background: #ffffff;
            color: var(--text-main);
            transition: var(--transition-fast);
        }

        .input-icon-wrapper input:focus,
        .input-icon-wrapper select:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px var(--secondary-glow);
        }

        .filter-actions {
            display: flex;
            gap: 8px;
        }

        @media (max-width: 1100px) {
            .filter-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 768px) {
            .filter-grid {
                grid-template-columns: 1fr;
            }
        }
.agent-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    object-position: center;
    border: 2px solid var(--border-color);
    display: block;
    flex-shrink: 0;
}

.agent-avatar-placeholder {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.85rem;
    flex-shrink: 0;
    border: 2px solid var(--secondary);
}
        @media (max-width: 992px) {
            .sidebar-admin {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            .sidebar-admin.open {
                transform: translateX(0);
            }
            .main-admin-content {
                margin-left: 0;
                width: 100%;
            }
            #menuToggleBtn {
                display: block !important;
            }
        }
    </style>
</head>
<body>

<div class="dashboard-layout">
    
    <!-- Sidebar -->
    <aside class="sidebar-admin" id="sidebarAdmin">
        <div class="sidebar-admin-header">
            <div style="width: 58px; height: 58px; border-radius: 50%; background: rgba(255,255,255,0.1); border: 2px solid var(--secondary); display: flex; align-items: center; justify-content: center; margin: 0 auto 0.8rem; color: var(--secondary); font-size: 1.5rem;">
                <i class="fa-solid fa-building-columns"></i>
            </div>
            <h3 style="font-size: 0.9rem; font-weight: 700; color: white; margin: 0; letter-spacing: 0.5px;">
                MINISTÈRE DES AFFAIRES ÉTRANGÈRES
            </h3>
        </div>

        <nav class="sidebar-admin-menu">
            <div class="menu-cat-title">GÉNÉRAL</div>
            <a href="admin_dashboard.php" class="menu-link">
                <i class="fa-solid fa-chart-pie" style="color: var(--secondary);"></i> Tableau de bord
            </a>

            <?php if ($user_role === 'admin'): ?>
                <div class="menu-cat-title">ADMINISTRATION</div>
                <a href="../saisie/ajouter_gestionnaire.php" class="menu-link">
                    <i class="fa-solid fa-user-shield"></i> Créer Accès Local
                </a>
                <a href="../saisie/ajouter_direction.php" class="menu-link">
                    <i class="fa-solid fa-sitemap"></i> Gérer Directions
                </a>
                <a href="admin_historique.php" class="menu-link">
                    <i class="fa-solid fa-clock-rotate-left"></i> Historique Actions
                </a>
            <?php endif; ?>

            <div class="menu-cat-title">RESSOURCES HUMAINES</div>
            <a href="liste_fonctionnaires.php" class="menu-link active">
                <i class="fa-solid fa-users"></i> Tous les Fonctionnaires
            </a>
            <a href="../saisie/ajouter_agent.php" class="menu-link">
                <i class="fa-solid fa-user-plus"></i> Nouveau Fonctionnaire
            </a>
            <a href="gestion_documents.php" class="menu-link">
                <i class="fa-solid fa-folder-open"></i> Archive Documents
            </a>

            <div class="menu-cat-title">MON COMPTE</div>
            <a href="modifier_mdp.php" class="menu-link">
                <i class="fa-solid fa-lock"></i> Sécurité & MDP
            </a>
        </nav>

        <div style="padding: 1.2rem; background: rgba(0,0,0,0.2); border-top: 1px solid rgba(255,255,255,0.08); font-size: 0.8rem; display: flex; align-items: center; gap: 10px;">
            <i class="fa-solid fa-circle-user" style="font-size: 1.5rem; color: var(--secondary);"></i>
            <div>
                <strong style="display: block; color: white; line-height: 1.2;"><?php echo htmlspecialchars($_SESSION['nom'] ?? 'Admin'); ?></strong>
                <span style="font-size: 0.72rem; color: rgba(255,255,255,0.6);"><?php echo ucfirst($user_role); ?></span>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-admin-content">
        
        <!-- Top Bar -->
        <div style="background: white; border-bottom: 1px solid var(--border-color); padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 90;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <button id="menuToggleBtn" style="background: none; border: none; font-size: 1.2rem; cursor: pointer; color: var(--primary); display: none;">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="badge-status badge-gold" style="padding: 0.5rem 1rem; font-size: 0.8rem;">
                    <i class="fa-solid fa-building-shield"></i> <?php echo htmlspecialchars($nom_organisation_affichage); ?>
                </div>
            </div>

            <div style="display: flex; align-items: center; gap: 1rem;">
                <a href="admin_dashboard.php" class="btn-custom btn-outline" style="padding: 0.45rem 1rem; font-size: 0.8rem;" title="Retour au Dashboard">
                    <i class="fa-solid fa-chart-pie"></i> Dashboard
                </a>
                <a href="../logout.php" class="btn-custom btn-outline" style="padding: 0.45rem 1rem; font-size: 0.8rem; color: var(--danger); border-color: var(--danger-bg);" title="Déconnexion">
                    <i class="fa-solid fa-power-off"></i>
                </a>
            </div>
        </div>

        <div style="padding: 2rem;">
            
            <!-- Page Header -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.8rem; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h1 style="font-size: 1.6rem; font-weight: 800; color: var(--primary); margin: 0 0 0.3rem 0; letter-spacing: -0.5px;">
                        <i class="fa-solid fa-users" style="color: var(--secondary); margin-right: 10px;"></i>
                        Répertoire des Fonctionnaires
                    </h1>
                    <p style="color: var(--text-muted); font-size: 0.88rem; margin: 0;">
                        Consultation globale, filtrage par organisation et gestion des dossiers des agents publics
                    </p>
                </div>
                <div style="display: flex; gap: 10px;">
                    <a href="../saisie/ajouter_agent.php" class="btn-custom btn-primary" style="padding: 0.6rem 1.2rem; font-size: 0.85rem;">
                        <i class="fa-solid fa-user-plus"></i> Nouveau Fonctionnaire
                    </a>
                </div>
            </div>

            <!-- KPI Summary Cards -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.2rem; margin-bottom: 1.8rem;">
                <div class="glass-card" style="padding: 1.2rem; display: flex; align-items: center; gap: 1rem;">
                    <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(10,37,64,0.08); display: flex; align-items: center; justify-content: center; color: var(--primary); font-size: 1.3rem;">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.72rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted);">Total Agents</div>
                        <div style="font-size: 1.5rem; font-weight: 800; color: var(--primary);"><?php echo number_format($total_global_agents); ?></div>
                    </div>
                </div>

                <div class="glass-card" style="padding: 1.2rem; display: flex; align-items: center; gap: 1rem;">
                    <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(212,175,55,0.15); display: flex; align-items: center; justify-content: center; color: var(--secondary-dark); font-size: 1.3rem;">
                        <i class="fa-solid fa-sitemap"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.72rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted);">Organisations</div>
                        <div style="font-size: 1.5rem; font-weight: 800; color: var(--primary);"><?php echo number_format($total_global_orgs); ?></div>
                    </div>
                </div>

                <div class="glass-card" style="padding: 1.2rem; display: flex; align-items: center; gap: 1rem;">
                    <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(16,185,129,0.12); display: flex; align-items: center; justify-content: center; color: var(--success); font-size: 1.3rem;">
                        <i class="fa-solid fa-filter"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.72rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted);">Résultats Filtrés</div>
                        <div style="font-size: 1.5rem; font-weight: 800; color: var(--success);"><?php echo number_format($total_items); ?></div>
                    </div>
                </div>
            </div>

            <!-- Search & Filter Bar Specifically for Organisations & Agents -->
            <form method="GET" action="liste_fonctionnaires.php" class="filter-section">
                <div class="filter-grid">
                    
                    <!-- 1. Recherche par Organisation (Spécifique) -->
                    <div>
                        <label class="filter-label" for="org_search">
                            <i class="fa-solid fa-building" style="color: var(--secondary); margin-right: 4px;"></i>
                            Filtrer par Organisation
                        </label>
                        <div class="input-icon-wrapper">
                            <i class="fa-solid fa-building-circle-check"></i>
                            <select name="org" id="orgSelect" onchange="this.form.submit()">
                                <option value="">Toutes les organisations (<?php echo $total_global_agents; ?>)</option>
                                <?php foreach($liste_organisations as $org): ?>
                                    <option value="<?php echo $org['id_org']; ?>" <?php echo ($org_filter == $org['id_org']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($org['sigle_org'] ? "[{$org['sigle_org']}] " : ''); ?><?php echo htmlspecialchars($org['nom_org']); ?> (<?php echo $org['nb_agents']; ?>)
                                    </option>
                                <?php endforeach; ?>
                                <option value="none" <?php echo ($org_filter === 'none') ? 'selected' : ''; ?>>-- Sans affectation --</option>
                            </select>
                        </div>
                    </div>

                    <!-- 2. Recherche générale par mot-clé -->
                    <div>
                        <label class="filter-label" for="search_query">
                            <i class="fa-solid fa-magnifying-glass" style="color: var(--secondary); margin-right: 4px;"></i>
                            Recherche Fonctionnaire
                        </label>
                        <div class="input-icon-wrapper">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="text" name="search" id="search_query" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Nom, prénom, matricule, grade, ville...">
                        </div>
                    </div>

                    <!-- 3. Éléments par page -->
                    <div>
                        <label class="filter-label" for="per_page">
                            <i class="fa-solid fa-list-ol" style="color: var(--secondary); margin-right: 4px;"></i>
                            Lignes / Page
                        </label>
                        <div class="input-icon-wrapper">
                            <i class="fa-solid fa-table-cells"></i>
                            <select name="per_page" id="per_page" onchange="this.form.submit()">
                                <option value="10" <?php echo ($per_page == 10) ? 'selected' : ''; ?>>10 par page</option>
                                <option value="25" <?php echo ($per_page == 25) ? 'selected' : ''; ?>>25 par page</option>
                                <option value="50" <?php echo ($per_page == 50) ? 'selected' : ''; ?>>50 par page</option>
                                <option value="100" <?php echo ($per_page == 100) ? 'selected' : ''; ?>>100 par page</option>
                            </select>
                        </div>
                    </div>

                    <!-- 4. Boutons d'action -->
                    <div class="filter-actions">
                        <button type="submit" class="btn-custom btn-primary" style="padding: 0.65rem 1.1rem;" title="Appliquer les filtres">
                            <i class="fa-solid fa-filter"></i> Filtrer
                        </button>
                        <?php if(!empty($search_query) || !empty($org_filter) || !empty($org_search)): ?>
                            <a href="liste_fonctionnaires.php" class="btn-custom btn-outline" style="padding: 0.65rem 0.9rem;" title="Réinitialiser les filtres">
                                <i class="fa-solid fa-rotate-left"></i>
                            </a>
                        <?php endif; ?>
                    </div>

                </div>

                <!-- Barre secondaire de recherche textuelle rapide pour organisation si besoin -->
                <div style="margin-top: 1rem; display: flex; align-items: center; gap: 10px; font-size: 0.82rem; color: var(--text-muted); flex-wrap: wrap;">
                    <span><i class="fa-solid fa-tags" style="color: var(--secondary);"></i> Raccourcis organisations :</span>
                    <a href="liste_fonctionnaires.php" class="badge-status <?php echo empty($org_filter) ? 'badge-gold' : 'badge-info'; ?>" style="cursor: pointer; text-decoration: none;">
                        Toutes
                    </a>
                    <?php foreach(array_slice($liste_organisations, 0, 5) as $quick_org): ?>
                        <a href="liste_fonctionnaires.php?org=<?php echo $quick_org['id_org']; ?>" 
                           class="badge-status <?php echo ($org_filter == $quick_org['id_org']) ? 'badge-gold' : 'badge-info'; ?>" 
                           style="cursor: pointer; text-decoration: none;" 
                           title="<?php echo htmlspecialchars($quick_org['nom_org']); ?>">
                            <?php echo htmlspecialchars($quick_org['sigle_org'] ?: substr($quick_org['nom_org'], 0, 15)); ?> (<?php echo $quick_org['nb_agents']; ?>)
                        </a>
                    <?php endforeach; ?>
                </div>
            </form>

            <!-- Table of Civil Servants -->
            <div class="glass-card" style="padding: 1.8rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.2rem; flex-wrap: wrap; gap: 0.8rem;">
                    <div>
                        <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--primary); margin: 0;">
                            <i class="fa-solid fa-address-book" style="color: var(--secondary); margin-right: 8px;"></i>
                            Liste Officielle des Agents de l'État
                        </h3>
                        <span style="font-size: 0.82rem; color: var(--text-muted);">
                            Affichage de <strong><?php echo $start_item; ?></strong> à <strong><?php echo $end_item; ?></strong> sur <strong><?php echo number_format($total_items); ?></strong> agent(s)
                        </span>
                    </div>

                    <?php if(!empty($org_filter) || !empty($search_query)): ?>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="font-size: 0.78rem; color: var(--text-muted);">Filtre actif :</span>
                            <span class="badge-status badge-gold">
                                <?php 
                                    if ($org_filter === 'none') {
                                        echo 'Sans affectation';
                                    } elseif (!empty($org_filter)) {
                                        foreach($liste_organisations as $lo) {
                                            if ($lo['id_org'] == $org_filter) {
                                                echo htmlspecialchars($lo['sigle_org'] ?: $lo['nom_org']);
                                                break;
                                            }
                                        }
                                    }
                                    if (!empty($search_query)) {
                                        echo ' | "' . htmlspecialchars($search_query) . '"';
                                    }
                                ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="table-container">
                    <table class="table-modern">
                        <thead>
                            <tr>
                                <th style="width: 50px;">Agent</th>
                                <th>Matricule</th>
                                <th>Nom & Prénoms</th>
                                <th>Organisation / Direction</th>
                                <th>Poste & Grade</th>
                                <th>Affectation</th>
                                <th style="text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($fonctionnaires)): ?>
                                <?php foreach ($fonctionnaires as $f): 
                                    $photo_path = !empty($f['photo_fonc']) ? '../uploads/photos/' . $f['photo_fonc'] : '';
                                    $has_photo = !empty($f['photo_fonc']) && file_exists(__DIR__ . '/../uploads/photos/' . $f['photo_fonc']);
                                    $initials = mb_substr($f['nom_fonc'] ?? '', 0, 1) . mb_substr($f['prenom_fonc'] ?? '', 0, 1);
                                ?>
                                <tr>
                                    <!-- Photo / Avatar -->
                                    <td>
                                        <?php if ($has_photo): ?>
                                            <img src="<?php echo htmlspecialchars($photo_path); ?>" alt="<?php echo htmlspecialchars($f['nom_fonc']); ?>" class="agent-avatar">
                                        <?php else: ?>
                                            <div class="agent-avatar-placeholder">
                                                <?php echo strtoupper($initials ?: 'FN'); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Matricule -->
                                    <td>
                                        <span style="font-family: monospace; font-weight: 700; color: var(--secondary-dark); background: var(--secondary-glow); padding: 4px 8px; border-radius: 6px; font-size: 0.88rem;">
                                            <?php echo htmlspecialchars($f['matri']); ?>
                                        </span>
                                    </td>

                                    <!-- Nom & Prénoms -->
                                    <td>
                                        <div style="font-weight: 700; color: var(--primary);">
                                            <?php echo strtoupper(htmlspecialchars($f['nom_fonc'])); ?>
                                            <span style="font-weight: 500; color: var(--text-main);"><?php echo htmlspecialchars($f['prenom_fonc']); ?></span>
                                        </div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted); display: flex; align-items: center; gap: 8px; margin-top: 2px;">
                                            <?php if (!empty($f['gen'])): ?>
                                                <span><i class="fa-solid <?php echo ($f['gen'] === 'F') ? 'fa-venus' : 'fa-mars'; ?>" style="color: <?php echo ($f['gen'] === 'F') ? '#ec4899' : '#3b82f6'; ?>;"></i> <?php echo htmlspecialchars($f['gen']); ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($f['cont'])): ?>
                                                <span><i class="fa-solid fa-phone" style="font-size: 0.7rem;"></i> <?php echo htmlspecialchars($f['cont']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <!-- Organisation -->
                                    <td>
                                        <?php if (!empty($f['nom_org'])): ?>
                                            <div class="org-tag" title="<?php echo htmlspecialchars($f['nom_org']); ?>">
                                                <?php if (!empty($f['sigle_org'])): ?>
                                                    <span class="sigle"><?php echo htmlspecialchars($f['sigle_org']); ?></span>
                                                <?php endif; ?>
                                                <span><?php echo htmlspecialchars(mb_strimwidth($f['nom_org'], 0, 32, '...')); ?></span>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge-status badge-warning" style="font-size: 0.75rem;">
                                                <i class="fa-solid fa-triangle-exclamation"></i> Non affecté
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Grade & Poste -->
                                    <td>
                                        <div style="font-weight: 600; color: var(--primary); font-size: 0.85rem;">
                                            <?php echo htmlspecialchars($f['tit_post'] ?? 'Fonctionnaire'); ?>
                                        </div>
                                        <div>
                                            <span class="badge-status badge-gold" style="font-size: 0.7rem; padding: 2px 6px;">
                                                Grade: <?php echo htmlspecialchars($f['gra_post'] ?? 'N/A'); ?>
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Affectation Ville -->
                                    <td>
                                        <div style="font-size: 0.85rem; color: var(--text-main);">
                                            <i class="fa-solid fa-location-dot" style="color: var(--secondary); margin-right: 4px;"></i>
                                            <?php echo htmlspecialchars($f['vil_affec'] ?? 'N/A'); ?>
                                        </div>
                                        <?php if (!empty($f['date_affectation'])): ?>
                                            <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 2px;">
                                                Depuis le <?php echo date('d/m/Y', strtotime($f['date_affectation'])); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Actions -->
                                    <td style="text-align: right;">
                                        <div style="display: flex; gap: 6px; justify-content: flex-end;">
                                            <a href="profil_agent.php?id=<?php echo $f['id_fonc']; ?>" class="btn-custom btn-outline" style="padding: 0.35rem 0.7rem; font-size: 0.75rem;" title="Voir la fiche complète">
                                                <i class="fa-solid fa-eye"></i>
                                            </a>
                                            <?php if ($user_role !== 'consultant'): ?>
                                                <a href="modifier_agent.php?id=<?php echo $f['id_fonc']; ?>" class="btn-custom btn-outline" style="padding: 0.35rem 0.7rem; font-size: 0.75rem; color: var(--info);" title="Modifier">
                                                    <i class="fa-solid fa-pen"></i>
                                                </a>
                                                <a href="../saisie/muter_agent.php?id=<?php echo $f['id_fonc']; ?>" class="btn-custom btn-outline" style="padding: 0.35rem 0.7rem; font-size: 0.75rem; color: var(--warning);" title="Muter dans une autre structure">
                                                    <i class="fa-solid fa-arrows-rotate"></i>
                                                </a>
                                                <a href="../supprimer_agent.php?id=<?php echo $f['id_fonc']; ?>" class="btn-custom btn-outline" style="padding: 0.35rem 0.7rem; font-size: 0.75rem; color: var(--danger);" title="Supprimer" onclick="return confirm('Êtes-vous sûr de vouloir supprimer définitivement cet agent (<?php echo htmlspecialchars($f['nom_fonc'] . ' ' . $f['prenom_fonc']); ?>) ?');">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 3.5rem 1.5rem; color: var(--text-muted);">
                                        <div style="width: 70px; height: 70px; border-radius: 50%; background: var(--bg-card-alt); display: flex; align-items: center; justify-content: center; margin: 0 auto 1.2rem; color: var(--text-light); font-size: 2rem;">
                                            <i class="fa-solid fa-users-slash"></i>
                                        </div>
                                        <h4 style="font-size: 1.1rem; font-weight: 700; color: var(--primary); margin-bottom: 0.5rem;">Aucun fonctionnaire trouvé</h4>
                                        <p style="font-size: 0.88rem; max-width: 500px; margin: 0 auto 1.5rem;">
                                            Aucun enregistrement ne correspond aux critères de recherche actuels. Essayez de modifier ou de réinitialiser vos filtres.
                                        </p>
                                        <a href="liste_fonctionnaires.php" class="btn-custom btn-secondary" style="padding: 0.55rem 1.2rem; font-size: 0.85rem;">
                                            <i class="fa-solid fa-rotate-left"></i> Réinitialiser les filtres
                                        </a>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Component -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination-container">
                        <div class="pagination-info">
                            Page <strong><?php echo $page; ?></strong> sur <strong><?php echo $total_pages; ?></strong> 
                            (Total: <strong><?php echo number_format($total_items); ?></strong> agents)
                        </div>

                        <ul class="pagination">
                            <!-- Bouton Précédent -->
                            <li>
                                <a href="<?php echo ($page > 1) ? getPaginationUrl($page - 1) : '#'; ?>" 
                                   class="page-btn <?php echo ($page <= 1) ? 'disabled' : ''; ?>" 
                                   title="Page précédente">
                                    <i class="fa-solid fa-chevron-left"></i>
                                </a>
                            </li>

                            <?php
                            // Calcul des pages visibles (fenêtre glissante)
                            $start_page = max(1, $page - 2);
                            $end_page   = min($total_pages, $page + 2);

                            // Premier bouton si éloigné
                            if ($start_page > 1) {
                                echo '<li><a href="' . getPaginationUrl(1) . '" class="page-btn">1</a></li>';
                                if ($start_page > 2) {
                                    echo '<li><span class="page-dots">...</span></li>';
                                }
                            }

                            // Pages numériques
                            for ($i = $start_page; $i <= $end_page; $i++) {
                                $active = ($i == $page) ? 'active' : '';
                                echo '<li><a href="' . getPaginationUrl($i) . '" class="page-btn ' . $active . '">' . $i . '</a></li>';
                            }

                            // Dernier bouton si éloigné
                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<li><span class="page-dots">...</span></li>';
                                }
                                echo '<li><a href="' . getPaginationUrl($total_pages) . '" class="page-btn">' . $total_pages . '</a></li>';
                            }
                            ?>

                            <!-- Bouton Suivant -->
                            <li>
                                <a href="<?php echo ($page < $total_pages) ? getPaginationUrl($page + 1) : '#'; ?>" 
                                   class="page-btn <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>" 
                                   title="Page suivante">
                                    <i class="fa-solid fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </div>
                <?php endif; ?>

            </div>

        </div>
    </main>
</div>

<script src="../public/js/app.js"></script>
<script>
    const toggleBtn = document.getElementById('menuToggleBtn');
    const sidebar = document.getElementById('sidebarAdmin');
    if(toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('open');
        });
    }
</script>
</body>
</html>

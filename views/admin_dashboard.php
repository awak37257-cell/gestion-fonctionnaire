<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config.php';

// 1. PROTECTION ET SESSION
if (!isset($_SESSION['rol'])) {
    header("Location:../saisie/login.php");
    exit();
}

$user_role = $_SESSION['rol'];
$user_org_id = $_SESSION['id_org'] ?? null;

// Initialisations des compteurs
$total_agents = 0; 
$total_directions = 0; 
$total_diplomes = 0;
$total_admins_locaux = 0;
$nom_organisation_affichage = "Administration Centrale";

// 2. RÉCUPÉRATION DU NOM DE L'ORGANISATION
if ($user_role === 'admin_local' && $user_org_id) {
    $stmtOrg = $db->prepare("SELECT nom_org FROM ORGANISATION WHERE id_org = ?");
    $stmtOrg->execute([$user_org_id]);
    $resOrg = $stmtOrg->fetch();
    if ($resOrg) $nom_organisation_affichage = $resOrg['nom_org'];
}

// 3. LOGIQUE DES STATISTIQUES (Via la table RECEVOIR)
if ($user_role === 'admin_local' && $user_org_id) {
    $stmtA = $db->prepare("SELECT COUNT(f.id_fonc) FROM FONCTIONNAIRE f 
                           JOIN RECEVOIR r ON f.id_fonc = r.id_fonc 
                           WHERE r.id_org = ? AND r.date_fin IS NULL");
    $stmtA->execute([$user_org_id]);
    $total_agents = $stmtA->fetchColumn();
    
    $total_directions = 1; 
    
    $stmtD = $db->prepare("SELECT COUNT(DISTINCT d.id_dipl) FROM OBTENIR d 
                           JOIN RECEVOIR r ON d.id_fonc = r.id_fonc 
                           WHERE r.id_org = ? AND r.date_fin IS NULL");
    $stmtD->execute([$user_org_id]);
    $total_diplomes = $stmtD->fetchColumn();
} else {
    // Statistiques globales
    $total_agents = $db->query("SELECT COUNT(*) FROM FONCTIONNAIRE")->fetchColumn();
    $total_directions = $db->query("SELECT COUNT(*) FROM ORGANISATION")->fetchColumn();
    $total_diplomes = $db->query("SELECT COUNT(*) FROM DIPLOME")->fetchColumn();
    $total_admins_locaux = $db->query("SELECT COUNT(*) FROM UTILISATEURS WHERE rol = 'admin_local'")->fetchColumn();
    
    // Liste des administrateurs
    $liste_admins_locaux = $db->query("
        SELECT u.iden, o.nom_org, g.nom_gest, g.prenom_gest, g.email_gest
        FROM UTILISATEURS u 
        LEFT JOIN ORGANISATION o ON u.id_org = o.id_org 
        LEFT JOIN GESTIONNAIRE g ON u.id_user = g.id_user
        WHERE u.rol = 'admin_local'
        ORDER BY o.nom_org ASC
    ")->fetchAll();

    // Liste des organisations pour les filtres
    $liste_organisations = $db->query("
        SELECT id_org, nom_org, sigle_org 
        FROM ORGANISATION 
        ORDER BY nom_org ASC
    ")->fetchAll();
}

// 3b. FLUX D'ACTIVITÉS POUR LA SIDEBAR
if ($user_role === 'admin_local' && $user_org_id) {
    $stmt_sidebar = $db->prepare("
        SELECT r.id_rec, f.id_fonc, f.nom_fonc, f.prenom_fonc, o.sigle_org, a.gra_post
        FROM RECEVOIR r
        JOIN FONCTIONNAIRE f ON r.id_fonc = f.id_fonc
        JOIN ORGANISATION o ON r.id_org = o.id_org
        JOIN AFFECTATION a ON r.id_affec = a.id_affec
        WHERE r.id_org = ?
        ORDER BY r.id_rec DESC 
        LIMIT 3
    ");
    $stmt_sidebar->execute([$user_org_id]);
    $flux_recents = $stmt_sidebar->fetchAll();
} else {
    $flux_recents = $db->query("
        SELECT r.id_rec, f.id_fonc, f.nom_fonc, f.prenom_fonc, o.sigle_org, a.gra_post
        FROM RECEVOIR r
        JOIN FONCTIONNAIRE f ON r.id_fonc = f.id_fonc
        JOIN ORGANISATION o ON r.id_org = o.id_org
        JOIN AFFECTATION a ON r.id_affec = a.id_affec
        ORDER BY r.id_rec DESC 
        LIMIT 3
    ")->fetchAll();
}

// 4. REQUÊTE TABLEAU DES EFFECTIFS
$sql_base = "SELECT f.*, a.gra_post, a.vil_affec, o.nom_org, o.sigle_org 
             FROM FONCTIONNAIRE f 
             LEFT JOIN RECEVOIR r ON f.id_fonc = r.id_fonc AND r.date_fin IS NULL 
             LEFT JOIN AFFECTATION a ON r.id_affec = a.id_affec 
             LEFT JOIN ORGANISATION o ON r.id_org = o.id_org";

if ($user_role === 'admin_local' && $user_org_id) {
    $stmtTable = $db->prepare($sql_base . " WHERE r.id_org = ? ORDER BY f.nom_fonc ASC");
    $stmtTable->execute([$user_org_id]);

    $stmtLast = $db->prepare($sql_base . " WHERE r.id_org = ? ORDER BY f.id_fonc DESC LIMIT 5");
    $stmtLast->execute([$user_org_id]);
    $lastAgents = $stmtLast->fetchAll();
} else {
    $stmtTable = $db->query($sql_base . " ORDER BY f.nom_fonc ASC");
    $lastAgents = $db->query($sql_base . " ORDER BY f.id_fonc DESC LIMIT 5")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Administrateur | Ministère 2026</title>
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
            <a href="admin_dashboard.php" class="menu-link active">
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
            <a href="liste_fonctionnaires.php" class="menu-link">
                <i class="fa-solid fa-table-list"></i> Liste Complète
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

    <!-- Main Content Area -->
    <main class="main-admin-content">
        
        <!-- Top Bar -->
        <div style="background: white; border-bottom: 1px solid var(--border-color); padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; sticky: top;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <button id="menuToggleBtn" style="background: none; border: none; font-size: 1.2rem; cursor: pointer; color: var(--primary); display: none;">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="badge-status badge-gold" style="padding: 0.5rem 1rem; font-size: 0.8rem;">
                    <i class="fa-solid fa-building-shield"></i> <?php echo htmlspecialchars($nom_organisation_affichage); ?>
                </div>
            </div>

            <div style="display: flex; align-items: center; gap: 1rem;">
               
                <a href="../logout.php" class="btn-custom btn-outline" style="padding: 0.45rem 1rem; font-size: 0.8rem; color: var(--danger); border-color: var(--danger-bg);" title="Déconnexion">
                    <i class="fa-solid fa-power-off"></i>
                </a>
            </div>
        </div>

        <!-- Dashboard Body Container -->
        <div style="padding: 2rem;">
            
            <?php if(isset($_GET['msg']) && $_GET['msg'] === 'success_mdp'): ?>
                <div class="toast toast-success" style="position: static; margin-bottom: 1.5rem; animation: none;">
                    <i class="fa-solid fa-circle-check"></i>
                    <span>Votre mot de passe a été mis à jour avec succès.</span>
                </div>
            <?php endif; ?>
            
            <?php if(isset($_GET['success']) && $_GET['success'] == 1): ?>
                <div class="toast toast-success" style="position: static; margin-bottom: 1.5rem; animation: none;">
                    <i class="fa-solid fa-circle-check"></i>
                    <span>Opération effectuée avec succès !</span>
                </div>
            <?php endif; ?>

            <!-- KPI Cards Grid -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                <div class="glass-card" style="padding: 1.5rem; display: flex; align-items: center; gap: 1.2rem;">
                    <div style="width: 54px; height: 54px; border-radius: var(--radius-md); background: rgba(10,37,64,0.08); display: flex; align-items: center; justify-content: center; color: var(--primary); font-size: 1.5rem;">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div>
                        <span style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); letter-spacing: 0.5px;">Fonctionnaires</span>
                        <h3 style="font-size: 1.6rem; font-weight: 800; color: var(--primary); margin: 0;"><?php echo number_format($total_agents); ?></h3>
                    </div>
                </div>

                <div class="glass-card" style="padding: 1.5rem; display: flex; align-items: center; gap: 1.2rem;">
                    <div style="width: 54px; height: 54px; border-radius: var(--radius-md); background: rgba(212,175,55,0.15); display: flex; align-items: center; justify-content: center; color: var(--secondary-dark); font-size: 1.5rem;">
                        <i class="fa-solid fa-sitemap"></i>
                    </div>
                    <div>
                        <span style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); letter-spacing: 0.5px;">Directions / Org.</span>
                        <h3 style="font-size: 1.6rem; font-weight: 800; color: var(--primary); margin: 0;"><?php echo number_format($total_directions); ?></h3>
                    </div>
                </div>

                <div class="glass-card" style="padding: 1.5rem; display: flex; align-items: center; gap: 1.2rem;">
                    <div style="width: 54px; height: 54px; border-radius: var(--radius-md); background: rgba(16,185,129,0.15); display: flex; align-items: center; justify-content: center; color: var(--success); font-size: 1.5rem;">
                        <i class="fa-solid fa-graduation-cap"></i>
                    </div>
                    <div>
                        <span style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); letter-spacing: 0.5px;">Diplômes</span>
                        <h3 style="font-size: 1.6rem; font-weight: 800; color: var(--primary); margin: 0;"><?php echo number_format($total_diplomes); ?></h3>
                    </div>
                </div>

                <?php if ($user_role === 'admin'): ?>
                <div class="glass-card" style="padding: 1.5rem; display: flex; align-items: center; gap: 1.2rem;">
                    <div style="width: 54px; height: 54px; border-radius: var(--radius-md); background: rgba(59,130,246,0.15); display: flex; align-items: center; justify-content: center; color: var(--info); font-size: 1.5rem;">
                        <i class="fa-solid fa-user-shield"></i>
                    </div>
                    <div>
                        <span style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); letter-spacing: 0.5px;">Admins Locaux</span>
                        <h3 style="font-size: 1.6rem; font-weight: 800; color: var(--primary); margin: 0;"><?php echo number_format($total_admins_locaux); ?></h3>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Search & Filter Bar -->
            <div class="glass-card" style="padding: 1.2rem; margin-bottom: 2rem; display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                <div class="search-input-group" style="flex: 2; margin: 0; min-width: 280px;">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="dashboardSearch" placeholder="Rechercher par nom, prénom, matricule ou grade...">
                </div>
                <?php if ($user_role === 'admin' && !empty($liste_organisations)): ?>
                    <div style="flex: 1; min-width: 220px;">
                        <select id="dashOrgFilter" class="form-control-custom" style="width: 100%; padding: 0.65rem 1rem; font-size: 0.88rem; cursor: pointer;">
                            <option value="">Toutes les organisations</option>
                            <?php foreach($liste_organisations as $org): ?>
                                <option value="<?php echo htmlspecialchars($org['nom_org']); ?>">
                                    <?php echo htmlspecialchars($org['sigle_org'] ? "[{$org['sigle_org']}] " : ''); ?><?php echo htmlspecialchars($org['nom_org']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Main Agents Table -->
            <div class="glass-card" style="padding: 1.8rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.2rem;">
                    <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--primary); margin: 0;">
                        <i class="fa-solid fa-users-gear" style="color: var(--secondary); margin-right: 8px;"></i>
                        Registre des Fonctionnaires
                    </h3>
                    <a href="../saisie/ajouter_agent.php" class="btn-custom btn-primary" style="padding: 0.5rem 1rem; font-size: 0.8rem;">
                        <i class="fa-solid fa-user-plus"></i> Ajouter un Agent
                    </a>
                </div>

                <div class="table-container">
                    <table class="table-modern">
                        <thead>
                            <tr>
                                <th>Matricule</th>
                                <th>Nom & Prénoms</th>
                                <th>Organisation</th>
                                <th>Grade</th>
                                <th>Ville</th>
                                <th style="text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="mainAgentTable">
                            <?php 
                            $agents = [];
                            while($f = $stmtTable->fetch()): 
                                $agents[] = $f;
                            ?>
                            <tr data-searchable-item data-org="<?php echo htmlspecialchars($f['nom_org'] ?? ''); ?>">
                                <td>
                                    <span style="font-family: monospace; font-weight: 700; color: var(--secondary-dark); background: var(--secondary-glow); padding: 4px 8px; border-radius: 6px;">
                                        <?php echo htmlspecialchars($f['matri']); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong style="color: var(--primary);"><?php echo strtoupper(htmlspecialchars($f['nom_fonc'])); ?></strong> 
                                    <span style="color: var(--text-muted);"><?php echo htmlspecialchars($f['prenom_fonc']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($f['nom_org'] ?? 'Non affecté'); ?></td>
                                <td>
                                    <span class="badge-status badge-gold">
                                        <?php echo htmlspecialchars($f['gra_post'] ?? 'N/A'); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($f['vil_affec'] ?? 'N/A'); ?></td>
                                <td style="text-align: right;">
                                    <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                        <a href="profil_agent.php?id=<?php echo $f['id_fonc']; ?>" class="btn-custom btn-outline" style="padding: 0.35rem 0.7rem; font-size: 0.75rem;" title="Voir le profil">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                        <?php if ($user_role !== 'consultant'): ?>
                                            <a href="modifier_agent.php?id=<?php echo $f['id_fonc']; ?>" class="btn-custom btn-outline" style="padding: 0.35rem 0.7rem; font-size: 0.75rem; color: var(--info);" title="Modifier">
                                                <i class="fa-solid fa-pen"></i>
                                            </a>
                                            <a href="../saisie/muter_agent.php?id=<?php echo $f['id_fonc']; ?>" class="btn-custom btn-outline" style="padding: 0.35rem 0.7rem; font-size: 0.75rem; color: var(--warning);" title="Muter">
                                                <i class="fa-solid fa-arrows-rotate"></i>
                                            </a>
                                            <a href="../supprimer_agent.php?id=<?php echo $f['id_fonc']; ?>" class="btn-custom btn-outline" style="padding: 0.35rem 0.7rem; font-size: 0.75rem; color: var(--danger);" title="Supprimer" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet agent ?');">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>

                            <?php if(empty($agents)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                    <i class="fa-solid fa-users-slash" style="font-size: 2rem; margin-bottom: 0.5rem; display: block;"></i>
                                    Aucun fonctionnaire enregistré pour le moment.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination for Dashboard Table -->
                <div class="pagination-container" id="dashPaginationContainer" style="display: none;">
                    <div class="pagination-info" id="dashPaginationInfo"></div>
                    <ul class="pagination" id="dashPagination"></ul>
                </div>
            </div>

        </div>
    </main>
</div>

<script src="../public/js/app.js"></script>
<script>
    // Toggle sidebar mobile
    const toggleBtn = document.getElementById('menuToggleBtn');
    const sidebar = document.getElementById('sidebarAdmin');
    if(toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('open');
        });
    }

    // Filtrage et Pagination du tableau de bord
    (function() {
        const searchInput = document.getElementById('dashboardSearch');
        const orgFilter = document.getElementById('dashOrgFilter');
        const tableBody = document.getElementById('mainAgentTable');
        const pagContainer = document.getElementById('dashPaginationContainer');
        const pagUl = document.getElementById('dashPagination');
        const pagInfo = document.getElementById('dashPaginationInfo');

        if (!tableBody) return;

        const allRows = Array.from(tableBody.querySelectorAll('tr[data-searchable-item]'));
        let currentPage = 1;
        const rowsPerPage = 10;
        let filteredRows = allRows;

        function applyFilters() {
            const query = (searchInput ? searchInput.value : '').toLowerCase().trim();
            const selectedOrg = (orgFilter ? orgFilter.value : '').toLowerCase().trim();

            filteredRows = allRows.filter(row => {
                const text = row.textContent.toLowerCase();
                const rowOrg = (row.getAttribute('data-org') || '').toLowerCase();

                const matchesText = !query || text.includes(query);
                const matchesOrg = !selectedOrg || rowOrg.includes(selectedOrg);

                return matchesText && matchesOrg;
            });

            currentPage = 1;
            renderTablePage();
        }

        function renderTablePage() {
            const total = filteredRows.length;
            const totalPages = Math.max(1, Math.ceil(total / rowsPerPage));
            if (currentPage > totalPages) currentPage = totalPages;

            const start = (currentPage - 1) * rowsPerPage;
            const end = start + rowsPerPage;

            allRows.forEach(row => row.style.display = 'none');
            filteredRows.slice(start, end).forEach(row => row.style.display = '');

            // Rendu pagination
            if (total > rowsPerPage) {
                pagContainer.style.display = 'flex';
                pagInfo.textContent = `Affichage de ${total > 0 ? start + 1 : 0} à ${Math.min(end, total)} sur ${total} fonctionnaires`;

                let html = '';
                html += `<li><button type="button" class="page-btn ${currentPage <= 1 ? 'disabled' : ''}" data-goto="${currentPage - 1}"><i class="fa-solid fa-chevron-left"></i></button></li>`;
                for (let p = 1; p <= totalPages; p++) {
                    html += `<li><button type="button" class="page-btn ${p === currentPage ? 'active' : ''}" data-goto="${p}">${p}</button></li>`;
                }
                html += `<li><button type="button" class="page-btn ${currentPage >= totalPages ? 'disabled' : ''}" data-goto="${currentPage + 1}"><i class="fa-solid fa-chevron-right"></i></button></li>`;
                pagUl.innerHTML = html;

                pagUl.querySelectorAll('button[data-goto]').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        e.preventDefault();
                        const target = parseInt(btn.getAttribute('data-goto'));
                        if (target >= 1 && target <= totalPages) {
                            currentPage = target;
                            renderTablePage();
                        }
                    });
                });
            } else {
                pagContainer.style.display = 'none';
            }
        }

        if (searchInput) searchInput.addEventListener('input', applyFilters);
        if (orgFilter) orgFilter.addEventListener('change', applyFilters);

        // Initialisation au chargement
        applyFilters();
    })();
</script>
</body>
</html>
<?php
require_once '../config.php';

// Vérification de la session et du rôle
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Récupération des 100 derniers logs avec jointure (Nom, Prénom, Iden et Rôle)
$stmt = $db->query("SELECT l.*, u.iden, u.rol 
                    FROM HISTORIQUE l 
                    LEFT JOIN UTILISATEURS u ON l.id_user = u.id_user 
                    ORDER BY l.dat_hist DESC LIMIT 100");

// Statistiques supplémentaires
$total_logs = $db->query("SELECT COUNT(*) FROM HISTORIQUE")->fetchColumn();
$total_actions_today = $db->query("SELECT COUNT(*) FROM HISTORIQUE WHERE DATE(dat_hist) = CURDATE()")->fetchColumn();

// Top 5 utilisateurs les plus actifs (Affichage Nom + Prénom ou Iden)
$top_users = $db->query("
    SELECT u.iden, COUNT(*) as action_count 
    FROM HISTORIQUE l 
    LEFT JOIN UTILISATEURS u ON l.id_user = u.id_user 
    GROUP BY l.id_user 
    ORDER BY action_count DESC 
    LIMIT 5
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Journal Sécurisé | Système 2026</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #0A2540;
            --primary-dark: #051a2c;
            --primary-light: #1a3d5c;
            --accent: #D4AF37;
            --accent-dark: #b8960c;
            --success: #10B981;
            --danger: #EF4444;
            --warning: #F59E0B;
            --info: #3B82F6;
            --purple: #8B5CF6;
            --bg: #F8FAFC;
            --white: #FFFFFF;
            --text: #1E293B;
            --text-light: #64748B;
            --border: #E2E8F0;
            --shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 20px 35px -10px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 30px 45px -15px rgba(0, 0, 0, 0.15);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 40px;
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }

        /* Animation de fond */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="rgba(255,255,255,0.05)" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,154.7C960,171,1056,181,1152,165.3C1248,149,1344,107,1392,85.3L1440,64L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>') repeat-x bottom;
            background-size: cover;
            opacity: 0.1;
            pointer-events: none;
        }

        /* Particules animées */
        .particle {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            pointer-events: none;
            animation: float linear infinite;
        }

        @keyframes float {
            0% {
                transform: translateY(100vh) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(-100vh) rotate(360deg);
                opacity: 0;
            }
        }

        .container { 
            max-width: 1400px; 
            margin: 0 auto; 
            position: relative;
            z-index: 1;
        }

        /* ========== HEADER ========== */
        .log-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 35px;
            flex-wrap: wrap;
            gap: 20px;
            animation: slideDown 0.6s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .header-title h1 { 
            margin: 0; 
            color: white;
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header-title h1 i {
            color: var(--accent);
            font-size: 2rem;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .header-title p { 
            color: rgba(255,255,255,0.9); 
            margin-top: 10px;
            font-size: 0.9rem;
        }

        .btn-back {
            text-decoration: none;
            background: rgba(255,255,255,0.95);
            color: var(--primary);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 24px;
            border-radius: 16px;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: var(--shadow);
            backdrop-filter: blur(10px);
        }

        .btn-back:hover { 
            transform: translateX(-5px);
            background: white;
            box-shadow: var(--shadow-lg);
            color: var(--accent);
        }

        /* ========== STATS MINI ========== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            padding: 20px;
            border-radius: 24px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: var(--shadow);
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            animation: fadeInUp 0.5s ease-out forwards;
            opacity: 0;
        }

        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
        }

        .stat-info {
            flex: 1;
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--primary);
            line-height: 1;
        }

        .stat-label {
            font-size: 0.7rem;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 5px;
        }

        /* ========== TOP USERS ========== */
        .top-users {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
        }

        .top-users h3 {
            font-size: 0.9rem;
            color: var(--primary);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-rank {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid var(--border);
        }

        .user-rank:last-child {
            border-bottom: none;
        }

        .rank-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .rank-number {
            width: 28px;
            height: 28px;
            background: var(--bg);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.8rem;
            color: var(--primary);
        }

        .rank-name {
            font-weight: 600;
            color: var(--text);
        }

        .rank-count {
            background: var(--accent);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        /* ========== BARRE DE RECHERCHE ========== */
        .search-box {
            background: white;
            padding: 14px 22px;
            border-radius: 20px;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            margin-bottom: 30px;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }

        .search-box:focus-within {
            border-color: var(--accent);
            box-shadow: 0 0 0 4px rgba(212, 175, 55, 0.1);
        }

        .search-box i {
            color: var(--accent);
            font-size: 1.1rem;
        }

        .search-box input {
            border: none;
            outline: none;
            width: 100%;
            margin-left: 15px;
            font-family: inherit;
            font-size: 0.9rem;
            background: transparent;
        }

        .search-box input::placeholder {
            color: var(--text-light);
        }

        /* ========== FILTRES ========== */
        .filters {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 8px 18px;
            border: 2px solid var(--border);
            background: white;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            color: var(--text-light);
        }

        .filter-btn:hover, .filter-btn.active {
            background: var(--accent);
            border-color: var(--accent);
            color: white;
            transform: translateY(-2px);
        }

        /* ========== TABLE ========== */
        .table-wrapper {
            background: white;
            border-radius: 24px;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            border: 1px solid var(--border);
        }

        table { 
            width: 100%; 
            border-collapse: collapse; 
        }

        th { 
            background: linear-gradient(135deg, #F1F5F9, #E2E8F0);
            padding: 18px 20px; 
            text-align: left; 
            font-size: 0.7rem; 
            text-transform: uppercase; 
            letter-spacing: 1px;
            color: var(--text-light);
            font-weight: 700;
        }

        td { 
            padding: 16px 20px; 
            border-bottom: 1px solid var(--border); 
            vertical-align: middle;
            font-size: 0.85rem;
            transition: all 0.2s ease;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover { 
            background: linear-gradient(90deg, #F8FAFC, white);
            transform: scale(1.01);
        }

        /* Badges de rôle */
        .badge-role {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .role-admin { background: #FEE2E2; color: #991B1B; }
        .role-local { background: #DCFCE7; color: #166534; }
        .role-consultant { background: #E0E7FF; color: #3730A3; }
        .role-auto { background: #F1F5F9; color: #475569; }

        /* Style des dates */
        .date-text { 
            color: var(--text-light); 
            font-size: 0.75rem; 
            font-family: 'Monaco', monospace;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Style de l'action */
        .action-text { 
            font-weight: 500; 
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .action-highlight {
            background: var(--bg);
            padding: 2px 8px;
            border-radius: 12px;
            font-weight: 600;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
        }

        .page-btn {
            padding: 10px 16px;
            border: 2px solid var(--border);
            background: white;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
        }

        .page-btn:hover, .page-btn.active {
            background: var(--accent);
            border-color: var(--accent);
            color: white;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px;
            color: var(--text-light);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        /* Responsive */
        @media (max-width: 768px) {
            body {
                padding: 20px;
            }
            .log-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
            th, td {
                padding: 12px 15px;
            }
            .date-text {
                white-space: normal;
                font-size: 0.7rem;
            }
            .action-text {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>

<!-- Particules animées -->


<div class="container">
    <div class="log-header">
        <div class="header-title">
            <h1>
                <i class="fa-solid fa-shield-halved"></i>
                Journal des Activités
            </h1>
            <p><i class="fa-regular fa-clock"></i> Suivi en temps réel des actions effectuées sur la plateforme</p>
        </div>
        <a href="admin_dashboard.php" class="btn-back">
            <i class="fa-solid fa-arrow-left"></i> Retour au Dashboard
        </a>
    </div>

    <!-- Statistiques -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #E0F2FE, #BAE6FD); color: #0369A1;">
                <i class="fa-solid fa-database"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value"><?= number_format($total_logs) ?></div>
                <div class="stat-label">Total des logs</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #DCFCE7, #BBF7D0); color: #166534;">
                <i class="fa-solid fa-calendar-day"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value"><?= number_format($total_actions_today) ?></div>
                <div class="stat-label">Actions aujourd'hui</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #FEF3C7, #FDE68A); color: #92400E;">
                <i class="fa-solid fa-chart-line"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value">100</div>
                <div class="stat-label">Derniers logs affichés</div>
            </div>
        </div>
    </div>

    <!-- Top utilisateurs -->
    <?php if(!empty($top_users)): ?>
    <div class="top-users">
        <h3><i class="fa-solid fa-trophy" style="color: var(--accent);"></i> Utilisateurs les plus actifs</h3>
        <?php $rank = 1; foreach($top_users as $user): ?>
        <div class="user-rank">
            <div class="rank-info">
                <div class="rank-number">#<?= $rank++ ?></div>
                <div class="rank-name">
                    <i class="fa-regular fa-circle-user"></i>
                    <?= htmlspecialchars($user['iden'] ?? 'Système') ?>
                </div>
            </div>
            <div class="rank-count"><?= $user['action_count'] ?> actions</div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Recherche et filtres -->
    <div class="search-box">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" id="logSearch" placeholder="Rechercher par utilisateur, action, matricule ou date...">
    </div>
    
    <div class="filters">
        <button class="filter-btn active" data-filter="all">Tous</button>
        <button class="filter-btn" data-filter="connexion">Connexions</button>
        <button class="filter-btn" data-filter="creation">Créations</button>
        <button class="filter-btn" data-filter="modification">Modifications</button>
        <button class="filter-btn" data-filter="suppression">Suppressions</button>
        <button class="filter-btn" data-filter="mutation">Mutations</button>
    </div>

    <div class="table-wrapper">
        <table id="logTable">
            <thead>
                <tr>
                    <th><i class="fa-regular fa-calendar"></i> Horodatage</th>
                    <th><i class="fa-solid fa-user"></i> Utilisateur</th>
                    <th><i class="fa-solid fa-tag"></i> Rôle</th>
                    <th><i class="fa-solid fa-circle-info"></i> Action effectuée</th>
                </tr>
            </thead>
            <tbody>
    <?php 
    $logs = [];
    while($row = $stmt->fetch()): 
        $logs[] = $row;
        
        $role_class = '';
        if(($row['rol'] ?? '') === 'admin') $role_class = 'role-admin';
        elseif(($row['rol'] ?? '') === 'admin_local') $role_class = 'role-local';
        elseif(($row['rol'] ?? '') === 'consultant') $role_class = 'role-consultant';
        else $role_class = 'role-auto';
        
        // 1. Conversion propre en minuscules (Support UTF-8/Accents)
        $raw_desc = $row['action_desc'] ?? $row['description'] ?? '';
        $desc_lower = mb_strtolower($raw_desc, 'UTF-8');

        // 2. Détermination du type d'action (plus flexible)
        $action_type = 'autre';
        if (mb_strpos($desc_lower, 'connexi') !== false || mb_strpos($desc_lower, 'connect') !== false) {
            $action_type = 'connexion';
        } elseif (mb_strpos($desc_lower, 'suppr') !== false) {
            $action_type = 'suppression';
        } elseif (mb_strpos($desc_lower, 'modif') !== false || mb_strpos($desc_lower, 'mise a jour') !== false || mb_strpos($desc_lower, 'mise à jour') !== false) {
            $action_type = 'modification';
        } elseif (mb_strpos($desc_lower, 'crea') !== false || mb_strpos($desc_lower, 'créa') !== false || mb_strpos($desc_lower, 'ajout') !== false) {
            $action_type = 'création'; // Ou 'creation' si ton bouton HTML utilise data-filter="creation"
        } elseif (mb_strpos($desc_lower, 'mutat') !== false || mb_strpos($desc_lower, 'muter') !== false) {
            $action_type = 'mutation';
        }
    ?>
    <tr data-action-type="<?= $action_type ?>">
        <td class="date-text">
            <i class="fa-regular fa-clock"></i> 
            <?= date('d/m/Y H:i:s', strtotime($row['dat_hist'] ?? $row['dat_log'] ?? 'now')) ?>
        </td>
        <td>
            <span class="user-name">
                <i class="fa-regular fa-circle-user"></i>
                <?php 
                    if (!empty($row['nom_fonc']) || !empty($row['prenom_fonc'])) {
                        echo htmlspecialchars(trim(($row['nom_fonc'] ?? '') . ' ' . ($row['prenom_fonc'] ?? '')));
                    } else {
                        echo htmlspecialchars($row['iden'] ?? 'Système');
                    }
                ?>
            </span>
        </td>
        <td>
            <span class="badge-role <?= $role_class ?>">
                <i class="fa-solid fa-shield-alt"></i>
                <?= strtoupper($row['rol'] ?? 'AUTO') ?>
            </span>
        </td>
        <td class="action-text">
            <?php 
                // Icônes dynamiques basées sur l'action_type détecté
                if($action_type === 'suppression') echo '<i class="fa-solid fa-trash-can" style="color:#EF4444;"></i> ';
                elseif($action_type === 'connexion') echo '<i class="fa-solid fa-right-to-bracket" style="color:#10B981;"></i> ';
                elseif($action_type === 'modification') echo '<i class="fa-solid fa-pen-nib" style="color:#F59E0B;"></i> ';
                elseif($action_type === 'création') echo '<i class="fa-solid fa-plus-circle" style="color:#3B82F6;"></i> ';
                elseif($action_type === 'mutation') echo '<i class="fa-solid fa-arrows-spin" style="color:#8B5CF6;"></i> ';
                else echo '<i class="fa-solid fa-info-circle" style="color:#94A3B8;"></i> ';
                
                // Formatage du texte avec le matricule en valeur mise en évidence
                $desc = htmlspecialchars($raw_desc ?: 'Action inconnue');
                $desc = preg_replace('/\(Matricule:\s*([^)]+)\)/i', '<span class="action-highlight">Matricule: $1</span>', $desc);
                echo $desc;
            ?> 
        </td>
    </tr>
    <?php endwhile; ?>
    
    <?php if(empty($logs)): ?>
    <tr>
        <td colspan="4" class="empty-state">
            <i class="fa-solid fa-database"></i>
            <p>Aucun log trouvé</p>
        </td>
    </tr>
    <?php endif; ?>
</tbody>
        </table>
    </div>
    
    <!-- Pagination simple -->
    <div class="pagination" id="pagination"></div>
</div>

<script>
// Recherche dynamique
const searchInput = document.getElementById('logSearch');
const tableRows = document.querySelectorAll('#logTable tbody tr');
let currentFilter = 'all';

function filterTable() {
    let searchTerm = searchInput.value.toLowerCase();
    let visibleCount = 0;
    
    tableRows.forEach(row => {
        if(row.cells.length === 1) return; // Skip empty state row
        
        const text = row.textContent.toLowerCase();
        const actionType = row.getAttribute('data-action-type');
        
        const matchesSearch = text.includes(searchTerm);
        const matchesFilter = currentFilter === 'all' || actionType === currentFilter;
        
        if(matchesSearch && matchesFilter) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    // Afficher un message si aucun résultat
    const emptyRow = document.querySelector('#logTable tbody tr.empty-state-row');
    if(visibleCount === 0 && !emptyRow) {
        const tbody = document.querySelector('#logTable tbody');
        const newEmptyRow = document.createElement('tr');
        newEmptyRow.className = 'empty-state-row';
        newEmptyRow.innerHTML = `
            <td colspan="4" class="empty-state">
                <i class="fa-solid fa-search"></i>
                <p>Aucun résultat trouvé</p>
            </td>
        `;
        tbody.appendChild(newEmptyRow);
    } else if(visibleCount > 0 && emptyRow) {
        emptyRow.remove();
    }
}

searchInput.addEventListener('keyup', filterTable);

// Filtres par type d'action
const filterBtns = document.querySelectorAll('.filter-btn');
filterBtns.forEach(btn => {
    btn.addEventListener('click', () => {
        filterBtns.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        currentFilter = btn.getAttribute('data-filter');
        filterTable();
    });
});

// Animation des lignes au chargement
document.addEventListener('DOMContentLoaded', () => {
    const rows = document.querySelectorAll('#logTable tbody tr');
    rows.forEach((row, index) => {
        if(row.cells.length > 1) {
            row.style.opacity = '0';
            row.style.transform = 'translateX(-10px)';
            setTimeout(() => {
                row.style.transition = 'all 0.3s ease';
                row.style.opacity = '1';
                row.style.transform = 'translateX(0)';
            }, index * 20);
        }
    });
});

// Pagination simple (10 lignes par page)
let currentPage = 1;
const rowsPerPage = 15;

function updatePagination() {
    const rows = Array.from(document.querySelectorAll('#logTable tbody tr')).filter(r => r.cells.length > 1 && r.style.display !== 'none');
    const totalPages = Math.ceil(rows.length / rowsPerPage);
    const paginationDiv = document.getElementById('pagination');
    
    if(totalPages <= 1) {
        paginationDiv.innerHTML = '';
        return;
    }
    
    let paginationHtml = '';
    for(let i = 1; i <= totalPages; i++) {
        paginationHtml += `<button class="page-btn ${i === currentPage ? 'active' : ''}" data-page="${i}">${i}</button>`;
    }
    paginationDiv.innerHTML = paginationHtml;
    
    // Afficher les lignes de la page courante
    const start = (currentPage - 1) * rowsPerPage;
    const end = start + rowsPerPage;
    
    rows.forEach((row, index) => {
        row.style.display = (index >= start && index < end) ? '' : 'none';
    });
    
    // Ajouter les événements aux boutons de pagination
    document.querySelectorAll('.page-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            currentPage = parseInt(btn.getAttribute('data-page'));
            updatePagination();
        });
    });
}

// Observer les changements de filtre pour mettre à jour la pagination
const observer = new MutationObserver(() => {
    currentPage = 1;
    updatePagination();
});
observer.observe(document.querySelector('#logTable tbody'), { childList: true, subtree: true });

// Initialiser la pagination
setTimeout(updatePagination, 100);

// Actualisation automatique des logs (toutes les 30 secondes)
let autoRefresh = true;
let refreshInterval;

function startAutoRefresh() {
    if(refreshInterval) clearInterval(refreshInterval);
    refreshInterval = setInterval(() => {
        if(autoRefresh) {
            location.reload();
        }
    }, 30000);
}

startAutoRefresh();

// Toggle auto-refresh (optionnel)
const refreshIndicator = document.createElement('div');
refreshIndicator.style.cssText = `
    position: fixed;
    bottom: 20px;
    right: 20px;
    background: rgba(10, 37, 64, 0.9);
    color: white;
    padding: 8px 15px;
    border-radius: 30px;
    font-size: 0.7rem;
    cursor: pointer;
    z-index: 1000;
    backdrop-filter: blur(10px);
`;
refreshIndicator.innerHTML = '<i class="fa-solid fa-sync-alt"></i> Auto-refresh ON';
refreshIndicator.onclick = () => {
    autoRefresh = !autoRefresh;
    refreshIndicator.innerHTML = autoRefresh ? '<i class="fa-solid fa-sync-alt"></i> Auto-refresh ON' : '<i class="fa-solid fa-pause"></i> Auto-refresh OFF';
    if(autoRefresh) startAutoRefresh();
    else clearInterval(refreshInterval);
};
document.body.appendChild(refreshIndicator);
</script>

</body>
</html>
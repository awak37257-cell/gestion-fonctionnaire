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

$user_role   = $_SESSION['rol'];
$user_org_id = $_SESSION['id_org'] ?? null;
$nom_session = $_SESSION['nom'] ?? 'Admin';
$stmtNotif = $db->prepare("SELECT COUNT(*) FROM NOTIFICATIONS WHERE id_user = ? AND statut = 'a_verifier'");
$stmtNotif->execute([(int)($_SESSION['id_user'] ?? 0)]);
$notifications_a_verifier = (int)$stmtNotif->fetchColumn();

// Vérification de sécurité : seuls les admins (global ou local) peuvent accéder
if (!in_array($user_role, ['admin', 'admin_local'])) {
    header("Location: ../saisie/login.php");
    exit();
}

// Helpers d'affichage
if (!function_exists('e')) {
    function e($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('initiales')) {
    function initiales($nom, $prenom = '') {
        $a = mb_substr(trim((string)$nom), 0, 1, 'UTF-8');
        $b = mb_substr(trim((string)$prenom), 0, 1, 'UTF-8');
        return mb_strtoupper($a . $b, 'UTF-8') ?: '?';
    }
}
$libelles_roles = [
    'admin'       => 'Administrateur central',
    'admin_local' => 'Administrateur local',
];
$libelle_role = $libelles_roles[$user_role] ?? ucfirst($user_role);

// 2. RÉCUPÉRATION DES PARAMÈTRES DE RECHERCHE, FILTRE ET PAGINATION
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$org_filter   = isset($_GET['org']) ? trim($_GET['org']) : '';
$org_search   = isset($_GET['org_search']) ? trim($_GET['org_search']) : '';
$page         = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page     = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], [5, 10, 25, 50, 100]) ? (int)$_GET['per_page'] : 10;

// Nom de l'organisation pour la barre supérieure
$nom_organisation_affichage = "Administration centrale";
if ($user_role === 'admin_local' && $user_org_id) {
    $stmtOrg = $db->prepare("SELECT nom_org FROM ORGANISATION WHERE id_org = ?");
    $stmtOrg->execute([$user_org_id]);
    $resOrg = $stmtOrg->fetch();
    if ($resOrg) $nom_organisation_affichage = $resOrg['nom_org'];
}

// 3. ORGANISATIONS POUR LE SÉLECTEUR DE FILTRE
$liste_organisations = $db->query("
    SELECT o.id_org, o.nom_org, o.sigle_org, COUNT(r.id_fonc) as nb_agents
    FROM ORGANISATION o
    LEFT JOIN RECEVOIR r ON o.id_org = r.id_org AND r.date_fin IS NULL
    GROUP BY o.id_org, o.nom_org, o.sigle_org
    ORDER BY o.nom_org ASC
")->fetchAll();

// 4. CONSTRUCTION DYNAMIQUE DE LA REQUÊTE SQL
$where_clauses = ["1=1"];
$params = [];

if ($user_role === 'admin_local') {
    if ($user_org_id) {
        $where_clauses[] = "r.id_org = ?";
        $params[] = $user_org_id;
    } else {
        $where_clauses[] = "1 = 0";
    }
} elseif ($user_role === 'admin') {
    if (!empty($org_filter)) {
        if ($org_filter === 'none') {
            $where_clauses[] = "r.id_org IS NULL";
        } else {
            $where_clauses[] = "r.id_org = ?";
            $params[] = $org_filter;
        }
    }
    if (!empty($org_search)) {
        $where_clauses[] = "(o.nom_org LIKE ? OR o.sigle_org LIKE ?)";
        $params[] = "%" . $org_search . "%";
        $params[] = "%" . $org_search . "%";
    }
}

// Recherche générale
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

// 5. NOMBRE TOTAL DE FONCTIONNAIRES CORRESPONDANTS
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

// Pagination
$total_pages = max(1, (int)ceil($total_items / $per_page));
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset     = ($page - 1) * $per_page;
$start_item = $total_items > 0 ? $offset + 1 : 0;
$end_item   = min($offset + $per_page, $total_items);

// 6. FONCTIONNAIRES DE LA PAGE COURANTE
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

// Liens de pagination et de raccourcis
if (!function_exists('getPaginationUrl')) {
    function getPaginationUrl($target_page) {
        $params = $_GET;
        $params['page'] = $target_page;
        return '?' . http_build_query($params);
    }
}
function url_filtre(array $over) {
    $p = $_GET;
    unset($p['page']);
    foreach ($over as $k => $v) {
        if ($v === null || $v === '') unset($p[$k]); else $p[$k] = $v;
    }
    $qs = http_build_query($p);
    return 'liste_fonctionnaires.php' . ($qs ? '?' . $qs : '');
}

// Libellé du filtre d'organisation actif
$org_label_actif = '';
if ($org_filter === 'none') {
    $org_label_actif = 'Sans affectation';
} elseif (!empty($org_filter)) {
    foreach ($liste_organisations as $lo) {
        if ($lo['id_org'] == $org_filter) { $org_label_actif = $lo['sigle_org'] ?: $lo['nom_org']; break; }
    }
}
$filtre_actif = ($user_role === 'admin' && ($org_filter !== '' || $org_search !== '')) || $search_query !== '';

// Raccourcis : les 5 organisations les plus peuplées
$raccourcis = $liste_organisations;
usort($raccourcis, function ($a, $b) { return (int)$b['nb_agents'] <=> (int)$a['nb_agents']; });
$raccourcis = array_slice($raccourcis, 0, 5);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des fonctionnaires | Ministère des Affaires étrangères</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --ink: #0A3B2C;
            --ink-2: #11553F;
            --ink-line: rgba(255, 255, 255, .10);
            --paper: #F2F6F3;
            --surface: #FFFFFF;
            --line: #E4E8EE;
            --line-soft: #EEF1F5;
            --text: #16222E;
            --muted: #6A7686;
            --accent: #F77F00;
            --accent-dark: #C25F00;
            --accent-soft: #FFEFD9;
            --green: #0E9F68;
            --green-soft: #DDF5EA;
            --blue: #2563EB;
            --blue-soft: #E3ECFF;
            --violet: #7C3AED;
            --violet-soft: #EEE6FF;
            --red: #C73A30;
            --red-soft: #FBE9E7;
            --amber: #B7791F;
            --amber-soft: #FBF1DC;
            --sidebar-w: 268px;
            --font-ui: 'Manrope', system-ui, -apple-system, 'Segoe UI', sans-serif;
            --font-display: 'Instrument Serif', Georgia, 'Times New Roman', serif;
        }

        *, *::before, *::after { box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }

        body {
            margin: 0;
            font-family: var(--font-ui);
            font-size: 14px;
            line-height: 1.5;
            color: var(--text);
            background: var(--paper);
            -webkit-font-smoothing: antialiased;
        }

        a { color: inherit; text-decoration: none; }
        button, input, select { font: inherit; color: inherit; }
        :focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; border-radius: 6px; }

        /* ---------- Layout ---------- */
        .shell { display: flex; min-height: 100vh; }

        .sidebar {
            position: fixed; inset: 0 auto 0 0;
            width: var(--sidebar-w);
            background: linear-gradient(180deg, #0E4A37 0%, #0A3B2C 55%, #072B20 100%);
            color: #fff;
            display: flex; flex-direction: column;
            z-index: 60;
            transition: transform .25s ease;
        }
        .brand { display: flex; align-items: center; gap: 12px; padding: 22px 20px 20px; border-bottom: 1px solid var(--ink-line); }
        .brand-mark { width: 40px; height: 40px; flex: none; border-radius: 10px; background: var(--accent); display: grid; place-items: center; font-size: 18px; color: #fff; }
        .brand-name { font-weight: 700; font-size: 14px; line-height: 1.25; }
        .brand-sub  { font-size: 12px; color: rgba(255,255,255,.55); margin-top: 2px; }

        .nav { flex: 1; overflow-y: auto; padding: 14px 12px; }
        .nav-group { font-size: 12px; font-weight: 600; color: rgba(255,255,255,.4); padding: 16px 12px 6px; }
        .nav-link {
            position: relative; display: flex; align-items: center; gap: 12px;
            padding: 10px 12px; border-radius: 8px;
            color: rgba(255,255,255,.72); font-weight: 500;
            transition: background .15s, color .15s;
        }
        .nav-link i { width: 18px; text-align: center; font-size: 14px; color: rgba(255,255,255,.45); transition: color .15s; }
        .nav-link:hover { background: rgba(255,255,255,.06); color: #fff; }
        .nav-link:hover i { color: rgba(255,255,255,.8); }
        .nav-link.active { background: rgba(247,127,0,.16); color: #fff; }
        .nav-link.active i { color: var(--accent); }
        .nav-link.active::before { content: ""; position: absolute; left: -12px; top: 8px; bottom: 8px; width: 3px; border-radius: 0 3px 3px 0; background: var(--accent); }

        .sidebar-user { display: flex; align-items: center; gap: 12px; padding: 16px 20px; border-top: 1px solid var(--ink-line); }
        .sidebar-user .name { font-weight: 600; font-size: 13px; line-height: 1.2; }
        .sidebar-user .role { font-size: 12px; color: rgba(255,255,255,.55); }

        .avatar {
            flex: none; width: 36px; height: 36px; border-radius: 50%; overflow: hidden;
            display: grid; place-items: center;
            font-size: 12px; font-weight: 700; letter-spacing: .3px;
            background: var(--ink-2); color: #fff;
        }
        .avatar.light { background: var(--green-soft); color: var(--green); }
        .avatar.md { width: 40px; height: 40px; font-size: 13px; }
        .avatar img { width: 100%; height: 100%; object-fit: cover; object-position: center; display: block; }

        .main { margin-left: var(--sidebar-w); width: calc(100% - var(--sidebar-w)); min-width: 0; }

        .topbar {
            position: sticky; top: 0; z-index: 40;
            display: flex; align-items: center; justify-content: space-between; gap: 16px;
            padding: 12px 32px;
            background: rgba(242,246,243,.88);
            backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--line);
        }
        .topbar-left, .topbar-right { display: flex; align-items: center; gap: 12px; min-width: 0; }

        .icon-btn {
            width: 38px; height: 38px; flex: none;
            display: inline-grid; place-items: center;
            border: 1px solid var(--line); background: var(--surface);
            border-radius: 9px; cursor: pointer; color: var(--muted);
            transition: color .15s, border-color .15s, background .15s;
        }
        .icon-btn:hover { color: var(--text); border-color: #CBD2DC; }
        .icon-btn.danger:hover { color: var(--red); border-color: #F0C4BF; background: var(--red-soft); }
        #menuToggleBtn { display: none; }

        .org-chip {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 7px 12px; background: #F7FCF9; border: 1px solid #CFE5D8;
            border-radius: 999px; font-weight: 600; font-size: 13px;
        }
        .org-chip i { color: var(--green); }
        .org-chip span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        .content { padding: 32px; max-width: 1560px; }

        /* ---------- Bannière ---------- */
        .page-head {
            position: relative; overflow: hidden;
            display: flex; align-items: flex-end; justify-content: space-between;
            gap: 20px; flex-wrap: wrap;
            margin-bottom: 24px; padding: 32px 32px 28px;
            border-radius: 16px; color: #fff;
            background:
                radial-gradient(520px 220px at 100% 0%, rgba(247,127,0,.38), transparent 70%),
                linear-gradient(120deg, #0A3B2C 0%, #11634A 100%);
        }
        .page-head::before {
            content: ""; position: absolute; top: 0; left: 0; right: 0; height: 5px;
            background: linear-gradient(90deg, #F77F00 0 33.33%, #FFFFFF 33.33% 66.66%, #009E60 66.66% 100%);
        }
        .page-head h1 {
            margin: 0; font-family: var(--font-display); font-weight: 400;
            font-size: clamp(30px, 4vw, 44px); line-height: 1.05; letter-spacing: -.01em;
        }
        .page-head p { margin: 8px 0 0; color: rgba(255,255,255,.78); font-size: 14px; max-width: 62ch; }

        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            padding: 10px 16px; border-radius: 9px; border: 1px solid transparent;
            font-weight: 600; font-size: 13px; cursor: pointer;
            transition: background .15s, border-color .15s, transform .1s;
        }
        .btn:active { transform: translateY(1px); }
        .btn-primary { background: var(--accent); color: #fff; box-shadow: 0 6px 16px rgba(247,127,0,.3); }
        .btn-primary:hover { background: #FF8F1F; }
        .btn-ghost { background: var(--surface); border-color: var(--line); color: var(--muted); }
        .btn-ghost:hover { color: var(--text); border-color: #CBD2DC; }

        /* ---------- KPI ---------- */
        .kpis {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            background: var(--surface); border: 1px solid var(--line);
            border-radius: 14px; margin-bottom: 24px; overflow: hidden;
        }
        .kpi { display: flex; align-items: center; gap: 16px; padding: 22px 24px; border-right: 1px solid var(--line); }
        .kpi:last-child { border-right: 0; }
        .kpi:nth-child(1) { background: linear-gradient(135deg, #E6F6EE, #FFFFFF 75%); }
        .kpi:nth-child(2) { background: linear-gradient(135deg, #FFF0DC, #FFFFFF 75%); }
        .kpi:nth-child(3) { background: linear-gradient(135deg, #E6EEFF, #FFFFFF 75%); }
        .kpi-icon { width: 44px; height: 44px; flex: none; display: grid; place-items: center; border-radius: 12px; font-size: 17px; color: #fff; box-shadow: 0 6px 14px rgba(0,0,0,.12); }
        .kpi-icon.i-green { background: var(--green); }
        .kpi-icon.i-warm  { background: var(--accent); }
        .kpi-icon.i-blue  { background: var(--blue); }
        .kpi-value { font-family: var(--font-display); font-size: 38px; line-height: 1; letter-spacing: -.01em; }
        .kpi-label { color: var(--muted); font-size: 13px; font-weight: 500; margin-top: 4px; }

        /* ---------- Panneaux ---------- */
        .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; }
        .panel + .panel { margin-top: 24px; }
        .panel-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 20px 24px 16px; }
        .panel-title { margin: 0; font-size: 16px; font-weight: 700; }
        .panel-sub { margin: 4px 0 0; color: var(--muted); font-size: 13px; }
        .panel-sub strong { color: var(--text); font-weight: 700; }

        /* ---------- Filtres ---------- */
        .filters { padding: 20px 24px; }
        .filter-grid { display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; }
        .f-group { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
        .f-group.grow   { flex: 1 1 280px; }
        .f-group.org    { flex: 0 1 300px; }
        .f-group.small  { flex: 0 0 150px; }
        .f-group label { font-size: 13px; font-weight: 600; }

        .control-wrap { position: relative; }
        .control-wrap > i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 13px; pointer-events: none; }
        .control {
            width: 100%; height: 40px; padding: 0 14px 0 38px;
            background: var(--paper); border: 1px solid transparent; border-radius: 9px;
            transition: border-color .15s, background .15s, box-shadow .15s;
        }
        select.control { appearance: none; cursor: pointer; padding-right: 34px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='none' stroke='%236A7686' stroke-width='1.6' stroke-linecap='round' d='M2.5 4.5 6 8l3.5-3.5'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 14px center; }
        .control::placeholder { color: #8A95A3; }
        .control:hover { border-color: var(--line); }
        .control:focus { outline: none; background-color: var(--surface); border-color: var(--green); box-shadow: 0 0 0 3px rgba(14,159,104,.15); }

        .filter-actions { display: flex; gap: 8px; }

        .quick { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--line-soft); }
        .quick-label { color: var(--muted); font-size: 13px; font-weight: 500; margin-right: 4px; }
        .chip {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 5px 12px; border-radius: 999px;
            border: 1px solid var(--line); background: var(--surface);
            font-weight: 600; font-size: 13px; color: var(--muted);
            transition: background .15s, color .15s, border-color .15s;
        }
        .chip:hover { color: var(--text); border-color: #CBD2DC; }
        .chip .n { font-size: 12px; font-weight: 700; padding: 1px 7px; border-radius: 999px; background: var(--paper); color: var(--muted); }
        .chip.active { background: var(--ink); border-color: var(--ink); color: #fff; }
        .chip.active .n { background: rgba(255,255,255,.18); color: #fff; }

        .tag-active {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 4px 12px; border-radius: 999px;
            background: var(--accent-soft); color: var(--accent-dark);
            font-size: 13px; font-weight: 600;
        }

        /* ---------- Table ---------- */
        .table-wrap { overflow-x: auto; border-top: 1px solid var(--line); }
        table { width: 100%; border-collapse: collapse; }
        thead th {
            text-align: left; white-space: nowrap; padding: 12px 16px;
            font-size: 12px; font-weight: 600; color: var(--ink-2);
            background: #EEF5F0; border-bottom: 1px solid var(--line);
        }
        thead th:first-child, tbody td:first-child { padding-left: 24px; }
        thead th:last-child,  tbody td:last-child  { padding-right: 24px; }
        tbody td { padding: 14px 16px; border-bottom: 1px solid var(--line-soft); vertical-align: middle; }
        tbody tr:last-child td { border-bottom: 0; }
        tbody tr[data-row]:hover { background: #F3FAF6; }

        .matricule {
            display: inline-block; padding: 3px 9px;
            background: var(--paper); border: 1px solid var(--line); border-radius: 6px;
            font-weight: 700; font-size: 12px; letter-spacing: .4px;
            font-variant-numeric: tabular-nums; white-space: nowrap;
        }

        .person { display: flex; align-items: center; gap: 12px; min-width: 230px; }
        .person-name { font-weight: 700; line-height: 1.25; }
        .person-first { color: var(--muted); font-weight: 500; }
        .person-meta { display: flex; align-items: center; gap: 10px; margin-top: 2px; color: var(--muted); font-size: 12px; }
        .person-meta i { font-size: 11px; }
        .g-f { color: #DB2777; }
        .g-m { color: var(--blue); }

        .org-name { font-weight: 500; }
        .org-sigle { display: block; font-size: 12px; color: var(--muted); }

        .post-title { font-weight: 600; line-height: 1.3; }
        .pill { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap; background: var(--accent-soft); color: var(--accent-dark); margin-top: 4px; }
        .pill.warn { background: var(--amber-soft); color: var(--amber); margin-top: 0; }

        .where { white-space: nowrap; }
        .where .city i { color: var(--accent); margin-right: 6px; font-size: 12px; }
        .where .since { color: var(--muted); font-size: 12px; margin-top: 2px; }

        .actions { display: flex; justify-content: flex-end; gap: 6px; }
        .act { width: 32px; height: 32px; display: inline-grid; place-items: center; border-radius: 8px; color: var(--muted); font-size: 13px; transition: background .15s, color .15s; }
        .act:hover { background: var(--paper); color: var(--text); }
        .act.edit:hover   { background: var(--blue-soft);  color: var(--blue); }
        .act.move:hover   { background: var(--amber-soft); color: var(--amber); }
        .act.delete:hover { background: var(--red-soft);   color: var(--red); }

        .empty { text-align: center; padding: 56px 24px !important; color: var(--muted); }
        .empty .empty-ico { width: 64px; height: 64px; margin: 0 auto 16px; display: grid; place-items: center; border-radius: 50%; background: var(--paper); color: #B5BEC9; font-size: 26px; }
        .empty strong { display: block; color: var(--text); font-size: 15px; margin-bottom: 4px; }
        .empty p { margin: 0 auto 18px; max-width: 46ch; }

        /* ---------- Pagination serveur ---------- */
        .pager { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 14px 24px; border-top: 1px solid var(--line); }
        .pager-info { color: var(--muted); font-size: 13px; }
        .pager ul { display: flex; gap: 4px; list-style: none; margin: 0; padding: 0; flex-wrap: wrap; align-items: center; }
        .page-btn { display: inline-grid; place-items: center; min-width: 34px; height: 34px; padding: 0 8px; border-radius: 8px; font-weight: 600; font-size: 13px; color: var(--muted); transition: background .15s, color .15s; }
        .page-btn:hover { background: var(--paper); color: var(--text); }
        .page-btn.active { background: var(--accent); color: #fff; }
        .page-btn.disabled { opacity: .35; pointer-events: none; }
        .page-dots { display: inline-grid; place-items: center; min-width: 28px; color: var(--muted); }

        .overlay { position: fixed; inset: 0; z-index: 50; background: rgba(10,59,44,.5); opacity: 0; pointer-events: none; transition: opacity .25s; }
        .overlay.show { opacity: 1; pointer-events: auto; }

        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); box-shadow: 0 0 40px rgba(0,0,0,.3); }
            .main { margin-left: 0; width: 100%; }
            #menuToggleBtn { display: inline-grid; }
            .topbar { padding: 12px 16px; }
            .content { padding: 20px 16px 32px; }
            .page-head { padding: 28px 20px 22px; }
            .kpi { border-right: 0; border-bottom: 1px solid var(--line); }
            .kpi:last-child { border-bottom: 0; }
            .panel-head, .filters, .pager { padding-left: 16px; padding-right: 16px; }
            thead th:first-child, tbody td:first-child { padding-left: 16px; }
            thead th:last-child,  tbody td:last-child  { padding-right: 16px; }
            .f-group.small, .f-group.org { flex: 1 1 100%; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { transition: none !important; animation: none !important; }
        }
    </style>
</head>
<body>

<div class="shell">

    <?php $sidebar_page = 'liste_fonctionnaires'; $sidebar_base = ''; require_once __DIR__ . '/../includes/sidebar_admin.php'; ?>

    <main class="main">

        <header class="topbar">
            <div class="topbar-left">
                <button type="button" id="menuToggleBtn" class="icon-btn" aria-label="Ouvrir le menu" aria-controls="sidebarAdmin">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="org-chip">
                    <i class="fa-solid fa-building-shield"></i>
                    <span><?php echo e($nom_organisation_affichage); ?></span>
                </div>
            </div>
            <div class="topbar-right">
                <a href="../logout.php" class="icon-btn danger" title="Se déconnecter" aria-label="Se déconnecter">
                    <i class="fa-solid fa-power-off"></i>
                </a>
            </div>
        </header>

        <div class="content">

            <div class="page-head">
                <div>
                    <h1>Liste des fonctionnaires</h1>
                    <p>Consultez les agents, filtrez par organisation et accédez à leur dossier.</p>
                </div>
                <a href="../saisie/ajouter_agent.php" class="btn btn-primary">
                    <i class="fa-solid fa-user-plus"></i> Nouveau fonctionnaire
                </a>
            </div>

            <!-- Indicateurs -->
            <section class="kpis" aria-label="Indicateurs clés">
                <div class="kpi">
                    <div class="kpi-icon i-green"><i class="fa-solid fa-users"></i></div>
                    <div>
                        <div class="kpi-value"><?php echo number_format((int)$total_global_agents, 0, ',', ' '); ?></div>
                        <div class="kpi-label">Agents au total</div>
                    </div>
                </div>
                <div class="kpi">
                    <div class="kpi-icon i-warm"><i class="fa-solid fa-sitemap"></i></div>
                    <div>
                        <div class="kpi-value"><?php echo number_format((int)$total_global_orgs, 0, ',', ' '); ?></div>
                        <div class="kpi-label">Organisations</div>
                    </div>
                </div>
                <div class="kpi">
                    <div class="kpi-icon i-blue"><i class="fa-solid fa-filter"></i></div>
                    <div>
                        <div class="kpi-value"><?php echo number_format($total_items, 0, ',', ' '); ?></div>
                        <div class="kpi-label"><?php echo $filtre_actif ? 'Résultats du filtre' : 'Agents affichés'; ?></div>
                    </div>
                </div>
            </section>

            <!-- Filtres -->
            <form method="GET" action="liste_fonctionnaires.php" class="panel filters" id="filterForm">
                <div class="filter-grid">

                    <?php if ($user_role === 'admin'): ?>
                    <div class="f-group org">
                        <label for="orgSelect">Organisation</label>
                        <div class="control-wrap">
                            <i class="fa-solid fa-building"></i>
                            <select name="org" id="orgSelect" class="control" data-autosubmit>
                                <option value="">Toutes les organisations (<?php echo (int)$total_global_agents; ?>)</option>
                                <?php foreach ($liste_organisations as $org): ?>
                                    <option value="<?php echo (int)$org['id_org']; ?>" <?php echo ($org_filter == $org['id_org']) ? 'selected' : ''; ?>>
                                        <?php echo e(!empty($org['sigle_org']) ? "[{$org['sigle_org']}] " : ''); ?><?php echo e($org['nom_org']); ?> (<?php echo (int)$org['nb_agents']; ?>)
                                    </option>
                                <?php endforeach; ?>
                                <option value="none" <?php echo ($org_filter === 'none') ? 'selected' : ''; ?>>Sans affectation</option>
                            </select>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="f-group grow">
                        <label for="search_query">Recherche</label>
                        <div class="control-wrap">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="search" name="search" id="search_query" class="control" value="<?php echo e($search_query); ?>" placeholder="Nom, prénom, matricule, grade, ville" autocomplete="off">
                        </div>
                    </div>

                    <div class="f-group small">
                        <label for="per_page">Lignes par page</label>
                        <div class="control-wrap">
                            <i class="fa-solid fa-list-ol"></i>
                            <select name="per_page" id="per_page" class="control" data-autosubmit>
                                <?php foreach ([10, 25, 50, 100] as $n): ?>
                                    <option value="<?php echo $n; ?>" <?php echo ($per_page == $n) ? 'selected' : ''; ?>><?php echo $n; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary" title="Appliquer les filtres">
                            <i class="fa-solid fa-filter"></i> Filtrer
                        </button>
                        <?php if ($filtre_actif): ?>
                            <a href="liste_fonctionnaires.php" class="btn btn-ghost" title="Réinitialiser les filtres" aria-label="Réinitialiser les filtres">
                                <i class="fa-solid fa-rotate-left"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($user_role === 'admin' && !empty($raccourcis)): ?>
                <div class="quick">
                    <span class="quick-label">Organisations les plus peuplées</span>
                    <a href="<?php echo e(url_filtre(['org' => null])); ?>" class="chip <?php echo ($org_filter === '') ? 'active' : ''; ?>">Toutes</a>
                    <?php foreach ($raccourcis as $qo): ?>
                        <a href="<?php echo e(url_filtre(['org' => $qo['id_org']])); ?>"
                           class="chip <?php echo ($org_filter == $qo['id_org']) ? 'active' : ''; ?>"
                           title="<?php echo e($qo['nom_org']); ?>">
                            <?php echo e($qo['sigle_org'] ?: mb_substr((string)$qo['nom_org'], 0, 15, 'UTF-8')); ?>
                            <span class="n"><?php echo (int)$qo['nb_agents']; ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </form>

            <!-- Tableau -->
            <section class="panel" aria-labelledby="listeTitre">
                <div class="panel-head">
                    <div>
                        <h2 class="panel-title" id="listeTitre">Agents de l’État</h2>
                        <p class="panel-sub">
                            <?php if ($total_items > 0): ?>
                                <strong><?php echo $start_item; ?></strong> à <strong><?php echo $end_item; ?></strong> sur <strong><?php echo number_format($total_items, 0, ',', ' '); ?></strong>
                            <?php else: ?>
                                Aucun agent à afficher
                            <?php endif; ?>
                        </p>
                    </div>

                    <?php if ($filtre_actif): ?>
                        <span class="tag-active">
                            <i class="fa-solid fa-filter"></i>
                            <?php
                                $morceaux = [];
                                if ($org_label_actif !== '' && $user_role === 'admin') $morceaux[] = e($org_label_actif);
                                if ($search_query !== '') $morceaux[] = '« ' . e($search_query) . ' »';
                                echo implode(' · ', $morceaux);
                            ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Matricule</th>
                                <th>Agent</th>
                                <th>Organisation</th>
                                <th>Poste et grade</th>
                                <th>Affectation</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($fonctionnaires)): ?>
                                <?php foreach ($fonctionnaires as $f):
                                    $photo_path = !empty($f['photo_fonc']) ? '../uploads/photos/' . $f['photo_fonc'] : '';
                                    $has_photo  = !empty($f['photo_fonc']) && file_exists(__DIR__ . '/../uploads/photos/' . $f['photo_fonc']);
                                    $nom_complet = trim(($f['nom_fonc'] ?? '') . ' ' . ($f['prenom_fonc'] ?? ''));
                                ?>
                                <tr data-row>
                                    <td><span class="matricule"><?php echo e($f['matri']); ?></span></td>

                                    <td>
                                        <div class="person">
                                            <div class="avatar md light">
                                                <?php if ($has_photo): ?>
                                                    <img src="<?php echo e($photo_path); ?>" alt="<?php echo e($nom_complet); ?>">
                                                <?php else: ?>
                                                    <?php echo e(initiales($f['nom_fonc'], $f['prenom_fonc'])); ?>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <div class="person-name">
                                                    <?php echo e(mb_strtoupper((string)$f['nom_fonc'], 'UTF-8')); ?>
                                                    <span class="person-first"><?php echo e($f['prenom_fonc']); ?></span>
                                                </div>
                                                <?php if (!empty($f['gen']) || !empty($f['cont'])): ?>
                                                <div class="person-meta">
                                                    <?php if (!empty($f['gen'])): ?>
                                                        <span><i class="fa-solid <?php echo ($f['gen'] === 'F') ? 'fa-venus g-f' : 'fa-mars g-m'; ?>"></i> <?php echo e($f['gen']); ?></span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($f['cont'])): ?>
                                                        <span><i class="fa-solid fa-phone"></i> <?php echo e($f['cont']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <?php if (!empty($f['nom_org'])): ?>
                                            <span class="org-name" title="<?php echo e($f['nom_org']); ?>"><?php echo e(mb_strimwidth((string)$f['nom_org'], 0, 36, '…', 'UTF-8')); ?></span>
                                            <?php if (!empty($f['sigle_org'])): ?>
                                                <span class="org-sigle"><?php echo e($f['sigle_org']); ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="pill warn"><i class="fa-solid fa-triangle-exclamation"></i> Non affecté</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <div class="post-title"><?php echo e($f['tit_post'] ?? 'Fonctionnaire'); ?></div>
                                        <span class="pill">Grade <?php echo e($f['gra_post'] ?? 'N/A'); ?></span>
                                    </td>

                                    <td class="where">
                                        <div class="city"><i class="fa-solid fa-location-dot"></i><?php echo e($f['vil_affec'] ?? 'N/A'); ?></div>
                                        <?php if (!empty($f['date_affectation'])): ?>
                                            <div class="since">Depuis le <?php echo date('d/m/Y', strtotime($f['date_affectation'])); ?></div>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <div class="actions">
                                            <a href="profil_agent.php?id=<?php echo (int)$f['id_fonc']; ?>" class="act" title="Voir la fiche" aria-label="Voir la fiche">
                                                <i class="fa-solid fa-eye"></i>
                                            </a>
                                            <?php if ($user_role !== 'consultant'): ?>
                                                <a href="modifier_agent.php?id=<?php echo (int)$f['id_fonc']; ?>" class="act edit" title="Modifier" aria-label="Modifier">
                                                    <i class="fa-solid fa-pen"></i>
                                                </a>
                                                <a href="../saisie/muter_agent.php?id=<?php echo (int)$f['id_fonc']; ?>" class="act move" title="Muter dans une autre structure" aria-label="Muter">
                                                    <i class="fa-solid fa-arrows-rotate"></i>
                                                </a>
                                                <a href="../supprimer_agent.php?id=<?php echo (int)$f['id_fonc']; ?>" class="act delete" title="Supprimer" aria-label="Supprimer"
                                                   data-confirm-delete data-nom="<?php echo e($nom_complet); ?>">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="empty">
                                        <div class="empty-ico"><i class="fa-solid fa-users-slash"></i></div>
                                        <strong>Aucun fonctionnaire trouvé</strong>
                                        <p>Aucun agent ne correspond à ces critères. Modifiez la recherche ou réinitialisez les filtres.</p>
                                        <a href="liste_fonctionnaires.php" class="btn btn-ghost">
                                            <i class="fa-solid fa-rotate-left"></i> Réinitialiser les filtres
                                        </a>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1): ?>
                <nav class="pager" aria-label="Pagination">
                    <div class="pager-info">
                        Page <strong><?php echo $page; ?></strong> sur <strong><?php echo $total_pages; ?></strong>
                    </div>

                    <ul>
                        <li>
                            <a href="<?php echo ($page > 1) ? e(getPaginationUrl($page - 1)) : '#'; ?>"
                               class="page-btn <?php echo ($page <= 1) ? 'disabled' : ''; ?>"
                               aria-label="Page précédente" <?php echo ($page <= 1) ? 'aria-disabled="true" tabindex="-1"' : ''; ?>>
                                <i class="fa-solid fa-chevron-left"></i>
                            </a>
                        </li>

                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page   = min($total_pages, $page + 2);

                        if ($start_page > 1) {
                            echo '<li><a href="' . e(getPaginationUrl(1)) . '" class="page-btn">1</a></li>';
                            if ($start_page > 2) echo '<li><span class="page-dots">…</span></li>';
                        }
                        for ($i = $start_page; $i <= $end_page; $i++) {
                            $active = ($i == $page) ? 'active' : '';
                            $cur = ($i == $page) ? ' aria-current="page"' : '';
                            echo '<li><a href="' . e(getPaginationUrl($i)) . '" class="page-btn ' . $active . '"' . $cur . '>' . $i . '</a></li>';
                        }
                        if ($end_page < $total_pages) {
                            if ($end_page < $total_pages - 1) echo '<li><span class="page-dots">…</span></li>';
                            echo '<li><a href="' . e(getPaginationUrl($total_pages)) . '" class="page-btn">' . $total_pages . '</a></li>';
                        }
                        ?>

                        <li>
                            <a href="<?php echo ($page < $total_pages) ? e(getPaginationUrl($page + 1)) : '#'; ?>"
                               class="page-btn <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>"
                               aria-label="Page suivante" <?php echo ($page >= $total_pages) ? 'aria-disabled="true" tabindex="-1"' : ''; ?>>
                                <i class="fa-solid fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            </section>

        </div>
    </main>
</div>

<script>
    // Menu mobile
    (function () {
        const toggleBtn = document.getElementById('menuToggleBtn');
        const sidebar   = document.getElementById('sidebarAdmin');
        const overlay   = document.getElementById('overlay');
        if (!toggleBtn || !sidebar) return;
        function setOpen(open) {
            sidebar.classList.toggle('open', open);
            overlay.classList.toggle('show', open);
            toggleBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
        toggleBtn.addEventListener('click', () => setOpen(!sidebar.classList.contains('open')));
        overlay.addEventListener('click', () => setOpen(false));
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') setOpen(false); });
    })();

    // Envoi automatique du formulaire quand on change l'organisation ou le nombre de lignes
    document.querySelectorAll('[data-autosubmit]').forEach(el => {
        el.addEventListener('change', () => document.getElementById('filterForm').submit());
    });

    // Confirmation de suppression (le nom passe par un attribut data-*, sans risque avec les apostrophes)
    document.querySelectorAll('[data-confirm-delete]').forEach(link => {
        link.addEventListener('click', (e) => {
            const nom = link.getAttribute('data-nom') || 'cet agent';
            if (!confirm('Supprimer définitivement ' + nom + ' ? Cette action est irréversible.')) {
                e.preventDefault();
            }
        });
    });
</script>
</body>
</html>
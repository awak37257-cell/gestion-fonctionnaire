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

$user_role   = $_SESSION['rol'];
$user_org_id = $_SESSION['id_org'] ?? null;
$stmtNotifications = $db->prepare("SELECT COUNT(*) FROM NOTIFICATIONS WHERE id_user = ? AND statut = 'a_verifier'");
$stmtNotifications->execute([(int)($_SESSION['id_user'] ?? 0)]);
$notifications_a_verifier = (int)$stmtNotifications->fetchColumn();

// Initialisations des compteurs
$total_agents = 0;
$total_directions = 0;
$total_diplomes = 0;
$total_admins_locaux = 0;
$nom_organisation_affichage = "Administration centrale";
$liste_admins_locaux = [];
$liste_organisations = [];

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
$jours = ['dimanche','lundi','mardi','mercredi','jeudi','vendredi','samedi'];
$mois  = ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
$date_du_jour = $jours[(int)date('w')] . ' ' . date('j') . ' ' . $mois[(int)date('n') - 1] . ' ' . date('Y');
$libelles_roles = [
    'admin'       => 'Administrateur central',
    'admin_local' => 'Administrateur local',
    'consultant'  => 'Consultant',
];
$libelle_role = $libelles_roles[$user_role] ?? ucfirst($user_role);

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
        SELECT u.iden, o.nom_org, f.nom_fonc, f.prenom_fonc, g.nom_gest, g.prenom_gest, g.email_gest
        FROM UTILISATEURS u
        LEFT JOIN ORGANISATION o ON u.id_org = o.id_org
        LEFT JOIN FONCTIONNAIRE f ON u.id_fonc = f.id_fonc
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

// 3b. FLUX D'ACTIVITÉS
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
$agents = $stmtTable->fetchAll();

$prenom_session = $_SESSION['nom'] ?? 'Admin';
$nom_session      = $prenom_session; // alias pour le sidebar partagé
$nom_affichage_session = $prenom_session;
if ($user_role === 'admin_local' && isset($_SESSION['id_user'])) {
    $stmtGestionnaire = $db->prepare("SELECT f.nom_fonc, f.prenom_fonc, g.nom_gest, g.prenom_gest
        FROM UTILISATEURS u
        LEFT JOIN FONCTIONNAIRE f ON f.id_fonc = u.id_fonc
        LEFT JOIN GESTIONNAIRE g ON g.id_user = u.id_user
        WHERE u.id_user = ?");
    $stmtGestionnaire->execute([$_SESSION['id_user']]);
    $gestionnaire = $stmtGestionnaire->fetch();
    if ($gestionnaire) {
        $prenom_admin_local = $gestionnaire['prenom_fonc'] ?: $gestionnaire['prenom_gest'];
        $nom_admin_local = $gestionnaire['nom_fonc'] ?: $gestionnaire['nom_gest'];
        $nom_affichage_session = trim(($prenom_admin_local ?? '') . ' ' . ($nom_admin_local ?? ''));
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord | Ministère des Affaires étrangères</title>
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
            --radius: 10px;
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

        :focus-visible {
            outline: 2px solid var(--accent);
            outline-offset: 2px;
            border-radius: 6px;
        }

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

        .brand {
            display: flex; align-items: center; gap: 12px;
            padding: 22px 20px 20px;
            border-bottom: 1px solid var(--ink-line);
        }
        .brand-mark {
            width: 40px; height: 40px; flex: none;
            border-radius: 10px;
            background: var(--accent);
            display: grid; place-items: center;
            font-size: 18px; color: #fff;
        }
        .brand-name { font-weight: 700; font-size: 14px; line-height: 1.25; }
        .brand-sub  { font-size: 12px; color: rgba(255,255,255,.55); margin-top: 2px; }

        .nav { flex: 1; overflow-y: auto; padding: 14px 12px; }
        .nav-group {
            font-size: 12px; font-weight: 600;
            color: rgba(255,255,255,.4);
            padding: 16px 12px 6px;
        }
        .nav-link {
            position: relative;
            display: flex; align-items: center; gap: 12px;
            padding: 10px 12px;
            border-radius: 8px;
            color: rgba(255,255,255,.72);
            font-weight: 500;
            transition: background .15s, color .15s;
        }
        .nav-link i { width: 18px; text-align: center; font-size: 14px; color: rgba(255,255,255,.45); transition: color .15s; }
        .nav-link:hover { background: rgba(255,255,255,.06); color: #fff; }
        .nav-link:hover i { color: rgba(255,255,255,.8); }
        .nav-link.active { background: rgba(247,127,0,.16); color: #fff; }
        .nav-link.active i { color: var(--accent); }
        .nav-link.active::before {
            content: ""; position: absolute; left: -12px; top: 8px; bottom: 8px;
            width: 3px; border-radius: 0 3px 3px 0; background: var(--accent);
        }

        .sidebar-user {
            display: flex; align-items: center; gap: 12px;
            padding: 16px 20px;
            border-top: 1px solid var(--ink-line);
        }
        .sidebar-user .name { font-weight: 600; font-size: 13px; line-height: 1.2; }
        .sidebar-user .role { font-size: 12px; color: rgba(255,255,255,.55); }

        .avatar {
            flex: none;
            width: 36px; height: 36px; border-radius: 50%;
            display: grid; place-items: center;
            font-size: 12px; font-weight: 700; letter-spacing: .3px;
            background: var(--ink-2); color: #fff;
        }
        .avatar.light { background: var(--green-soft); color: var(--green); }
        .avatar.warm  { background: var(--accent-soft); color: var(--accent-dark); }
        .avatar.sm { width: 32px; height: 32px; font-size: 11px; }

        .main {
            margin-left: var(--sidebar-w);
            width: calc(100% - var(--sidebar-w));
            min-width: 0;
        }

        .topbar {
            position: sticky; top: 0; z-index: 40;
            display: flex; align-items: center; justify-content: space-between;
            gap: 16px;
            padding: 12px 32px;
            background: rgba(243,245,248,.88);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
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
            padding: 7px 12px;
            background: var(--surface); border: 1px solid var(--line);
            border-radius: 999px;
            font-weight: 600; font-size: 13px;
            max-width: 100%;
        }
        .org-chip i { color: var(--green); }
        .org-chip { border-color: #CFE5D8; background: #F7FCF9; }
        .org-chip span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        .content { padding: 32px; max-width: 1560px; }

        /* ---------- Page header ---------- */
        .page-head {
            position: relative; overflow: hidden;
            display: flex; align-items: flex-end; justify-content: space-between;
            gap: 20px; flex-wrap: wrap;
            margin-bottom: 24px;
            padding: 32px 32px 28px;
            border-radius: 16px;
            color: #fff;
            background:
                radial-gradient(520px 220px at 100% 0%, rgba(247,127,0,.38), transparent 70%),
                linear-gradient(120deg, #0A3B2C 0%, #11634A 100%);
        }
        .page-head::before {
            content: ""; position: absolute; top: 0; left: 0; right: 0; height: 5px;
            background: linear-gradient(90deg, #F77F00 0 33.33%, #FFFFFF 33.33% 66.66%, #009E60 66.66% 100%);
        }
        .page-head h1 {
            margin: 0;
            font-family: var(--font-display);
            font-weight: 400;
            font-size: clamp(30px, 4vw, 44px);
            line-height: 1.05;
            letter-spacing: -.01em;
        }
        .page-head p { margin: 8px 0 0; color: rgba(255,255,255,.78); font-size: 14px; }

        .btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 10px 16px;
            border-radius: 9px; border: 1px solid transparent;
            font-weight: 600; font-size: 13px; cursor: pointer;
            transition: background .15s, border-color .15s, transform .1s;
        }
        .btn:active { transform: translateY(1px); }
        .btn-primary { background: var(--accent); color: #fff; box-shadow: 0 6px 16px rgba(247,127,0,.35); }
        .btn-primary:hover { background: #FF8F1F; }

        /* ---------- Alerts ---------- */
        .alert {
            display: flex; align-items: center; gap: 12px;
            padding: 12px 16px; margin-bottom: 20px;
            background: var(--green-soft); color: #14633F;
            border: 1px solid #BFE3D0; border-radius: var(--radius);
            font-weight: 500;
        }
        .alert .close { margin-left: auto; background: none; border: 0; cursor: pointer; color: inherit; opacity: .6; }
        .alert .close:hover { opacity: 1; }

        /* ---------- KPI strip ---------- */
        .kpis {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 14px;
            margin-bottom: 24px;
            overflow: hidden;
        }
        .kpi {
            display: flex; align-items: center; gap: 16px;
            padding: 22px 24px;
            border-right: 1px solid var(--line);
        }
        .kpi:last-child { border-right: 0; }
        .kpi-icon {
            width: 44px; height: 44px; flex: none;
            display: grid; place-items: center;
            border-radius: 12px; font-size: 17px;
        }
        .kpi:nth-child(1) { background: linear-gradient(135deg, #E6F6EE, #FFFFFF 75%); }
        .kpi:nth-child(2) { background: linear-gradient(135deg, #FFF0DC, #FFFFFF 75%); }
        .kpi:nth-child(3) { background: linear-gradient(135deg, #E6EEFF, #FFFFFF 75%); }
        .kpi:nth-child(4) { background: linear-gradient(135deg, #F0E9FF, #FFFFFF 75%); }
        .kpi-icon { color: #fff; box-shadow: 0 6px 14px rgba(0,0,0,.12); }
        .kpi-icon.i-ink   { background: var(--green); }
        .kpi-icon.i-warm  { background: var(--accent); }
        .kpi-icon.i-green { background: var(--blue); }
        .kpi-icon.i-blue  { background: var(--violet); }
        .kpi-value {
            font-family: var(--font-display);
            font-size: 38px; line-height: 1;
            letter-spacing: -.01em;
            font-variant-numeric: lining-nums;
        }
        .kpi-label { color: var(--muted); font-size: 13px; font-weight: 500; margin-top: 4px; }

        /* ---------- Main grid ---------- */
        .grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 24px;
            align-items: start;
        }
        @media (min-width: 1320px) {
            .grid { grid-template-columns: minmax(0, 1fr) 320px; }
        }

        .panel {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 14px;
        }
        .panel-head {
            display: flex; align-items: center; justify-content: space-between;
            gap: 12px; flex-wrap: wrap;
            padding: 20px 24px 0;
        }
        .panel-title { margin: 0; font-size: 16px; font-weight: 700; }
        .panel-meta { color: var(--muted); font-size: 13px; }

        /* Toolbar */
        .toolbar {
            display: flex; gap: 12px; flex-wrap: wrap;
            padding: 16px 24px 18px;
        }
        .field {
            position: relative; flex: 1 1 280px; min-width: 0;
        }
        .field.select { flex: 0 1 260px; }
        .field > i {
            position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
            color: var(--muted); font-size: 13px; pointer-events: none;
        }
        .field input, .field select {
            width: 100%;
            height: 40px;
            padding: 0 14px 0 38px;
            background: var(--paper);
            border: 1px solid transparent;
            border-radius: 9px;
            transition: border-color .15s, background .15s;
        }
        .field select { appearance: none; cursor: pointer; padding-right: 36px; }
        .field.select::after {
            content: "\f107"; font-family: "Font Awesome 6 Free"; font-weight: 900;
            position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
            color: var(--muted); pointer-events: none;
        }
        .field input::placeholder { color: #8A95A3; }
        .field input:hover, .field select:hover { border-color: var(--line); }
        .field input:focus, .field select:focus {
            outline: none; background: var(--surface); border-color: var(--green);
            box-shadow: 0 0 0 3px rgba(14,159,104,.15);
        }

        /* Table */
        .table-wrap { overflow-x: auto; border-top: 1px solid var(--line); }
        table { width: 100%; border-collapse: collapse; }
        thead th {
            text-align: left; white-space: nowrap;
            padding: 12px 16px;
            font-size: 12px; font-weight: 600; color: var(--muted);
            background: #EEF5F0; color: var(--ink-2);
            border-bottom: 1px solid var(--line);
        }
        thead th:first-child, tbody td:first-child { padding-left: 24px; }
        thead th:last-child,  tbody td:last-child  { padding-right: 24px; }
        tbody td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--line-soft);
            vertical-align: middle;
        }
        tbody tr:last-child td { border-bottom: 0; }
        tbody tr[data-row]:hover { background: #F3FAF6; }

        .matricule {
            display: inline-block;
            padding: 3px 9px;
            background: var(--paper);
            border: 1px solid var(--line);
            border-radius: 6px;
            font-weight: 700; font-size: 12px; letter-spacing: .4px;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .person { display: flex; align-items: center; gap: 12px; min-width: 200px; }
        .person-name { font-weight: 700; line-height: 1.25; }
        .person-first { color: var(--muted); font-weight: 500; }

        .org-name { font-weight: 500; }
        .org-sigle { display: block; font-size: 12px; color: var(--muted); }

        .pill {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 12px; font-weight: 600; white-space: nowrap;
            background: var(--accent-soft); color: var(--accent-dark);
        }
        .pill.muted { background: var(--paper); color: var(--muted); font-weight: 500; }

        .cell-muted { color: var(--muted); }

        .actions { display: flex; justify-content: flex-end; gap: 6px; }
        .act {
            width: 32px; height: 32px;
            display: inline-grid; place-items: center;
            border-radius: 8px; color: var(--muted);
            font-size: 13px;
            transition: background .15s, color .15s;
        }
        .act:hover { background: var(--paper); color: var(--text); }
        .act.edit:hover   { background: var(--blue-soft);  color: var(--blue); }
        .act.move:hover   { background: var(--amber-soft); color: var(--amber); }
        .act.delete:hover { background: var(--red-soft);   color: var(--red); }

        .empty {
            text-align: center; padding: 48px 24px !important;
            color: var(--muted);
        }
        .empty i { display: block; font-size: 28px; margin-bottom: 12px; color: #B5BEC9; }
        .empty strong { display: block; color: var(--text); margin-bottom: 4px; }
        .empty a { color: var(--accent-dark); font-weight: 600; }
        .empty a:hover { text-decoration: underline; }

        /* Pagination */
        .pager {
            display: none;
            align-items: center; justify-content: space-between;
            gap: 12px; flex-wrap: wrap;
            padding: 14px 24px;
            border-top: 1px solid var(--line);
        }
        .pager-info { color: var(--muted); font-size: 13px; }
        .pager ul { display: flex; gap: 4px; list-style: none; margin: 0; padding: 0; flex-wrap: wrap; }
        .page-btn {
            min-width: 34px; height: 34px; padding: 0 8px;
            border: 1px solid transparent; background: none;
            border-radius: 8px; cursor: pointer;
            font-weight: 600; font-size: 13px; color: var(--muted);
            transition: background .15s, color .15s;
        }
        .page-btn:hover { background: var(--paper); color: var(--text); }
        .page-btn.active { background: var(--accent); color: #fff; }
        .page-btn.disabled { opacity: .35; pointer-events: none; }

        /* Side rail */
        .rail { display: grid; gap: 24px; }
        .rail .panel { padding: 20px 20px 8px; }
        .rail .panel-title { font-size: 15px; }
        .list { list-style: none; margin: 14px 0 0; padding: 0; }
        .list li + li { border-top: 1px solid var(--line-soft); }
        .list a, .list .row {
            display: flex; align-items: center; gap: 12px;
            padding: 12px 6px; margin: 0 -6px;
            border-radius: 8px;
            transition: background .15s;
        }
        .list a:hover { background: #F8FAFC; }
        .list-main { min-width: 0; flex: 1; }
        .list-title { font-weight: 600; line-height: 1.3; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .list-sub   { font-size: 12px; color: var(--muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .list-empty { padding: 20px 0 16px; color: var(--muted); font-size: 13px; }

        /* Overlay mobile */
        .overlay {
            position: fixed; inset: 0; z-index: 50;
            background: rgba(13,34,49,.5);
            opacity: 0; pointer-events: none;
            transition: opacity .25s;
        }
        .overlay.show { opacity: 1; pointer-events: auto; }

        /* ---------- Responsive ---------- */
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); box-shadow: 0 0 40px rgba(0,0,0,.3); }
            .main { margin-left: 0; width: 100%; }
            #menuToggleBtn { display: inline-grid; }
            .topbar { padding: 12px 16px; }
            .content { padding: 20px 16px 32px; }
            .kpi { border-right: 0; border-bottom: 1px solid var(--line); }
            .kpi:last-child { border-bottom: 0; }
            .panel-head, .toolbar, .pager { padding-left: 16px; padding-right: 16px; }
            thead th:first-child, tbody td:first-child { padding-left: 16px; }
            thead th:last-child,  tbody td:last-child  { padding-right: 16px; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { transition: none !important; animation: none !important; }
        }
    </style>
</head>
<body>

<div class="shell">

    <?php $sidebar_page = 'dashboard'; $sidebar_base = ''; require_once __DIR__ . '/../includes/sidebar_admin.php'; ?>

    <!-- Contenu principal -->
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
                    <h1>Bonjour, <?php echo e($nom_affichage_session); ?></h1>
                    <p>
                        <?php echo e(ucfirst($date_du_jour)); ?> —
                        <?php echo ($user_role === 'admin_local')
                            ? 'effectifs de ' . e($nom_organisation_affichage)
                            : 'vue d’ensemble de toutes les directions'; ?>
                    </p>
                </div>
                <?php if ($user_role !== 'consultant'): ?>
                <a href="../saisie/ajouter_agent.php" class="btn btn-primary">
                    <i class="fa-solid fa-user-plus"></i> Nouveau fonctionnaire
                </a>
                <?php endif; ?>
            </div>

            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'success_mdp'): ?>
                <div class="alert" role="status">
                    <i class="fa-solid fa-circle-check"></i>
                    <span>Votre mot de passe a été mis à jour.</span>
                    <button type="button" class="close" aria-label="Fermer"><i class="fa-solid fa-xmark"></i></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
                <div class="alert" role="status">
                    <i class="fa-solid fa-circle-check"></i>
                    <span>L’opération a bien été enregistrée.</span>
                    <button type="button" class="close" aria-label="Fermer"><i class="fa-solid fa-xmark"></i></button>
                </div>
            <?php endif; ?>

            <!-- Indicateurs -->
            <section class="kpis" aria-label="Indicateurs clés">
                <div class="kpi">
                    <div class="kpi-icon i-ink"><i class="fa-solid fa-users"></i></div>
                    <div>
                        <div class="kpi-value"><?php echo number_format((int)$total_agents, 0, ',', ' '); ?></div>
                        <div class="kpi-label">Fonctionnaires</div>
                    </div>
                </div>
                <div class="kpi">
                    <div class="kpi-icon i-warm"><i class="fa-solid fa-sitemap"></i></div>
                    <div>
                        <div class="kpi-value"><?php echo number_format((int)$total_directions, 0, ',', ' '); ?></div>
                        <div class="kpi-label"><?php echo $user_role === 'admin_local' ? 'Direction' : 'Directions'; ?></div>
                    </div>
                </div>
                <div class="kpi">
                    <div class="kpi-icon i-green"><i class="fa-solid fa-graduation-cap"></i></div>
                    <div>
                        <div class="kpi-value"><?php echo number_format((int)$total_diplomes, 0, ',', ' '); ?></div>
                        <div class="kpi-label">Diplômes</div>
                    </div>
                </div>
                <?php if ($user_role === 'admin'): ?>
                <div class="kpi">
                    <div class="kpi-icon i-blue"><i class="fa-solid fa-user-shield"></i></div>
                    <div>
                        <div class="kpi-value"><?php echo number_format((int)$total_admins_locaux, 0, ',', ' '); ?></div>
                        <div class="kpi-label">Administrateurs locaux</div>
                    </div>
                </div>
                <?php endif; ?>
            </section>

            <div class="grid">

                <!-- Registre -->
                <section class="panel" aria-labelledby="registreTitre">
                    <div class="panel-head">
                        <h2 class="panel-title" id="registreTitre">Registre des fonctionnaires</h2>
                        <span class="panel-meta" id="resultCount"></span>
                    </div>

                    <div class="toolbar">
                        <div class="field">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="search" id="dashboardSearch" placeholder="Rechercher un nom, un matricule ou un grade" aria-label="Rechercher un fonctionnaire" autocomplete="off">
                        </div>
                        <?php if ($user_role === 'admin' && !empty($liste_organisations)): ?>
                        <div class="field select">
                            <i class="fa-solid fa-filter"></i>
                            <select id="dashOrgFilter" aria-label="Filtrer par organisation">
                                <option value="">Toutes les organisations</option>
                                <?php foreach ($liste_organisations as $org): ?>
                                    <option value="<?php echo e($org['nom_org']); ?>">
                                        <?php echo e(!empty($org['sigle_org']) ? "[{$org['sigle_org']}] " : ''); ?><?php echo e($org['nom_org']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Matricule</th>
                                    <th>Agent</th>
                                    <th>Organisation</th>
                                    <th>Grade</th>
                                    <th>Ville</th>
                                    <th style="text-align:right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="mainAgentTable">
                                <?php foreach ($agents as $f): ?>
                                <tr data-row data-searchable-item data-org="<?php echo e($f['nom_org'] ?? ''); ?>">
                                    <td><span class="matricule"><?php echo e($f['matri']); ?></span></td>
                                    <td>
                                        <div class="person">
                                            <div class="avatar sm light"><?php echo e(initiales($f['nom_fonc'], $f['prenom_fonc'])); ?></div>
                                            <div>
                                                <div class="person-name"><?php echo e(mb_strtoupper((string)$f['nom_fonc'], 'UTF-8')); ?></div>
                                                <div class="person-first"><?php echo e($f['prenom_fonc']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($f['nom_org'])): ?>
                                            <span class="org-name"><?php echo e($f['nom_org']); ?></span>
                                            <?php if (!empty($f['sigle_org'])): ?>
                                                <span class="org-sigle"><?php echo e($f['sigle_org']); ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="pill muted">Non affecté</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($f['gra_post'])): ?>
                                            <span class="pill"><?php echo e($f['gra_post']); ?></span>
                                        <?php else: ?>
                                            <span class="cell-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="<?php echo empty($f['vil_affec']) ? 'cell-muted' : ''; ?>">
                                        <?php echo e(!empty($f['vil_affec']) ? $f['vil_affec'] : '—'); ?>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <a href="profil_agent.php?id=<?php echo (int)$f['id_fonc']; ?>" class="act" title="Voir le profil" aria-label="Voir le profil">
                                                <i class="fa-solid fa-eye"></i>
                                            </a>
                                            <?php if ($user_role !== 'consultant'): ?>
                                                <a href="modifier_agent.php?id=<?php echo (int)$f['id_fonc']; ?>" class="act edit" title="Modifier" aria-label="Modifier">
                                                    <i class="fa-solid fa-pen"></i>
                                                </a>
                                                <a href="../saisie/muter_agent.php?id=<?php echo (int)$f['id_fonc']; ?>" class="act move" title="Muter" aria-label="Muter">
                                                    <i class="fa-solid fa-arrows-rotate"></i>
                                                </a>
                                                <a href="../supprimer_agent.php?id=<?php echo (int)$f['id_fonc']; ?>" class="act delete" title="Supprimer" aria-label="Supprimer"
                                                   onclick="return confirm('Supprimer définitivement cet agent ?');">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>

                                <?php if (empty($agents)): ?>
                                <tr>
                                    <td colspan="6" class="empty">
                                        <i class="fa-solid fa-users-slash"></i>
                                        <strong>Aucun fonctionnaire enregistré</strong>
                                        <?php if ($user_role !== 'consultant'): ?>
                                            <a href="../saisie/ajouter_agent.php">Ajouter le premier fonctionnaire</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endif; ?>

                                <tr id="noResultRow" style="display:none;">
                                    <td colspan="6" class="empty">
                                        <i class="fa-solid fa-magnifying-glass"></i>
                                        <strong>Aucun résultat</strong>
                                        Modifiez votre recherche ou retirez le filtre d’organisation.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="pager" id="dashPaginationContainer">
                        <div class="pager-info" id="dashPaginationInfo"></div>
                        <ul id="dashPagination"></ul>
                    </div>
                </section>

                <!-- Colonne latérale -->
                <aside class="rail">

                    <section class="panel" aria-labelledby="fluxTitre">
                        <h2 class="panel-title" id="fluxTitre">Dernières affectations</h2>
                        <?php if (!empty($flux_recents)): ?>
                        <ul class="list">
                            <?php foreach ($flux_recents as $fl): ?>
                            <li>
                                <a href="profil_agent.php?id=<?php echo (int)$fl['id_fonc']; ?>">
                                    <div class="avatar sm warm"><?php echo e(initiales($fl['nom_fonc'], $fl['prenom_fonc'])); ?></div>
                                    <div class="list-main">
                                        <div class="list-title"><?php echo e($fl['prenom_fonc'] . ' ' . mb_strtoupper((string)$fl['nom_fonc'], 'UTF-8')); ?></div>
                                        <div class="list-sub"><?php echo e($fl['gra_post'] ?? 'Grade non renseigné'); ?><?php echo !empty($fl['sigle_org']) ? ' · ' . e($fl['sigle_org']) : ''; ?></div>
                                    </div>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php else: ?>
                            <p class="list-empty">Aucune affectation récente.</p>
                        <?php endif; ?>
                    </section>

                    <?php if (!empty($lastAgents)): ?>
                    <section class="panel" aria-labelledby="derniersTitre">
                        <h2 class="panel-title" id="derniersTitre">Derniers agents ajoutés</h2>
                        <ul class="list">
                            <?php foreach ($lastAgents as $la): ?>
                            <li>
                                <a href="profil_agent.php?id=<?php echo (int)$la['id_fonc']; ?>">
                                    <div class="avatar sm light"><?php echo e(initiales($la['nom_fonc'], $la['prenom_fonc'])); ?></div>
                                    <div class="list-main">
                                        <div class="list-title"><?php echo e($la['prenom_fonc'] . ' ' . mb_strtoupper((string)$la['nom_fonc'], 'UTF-8')); ?></div>
                                        <div class="list-sub"><?php echo e($la['nom_org'] ?? 'Non affecté'); ?></div>
                                    </div>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                    <?php endif; ?>

                    <?php if ($user_role === 'admin' && !empty($liste_admins_locaux)): ?>
                    <section class="panel" aria-labelledby="adminsTitre">
                        <h2 class="panel-title" id="adminsTitre">Administrateurs locaux</h2>
                        <ul class="list">
                            <?php foreach ($liste_admins_locaux as $ad):
                                $prenom_admin_local = $ad['prenom_fonc'] ?: $ad['prenom_gest'];
                                $nom_admin_local = $ad['nom_fonc'] ?: $ad['nom_gest'];
                                $nom_complet = trim(($prenom_admin_local ?? '') . ' ' . ($nom_admin_local ?? ''));
                            ?>
                            <li>
                                <div class="row">
                                    <div class="avatar sm"><?php echo e(initiales($nom_admin_local ?: $ad['iden'], $prenom_admin_local ?? '')); ?></div>
                                    <div class="list-main">
                                        <div class="list-title"><?php echo e($nom_complet !== '' ? $nom_complet : $ad['iden']); ?></div>
                                        <div class="list-sub"><?php echo e($ad['nom_org'] ?? 'Sans organisation'); ?></div>
                                    </div>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                    <?php endif; ?>

                </aside>
            </div>

        </div>
    </main>
</div>

<script src="../public/js/app.js"></script>
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

    // Fermeture des messages
    document.querySelectorAll('.alert .close').forEach(btn => {
        btn.addEventListener('click', () => btn.closest('.alert').remove());
    });

    // Recherche, filtre et pagination
    (function () {
        const searchInput = document.getElementById('dashboardSearch');
        const orgFilter   = document.getElementById('dashOrgFilter');
        const tableBody   = document.getElementById('mainAgentTable');
        const pagContainer = document.getElementById('dashPaginationContainer');
        const pagUl       = document.getElementById('dashPagination');
        const pagInfo     = document.getElementById('dashPaginationInfo');
        const countLabel  = document.getElementById('resultCount');
        const noResultRow = document.getElementById('noResultRow');

        if (!tableBody) return;

        const allRows = Array.from(tableBody.querySelectorAll('tr[data-searchable-item]'));
        const rowsPerPage = 10;
        let currentPage = 1;
        let filteredRows = allRows;

        const plural = (n) => n + (n > 1 ? ' fonctionnaires' : ' fonctionnaire');

        function applyFilters() {
            const query = (searchInput ? searchInput.value : '').toLowerCase().trim();
            const selectedOrg = (orgFilter ? orgFilter.value : '').toLowerCase().trim();

            filteredRows = allRows.filter(row => {
                const text   = row.textContent.toLowerCase();
                const rowOrg = (row.getAttribute('data-org') || '').toLowerCase();
                return (!query || text.includes(query)) && (!selectedOrg || rowOrg === selectedOrg);
            });

            currentPage = 1;
            render();
        }

        function render() {
            const total = filteredRows.length;
            const totalPages = Math.max(1, Math.ceil(total / rowsPerPage));
            if (currentPage > totalPages) currentPage = totalPages;

            const start = (currentPage - 1) * rowsPerPage;
            const end   = start + rowsPerPage;

            allRows.forEach(row => row.style.display = 'none');
            filteredRows.slice(start, end).forEach(row => row.style.display = '');

            if (noResultRow) noResultRow.style.display = (allRows.length > 0 && total === 0) ? '' : 'none';
            if (countLabel && allRows.length > 0) countLabel.textContent = plural(total);

            if (total > rowsPerPage) {
                pagContainer.style.display = 'flex';
                pagInfo.textContent = `${start + 1} à ${Math.min(end, total)} sur ${total}`;

                let html = `<li><button type="button" class="page-btn ${currentPage <= 1 ? 'disabled' : ''}" data-goto="${currentPage - 1}" aria-label="Page précédente"><i class="fa-solid fa-chevron-left"></i></button></li>`;
                for (let p = 1; p <= totalPages; p++) {
                    html += `<li><button type="button" class="page-btn ${p === currentPage ? 'active' : ''}" data-goto="${p}" ${p === currentPage ? 'aria-current="page"' : ''}>${p}</button></li>`;
                }
                html += `<li><button type="button" class="page-btn ${currentPage >= totalPages ? 'disabled' : ''}" data-goto="${currentPage + 1}" aria-label="Page suivante"><i class="fa-solid fa-chevron-right"></i></button></li>`;
                pagUl.innerHTML = html;

                pagUl.querySelectorAll('button[data-goto]').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        e.preventDefault();
                        const target = parseInt(btn.getAttribute('data-goto'), 10);
                        if (target >= 1 && target <= totalPages) {
                            currentPage = target;
                            render();
                        }
                    });
                });
            } else {
                pagContainer.style.display = 'none';
            }
        }

        if (searchInput) searchInput.addEventListener('input', applyFilters);
        if (orgFilter)   orgFilter.addEventListener('change', applyFilters);

        applyFilters();
    })();
</script>
</body>
</html>
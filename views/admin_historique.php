<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config.php';

// Vérification de la session et du rôle
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header("Location: ../saisie/login.php");
    exit();
}

$user_role = $_SESSION['rol'];
$nom_session = $_SESSION['nom'] ?? 'Admin';
$libelle_role = 'Administrateur central';
$stmtNotif = $db->prepare("SELECT COUNT(*) FROM NOTIFICATIONS WHERE id_user = ? AND statut = 'a_verifier'");
$stmtNotif->execute([(int)($_SESSION['id_user'] ?? 0)]);
$notifications_a_verifier = (int)$stmtNotif->fetchColumn();

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

// Récupération des 100 derniers logs avec jointure
$stmt = $db->query("SELECT l.*, u.iden, u.rol,
        f.nom_fonc, f.prenom_fonc,
        g.nom_gest, g.prenom_gest
                    FROM HISTORIQUE l
                    LEFT JOIN UTILISATEURS u ON l.id_user = u.id_user
                    LEFT JOIN FONCTIONNAIRE f ON u.id_fonc = f.id_fonc
                    LEFT JOIN GESTIONNAIRE g ON g.id_user = u.id_user
                    ORDER BY l.dat_hist DESC LIMIT 100");
$rows = $stmt->fetchAll();

// Statistiques
$total_logs = $db->query("SELECT COUNT(*) FROM HISTORIQUE")->fetchColumn();
$total_actions_today = $db->query("SELECT COUNT(*) FROM HISTORIQUE WHERE DATE(dat_hist) = CURDATE()")->fetchColumn();

// Top 5 utilisateurs les plus actifs
$top_users = $db->query("
    SELECT u.iden, f.nom_fonc, f.prenom_fonc, g.nom_gest, g.prenom_gest,
           COUNT(*) AS action_count
    FROM HISTORIQUE l
    LEFT JOIN UTILISATEURS u ON l.id_user = u.id_user
    LEFT JOIN FONCTIONNAIRE f ON u.id_fonc = f.id_fonc
    LEFT JOIN GESTIONNAIRE g ON g.id_user = u.id_user
    GROUP BY l.id_user, u.iden, f.nom_fonc, f.prenom_fonc, g.nom_gest, g.prenom_gest
    ORDER BY action_count DESC
    LIMIT 5
")->fetchAll();
$top_max = 1;
foreach ($top_users as &$tu) {
    $prenomTop = $tu['prenom_fonc'] ?: ($tu['prenom_gest'] ?? '');
    $nomTop = $tu['nom_fonc'] ?: ($tu['nom_gest'] ?? '');
    $tu['nom_affiche'] = trim($prenomTop . ' ' . $nomTop) ?: ($tu['iden'] ?? 'Système');
    $top_max = max($top_max, (int)$tu['action_count']);
}
unset($tu);

// Préparation des logs : type d'action, rôle, date
$types_meta = [
    'connexion'    => ['label' => 'Connexions',    'icon' => 'fa-right-to-bracket'],
    'creation'     => ['label' => 'Créations',     'icon' => 'fa-plus'],
    'modification' => ['label' => 'Modifications', 'icon' => 'fa-pen'],
    'suppression'  => ['label' => 'Suppressions',  'icon' => 'fa-trash-can'],
    'validation'   => ['label' => 'Validations',   'icon' => 'fa-circle-check'],
    'mutation'     => ['label' => 'Mutations',     'icon' => 'fa-arrows-rotate'],
    'autre'        => ['label' => 'Autres',        'icon' => 'fa-circle-info'],
];
$roles_meta = [
    'admin'       => ['label' => 'Admin central', 'class' => 'r-admin'],
    'admin_local' => ['label' => 'Admin local',   'class' => 'r-local'],
    'AGENT'       => ['label' => 'Fonctionnaire', 'class' => 'r-agent'],
    'agent'       => ['label' => 'Fonctionnaire', 'class' => 'r-agent'],
    'consultant'  => ['label' => 'Consultant',    'class' => 'r-consultant'],
];
$compteurs = array_fill_keys(array_keys($types_meta), 0);

$logs = [];
foreach ($rows as $row) {
    $raw_desc   = $row['action_desc'] ?? $row['description'] ?? '';
    $desc_lower = mb_strtolower($raw_desc, 'UTF-8');

    $type = 'autre';
    if (mb_strpos($desc_lower, 'connexi') !== false || mb_strpos($desc_lower, 'connect') !== false) {
        $type = 'connexion';
    } elseif (mb_strpos($desc_lower, 'suppr') !== false) {
        $type = 'suppression';
    } elseif (mb_strpos($desc_lower, 'valid') !== false || mb_strpos($desc_lower, 'vérifi') !== false || mb_strpos($desc_lower, 'verifi') !== false) {
        $type = 'validation';
    } elseif (mb_strpos($desc_lower, 'modif') !== false || mb_strpos($desc_lower, 'mise a jour') !== false || mb_strpos($desc_lower, 'mise à jour') !== false) {
        $type = 'modification';
    } elseif (mb_strpos($desc_lower, 'crea') !== false || mb_strpos($desc_lower, 'créa') !== false || mb_strpos($desc_lower, 'ajout') !== false) {
        $type = 'creation';
    } elseif (mb_strpos($desc_lower, 'mutat') !== false || mb_strpos($desc_lower, 'muter') !== false) {
        $type = 'mutation';
    }
    $compteurs[$type]++;

    $prenomUtilisateur = $row['prenom_fonc'] ?: ($row['prenom_gest'] ?? '');
    $nomUtilisateur = $row['nom_fonc'] ?: ($row['nom_gest'] ?? '');
    $qui = trim($prenomUtilisateur . ' ' . $nomUtilisateur);
    if ($qui === '') $qui = $row['iden'] ?? 'Système';

    $ts = strtotime($row['dat_hist'] ?? $row['dat_log'] ?? 'now') ?: time();

    $desc = e($raw_desc !== '' ? $raw_desc : 'Action inconnue');
    $desc = preg_replace('/\(Matricule:\s*([^)]+)\)/i', '<span class="tag-matri">Matricule : $1</span>', $desc);

    $logs[] = [
        'type'  => $type,
        'qui'   => $qui,
        'role'  => $roles_meta[$row['rol'] ?? ''] ?? ['label' => 'Système', 'class' => 'r-auto'],
        'jour'  => date('d/m/Y', $ts),
        'heure' => date('H:i:s', $ts),
        'desc'  => $desc,
    ];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique des actions | Ministère des Affaires étrangères</title>
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
            flex: none; width: 36px; height: 36px; border-radius: 50%;
            display: grid; place-items: center;
            font-size: 12px; font-weight: 700; letter-spacing: .3px;
            background: var(--ink-2); color: #fff;
        }
        .avatar.light { background: var(--green-soft); color: var(--green); }
        .avatar.sm { width: 32px; height: 32px; font-size: 11px; }

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
        .page-head p { margin: 8px 0 0; color: rgba(255,255,255,.78); font-size: 14px; max-width: 60ch; }

        .btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 10px 16px; border-radius: 9px; border: 1px solid transparent;
            font-weight: 600; font-size: 13px; cursor: pointer;
            transition: background .15s, border-color .15s, transform .1s;
        }
        .btn:active { transform: translateY(1px); }
        .btn-light { background: rgba(255,255,255,.14); border-color: rgba(255,255,255,.25); color: #fff; }
        .btn-light:hover { background: rgba(255,255,255,.22); }

        /* ---------- KPI ---------- */
        .kpis {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            background: var(--surface); border: 1px solid var(--line);
            border-radius: 14px; margin-bottom: 24px; overflow: hidden;
        }
        .kpi { display: flex; align-items: center; gap: 16px; padding: 22px 24px; border-right: 1px solid var(--line); }
        .kpi:last-child { border-right: 0; }
        .kpi:nth-child(1) { background: linear-gradient(135deg, #E6EEFF, #FFFFFF 75%); }
        .kpi:nth-child(2) { background: linear-gradient(135deg, #E6F6EE, #FFFFFF 75%); }
        .kpi:nth-child(3) { background: linear-gradient(135deg, #FFF0DC, #FFFFFF 75%); }
        .kpi-icon { width: 44px; height: 44px; flex: none; display: grid; place-items: center; border-radius: 12px; font-size: 17px; color: #fff; box-shadow: 0 6px 14px rgba(0,0,0,.12); }
        .kpi-icon.i-blue  { background: var(--blue); }
        .kpi-icon.i-green { background: var(--green); }
        .kpi-icon.i-warm  { background: var(--accent); }
        .kpi-value { font-family: var(--font-display); font-size: 38px; line-height: 1; letter-spacing: -.01em; }
        .kpi-label { color: var(--muted); font-size: 13px; font-weight: 500; margin-top: 4px; }

        /* ---------- Grille ---------- */
        .grid { display: grid; grid-template-columns: minmax(0, 1fr); gap: 24px; align-items: start; }
        @media (min-width: 1320px) { .grid { grid-template-columns: minmax(0, 1fr) 320px; } }

        .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; }
        .panel-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 20px 24px 0; }
        .panel-title { margin: 0; font-size: 16px; font-weight: 700; }
        .panel-meta { color: var(--muted); font-size: 13px; }

        .toolbar { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; padding: 16px 24px 14px; }
        .field { position: relative; flex: 1 1 280px; min-width: 0; }
        .field > i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 13px; pointer-events: none; }
        .field input {
            width: 100%; height: 40px; padding: 0 14px 0 38px;
            background: var(--paper); border: 1px solid transparent; border-radius: 9px;
            transition: border-color .15s, background .15s;
        }
        .field input::placeholder { color: #8A95A3; }
        .field input:hover { border-color: var(--line); }
        .field input:focus { outline: none; background: var(--surface); border-color: var(--green); box-shadow: 0 0 0 3px rgba(14,159,104,.15); }

        .switch {
            display: inline-flex; align-items: center; gap: 10px;
            height: 40px; padding: 0 14px;
            background: var(--paper); border: 1px solid transparent; border-radius: 9px;
            cursor: pointer; font-weight: 600; font-size: 13px; color: var(--muted);
            transition: color .15s, background .15s;
        }
        .switch:hover { color: var(--text); }
        .switch .dot { width: 8px; height: 8px; border-radius: 50%; background: #B5BEC9; }
        .switch.on { color: var(--green); background: var(--green-soft); }
        .switch.on .dot { background: var(--green); box-shadow: 0 0 0 3px rgba(14,159,104,.2); }

        .chips { display: flex; gap: 8px; flex-wrap: wrap; padding: 0 24px 18px; }
        .chip {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 6px 12px; border-radius: 999px;
            border: 1px solid var(--line); background: var(--surface);
            font-weight: 600; font-size: 13px; color: var(--muted); cursor: pointer;
            transition: background .15s, color .15s, border-color .15s;
        }
        .chip:hover { color: var(--text); border-color: #CBD2DC; }
        .chip .n { font-size: 12px; font-weight: 700; padding: 1px 7px; border-radius: 999px; background: var(--paper); color: var(--muted); }
        .chip.active { background: var(--ink); border-color: var(--ink); color: #fff; }
        .chip.active .n { background: rgba(255,255,255,.18); color: #fff; }

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

        .when { white-space: nowrap; }
        .when .d { font-weight: 600; }
        .when .t { color: var(--muted); font-size: 12px; font-variant-numeric: tabular-nums; }

        .who { display: flex; align-items: center; gap: 10px; min-width: 160px; font-weight: 600; }

        .pill { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap; }
        .r-admin     { background: var(--red-soft);    color: #9B2C23; }
        .r-local     { background: var(--green-soft);  color: #0B7A51; }
        .r-agent     { background: var(--blue-soft);   color: #1D4FBF; }
        .r-consultant{ background: var(--blue-soft);   color: #1D4FBF; }
        .r-auto      { background: var(--paper);       color: var(--muted); }

        .act-cell { display: flex; align-items: center; gap: 12px; }
        .act-ico { width: 32px; height: 32px; flex: none; display: grid; place-items: center; border-radius: 9px; font-size: 13px; }
        .t-connexion    { background: var(--green-soft);  color: var(--green); }
        .t-creation     { background: var(--blue-soft);   color: var(--blue); }
        .t-modification { background: var(--amber-soft);  color: var(--amber); }
        .t-suppression  { background: var(--red-soft);    color: var(--red); }
        .t-validation   { background: var(--green-soft);  color: var(--green); }
        .t-mutation     { background: var(--violet-soft); color: var(--violet); }
        .t-autre        { background: var(--paper);       color: var(--muted); }
        .act-text { min-width: 220px; }
        .tag-matri {
            display: inline-block; margin-left: 4px; padding: 1px 8px;
            background: var(--paper); border: 1px solid var(--line); border-radius: 6px;
            font-size: 12px; font-weight: 700; font-variant-numeric: tabular-nums; white-space: nowrap;
        }

        .empty { text-align: center; padding: 48px 24px !important; color: var(--muted); }
        .empty i { display: block; font-size: 28px; margin-bottom: 12px; color: #B5BEC9; }
        .empty strong { display: block; color: var(--text); margin-bottom: 4px; }

        .pager { display: none; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 14px 24px; border-top: 1px solid var(--line); }
        .pager-info { color: var(--muted); font-size: 13px; }
        .pager ul { display: flex; gap: 4px; list-style: none; margin: 0; padding: 0; flex-wrap: wrap; }
        .page-btn { min-width: 34px; height: 34px; padding: 0 8px; border: 1px solid transparent; background: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 13px; color: var(--muted); transition: background .15s, color .15s; }
        .page-btn:hover { background: var(--paper); color: var(--text); }
        .page-btn.active { background: var(--accent); color: #fff; }
        .page-btn.disabled { opacity: .35; pointer-events: none; }

        /* ---------- Colonne latérale ---------- */
        .rail { display: grid; gap: 24px; }
        .rail .panel { padding: 20px 20px 10px; }
        .rail .panel-title { font-size: 15px; }
        .rank { list-style: none; margin: 14px 0 0; padding: 0; }
        .rank li { padding: 12px 0; }
        .rank li + li { border-top: 1px solid var(--line-soft); }
        .rank-top { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
        .rank-pos { width: 24px; height: 24px; flex: none; display: grid; place-items: center; border-radius: 7px; background: var(--paper); font-weight: 700; font-size: 12px; color: var(--muted); }
        li:first-child .rank-pos { background: var(--accent); color: #fff; }
        .rank-name { flex: 1; min-width: 0; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .rank-count { font-weight: 700; font-size: 13px; font-variant-numeric: tabular-nums; }
        .bar { height: 6px; border-radius: 999px; background: var(--paper); overflow: hidden; }
        .bar > span { display: block; height: 100%; border-radius: 999px; background: linear-gradient(90deg, var(--green), #34C48E); }
        li:first-child .bar > span { background: linear-gradient(90deg, var(--accent), #FFA94D); }
        .list-empty { padding: 20px 0 16px; color: var(--muted); font-size: 13px; }

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
            .panel-head, .toolbar, .chips, .pager { padding-left: 16px; padding-right: 16px; }
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

    <?php $sidebar_page = 'admin_historique'; $sidebar_base = ''; require_once __DIR__ . '/../includes/sidebar_admin.php'; ?>

    <main class="main">

        <header class="topbar">
            <div class="topbar-left">
                <button type="button" id="menuToggleBtn" class="icon-btn" aria-label="Ouvrir le menu" aria-controls="sidebarAdmin">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="org-chip">
                    <i class="fa-solid fa-building-shield"></i>
                    <span>Administration centrale</span>
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
                    <h1>Historique des actions</h1>
                    <p>Toutes les opérations effectuées sur la plateforme : connexions, créations, modifications, suppressions et mutations.</p>
                </div>
                <a href="admin_dashboard.php" class="btn btn-light">
                    <i class="fa-solid fa-arrow-left"></i> Retour au tableau de bord
                </a>
            </div>

            <!-- Indicateurs -->
            <section class="kpis" aria-label="Indicateurs clés">
                <div class="kpi">
                    <div class="kpi-icon i-blue"><i class="fa-solid fa-database"></i></div>
                    <div>
                        <div class="kpi-value"><?php echo number_format((int)$total_logs, 0, ',', ' '); ?></div>
                        <div class="kpi-label">Actions enregistrées</div>
                    </div>
                </div>
                <div class="kpi">
                    <div class="kpi-icon i-green"><i class="fa-solid fa-calendar-day"></i></div>
                    <div>
                        <div class="kpi-value"><?php echo number_format((int)$total_actions_today, 0, ',', ' '); ?></div>
                        <div class="kpi-label">Actions aujourd’hui</div>
                    </div>
                </div>
                <div class="kpi">
                    <div class="kpi-icon i-warm"><i class="fa-solid fa-list-check"></i></div>
                    <div>
                        <div class="kpi-value"><?php echo number_format(count($logs), 0, ',', ' '); ?></div>
                        <div class="kpi-label">Dernières actions affichées</div>
                    </div>
                </div>
            </section>

            <div class="grid">

                <section class="panel" aria-labelledby="journalTitre">
                    <div class="panel-head">
                        <h2 class="panel-title" id="journalTitre">Journal</h2>
                        <span class="panel-meta" id="resultCount"></span>
                    </div>

                    <div class="toolbar">
                        <div class="field">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="search" id="logSearch" placeholder="Rechercher un utilisateur, une action, un matricule ou une date" aria-label="Rechercher dans le journal" autocomplete="off">
                        </div>
                        <button type="button" class="switch" id="refreshSwitch" aria-pressed="false" title="Recharger la page toutes les 30 secondes">
                            <span class="dot"></span> Actualisation auto
                        </button>
                    </div>

                    <div class="chips" role="group" aria-label="Filtrer par type d’action">
                        <button type="button" class="chip active" data-filter="all">Tout <span class="n"><?php echo count($logs); ?></span></button>
                        <?php foreach ($types_meta as $key => $meta): ?>
                            <?php if ($compteurs[$key] > 0): ?>
                            <button type="button" class="chip" data-filter="<?php echo e($key); ?>">
                                <?php echo e($meta['label']); ?> <span class="n"><?php echo (int)$compteurs[$key]; ?></span>
                            </button>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>

                    <div class="table-wrap">
                        <table id="logTable">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Utilisateur</th>
                                    <th>Rôle</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="logBody">
                                <?php foreach ($logs as $lg): ?>
                                <tr data-row data-type="<?php echo e($lg['type']); ?>">
                                    <td class="when">
                                        <div class="d"><?php echo e($lg['jour']); ?></div>
                                        <div class="t"><?php echo e($lg['heure']); ?></div>
                                    </td>
                                    <td>
                                        <div class="who">
                                            <div class="avatar sm light"><?php echo e(initiales($lg['qui'])); ?></div>
                                            <span><?php echo e($lg['qui']); ?></span>
                                        </div>
                                    </td>
                                    <td><span class="pill <?php echo e($lg['role']['class']); ?>"><?php echo e($lg['role']['label']); ?></span></td>
                                    <td>
                                        <div class="act-cell">
                                            <span class="act-ico t-<?php echo e($lg['type']); ?>" title="<?php echo e($types_meta[$lg['type']]['label']); ?>">
                                                <i class="fa-solid <?php echo e($types_meta[$lg['type']]['icon']); ?>"></i>
                                            </span>
                                            <span class="act-text"><?php echo $lg['desc']; /* déjà échappé plus haut */ ?></span>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>

                                <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="4" class="empty">
                                        <i class="fa-solid fa-database"></i>
                                        <strong>Aucune action enregistrée</strong>
                                        Les opérations apparaîtront ici dès qu’elles auront lieu.
                                    </td>
                                </tr>
                                <?php endif; ?>

                                <tr id="noResultRow" style="display:none;">
                                    <td colspan="4" class="empty">
                                        <i class="fa-solid fa-magnifying-glass"></i>
                                        <strong>Aucun résultat</strong>
                                        Modifiez votre recherche ou choisissez un autre type d’action.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="pager" id="pagerBox">
                        <div class="pager-info" id="pagerInfo"></div>
                        <ul id="pagerList"></ul>
                    </div>
                </section>

                <aside class="rail">
                    <section class="panel" aria-labelledby="topTitre">
                        <h2 class="panel-title" id="topTitre">Utilisateurs les plus actifs</h2>
                        <?php if (!empty($top_users)): ?>
                        <ol class="rank">
                            <?php $pos = 1; foreach ($top_users as $tu): ?>
                            <li>
                                <div class="rank-top">
                                    <span class="rank-pos"><?php echo $pos++; ?></span>
                                    <span class="rank-name"><?php echo e($tu['nom_affiche']); ?></span>
                                    <span class="rank-count"><?php echo (int)$tu['action_count']; ?></span>
                                </div>
                                <div class="bar" aria-hidden="true"><span style="width: <?php echo round(((int)$tu['action_count'] / $top_max) * 100); ?>%;"></span></div>
                            </li>
                            <?php endforeach; ?>
                        </ol>
                        <?php else: ?>
                            <p class="list-empty">Pas encore d’activité.</p>
                        <?php endif; ?>
                    </section>
                </aside>
            </div>

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

    // Recherche + filtre par type + pagination
    (function () {
        const body   = document.getElementById('logBody');
        const search = document.getElementById('logSearch');
        const chips  = document.querySelectorAll('.chip');
        const pager  = document.getElementById('pagerBox');
        const pList  = document.getElementById('pagerList');
        const pInfo  = document.getElementById('pagerInfo');
        const count  = document.getElementById('resultCount');
        const noRes  = document.getElementById('noResultRow');
        if (!body) return;

        const allRows = Array.from(body.querySelectorAll('tr[data-row]'));
        const perPage = 15;
        let page = 1, type = 'all', filtered = allRows;

        function apply() {
            const q = (search.value || '').toLowerCase().trim();
            filtered = allRows.filter(r =>
                (type === 'all' || r.dataset.type === type) &&
                (!q || r.textContent.toLowerCase().includes(q))
            );
            page = 1;
            render();
        }

        function render() {
            const total = filtered.length;
            const pages = Math.max(1, Math.ceil(total / perPage));
            if (page > pages) page = pages;
            const start = (page - 1) * perPage, end = start + perPage;

            allRows.forEach(r => r.style.display = 'none');
            filtered.slice(start, end).forEach(r => r.style.display = '');
            noRes.style.display = (allRows.length > 0 && total === 0) ? '' : 'none';
            if (allRows.length > 0) count.textContent = total + (total > 1 ? ' actions' : ' action');

            if (total > perPage) {
                pager.style.display = 'flex';
                pInfo.textContent = (start + 1) + ' à ' + Math.min(end, total) + ' sur ' + total;
                let h = `<li><button type="button" class="page-btn ${page <= 1 ? 'disabled' : ''}" data-goto="${page - 1}" aria-label="Page précédente"><i class="fa-solid fa-chevron-left"></i></button></li>`;
                for (let p = 1; p <= pages; p++) {
                    h += `<li><button type="button" class="page-btn ${p === page ? 'active' : ''}" data-goto="${p}" ${p === page ? 'aria-current="page"' : ''}>${p}</button></li>`;
                }
                h += `<li><button type="button" class="page-btn ${page >= pages ? 'disabled' : ''}" data-goto="${page + 1}" aria-label="Page suivante"><i class="fa-solid fa-chevron-right"></i></button></li>`;
                pList.innerHTML = h;
                pList.querySelectorAll('button[data-goto]').forEach(b => b.addEventListener('click', () => {
                    const t = parseInt(b.dataset.goto, 10);
                    if (t >= 1 && t <= pages) { page = t; render(); }
                }));
            } else {
                pager.style.display = 'none';
            }
        }

        search.addEventListener('input', apply);
        chips.forEach(c => c.addEventListener('click', () => {
            chips.forEach(x => x.classList.remove('active'));
            c.classList.add('active');
            type = c.dataset.filter;
            apply();
        }));
        apply();
    })();

    // Actualisation automatique (désactivée par défaut pour ne pas perdre la recherche en cours)
    (function () {
        const sw = document.getElementById('refreshSwitch');
        let timer = null;
        sw.addEventListener('click', () => {
            const on = sw.getAttribute('aria-pressed') !== 'true';
            sw.setAttribute('aria-pressed', on ? 'true' : 'false');
            sw.classList.toggle('on', on);
            if (on) { timer = setInterval(() => location.reload(), 30000); }
            else { clearInterval(timer); }
        });
    })();
</script>
</body>
</html>
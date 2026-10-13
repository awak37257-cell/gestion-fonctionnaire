<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config.php';

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
// Les archives de tous les agents sont réservées au personnel habilité (un simple agent n'y a pas accès)
$role_archives = strtolower(trim($user_role));
if (!in_array($role_archives, ['admin', 'admin_local', 'gestionnaire', 'consultant'], true)) {
    $id_fonc_session = isset($_SESSION['id_fonc']) ? (int)$_SESSION['id_fonc'] : 0;
    header("Location: " . ($id_fonc_session ? "profil_agent.php?id=" . $id_fonc_session : "../saisie/login.php"));
    exit();
}
$peut_modifier = in_array($role_archives, ['admin', 'admin_local', 'gestionnaire'], true);

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
    'consultant'  => 'Consultant',
];
$libelle_role = $libelles_roles[$user_role] ?? ucfirst($user_role);

// Nom de l'organisation affichée dans la barre du haut
$nom_organisation_affichage = "Administration centrale";
if ($user_role === 'admin_local' && $user_org_id) {
    $stmtOrg = $db->prepare("SELECT nom_org FROM ORGANISATION WHERE id_org = ?");
    $stmtOrg->execute([$user_org_id]);
    $resOrg = $stmtOrg->fetch();
    if ($resOrg) $nom_organisation_affichage = $resOrg['nom_org'];
}

$agents = $db->query("
    SELECT f.id_fonc, f.nom_fonc, f.prenom_fonc, f.matri, r.id_affec
    FROM FONCTIONNAIRE f
    LEFT JOIN RECEVOIR r ON f.id_fonc = r.id_fonc
    GROUP BY f.id_fonc
    ORDER BY f.nom_fonc ASC
")->fetchAll();

$sqlDocs = "SELECT d.id_doc, d.lib_doc, d.date_enre, d.fich_url, f.nom_fonc, f.prenom_fonc, f.matri, a.tit_post, a.vil_affec
FROM DOCUMENT d
JOIN FONCTIONNAIRE f ON d.id_fonc = f.id_fonc
LEFT JOIN RECEVOIR r ON f.id_fonc = r.id_fonc
LEFT JOIN AFFECTATION a ON r.id_affec = a.id_affec
GROUP BY d.id_doc
ORDER BY d.date_enre DESC";
$documents = $db->query($sqlDocs)->fetchAll();

// Statistiques
$total_docs   = count($documents);
$total_agents = count($agents);

// Icône selon le type de fichier
function icone_doc($url) {
    $ext = strtolower(pathinfo(parse_url((string)$url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
    if ($ext === 'pdf') return ['fa-file-pdf', 'f-pdf'];
    if (in_array($ext, ['jpg', 'jpeg', 'png'], true)) return ['fa-file-image', 'f-img'];
    return ['fa-file', 'f-other'];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archive des documents | Ministère des Affaires étrangères</title>
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
        .page-head p { margin: 8px 0 0; color: rgba(255,255,255,.78); font-size: 14px; max-width: 60ch; }

        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            padding: 10px 16px; border-radius: 9px; border: 1px solid transparent;
            font-weight: 600; font-size: 13px; cursor: pointer;
            transition: background .15s, border-color .15s, transform .1s;
        }
        .btn:active { transform: translateY(1px); }
        .btn-light { background: rgba(255,255,255,.14); border-color: rgba(255,255,255,.25); color: #fff; }
        .btn-light:hover { background: rgba(255,255,255,.22); }
        .btn-primary { background: var(--accent); color: #fff; box-shadow: 0 6px 16px rgba(247,127,0,.3); }
        .btn-primary:hover { background: #FF8F1F; }
        .btn-block { width: 100%; padding: 12px 16px; font-size: 14px; }
        .btn[disabled] { opacity: .75; pointer-events: none; }
        .btn .spin { display: none; width: 16px; height: 16px; border: 2px solid rgba(255,255,255,.4); border-top-color: #fff; border-radius: 50%; animation: spin .7s linear infinite; }
        .btn.loading .spin { display: inline-block; }
        .btn.loading .ico { display: none; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ---------- Alertes ---------- */
        .alert {
            display: flex; align-items: center; gap: 12px;
            padding: 12px 16px; margin-bottom: 20px;
            border-radius: 10px; font-weight: 500;
            background: var(--green-soft); color: #14633F; border: 1px solid #BFE3D0;
        }
        .alert.error { background: var(--red-soft); color: #8E2A22; border-color: #F0C4BF; }
        .alert .close { margin-left: auto; background: none; border: 0; cursor: pointer; color: inherit; opacity: .6; }
        .alert .close:hover { opacity: 1; }

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
        .kpi-icon { width: 44px; height: 44px; flex: none; display: grid; place-items: center; border-radius: 12px; font-size: 17px; color: #fff; box-shadow: 0 6px 14px rgba(0,0,0,.12); }
        .kpi-icon.i-green { background: var(--green); }
        .kpi-icon.i-warm  { background: var(--accent); }
        .kpi-value { font-family: var(--font-display); font-size: 38px; line-height: 1; letter-spacing: -.01em; }
        .kpi-label { color: var(--muted); font-size: 13px; font-weight: 500; margin-top: 4px; }

        /* ---------- Grille ---------- */
        .grid { display: grid; grid-template-columns: minmax(0, 1fr); gap: 24px; align-items: start; }
        .grid .rail { order: -1; }
        @media (min-width: 1200px) {
            .grid.with-rail { grid-template-columns: minmax(0, 1fr) 380px; }
            .grid .rail { order: 0; position: sticky; top: 80px; }
        }

        .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; }
        .panel-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 20px 24px 0; }
        .panel-title { margin: 0; font-size: 16px; font-weight: 700; }
        .panel-sub { margin: 4px 0 0; color: var(--muted); font-size: 13px; }
        .panel-meta { color: var(--muted); font-size: 13px; }
        .panel-body { padding: 18px 24px 24px; }

        .toolbar { display: flex; gap: 12px; flex-wrap: wrap; padding: 16px 24px 18px; }
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

        /* ---------- Formulaire ---------- */
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; }
        .control {
            width: 100%; height: 42px; padding: 0 14px;
            background: var(--surface); border: 1px solid #D5DBE3; border-radius: 9px;
            transition: border-color .15s, box-shadow .15s;
        }
        select.control { appearance: none; cursor: pointer; padding-right: 36px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='none' stroke='%236A7686' stroke-width='1.6' stroke-linecap='round' d='M2.5 4.5 6 8l3.5-3.5'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 14px center; }
        .control:hover { border-color: #B9C2CE; }
        .control:focus { outline: none; border-color: var(--green); box-shadow: 0 0 0 3px rgba(14,159,104,.15); }
        .control.ok  { border-color: var(--green); }
        .control.bad { border-color: var(--red); }
        .hint { display: block; min-height: 18px; margin-top: 6px; font-size: 12px; font-weight: 500; color: var(--muted); }
        .hint.ok  { color: var(--green); }
        .hint.bad { color: var(--red); }

        .dropzone {
            position: relative; display: block; text-align: center;
            padding: 26px 16px;
            border: 1.5px dashed #BAC4D0; border-radius: 12px;
            background: var(--paper); cursor: pointer;
            transition: border-color .15s, background .15s;
        }
        .dropzone:hover, .dropzone.drag { border-color: var(--accent); background: var(--accent-soft); }
        .dropzone.ok  { border-color: var(--green); background: #F2FBF7; border-style: solid; }
        .dropzone.bad { border-color: var(--red); background: var(--red-soft); }
        .dropzone .dz-ico { width: 44px; height: 44px; margin: 0 auto 10px; display: grid; place-items: center; border-radius: 12px; background: var(--surface); color: var(--accent); font-size: 18px; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
        .dropzone.ok .dz-ico { color: var(--green); }
        .dropzone .dz-title { font-weight: 600; word-break: break-word; }
        .dropzone .dz-sub { color: var(--muted); font-size: 12px; margin-top: 2px; }
        .dropzone input { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }

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

        .person { display: flex; align-items: center; gap: 12px; min-width: 200px; }
        .person-name { font-weight: 700; line-height: 1.25; }
        .person-first { color: var(--muted); font-weight: 500; }
        .person-matri { display: block; font-size: 12px; color: var(--muted); font-variant-numeric: tabular-nums; }

        .doc-link { display: inline-flex; align-items: center; gap: 12px; font-weight: 600; min-width: 180px; transition: color .15s; }
        .doc-link:hover { color: var(--accent-dark); }
        .file-ico { width: 34px; height: 34px; flex: none; display: grid; place-items: center; border-radius: 9px; font-size: 15px; }
        .f-pdf   { background: var(--red-soft);  color: var(--red); }
        .f-img   { background: var(--blue-soft); color: var(--blue); }
        .f-other { background: var(--paper);     color: var(--muted); }

        .when { white-space: nowrap; }
        .when .d { font-weight: 600; }
        .when .t { color: var(--muted); font-size: 12px; font-variant-numeric: tabular-nums; }

        .actions { display: flex; justify-content: flex-end; gap: 6px; }
        .act { width: 32px; height: 32px; display: inline-grid; place-items: center; border-radius: 8px; color: var(--muted); font-size: 13px; transition: background .15s, color .15s; }
        .act:hover { background: var(--paper); color: var(--text); }
        .act.edit:hover   { background: var(--blue-soft); color: var(--blue); }
        .act.delete:hover { background: var(--red-soft);  color: var(--red); }

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

        /* ---------- Toast ---------- */
        .toast {
            position: fixed; top: 20px; right: 20px; z-index: 100;
            display: flex; align-items: center; gap: 10px;
            max-width: min(360px, calc(100vw - 40px));
            padding: 12px 16px; border-radius: 10px;
            background: var(--ink); color: #fff; font-weight: 500;
            box-shadow: 0 12px 30px rgba(0,0,0,.2);
            animation: toast-in .25s ease-out;
        }
        .toast.error i { color: #FF8B80; }
        .toast.success i { color: #5EE0A8; }
        @keyframes toast-in { from { transform: translateY(-8px); opacity: 0; } to { transform: none; opacity: 1; } }

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
            .panel-head, .toolbar, .pager { padding-left: 16px; padding-right: 16px; }
            .panel-body { padding: 16px; }
            thead th:first-child, tbody td:first-child { padding-left: 16px; }
            thead th:last-child,  tbody td:last-child  { padding-right: 16px; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { transition: none !important; animation-duration: .001ms !important; }
        }
    </style>
</head>
<body>

<div class="shell">

    <?php $sidebar_page = 'gestion_documents'; $sidebar_base = ''; require_once __DIR__ . '/../includes/sidebar_admin.php'; ?>

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
                    <h1>Archive des documents</h1>
                    <p>Les arrêtés, contrats, diplômes et autres pièces numérisées du personnel, classés par agent.</p>
                </div>
                <a href="admin_dashboard.php" class="btn btn-light">
                    <i class="fa-solid fa-arrow-left"></i> Retour au tableau de bord
                </a>
            </div>

            <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
                <div class="alert" role="status">
                    <i class="fa-solid fa-circle-check"></i>
                    <span>L’opération a bien été enregistrée.</span>
                    <button type="button" class="close" aria-label="Fermer"><i class="fa-solid fa-xmark"></i></button>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['error'])): ?>
                <div class="alert error" role="alert">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span>L’opération n’a pas pu aboutir. Vérifiez le fichier et réessayez.</span>
                    <button type="button" class="close" aria-label="Fermer"><i class="fa-solid fa-xmark"></i></button>
                </div>
            <?php endif; ?>

            <!-- Indicateurs -->
            <section class="kpis" aria-label="Indicateurs clés">
                <div class="kpi">
                    <div class="kpi-icon i-green"><i class="fa-solid fa-users"></i></div>
                    <div>
                        <div class="kpi-value"><?php echo number_format($total_agents, 0, ',', ' '); ?></div>
                        <div class="kpi-label">Agents</div>
                    </div>
                </div>
                <div class="kpi">
                    <div class="kpi-icon i-warm"><i class="fa-solid fa-folder-open"></i></div>
                    <div>
                        <div class="kpi-value"><?php echo number_format($total_docs, 0, ',', ' '); ?></div>
                        <div class="kpi-label">Documents archivés</div>
                    </div>
                </div>
            </section>

            <div class="grid <?php echo $peut_modifier ? 'with-rail' : ''; ?>">

                <!-- Archives -->
                <section class="panel" aria-labelledby="archivesTitre">
                    <div class="panel-head">
                        <h2 class="panel-title" id="archivesTitre">Archives numériques du personnel</h2>
                        <span class="panel-meta" id="resultCount"></span>
                    </div>

                    <div class="toolbar">
                        <div class="field">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="search" id="searchDocs" placeholder="Rechercher un agent, un document ou un matricule" aria-label="Rechercher dans les archives" autocomplete="off">
                        </div>
                    </div>

                    <div class="table-wrap">
                        <table id="docsTable">
                            <thead>
                                <tr>
                                    <th>Agent</th>
                                    <th>Document</th>
                                    <th>Enregistré le</th>
                                    <th style="text-align:right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="docsBody">
                                <?php foreach ($documents as $doc):
                                    [$ico, $icoClass] = icone_doc($doc['fich_url']);
                                    $ts = strtotime((string)$doc['date_enre']) ?: null;
                                ?>
                                <tr data-row>
                                    <td>
                                        <div class="person">
                                            <div class="avatar sm light"><?php echo e(initiales($doc['nom_fonc'], $doc['prenom_fonc'])); ?></div>
                                            <div>
                                                <div class="person-name">
                                                    <?php echo e(mb_strtoupper((string)$doc['nom_fonc'], 'UTF-8')); ?>
                                                    <span class="person-first"><?php echo e($doc['prenom_fonc']); ?></span>
                                                </div>
                                                <span class="person-matri">Matricule <?php echo e($doc['matri']); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="<?php echo e($doc['fich_url']); ?>" target="_blank" rel="noopener" class="doc-link">
                                            <span class="file-ico <?php echo $icoClass; ?>"><i class="fa-solid <?php echo $ico; ?>"></i></span>
                                            <?php echo e($doc['lib_doc']); ?>
                                        </a>
                                    </td>
                                    <td class="when">
                                        <?php if ($ts): ?>
                                            <div class="d"><?php echo date('d/m/Y', $ts); ?></div>
                                            <div class="t"><?php echo date('H:i', $ts); ?></div>
                                        <?php else: ?>
                                            <span style="color:var(--muted);">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <a href="<?php echo e($doc['fich_url']); ?>" target="_blank" rel="noopener" class="act" title="Ouvrir le document" aria-label="Ouvrir le document">
                                                <i class="fa-solid fa-eye"></i>
                                            </a>
                                            <?php if ($peut_modifier): ?>
                                                <a href="../saisie/modifier_doc.php?id=<?php echo (int)$doc['id_doc']; ?>" class="act edit" title="Modifier" aria-label="Modifier">
                                                    <i class="fa-solid fa-pen"></i>
                                                </a>
                                                <a href="supprimer_doc.php?id=<?php echo (int)$doc['id_doc']; ?>" class="act delete" title="Supprimer" aria-label="Supprimer"
                                                   onclick="return confirm('Supprimer définitivement ce document ? Cette action est irréversible.');">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>

                                <?php if (empty($documents)): ?>
                                <tr>
                                    <td colspan="4" class="empty">
                                        <i class="fa-solid fa-folder-open"></i>
                                        <strong>Aucun document archivé</strong>
                                        <?php echo $peut_modifier ? 'Ajoutez le premier document avec le formulaire.' : 'Les documents apparaîtront ici dès qu’ils seront archivés.'; ?>
                                    </td>
                                </tr>
                                <?php endif; ?>

                                <tr id="noResultRow" style="display:none;">
                                    <td colspan="4" class="empty">
                                        <i class="fa-solid fa-magnifying-glass"></i>
                                        <strong>Aucun résultat</strong>
                                        Essayez un autre nom, un autre matricule ou un autre intitulé.
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

                <?php if ($peut_modifier): ?>
                <!-- Formulaire d'ajout -->
                <aside class="rail">
                    <section class="panel" aria-labelledby="ajoutTitre">
                        <div class="panel-head">
                            <div>
                                <h2 class="panel-title" id="ajoutTitre">Archiver un document</h2>
                                <p class="panel-sub">PDF, JPG ou PNG, 5 Mo maximum.</p>
                            </div>
                        </div>
                        <div class="panel-body">
                            <form id="documentForm" action="../actions/traitement_document.php" method="POST" enctype="multipart/form-data" novalidate>
                                <div class="form-group">
                                    <label for="agentSelect">Agent concerné</label>
                                    <select name="id_fonc" id="agentSelect" class="control" required>
                                        <option value="">Choisir un agent</option>
                                        <?php foreach ($agents as $a): ?>
                                            <option value="<?php echo (int)$a['id_fonc']; ?>" data-aff="<?php echo e($a['id_affec']); ?>">
                                                <?php echo e(mb_strtoupper((string)$a['nom_fonc'], 'UTF-8')); ?> <?php echo e($a['prenom_fonc']); ?> (<?php echo e($a['matri']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <input type="hidden" name="id_affec" id="id_aff_hidden">

                                <div class="form-group">
                                    <label for="libDoc">Intitulé du document</label>
                                    <input type="text" name="lib_doc" id="libDoc" class="control" placeholder="Ex. : Arrêté de nomination, contrat, diplôme" required autocomplete="off">
                                    <span class="hint" id="libDocHelp"></span>
                                </div>

                                <div class="form-group">
                                    <label for="documentFile">Fichier</label>
                                    <div class="dropzone" id="fileUpload">
                                        <div class="dz-ico"><i class="fa-solid fa-cloud-arrow-up" id="dzIcon"></i></div>
                                        <div class="dz-title" id="file-name">Cliquez ou déposez un fichier</div>
                                        <div class="dz-sub" id="file-sub">PDF, JPG ou PNG</div>
                                        <input type="file" name="document_file" id="documentFile" required accept=".pdf,.jpg,.jpeg,.png">
                                    </div>
                                </div>

                                <button type="submit" id="submitBtn" class="btn btn-primary btn-block">
                                    <i class="fa-solid fa-box-archive ico"></i>
                                    <span class="spin"></span>
                                    <span>Archiver le document</span>
                                </button>
                            </form>
                        </div>
                    </section>
                </aside>
                <?php endif; ?>

            </div>
        </div>
    </main>
</div>

<script>
    // ---------- Menu mobile ----------
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

    document.querySelectorAll('.alert .close').forEach(btn => {
        btn.addEventListener('click', () => btn.closest('.alert').remove());
    });

    // ---------- Notification ----------
    function showNotification(message, type) {
        document.querySelectorAll('.toast').forEach(t => t.remove());
        const t = document.createElement('div');
        t.className = 'toast ' + (type || 'info');
        t.setAttribute('role', 'status');
        const icon = document.createElement('i');
        icon.className = 'fa-solid ' + (type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation');
        const span = document.createElement('span');
        span.textContent = message;
        t.append(icon, span);
        document.body.appendChild(t);
        setTimeout(() => t.remove(), 3500);
    }

    // ---------- Recherche + pagination des archives ----------
    (function () {
        const body   = document.getElementById('docsBody');
        const search = document.getElementById('searchDocs');
        const pager  = document.getElementById('pagerBox');
        const pList  = document.getElementById('pagerList');
        const pInfo  = document.getElementById('pagerInfo');
        const count  = document.getElementById('resultCount');
        const noRes  = document.getElementById('noResultRow');
        if (!body) return;

        const allRows = Array.from(body.querySelectorAll('tr[data-row]'));
        const perPage = 10;
        let page = 1, filtered = allRows;

        function apply() {
            const q = (search.value || '').toLowerCase().trim();
            filtered = allRows.filter(r => !q || r.textContent.toLowerCase().includes(q));
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
            if (allRows.length > 0) count.textContent = total + (total > 1 ? ' documents' : ' document');

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
        apply();
    })();

    // ---------- Formulaire d'archivage ----------
    (function () {
        const form = document.getElementById('documentForm');
        if (!form) return;

        const agentSelect = document.getElementById('agentSelect');
        const affInput    = document.getElementById('id_aff_hidden');
        const libDoc      = document.getElementById('libDoc');
        const libHelp     = document.getElementById('libDocHelp');
        const fileInput   = document.getElementById('documentFile');
        const dropzone    = document.getElementById('fileUpload');
        const dzTitle     = document.getElementById('file-name');
        const dzSub       = document.getElementById('file-sub');
        const dzIcon      = document.getElementById('dzIcon');
        const submitBtn   = document.getElementById('submitBtn');

        const MAX_BYTES = 5 * 1024 * 1024;
        const EXT_OK = ['pdf', 'jpg', 'jpeg', 'png'];

        // Affectation de l'agent choisi
        agentSelect.addEventListener('change', () => {
            const opt = agentSelect.options[agentSelect.selectedIndex];
            affInput.value = opt ? (opt.getAttribute('data-aff') || '') : '';
            agentSelect.classList.toggle('ok', !!agentSelect.value);
        });

        // Validation de l'intitulé
        libDoc.addEventListener('input', () => {
            const v = libDoc.value.trim();
            libDoc.classList.remove('ok', 'bad');
            libHelp.className = 'hint';
            if (!v) { libHelp.textContent = ''; return; }
            if (v.length >= 5) {
                libDoc.classList.add('ok'); libHelp.classList.add('ok');
                libHelp.textContent = 'Intitulé valide';
            } else {
                libDoc.classList.add('bad'); libHelp.classList.add('bad');
                libHelp.textContent = 'Au moins 5 caractères';
            }
        });

        // Gestion du fichier
        function resetDrop() {
            dropzone.classList.remove('ok', 'bad');
            dzTitle.textContent = 'Cliquez ou déposez un fichier';
            dzSub.textContent = 'PDF, JPG ou PNG';
            dzIcon.className = 'fa-solid fa-cloud-arrow-up';
        }

        fileInput.addEventListener('change', () => {
            const file = fileInput.files && fileInput.files[0];
            if (!file) { resetDrop(); return; }

            const ext = (file.name.split('.').pop() || '').toLowerCase();
            if (!EXT_OK.includes(ext)) {
                fileInput.value = ''; resetDrop();
                dropzone.classList.add('bad');
                dzSub.textContent = 'Format non pris en charge. Utilisez PDF, JPG ou PNG.';
                showNotification('Format non pris en charge (PDF, JPG ou PNG).', 'error');
                return;
            }
            if (file.size > MAX_BYTES) {
                fileInput.value = ''; resetDrop();
                dropzone.classList.add('bad');
                dzSub.textContent = 'Fichier trop volumineux (5 Mo maximum).';
                showNotification('Le fichier ne doit pas dépasser 5 Mo.', 'error');
                return;
            }
            dropzone.classList.remove('bad');
            dropzone.classList.add('ok');
            dzTitle.textContent = file.name;
            dzSub.textContent = (file.size / 1024 / 1024).toFixed(2).replace('.', ',') + ' Mo, prêt à être archivé';
            dzIcon.className = 'fa-solid fa-circle-check';
        });

        ['dragenter', 'dragover'].forEach(ev => dropzone.addEventListener(ev, () => dropzone.classList.add('drag')));
        ['dragleave', 'drop'].forEach(ev => dropzone.addEventListener(ev, () => dropzone.classList.remove('drag')));

        // Envoi réel du formulaire après validation
        form.addEventListener('submit', (e) => {
            if (!agentSelect.value) {
                e.preventDefault();
                showNotification('Choisissez l’agent concerné.', 'error');
                agentSelect.focus();
                return;
            }
            if (libDoc.value.trim().length < 5) {
                e.preventDefault();
                showNotification('L’intitulé doit contenir au moins 5 caractères.', 'error');
                libDoc.focus();
                return;
            }
            if (!fileInput.files || !fileInput.files[0]) {
                e.preventDefault();
                showNotification('Ajoutez un fichier à archiver.', 'error');
                return;
            }
            // Le formulaire part vers traitement_document.php ; on évite seulement le double clic.
            submitBtn.classList.add('loading');
            setTimeout(() => { submitBtn.setAttribute('disabled', 'disabled'); }, 0);
        });
    })();
</script>
</body>
</html>
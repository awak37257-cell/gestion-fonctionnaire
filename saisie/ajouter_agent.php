<?php
session_start();
require_once '../config.php';
require_once '../includes/phone_numbers.php';

// Protection : connexion obligatoire
if (!isset($_SESSION['rol'])) {
    header("Location: ../saisie/login.php");
    exit();
}

$user_role = $_SESSION['rol'];
$user_org_id = $_SESSION['id_org'] ?? null;
$nom_session = $_SESSION['nom'] ?? 'Admin';
$stmtNotif = $db->prepare("SELECT COUNT(*) FROM NOTIFICATIONS WHERE id_user = ? AND statut = 'a_verifier'");
$stmtNotif->execute([(int) ($_SESSION['id_user'] ?? 0)]);
$notifications_a_verifier = (int) $stmtNotif->fetchColumn();

// Seuls les profils habilités à créer des agents peuvent ouvrir ce formulaire
if (!in_array($user_role, ['admin', 'admin_local', 'gestionnaire'], true)) {
    header("Location: ../views/admin_dashboard.php");
    exit();
}

// Helpers d'affichage
if (!function_exists('e')) {
    function e($v)
    {
        return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('initiales')) {
    function initiales($nom, $prenom = '')
    {
        $a = mb_substr(trim((string) $nom), 0, 1, 'UTF-8');
        $b = mb_substr(trim((string) $prenom), 0, 1, 'UTF-8');
        return mb_strtoupper($a . $b, 'UTF-8') ?: '?';
    }
}
$libelles_roles = [
    'admin' => 'Administrateur central',
    'admin_local' => 'Administrateur local',
    'gestionnaire' => 'Gestionnaire',
];
$libelle_role = $libelles_roles[$user_role] ?? ucfirst($user_role);

// Jeton CSRF (à vérifier dans traitement_agent.php avec hash_equals)
if (empty($_SESSION['csrf_agent'])) {
    $_SESSION['csrf_agent'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_agent'];
$regions_telephoniques = indicatifs_telephoniques();

// Admin global : toutes les organisations. Sinon : uniquement la sienne.
$organisations = [];
$nom_ma_structure = null;
if ($user_role === 'admin') {
    $organisations = $db->query("SELECT id_org, nom_org, sigle_org FROM ORGANISATION ORDER BY nom_org ASC")->fetchAll();
} elseif ($user_org_id) {
    $stmt = $db->prepare("SELECT nom_org FROM ORGANISATION WHERE id_org = ?");
    $stmt->execute([$user_org_id]);
    $ma_structure = $stmt->fetch();
    $nom_ma_structure = $ma_structure['nom_org'] ?? null;
}
$org_manquante = ($user_role !== 'admin' && !$nom_ma_structure);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouveau fonctionnaire | Ministère des Affaires étrangères</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Instrument+Serif&family=Manrope:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
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
            --red: #C73A30;
            --red-soft: #FBE9E7;
            --sidebar-w: 268px;
            --font-ui: 'Manrope', system-ui, -apple-system, 'Segoe UI', sans-serif;
            --font-display: 'Instrument Serif', Georgia, 'Times New Roman', serif;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html {
            -webkit-text-size-adjust: 100%;
        }

        body {
            margin: 0;
            font-family: var(--font-ui);
            font-size: 14px;
            line-height: 1.5;
            color: var(--text);
            background: var(--paper);
            -webkit-font-smoothing: antialiased;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button,
        input,
        select {
            font: inherit;
            color: inherit;
        }

        :focus-visible {
            outline: 2px solid var(--accent);
            outline-offset: 2px;
            border-radius: 6px;
        }

        /* ---------- Layout ---------- */
        .shell {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            width: var(--sidebar-w);
            background: linear-gradient(180deg, #0E4A37 0%, #0A3B2C 55%, #072B20 100%);
            color: #fff;
            display: flex;
            flex-direction: column;
            z-index: 60;
            transition: transform .25s ease;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 22px 20px 20px;
            border-bottom: 1px solid var(--ink-line);
        }

        .brand-mark {
            width: 40px;
            height: 40px;
            flex: none;
            border-radius: 10px;
            background: var(--accent);
            display: grid;
            place-items: center;
            font-size: 18px;
            color: #fff;
        }

        .brand-name {
            font-weight: 700;
            font-size: 14px;
            line-height: 1.25;
        }

        .brand-sub {
            font-size: 12px;
            color: rgba(255, 255, 255, .55);
            margin-top: 2px;
        }

        .nav {
            flex: 1;
            overflow-y: auto;
            padding: 14px 12px;
        }

        .nav-group {
            font-size: 12px;
            font-weight: 600;
            color: rgba(255, 255, 255, .4);
            padding: 16px 12px 6px;
        }

        .nav-link {
            position: relative;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            border-radius: 8px;
            color: rgba(255, 255, 255, .72);
            font-weight: 500;
            transition: background .15s, color .15s;
        }

        .nav-link i {
            width: 18px;
            text-align: center;
            font-size: 14px;
            color: rgba(255, 255, 255, .45);
            transition: color .15s;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, .06);
            color: #fff;
        }

        .nav-link:hover i {
            color: rgba(255, 255, 255, .8);
        }

        .nav-link.active {
            background: rgba(247, 127, 0, .16);
            color: #fff;
        }

        .nav-link.active i {
            color: var(--accent);
        }

        .nav-link.active::before {
            content: "";
            position: absolute;
            left: -12px;
            top: 8px;
            bottom: 8px;
            width: 3px;
            border-radius: 0 3px 3px 0;
            background: var(--accent);
        }

        .sidebar-user {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 20px;
            border-top: 1px solid var(--ink-line);
        }

        .sidebar-user .name {
            font-weight: 600;
            font-size: 13px;
            line-height: 1.2;
        }

        .sidebar-user .role {
            font-size: 12px;
            color: rgba(255, 255, 255, .55);
        }

        .avatar {
            flex: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            font-size: 12px;
            font-weight: 700;
            background: var(--ink-2);
            color: #fff;
            overflow: hidden;
        }

        .main {
            margin-left: var(--sidebar-w);
            width: calc(100% - var(--sidebar-w));
            min-width: 0;
        }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 40;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 12px 32px;
            background: rgba(242, 246, 243, .88);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--line);
        }

        .topbar-left,
        .topbar-right {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .icon-btn {
            width: 38px;
            height: 38px;
            flex: none;
            display: inline-grid;
            place-items: center;
            border: 1px solid var(--line);
            background: var(--surface);
            border-radius: 9px;
            cursor: pointer;
            color: var(--muted);
            transition: color .15s, border-color .15s, background .15s;
        }

        .icon-btn:hover {
            color: var(--text);
            border-color: #CBD2DC;
        }

        .icon-btn.danger:hover {
            color: var(--red);
            border-color: #F0C4BF;
            background: var(--red-soft);
        }

        #menuToggleBtn {
            display: none;
        }

        .org-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 12px;
            background: #F7FCF9;
            border: 1px solid #CFE5D8;
            border-radius: 999px;
            font-weight: 600;
            font-size: 13px;
            max-width: 100%;
        }

        .org-chip i {
            color: var(--green);
        }

        .org-chip span {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .content {
            padding: 32px;
            max-width: 980px;
        }

        /* ---------- Bannière ---------- */
        .page-head {
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 24px;
            padding: 32px 32px 28px;
            border-radius: 16px;
            color: #fff;
            background:
                radial-gradient(520px 220px at 100% 0%, rgba(247, 127, 0, .38), transparent 70%),
                linear-gradient(120deg, #0A3B2C 0%, #11634A 100%);
        }

        .page-head::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
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

        .page-head p {
            margin: 8px 0 0;
            color: rgba(255, 255, 255, .78);
            font-size: 14px;
            max-width: 62ch;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 16px;
            border-radius: 9px;
            border: 1px solid transparent;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: background .15s, border-color .15s, transform .1s, color .15s;
        }

        .btn:active {
            transform: translateY(1px);
        }

        .btn-primary {
            background: var(--accent);
            color: #fff;
            box-shadow: 0 6px 16px rgba(247, 127, 0, .3);
        }

        .btn-primary:hover {
            background: #FF8F1F;
        }

        .btn-light {
            background: rgba(255, 255, 255, .14);
            border-color: rgba(255, 255, 255, .25);
            color: #fff;
        }

        .btn-light:hover {
            background: rgba(255, 255, 255, .22);
        }

        .btn-ghost {
            background: var(--surface);
            border-color: var(--line);
            color: var(--muted);
        }

        .btn-ghost:hover {
            color: var(--text);
            border-color: #CBD2DC;
        }

        .btn.loading {
            pointer-events: none;
            opacity: .8;
        }

        .btn[disabled] {
            opacity: .5;
            pointer-events: none;
            box-shadow: none;
        }

        .btn .spin {
            display: none;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, .4);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin .7s linear infinite;
        }

        .btn.loading .spin {
            display: inline-block;
        }

        .btn.loading .ico {
            display: none;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* ---------- Alertes ---------- */
        .alert {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px 16px;
            margin-bottom: 20px;
            border-radius: 10px;
            font-weight: 500;
            background: var(--green-soft);
            color: #14633F;
            border: 1px solid #BFE3D0;
        }

        .alert.error {
            background: var(--red-soft);
            color: #8E2A22;
            border-color: #F0C4BF;
        }

        .alert i {
            margin-top: 3px;
        }

        .alert .close {
            margin-left: auto;
            background: none;
            border: 0;
            cursor: pointer;
            color: inherit;
            opacity: .6;
        }

        .alert .close:hover {
            opacity: 1;
        }

        /* ---------- Sections ---------- */
        .panel {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 14px;
            margin-bottom: 20px;
        }

        .panel-head {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 18px 24px;
            border-bottom: 1px solid var(--line-soft);
        }

        .panel-ico {
            width: 34px;
            height: 34px;
            flex: none;
            display: grid;
            place-items: center;
            border-radius: 9px;
            font-size: 14px;
            background: var(--green-soft);
            color: var(--green);
        }

        .panel-ico.warm {
            background: var(--accent-soft);
            color: var(--accent-dark);
        }

        .panel-ico.blue {
            background: var(--blue-soft);
            color: var(--blue);
        }

        .panel-title {
            margin: 0;
            font-size: 15px;
            font-weight: 700;
        }

        .panel-sub {
            margin: 0;
            color: var(--muted);
            font-size: 12px;
        }

        .panel-body {
            padding: 22px 24px 24px;
        }

        .fgrid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px 20px;
        }

        .full {
            grid-column: 1 / -1;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 6px;
            min-width: 0;
        }

        .field label {
            font-size: 13px;
            font-weight: 600;
        }

        .req {
            color: var(--accent-dark);
            margin-left: 2px;
        }

        .opt {
            color: var(--muted);
            font-weight: 500;
            font-size: 12px;
            margin-left: 4px;
        }

        .control {
            width: 100%;
            height: 42px;
            padding: 0 14px;
            background: var(--surface);
            border: 1px solid #D5DBE3;
            border-radius: 9px;
            transition: border-color .15s, box-shadow .15s;
        }

        select.control {
            appearance: none;
            cursor: pointer;
            padding-right: 36px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='none' stroke='%236A7686' stroke-width='1.6' stroke-linecap='round' d='M2.5 4.5 6 8l3.5-3.5'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
        }

        .control::placeholder {
            color: #9AA5B3;
        }

        .control:hover {
            border-color: #B9C2CE;
        }

        .control:focus {
            outline: none;
            border-color: var(--green);
            box-shadow: 0 0 0 3px rgba(14, 159, 104, .15);
        }

        .control.bad {
            border-color: var(--red);
        }

        .control:disabled {
            background-color: var(--paper);
            color: var(--muted);
            cursor: not-allowed;
            border-color: var(--line);
        }

        select.control:disabled {
            background-image: none;
            padding-right: 14px;
        }

        .err {
            font-size: 12px;
            font-weight: 600;
            color: var(--red);
        }

        .err:empty {
            display: none;
        }

        .hint {
            font-size: 12px;
            color: var(--muted);
        }

        .tel-row {
            display: grid;
            grid-template-columns: minmax(0, 1.5fr) minmax(0, 1fr);
            gap: 8px;
        }

        .tel-row select {
            min-width: 0;
        }

        .tel-row input {
            min-width: 0;
        }

        @media (max-width: 480px) {
            .tel-row { grid-template-columns: 1fr; }
        }

        /* Photo */
        .photo-row {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .photo-preview {
            width: 64px;
            height: 64px;
            flex: none;
            border-radius: 50%;
            overflow: hidden;
            display: grid;
            place-items: center;
            background: var(--paper);
            color: #9AA5B3;
            font-size: 22px;
            border: 2px solid var(--line);
        }

        .photo-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            display: block;
        }

        .photo-actions {
            display: flex;
            flex-direction: column;
            gap: 6px;
            align-items: flex-start;
            min-width: 0;
        }

        .file-btn {
            position: relative;
            overflow: hidden;
        }

        .file-btn input {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
        }

        .hint.bad {
            color: var(--red);
            font-weight: 600;
        }

        .hint.ok {
            color: var(--green);
            font-weight: 600;
        }

        .fixed-org {
            display: flex;
            align-items: center;
            gap: 10px;
            height: 42px;
            padding: 0 14px;
            border: 1px solid var(--line);
            border-radius: 9px;
            background: var(--paper);
            font-weight: 600;
        }

        .fixed-org i {
            color: var(--green);
        }

        .actions-bar {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            flex-wrap: wrap;
            padding-top: 4px;
        }

        .overlay {
            position: fixed;
            inset: 0;
            z-index: 50;
            background: rgba(10, 59, 44, .5);
            opacity: 0;
            pointer-events: none;
            transition: opacity .25s;
        }

        .overlay.show {
            opacity: 1;
            pointer-events: auto;
        }

        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.open {
                transform: translateX(0);
                box-shadow: 0 0 40px rgba(0, 0, 0, .3);
            }

            .main {
                margin-left: 0;
                width: 100%;
            }

            #menuToggleBtn {
                display: inline-grid;
            }

            .topbar {
                padding: 12px 16px;
            }

            .content {
                padding: 20px 16px 32px;
            }

            .page-head {
                padding: 28px 20px 22px;
            }

            .panel-head,
            .panel-body {
                padding-left: 16px;
                padding-right: 16px;
            }
        }

        @media (max-width: 640px) {
            .fgrid {
                grid-template-columns: 1fr;
            }

            .actions-bar .btn {
                flex: 1;
            }
        }

        @media (prefers-reduced-motion: reduce) {

            *,
            *::before,
            *::after {
                transition: none !important;
                animation: none !important;
            }
        }
    </style>
</head>

<body>

    <div class="shell">

        <?php $sidebar_page = 'ajouter_agent';
        $sidebar_base = '../';
        require_once __DIR__ . '/../includes/sidebar_admin.php'; ?>

        <main class="main">

            <header class="topbar">
                <div class="topbar-left">
                    <button type="button" id="menuToggleBtn" class="icon-btn" aria-label="Ouvrir le menu"
                        aria-controls="sidebarAdmin">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                    <div class="org-chip">
                        <i class="fa-solid fa-building-shield"></i>
                        <span><?= e($user_role === 'admin' ? 'Administration centrale' : ($nom_ma_structure ?: 'Organisation non définie')) ?></span>
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
                        <h1>Nouveau fonctionnaire</h1>
                        <p>
                            <?= $user_role === 'admin'
                                ? 'Enregistrez un agent et rattachez-le à une direction.'
                                : 'Le fonctionnaire sera rattaché à ' . e($nom_ma_structure ?: 'votre organisation') . '.' ?>
                        </p>
                    </div>
                    <a href="../views/liste_fonctionnaires.php" class="btn btn-light">
                        <i class="fa-solid fa-arrow-left"></i> Retour à la liste
                    </a>
                </div>

                <?php if ($org_manquante): ?>
                    <div class="alert error" role="alert">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span>Aucune organisation n’est associée à votre compte. Contactez l’administrateur central avant
                            d’enregistrer un agent.</span>
                    </div>
                <?php endif; ?>
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert" role="status">
                        <i class="fa-solid fa-circle-check"></i>
                        <span>Le fonctionnaire a bien été enregistré.</span>
                        <button type="button" class="close" aria-label="Fermer"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                <?php endif; ?>
                <?php if (isset($_GET['error'])): ?>
                    <div class="alert error" role="alert">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span><?= e($_GET['error']) ?></span>
                        <button type="button" class="close" aria-label="Fermer"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                <?php endif; ?>

                <form action="../actions/traitement_agent.php" method="POST" enctype="multipart/form-data"
                    id="agentForm" novalidate>
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

                    <!-- Identité -->
                    <section class="panel">
                        <div class="panel-head">
                            <div class="panel-ico"><i class="fa-solid fa-id-card"></i></div>
                            <div>
                                <h2 class="panel-title">Identité</h2>
                                <p class="panel-sub">État civil et photo de l’agent</p>
                            </div>
                        </div>
                        <div class="panel-body">
                            <div class="fgrid">
                                <div class="field">
                                    <label for="matricule">Numéro de matricule <span class="req">*</span></label>
                                    <input class="control" type="text" id="matricule" name="matricule"
                                        placeholder="Ex. : 584930Z" autocomplete="off" required>
                                    <span class="err" data-err="matricule"></span>
                                </div>
                                <div class="field">
                                    <label for="genre">Genre <span class="req">*</span></label>
                                    <select class="control" id="genre" name="genre" required>
                                        <option value="">Choisir</option>
                                        <option value="M">Masculin</option>
                                        <option value="F">Féminin</option>
                                    </select>
                                    <span class="err" data-err="genre"></span>
                                </div>
                                <div class="field">
                                    <label for="nom">Nom de famille <span class="req">*</span></label>
                                    <input class="control" type="text" id="nom" name="nom" placeholder="Ex. : KOFFI"
                                        autocomplete="off" required>
                                    <span class="err" data-err="nom"></span>
                                </div>
                                <div class="field">
                                    <label for="prenoms">Prénoms <span class="req">*</span></label>
                                    <input class="control" type="text" id="prenoms" name="prenoms"
                                        placeholder="Ex. : Jean-Marc" autocomplete="off" required>
                                    <span class="err" data-err="prenoms"></span>
                                </div>
                                <div class="field">
                                    <label for="date_naissance">Date de naissance <span class="req">*</span></label>
                                    <input class="control" type="date" id="date_naissance" name="date_naissance"
                                        max="<?= date('Y-m-d') ?>" required>
                                    <span class="err" data-err="date_naissance"></span>
                                </div>

                                <div class="field full">
                                    <label for="photo_agent">Photo de profil <span class="opt">facultatif</span></label>
                                    <div class="photo-row">
                                        <div class="photo-preview" id="photoPreview"><i class="fa-solid fa-user"></i>
                                        </div>
                                        <div class="photo-actions">
                                            <span class="btn btn-ghost file-btn">
                                                <i class="fa-solid fa-camera"></i> Choisir une photo
                                                <input type="file" id="photo_agent" name="photo_agent"
                                                    accept="image/jpeg,image/png,image/webp">
                                            </span>
                                            <span class="hint" id="photoHint">JPG, PNG ou WebP, 2 Mo maximum.</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Affectation -->
                    <section class="panel">
                        <div class="panel-head">
                            <div class="panel-ico warm"><i class="fa-solid fa-briefcase"></i></div>
                            <div>
                                <h2 class="panel-title">Affectation</h2>
                                <p class="panel-sub">Direction et grade de l’agent</p>
                            </div>
                        </div>
                        <div class="panel-body">
                            <div class="fgrid">
                                <div class="field">
                                    <label for="id_org">Organisation <span class="req">*</span></label>
                                    <?php if ($user_role === 'admin'): ?>
                                        <select class="control" name="id_org" id="id_org" required>
                                            <option value="">Choisir une direction</option>
                                            <?php foreach ($organisations as $org): ?>
                                                <option value="<?= (int) $org['id_org'] ?>">
                                                    <?= e(!empty($org['sigle_org']) ? '[' . $org['sigle_org'] . '] ' : '') ?>        <?= e($org['nom_org']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php else: ?>
                                        <div class="fixed-org"><i class="fa-solid fa-lock"></i>
                                            <?= e($nom_ma_structure ?: 'Organisation non définie') ?></div>
                                        <input type="hidden" name="id_org" id="id_org"
                                            value="<?= $org_manquante ? '' : (int) $user_org_id ?>">
                                    <?php endif; ?>
                                    <span class="err" data-err="id_org"></span>
                                </div>

                                <div class="field">
                                    <label for="id_grade">Grade <span class="req">*</span></label>
                                    <select class="control" name="id_affec" id="id_grade" required disabled>
                                        <option value="">Choisissez d’abord une direction</option>
                                    </select>
                                    <span class="err" data-err="id_affec"></span>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Coordonnées -->
                    <section class="panel">
                        <div class="panel-head">
                            <div class="panel-ico blue"><i class="fa-solid fa-address-book"></i></div>
                            <div>
                                <h2 class="panel-title">Coordonnées</h2>
                                <p class="panel-sub">Pour joindre l’agent</p>
                            </div>
                        </div>
                        <div class="panel-body">
                            <div class="fgrid">
                                <div class="field">
                                    <label for="telephone">Téléphone <span class="req">*</span></label>
                                    <div class="tel-row">
                                        <select class="control" name="indicatif" id="indicatif"
                                            aria-label="Indicatif du pays">
                                            <?php foreach ($regions_telephoniques as $region): ?>
                                                <option value="<?= e($region['region']) ?>" data-calling-code="<?= e($region['calling_code']) ?>" data-digits="<?= (int)$region['max_digits'] ?>" <?= $region['region'] === 'CI' ? 'selected' : '' ?>>
                                                    <?= e($region['name']) ?> (<?= e($region['calling_code']) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input class="control" type="tel" name="telephone" id="telephone"
                                            placeholder="Numéro national" inputmode="numeric" autocomplete="tel-national"
                                            maxlength="15" required>
                                    </div>
                                    <span class="hint" id="telephoneHint">Saisissez le numéro national ; sa validité sera vérifiée pour le pays choisi.</span>
                                    <span class="err" data-err="telephone"></span>
                                </div>
                                <div class="field">
                                    <label for="email">E-mail <span class="opt">facultatif</span></label>
                                    <input class="control" type="email" id="email" name="email"
                                        placeholder="agent@ministere.ci" autocomplete="off">
                                    <span class="err" data-err="email"></span>
                                </div>
                                <div class="field full">
                                    <label for="adresse">Adresse de résidence <span class="req">*</span></label>
                                    <input class="control" type="text" id="adresse" name="adresse"
                                        placeholder="Ex. : Abidjan, Cocody" autocomplete="off" required>
                                    <span class="err" data-err="adresse"></span>
                                </div>
                            </div>
                        </div>
                    </section>

                    <div class="actions-bar">
                        <a href="../views/liste_fonctionnaires.php" class="btn btn-ghost">Annuler</a>
                        <button type="submit" class="btn btn-primary" id="submitBtn" <?= $org_manquante ? 'disabled' : '' ?>>
                            <i class="fa-solid fa-floppy-disk ico"></i>
                            <span class="spin"></span>
                            <span>Enregistrer le fonctionnaire</span>
                        </button>
                    </div>
                </form>

            </div>
        </main>
    </div>

    <script>
        (function () {
            // ---------- Menu mobile ----------
            const toggleBtn = document.getElementById('menuToggleBtn');
            const sidebar = document.getElementById('sidebarAdmin');
            const overlay = document.getElementById('overlay');
            function setOpen(open) {
                sidebar.classList.toggle('open', open);
                overlay.classList.toggle('show', open);
                toggleBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
            }
            toggleBtn.addEventListener('click', () => setOpen(!sidebar.classList.contains('open')));
            overlay.addEventListener('click', () => setOpen(false));
            document.addEventListener('keydown', (e) => { if (e.key === 'Escape') setOpen(false); });
            document.querySelectorAll('.alert .close').forEach(b => b.addEventListener('click', () => b.closest('.alert').remove()));

            const form = document.getElementById('agentForm');
            const orgInput = document.getElementById('id_org');
            const gradeSelect = document.getElementById('id_grade');

            // ---------- Chargement des grades selon l'organisation ----------
            let gradeRequest = 0;

            function setGradeMessage(text, disabled) {
                gradeSelect.innerHTML = '';
                const o = document.createElement('option');
                o.value = '';
                o.textContent = text;
                gradeSelect.appendChild(o);
                gradeSelect.disabled = !!disabled;
            }

            function chargerGrades(idOrg) {
                const token = ++gradeRequest;
                if (!idOrg) { setGradeMessage('Choisissez d’abord une direction', true); return; }

                setGradeMessage('Chargement…', true);
                const fd = new FormData();
                fd.append('id_org', idOrg);

                fetch('get_grades.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.text(); })
                    .then(html => {
                        if (token !== gradeRequest) return;       // une réponse plus récente existe
                        // On ne garde que les <option> et on recrée les éléments : aucun HTML du serveur n'est injecté tel quel
                        const doc = new DOMParser().parseFromString('<select>' + html + '</select>', 'text/html');
                        const opts = Array.from(doc.querySelectorAll('option'));
                        gradeSelect.innerHTML = '';
                        const placeholder = opts.find(o => o.value === '');
                        const first = document.createElement('option');
                        first.value = '';
                        first.textContent = placeholder ? placeholder.textContent.trim() : 'Choisir un grade';
                        gradeSelect.appendChild(first);

                        const real = opts.filter(o => o.value !== '');
                        real.forEach(o => {
                            const n = document.createElement('option');
                            n.value = o.value;
                            n.textContent = o.textContent.trim();
                            gradeSelect.appendChild(n);
                        });
                        gradeSelect.disabled = real.length === 0;
                        if (real.length === 0) first.textContent = 'Aucun grade disponible pour cette direction';
                        clearError('id_affec');
                    })
                    .catch(() => {
                        if (token !== gradeRequest) return;
                        setGradeMessage('Chargement impossible. Changez de direction pour réessayer.', true);
                    });
            }

            if (orgInput.tagName === 'SELECT') {
                orgInput.addEventListener('change', () => chargerGrades(orgInput.value));
            }
            if (orgInput.value) chargerGrades(orgInput.value);

            // ---------- Photo : aperçu et contrôle ----------
            const fileInput = document.getElementById('photo_agent');
            const preview = document.getElementById('photoPreview');
            const hint = document.getElementById('photoHint');
            const defaultPreview = preview.innerHTML;
            const defaultHint = hint.textContent;
            fileInput.addEventListener('change', () => {
                const f = fileInput.files && fileInput.files[0];
                hint.className = 'hint';
                if (!f) { preview.innerHTML = defaultPreview; hint.textContent = defaultHint; return; }
                if (!['image/jpeg', 'image/png', 'image/webp'].includes(f.type)) {
                    fileInput.value = ''; preview.innerHTML = defaultPreview;
                    hint.classList.add('bad'); hint.textContent = 'Format non pris en charge. Utilisez JPG, PNG ou WebP.';
                    return;
                }
                if (f.size > 2 * 1024 * 1024) {
                    fileInput.value = ''; preview.innerHTML = defaultPreview;
                    hint.classList.add('bad'); hint.textContent = 'Photo trop volumineuse (2 Mo maximum).';
                    return;
                }
                const img = document.createElement('img');
                img.alt = 'Aperçu de la photo';
                img.src = URL.createObjectURL(f);
                preview.innerHTML = '';
                preview.appendChild(img);
                hint.classList.add('ok');
                hint.textContent = f.name + ' (' + (f.size / 1024 / 1024).toFixed(2).replace('.', ',') + ' Mo)';
            });

            // Nom en majuscules à la sortie du champ
            const nom = document.getElementById('nom');
            nom.addEventListener('blur', () => { nom.value = nom.value.trim().toLocaleUpperCase('fr-FR'); });

            // ---------- Validation ----------
            const submitBtn = document.getElementById('submitBtn');
            const indicatif = document.getElementById('indicatif');
            const telephone = document.getElementById('telephone');
            const telephoneHint = document.getElementById('telephoneHint');
            let submitting = false;

            function configurerTelephone() {
                const longueur = Number(indicatif.selectedOptions[0]?.dataset.digits || 0);
                const estCoteDIvoire = indicatif.value === 'CI';
                telephone.maxLength = longueur;
                telephone.placeholder = 'Numéro national, max. ' + longueur + ' chiffres';
                telephoneHint.textContent = estCoteDIvoire
                    ? 'Côte d’Ivoire (+225) : le numéro doit commencer par 07, 01 ou 05.'
                    : 'Indicatif ' + indicatif.selectedOptions[0]?.dataset.callingCode + ' ; maximum ' + longueur + ' chiffres.';
                telephone.value = telephone.value.replace(/\D/g, '').slice(0, longueur);
                verifierPrefixeTelephone();
                clearError('telephone');
            }

            function verifierPrefixeTelephone() {
                const digits = telephone.value.replace(/\D/g, '');
                const prefixeValide = indicatif.value !== 'CI' || digits.length < 2 || /^(07|01|05)/.test(digits);
                telephoneHint.classList.toggle('bad', !prefixeValide);
                if (!prefixeValide) telephoneHint.textContent = 'En Côte d’Ivoire, le numéro doit commencer par 07, 01 ou 05.';
                return prefixeValide;
            }

            indicatif.addEventListener('change', configurerTelephone);
            telephone.addEventListener('input', () => {
                const longueur = Number(indicatif.selectedOptions[0]?.dataset.digits || 0);
                const valeur = telephone.value.replace(/\D/g, '').slice(0, longueur);
                if (telephone.value !== valeur) telephone.value = valeur;
                verifierPrefixeTelephone();
            });
            configurerTelephone();

            function showError(name, msg) {
                const box = form.querySelector('[data-err="' + name + '"]');
                const input = form.elements[name];
                if (box) box.textContent = msg;
                if (input && input.classList) input.classList.add('bad');
            }
            function clearError(name) {
                const box = form.querySelector('[data-err="' + name + '"]');
                const input = form.elements[name];
                if (box) box.textContent = '';
                if (input && input.classList) input.classList.remove('bad');
            }
            ['matricule', 'genre', 'nom', 'prenoms', 'date_naissance', 'id_org', 'id_affec', 'telephone', 'email', 'adresse'].forEach(n => {
                const el = form.elements[n];
                if (!el) return;
                el.addEventListener('input', () => clearError(n));
                el.addEventListener('change', () => clearError(n));
            });

            form.addEventListener('submit', (e) => {
                if (submitting) { e.preventDefault(); return; }
                const v = n => (form.elements[n].value || '').trim();
                const errors = [];

                if (!v('matricule')) errors.push(['matricule', 'Saisissez le numéro de matricule.']);
                if (!v('genre')) errors.push(['genre', 'Choisissez le genre.']);
                if (!v('nom')) errors.push(['nom', 'Saisissez le nom de famille.']);
                if (!v('prenoms')) errors.push(['prenoms', 'Saisissez les prénoms.']);
                if (!v('date_naissance')) errors.push(['date_naissance', 'Saisissez la date de naissance.']);
                else if (v('date_naissance') > new Date().toISOString().slice(0, 10)) errors.push(['date_naissance', 'La date de naissance ne peut pas être dans le futur.']);
                if (!v('id_org')) errors.push(['id_org', 'Choisissez une direction.']);
                if (!v('id_affec')) errors.push(['id_affec', 'Choisissez un grade.']);
                const longueurTelephone = Number(indicatif.selectedOptions[0]?.dataset.digits || 0);
                const digits = v('telephone').replace(/\D/g, '');
                if (!digits) errors.push(['telephone', 'Saisissez le numéro de téléphone.']);
                else if (!longueurTelephone || digits.length > longueurTelephone) errors.push(['telephone', 'Ce numéro dépasse la longueur autorisée pour le pays sélectionné.']);
                else if (indicatif.value === 'CI' && !/^(07|01|05)/.test(digits)) errors.push(['telephone', 'En Côte d’Ivoire, le numéro doit commencer par 07, 01 ou 05.']);
                if (v('email') && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v('email'))) errors.push(['email', 'Cette adresse e-mail n’est pas valide.']);
                if (!v('adresse')) errors.push(['adresse', 'Saisissez l’adresse de résidence.']);

                if (errors.length) {
                    e.preventDefault();
                    errors.forEach(([n, m]) => showError(n, m));
                    const target = form.elements[errors[0][0]];
                    if (target && target.type !== 'hidden') target.focus();
                    else document.querySelector('.fixed-org')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    return;
                }
                // Le formulaire part vers traitement_agent.php ; on évite seulement le double envoi
                submitting = true;
                submitBtn.classList.add('loading');
            });
        })();
    </script>
</body>

</html>
<?php
session_start();
require_once '../config.php';

// Sécurité Admin
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header("Location: login.php");
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

$nom_session = $_SESSION['nom'] ?? 'Admin';

// Jeton CSRF (à vérifier dans traitement_direction.php et traitement_affectation.php avec hash_equals)
if (empty($_SESSION['csrf_dir'])) {
    $_SESSION['csrf_dir'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_dir'];

// Organisations existantes (avec effectif en poste)
$organisations = $db->query("
    SELECT o.id_org, o.nom_org, o.sigle_org, COUNT(r.id_fonc) AS nb_agents
    FROM ORGANISATION o
    LEFT JOIN RECEVOIR r ON o.id_org = r.id_org AND r.date_fin IS NULL
    GROUP BY o.id_org, o.nom_org, o.sigle_org
    ORDER BY o.nom_org ASC
")->fetchAll();

// Données pour la détection de doublons côté navigateur
$deja = [
    'noms'   => array_map(function ($o) { return mb_strtolower((string)$o['nom_org'], 'UTF-8'); }, $organisations),
    'sigles' => array_values(array_filter(array_map(function ($o) { return mb_strtolower((string)$o['sigle_org'], 'UTF-8'); }, $organisations))),
];

// Message de retour des traitements
$flash_ok = null;
if (isset($_GET['success'])) {
    $flash_ok = match ($_GET['success']) {
        'org', 'organisation' => "L’organisation a bien été enregistrée.",
        'aff', 'affectation'  => "Le poste type a bien été enregistré.",
        default               => "L’opération a bien été enregistrée.",
    };
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Directions et affectations | Ministère des Affaires étrangères</title>
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
            --red: #C73A30;
            --red-soft: #FBE9E7;
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
        .avatar { flex: none; width: 36px; height: 36px; border-radius: 50%; display: grid; place-items: center; font-size: 12px; font-weight: 700; background: var(--ink-2); color: #fff; }

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
        .icon-btn { width: 38px; height: 38px; flex: none; display: inline-grid; place-items: center; border: 1px solid var(--line); background: var(--surface); border-radius: 9px; cursor: pointer; color: var(--muted); transition: color .15s, border-color .15s, background .15s; }
        .icon-btn:hover { color: var(--text); border-color: #CBD2DC; }
        .icon-btn.danger:hover { color: var(--red); border-color: #F0C4BF; background: var(--red-soft); }
        #menuToggleBtn { display: none; }
        .org-chip { display: inline-flex; align-items: center; gap: 8px; padding: 7px 12px; background: #F7FCF9; border: 1px solid #CFE5D8; border-radius: 999px; font-weight: 600; font-size: 13px; }
        .org-chip i { color: var(--green); }

        .content { padding: 32px; max-width: 1360px; }

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
        .page-head::before { content: ""; position: absolute; top: 0; left: 0; right: 0; height: 5px; background: linear-gradient(90deg, #F77F00 0 33.33%, #FFFFFF 33.33% 66.66%, #009E60 66.66% 100%); }
        .page-head h1 { margin: 0; font-family: var(--font-display); font-weight: 400; font-size: clamp(30px, 4vw, 44px); line-height: 1.05; letter-spacing: -.01em; }
        .page-head p { margin: 8px 0 0; color: rgba(255,255,255,.78); font-size: 14px; max-width: 62ch; }

        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            padding: 10px 16px; border-radius: 9px; border: 1px solid transparent;
            font-weight: 600; font-size: 13px; cursor: pointer;
            transition: background .15s, border-color .15s, transform .1s, color .15s;
        }
        .btn:active { transform: translateY(1px); }
        .btn-primary { background: var(--accent); color: #fff; box-shadow: 0 6px 16px rgba(247,127,0,.3); }
        .btn-primary:hover { background: #FF8F1F; }
        .btn-dark { background: var(--ink); color: #fff; }
        .btn-dark:hover { background: var(--ink-2); }
        .btn-light { background: rgba(255,255,255,.14); border-color: rgba(255,255,255,.25); color: #fff; }
        .btn-light:hover { background: rgba(255,255,255,.22); }
        .btn-block { width: 100%; height: 44px; font-size: 14px; }
        .btn.loading { pointer-events: none; opacity: .8; }
        .btn .spin { display: none; width: 16px; height: 16px; border: 2px solid rgba(255,255,255,.4); border-top-color: #fff; border-radius: 50%; animation: spin .7s linear infinite; }
        .btn.loading .spin { display: inline-block; }
        .btn.loading .ico { display: none; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ---------- Alertes ---------- */
        .alert { display: flex; align-items: flex-start; gap: 12px; padding: 12px 16px; margin-bottom: 20px; border-radius: 10px; font-weight: 500; background: var(--green-soft); color: #14633F; border: 1px solid #BFE3D0; }
        .alert.error { background: var(--red-soft); color: #8E2A22; border-color: #F0C4BF; }
        .alert i { margin-top: 3px; }
        .alert .close { margin-left: auto; background: none; border: 0; cursor: pointer; color: inherit; opacity: .6; }
        .alert .close:hover { opacity: 1; }

        /* ---------- Panneaux ---------- */
        .forms { display: grid; grid-template-columns: minmax(0, 1fr); gap: 24px; align-items: start; }
        @media (min-width: 1100px) { .forms { grid-template-columns: repeat(2, minmax(0, 1fr)); } }

        .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; }
        .panel + .panel { margin-top: 24px; }
        .forms .panel + .panel { margin-top: 0; }
        .panel-head { display: flex; align-items: center; gap: 12px; padding: 18px 24px; border-bottom: 1px solid var(--line-soft); }
        .panel-ico { width: 34px; height: 34px; flex: none; display: grid; place-items: center; border-radius: 9px; font-size: 14px; background: var(--green-soft); color: var(--green); }
        .panel-ico.warm { background: var(--accent-soft); color: var(--accent-dark); }
        .panel-ico.blue { background: var(--blue-soft); color: var(--blue); }
        .panel-title { margin: 0; font-size: 15px; font-weight: 700; }
        .panel-sub { margin: 0; color: var(--muted); font-size: 12px; }
        .panel-body { padding: 22px 24px 24px; }
        .panel-head .count { margin-left: auto; font-size: 13px; color: var(--muted); font-weight: 600; }

        .note { display: flex; gap: 10px; align-items: flex-start; margin-bottom: 20px; padding: 12px 14px; border-radius: 10px; background: var(--accent-soft); color: #8A4A00; font-size: 13px; }
        .note i { margin-top: 3px; color: var(--accent-dark); }

        .field { margin-bottom: 18px; }
        .field label { display: block; margin-bottom: 6px; font-size: 13px; font-weight: 600; }
        .req { color: var(--accent-dark); margin-left: 2px; }
        .opt { color: var(--muted); font-weight: 500; font-size: 12px; margin-left: 4px; }
        .hint { margin-top: 6px; font-size: 12px; color: var(--muted); }
        .err { margin-top: 6px; font-size: 12px; font-weight: 600; color: var(--red); }
        .err:empty { display: none; }
        .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0 16px; }

        .control {
            width: 100%; height: 42px; padding: 0 14px;
            background: var(--surface); border: 1px solid #D5DBE3; border-radius: 9px;
            transition: border-color .15s, box-shadow .15s;
        }
        select.control { appearance: none; cursor: pointer; padding-right: 36px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='none' stroke='%236A7686' stroke-width='1.6' stroke-linecap='round' d='M2.5 4.5 6 8l3.5-3.5'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 14px center; }
        .control::placeholder { color: #9AA5B3; }
        .control:hover { border-color: #B9C2CE; }
        .control:focus { outline: none; border-color: var(--green); box-shadow: 0 0 0 3px rgba(14,159,104,.15); }
        .control.bad { border-color: var(--red); }

        /* ---------- Liste ---------- */
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        thead th { text-align: left; white-space: nowrap; padding: 12px 16px; font-size: 12px; font-weight: 600; color: var(--ink-2); background: #EEF5F0; border-bottom: 1px solid var(--line); }
        thead th:first-child, tbody td:first-child { padding-left: 24px; }
        thead th:last-child,  tbody td:last-child  { padding-right: 24px; text-align: right; }
        tbody td { padding: 12px 16px; border-bottom: 1px solid var(--line-soft); vertical-align: middle; }
        tbody tr:last-child td { border-bottom: 0; }
        tbody tr:hover { background: #F3FAF6; }
        .sigle { display: inline-block; padding: 3px 10px; border-radius: 6px; background: var(--paper); border: 1px solid var(--line); font-weight: 700; font-size: 12px; letter-spacing: .4px; }
        .nb { font-variant-numeric: tabular-nums; font-weight: 600; }
        .empty { text-align: center; padding: 40px 24px; color: var(--muted); }

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
            .panel-head, .panel-body { padding-left: 16px; padding-right: 16px; }
            thead th:first-child, tbody td:first-child { padding-left: 16px; }
            thead th:last-child,  tbody td:last-child  { padding-right: 16px; }
        }
        @media (max-width: 560px) { .row2 { grid-template-columns: 1fr; } }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { transition: none !important; animation: none !important; }
        }
    </style>
</head>
<body>

<div class="shell">

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebarAdmin" aria-label="Navigation principale">
        <div class="brand">
            <div class="brand-mark"><i class="fa-solid fa-building-columns"></i></div>
            <div>
                <div class="brand-name">Ministère des Affaires étrangères</div>
                <div class="brand-sub">Ressources humaines</div>
            </div>
        </div>

        <nav class="nav">
            <div class="nav-group">Général</div>
            <a href="../views/admin_dashboard.php" class="nav-link">
                <i class="fa-solid fa-chart-pie"></i> Tableau de bord
            </a>

            <div class="nav-group">Administration</div>
            <a href="ajouter_gestionnaire.php" class="nav-link">
                <i class="fa-solid fa-user-shield"></i> Créer un accès local
            </a>
            <a href="ajouter_direction.php" class="nav-link active" aria-current="page">
                <i class="fa-solid fa-sitemap"></i> Gérer les directions
            </a>
            <a href="../views/admin_historique.php" class="nav-link">
                <i class="fa-solid fa-clock-rotate-left"></i> Historique des actions
            </a>

            <div class="nav-group">Ressources humaines</div>
            <a href="../views/liste_fonctionnaires.php" class="nav-link">
                <i class="fa-solid fa-table-list"></i> Liste complète
            </a>
            <a href="ajouter_agent.php" class="nav-link">
                <i class="fa-solid fa-user-plus"></i> Nouveau fonctionnaire
            </a>
            <a href="../views/gestion_documents.php" class="nav-link">
                <i class="fa-solid fa-folder-open"></i> Archive des documents
            </a>

            <div class="nav-group">Mon compte</div>
            <a href="../views/modifier_mdp.php" class="nav-link">
                <i class="fa-solid fa-lock"></i> Sécurité et mot de passe
            </a>
        </nav>

        <div class="sidebar-user">
            <div class="avatar"><?= e(initiales($nom_session)) ?></div>
            <div>
                <div class="name"><?= e($nom_session) ?></div>
                <div class="role">Administrateur central</div>
            </div>
        </div>
    </aside>
    <div class="overlay" id="overlay"></div>

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
                    <h1>Directions et affectations</h1>
                    <p>Créez les organisations du ministère et les postes types qui servent aux affectations des agents.</p>
                </div>
                <a href="../views/admin_dashboard.php" class="btn btn-light">
                    <i class="fa-solid fa-arrow-left"></i> Retour au tableau de bord
                </a>
            </div>

            <?php if ($flash_ok): ?>
                <div class="alert" role="status">
                    <i class="fa-solid fa-circle-check"></i>
                    <span><?= e($flash_ok) ?></span>
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

            <div class="forms">

                <!-- 1. Organisation -->
                <section class="panel" aria-labelledby="orgTitre">
                    <div class="panel-head">
                        <div class="panel-ico"><i class="fa-solid fa-building-columns"></i></div>
                        <div>
                            <h2 class="panel-title" id="orgTitre">Ajouter une organisation</h2>
                            <p class="panel-sub">Direction ou structure qui accueille des agents</p>
                        </div>
                    </div>
                    <div class="panel-body">
                        <div class="note"><i class="fa-solid fa-circle-info"></i><span>Indiquez le nom complet et le sigle de l’organisation.</span></div>

                        <form action="../actions/traitement_direction.php" method="POST" id="orgForm" novalidate>
                            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

                            <div class="field">
                                <label for="nom_org">Nom de l’organisation <span class="req">*</span></label>
                                <input class="control" type="text" id="nom_org" name="nom_org" maxlength="150" placeholder="Ex. : Organisation des Nations Unies" autocomplete="off" required>
                                <div class="err" data-err="nom_org"></div>
                            </div>

                            <div class="row2">
                                <div class="field">
                                    <label for="sigle_org">Sigle <span class="req">*</span></label>
                                    <input class="control" type="text" id="sigle_org" name="sigle_org" maxlength="20" placeholder="Ex. : ONU" autocomplete="off" required>
                                    <div class="err" data-err="sigle_org"></div>
                                </div>
                                <div class="field">
                                    <label for="siege">Siège ou adresse <span class="opt">facultatif</span></label>
                                    <input class="control" type="text" id="siege" name="siege" maxlength="150" placeholder="Ex. : New York, USA" autocomplete="off">
                                </div>
                            </div>

                            <button type="submit" class="btn btn-dark btn-block" id="orgBtn">
                                <i class="fa-solid fa-floppy-disk ico"></i><span class="spin"></span>
                                <span>Enregistrer l’organisation</span>
                            </button>
                        </form>
                    </div>
                </section>

                <!-- 2. Poste type -->
                <section class="panel" aria-labelledby="affTitre">
                    <div class="panel-head">
                        <div class="panel-ico warm"><i class="fa-solid fa-briefcase"></i></div>
                        <div>
                            <h2 class="panel-title" id="affTitre">Créer un poste type</h2>
                            <p class="panel-sub">Modèle d’affectation réutilisable</p>
                        </div>
                    </div>
                    <div class="panel-body">
                        <div class="note"><i class="fa-solid fa-circle-info"></i><span>Un poste type n’est pas lié à une organisation : le lien se fait lors de l’affectation de l’agent.</span></div>

                        <form action="../actions/traitement_affectation.php" method="POST" id="affForm" novalidate>
                            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

                            <div class="field">
                                <label for="tit_post">Titre du poste <span class="req">*</span></label>
                                <input class="control" type="text" id="tit_post" name="tit_post" maxlength="120" placeholder="Ex. : Premier secrétaire, consultant" autocomplete="off" required>
                                <div class="err" data-err="tit_post"></div>
                            </div>

                            <div class="row2">
                                <div class="field">
                                    <label for="vil_affec">Ville de la mission <span class="req">*</span></label>
                                    <input class="control" type="text" id="vil_affec" name="vil_affec" maxlength="80" placeholder="Ex. : Paris, Abidjan" autocomplete="off" required>
                                    <div class="err" data-err="vil_affec"></div>
                                </div>
                                <div class="field">
                                    <label for="gra_post">Grade (sigle) <span class="req">*</span></label>
                                    <input class="control" type="text" id="gra_post" name="gra_post" maxlength="20" placeholder="Ex. : A4, P3, HC" autocomplete="off" required>
                                    <div class="err" data-err="gra_post"></div>
                                </div>
                            </div>

                            <div class="field">
                                <label for="sta_post">Statut du poste <span class="req">*</span></label>
                                <select class="control" id="sta_post" name="sta_post" required>
                                    <optgroup label="Personnel régulier">
                                        <option value="International (P)">International professionnel (P)</option>
                                        <option value="Local (GS)">Agent local, service général (GS)</option>
                                        <option value="Fixed-Term">Contrat à durée déterminée</option>
                                    </optgroup>
                                    <optgroup label="Autre personnel">
                                        <option value="Consultant">Consultant international</option>
                                        <option value="Volunteer">Volontaire</option>
                                        <option value="Short-Term">Contrat de courte durée</option>
                                    </optgroup>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary btn-block" id="affBtn">
                                <i class="fa-solid fa-plus ico"></i><span class="spin"></span>
                                <span>Enregistrer le poste type</span>
                            </button>
                        </form>
                    </div>
                </section>
            </div>

            <!-- Organisations existantes -->
            <section class="panel" aria-labelledby="listeTitre">
                <div class="panel-head">
                    <div class="panel-ico blue"><i class="fa-solid fa-sitemap"></i></div>
                    <div>
                        <h2 class="panel-title" id="listeTitre">Organisations existantes</h2>
                        <p class="panel-sub">Vérifiez qu’elle n’existe pas déjà avant d’en créer une</p>
                    </div>
                    <span class="count"><?= count($organisations) ?></span>
                </div>
                <?php if (!empty($organisations)): ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr><th>Sigle</th><th>Nom</th><th>Agents en poste</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($organisations as $o): ?>
                            <tr>
                                <td><?= !empty($o['sigle_org']) ? '<span class="sigle">' . e($o['sigle_org']) . '</span>' : '<span style="color:var(--muted)">—</span>' ?></td>
                                <td><?= e($o['nom_org']) ?></td>
                                <td class="nb"><?= (int)$o['nb_agents'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <div class="empty">Aucune organisation enregistrée pour le moment.</div>
                <?php endif; ?>
            </section>

        </div>
    </main>
</div>

<script>
(function () {
    const EXISTANT = <?= json_encode($deja, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    // ---------- Menu mobile ----------
    const toggleBtn = document.getElementById('menuToggleBtn');
    const sidebar   = document.getElementById('sidebarAdmin');
    const overlay   = document.getElementById('overlay');
    function setOpen(open) {
        sidebar.classList.toggle('open', open);
        overlay.classList.toggle('show', open);
        toggleBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    toggleBtn.addEventListener('click', () => setOpen(!sidebar.classList.contains('open')));
    overlay.addEventListener('click', () => setOpen(false));
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') setOpen(false); });
    document.querySelectorAll('.alert .close').forEach(b => b.addEventListener('click', () => b.closest('.alert').remove()));

    // ---------- Aide commune de validation ----------
    function bindForm(form, btn, validate) {
        let submitting = false;

        const showError = (name, msg) => {
            const box = form.querySelector('[data-err="' + name + '"]');
            if (box) box.textContent = msg;
            form.elements[name].classList.add('bad');
        };
        const clearError = (name) => {
            const box = form.querySelector('[data-err="' + name + '"]');
            if (box) box.textContent = '';
            form.elements[name].classList.remove('bad');
        };
        Array.from(form.elements).forEach(el => {
            if (el.name && el.name !== 'csrf') el.addEventListener('input', () => clearError(el.name));
        });

        form.addEventListener('submit', (e) => {
            if (submitting) { e.preventDefault(); return; }
            const errors = validate(n => form.elements[n].value.trim());
            if (errors.length) {
                e.preventDefault();
                errors.forEach(([n, m]) => showError(n, m));
                form.elements[errors[0][0]].focus();
                return;
            }
            // Le formulaire part vers son script de traitement ; on évite seulement le double envoi
            submitting = true;
            btn.classList.add('loading');
        });
    }

    const orgForm = document.getElementById('orgForm');
    const affForm = document.getElementById('affForm');

    // Sigles et grades en majuscules à la sortie du champ
    ['sigle_org'].forEach(n => orgForm.elements[n].addEventListener('blur', e => { e.target.value = e.target.value.trim().toLocaleUpperCase('fr-FR'); }));
    affForm.elements['gra_post'].addEventListener('blur', e => { e.target.value = e.target.value.trim().toLocaleUpperCase('fr-FR'); });

    // Organisation : nom, sigle, détection de doublons
    bindForm(orgForm, document.getElementById('orgBtn'), (v) => {
        const errors = [];
        const nom = v('nom_org'), sigle = v('sigle_org');
        if (nom.length < 3) errors.push(['nom_org', 'Indiquez le nom complet de l’organisation (3 caractères minimum).']);
        else if (EXISTANT.noms.includes(nom.toLocaleLowerCase('fr-FR'))) errors.push(['nom_org', 'Une organisation porte déjà ce nom.']);
        if (sigle.length < 2) errors.push(['sigle_org', 'Indiquez le sigle (2 caractères minimum).']);
        else if (EXISTANT.sigles.includes(sigle.toLocaleLowerCase('fr-FR'))) errors.push(['sigle_org', 'Ce sigle est déjà utilisé.']);
        return errors;
    });

    // Poste type
    bindForm(affForm, document.getElementById('affBtn'), (v) => {
        const errors = [];
        if (v('tit_post').length < 3) errors.push(['tit_post', 'Indiquez le titre du poste (3 caractères minimum).']);
        if (v('vil_affec').length < 2) errors.push(['vil_affec', 'Indiquez la ville de la mission.']);
        if (!v('gra_post')) errors.push(['gra_post', 'Indiquez le grade.']);
        return errors;
    });
})();
</script>
</body>
</html>
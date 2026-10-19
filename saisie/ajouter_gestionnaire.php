<?php
session_start();
require_once '../config.php';

// Sécurité : seul l'admin central peut accéder à cette page
if (!isset($_SESSION['id_user']) || ($_SESSION['rol'] ?? '') !== 'admin') {
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

// Jeton CSRF (à vérifier dans traitement_gest.php avec hash_equals)
if (empty($_SESSION['csrf_gest'])) {
    $_SESSION['csrf_gest'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_gest'];

$directions = $db->query("SELECT id_org, nom_org, sigle_org FROM ORGANISATION ORDER BY nom_org ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer un accès local | Ministère des Affaires étrangères</title>
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

        .content { padding: 32px; max-width: 1280px; }

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
        .btn-light { background: rgba(255,255,255,.14); border-color: rgba(255,255,255,.25); color: #fff; }
        .btn-light:hover { background: rgba(255,255,255,.22); }
        .btn-ghost { background: var(--surface); border-color: var(--line); color: var(--muted); }
        .btn-ghost:hover { color: var(--text); border-color: #CBD2DC; }
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

        /* ---------- Grille ---------- */
        .grid { display: grid; grid-template-columns: minmax(0, 1fr); gap: 24px; align-items: start; }
        @media (min-width: 1200px) { .grid { grid-template-columns: minmax(0, 1fr) 320px; } }

        .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; margin-bottom: 20px; }
        .panel-head { display: flex; align-items: center; gap: 12px; padding: 18px 24px; border-bottom: 1px solid var(--line-soft); }
        .panel-ico { width: 34px; height: 34px; flex: none; display: grid; place-items: center; border-radius: 9px; font-size: 14px; background: var(--green-soft); color: var(--green); }
        .panel-ico.warm { background: var(--accent-soft); color: var(--accent-dark); }
        .panel-title { margin: 0; font-size: 15px; font-weight: 700; }
        .panel-sub { margin: 0; color: var(--muted); font-size: 12px; }
        .panel-body { padding: 22px 24px 24px; }

        .fgrid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px 20px; }
        .full { grid-column: 1 / -1; }
        .field { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
        .field label { font-size: 13px; font-weight: 600; }
        .req { color: var(--accent-dark); margin-left: 2px; }
        .opt { color: var(--muted); font-weight: 500; font-size: 12px; margin-left: 4px; }

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
        .err { min-height: 0; font-size: 12px; font-weight: 600; color: var(--red); }
        .err:empty { display: none; }

        .pwd-row { display: flex; gap: 8px; }
        .pwd-wrap { position: relative; flex: 1; min-width: 0; }
        .pwd-wrap .control { padding-right: 44px; }
        .eye { position: absolute; right: 6px; top: 50%; transform: translateY(-50%); width: 32px; height: 32px; display: grid; place-items: center; border: 0; border-radius: 8px; background: none; cursor: pointer; color: var(--muted); transition: background .15s, color .15s; }
        .eye:hover { background: var(--paper); color: var(--text); }
        .btn-tool { height: 42px; padding: 0 14px; background: var(--surface); border: 1px solid #D5DBE3; border-radius: 9px; font-weight: 600; font-size: 13px; color: var(--ink-2); cursor: pointer; display: inline-flex; align-items: center; gap: 8px; white-space: nowrap; transition: background .15s, border-color .15s; }
        .btn-tool:hover { background: var(--paper); border-color: #B9C2CE; }
        .hint { font-size: 12px; color: var(--muted); }

        /* Barre d'actions */
        .actions-bar { display: flex; justify-content: flex-end; gap: 12px; flex-wrap: wrap; padding-top: 4px; }

        /* Colonne d'aide */
        .tips { padding: 20px 22px; }
        .tips h2 { margin: 0 0 14px; font-size: 15px; font-weight: 700; }
        .tips ul { list-style: none; margin: 0; padding: 0; display: grid; gap: 14px; }
        .tips li { display: flex; gap: 12px; font-size: 13px; color: var(--muted); }
        .tips li i { flex: none; width: 28px; height: 28px; display: grid; place-items: center; border-radius: 8px; background: var(--accent-soft); color: var(--accent-dark); font-size: 12px; }
        .tips li strong { display: block; color: var(--text); font-size: 13px; margin-bottom: 1px; }

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
        }
        @media (max-width: 640px) {
            .fgrid { grid-template-columns: 1fr; }
            .pwd-row { flex-wrap: wrap; }
            .pwd-wrap { flex: 1 1 100%; }
            .actions-bar .btn { flex: 1; }
        }
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
            <a href="ajouter_gestionnaire.php" class="nav-link active" aria-current="page">
                <i class="fa-solid fa-user-shield"></i> Créer un accès local
            </a>
            <a href="ajouter_direction.php" class="nav-link">
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
                    <h1>Créer un accès local</h1>
                    <p>Désignez un fonctionnaire comme administrateur local de son organisation.</p>
                </div>
                <a href="../views/admin_dashboard.php" class="btn btn-light">
                    <i class="fa-solid fa-arrow-left"></i> Retour au tableau de bord
                </a>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert" role="status">
                    <i class="fa-solid fa-circle-check"></i>
                    <span>L’accès local a bien été créé.</span>
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

            <div class="grid">

                <form action="../actions/traitement_gest.php" method="POST" id="gestForm" novalidate>
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

                    <section class="panel">
                        <div class="panel-head">
                            <div class="panel-ico warm"><i class="fa-solid fa-key"></i></div>
                            <div>
                                <h2 class="panel-title">Fonctionnaire et accès</h2>
                                <p class="panel-sub">Choisissez l’organisation, puis le fonctionnaire qui en sera l’administrateur local</p>
                            </div>
                        </div>
                        <div class="panel-body">
                            <div class="fgrid">
                                <div class="field full">
                                    <label for="id_org">Organisation <span class="req">*</span></label>
                                    <select class="control" id="id_org" name="id_org" required>
                                        <option value="">Choisir une organisation</option>
                                        <?php foreach ($directions as $dir): ?>
                                            <option value="<?= (int)$dir['id_org'] ?>">
                                                <?= e(!empty($dir['sigle_org']) ? '[' . $dir['sigle_org'] . '] ' : '') ?><?= e($dir['nom_org']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <span class="err" data-err="id_org"></span>
                                </div>

                                <div class="field full">
                                    <label for="id_fonc">Fonctionnaire de l’organisation <span class="req">*</span></label>
                                    <select class="control" id="id_fonc" name="id_fonc" required disabled>
                                        <option value="">Choisissez d’abord une organisation</option>
                                    </select>
                                    <span class="hint" id="fonctionnaireHint">Seuls les fonctionnaires actuellement affectés à cette organisation et ayant un e-mail valide sont proposés.</span>
                                    <span class="err" data-err="id_fonc"></span>
                                </div>

                                <div class="field full">
                                    <label for="iden_preview">E-mail et identifiant de connexion</label>
                                    <input class="control" type="text" id="iden_preview" value="Choisissez un fonctionnaire" readonly>
                                </div>

                                <div class="field full">
                                    <label for="mdp">Mot de passe provisoire <span class="req">*</span></label>
                                    <div class="pwd-row">
                                        <div class="pwd-wrap">
                                            <input class="control" type="password" id="mdp" name="mdp" placeholder="8 caractères minimum" autocomplete="new-password" required>
                                            <button type="button" class="eye" id="eyeBtn" aria-label="Afficher le mot de passe"><i class="fa-solid fa-eye"></i></button>
                                        </div>
                                        <button type="button" class="btn-tool" id="genBtn"><i class="fa-solid fa-wand-magic-sparkles"></i> Générer</button>
                                        <button type="button" class="btn-tool" id="copyBtn"><i class="fa-regular fa-copy"></i> <span>Copier</span></button>
                                    </div>
                                    <span class="hint">Notez-le avant de valider : il ne sera plus affiché ensuite.</span>
                                    <span class="err" data-err="mdp"></span>
                                </div>
                            </div>
                        </div>
                    </section>

                    <div class="actions-bar">
                        <a href="../views/admin_dashboard.php" class="btn btn-ghost">Annuler</a>
                        <button type="submit" name="valider" value="1" class="btn btn-primary" id="submitBtn">
                            <i class="fa-solid fa-circle-check ico"></i>
                            <span class="spin"></span>
                            <span>Créer l’accès</span>
                        </button>
                    </div>
                </form>

                <aside class="panel tips" aria-labelledby="tipsTitre">
                    <h2 id="tipsTitre">À savoir</h2>
                    <ul>
                        <li>
                            <i class="fa-solid fa-sitemap"></i>
                            <div><strong>Un accès lié au fonctionnaire</strong>Le compte conserve le matricule et le profil de la personne choisie.</div>
                        </li>
                        <li>
                            <i class="fa-solid fa-paper-plane"></i>
                            <div><strong>Transmettez le mot de passe avec soin</strong>Remettez-le en main propre ou par un canal privé, jamais dans un e-mail collectif.</div>
                        </li>
                        <li>
                            <i class="fa-solid fa-lock"></i>
                            <div><strong>Demandez de le changer</strong>Dès sa première connexion, la page « Sécurité et mot de passe » lui permet de choisir le sien.</div>
                        </li>
                    </ul>
                </aside>

            </div>
        </div>
    </main>
</div>

<script>
(function () {
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

    // ---------- Mot de passe : afficher, générer, copier ----------
    const pwd = document.getElementById('mdp');
    const eye = document.getElementById('eyeBtn');
    const gen = document.getElementById('genBtn');
    const copy = document.getElementById('copyBtn');

    function setVisible(show) {
        pwd.type = show ? 'text' : 'password';
        eye.setAttribute('aria-label', show ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
        eye.firstElementChild.className = show ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
    }
    eye.addEventListener('click', () => setVisible(pwd.type === 'password'));

    function randomInt(max) {
        const buf = new Uint32Array(1);
        const limit = Math.floor(0xFFFFFFFF / max) * max;
        do { crypto.getRandomValues(buf); } while (buf[0] >= limit);
        return buf[0] % max;
    }
    function generate() {
        const sets = ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghijkmnopqrstuvwxyz', '23456789', '!?#@%+='];
        const all = sets.join('');
        const out = sets.map(s => s[randomInt(s.length)]);          // au moins un de chaque famille
        while (out.length < 12) out.push(all[randomInt(all.length)]);
        for (let i = out.length - 1; i > 0; i--) { const j = randomInt(i + 1); [out[i], out[j]] = [out[j], out[i]]; }
        return out.join('');
    }
    gen.addEventListener('click', () => {
        pwd.value = generate();
        setVisible(true);
        clearError('mdp');
    });
    copy.addEventListener('click', async () => {
        if (!pwd.value) return;
        const label = copy.querySelector('span');
        try {
            await navigator.clipboard.writeText(pwd.value);
            label.textContent = 'Copié';
        } catch (e) {
            pwd.select();
            label.textContent = 'Sélectionné';
        }
        setTimeout(() => { label.textContent = 'Copier'; }, 1800);
    });

    // ---------- Validation ----------
    const form = document.getElementById('gestForm');
    const submitBtn = document.getElementById('submitBtn');
    const orgSelect = document.getElementById('id_org');
    const fonctionnaireSelect = document.getElementById('id_fonc');
    const loginPreview = document.getElementById('iden_preview');
    const fonctionnaireHint = document.getElementById('fonctionnaireHint');
    let submitting = false;
    let fonctionnaireRequest = 0;

    orgSelect.addEventListener('change', async () => {
        const request = ++fonctionnaireRequest;
        fonctionnaireSelect.disabled = true;
        fonctionnaireSelect.innerHTML = '<option value="">Chargement des fonctionnaires...</option>';
        loginPreview.value = 'Choisissez un fonctionnaire';
        fonctionnaireHint.textContent = 'Seuls les fonctionnaires actuellement affectés à cette organisation et ayant un e-mail valide sont proposés.';
        clearError('id_fonc');

        if (!orgSelect.value) {
            fonctionnaireSelect.innerHTML = '<option value="">Choisissez d’abord une organisation</option>';
            return;
        }

        try {
            const response = await fetch('get_fonctionnaires_org.php?id_org=' + encodeURIComponent(orgSelect.value), {
                credentials: 'same-origin',
                headers: { 'Accept': 'text/html' }
            });
            if (!response.ok) throw new Error('HTTP ' + response.status);
            const options = await response.text();
            if (request !== fonctionnaireRequest) return;
            fonctionnaireSelect.innerHTML = options;
            fonctionnaireSelect.disabled = fonctionnaireSelect.options.length < 2;
            if (fonctionnaireSelect.disabled) {
                fonctionnaireHint.textContent = fonctionnaireSelect.options[0]?.textContent || 'Aucun fonctionnaire disponible.';
            }
        } catch (error) {
            if (request !== fonctionnaireRequest) return;
            fonctionnaireSelect.innerHTML = '<option value="">Chargement impossible</option>';
            fonctionnaireHint.textContent = 'Impossible de charger la liste. Réessayez en sélectionnant l’organisation.';
        }
    });

    fonctionnaireSelect.addEventListener('change', () => {
        const option = fonctionnaireSelect.selectedOptions[0];
        loginPreview.value = option?.dataset.login || 'Choisissez un fonctionnaire';
        clearError('id_fonc');
    });

    function showError(name, msg) {
        const box = form.querySelector('[data-err="' + name + '"]');
        const input = form.elements[name];
        if (box) box.textContent = msg;
        if (input) input.classList.add('bad');
    }
    function clearError(name) {
        const box = form.querySelector('[data-err="' + name + '"]');
        const input = form.elements[name];
        if (box) box.textContent = '';
        if (input) input.classList.remove('bad');
    }
    ['id_org', 'id_fonc', 'mdp'].forEach(n => {
        const el = form.elements[n];
        el.addEventListener('input', () => clearError(n));
        el.addEventListener('change', () => clearError(n));
    });

    form.addEventListener('submit', (e) => {
        if (submitting) { e.preventDefault(); return; }

        const v = n => form.elements[n].value.trim();
        const errors = [];
        if (!form.elements.id_org.value) errors.push(['id_org', 'Choisissez une organisation.']);
        if (!form.elements.id_fonc.value) errors.push(['id_fonc', 'Choisissez un fonctionnaire de cette organisation.']);
        if (pwd.value.length < 8) errors.push(['mdp', 'Le mot de passe doit contenir au moins 8 caractères.']);

        if (errors.length) {
            e.preventDefault();
            errors.forEach(([n, m]) => showError(n, m));
            form.elements[errors[0][0]].focus();
            return;
        }
        // Le formulaire part vers traitement_gest.php ; on évite seulement le double envoi
        submitting = true;
        submitBtn.classList.add('loading');
    });
})();
</script>
</body>
</html>
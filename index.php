<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';

/*
 * RÉPERTOIRE PUBLIC
 * false (recommandé) : la liste des agents n'est visible que par le personnel habilité connecté.
 * true : la liste est visible par tout le monde, mais sans le matricule et sans lien vers les dossiers.
 * Avant de passer à true, vérifiez que la publication nominative des agents est autorisée par le cadre légal applicable.
 */
const REPERTOIRE_PUBLIC = false;

if (!function_exists('e')) {
    function e($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
}

// Session et droits
$role        = strtolower(trim($_SESSION['rol'] ?? ''));
$connecte    = isset($_SESSION['id_user']);
$id_fonc_ses = isset($_SESSION['id_fonc']) ? (int)$_SESSION['id_fonc'] : 0;
$user_org_id = isset($_SESSION['id_org']) ? (int)$_SESSION['id_org'] : 0;

$autorise = $connecte && in_array($role, ['admin', 'admin_local', 'gestionnaire', 'consultant'], true);
$voir_repertoire = $autorise || REPERTOIRE_PUBLIC;
$voir_details = $autorise;   // matricule et lien vers le dossier

// Lien « Mon espace »
if ($connecte) {
    if (in_array($role, ['admin', 'admin_local', 'gestionnaire'], true)) {
        $lien_espace = 'views/admin_dashboard.php';
    } elseif ($id_fonc_ses) {
        $lien_espace = 'views/profil_agent.php?id=' . $id_fonc_ses;
    } else {
        $lien_espace = 'saisie/login.php';
    }
}

// Statistiques globales (chiffres agrégés, sans donnée nominative)
$total_agents = (int)$db->query("SELECT COUNT(*) FROM FONCTIONNAIRE")->fetchColumn();
$total_orgs   = (int)$db->query("SELECT COUNT(*) FROM ORGANISATION")->fetchColumn();
$total_dipl   = (int)$db->query("SELECT COUNT(*) FROM DIPLOME")->fetchColumn();

// Répertoire : recherche et pagination côté serveur
$q        = isset($_GET['q']) ? trim($_GET['q']) : '';
$page     = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 15;
$agents = [];
$total_items = 0;
$total_pages = 1;
$start_item = $end_item = 0;

if ($voir_repertoire) {
    // Une seule ligne par agent : son affectation en cours la plus récente
    $from = "
        FROM FONCTIONNAIRE f
        LEFT JOIN RECEVOIR r ON r.id_rec = (
            SELECT r2.id_rec FROM RECEVOIR r2
            WHERE r2.id_fonc = f.id_fonc AND r2.date_fin IS NULL
            ORDER BY r2.date_debut DESC, r2.id_rec DESC LIMIT 1
        )
        LEFT JOIN AFFECTATION a ON r.id_affec = a.id_affec
        LEFT JOIN ORGANISATION o ON r.id_org = o.id_org
    ";
    $where = ["1=1"];
    $params = [];

    // Un administrateur local ne voit que les agents de sa propre organisation
    if ($autorise && $role === 'admin_local') {
        $where[] = "r.id_org = ?";
        $params[] = $user_org_id;
    }
    if ($q !== '') {
        $where[] = "(f.nom_fonc LIKE ? OR f.prenom_fonc LIKE ? OR f.matri LIKE ? OR a.gra_post LIKE ? OR o.nom_org LIKE ? OR o.sigle_org LIKE ?)";
        $t = '%' . $q . '%';
        for ($i = 0; $i < 6; $i++) { $params[] = $t; }
    }
    $where_sql = implode(' AND ', $where);

    $stmt = $db->prepare("SELECT COUNT(*) $from WHERE $where_sql");
    $stmt->execute($params);
    $total_items = (int)$stmt->fetchColumn();

    $total_pages = max(1, (int)ceil($total_items / $per_page));
    if ($page > $total_pages) { $page = $total_pages; }
    $offset = ($page - 1) * $per_page;
    $start_item = $total_items > 0 ? $offset + 1 : 0;
    $end_item = min($offset + $per_page, $total_items);

    $stmt = $db->prepare("
        SELECT f.id_fonc, f.matri, f.nom_fonc, f.prenom_fonc, a.gra_post, o.nom_org, o.sigle_org
        $from
        WHERE $where_sql
        ORDER BY f.nom_fonc ASC, f.prenom_fonc ASC
        LIMIT $per_page OFFSET $offset
    ");
    $stmt->execute($params);
    $agents = $stmt->fetchAll();
}

function url_page($p) {
    $params = $_GET;
    $params['page'] = $p;
    return '?' . http_build_query($params) . '#repertoire';
}
function initiale($s) {
    return mb_strtoupper(mb_substr(trim((string)$s), 0, 1, 'UTF-8'), 'UTF-8') ?: '?';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portail RH | Ministère des Affaires étrangères</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --ink: #0A3B2C;
            --ink-2: #11553F;
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
            --font-ui: 'Manrope', system-ui, -apple-system, 'Segoe UI', sans-serif;
            --font-display: 'Instrument Serif', Georgia, 'Times New Roman', serif;
        }

        *, *::before, *::after { box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; scroll-behavior: smooth; }

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
        button, input { font: inherit; color: inherit; }
        :focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; border-radius: 6px; }

        /* ---------- En-tête ---------- */
        .topbar {
            position: sticky; top: 0; z-index: 40;
            display: flex; align-items: center; justify-content: space-between; gap: 16px;
            padding: 12px 5%;
            background: rgba(242,246,243,.9);
            backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--line);
        }
        .brand { display: flex; align-items: center; gap: 12px; min-width: 0; }
        .brand-mark { width: 40px; height: 40px; flex: none; border-radius: 10px; background: var(--ink); color: #fff; display: grid; place-items: center; font-size: 18px; }
        .brand-name { font-weight: 700; font-size: 14px; line-height: 1.2; }
        .brand-sub  { font-size: 12px; color: var(--muted); }

        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            padding: 10px 18px; border-radius: 9px; border: 1px solid transparent;
            font-weight: 600; font-size: 13px; cursor: pointer;
            transition: background .15s, border-color .15s, transform .1s, color .15s;
        }
        .btn:active { transform: translateY(1px); }
        .btn-primary { background: var(--accent); color: #fff; box-shadow: 0 6px 16px rgba(247,127,0,.3); }
        .btn-primary:hover { background: #FF8F1F; }
        .btn-ghost { background: var(--surface); border-color: var(--line); color: var(--muted); }
        .btn-ghost:hover { color: var(--text); border-color: #CBD2DC; }
        .btn-light { background: rgba(255,255,255,.14); border-color: rgba(255,255,255,.28); color: #fff; }
        .btn-light:hover { background: rgba(255,255,255,.22); }

        /* ---------- Page ---------- */
        .page { max-width: 1240px; margin: 0 auto; padding: 32px 5% 24px; }

        .hero {
            position: relative; overflow: hidden;
            padding: 56px 48px 52px;
            border-radius: 20px; color: #fff;
            background:
                radial-gradient(640px 320px at 100% 0%, rgba(247,127,0,.42), transparent 70%),
                linear-gradient(120deg, #0A3B2C 0%, #11634A 100%);
        }
        .hero::before { content: ""; position: absolute; top: 0; left: 0; right: 0; height: 6px; background: linear-gradient(90deg, #F77F00 0 33.33%, #FFFFFF 33.33% 66.66%, #009E60 66.66% 100%); }
        .hero-tag { display: inline-flex; align-items: center; gap: 8px; padding: 5px 14px; border-radius: 999px; background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.25); font-size: 12px; font-weight: 600; }
        .hero-tag i { color: #FFB066; }
        .hero h1 { margin: 18px 0 12px; font-family: var(--font-display); font-weight: 400; font-size: clamp(36px, 6vw, 64px); line-height: 1.02; letter-spacing: -.015em; max-width: 16ch; }
        .hero p { margin: 0 0 28px; max-width: 58ch; color: rgba(255,255,255,.82); font-size: 16px; }
        .hero-actions { display: flex; gap: 12px; flex-wrap: wrap; }

        /* ---------- Statistiques ---------- */
        .kpis {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            background: var(--surface); border: 1px solid var(--line);
            border-radius: 14px; margin: 24px 0; overflow: hidden;
        }
        .kpi { display: flex; align-items: center; gap: 16px; padding: 22px 24px; border-right: 1px solid var(--line); }
        .kpi:last-child { border-right: 0; }
        .kpi:nth-child(1) { background: linear-gradient(135deg, #E6F6EE, #FFFFFF 75%); }
        .kpi:nth-child(2) { background: linear-gradient(135deg, #FFF0DC, #FFFFFF 75%); }
        .kpi:nth-child(3) { background: linear-gradient(135deg, #E6EEFF, #FFFFFF 75%); }
        .kpi-icon { width: 46px; height: 46px; flex: none; display: grid; place-items: center; border-radius: 12px; font-size: 18px; color: #fff; box-shadow: 0 6px 14px rgba(0,0,0,.12); }
        .i-green { background: var(--green); }
        .i-warm  { background: var(--accent); }
        .i-blue  { background: var(--blue); }
        .kpi-value { font-family: var(--font-display); font-size: 40px; line-height: 1; letter-spacing: -.01em; }
        .kpi-label { color: var(--muted); font-size: 13px; font-weight: 500; margin-top: 4px; }

        /* ---------- Répertoire ---------- */
        .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; scroll-margin-top: 90px; }
        .panel-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 22px 24px 4px; }
        .panel-title { margin: 0; font-size: 17px; font-weight: 700; display: flex; align-items: center; gap: 10px; }
        .panel-title i { color: var(--accent); }
        .panel-sub { margin: 4px 0 0; color: var(--muted); font-size: 13px; }
        .panel-sub strong { color: var(--text); }

        .search { display: flex; gap: 10px; flex-wrap: wrap; padding: 16px 24px 18px; }
        .field { position: relative; flex: 1 1 280px; min-width: 0; }
        .field > i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 13px; pointer-events: none; }
        .field input { width: 100%; height: 42px; padding: 0 14px 0 38px; background: var(--paper); border: 1px solid transparent; border-radius: 9px; transition: border-color .15s, background .15s; }
        .field input::placeholder { color: #8A95A3; }
        .field input:hover { border-color: var(--line); }
        .field input:focus { outline: none; background: var(--surface); border-color: var(--green); box-shadow: 0 0 0 3px rgba(14,159,104,.15); }

        .table-wrap { overflow-x: auto; border-top: 1px solid var(--line); }
        table { width: 100%; border-collapse: collapse; }
        thead th { text-align: left; white-space: nowrap; padding: 12px 16px; font-size: 12px; font-weight: 600; color: var(--ink-2); background: #EEF5F0; border-bottom: 1px solid var(--line); }
        thead th:first-child, tbody td:first-child { padding-left: 24px; }
        thead th:last-child,  tbody td:last-child  { padding-right: 24px; }
        tbody td { padding: 13px 16px; border-bottom: 1px solid var(--line-soft); vertical-align: middle; }
        tbody tr:last-child td { border-bottom: 0; }
        tbody tr:hover { background: #F3FAF6; }

        .matricule { display: inline-block; padding: 3px 9px; background: var(--paper); border: 1px solid var(--line); border-radius: 6px; font-weight: 700; font-size: 12px; letter-spacing: .4px; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .person { display: flex; align-items: center; gap: 12px; min-width: 220px; }
        .avatar { flex: none; width: 36px; height: 36px; border-radius: 50%; display: grid; place-items: center; font-size: 12px; font-weight: 700; background: var(--green-soft); color: var(--green); }
        .person-name { font-weight: 700; line-height: 1.25; }
        .person-first { color: var(--muted); font-weight: 500; }
        .org-name { font-weight: 500; }
        .org-sigle { display: block; font-size: 12px; color: var(--muted); }
        .pill { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap; background: var(--accent-soft); color: var(--accent-dark); }
        .pill.muted { background: var(--paper); color: var(--muted); font-weight: 500; }
        .act { width: 32px; height: 32px; display: inline-grid; place-items: center; border-radius: 8px; color: var(--muted); font-size: 13px; transition: background .15s, color .15s; }
        .act:hover { background: var(--paper); color: var(--text); }
        td.right { text-align: right; }

        .empty { text-align: center; padding: 48px 24px; color: var(--muted); }
        .empty .empty-ico { width: 64px; height: 64px; margin: 0 auto 16px; display: grid; place-items: center; border-radius: 50%; background: var(--paper); color: #B5BEC9; font-size: 26px; }
        .empty strong { display: block; color: var(--text); font-size: 15px; margin-bottom: 4px; }
        .empty p { margin: 0 auto 18px; max-width: 52ch; }
        .panel > .empty { border-top: 1px solid var(--line); }

        .pager { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 14px 24px; border-top: 1px solid var(--line); }
        .pager-info { color: var(--muted); font-size: 13px; }
        .pager ul { display: flex; gap: 4px; list-style: none; margin: 0; padding: 0; flex-wrap: wrap; align-items: center; }
        .page-btn { display: inline-grid; place-items: center; min-width: 34px; height: 34px; padding: 0 8px; border-radius: 8px; font-weight: 600; font-size: 13px; color: var(--muted); transition: background .15s, color .15s; }
        .page-btn:hover { background: var(--paper); color: var(--text); }
        .page-btn.active { background: var(--accent); color: #fff; }
        .page-btn.disabled { opacity: .35; pointer-events: none; }
        .dots { display: inline-grid; place-items: center; min-width: 28px; color: var(--muted); }

        .footer { margin-top: 40px; padding: 24px 5%; border-top: 1px solid var(--line); background: var(--surface); text-align: center; color: var(--muted); font-size: 13px; }

        @media (max-width: 720px) {
            .hero { padding: 40px 24px 36px; border-radius: 16px; }
            .hero p { font-size: 15px; }
            .kpi { border-right: 0; border-bottom: 1px solid var(--line); }
            .kpi:last-child { border-bottom: 0; }
            .brand-sub { display: none; }
            .panel-head, .search, .pager { padding-left: 16px; padding-right: 16px; }
            thead th:first-child, tbody td:first-child { padding-left: 16px; }
            thead th:last-child,  tbody td:last-child  { padding-right: 16px; }
        }
        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }
            *, *::before, *::after { transition: none !important; animation: none !important; }
        }
    </style>
</head>
<body>

<header class="topbar">
    <div class="brand">
        <div class="brand-mark"><i class="fa-solid fa-building-columns"></i></div>
        <div>
            <div class="brand-name">Ministère des Affaires étrangères</div>
            <div class="brand-sub">République de Côte d’Ivoire</div>
        </div>
    </div>
    <div>
        <?php if ($connecte): ?>
            <a href="<?= e($lien_espace) ?>" class="btn btn-primary"><i class="fa-solid fa-gauge-high"></i> Mon espace</a>
        <?php else: ?>
            <a href="saisie/login.php" class="btn btn-primary"><i class="fa-solid fa-right-to-bracket"></i> Connexion</a>
        <?php endif; ?>
    </div>
</header>

<main class="page">

    <section class="hero">
        <span class="hero-tag"><i class="fa-solid fa-shield-halved"></i> Portail officiel des ressources humaines</span>
        <h1>Le personnel du ministère, en un seul endroit</h1>
        <p>Dossiers des agents, archives numériques et suivi des affectations : un accès unique, réservé au personnel autorisé.</p>
        <div class="hero-actions">
            <?php if ($connecte): ?>
                <a href="<?= e($lien_espace) ?>" class="btn btn-primary"><i class="fa-solid fa-gauge-high"></i> Accéder à mon espace</a>
            <?php else: ?>
                <a href="saisie/login.php" class="btn btn-primary"><i class="fa-solid fa-right-to-bracket"></i> Se connecter</a>
            <?php endif; ?>
            <?php if ($voir_repertoire): ?>
                <a href="#repertoire" class="btn btn-light"><i class="fa-solid fa-list"></i> Voir le répertoire</a>
            <?php endif; ?>
        </div>
    </section>

    <section class="kpis" aria-label="Chiffres clés">
        <div class="kpi">
            <div class="kpi-icon i-green"><i class="fa-solid fa-users"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($total_agents, 0, ',', ' ') ?></div>
                <div class="kpi-label">Agents</div>
            </div>
        </div>
        <div class="kpi">
            <div class="kpi-icon i-warm"><i class="fa-solid fa-sitemap"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($total_orgs, 0, ',', ' ') ?></div>
                <div class="kpi-label">Structures et organisations</div>
            </div>
        </div>
        <div class="kpi">
            <div class="kpi-icon i-blue"><i class="fa-solid fa-graduation-cap"></i></div>
            <div>
                <div class="kpi-value"><?= number_format($total_dipl, 0, ',', ' ') ?></div>
                <div class="kpi-label">Diplômes enregistrés</div>
            </div>
        </div>
    </section>

    <section class="panel" id="repertoire" aria-labelledby="repTitre">

        <?php if ($voir_repertoire): ?>

            <div class="panel-head">
                <div>
                    <h2 class="panel-title" id="repTitre"><i class="fa-solid fa-address-book"></i> Répertoire des agents</h2>
                    <p class="panel-sub">
                        <?php if ($total_items > 0): ?>
                            <strong><?= $start_item ?></strong> à <strong><?= $end_item ?></strong> sur <strong><?= number_format($total_items, 0, ',', ' ') ?></strong>
                        <?php else: ?>
                            Aucun agent à afficher
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <form class="search" method="GET" action="#repertoire" role="search">
                <div class="field">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= $voir_details ? 'Nom, prénom, matricule, grade ou structure' : 'Nom, prénom, grade ou structure' ?>" aria-label="Rechercher un agent" autocomplete="off">
                </div>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i> Rechercher</button>
                <?php if ($q !== ''): ?>
                    <a href="index.php#repertoire" class="btn btn-ghost" aria-label="Effacer la recherche"><i class="fa-solid fa-xmark"></i></a>
                <?php endif; ?>
            </form>

            <?php if (!empty($agents)): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <?php if ($voir_details): ?><th>Matricule</th><?php endif; ?>
                            <th>Agent</th>
                            <th>Structure</th>
                            <th>Grade</th>
                            <?php if ($voir_details): ?><th style="text-align:right;">Dossier</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($agents as $row): ?>
                        <tr>
                            <?php if ($voir_details): ?>
                                <td><span class="matricule"><?= e($row['matri']) ?></span></td>
                            <?php endif; ?>
                            <td>
                                <div class="person">
                                    <div class="avatar"><?= e(initiale($row['nom_fonc']) . initiale($row['prenom_fonc'])) ?></div>
                                    <div>
                                        <div class="person-name"><?= e(mb_strtoupper((string)$row['nom_fonc'], 'UTF-8')) ?> <span class="person-first"><?= e($row['prenom_fonc']) ?></span></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($row['nom_org'])): ?>
                                    <span class="org-name"><?= e($row['nom_org']) ?></span>
                                    <?php if (!empty($row['sigle_org'])): ?><span class="org-sigle"><?= e($row['sigle_org']) ?></span><?php endif; ?>
                                <?php else: ?>
                                    <span class="pill muted">Non affecté</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="pill"><?= e($row['gra_post'] ?? 'Agent de l’État') ?></span></td>
                            <?php if ($voir_details): ?>
                                <td class="right">
                                    <a href="views/profil_agent.php?id=<?= (int)$row['id_fonc'] ?>" class="act" title="Voir le dossier" aria-label="Voir le dossier">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
            <nav class="pager" aria-label="Pagination">
                <div class="pager-info">Page <strong><?= $page ?></strong> sur <strong><?= $total_pages ?></strong></div>
                <ul>
                    <li><a href="<?= $page > 1 ? e(url_page($page - 1)) : '#' ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>" aria-label="Page précédente" <?= $page <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>><i class="fa-solid fa-chevron-left"></i></a></li>
                    <?php
                    $debut = max(1, $page - 2);
                    $fin   = min($total_pages, $page + 2);
                    if ($debut > 1) {
                        echo '<li><a href="' . e(url_page(1)) . '" class="page-btn">1</a></li>';
                        if ($debut > 2) echo '<li><span class="dots">…</span></li>';
                    }
                    for ($i = $debut; $i <= $fin; $i++) {
                        echo '<li><a href="' . e(url_page($i)) . '" class="page-btn ' . ($i == $page ? 'active' : '') . '"' . ($i == $page ? ' aria-current="page"' : '') . '>' . $i . '</a></li>';
                    }
                    if ($fin < $total_pages) {
                        if ($fin < $total_pages - 1) echo '<li><span class="dots">…</span></li>';
                        echo '<li><a href="' . e(url_page($total_pages)) . '" class="page-btn">' . $total_pages . '</a></li>';
                    }
                    ?>
                    <li><a href="<?= $page < $total_pages ? e(url_page($page + 1)) : '#' ?>" class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>" aria-label="Page suivante" <?= $page >= $total_pages ? 'aria-disabled="true" tabindex="-1"' : '' ?>><i class="fa-solid fa-chevron-right"></i></a></li>
                </ul>
            </nav>
            <?php endif; ?>

            <?php else: ?>
                <div class="empty">
                    <div class="empty-ico"><i class="fa-solid fa-magnifying-glass"></i></div>
                    <strong>Aucun agent trouvé</strong>
                    <p>Aucun agent ne correspond à votre recherche. Essayez un autre nom ou une autre structure.</p>
                    <?php if ($q !== ''): ?><a href="index.php#repertoire" class="btn btn-ghost">Effacer la recherche</a><?php endif; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>

            <div class="panel-head">
                <h2 class="panel-title" id="repTitre"><i class="fa-solid fa-address-book"></i> Répertoire des agents</h2>
            </div>
            <div class="empty">
                <div class="empty-ico"><i class="fa-solid fa-lock"></i></div>
                <strong>Accès réservé au personnel habilité</strong>
                <p>Le répertoire nominatif des agents n’est consultable qu’après connexion. Les données personnelles des fonctionnaires ne sont pas publiques.</p>
                <?php if ($connecte): ?>
                    <a href="<?= e($lien_espace) ?>" class="btn btn-primary"><i class="fa-solid fa-gauge-high"></i> Accéder à mon espace</a>
                <?php else: ?>
                    <a href="saisie/login.php" class="btn btn-primary"><i class="fa-solid fa-right-to-bracket"></i> Se connecter</a>
                <?php endif; ?>
            </div>

        <?php endif; ?>
    </section>

</main>

<footer class="footer">
    <i class="fa-regular fa-copyright"></i> 2026 République de Côte d’Ivoire · Ministère des Affaires étrangères
</footer>

</body>
</html>
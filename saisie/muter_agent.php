<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config.php';

// Helpers d'affichage
if (!function_exists('e')) {
    function e($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('fmt_date')) {
    function fmt_date($d) {
        $t = $d ? strtotime((string)$d) : false;
        return $t ? date('d/m/Y', $t) : null;
    }
}

// Session et droits
$role        = strtolower(trim($_SESSION['rol'] ?? ''));
$id_session  = isset($_SESSION['id_fonc']) ? (int)$_SESSION['id_fonc'] : 0;
$user_org_id = isset($_SESSION['id_org']) ? (int)$_SESSION['id_org'] : 0;
$est_admin   = in_array($role, ['admin', 'admin_local'], true);

// Il faut être connecté
if ($role === '' && !$id_session) {
    header('Location: ../saisie/login.php');
    exit();
}

// 1. Agent à muter ('id' ou 'id_agent')
$id_a_muter = (int)($_GET['id'] ?? $_GET['id_agent'] ?? 0);
if (!$est_admin && (!$id_session || $id_a_muter !== $id_session)) {
    header('Location: ' . ($id_session ? '../views/profil_agent.php?id=' . $id_session : '../saisie/login.php'));
    exit();
}
if (!$id_a_muter) {
    header('Location: ../views/liste_fonctionnaires.php');
    exit();
}

// 2. Agent et affectation en cours (une seule ligne : celle qui n'est pas terminée)
$stmt = $db->prepare("
    SELECT f.id_fonc, f.matri, f.nom_fonc, f.prenom_fonc,
           a.gra_post, a.tit_post, a.vil_affec, r.ville_affec, r.date_debut,
           r.id_org AS id_org_actuelle, o.nom_org
    FROM FONCTIONNAIRE f
    LEFT JOIN RECEVOIR r ON f.id_fonc = r.id_fonc AND r.date_fin IS NULL
    LEFT JOIN AFFECTATION a ON r.id_affec = a.id_affec
    LEFT JOIN ORGANISATION o ON r.id_org = o.id_org
    WHERE f.id_fonc = ?
    ORDER BY r.date_debut DESC
    LIMIT 1
");
$stmt->execute([$id_a_muter]);
$agent = $stmt->fetch();

// Un administrateur local ne mute que les agents de sa propre organisation
$acces_refuse = false;
if ($agent && $role === 'admin_local') {
    if (!$user_org_id || (int)($agent['id_org_actuelle'] ?? 0) !== $user_org_id) {
        $acces_refuse = true;
    }
}

$retour_url = $agent
    ? '../views/profil_agent.php?id=' . (int)$agent['id_fonc'] . '&section=mutations'
    : '../views/liste_fonctionnaires.php';

$nom_agent = $agent ? trim(($agent['prenom_fonc'] ?? '') . ' ' . mb_strtoupper((string)($agent['nom_fonc'] ?? ''), 'UTF-8')) : '';

// 3. Organisations de destination et villes déjà utilisées
$organisations = [];
$villes = [];
if ($agent && !$acces_refuse) {
    $organisations = $db->query("SELECT id_org, nom_org, sigle_org FROM ORGANISATION ORDER BY nom_org ASC")->fetchAll();
    try {
        $villes = $db->query("SELECT DISTINCT vil_affec FROM AFFECTATION WHERE vil_affec IS NOT NULL AND vil_affec != '' ORDER BY vil_affec ASC LIMIT 200")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $ex) {
        error_log("muter_agent.php (villes) : " . $ex->getMessage());
    }
}

// Date d'effet : pas avant le début de l'affectation actuelle
$min_date = '';
if ($agent && !empty($agent['date_debut']) && strtotime($agent['date_debut'])) {
    $min_date = date('Y-m-d', strtotime($agent['date_debut']));
}
$aujourdhui = date('Y-m-d');
$valeur_date = ($min_date && $min_date > $aujourdhui) ? $min_date : $aujourdhui;

// Jeton CSRF (à vérifier dans traitement_mutation.php avec hash_equals)
if (empty($_SESSION['csrf_mut'])) {
    $_SESSION['csrf_mut'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_mut'];

$ville_actuelle = $agent ? (!empty($agent['ville_affec']) ? $agent['ville_affec'] : ($agent['vil_affec'] ?? null)) : null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SICP | Mutation - <?= e($agent['nom_fonc'] ?? 'Agent') ?></title>
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
            --red-soft: #FBE9E7;
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
        button, input, select, textarea { font: inherit; color: inherit; }
        :focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; border-radius: 6px; }

        .topbar {
            position: sticky; top: 0; z-index: 40;
            display: flex; align-items: center; justify-content: space-between; gap: 16px;
            padding: 12px 32px;
            background: rgba(242,246,243,.88);
            backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--line);
        }
        .brand { display: flex; align-items: center; gap: 12px; min-width: 0; }
        .brand-mark { width: 36px; height: 36px; flex: none; border-radius: 10px; background: var(--ink); color: #fff; display: grid; place-items: center; font-size: 16px; }
        .brand-name { font-weight: 700; font-size: 14px; line-height: 1.2; }
        .brand-sub  { font-size: 12px; color: var(--muted); }
        .topbar-right { display: flex; align-items: center; gap: 10px; }
        .icon-btn { width: 38px; height: 38px; display: inline-grid; place-items: center; border: 1px solid var(--line); background: var(--surface); border-radius: 9px; color: var(--muted); transition: color .15s, border-color .15s, background .15s; }
        .icon-btn.danger:hover { color: var(--red); border-color: #F0C4BF; background: var(--red-soft); }

        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            padding: 10px 16px; border-radius: 9px; border: 1px solid transparent;
            font-weight: 600; font-size: 13px; cursor: pointer;
            transition: background .15s, border-color .15s, transform .1s, color .15s;
        }
        .btn:active { transform: translateY(1px); }
        .btn-primary { background: var(--accent); color: #fff; box-shadow: 0 6px 16px rgba(247,127,0,.3); }
        .btn-primary:hover { background: #FF8F1F; }
        .btn-ghost { background: var(--surface); border-color: var(--line); color: var(--muted); }
        .btn-ghost:hover { color: var(--text); border-color: #CBD2DC; }
        .btn.loading { pointer-events: none; opacity: .8; }
        .btn .spin { display: none; width: 16px; height: 16px; border: 2px solid rgba(255,255,255,.4); border-top-color: #fff; border-radius: 50%; animation: spin .7s linear infinite; }
        .btn.loading .spin { display: inline-block; }
        .btn.loading .ico { display: none; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .page { max-width: 760px; margin: 0 auto; padding: 32px 24px 40px; }

        .hero {
            position: relative; overflow: hidden;
            display: flex; align-items: center; gap: 18px;
            margin-bottom: 24px; padding: 28px 28px 24px;
            border-radius: 16px; color: #fff;
            background:
                radial-gradient(420px 200px at 100% 0%, rgba(247,127,0,.38), transparent 70%),
                linear-gradient(120deg, #0A3B2C 0%, #11634A 100%);
        }
        .hero::before { content: ""; position: absolute; top: 0; left: 0; right: 0; height: 5px; background: linear-gradient(90deg, #F77F00 0 33.33%, #FFFFFF 33.33% 66.66%, #009E60 66.66% 100%); }
        .hero-ico { flex: none; width: 56px; height: 56px; border-radius: 14px; display: grid; place-items: center; background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.25); font-size: 22px; color: #FFB066; }
        .hero h1 { margin: 0; font-family: var(--font-display); font-weight: 400; font-size: clamp(26px, 4vw, 36px); line-height: 1.05; letter-spacing: -.01em; }
        .hero p { margin: 6px 0 0; color: rgba(255,255,255,.82); font-size: 14px; }
        .hero p strong { color: #fff; }

        .alert { display: flex; align-items: flex-start; gap: 12px; padding: 12px 16px; margin-bottom: 20px; border-radius: 10px; background: var(--red-soft); color: #8E2A22; border: 1px solid #F0C4BF; font-weight: 500; }
        .alert i { margin-top: 3px; }

        .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; margin-bottom: 20px; }
        .panel-head { display: flex; align-items: center; gap: 12px; padding: 18px 24px; border-bottom: 1px solid var(--line-soft); }
        .panel-ico { width: 34px; height: 34px; flex: none; display: grid; place-items: center; border-radius: 9px; font-size: 14px; background: var(--blue-soft); color: var(--blue); }
        .panel-ico.warm { background: var(--accent-soft); color: var(--accent-dark); }
        .panel-title { margin: 0; font-size: 15px; font-weight: 700; }
        .panel-sub { margin: 0; color: var(--muted); font-size: 12px; }
        .panel-body { padding: 22px 24px 24px; }

        /* Situation actuelle */
        .facts { display: grid; grid-template-columns: 1fr 1fr; margin: 0; }
        .fact { padding: 16px 24px; border-bottom: 1px solid var(--line-soft); }
        .fact:nth-last-child(-n+2) { border-bottom: 0; }
        .fact dt { margin: 0 0 2px; color: var(--muted); font-size: 12px; font-weight: 600; }
        .fact dd { margin: 0; font-weight: 600; word-break: break-word; }
        .fact dd small { display: block; color: var(--muted); font-weight: 500; font-size: 12px; }
        .na { color: var(--muted); font-weight: 500; font-style: italic; }

        .arrow { display: grid; place-items: center; margin: -6px 0 14px; color: var(--accent); font-size: 18px; }

        /* Formulaire */
        .field { margin-bottom: 20px; }
        .field label { display: block; margin-bottom: 6px; font-size: 13px; font-weight: 600; }
        .req { color: var(--accent-dark); margin-left: 2px; }
        .opt { color: var(--muted); font-weight: 500; font-size: 12px; margin-left: 4px; }
        .hint { margin-top: 6px; font-size: 12px; color: var(--muted); display: flex; justify-content: space-between; gap: 12px; }
        .err { margin-top: 6px; font-size: 12px; font-weight: 600; color: var(--red); }
        .err:empty { display: none; }
        .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0 20px; }

        .control {
            width: 100%; height: 44px; padding: 0 14px;
            background: var(--surface); border: 1px solid #D5DBE3; border-radius: 9px;
            transition: border-color .15s, box-shadow .15s;
        }
        textarea.control { height: auto; min-height: 96px; padding: 12px 14px; resize: vertical; line-height: 1.5; }
        select.control { appearance: none; cursor: pointer; padding-right: 36px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='none' stroke='%236A7686' stroke-width='1.6' stroke-linecap='round' d='M2.5 4.5 6 8l3.5-3.5'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 14px center; }
        .control::placeholder { color: #9AA5B3; }
        .control:hover { border-color: #B9C2CE; }
        .control:focus { outline: none; border-color: var(--green); box-shadow: 0 0 0 3px rgba(14,159,104,.15); }
        .control.bad { border-color: var(--red); }
        .control:disabled { background-color: var(--paper); color: var(--muted); cursor: not-allowed; border-color: var(--line); }
        select.control:disabled { background-image: none; padding-right: 14px; }

        .actions { display: flex; justify-content: flex-end; gap: 12px; flex-wrap: wrap; margin-top: 8px; padding-top: 20px; border-top: 1px solid var(--line-soft); }

        .empty { text-align: center; padding: 48px 24px; color: var(--muted); }
        .empty .empty-ico { width: 64px; height: 64px; margin: 0 auto 16px; display: grid; place-items: center; border-radius: 50%; background: var(--paper); color: #B5BEC9; font-size: 26px; }
        .empty strong { display: block; color: var(--text); font-size: 15px; margin-bottom: 4px; }
        .empty p { margin: 0 auto 18px; max-width: 46ch; }

        @media (max-width: 720px) {
            .topbar { padding: 12px 16px; }
            .brand-sub { display: none; }
            .page { padding: 20px 16px 32px; }
            .hero { padding: 24px 20px 20px; }
            .panel-head, .panel-body, .fact { padding-left: 16px; padding-right: 16px; }
            .facts, .row2 { grid-template-columns: 1fr; }
            .fact:nth-last-child(-n+2) { border-bottom: 1px solid var(--line-soft); }
            .fact:last-child { border-bottom: 0; }
            .actions .btn { flex: 1; }
        }
        @media (prefers-reduced-motion: reduce) {
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
            <div class="brand-sub">Ressources humaines</div>
        </div>
    </div>
    <div class="topbar-right">
        <a href="<?= e($retour_url) ?>" class="btn btn-ghost"><i class="fa-solid fa-arrow-left"></i> Retour au dossier</a>
        <a href="../logout.php" class="icon-btn danger" title="Se déconnecter" aria-label="Se déconnecter"><i class="fa-solid fa-power-off"></i></a>
    </div>
</header>

<main class="page">

    <div class="hero">
        <div class="hero-ico"><i class="fa-solid fa-route"></i></div>
        <div>
            <h1>Ordre de mutation</h1>
            <p>
                <?php if ($agent && !$acces_refuse): ?>
                    <strong><?= e($nom_agent) ?></strong> · Matricule <?= e($agent['matri'] ?? 'N/A') ?>
                <?php else: ?>
                    Changement d’affectation d’un agent
                <?php endif; ?>
            </p>
        </div>
    </div>

    <?php if (!$agent): ?>

        <section class="panel">
            <div class="empty">
                <div class="empty-ico"><i class="fa-solid fa-user-slash"></i></div>
                <strong>Agent introuvable</strong>
                <p>Aucun agent ne correspond à cette demande. Ouvrez cette page depuis le dossier de l’agent.</p>
                <a href="<?= e($retour_url) ?>" class="btn btn-ghost"><i class="fa-solid fa-arrow-left"></i> Retour à la liste</a>
            </div>
        </section>

    <?php elseif ($acces_refuse): ?>

        <section class="panel">
            <div class="empty">
                <div class="empty-ico"><i class="fa-solid fa-lock"></i></div>
                <strong>Mutation non autorisée</strong>
                <p>Vous ne pouvez muter que les agents de votre propre organisation.</p>
                <a href="../views/liste_fonctionnaires.php" class="btn btn-ghost"><i class="fa-solid fa-arrow-left"></i> Retour à la liste</a>
            </div>
        </section>

    <?php else: ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert" role="alert">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= e($_GET['error']) ?></span>
            </div>
        <?php endif; ?>

        <!-- Situation actuelle -->
        <section class="panel" aria-labelledby="actuTitre">
            <div class="panel-head">
                <div class="panel-ico"><i class="fa-solid fa-circle-info"></i></div>
                <div>
                    <h2 class="panel-title" id="actuTitre">Situation actuelle</h2>
                    <p class="panel-sub">Affectation en cours de l’agent</p>
                </div>
            </div>
            <dl class="facts">
                <div class="fact">
                    <dt>Organisation</dt>
                    <dd><?= !empty($agent['nom_org']) ? e($agent['nom_org']) : '<span class="na">Aucune affectation en cours</span>' ?></dd>
                </div>
                <div class="fact">
                    <dt>Poste et grade</dt>
                    <dd>
                        <?= !empty($agent['gra_post']) ? 'Grade ' . e($agent['gra_post']) : '<span class="na">Non défini</span>' ?>
                        <?php if (!empty($agent['tit_post'])): ?><small><?= e($agent['tit_post']) ?></small><?php endif; ?>
                    </dd>
                </div>
                <div class="fact">
                    <dt>Ville</dt>
                    <dd><?= !empty($ville_actuelle) ? e($ville_actuelle) : '<span class="na">Non précisée</span>' ?></dd>
                </div>
                <div class="fact">
                    <dt>En poste depuis</dt>
                    <dd><?= ($d = fmt_date($agent['date_debut'] ?? null)) ? e($d) : '<span class="na">Non renseignée</span>' ?></dd>
                </div>
            </dl>
        </section>

        <div class="arrow" aria-hidden="true"><i class="fa-solid fa-arrow-down"></i></div>

        <!-- Nouvelle affectation -->
        <section class="panel" aria-labelledby="nouvTitre">
            <div class="panel-head">
                <div class="panel-ico warm"><i class="fa-solid fa-paper-plane"></i></div>
                <div>
                    <h2 class="panel-title" id="nouvTitre">Nouvelle affectation</h2>
                    <p class="panel-sub">L’affectation actuelle sera clôturée à la date d’effet</p>
                </div>
            </div>
            <div class="panel-body">
                <form action="../actions/traitement_mutation.php" method="POST" id="mutForm" novalidate>
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="id_fonc" value="<?= (int)$agent['id_fonc'] ?>">

                    <div class="field">
                        <label for="org_select">Organisation de destination <span class="req">*</span></label>
                        <select class="control" name="id_org_dest" id="org_select" required>
                            <option value="">Choisir la nouvelle organisation</option>
                            <?php foreach ($organisations as $org): ?>
                                <option value="<?= (int)$org['id_org'] ?>">
                                    <?= e(!empty($org['sigle_org']) ? '[' . $org['sigle_org'] . '] ' : '') ?><?= e($org['nom_org']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="err" data-err="id_org_dest"></div>
                    </div>

                    <div class="field">
                        <label for="grade_select">Nouveau grade ou poste <span class="req">*</span></label>
                        <select class="control" name="id_affec_dest" id="grade_select" required disabled>
                            <option value="">Choisissez d’abord une organisation</option>
                        </select>
                        <div class="err" data-err="id_affec_dest"></div>
                    </div>

                    <div class="row2">
                        <div class="field">
                            <label for="ville_affec">Ville d’affectation <span class="req">*</span></label>
                            <input class="control" type="text" name="ville_affec" id="ville_affec" list="villes-list" maxlength="80" placeholder="Ex. : Abidjan, Bouaké" autocomplete="off" required>
                            <datalist id="villes-list">
                                <?php foreach ($villes as $v): ?><option value="<?= e($v) ?>"></option><?php endforeach; ?>
                            </datalist>
                            <div class="err" data-err="ville_affec"></div>
                        </div>
                        <div class="field">
                            <label for="date_effet">Date d’effet <span class="req">*</span></label>
                            <input class="control" type="date" name="date_effet" id="date_effet" value="<?= e($valeur_date) ?>" <?= $min_date ? 'min="' . e($min_date) . '"' : '' ?> required>
                            <div class="err" data-err="date_effet"></div>
                        </div>
                    </div>

                    <div class="field">
                        <label for="motif">Motif de la mutation <span class="opt">facultatif</span></label>
                        <textarea class="control" name="motif" id="motif" rows="3" maxlength="255" placeholder="Ex. : rapprochement familial, nécessité de service, promotion"></textarea>
                        <div class="hint"><span>Visible dans le parcours de l’agent.</span><span id="motifCount">0 / 255</span></div>
                    </div>

                    <div class="actions">
                        <a href="<?= e($retour_url) ?>" class="btn btn-ghost">Annuler</a>
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="fa-solid fa-circle-check ico"></i>
                            <span class="spin"></span>
                            <span>Valider la mutation</span>
                        </button>
                    </div>
                </form>
            </div>
        </section>

    <?php endif; ?>

</main>

<script>
(function () {
    const form = document.getElementById('mutForm');
    if (!form) return;

    const orgSelect = document.getElementById('org_select');
    const gradeSelect = document.getElementById('grade_select');
    const ville = document.getElementById('ville_affec');
    const dateEffet = document.getElementById('date_effet');
    const motif = document.getElementById('motif');
    const motifCount = document.getElementById('motifCount');
    const submitBtn = document.getElementById('submitBtn');
    const MIN_DATE = <?= json_encode($min_date) ?>;
    const AGENT = <?= json_encode($nom_agent, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const ORG_ACTUELLE = <?= (int)($agent['id_org_actuelle'] ?? 0) ?>;
    let submitting = false;
    let gradeRequest = 0;

    // ---------- Grades selon l'organisation de destination ----------
    function setGradeMessage(text, disabled) {
        gradeSelect.innerHTML = '';
        const o = document.createElement('option');
        o.value = ''; o.textContent = text;
        gradeSelect.appendChild(o);
        gradeSelect.disabled = !!disabled;
    }

    function chargerGrades(idOrg) {
        const token = ++gradeRequest;
        if (!idOrg) { setGradeMessage('Choisissez d’abord une organisation', true); return; }
        setGradeMessage('Chargement…', true);

        fetch('get_grades.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id_org=' + encodeURIComponent(idOrg),
            credentials: 'same-origin'
        })
        .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.text(); })
        .then(html => {
            if (token !== gradeRequest) return;
            // On ne garde que les <option> et on recrée les éléments : aucun HTML du serveur n'est injecté tel quel
            const doc = new DOMParser().parseFromString('<select>' + html + '</select>', 'text/html');
            const opts = Array.from(doc.querySelectorAll('option'));
            const real = opts.filter(o => o.value !== '');
            const placeholder = opts.find(o => o.value === '');

            gradeSelect.innerHTML = '';
            const first = document.createElement('option');
            first.value = '';
            first.textContent = real.length ? (placeholder ? placeholder.textContent.trim() : 'Choisir un grade') : 'Aucun grade disponible pour cette organisation';
            gradeSelect.appendChild(first);
            real.forEach(o => {
                const n = document.createElement('option');
                n.value = o.value;
                n.textContent = o.textContent.trim();
                gradeSelect.appendChild(n);
            });
            gradeSelect.disabled = real.length === 0;
            clearError('id_affec_dest');
        })
        .catch(() => {
            if (token !== gradeRequest) return;
            setGradeMessage('Chargement impossible. Changez d’organisation pour réessayer.', true);
        });
    }
    orgSelect.addEventListener('change', () => chargerGrades(orgSelect.value));

    // ---------- Compteur du motif ----------
    function majCompteur() { motifCount.textContent = motif.value.length + ' / 255'; }
    motif.addEventListener('input', majCompteur);
    majCompteur();

    // ---------- Validation ----------
    function showError(name, msg) {
        const box = form.querySelector('[data-err="' + name + '"]');
        if (box) box.textContent = msg;
        form.elements[name].classList.add('bad');
    }
    function clearError(name) {
        const box = form.querySelector('[data-err="' + name + '"]');
        if (box) box.textContent = '';
        form.elements[name].classList.remove('bad');
    }
    ['id_org_dest', 'id_affec_dest', 'ville_affec', 'date_effet'].forEach(n => {
        form.elements[n].addEventListener('input', () => clearError(n));
        form.elements[n].addEventListener('change', () => clearError(n));
    });

    form.addEventListener('submit', (e) => {
        if (submitting) { e.preventDefault(); return; }
        const errors = [];

        if (!orgSelect.value) errors.push(['id_org_dest', 'Choisissez l’organisation de destination.']);
        if (!gradeSelect.value) errors.push(['id_affec_dest', 'Choisissez le nouveau grade ou poste.']);
        if (ville.value.trim().length < 2) errors.push(['ville_affec', 'Indiquez la ville d’affectation.']);
        if (!dateEffet.value) errors.push(['date_effet', 'Indiquez la date d’effet.']);
        else if (MIN_DATE && dateEffet.value < MIN_DATE) errors.push(['date_effet', 'La date d’effet ne peut pas précéder le début de l’affectation actuelle.']);

        if (errors.length) {
            e.preventDefault();
            errors.forEach(([n, m]) => showError(n, m));
            form.elements[errors[0][0]].focus();
            return;
        }

        // Une mutation clôture l'affectation en cours : on demande confirmation
        const orgTxt = orgSelect.options[orgSelect.selectedIndex].textContent.trim();
        const memeOrg = ORG_ACTUELLE && parseInt(orgSelect.value, 10) === ORG_ACTUELLE;
        const date = dateEffet.value.split('-').reverse().join('/');
        const msg = 'Confirmer la mutation de ' + AGENT + ' vers « ' + orgTxt + ' » à compter du ' + date + ' ?'
                  + (memeOrg ? '\n\nL’organisation de destination est la même que l’organisation actuelle.' : '');
        if (!window.confirm(msg)) { e.preventDefault(); return; }

        // Le formulaire part vers traitement_mutation.php ; on évite seulement le double envoi
        submitting = true;
        submitBtn.classList.add('loading');
    });
})();
</script>
</body>
</html>
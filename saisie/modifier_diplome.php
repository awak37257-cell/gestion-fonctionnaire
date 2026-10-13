<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config.php';

// Helpers d'affichage
if (!function_exists('e')) {
    function e($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
}

// Session et droits
$role       = strtolower(trim($_SESSION['rol'] ?? ''));
$id_session = isset($_SESSION['id_fonc']) ? (int)$_SESSION['id_fonc'] : 0;
$est_admin  = in_array($role, ['admin', 'admin_local'], true);

// Il faut être connecté
if (!$id_session && !$est_admin) {
    header('Location: ../saisie/login.php');
    exit();
}

$id_agent = isset($_GET['id_agent']) ? (int)$_GET['id_agent'] : 0;
$id_dipl  = isset($_GET['id_dipl']) ? (int)$_GET['id_dipl'] : 0;

// Un agent ne peut modifier que ses propres diplômes
if (!$est_admin && $id_agent !== $id_session) {
    header('Location: ../views/profil_agent.php?id=' . $id_session);
    exit();
}

$retour_url = $id_agent
    ? '../views/profil_agent.php?id=' . $id_agent . '&section=diplomes'
    : ($est_admin ? '../views/liste_fonctionnaires.php' : '../views/profil_agent.php?id=' . $id_session);

// Agent et diplôme concernés
$agent = null;
$diplome = null;
if ($id_agent && $id_dipl) {
    $stmt = $db->prepare("SELECT nom_fonc, prenom_fonc, matri FROM FONCTIONNAIRE WHERE id_fonc = ?");
    $stmt->execute([$id_agent]);
    $agent = $stmt->fetch();

    $stmt = $db->prepare("
        SELECT d.*, o.ann_obte
        FROM DIPLOME d
        JOIN OBTENIR o ON d.id_dipl = o.id_dipl
        WHERE d.id_dipl = :id_dipl AND o.id_fonc = :id_agent
    ");
    $stmt->execute([':id_dipl' => $id_dipl, ':id_agent' => $id_agent]);
    $diplome = $stmt->fetch();
}

$nom_agent = $agent ? trim(($agent['prenom_fonc'] ?? '') . ' ' . mb_strtoupper((string)($agent['nom_fonc'] ?? ''), 'UTF-8')) : '';

// Jeton CSRF
if (empty($_SESSION['csrf_dipl_mod'])) {
    $_SESSION['csrf_dipl_mod'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_dipl_mod'];

// Niveaux proposés (les mêmes que dans le formulaire d'ajout)
$niveaux = [
    'CEPC'     => 'CEPC',
    'BEPC'     => 'BEPC',
    'BAC'      => 'BAC',
    'BAC+2'    => 'BTS / BAC+2',
    'BAC+3'    => 'Licence / BAC+3',
    'BAC+5'    => 'Master / BAC+5',
    'Doctorat' => 'Doctorat',
];

$annee_courante = (int)date('Y');
$erreurs = [];

// Valeurs affichées dans le formulaire
$val_libelle = $diplome['lib_dipl'] ?? '';
$val_annee   = $diplome['ann_obte'] ?? $annee_courante;
$niv_actuel  = (string)($diplome['niv_etu'] ?? '');
if ($niv_actuel !== '' && isset($niveaux[$niv_actuel])) {
    $val_niv_select = $niv_actuel; $val_niv_custom = '';
} elseif ($niv_actuel !== '') {
    $val_niv_select = 'Autre'; $val_niv_custom = $niv_actuel;
} else {
    $val_niv_select = 'BAC'; $val_niv_custom = '';
}

if ($diplome && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $val_libelle    = trim($_POST['lib_dipl'] ?? '');
    $val_niv_select = trim($_POST['niv_select'] ?? '');
    $val_niv_custom = trim($_POST['niv_custom'] ?? '');
    $val_annee      = (int)($_POST['ann_obte'] ?? 0);
    $fich_url       = $diplome['fich_url'];

    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        $erreurs[] = "La session du formulaire a expiré. Rechargez la page et réessayez.";
    }
    $lg = mb_strlen($val_libelle, 'UTF-8');
    if ($lg < 3) {
        $erreurs[] = "Indiquez la désignation du diplôme (3 caractères minimum).";
    } elseif ($lg > 150) {
        $erreurs[] = "La désignation ne doit pas dépasser 150 caractères.";
    }

    // Niveau : liste fixe, ou valeur personnalisée si « Autre »
    $niv_etu = '';
    if ($val_niv_select === 'Autre') {
        if ($val_niv_custom === '' || mb_strlen($val_niv_custom, 'UTF-8') > 40) {
            $erreurs[] = "Précisez le niveau du diplôme (40 caractères maximum).";
        } else {
            $niv_etu = $val_niv_custom;
        }
    } elseif (isset($niveaux[$val_niv_select])) {
        $niv_etu = $val_niv_select;
    } else {
        $erreurs[] = "Le niveau sélectionné n'est pas valide.";
    }

    if ($val_annee < 1950 || $val_annee > $annee_courante) {
        $erreurs[] = "L'année d'obtention doit être comprise entre 1950 et " . $annee_courante . ".";
    }

    // Nouveau justificatif : contrôle du contenu réel du fichier
    $ext_fichier = null;
    $a_un_fichier = isset($_FILES['pdf_diplome']) && $_FILES['pdf_diplome']['error'] !== UPLOAD_ERR_NO_FILE;
    if ($a_un_fichier) {
        $f = $_FILES['pdf_diplome'];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $erreurs[] = ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE)
                ? "Le justificatif est trop volumineux."
                : "Le téléversement du justificatif a échoué. Réessayez.";
        } elseif ($f['size'] > 5 * 1024 * 1024) {
            $erreurs[] = "Le justificatif ne doit pas dépasser 5 Mo.";
        } else {
            $debut = (string)file_get_contents($f['tmp_name'], false, null, 0, 8);
            if (strncmp($debut, '%PDF-', 5) === 0)                  $ext_fichier = 'pdf';
            elseif (substr($debut, 0, 3) === "\xFF\xD8\xFF")        $ext_fichier = 'jpg';
            elseif (substr($debut, 0, 8) === "\x89PNG\r\n\x1a\n")   $ext_fichier = 'png';
            else $erreurs[] = "Le justificatif doit être un PDF, un JPG ou un PNG.";
        }
    }

    if (empty($erreurs)) {
        $nouveau_fichier = null;
        try {
            $db->beginTransaction();

            // Justificatif
            if ($ext_fichier !== null) {
                $dossier = __DIR__ . '/../uploads/diplomes/';
                if (!is_dir($dossier)) { mkdir($dossier, 0755, true); }
                $nom_fichier = 'diplome_' . $id_agent . '_' . bin2hex(random_bytes(6)) . '.' . $ext_fichier;
                if (!move_uploaded_file($_FILES['pdf_diplome']['tmp_name'], $dossier . $nom_fichier)) {
                    throw new RuntimeException("Impossible d'enregistrer le justificatif.");
                }
                $nouveau_fichier = $nom_fichier;
                $fich_url = $nom_fichier;
            }

            // Si cette fiche de diplôme est partagée entre plusieurs agents, on ne la modifie pas :
            // on en crée une copie pour cet agent, afin de ne pas changer le diplôme des autres.
            $stmt = $db->prepare("SELECT COUNT(DISTINCT id_fonc) FROM OBTENIR WHERE id_dipl = ?");
            $stmt->execute([$id_dipl]);
            $nb_agents = (int)$stmt->fetchColumn();

            if ($nb_agents > 1) {
                $stmt = $db->prepare("INSERT INTO DIPLOME (lib_dipl, niv_etu, fich_url) VALUES (?, ?, ?)");
                $stmt->execute([$val_libelle, $niv_etu, $fich_url]);
                $nouvel_id = (int)$db->lastInsertId();

                $stmt = $db->prepare("UPDATE OBTENIR SET id_dipl = ?, ann_obte = ? WHERE id_dipl = ? AND id_fonc = ?");
                $stmt->execute([$nouvel_id, $val_annee, $id_dipl, $id_agent]);
            } else {
                $stmt = $db->prepare("UPDATE DIPLOME SET lib_dipl = ?, niv_etu = ?, fich_url = ? WHERE id_dipl = ?");
                $stmt->execute([$val_libelle, $niv_etu, $fich_url, $id_dipl]);

                $stmt = $db->prepare("UPDATE OBTENIR SET ann_obte = ? WHERE id_dipl = ? AND id_fonc = ?");
                $stmt->execute([$val_annee, $id_dipl, $id_agent]);
            }

            $db->commit();
            header("Location: ../views/profil_agent.php?id=" . $id_agent . "&section=diplomes&success=1");
            exit();

        } catch (Throwable $ex) {
            if ($db->inTransaction()) { $db->rollBack(); }
            if ($nouveau_fichier) { @unlink(__DIR__ . '/../uploads/diplomes/' . $nouveau_fichier); }
            error_log("modifier_diplome.php : " . $ex->getMessage());
            $erreurs[] = "La modification n'a pas pu être enregistrée. Réessayez dans un instant.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier le diplôme | Ministère des Affaires étrangères</title>
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
        button, input, select { font: inherit; color: inherit; }
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

        .page { max-width: 680px; margin: 0 auto; padding: 32px 24px 40px; }

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
        .alert ul { margin: 0; padding-left: 18px; }
        .alert ul:only-child { padding-left: 0; list-style: none; }

        .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; }
        .panel-body { padding: 26px 26px 24px; }

        .field { margin-bottom: 22px; }
        .field > label, .field > .label { display: block; margin-bottom: 6px; font-size: 13px; font-weight: 600; }
        .req { color: var(--accent-dark); margin-left: 2px; }
        .opt { color: var(--muted); font-weight: 500; font-size: 12px; margin-left: 4px; }
        .err { margin-top: 6px; font-size: 12px; font-weight: 600; color: var(--red); }
        .err:empty { display: none; }
        .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0 20px; }

        .control {
            width: 100%; height: 44px; padding: 0 14px;
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
        .custom-niveau { display: none; margin-top: 10px; }
        .custom-niveau.show { display: block; }

        .current-file { display: flex; align-items: center; gap: 12px; margin-bottom: 10px; padding: 10px 12px; border: 1px solid var(--line); border-radius: 10px; background: var(--paper); font-size: 13px; }
        .current-file i { color: var(--blue); }
        .current-file a { margin-left: auto; font-weight: 600; color: var(--blue); }
        .current-file a:hover { text-decoration: underline; }

        .dropzone { position: relative; display: block; text-align: center; padding: 20px 16px; border: 1.5px dashed #BAC4D0; border-radius: 12px; background: var(--paper); cursor: pointer; transition: border-color .15s, background .15s; }
        .dropzone:hover, .dropzone.drag { border-color: var(--accent); background: var(--accent-soft); }
        .dropzone.ok  { border-color: var(--green); background: #F2FBF7; border-style: solid; }
        .dropzone.bad { border-color: var(--red); background: var(--red-soft); }
        .dz-ico { width: 40px; height: 40px; margin: 0 auto 8px; display: grid; place-items: center; border-radius: 11px; background: var(--surface); color: var(--accent); font-size: 17px; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
        .dropzone.ok .dz-ico { color: var(--green); }
        .dz-title { font-weight: 600; word-break: break-word; }
        .dz-sub { color: var(--muted); font-size: 12px; margin-top: 2px; }
        .dropzone input { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }

        .actions { display: flex; justify-content: flex-end; gap: 12px; flex-wrap: wrap; margin-top: 8px; padding-top: 20px; border-top: 1px solid var(--line-soft); }

        .empty { text-align: center; padding: 48px 24px; color: var(--muted); }
        .empty .empty-ico { width: 64px; height: 64px; margin: 0 auto 16px; display: grid; place-items: center; border-radius: 50%; background: var(--paper); color: #B5BEC9; font-size: 26px; }
        .empty strong { display: block; color: var(--text); font-size: 15px; margin-bottom: 4px; }
        .empty p { margin: 0 auto 18px; max-width: 44ch; }

        @media (max-width: 720px) {
            .topbar { padding: 12px 16px; }
            .brand-sub { display: none; }
            .page { padding: 20px 16px 32px; }
            .hero { padding: 24px 20px 20px; }
            .panel-body { padding: 20px 16px; }
            .grid2 { grid-template-columns: 1fr; }
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
        <div class="hero-ico"><i class="fa-solid fa-graduation-cap"></i></div>
        <div>
            <h1>Modifier le diplôme</h1>
            <p>
                <?php if ($agent): ?>
                    Dossier de <strong><?= e($nom_agent) ?></strong> · Matricule <?= e($agent['matri'] ?? '') ?>
                <?php else: ?>
                    Diplôme d’un agent
                <?php endif; ?>
            </p>
        </div>
    </div>

    <?php if (!$diplome): ?>

        <section class="panel">
            <div class="empty">
                <div class="empty-ico"><i class="fa-solid fa-circle-question"></i></div>
                <strong>Diplôme introuvable</strong>
                <p>Ce diplôme n’existe pas dans le dossier de cet agent. Ouvrez cette page depuis l’onglet Diplômes du profil.</p>
                <a href="<?= e($retour_url) ?>" class="btn btn-ghost"><i class="fa-solid fa-arrow-left"></i> Retour</a>
            </div>
        </section>

    <?php else: ?>

        <?php if (!empty($erreurs)): ?>
            <div class="alert" role="alert">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?php if (count($erreurs) === 1): ?>
                    <span><?= e($erreurs[0]) ?></span>
                <?php else: ?>
                    <ul><?php foreach ($erreurs as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <section class="panel">
            <form action="" method="POST" enctype="multipart/form-data" id="diplForm" class="panel-body" novalidate>
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

                <div class="field">
                    <label for="lib_dipl">Désignation du diplôme <span class="req">*</span></label>
                    <input class="control" type="text" id="lib_dipl" name="lib_dipl" maxlength="150" value="<?= e($val_libelle) ?>" autocomplete="off" required>
                    <div class="err" data-err="lib_dipl"></div>
                </div>

                <div class="grid2">
                    <div class="field">
                        <label for="niv_select">Niveau <span class="req">*</span></label>
                        <select class="control" id="niv_select" name="niv_select" required>
                            <?php foreach ($niveaux as $val => $lib): ?>
                                <option value="<?= e($val) ?>" <?= ($val_niv_select === $val) ? 'selected' : '' ?>><?= e($lib) ?></option>
                            <?php endforeach; ?>
                            <option value="Autre" <?= ($val_niv_select === 'Autre') ? 'selected' : '' ?>>Autre…</option>
                        </select>
                        <div class="custom-niveau <?= ($val_niv_select === 'Autre') ? 'show' : '' ?>" id="customBox">
                            <input class="control" type="text" id="niv_custom" name="niv_custom" maxlength="40" value="<?= e($val_niv_custom) ?>" placeholder="Précisez le niveau (ex. : BAC+4)" autocomplete="off">
                        </div>
                        <div class="err" data-err="niv_custom"></div>
                    </div>

                    <div class="field">
                        <label for="ann_obte">Année d’obtention <span class="req">*</span></label>
                        <input class="control" type="number" id="ann_obte" name="ann_obte" min="1950" max="<?= $annee_courante ?>" value="<?= e($val_annee) ?>" inputmode="numeric" required>
                        <div class="err" data-err="ann_obte"></div>
                    </div>
                </div>

                <div class="field">
                    <span class="label">Justificatif <span class="opt">facultatif</span></span>
                    <?php if (!empty($diplome['fich_url'])): ?>
                        <div class="current-file">
                            <i class="fa-solid fa-file-pdf"></i>
                            <span>Un justificatif est déjà enregistré</span>
                            <a href="../uploads/diplomes/<?= e($diplome['fich_url']) ?>" target="_blank" rel="noopener">Voir</a>
                        </div>
                    <?php endif; ?>
                    <div class="dropzone" id="dropzone">
                        <div class="dz-ico"><i class="fa-solid fa-cloud-arrow-up" id="dzIcon"></i></div>
                        <div class="dz-title" id="dzTitle"><?= !empty($diplome['fich_url']) ? 'Déposer un nouveau fichier pour le remplacer' : 'Cliquez ou déposez un fichier' ?></div>
                        <div class="dz-sub" id="dzSub">PDF, JPG ou PNG, 5 Mo maximum</div>
                        <input type="file" name="pdf_diplome" id="scan" accept=".pdf,.jpg,.jpeg,.png" aria-label="Nouveau justificatif">
                    </div>
                </div>

                <div class="actions">
                    <a href="<?= e($retour_url) ?>" class="btn btn-ghost">Annuler</a>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fa-solid fa-floppy-disk ico"></i>
                        <span class="spin"></span>
                        <span>Enregistrer les modifications</span>
                    </button>
                </div>
            </form>
        </section>

    <?php endif; ?>

</main>

<script>
(function () {
    const form = document.getElementById('diplForm');
    if (!form) return;

    const libelle = document.getElementById('lib_dipl');
    const select = document.getElementById('niv_select');
    const customBox = document.getElementById('customBox');
    const custom = document.getElementById('niv_custom');
    const annee = document.getElementById('ann_obte');
    const scan = document.getElementById('scan');
    const dz = document.getElementById('dropzone');
    const dzTitle = document.getElementById('dzTitle');
    const dzSub = document.getElementById('dzSub');
    const dzIcon = document.getElementById('dzIcon');
    const submitBtn = document.getElementById('submitBtn');
    const MAX = 5 * 1024 * 1024;
    const EXT = ['pdf', 'jpg', 'jpeg', 'png'];
    const MAX_YEAR = <?= $annee_courante ?>;
    const defaultTitle = dzTitle.textContent;
    let submitting = false;

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
    ['lib_dipl', 'niv_custom', 'ann_obte'].forEach(n => form.elements[n].addEventListener('input', () => clearError(n)));

    select.addEventListener('change', () => {
        const autre = select.value === 'Autre';
        customBox.classList.toggle('show', autre);
        if (autre) custom.focus(); else { custom.value = ''; clearError('niv_custom'); }
    });

    function resetDrop() {
        dz.classList.remove('ok', 'bad');
        dzTitle.textContent = defaultTitle;
        dzSub.textContent = 'PDF, JPG ou PNG, 5 Mo maximum';
        dzIcon.className = 'fa-solid fa-cloud-arrow-up';
    }
    scan.addEventListener('change', () => {
        const f = scan.files && scan.files[0];
        if (!f) { resetDrop(); return; }
        const ext = (f.name.split('.').pop() || '').toLowerCase();
        if (!EXT.includes(ext)) {
            scan.value = ''; resetDrop(); dz.classList.add('bad');
            dzSub.textContent = 'Format non pris en charge. Utilisez PDF, JPG ou PNG.';
            return;
        }
        if (f.size > MAX) {
            scan.value = ''; resetDrop(); dz.classList.add('bad');
            dzSub.textContent = 'Fichier trop volumineux (5 Mo maximum).';
            return;
        }
        dz.classList.remove('bad'); dz.classList.add('ok');
        dzTitle.textContent = f.name;
        dzSub.textContent = (f.size / 1024 / 1024).toFixed(2).replace('.', ',') + ' Mo, remplacera le justificatif actuel';
        dzIcon.className = 'fa-solid fa-circle-check';
    });
    ['dragenter', 'dragover'].forEach(ev => dz.addEventListener(ev, () => dz.classList.add('drag')));
    ['dragleave', 'drop'].forEach(ev => dz.addEventListener(ev, () => dz.classList.remove('drag')));

    form.addEventListener('submit', (e) => {
        if (submitting) { e.preventDefault(); return; }
        const errors = [];
        if (libelle.value.trim().length < 3) errors.push(['lib_dipl', 'Indiquez la désignation du diplôme (3 caractères minimum).']);
        if (select.value === 'Autre' && !custom.value.trim()) errors.push(['niv_custom', 'Précisez le niveau du diplôme.']);
        const y = parseInt(annee.value, 10);
        if (!y || y < 1950 || y > MAX_YEAR) errors.push(['ann_obte', 'L’année doit être comprise entre 1950 et ' + MAX_YEAR + '.']);

        if (errors.length) {
            e.preventDefault();
            errors.forEach(([n, m]) => showError(n, m));
            form.elements[errors[0][0]].focus();
            return;
        }
        // Le formulaire part vers cette même page pour traitement ; on évite seulement le double envoi
        submitting = true;
        submitBtn.classList.add('loading');
    });
})();
</script>
</body>
</html>
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config.php';
require_once '../includes/phone_numbers.php';

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

// On récupère l'ID de l'agent ET l'ID du message s'il existe
$id     = (int)($_GET['id'] ?? $_POST['id_fonc'] ?? 0);
$id_msg = $_GET['id_msg'] ?? $_POST['id_msg'] ?? null;
$id_msg = ($id_msg !== null && $id_msg !== '') ? (int)$id_msg : null;

if ($id <= 0) {
    header("Location: ../views/admin_dashboard.php");
    exit();
}

// 1. SÉCURITÉ
$role       = $_SESSION['rol'] ?? '';
$id_session = $_SESSION['id_fonc'] ?? null;

$est_admin        = ($role === 'admin' || $role === 'admin_local');
$est_proprietaire = ($id_session !== null && (int)$id_session === $id);

if (!$est_admin && !$est_proprietaire) {
    header("Location: ../saisie/login.php");
    exit();
}

// Jeton CSRF propre à ce formulaire
if (empty($_SESSION['csrf_edit'])) {
    $_SESSION['csrf_edit'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_edit'];

// 2. RÉCUPÉRATION DE L'AGENT AVEC SON AFFECTATION ACTUELLE
$stmt = $db->prepare("
    SELECT f.*, r.id_org, a.gra_post, o.nom_org, o.sigle_org
    FROM FONCTIONNAIRE f
    LEFT JOIN RECEVOIR r ON f.id_fonc = r.id_fonc AND r.date_fin IS NULL
    LEFT JOIN AFFECTATION a ON r.id_affec = a.id_affec
    LEFT JOIN ORGANISATION o ON r.id_org = o.id_org
    WHERE f.id_fonc = ?
");
$stmt->execute([$id]);
$agent = $stmt->fetch();

if (!$agent) {
    die("Agent introuvable.");
}

$regions_telephoniques = indicatifs_telephoniques();
$telephone_decompose = decomposer_telephone((string)($agent['cont'] ?? ''));
$indicatif_enregistre = $telephone_decompose['region'];
$telephone_enregistre = $telephone_decompose['national'];

// 3. TRAITEMENT DE LA MISE À JOUR
$erreur = null;
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id_fonc'])) {
    // Les valeurs sont stockées telles quelles : l'échappement HTML se fait uniquement à l'affichage
    $nom        = mb_strtoupper(trim($_POST['nom'] ?? ''), 'UTF-8');
    $prenoms    = trim($_POST['prenoms'] ?? '');
    $regionTelephone = strtoupper(trim((string)($_POST['indicatif'] ?? '')));
    $contact = numero_telephone_e164((string)($_POST['telephone'] ?? ''), $regionTelephone);
    $email      = trim($_POST['email'] ?? '');
    $date_naiss = trim($_POST['date_naissance'] ?? '');
    $genre      = $_POST['genre'] ?? '';
    $adresse    = trim($_POST['adresse'] ?? '');

    // Données pro : seul l'admin modifie, l'agent garde ses valeurs actuelles
    $id_org   = ($est_admin && !empty($_POST['id_org'])) ? (int)$_POST['id_org'] : $agent['id_org'];
    $gra_post = ($est_admin && !empty($_POST['gra_post'])) ? trim($_POST['gra_post']) : $agent['gra_post'];

    // Validation
    $erreurs = [];
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        $erreurs[] = "La session du formulaire a expiré. Rechargez la page et réessayez.";
    }
    if ($nom === '' || $prenoms === '') {
        $erreurs[] = "Le nom et les prénoms sont obligatoires.";
    }
    if (!in_array($genre, ['M', 'F'], true)) {
        $erreurs[] = "Le genre sélectionné n'est pas valide.";
    }
    $d = DateTime::createFromFormat('Y-m-d', $date_naiss);
    if (!$d || $d->format('Y-m-d') !== $date_naiss) {
        $erreurs[] = "La date de naissance n'est pas valide.";
    }
    if ($contact === null) {
        $erreurs[] = "Le numéro de téléphone n'est pas valide pour le pays sélectionné.";
        $contact = trim((string)($agent['cont'] ?? ''));
    }
    if ($adresse === '') {
        $erreurs[] = "L'adresse est obligatoire.";
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreurs[] = "L'adresse e-mail n'est pas valide.";
    }

    // Validation de la photo (type réel, taille)
    $ext_photo = null;
    if (!empty($_FILES['photo_agent']['name'])) {
        $pf = $_FILES['photo_agent'];
        if ($pf['error'] !== UPLOAD_ERR_OK) {
            $erreurs[] = "Le téléversement de la photo a échoué. Réessayez avec un autre fichier.";
        } elseif ($pf['size'] > 2 * 1024 * 1024) {
            $erreurs[] = "La photo ne doit pas dépasser 2 Mo.";
        } else {
            $info  = @getimagesize($pf['tmp_name']);
            $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
            if (!$info || !isset($types[$info[2]])) {
                $erreurs[] = "La photo doit être au format JPG, PNG ou WebP.";
            } else {
                $ext_photo = $types[$info[2]];
            }
        }
    }

    if (empty($erreurs)) {
        $nom_photo = null;
        try {
            $db->beginTransaction();

            $photo_sql = "";
            $params = [$nom, $prenoms, $contact, ($email !== '' ? $email : null), $date_naiss, $genre, $adresse];

            if ($ext_photo !== null) {
                $dossier = __DIR__ . "/../uploads/photos/";
                if (!is_dir($dossier)) { mkdir($dossier, 0755, true); }
                $nom_photo = bin2hex(random_bytes(8)) . '.' . $ext_photo;
                if (!move_uploaded_file($_FILES['photo_agent']['tmp_name'], $dossier . $nom_photo)) {
                    $nom_photo = null;
                    throw new RuntimeException("Impossible d'enregistrer la photo.");
                }
                $photo_sql = ", photo_fonc = ?";
                $params[] = $nom_photo;
            }

            $params[] = $id;

            $sql = "UPDATE FONCTIONNAIRE
                    SET nom_fonc = ?, prenom_fonc = ?, cont = ?, email_perso = ?,
                        date_naiss = ?, gen = ?, adre = ? $photo_sql
                    WHERE id_fonc = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            // MISE À JOUR PROFESSIONNELLE (admin uniquement)
            if ($est_admin && !empty($id_org) && !empty($gra_post)) {
                $stmtAff = $db->prepare("SELECT id_affec FROM AFFECTATION WHERE gra_post = ? LIMIT 1");
                $stmtAff->execute([$gra_post]);
                $affectation = $stmtAff->fetch();

                if ($affectation) {
                    $id_affec_trouve = $affectation['id_affec'];
                } else {
                    $stmtInsAff = $db->prepare("INSERT INTO AFFECTATION (gra_post, sta_post) VALUES (?, 'Actif')");
                    $stmtInsAff->execute([$gra_post]);
                    $id_affec_trouve = $db->lastInsertId();
                }

                $stmtCheck = $db->prepare("SELECT id_rec FROM RECEVOIR WHERE id_fonc = ? AND date_fin IS NULL");
                $stmtCheck->execute([$id]);
                $rec_actuel = $stmtCheck->fetch();

                if ($rec_actuel) {
                    $stmtUpRec = $db->prepare("UPDATE RECEVOIR SET id_org = ?, id_affec = ? WHERE id_rec = ?");
                    $stmtUpRec->execute([$id_org, $id_affec_trouve, $rec_actuel['id_rec']]);
                } else {
                    $stmtInsRec = $db->prepare("INSERT INTO RECEVOIR (id_fonc, id_org, id_affec, date_debut) VALUES (?, ?, ?, CURRENT_DATE)");
                    $stmtInsRec->execute([$id, $id_org, $id_affec_trouve]);
                }
            }

            // Message de demande de modification
            if ($id_msg && $est_admin) {
                $updateMsg = $db->prepare("UPDATE MESSAGES SET stat = 'lu' WHERE id_mess = ?");
                $updateMsg->execute([$id_msg]);
            }

            // HISTORIQUE
            $id_user_action = $_SESSION['id_user'] ?? $id;
            $action_desc = ($est_admin ? "Admin" : "Agent") . " a modifié le profil : " . $nom;
            $log_stmt = $db->prepare("INSERT INTO HISTORIQUE (id_user, action_desc, dat_hist) VALUES (?, ?, NOW())");
            $log_stmt->execute([$id_user_action, $action_desc]);

            $db->commit();

            // REDIRECTION (le dashboard affiche son bandeau de succès avec success=1)
            if ($est_admin) {
                header("Location: ../views/admin_dashboard.php?success=1");
            } else {
                header("Location: profil_agent.php?id=$id&msg=update_success");
            }
            exit();
        } catch (Throwable $ex) {
            if ($db->inTransaction()) { $db->rollBack(); }
            if ($nom_photo) { @unlink(__DIR__ . "/../uploads/photos/" . $nom_photo); }
            error_log("modifier_agent.php : " . $ex->getMessage());
            $erreurs[] = "La mise à jour n'a pas pu être enregistrée. Réessayez, ou contactez l'administrateur si le problème persiste.";
        }
    }

    $erreur = $erreurs;

    // On réaffiche ce qui a été saisi
    $agent['nom_fonc']    = $nom;
    $agent['prenom_fonc'] = $prenoms;
    $agent['cont']        = $contact;
    $agent['email_perso'] = $email;
    $agent['date_naiss']  = $date_naiss;
    $agent['gen']         = $genre;
    $agent['adre']        = $adresse;
    $agent['id_org']      = $id_org;
    $agent['gra_post']    = $gra_post;
}

// Listes pour les <select>
$organisations = $db->query("SELECT id_org, nom_org, sigle_org FROM ORGANISATION ORDER BY nom_org ASC")->fetchAll();
$grades = $db->query("SELECT DISTINCT gra_post FROM AFFECTATION WHERE gra_post IS NOT NULL AND gra_post != '' ORDER BY gra_post ASC")->fetchAll();

$photo_agent = !empty($agent['photo_fonc']) && file_exists(__DIR__ . '/../uploads/photos/' . $agent['photo_fonc'])
    ? '../uploads/photos/' . $agent['photo_fonc'] : '';
$nom_complet = trim(($agent['prenom_fonc'] ?? '') . ' ' . mb_strtoupper((string)($agent['nom_fonc'] ?? ''), 'UTF-8'));
$retour_url  = $est_admin ? 'liste_fonctionnaires.php' : 'profil_agent.php?id=' . $id;
$retour_txt  = $est_admin ? 'Retour à la liste' : 'Retour à mon profil';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SICP | Modifier le profil - <?= e($agent['matri'] ?? 'Agent'); ?></title>
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

        /* ---------- Barre du haut ---------- */
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
        .btn[disabled] { opacity: .75; pointer-events: none; }
        .btn .spin { display: none; width: 16px; height: 16px; border: 2px solid rgba(255,255,255,.4); border-top-color: #fff; border-radius: 50%; animation: spin .7s linear infinite; }
        .btn.loading .spin { display: inline-block; }
        .btn.loading .ico { display: none; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .icon-btn { width: 38px; height: 38px; display: inline-grid; place-items: center; border: 1px solid var(--line); background: var(--surface); border-radius: 9px; color: var(--muted); transition: color .15s, border-color .15s, background .15s; }
        .icon-btn.danger:hover { color: var(--red); border-color: #F0C4BF; background: var(--red-soft); }

        /* ---------- Page ---------- */
        .page { max-width: 900px; margin: 0 auto; padding: 32px 24px 0; }

        .page-head {
            position: relative; overflow: hidden;
            display: flex; align-items: center; gap: 22px; flex-wrap: wrap;
            margin-bottom: 24px; padding: 32px;
            border-radius: 16px; color: #fff;
            background:
                radial-gradient(520px 220px at 100% 0%, rgba(247,127,0,.38), transparent 70%),
                linear-gradient(120deg, #0A3B2C 0%, #11634A 100%);
        }
        .page-head::before {
            content: ""; position: absolute; top: 0; left: 0; right: 0; height: 5px;
            background: linear-gradient(90deg, #F77F00 0 33.33%, #FFFFFF 33.33% 66.66%, #009E60 66.66% 100%);
        }
        .avatar-xl {
            flex: none; width: 76px; height: 76px; border-radius: 50%; overflow: hidden;
            display: grid; place-items: center;
            background: rgba(255,255,255,.14); border: 2px solid rgba(255,255,255,.5);
            font-weight: 700; font-size: 24px; letter-spacing: .5px;
        }
        .avatar-xl img { width: 100%; height: 100%; object-fit: cover; object-position: center; display: block; }
        .head-text { min-width: 0; }
        .page-head h1 {
            margin: 0; font-family: var(--font-display); font-weight: 400;
            font-size: clamp(28px, 4vw, 40px); line-height: 1.05; letter-spacing: -.01em;
        }
        .head-name { margin: 6px 0 0; color: rgba(255,255,255,.85); font-size: 15px; font-weight: 500; }
        .head-chips { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px; }
        .hchip { display: inline-flex; align-items: center; gap: 8px; padding: 4px 12px; border-radius: 999px; background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.22); font-size: 12px; font-weight: 600; }
        .hchip i { color: #FFB066; }

        /* ---------- Alerte ---------- */
        .alert { display: flex; gap: 12px; padding: 14px 16px; margin-bottom: 20px; border-radius: 10px; background: var(--red-soft); color: #8E2A22; border: 1px solid #F0C4BF; font-weight: 500; }
        .alert i { margin-top: 3px; }
        .alert ul { margin: 0; padding-left: 18px; }
        .alert ul:only-child { padding-left: 0; list-style: none; }

        /* ---------- Sections ---------- */
        .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; margin-bottom: 20px; }
        .panel-head { display: flex; align-items: center; gap: 12px; padding: 18px 24px; border-bottom: 1px solid var(--line-soft); }
        .panel-ico { width: 34px; height: 34px; flex: none; display: grid; place-items: center; border-radius: 9px; font-size: 14px; background: var(--green-soft); color: var(--green); }
        .panel-ico.warm { background: var(--accent-soft); color: var(--accent-dark); }
        .panel-title { margin: 0; font-size: 15px; font-weight: 700; }
        .panel-sub { margin: 0; color: var(--muted); font-size: 12px; }
        .panel-body { padding: 22px 24px 24px; }

        .tel-row { display: grid; grid-template-columns: minmax(0, 1.5fr) minmax(0, 1fr); gap: 8px; }
        .tel-row select, .tel-row input { min-width: 0; }
        @media (max-width: 480px) { .tel-row { grid-template-columns: 1fr; } }

        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px 20px; }
        .full { grid-column: 1 / -1; }
        .field { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
        .field label { font-size: 13px; font-weight: 600; }
        .req { color: var(--accent-dark); margin-left: 2px; }

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
        .control:disabled { background-color: var(--paper); color: var(--muted); cursor: not-allowed; border-color: var(--line); }
        select.control:disabled { background-image: none; padding-right: 14px; }

        .note { display: flex; gap: 10px; align-items: flex-start; margin-top: 18px; padding: 12px 14px; border-radius: 10px; background: var(--paper); color: var(--muted); font-size: 13px; }
        .note i { margin-top: 3px; color: var(--accent); }

        /* Photo */
        .photo-row { display: flex; align-items: center; gap: 18px; flex-wrap: wrap; }
        .photo-preview {
            width: 72px; height: 72px; flex: none; border-radius: 50%; overflow: hidden;
            display: grid; place-items: center;
            background: var(--green-soft); color: var(--green); font-weight: 700; font-size: 22px;
            border: 2px solid var(--line);
        }
        .photo-preview img { width: 100%; height: 100%; object-fit: cover; object-position: center; display: block; }
        .photo-actions { display: flex; flex-direction: column; gap: 6px; align-items: flex-start; min-width: 0; }
        .file-btn { position: relative; overflow: hidden; }
        .file-btn input { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; }
        .hint { font-size: 12px; color: var(--muted); word-break: break-word; }
        .hint.bad { color: var(--red); font-weight: 600; }
        .hint.ok  { color: var(--green); font-weight: 600; }

        /* Barre d'actions */
        .actions-bar {
            position: sticky; bottom: 0; z-index: 30;
            display: flex; justify-content: flex-end; gap: 12px; flex-wrap: wrap;
            margin: 0 -24px; padding: 16px 24px;
            background: rgba(242,246,243,.92);
            backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
            border-top: 1px solid var(--line);
        }

        @media (max-width: 720px) {
            .topbar { padding: 12px 16px; }
            .brand-sub { display: none; }
            .page { padding: 20px 16px 0; }
            .page-head { padding: 26px 20px; }
            .grid { grid-template-columns: 1fr; }
            .panel-head, .panel-body { padding-left: 16px; padding-right: 16px; }
            .actions-bar { margin: 0 -16px; padding: 12px 16px; }
            .actions-bar .btn { flex: 1; }
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
        <a href="<?= e($retour_url) ?>" class="btn btn-ghost" id="backLink">
            <i class="fa-solid fa-arrow-left"></i> <?= e($retour_txt) ?>
        </a>
        <a href="../logout.php" class="icon-btn danger" title="Se déconnecter" aria-label="Se déconnecter">
            <i class="fa-solid fa-power-off"></i>
        </a>
    </div>
</header>

<main class="page">

    <div class="page-head">
        <div class="avatar-xl">
            <?php if ($photo_agent): ?>
                <img src="<?= e($photo_agent) ?>" alt="<?= e($nom_complet) ?>">
            <?php else: ?>
                <?= e(initiales($agent['nom_fonc'] ?? '', $agent['prenom_fonc'] ?? '')) ?>
            <?php endif; ?>
        </div>
        <div class="head-text">
            <h1><?= $est_proprietaire ? 'Mon profil' : 'Modifier la fiche' ?></h1>
            <p class="head-name"><?= e($nom_complet) ?></p>
            <div class="head-chips">
                <span class="hchip"><i class="fa-solid fa-hashtag"></i> Matricule <?= e($agent['matri'] ?? 'N/A') ?></span>
                <?php if (!empty($agent['nom_org'])): ?>
                    <span class="hchip"><i class="fa-solid fa-sitemap"></i> <?= e($agent['nom_org']) ?><?= !empty($agent['sigle_org']) ? ' (' . e($agent['sigle_org']) . ')' : '' ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (!empty($erreur)): ?>
        <div class="alert" role="alert">
            <i class="fa-solid fa-circle-exclamation"></i>
            <?php if (count($erreur) === 1): ?>
                <span><?= e($erreur[0]) ?></span>
            <?php else: ?>
                <ul>
                    <?php foreach ($erreur as $msg): ?><li><?= e($msg) ?></li><?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <form id="editForm" method="POST" action="" enctype="multipart/form-data">
        <input type="hidden" name="id_fonc" value="<?= (int)$id ?>">
        <input type="hidden" name="id_msg" value="<?= e($id_msg ?? '') ?>">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

        <!-- Informations personnelles -->
        <section class="panel">
            <div class="panel-head">
                <div class="panel-ico"><i class="fa-solid fa-user"></i></div>
                <div>
                    <h2 class="panel-title">Informations personnelles</h2>
                    <p class="panel-sub">Identité de l’agent et photo de profil</p>
                </div>
            </div>
            <div class="panel-body">
                <div class="grid">
                    <div class="field">
                        <label for="nom">Nom de famille <span class="req">*</span></label>
                        <input class="control" type="text" id="nom" name="nom" value="<?= e($agent['nom_fonc'] ?? '') ?>" required autocomplete="family-name">
                    </div>
                    <div class="field">
                        <label for="prenoms">Prénoms <span class="req">*</span></label>
                        <input class="control" type="text" id="prenoms" name="prenoms" value="<?= e($agent['prenom_fonc'] ?? '') ?>" required autocomplete="given-name">
                    </div>
                    <div class="field">
                        <label for="date_naissance">Date de naissance <span class="req">*</span></label>
                        <input class="control" type="date" id="date_naissance" name="date_naissance" value="<?= e($agent['date_naiss'] ?? '') ?>" required>
                    </div>
                    <div class="field">
                        <label for="genre">Genre <span class="req">*</span></label>
                        <select class="control" id="genre" name="genre" required>
                            <option value="M" <?= (($agent['gen'] ?? '') == 'M') ? 'selected' : ''; ?>>Masculin</option>
                            <option value="F" <?= (($agent['gen'] ?? '') == 'F') ? 'selected' : ''; ?>>Féminin</option>
                        </select>
                    </div>

                    <div class="field full">
                        <label for="photo_agent">Photo de profil</label>
                        <div class="photo-row">
                            <div class="photo-preview" id="photoPreview">
                                <?php if ($photo_agent): ?>
                                    <img src="<?= e($photo_agent) ?>" alt="Photo actuelle">
                                <?php else: ?>
                                    <?= e(initiales($agent['nom_fonc'] ?? '', $agent['prenom_fonc'] ?? '')) ?>
                                <?php endif; ?>
                            </div>
                            <div class="photo-actions">
                                <span class="btn btn-ghost file-btn">
                                    <i class="fa-solid fa-camera"></i> Choisir une photo
                                    <input type="file" id="photo_agent" name="photo_agent" accept="image/jpeg,image/png,image/webp">
                                </span>
                                <span class="hint" id="photoHint">JPG, PNG ou WebP, 2 Mo maximum.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Coordonnées -->
        <section class="panel">
            <div class="panel-head">
                <div class="panel-ico"><i class="fa-solid fa-address-book"></i></div>
                <div>
                    <h2 class="panel-title">Coordonnées</h2>
                    <p class="panel-sub">Pour joindre l’agent</p>
                </div>
            </div>
            <div class="panel-body">
                <div class="grid">
                    <div class="field">
                        <label for="telephone">Téléphone <span class="req">*</span></label>
                        <div class="tel-row">
                            <select class="control" id="indicatif" name="indicatif" aria-label="Pays et indicatif">
                                <?php foreach ($regions_telephoniques as $region): ?>
                                    <option value="<?= e($region['region']) ?>" data-calling-code="<?= e($region['calling_code']) ?>" data-digits="<?= (int)$region['max_digits'] ?>" <?= $indicatif_enregistre === $region['region'] ? 'selected' : '' ?>>
                                        <?= e($region['name']) ?> (<?= e($region['calling_code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input class="control" type="tel" id="telephone" name="telephone" value="<?= e($telephone_enregistre) ?>" inputmode="numeric" maxlength="<?= (int)$regions_telephoniques[$indicatif_enregistre]['max_digits'] ?>" required autocomplete="tel-national">
                        </div>
                        <div class="hint" id="telephoneHint"><?= $indicatif_enregistre === 'CI' ? 'Côte d’Ivoire (+225) : le numéro doit commencer par 07, 01 ou 05.' : 'Indicatif ' . e($regions_telephoniques[$indicatif_enregistre]['calling_code']) . ' ; maximum ' . (int)$regions_telephoniques[$indicatif_enregistre]['max_digits'] . ' chiffres.' ?></div>
                    </div>
                    <div class="field">
                        <label for="email">E-mail personnel</label>
                        <input class="control" type="email" id="email" name="email" value="<?= e($agent['email_perso'] ?? '') ?>" autocomplete="email">
                    </div>
                    <div class="field full">
                        <label for="adresse">Adresse de résidence <span class="req">*</span></label>
                        <input class="control" type="text" id="adresse" name="adresse" value="<?= e($agent['adre'] ?? '') ?>" required autocomplete="street-address">
                    </div>
                </div>
            </div>
        </section>

        <!-- Situation professionnelle -->
        <section class="panel">
            <div class="panel-head">
                <div class="panel-ico warm"><i class="fa-solid fa-briefcase"></i></div>
                <div>
                    <h2 class="panel-title">Situation professionnelle</h2>
                    <p class="panel-sub">Structure d’affectation et grade</p>
                </div>
            </div>
            <div class="panel-body">
                <div class="grid">
                    <div class="field">
                        <label for="id_org">Organisation <?= $est_admin ? '<span class="req">*</span>' : '' ?></label>
                        <select class="control" name="id_org" id="id_org" <?= !$est_admin ? 'disabled' : 'required' ?>>
                            <option value="">Sélectionner</option>
                            <?php foreach ($organisations as $org): ?>
                                <option value="<?= (int)$org['id_org'] ?>" <?= (isset($agent['id_org']) && $agent['id_org'] == $org['id_org']) ? 'selected' : '' ?>>
                                    <?= e($org['nom_org'] . (!empty($org['sigle_org']) ? " (" . $org['sigle_org'] . ")" : '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label for="gra_post">Grade <?= $est_admin ? '<span class="req">*</span>' : '' ?></label>
                        <select class="control" name="gra_post" id="gra_post" <?= !$est_admin ? 'disabled' : 'required' ?>>
                            <option value="">Sélectionner</option>
                            <?php foreach ($grades as $g): ?>
                                <option value="<?= e($g['gra_post']) ?>" <?= (isset($agent['gra_post']) && $agent['gra_post'] == $g['gra_post']) ? 'selected' : '' ?>>
                                    <?= e($g['gra_post']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php if (!$est_admin): ?>
                    <div class="note">
                        <i class="fa-solid fa-circle-info"></i>
                        <span>Seul un administrateur peut modifier l’organisation et le grade.</span>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <div class="actions-bar">
            <a href="<?= e($retour_url) ?>" class="btn btn-ghost" id="cancelLink">Annuler</a>
            <button type="submit" class="btn btn-primary" id="submitBtn">
                <i class="fa-solid fa-floppy-disk ico"></i>
                <span class="spin"></span>
                <span>Enregistrer les modifications</span>
            </button>
        </div>
    </form>
</main>

<script>
    (function () {
        const form      = document.getElementById('editForm');
        const fileInput = document.getElementById('photo_agent');
        const preview   = document.getElementById('photoPreview');
        const hint      = document.getElementById('photoHint');
        const submitBtn = document.getElementById('submitBtn');
        const indicatif = document.getElementById('indicatif');
        const telephone = document.getElementById('telephone');
        const telephoneHint = document.getElementById('telephoneHint');
        const MAX_BYTES = 2 * 1024 * 1024;
        const TYPES = ['image/jpeg', 'image/png', 'image/webp'];
        const defaultPreview = preview.innerHTML;
        const defaultHint = hint.textContent;

        function configurerTelephone() {
            const longueur = Number(indicatif.selectedOptions[0]?.dataset.digits || 0);
            telephone.maxLength = longueur;
            telephone.value = telephone.value.replace(/\D/g, '').slice(0, longueur);
            telephoneHint.textContent = indicatif.value === 'CI'
                ? 'Côte d’Ivoire (+225) : le numéro doit commencer par 07, 01 ou 05.'
                : 'Indicatif ' + indicatif.selectedOptions[0]?.dataset.callingCode + ' ; maximum ' + longueur + ' chiffres.';
            verifierPrefixeTelephone();
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
            telephone.value = telephone.value.replace(/\D/g, '').slice(0, longueur);
            verifierPrefixeTelephone();
        });
        configurerTelephone();

        // Aperçu et contrôle de la photo
        fileInput.addEventListener('change', () => {
            const file = fileInput.files && fileInput.files[0];
            hint.className = 'hint';
            if (!file) { preview.innerHTML = defaultPreview; hint.textContent = defaultHint; return; }

            if (!TYPES.includes(file.type)) {
                fileInput.value = '';
                preview.innerHTML = defaultPreview;
                hint.classList.add('bad');
                hint.textContent = 'Format non pris en charge. Utilisez JPG, PNG ou WebP.';
                return;
            }
            if (file.size > MAX_BYTES) {
                fileInput.value = '';
                preview.innerHTML = defaultPreview;
                hint.classList.add('bad');
                hint.textContent = 'Photo trop volumineuse (2 Mo maximum).';
                return;
            }
            const img = document.createElement('img');
            img.alt = 'Aperçu de la nouvelle photo';
            img.src = URL.createObjectURL(file);
            preview.innerHTML = '';
            preview.appendChild(img);
            hint.classList.add('ok');
            hint.textContent = file.name + ' (' + (file.size / 1024 / 1024).toFixed(2).replace('.', ',') + ' Mo)';
        });

        // Avertissement si l'on quitte avec des modifications non enregistrées
        const snapshot = () => Array.from(new FormData(form))
            .filter(([k]) => k !== 'csrf')
            .map(([k, v]) => k + '=' + (v instanceof File ? v.name + v.size : v)).join('&');
        const initial = snapshot();
        let submitting = false;

        window.addEventListener('beforeunload', (e) => {
            if (!submitting && snapshot() !== initial) { e.preventDefault(); e.returnValue = ''; }
        });

        // Envoi : on bloque seulement le double clic
        form.addEventListener('submit', (event) => {
            const digits = telephone.value.replace(/\D/g, '');
            if (indicatif.value === 'CI' && digits.length >= 2 && !/^(07|01|05)/.test(digits)) {
                event.preventDefault();
                telephoneHint.classList.add('bad');
                telephoneHint.textContent = 'En Côte d’Ivoire, le numéro doit commencer par 07, 01 ou 05.';
                telephone.focus();
                return;
            }
            submitting = true;
            submitBtn.classList.add('loading');
            setTimeout(() => submitBtn.setAttribute('disabled', 'disabled'), 0);
        });
    })();
</script>
</body>
</html>
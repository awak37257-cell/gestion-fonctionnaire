<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config.php';

// Helpers d'affichage
if (!function_exists('e')) {
    function e($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('fmt_date_heure')) {
    function fmt_date_heure($d) {
        $t = $d ? strtotime((string)$d) : false;
        return $t ? date('d/m/Y à H:i', $t) : null;
    }
}

// Session et droits
$role        = strtolower(trim($_SESSION['rol'] ?? ''));
$user_org_id = isset($_SESSION['id_org']) ? (int)$_SESSION['id_org'] : 0;
$id_session  = isset($_SESSION['id_fonc']) ? (int)$_SESSION['id_fonc'] : 0;

// Il faut être connecté
if ($role === '' && !$id_session) {
    header('Location: ../saisie/login.php');
    exit();
}

// Seuls les profils habilités à gérer les archives peuvent modifier un document
if (!in_array($role, ['admin', 'admin_local', 'gestionnaire'], true)) {
    header('Location: ' . ($id_session ? '../views/profil_agent.php?id=' . $id_session : '../saisie/login.php'));
    exit();
}

// 1. Identifiant du document
$id_doc = (int)($_GET['id'] ?? 0);
if ($id_doc <= 0) {
    header('Location: ../views/gestion_documents.php');
    exit();
}

// 2. Document, agent concerné et organisation actuelle de l'agent
$stmt = $db->prepare("
    SELECT d.*, f.nom_fonc, f.prenom_fonc, f.matri,
           (SELECT r.id_org FROM RECEVOIR r
             WHERE r.id_fonc = d.id_fonc AND r.date_fin IS NULL
             ORDER BY r.date_debut DESC LIMIT 1) AS id_org_agent
    FROM DOCUMENT d
    JOIN FONCTIONNAIRE f ON d.id_fonc = f.id_fonc
    WHERE d.id_doc = ?
");
$stmt->execute([$id_doc]);
$document = $stmt->fetch();

// Hors admin central, on ne modifie que les documents des agents de sa propre organisation
$acces_refuse = false;
if ($document && $role !== 'admin') {
    if (!$user_org_id || (int)($document['id_org_agent'] ?? 0) !== $user_org_id) {
        $acces_refuse = true;
    }
}

$retour_url = '../views/gestion_documents.php';
$nom_agent = $document ? trim(($document['prenom_fonc'] ?? '') . ' ' . mb_strtoupper((string)($document['nom_fonc'] ?? ''), 'UTF-8')) : '';

// Jeton CSRF
if (empty($_SESSION['csrf_doc_mod'])) {
    $_SESSION['csrf_doc_mod'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_doc_mod'];

$erreurs = [];
$val_libelle = $document['lib_doc'] ?? '';

// 3. Traitement lors de l'enregistrement
if ($document && !$acces_refuse && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Valeur stockée telle quelle : l'échappement HTML se fait uniquement à l'affichage
    $val_libelle = trim($_POST['lib_doc'] ?? '');

    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        $erreurs[] = "La session du formulaire a expiré. Rechargez la page et réessayez.";
    }
    $lg = mb_strlen($val_libelle, 'UTF-8');
    if ($lg < 5) {
        $erreurs[] = "Le libellé doit contenir au moins 5 caractères.";
    } elseif ($lg > 150) {
        $erreurs[] = "Le libellé ne doit pas dépasser 150 caractères.";
    }

    // Nouveau fichier : contrôle du contenu réel
    $ext_fichier = null;
    $a_un_fichier = isset($_FILES['nouveau_fichier']) && $_FILES['nouveau_fichier']['error'] !== UPLOAD_ERR_NO_FILE;
    if ($a_un_fichier) {
        $f = $_FILES['nouveau_fichier'];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $erreurs[] = ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE)
                ? "Le fichier est trop volumineux."
                : "Le téléversement a échoué. Réessayez.";
        } elseif ($f['size'] > 5 * 1024 * 1024) {
            $erreurs[] = "Le fichier ne doit pas dépasser 5 Mo.";
        } else {
            $debut = (string)file_get_contents($f['tmp_name'], false, null, 0, 8);
            if (strncmp($debut, '%PDF-', 5) === 0)                  $ext_fichier = 'pdf';
            elseif (substr($debut, 0, 3) === "\xFF\xD8\xFF")        $ext_fichier = 'jpg';
            elseif (substr($debut, 0, 8) === "\x89PNG\r\n\x1a\n")   $ext_fichier = 'png';
            else $erreurs[] = "Le fichier doit être un PDF, un JPG ou un PNG.";
        }
    }

    if (empty($erreurs)) {
        $chemin_final = $document['fich_url'];
        $nouveau_fichier_disque = null;
        try {
            if ($ext_fichier !== null) {
                $dossier_rel = '../actions/uploads/pieces_jointes/';
                $dossier_abs = __DIR__ . '/' . $dossier_rel;
                if (!is_dir($dossier_abs)) { mkdir($dossier_abs, 0755, true); }

                $nom_unique = 'doc_' . $id_doc . '_' . bin2hex(random_bytes(6)) . '.' . $ext_fichier;
                if (!move_uploaded_file($_FILES['nouveau_fichier']['tmp_name'], $dossier_abs . $nom_unique)) {
                    throw new RuntimeException("Impossible d'enregistrer le fichier.");
                }
                $nouveau_fichier_disque = $dossier_abs . $nom_unique;
                $chemin_final = $dossier_rel . $nom_unique;     // même format que précédemment
            }

            $update = $db->prepare("UPDATE DOCUMENT SET lib_doc = ?, fich_url = ? WHERE id_doc = ?");
            $update->execute([$val_libelle, $chemin_final, $id_doc]);

            // L'ancien fichier n'est supprimé qu'une fois la base à jour, et seulement s'il se trouve dans le dossier des pièces jointes
            if ($nouveau_fichier_disque && !empty($document['fich_url'])) {
                $ancien = realpath(__DIR__ . '/' . $document['fich_url']);
                $racine = realpath(__DIR__ . '/../actions/uploads/pieces_jointes');
                if ($ancien && $racine && strpos($ancien, $racine . DIRECTORY_SEPARATOR) === 0 && is_file($ancien)) {
                    @unlink($ancien);
                }
            }

            header("Location: ../views/gestion_documents.php?success=1");
            exit();

        } catch (Throwable $ex) {
            if ($nouveau_fichier_disque) { @unlink($nouveau_fichier_disque); }
            error_log("modifier_doc.php : " . $ex->getMessage());
            $erreurs[] = "La modification n'a pas pu être enregistrée. Réessayez dans un instant.";
        }
    }
}

// Type du fichier actuel (pour l'icône)
$ext_actuelle = $document ? strtolower(pathinfo((string)$document['fich_url'], PATHINFO_EXTENSION)) : '';
$icone_actuelle = $ext_actuelle === 'pdf' ? 'fa-file-pdf' : (in_array($ext_actuelle, ['jpg', 'jpeg', 'png'], true) ? 'fa-file-image' : 'fa-file');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier l'archive | Ministère des Affaires étrangères</title>
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
        button, input { font: inherit; color: inherit; }
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

        .page { max-width: 640px; margin: 0 auto; padding: 32px 24px 40px; }

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
        .hint { margin-top: 6px; font-size: 12px; color: var(--muted); }
        .err { margin-top: 6px; font-size: 12px; font-weight: 600; color: var(--red); }
        .err:empty { display: none; }

        .control {
            width: 100%; height: 44px; padding: 0 14px;
            background: var(--surface); border: 1px solid #D5DBE3; border-radius: 9px;
            transition: border-color .15s, box-shadow .15s;
        }
        .control:hover { border-color: #B9C2CE; }
        .control:focus { outline: none; border-color: var(--green); box-shadow: 0 0 0 3px rgba(14,159,104,.15); }
        .control.bad { border-color: var(--red); }

        .current-file { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border: 1px solid #BFE3D0; border-radius: 10px; background: #F2FBF7; }
        .current-file .ico-file { width: 36px; height: 36px; flex: none; display: grid; place-items: center; border-radius: 9px; background: var(--green-soft); color: var(--green); font-size: 16px; }
        .current-file .name { flex: 1; min-width: 0; font-weight: 600; word-break: break-all; }
        .current-file .tag { padding: 2px 10px; border-radius: 999px; background: var(--green); color: #fff; font-size: 12px; font-weight: 700; }
        .current-file a.view { font-weight: 600; color: var(--blue); white-space: nowrap; }
        .current-file a.view:hover { text-decoration: underline; }

        .dropzone { position: relative; display: block; text-align: center; padding: 22px 16px; border: 1.5px dashed #BAC4D0; border-radius: 12px; background: var(--paper); cursor: pointer; transition: border-color .15s, background .15s; }
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
        <a href="<?= e($retour_url) ?>" class="btn btn-ghost"><i class="fa-solid fa-arrow-left"></i> Retour aux archives</a>
        <a href="../logout.php" class="icon-btn danger" title="Se déconnecter" aria-label="Se déconnecter"><i class="fa-solid fa-power-off"></i></a>
    </div>
</header>

<main class="page">

    <div class="hero">
        <div class="hero-ico"><i class="fa-solid fa-file-pen"></i></div>
        <div>
            <h1>Modifier l’archive</h1>
            <p>
                <?php if ($document && !$acces_refuse): ?>
                    <strong><?= e($nom_agent) ?></strong> · Matricule <?= e($document['matri'] ?? '') ?>
                    <?php if ($d = fmt_date_heure($document['date_enre'] ?? null)): ?> · Enregistré le <?= e($d) ?><?php endif; ?>
                <?php else: ?>
                    Mise à jour d’un document archivé
                <?php endif; ?>
            </p>
        </div>
    </div>

    <?php if (!$document): ?>

        <section class="panel">
            <div class="empty">
                <div class="empty-ico"><i class="fa-solid fa-circle-question"></i></div>
                <strong>Document introuvable</strong>
                <p>Ce document n’existe pas ou a été supprimé.</p>
                <a href="<?= e($retour_url) ?>" class="btn btn-ghost"><i class="fa-solid fa-arrow-left"></i> Retour aux archives</a>
            </div>
        </section>

    <?php elseif ($acces_refuse): ?>

        <section class="panel">
            <div class="empty">
                <div class="empty-ico"><i class="fa-solid fa-lock"></i></div>
                <strong>Modification non autorisée</strong>
                <p>Vous ne pouvez modifier que les documents des agents de votre propre organisation.</p>
                <a href="<?= e($retour_url) ?>" class="btn btn-ghost"><i class="fa-solid fa-arrow-left"></i> Retour aux archives</a>
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
            <form method="POST" enctype="multipart/form-data" id="docForm" class="panel-body" novalidate>
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

                <div class="field">
                    <label for="lib_doc">Libellé du document <span class="req">*</span></label>
                    <input class="control" type="text" id="lib_doc" name="lib_doc" maxlength="150" value="<?= e($val_libelle) ?>" autocomplete="off" required>
                    <div class="err" id="errLibelle"></div>
                </div>

                <div class="field">
                    <span class="label">Fichier actuellement archivé</span>
                    <div class="current-file">
                        <div class="ico-file"><i class="fa-solid <?= e($icone_actuelle) ?>"></i></div>
                        <span class="name"><?= !empty($document['fich_url']) ? e(basename($document['fich_url'])) : 'Aucun fichier' ?></span>
                        <?php if (!empty($document['fich_url'])): ?>
                            <a class="view" href="<?= e($document['fich_url']) ?>" target="_blank" rel="noopener">Voir</a>
                        <?php endif; ?>
                        <span class="tag">Actuel</span>
                    </div>
                </div>

                <div class="field">
                    <span class="label">Remplacer par un nouveau fichier <span class="opt">facultatif</span></span>
                    <div class="dropzone" id="dropzone">
                        <div class="dz-ico"><i class="fa-solid fa-cloud-arrow-up" id="dzIcon"></i></div>
                        <div class="dz-title" id="dzTitle">Cliquez ou déposez un fichier</div>
                        <div class="dz-sub" id="dzSub">PDF, JPG ou PNG, 5 Mo maximum</div>
                        <input type="file" name="nouveau_fichier" id="nouveau_fichier" accept=".pdf,.jpg,.jpeg,.png" aria-label="Nouveau fichier">
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
    const form = document.getElementById('docForm');
    if (!form) return;

    const libelle = document.getElementById('lib_doc');
    const errLib = document.getElementById('errLibelle');
    const file = document.getElementById('nouveau_fichier');
    const dz = document.getElementById('dropzone');
    const dzTitle = document.getElementById('dzTitle');
    const dzSub = document.getElementById('dzSub');
    const dzIcon = document.getElementById('dzIcon');
    const submitBtn = document.getElementById('submitBtn');
    const MAX = 5 * 1024 * 1024;
    const EXT = ['pdf', 'jpg', 'jpeg', 'png'];
    let submitting = false;

    function resetDrop() {
        dz.classList.remove('ok', 'bad');
        dzTitle.textContent = 'Cliquez ou déposez un fichier';
        dzSub.textContent = 'PDF, JPG ou PNG, 5 Mo maximum';
        dzIcon.className = 'fa-solid fa-cloud-arrow-up';
    }

    file.addEventListener('change', () => {
        const f = file.files && file.files[0];
        if (!f) { resetDrop(); return; }
        const ext = (f.name.split('.').pop() || '').toLowerCase();
        if (!EXT.includes(ext)) {
            file.value = ''; resetDrop(); dz.classList.add('bad');
            dzSub.textContent = 'Format non pris en charge. Utilisez PDF, JPG ou PNG.';
            return;
        }
        if (f.size > MAX) {
            file.value = ''; resetDrop(); dz.classList.add('bad');
            dzSub.textContent = 'Fichier trop volumineux (5 Mo maximum).';
            return;
        }
        dz.classList.remove('bad'); dz.classList.add('ok');
        dzTitle.textContent = f.name;                                  // textContent : aucun HTML injecté
        dzSub.textContent = (f.size / 1024 / 1024).toFixed(2).replace('.', ',') + ' Mo, remplacera le fichier actuel';
        dzIcon.className = 'fa-solid fa-circle-check';
    });
    ['dragenter', 'dragover'].forEach(ev => dz.addEventListener(ev, () => dz.classList.add('drag')));
    ['dragleave', 'drop'].forEach(ev => dz.addEventListener(ev, () => dz.classList.remove('drag')));

    libelle.addEventListener('input', () => { libelle.classList.remove('bad'); errLib.textContent = ''; });

    form.addEventListener('submit', (e) => {
        if (submitting) { e.preventDefault(); return; }
        if (libelle.value.trim().length < 5) {
            e.preventDefault();
            libelle.classList.add('bad');
            errLib.textContent = 'Le libellé doit contenir au moins 5 caractères.';
            libelle.focus();
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
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config.php';
require_once '../includes/notifications.php';

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

// Session et droits
$id_session   = isset($_SESSION['id_fonc']) ? (int)$_SESSION['id_fonc'] : 0;
$role_session = strtolower(trim($_SESSION['rol'] ?? ''));
$est_admin    = in_array($role_session, ['admin', 'admin_local'], true);

// Il faut être connecté
if (!$id_session && !$est_admin) {
    header('Location: ../saisie/login.php');
    exit();
}

// Agent concerné : celui de l'URL, sinon celui de la session
$id_agent = isset($_GET['id']) ? (int)$_GET['id'] : $id_session;
if (!$id_agent) {
    header('Location: ../index.php');
    exit();
}

// Un agent ne peut ajouter des compétences qu'à son propre dossier
if (!$est_admin && $id_agent !== $id_session) {
    header('Location: ../views/profil_agent.php?id=' . $id_session);
    exit();
}

// Agent concerné (nom et matricule pour l'affichage)
$stmt = $db->prepare("SELECT nom_fonc, prenom_fonc, matri FROM FONCTIONNAIRE WHERE id_fonc = ?");
$stmt->execute([$id_agent]);
$agent = $stmt->fetch();
if (!$agent) {
    header('Location: ../index.php');
    exit();
}
$nom_agent = trim(($agent['prenom_fonc'] ?? '') . ' ' . mb_strtoupper((string)($agent['nom_fonc'] ?? ''), 'UTF-8'));

// Jeton CSRF
if (empty($_SESSION['csrf_comp'])) {
    $_SESSION['csrf_comp'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_comp'];

// Niveaux autorisés
$niveaux = [
    'Débutant'      => 'Notions de base',
    'Intermédiaire' => 'Pratique régulière',
    'Avancé'        => 'Maîtrise complète',
    'Expert'        => 'Référent technique',
];

$erreurs = [];
$val_comp = '';
$val_niveau = 'Intermédiaire';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $val_comp   = trim($_POST['competence'] ?? '');
    $val_niveau = $_POST['niveau'] ?? '';

    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        $erreurs[] = "La session du formulaire a expiré. Rechargez la page et réessayez.";
    }
    $longueur = mb_strlen($val_comp, 'UTF-8');
    if ($longueur < 2) {
        $erreurs[] = "Indiquez le nom de la compétence (2 caractères minimum).";
    } elseif ($longueur > 100) {
        $erreurs[] = "Le nom de la compétence ne doit pas dépasser 100 caractères.";
    }
    if (!isset($niveaux[$val_niveau])) {
        $erreurs[] = "Le niveau de maîtrise sélectionné n'est pas valide.";
    }

    // Justificatif : contrôle du contenu réel du fichier, pas de son nom
    $ext_fichier = null;
    $a_un_fichier = isset($_FILES['justificatif']) && $_FILES['justificatif']['error'] !== UPLOAD_ERR_NO_FILE;
    if ($a_un_fichier) {
        $f = $_FILES['justificatif'];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $erreurs[] = ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE)
                ? "Le justificatif est trop volumineux."
                : "Le téléversement du justificatif a échoué. Réessayez.";
        } elseif ($f['size'] > 5 * 1024 * 1024) {
            $erreurs[] = "Le justificatif ne doit pas dépasser 5 Mo.";
        } else {
            $debut = (string)file_get_contents($f['tmp_name'], false, null, 0, 8);
            if (strncmp($debut, '%PDF-', 5) === 0)                      $ext_fichier = 'pdf';
            elseif (substr($debut, 0, 3) === "\xFF\xD8\xFF")            $ext_fichier = 'jpg';
            elseif (substr($debut, 0, 8) === "\x89PNG\r\n\x1a\n")       $ext_fichier = 'png';
            else $erreurs[] = "Le justificatif doit être un PDF, un JPG ou un PNG.";
        }
    }

    if (empty($erreurs)) {
        $nom_fichier = null;
        try {
            $stmtOrg = $db->prepare('SELECT id_org FROM RECEVOIR WHERE id_fonc = ? AND date_fin IS NULL');
            $stmtOrg->execute([$id_agent]);
            $organisationsAgent = $stmtOrg->fetchAll(PDO::FETCH_COLUMN);
            $db->beginTransaction();

            // 1. Compétence du référentiel : on la réutilise ou on la crée
            $stmt = $db->prepare("SELECT id_comp FROM competences WHERE libelle_comp = :libelle");
            $stmt->execute([':libelle' => $val_comp]);
            $comp_existante = $stmt->fetch();

            if ($comp_existante) {
                $id_comp = $comp_existante['id_comp'];
            } else {
                $stmt = $db->prepare("INSERT INTO competences (libelle_comp) VALUES (:libelle)");
                $stmt->execute([':libelle' => $val_comp]);
                $id_comp = $db->lastInsertId();
            }

            // 2. Justificatif : nom aléatoire, extension déduite du contenu
            if ($ext_fichier !== null) {
                $dossier = __DIR__ . '/../uploads/';
                if (!is_dir($dossier)) { mkdir($dossier, 0755, true); }
                $nom_fichier = 'comp_' . $id_agent . '_' . bin2hex(random_bytes(6)) . '.' . $ext_fichier;
                if (!move_uploaded_file($_FILES['justificatif']['tmp_name'], $dossier . $nom_fichier)) {
                    $nom_fichier = null;
                    throw new RuntimeException("Impossible d'enregistrer le justificatif.");
                }
            }

            // 3. Lien avec le fonctionnaire (mise à jour si la compétence existe déjà pour lui)
            $stmt = $db->prepare("INSERT INTO detenir (id_fonc, id_comp, niveau, justificatif_path)
                                  VALUES (:id_f, :id_c, :niv, :path)
                                  ON DUPLICATE KEY UPDATE niveau = :niv2,
                                  justificatif_path = IFNULL(:path2, justificatif_path)");
            $stmt->execute([
                ':id_f'  => $id_agent,
                ':id_c'  => $id_comp,
                ':niv'   => $val_niveau,
                ':path'  => $nom_fichier,
                ':niv2'  => $val_niveau,
                ':path2' => $nom_fichier,
            ]);

            notifierAdministrateurs(
                $db,
                $id_agent,
                'competence',
                (int)$id_comp,
                'Nouvelle compétence déclarée',
                $val_comp . ' · niveau ' . $val_niveau,
                array_map('intval', $organisationsAgent)
            );

            $db->commit();
            header("Location: ../views/profil_agent.php?id=" . $id_agent . "&section=competences&success=1");
            exit();

        } catch (Throwable $ex) {
            if ($db->inTransaction()) { $db->rollBack(); }
            if ($nom_fichier) { @unlink(__DIR__ . '/../uploads/' . $nom_fichier); }
            error_log("ajouter_competence.php : " . $ex->getMessage());
            $erreurs[] = "La compétence n'a pas pu être enregistrée. Réessayez dans un instant.";
        }
    }
}

// Suggestions : compétences déjà présentes dans le référentiel
$suggestions = [];
try {
    $suggestions = $db->query("SELECT libelle_comp FROM competences ORDER BY libelle_comp ASC LIMIT 300")->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $ex) {
    error_log("ajouter_competence.php (suggestions) : " . $ex->getMessage());
}

$retour_url = '../views/profil_agent.php?id=' . $id_agent . '&section=competences';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter une compétence | Ministère des Affaires étrangères</title>
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

        /* ---------- Page ---------- */
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
        .hint { margin-top: 6px; font-size: 12px; color: var(--muted); }
        .err { margin-top: 6px; font-size: 12px; font-weight: 600; color: var(--red); }
        .err:empty { display: none; }

        .control {
            width: 100%; height: 44px; padding: 0 14px;
            background: var(--surface); border: 1px solid #D5DBE3; border-radius: 9px;
            transition: border-color .15s, box-shadow .15s;
        }
        .control::placeholder { color: #9AA5B3; }
        .control:hover { border-color: #B9C2CE; }
        .control:focus { outline: none; border-color: var(--green); box-shadow: 0 0 0 3px rgba(14,159,104,.15); }
        .control.bad { border-color: var(--red); }

        /* Niveaux */
        .levels { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
        .level { position: relative; }
        .level input { position: absolute; opacity: 0; inset: 0; width: 100%; height: 100%; margin: 0; cursor: pointer; }
        .level .box { display: block; height: 100%; padding: 12px 14px; border: 1px solid #D5DBE3; border-radius: 10px; background: var(--surface); transition: border-color .15s, background .15s, box-shadow .15s; }
        .level strong { display: flex; align-items: center; gap: 8px; font-size: 14px; }
        .level strong i { font-size: 12px; color: transparent; width: 16px; height: 16px; display: grid; place-items: center; border-radius: 50%; border: 1.5px solid #B9C2CE; }
        .level small { display: block; margin-top: 2px; padding-left: 24px; color: var(--muted); font-size: 12px; }
        .level:hover .box { border-color: #B9C2CE; }
        .level input:checked + .box { border-color: var(--green); background: #F2FBF7; box-shadow: 0 0 0 3px rgba(14,159,104,.12); }
        .level input:checked + .box strong i { background: var(--green); border-color: var(--green); color: #fff; }
        .level input:focus-visible + .box { outline: 2px solid var(--accent); outline-offset: 2px; }

        /* Dépôt de fichier */
        .dropzone { position: relative; display: block; text-align: center; padding: 22px 16px; border: 1.5px dashed #BAC4D0; border-radius: 12px; background: var(--paper); cursor: pointer; transition: border-color .15s, background .15s; }
        .dropzone:hover, .dropzone.drag { border-color: var(--accent); background: var(--accent-soft); }
        .dropzone.ok  { border-color: var(--green); background: #F2FBF7; border-style: solid; }
        .dropzone.bad { border-color: var(--red); background: var(--red-soft); }
        .dz-ico { width: 40px; height: 40px; margin: 0 auto 8px; display: grid; place-items: center; border-radius: 11px; background: var(--surface); color: var(--accent); font-size: 17px; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
        .dropzone.ok .dz-ico { color: var(--green); }
        .dz-title { font-weight: 600; word-break: break-word; }
        .dz-sub { color: var(--muted); font-size: 12px; margin-top: 2px; }
        .dropzone input { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }

        .actions { display: flex; justify-content: flex-end; gap: 12px; flex-wrap: wrap; padding-top: 6px; border-top: 1px solid var(--line-soft); margin-top: 8px; padding-top: 20px; }

        @media (max-width: 720px) {
            .topbar { padding: 12px 16px; }
            .brand-sub { display: none; }
            .page { padding: 20px 16px 32px; }
            .hero { padding: 24px 20px 20px; }
            .panel-body { padding: 20px 16px; }
            .levels { grid-template-columns: 1fr; }
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
        <div class="hero-ico"><i class="fa-solid fa-award"></i></div>
        <div>
            <h1>Ajouter une compétence</h1>
            <p>Dossier de <strong><?= e($nom_agent) ?></strong> · Matricule <?= e($agent['matri'] ?? '') ?></p>
        </div>
    </div>

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
        <form action="" method="POST" enctype="multipart/form-data" id="compForm" class="panel-body" novalidate>
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

            <div class="field">
                <label for="competence">Nom de la compétence <span class="req">*</span></label>
                <input class="control" type="text" id="competence" name="competence" list="comp-list" maxlength="100"
                       value="<?= e($val_comp) ?>" placeholder="Ex. : Gestion de projet, audit, rédaction administrative" autocomplete="off" required>
                <datalist id="comp-list">
                    <?php foreach ($suggestions as $s): ?><option value="<?= e($s) ?>"></option><?php endforeach; ?>
                </datalist>
                <div class="hint">Choisissez une suggestion si elle existe déjà, pour éviter les doublons. Si l’agent possède déjà cette compétence, son niveau sera mis à jour.</div>
                <div class="err" id="errCompetence"></div>
            </div>

            <div class="field">
                <span class="label" id="niveauLabel">Niveau de maîtrise <span class="req">*</span></span>
                <div class="levels" role="radiogroup" aria-labelledby="niveauLabel">
                    <?php foreach ($niveaux as $nom => $desc): ?>
                        <label class="level">
                            <input type="radio" name="niveau" value="<?= e($nom) ?>" <?= ($val_niveau === $nom) ? 'checked' : '' ?>>
                            <span class="box">
                                <strong><i class="fa-solid fa-check"></i> <?= e($nom) ?></strong>
                                <small><?= e($desc) ?></small>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="field">
                <span class="label">Justificatif <span class="opt">facultatif</span></span>
                <div class="dropzone" id="dropzone">
                    <div class="dz-ico"><i class="fa-solid fa-cloud-arrow-up" id="dzIcon"></i></div>
                    <div class="dz-title" id="dzTitle">Cliquez ou déposez un fichier</div>
                    <div class="dz-sub" id="dzSub">PDF, JPG ou PNG, 5 Mo maximum</div>
                    <input type="file" name="justificatif" id="justificatif" accept=".pdf,.jpg,.jpeg,.png" aria-label="Justificatif">
                </div>
            </div>

            <div class="actions">
                <a href="<?= e($retour_url) ?>" class="btn btn-ghost">Annuler</a>
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <i class="fa-solid fa-circle-check ico"></i>
                    <span class="spin"></span>
                    <span>Enregistrer la compétence</span>
                </button>
            </div>
        </form>
    </section>

</main>

<script>
(function () {
    const form = document.getElementById('compForm');
    const comp = document.getElementById('competence');
    const errComp = document.getElementById('errCompetence');
    const file = document.getElementById('justificatif');
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
        dzTitle.textContent = f.name;
        dzSub.textContent = (f.size / 1024 / 1024).toFixed(2).replace('.', ',') + ' Mo, prêt à être enregistré';
        dzIcon.className = 'fa-solid fa-circle-check';
    });
    ['dragenter', 'dragover'].forEach(ev => dz.addEventListener(ev, () => dz.classList.add('drag')));
    ['dragleave', 'drop'].forEach(ev => dz.addEventListener(ev, () => dz.classList.remove('drag')));

    comp.addEventListener('input', () => { comp.classList.remove('bad'); errComp.textContent = ''; });

    form.addEventListener('submit', (e) => {
        if (submitting) { e.preventDefault(); return; }
        const v = comp.value.trim();
        if (v.length < 2) {
            e.preventDefault();
            comp.classList.add('bad');
            errComp.textContent = 'Indiquez le nom de la compétence (2 caractères minimum).';
            comp.focus();
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
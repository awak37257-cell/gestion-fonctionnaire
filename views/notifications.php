<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';

// Access Control
if (!in_array($_SESSION['rol'] ?? '', ['admin', 'admin_local'], true) || empty($_SESSION['id_user'])) {
    header('Location: ../saisie/login.php');
    exit();
}

// CSRF Token Generation
if (empty($_SESSION['csrf_notifications'])) {
    $_SESSION['csrf_notifications'] = bin2hex(random_bytes(32));
}

$user_role   = $_SESSION['rol'];
$nom_session = $_SESSION['nom'] ?? 'Admin';
$libelles_roles = [
    'admin'       => 'Administrateur central',
    'admin_local' => 'Administrateur local',
    'consultant'  => 'Consultant',
];
$libelle_role = $libelles_roles[$user_role] ?? ucfirst($user_role);

// Helper Functions
$e = static fn($value) => htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');

$initiales = static function ($nom) {
    $t = trim((string)$nom);
    return $t === '' ? '?' : mb_strtoupper(mb_substr($t, 0, 2, 'UTF-8'), 'UTF-8');
};

// Lien sûr vers un justificatif
$lien_fichier = static function (string $base, string $chemin) {
    $parts = array_filter(
        explode('/', str_replace('\\', '/', $chemin)),
        static fn($p) => $p !== '' && $p !== '.' && $p !== '..'
    );
    if (!$parts) return '';
    return $base . implode('/', array_map('rawurlencode', $parts));
};

// Fetch Notifications
$stmt = $db->prepare("SELECT n.*, f.nom_fonc, f.prenom_fonc, f.matri, o.nom_org
    FROM NOTIFICATIONS n
    LEFT JOIN FONCTIONNAIRE f ON f.id_fonc = n.id_fonc
    LEFT JOIN ORGANISATION o ON o.id_org = n.id_org
    WHERE n.id_user = ?
    ORDER BY (n.statut = 'a_verifier') DESC, n.date_creation DESC
    LIMIT 100");
$stmt->execute([(int)$_SESSION['id_user']]);
$notifications = $stmt->fetchAll();

$types = [
    'diplome'    => ['Diplôme',    'fa-graduation-cap'],
    'competence' => ['Compétence', 'fa-award'],
    'mutation'   => ['Mutation',   'fa-route'],
];
$statuts = [
    'a_verifier' => 'À vérifier', 
    'verifie'    => 'Vérifiée', 
    'supprime'   => 'Élément supprimé'
];

// Initialisation et calcul des compteurs par statut
$compte = ['a_verifier' => 0, 'verifie' => 0, 'supprime' => 0];
foreach ($notifications as $n) {
    if (isset($compte[$n['statut']])) {
        $compte[$n['statut']]++;
    }
}

// Mise à jour de la variable pour le badge sidebar (APRES le calcul de $compte)
$notifications_a_verifier = $compte['a_verifier'];

$flash_ok = isset($_GET['success']) ? "L’opération a bien été enregistrée." : null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification des déclarations | Ministère des Affaires étrangères</title>
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
        .nav-count { margin-left: auto; min-width: 24px; padding: 1px 8px; border-radius: 999px; background: var(--accent); color: #fff; font-size: 12px; font-weight: 700; text-align: center; }

        .sidebar-user { display: flex; align-items: center; gap: 12px; padding: 16px 20px; border-top: 1px solid var(--ink-line); }
        .sidebar-user .name { font-weight: 600; font-size: 13px; line-height: 1.2; }
        .sidebar-user .role { font-size: 12px; color: rgba(255,255,255,.55); }
        .avatar { flex: none; width: 36px; height: 36px; border-radius: 50%; display: grid; place-items: center; font-size: 12px; font-weight: 700; background: var(--ink-2); color: #fff; }
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
        .icon-btn { width: 38px; height: 38px; flex: none; display: inline-grid; place-items: center; border: 1px solid var(--line); background: var(--surface); border-radius: 9px; cursor: pointer; color: var(--muted); transition: color .15s, border-color .15s, background .15s; }
        .icon-btn:hover { color: var(--text); border-color: #CBD2DC; }
        .icon-btn.danger:hover { color: var(--red); border-color: #F0C4BF; background: var(--red-soft); }
        #menuToggleBtn { display: none; }
        .org-chip { display: inline-flex; align-items: center; gap: 8px; padding: 7px 12px; background: #F7FCF9; border: 1px solid #CFE5D8; border-radius: 999px; font-weight: 600; font-size: 13px; }
        .org-chip i { color: var(--green); }

        .content { padding: 32px; max-width: 1100px; }

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
        .btn-light { background: rgba(255,255,255,.14); border-color: rgba(255,255,255,.25); color: #fff; }
        .btn-light:hover { background: rgba(255,255,255,.22); }
        .btn-ghost { background: var(--surface); border-color: var(--line); color: var(--muted); }
        .btn-ghost:hover { color: var(--text); border-color: #CBD2DC; }
        .btn-ok { background: var(--green-soft); color: #0B7A51; }
        .btn-ok:hover { background: #C9EEDB; }
        .btn-del { background: var(--red-soft); color: var(--red); }
        .btn-del:hover { background: #F6D3CF; }

        .alert { display: flex; align-items: flex-start; gap: 12px; padding: 12px 16px; margin-bottom: 20px; border-radius: 10px; font-weight: 500; background: var(--green-soft); color: #14633F; border: 1px solid #BFE3D0; }
        .alert.error { background: var(--red-soft); color: #8E2A22; border-color: #F0C4BF; }
        .alert i { margin-top: 3px; }
        .alert .close { margin-left: auto; background: none; border: 0; cursor: pointer; color: inherit; opacity: .6; }
        .alert .close:hover { opacity: 1; }

        /* ---------- Filtres ---------- */
        .chips { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 18px; }
        .chip {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 6px 14px; border-radius: 999px;
            border: 1px solid var(--line); background: var(--surface);
            font-weight: 600; font-size: 13px; color: var(--muted); cursor: pointer;
            transition: background .15s, color .15s, border-color .15s;
        }
        .chip:hover { color: var(--text); border-color: #CBD2DC; }
        .chip .n { font-size: 12px; font-weight: 700; padding: 1px 7px; border-radius: 999px; background: var(--paper); color: var(--muted); }
        .chip.active { background: var(--ink); border-color: var(--ink); color: #fff; }
        .chip.active .n { background: rgba(255,255,255,.18); color: #fff; }

        /* ---------- Déclarations ---------- */
        .item { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 20px 22px; margin-bottom: 14px; }
        .item.pending { border-left: 4px solid var(--accent); }
        .item-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 14px; }
        .item-main { display: flex; gap: 14px; min-width: 0; }
        .type-ico { width: 40px; height: 40px; flex: none; display: grid; place-items: center; border-radius: 11px; font-size: 16px; }
        .t-diplome    { background: var(--blue-soft);   color: var(--blue); }
        .t-competence { background: var(--accent-soft); color: var(--accent-dark); }
        .t-mutation   { background: var(--green-soft);  color: var(--green); }
        .item h2 { margin: 0; font-size: 16px; font-weight: 700; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .meta { margin-top: 4px; color: var(--muted); font-size: 13px; }
        .meta span + span::before { content: "·"; margin: 0 8px; color: #B5BEC9; }
        .details { margin: 14px 0; padding: 12px 14px; border-radius: 10px; background: var(--paper); line-height: 1.6; overflow-wrap: anywhere; }

        .status { display: inline-flex; padding: 3px 10px; border-radius: 999px; background: var(--accent-soft); color: var(--accent-dark); font-size: 12px; font-weight: 700; white-space: nowrap; }
        .status.verifie { background: var(--green-soft); color: #0B7A51; }
        .status.supprime { background: var(--red-soft); color: var(--red); }

        .actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .actions form { margin: 0; }

        .empty { text-align: center; padding: 56px 24px; background: var(--surface); border: 1px solid var(--line); border-radius: 14px; color: var(--muted); }
        .empty .empty-ico { width: 64px; height: 64px; margin: 0 auto 16px; display: grid; place-items: center; border-radius: 50%; background: var(--green-soft); color: var(--green); font-size: 26px; }
        .empty strong { display: block; color: var(--text); font-size: 15px; margin-bottom: 4px; }
        .empty p { margin: 0; }
        #noMatch { display: none; }

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
        }
        @media (max-width: 600px) {
            .item { padding: 16px; }
            .item-top { flex-direction: column; }
            .actions { align-items: stretch; }
            .actions form, .actions .btn { flex: 1; }
            .actions .btn { width: 100%; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { transition: none !important; animation: none !important; }
        }
    </style>
</head>
<body>

<div class="shell">

    <?php
    // Fonction initiales compatible avec l'include partagé
    if (!function_exists('initiales')) {
        function initiales($nom, $prenom = '') {
            $a = mb_substr(trim((string)$nom), 0, 1, 'UTF-8');
            $b = mb_substr(trim((string)$prenom), 0, 1, 'UTF-8');
            return mb_strtoupper($a . $b, 'UTF-8') ?: '?';
        }
    }
    if (!function_exists('e')) {
        function e($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
    }
    $sidebar_page = 'notifications'; $sidebar_base = '';
    require_once __DIR__ . '/../includes/sidebar_admin.php';
    ?>

    <main class="main">

        <header class="topbar">
            <div class="topbar-left">
                <button type="button" id="menuToggleBtn" class="icon-btn" aria-label="Ouvrir le menu" aria-controls="sidebarAdmin">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="org-chip">
                    <i class="fa-solid fa-clipboard-check"></i>
                    <span><?= (int)$compte['a_verifier'] ?> à vérifier</span>
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
                    <h1>Déclarations à vérifier</h1>
                    <p>Diplômes, compétences et mutations ajoutés ou modifiés par les agents. Vérifiez-les, ou supprimez ceux qui sont erronés.</p>
                </div>
                <a href="admin_dashboard.php" class="btn btn-light">
                    <i class="fa-solid fa-arrow-left"></i> Retour au tableau de bord
                </a>
            </div>

            <?php if ($flash_ok): ?>
                <div class="alert" role="status">
                    <i class="fa-solid fa-circle-check"></i>
                    <span><?= $e($flash_ok) ?></span>
                    <button type="button" class="close" aria-label="Fermer"><i class="fa-solid fa-xmark"></i></button>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['error'])): ?>
                <div class="alert error" role="alert">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span><?= $e($_GET['error']) ?></span>
                    <button type="button" class="close" aria-label="Fermer"><i class="fa-solid fa-xmark"></i></button>
                </div>
            <?php endif; ?>

            <?php if (!$notifications): ?>

                <div class="empty">
                    <div class="empty-ico"><i class="fa-solid fa-circle-check"></i></div>
                    <strong>Tout est à jour</strong>
                    <p>Aucune déclaration à examiner pour le moment.</p>
                </div>

            <?php else: ?>

                <div class="chips" role="group" aria-label="Filtrer par statut">
                    <button type="button" class="chip <?= $compte['a_verifier'] > 0 ? '' : 'active' ?>" data-filter="all">Toutes <span class="n"><?= count($notifications) ?></span></button>
                    <button type="button" class="chip <?= $compte['a_verifier'] > 0 ? 'active' : '' ?>" data-filter="a_verifier">À vérifier <span class="n"><?= (int)$compte['a_verifier'] ?></span></button>
                    <button type="button" class="chip" data-filter="verifie">Vérifiées <span class="n"><?= (int)$compte['verifie'] ?></span></button>
                    <button type="button" class="chip" data-filter="supprime">Supprimées <span class="n"><?= (int)$compte['supprime'] ?></span></button>
                </div>

                <?php foreach ($notifications as $notification):
                    $type_cle = $notification['type_element'];
                    [$type_lib, $type_ico] = $types[$type_cle] ?? [$type_cle, 'fa-circle-info'];
                    $ts = strtotime((string)$notification['date_creation']);
                ?>
                    <article class="item <?= $notification['statut'] === 'a_verifier' ? 'pending' : '' ?>" data-statut="<?= $e($notification['statut']) ?>">
                        <div class="item-top">
                            <div class="item-main">
                                <div class="type-ico t-<?= $e($type_cle) ?>"><i class="fa-solid <?= $e($type_ico) ?>"></i></div>
                                <div>
                                    <h2>
                                        <?= $e($notification['titre']) ?>
                                        <span class="status <?= $e($notification['statut']) ?>"><?= $e($statuts[$notification['statut']] ?? $notification['statut']) ?></span>
                                    </h2>
                                    <div class="meta">
                                        <span><?= $e($type_lib) ?></span>
                                        <span><?= $e(trim(($notification['prenom_fonc'] ?? '') . ' ' . ($notification['nom_fonc'] ?? ''))) ?></span>
                                        <?php if (!empty($notification['matri'])): ?><span><?= $e($notification['matri']) ?></span><?php endif; ?>
                                        <?php if (!empty($notification['nom_org'])): ?><span><?= $e($notification['nom_org']) ?></span><?php endif; ?>
                                        <?php if ($ts): ?><span><?= $e(date('d/m/Y H:i', $ts)) ?></span><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <a class="btn btn-ghost" href="profil_agent.php?id=<?= (int)$notification['id_fonc'] ?>"><i class="fa-solid fa-user"></i> Dossier agent</a>
                        </div>

                        <p class="details"><?= nl2br($e($notification['details'])) ?></p>

                        <div class="actions">
                            <?php if ($notification['statut'] === 'a_verifier'): ?>
                                <form method="post" action="traitement_notification.php">
                                    <input type="hidden" name="csrf" value="<?= $e($_SESSION['csrf_notifications']) ?>">
                                    <input type="hidden" name="id_notification" value="<?= (int)$notification['id_notification'] ?>">
                                    <input type="hidden" name="action" value="verifier">
                                    <button class="btn btn-ok" type="submit"><i class="fa-solid fa-check"></i> Marquer vérifiée</button>
                                </form>
                                <form method="post" action="traitement_notification.php" onsubmit="return confirm('Supprimer définitivement cet élément du dossier du fonctionnaire ?');">
                                    <input type="hidden" name="csrf" value="<?= $e($_SESSION['csrf_notifications']) ?>">
                                    <input type="hidden" name="id_notification" value="<?= (int)$notification['id_notification'] ?>">
                                    <input type="hidden" name="action" value="supprimer">
                                    <button class="btn btn-del" type="submit"><i class="fa-solid fa-trash"></i> Supprimer l’élément</button>
                                </form>

                                <?php if ($notification['type_element'] === 'diplome' && !empty($notification['id_element'])): ?>
                                    <?php
                                    $stmtFichier = $db->prepare('SELECT fich_url FROM DIPLOME WHERE id_dipl = ?');
                                    $stmtFichier->execute([(int)$notification['id_element']]);
                                    $fichierDiplome = $stmtFichier->fetchColumn();
                                    $href = $fichierDiplome ? $lien_fichier('../uploads/diplomes/', basename((string)$fichierDiplome)) : '';
                                    ?>
                                    <?php if ($href): ?><a class="btn btn-ghost" target="_blank" rel="noopener" href="<?= $e($href) ?>"><i class="fa-solid fa-file-arrow-up"></i> Justificatif</a><?php endif; ?>
                                <?php elseif ($notification['type_element'] === 'competence'): ?>
                                    <?php
                                    $stmtFichier = $db->prepare('SELECT justificatif_path FROM detenir WHERE id_comp = ? AND id_fonc = ?');
                                    $stmtFichier->execute([(int)$notification['id_element'], (int)$notification['id_fonc']]);
                                    $fichierCompetence = $stmtFichier->fetchColumn();
                                    // Le chemin enregistré peut contenir un sous-dossier (competences/xxx.pdf) : on le conserve
                                    $href = $fichierCompetence ? $lien_fichier('../uploads/', (string)$fichierCompetence) : '';
                                    ?>
                                    <?php if ($href): ?><a class="btn btn-ghost" target="_blank" rel="noopener" href="<?= $e($href) ?>"><i class="fa-solid fa-file-arrow-up"></i> Justificatif</a><?php endif; ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>

                <div class="empty" id="noMatch">
                    <div class="empty-ico"><i class="fa-solid fa-filter"></i></div>
                    <strong>Aucune déclaration dans cette catégorie</strong>
                    <p>Choisissez un autre filtre pour voir les autres déclarations.</p>
                </div>

            <?php endif; ?>

        </div>
    </main>
</div>

<script>
(function () {
    // Menu mobile
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

    // Filtres par statut
    const chips = document.querySelectorAll('.chip');
    const items = document.querySelectorAll('.item');
    const noMatch = document.getElementById('noMatch');
    if (!chips.length) return;

    function appliquer(filtre) {
        let visibles = 0;
        items.forEach(it => {
            const ok = filtre === 'all' || it.dataset.statut === filtre;
            it.style.display = ok ? '' : 'none';
            if (ok) visibles++;
        });
        if (noMatch) noMatch.style.display = visibles === 0 ? 'block' : 'none';
    }
    chips.forEach(c => c.addEventListener('click', () => {
        chips.forEach(x => x.classList.remove('active'));
        c.classList.add('active');
        appliquer(c.dataset.filter);
    }));

    const actif = document.querySelector('.chip.active');
    appliquer(actif ? actif.dataset.filter : 'all');
})();
</script>
</body>
</html>

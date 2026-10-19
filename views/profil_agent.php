<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config.php';

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
if (!function_exists('fmt_date')) {
    function fmt_date($d) {
        $t = $d ? strtotime((string)$d) : false;
        return $t ? date('d/m/Y', $t) : null;
    }
}

// Session et rôle
$role       = isset($_SESSION['rol']) ? strtolower(trim($_SESSION['rol'])) : '';
$id_session = isset($_SESSION['id_fonc']) ? (int)$_SESSION['id_fonc'] : 0;
$id_agent   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$idUserSession = (int)($_SESSION['id_user'] ?? $_SESSION['user_id'] ?? 0);
$compteAdminLocal = null;

if ($idUserSession > 0) {
    $stmtCompte = $db->prepare("SELECT id_user, id_fonc, id_org FROM UTILISATEURS WHERE id_user = ? AND rol = 'admin_local'");
    $stmtCompte->execute([$idUserSession]);
    $compteAdminLocal = $stmtCompte->fetch(PDO::FETCH_ASSOC) ?: null;
}

if ($id_session < 1 && $compteAdminLocal) {
    $id_session = (int)$compteAdminLocal['id_fonc'];
}
if ($compteAdminLocal && empty($_SESSION['csrf_mode'])) {
    $_SESSION['csrf_mode'] = bin2hex(random_bytes(32));
}
$peutBasculerAdmin = $compteAdminLocal && (int)$compteAdminLocal['id_fonc'] > 0;

// Aucune session : retour à la connexion
if ($role === '' && $id_session === 0) {
    header("Location: ../saisie/login.php");
    exit();
}

// Sans identifiant, un agent arrive sur sa propre fiche
if (!$id_agent && $id_session) {
    $id_agent = $id_session;
}

$est_admin      = in_array($role, ['admin', 'admin_local'], true);
$role_lecture   = $est_admin || in_array($role, ['consultant', 'gestionnaire'], true);
$est_mon_profil = ($id_session > 0 && $id_session === $id_agent);

if ($role === 'admin_local' && !$est_mon_profil) {
    $idOrgSession = (int)($_SESSION['id_org'] ?? 0);
    $stmtAcces = $db->prepare('SELECT 1 FROM RECEVOIR WHERE id_fonc = ? AND id_org = ? AND date_fin IS NULL LIMIT 1');
    $stmtAcces->execute([$id_agent, $idOrgSession]);
    if (!$idOrgSession || !$stmtAcces->fetchColumn()) {
        http_response_code(403);
        exit('Vous ne pouvez consulter que les dossiers des fonctionnaires de votre organisation.');
    }
}

// Un agent ne peut consulter que sa propre fiche
if (!$role_lecture && !$est_mon_profil) {
    header("Location: " . ($id_session ? "profil_agent.php?id=" . $id_session : "../saisie/login.php"));
    exit();
}

$peut_modifier = ($est_admin || $est_mon_profil);   // diplômes, compétences, fiche
$peut_parcours = $est_admin;                          // mutations et parcours : administrateurs uniquement

// Section demandée
$sections_ok = ['profil', 'diplomes', 'competences', 'mutations'];
$section = isset($_GET['section']) ? trim($_GET['section']) : 'profil';
if (!in_array($section, $sections_ok, true)) {
    $section = 'profil';
}

// Agent et affectation en cours (LEFT JOIN : un agent non affecté reste consultable)
$agent = null;
if ($id_agent) {
    $stmt = $db->prepare("
        SELECT f.*, o.nom_org, o.sigle_org, a.gra_post, a.vil_affec, r.ville_affec, r.date_debut
        FROM FONCTIONNAIRE f
        LEFT JOIN RECEVOIR r ON f.id_fonc = r.id_fonc AND r.date_fin IS NULL
        LEFT JOIN ORGANISATION o ON r.id_org = o.id_org
        LEFT JOIN AFFECTATION a ON r.id_affec = a.id_affec
        WHERE f.id_fonc = :id_f
        ORDER BY r.date_debut DESC
        LIMIT 1
    ");
    $stmt->execute([':id_f' => $id_agent]);
    $agent = $stmt->fetch();
}

$stats = ['diplomes' => 0, 'competences' => 0, 'mutations' => 0];
$diplomes = $comps = $historique = [];
$comps_erreur = false;

if ($agent) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM DIPLOME d JOIN OBTENIR o ON d.id_dipl = o.id_dipl WHERE o.id_fonc = ?");
    $stmt->execute([$id_agent]);
    $stats['diplomes'] = (int)$stmt->fetchColumn();

    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM competences c JOIN detenir d ON c.id_comp = d.id_comp WHERE d.id_fonc = ?");
        $stmt->execute([$id_agent]);
        $stats['competences'] = (int)$stmt->fetchColumn();
    } catch (PDOException $ex) {
        error_log("profil_agent.php (compétences) : " . $ex->getMessage());
    }

    $stmt = $db->prepare("SELECT COUNT(*) FROM RECEVOIR WHERE id_fonc = ?");
    $stmt->execute([$id_agent]);
    $stats['mutations'] = (int)$stmt->fetchColumn();

    if ($section === 'diplomes') {
        $stmt = $db->prepare("
            SELECT d.id_dipl, d.lib_dipl, d.niv_etu, d.fich_url, o.ann_obte
            FROM DIPLOME d
            JOIN OBTENIR o ON d.id_dipl = o.id_dipl
            WHERE o.id_fonc = :id
            ORDER BY o.ann_obte DESC
        ");
        $stmt->execute([':id' => $id_agent]);
        $diplomes = $stmt->fetchAll();
    } elseif ($section === 'competences') {
        try {
            $stmt = $db->prepare("SELECT c.id_comp, c.libelle_comp, d.niveau, d.justificatif_path
                                  FROM competences c
                                  JOIN detenir d ON c.id_comp = d.id_comp
                                  WHERE d.id_fonc = :id");
            $stmt->execute([':id' => $id_agent]);
            $comps = $stmt->fetchAll();
        } catch (PDOException $ex) {
            error_log("profil_agent.php (compétences) : " . $ex->getMessage());
            $comps_erreur = true;
        }
    } elseif ($section === 'mutations') {
        $stmt = $db->prepare("
            SELECT r.id_rec, o.nom_org, a.gra_post, a.tit_post, a.vil_affec, r.date_debut, r.date_fin
            FROM RECEVOIR r
            JOIN AFFECTATION a ON r.id_affec = a.id_affec
            JOIN ORGANISATION o ON r.id_org = o.id_org
            WHERE r.id_fonc = :id
            ORDER BY r.date_debut DESC
        ");
        $stmt->execute([':id' => $id_agent]);
        $historique = $stmt->fetchAll();
    }
}

$nom_session = trim((string)($_SESSION['nom'] ?? ''));
$avatar_nom = '';
$avatar_prenom = '';
if ($idUserSession > 0) {
    $stmtIdentiteSession = $db->prepare('SELECT f.nom_fonc, f.prenom_fonc
        FROM UTILISATEURS u
        LEFT JOIN FONCTIONNAIRE f ON f.id_fonc = u.id_fonc
        WHERE u.id_user = ? LIMIT 1');
    $stmtIdentiteSession->execute([$idUserSession]);
    $identiteSession = $stmtIdentiteSession->fetch(PDO::FETCH_ASSOC) ?: [];
    $avatar_nom = trim((string)($identiteSession['nom_fonc'] ?? ''));
    $avatar_prenom = trim((string)($identiteSession['prenom_fonc'] ?? ''));
    $nomFonctionnaireSession = trim($avatar_prenom . ' ' . $avatar_nom);
    if ($nomFonctionnaireSession !== '') {
        $nom_session = $nomFonctionnaireSession;
    }
}
$nom_session = $nom_session !== '' ? $nom_session : 'Utilisateur';
$base         = '?id=' . (int)$id_agent . '&section=';
$ville_agent  = $agent ? (!empty($agent['ville_affec']) ? $agent['ville_affec'] : ($agent['vil_affec'] ?? null)) : null;
$nom_complet  = $agent ? trim(($agent['prenom_fonc'] ?? '') . ' ' . mb_strtoupper((string)($agent['nom_fonc'] ?? ''), 'UTF-8')) : '';
$photo_ok     = $agent && !empty($agent['photo_fonc']) && file_exists(__DIR__ . '/../uploads/photos/' . $agent['photo_fonc']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dossier agent | <?= e($agent['nom_fonc'] ?? 'Introuvable') ?></title>
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
            --amber: #B7791F;
            --amber-soft: #FBF1DC;
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
        .brand-mark { width: 40px; height: 40px; flex: none; border-radius: 10px; background: var(--accent); display: grid; place-items: center; font-size: 18px; color: #fff; overflow: hidden; }
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
        .nav-count { margin-left: auto; min-width: 24px; padding: 1px 8px; border-radius: 999px; background: rgba(255,255,255,.12); font-size: 12px; font-weight: 700; text-align: center; }
        .nav-link.active .nav-count { background: var(--accent); color: #fff; }

        .sidebar-user { display: flex; align-items: center; gap: 12px; padding: 16px 20px; border-top: 1px solid var(--ink-line); }
        .sidebar-user .name { font-weight: 600; font-size: 13px; line-height: 1.2; }
        .sidebar-user .role { font-size: 12px; color: rgba(255,255,255,.55); }

        .avatar {
            flex: none; width: 36px; height: 36px; border-radius: 50%; overflow: hidden;
            display: grid; place-items: center;
            font-size: 12px; font-weight: 700; letter-spacing: .3px;
            background: var(--ink-2); color: #fff;
        }

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

        .icon-btn {
            width: 38px; height: 38px; flex: none;
            display: inline-grid; place-items: center;
            border: 1px solid var(--line); background: var(--surface);
            border-radius: 9px; cursor: pointer; color: var(--muted);
            transition: color .15s, border-color .15s, background .15s;
        }
        .icon-btn:hover { color: var(--text); border-color: #CBD2DC; }
        .icon-btn.danger:hover { color: var(--red); border-color: #F0C4BF; background: var(--red-soft); }
        #menuToggleBtn { display: none; }

        .org-chip {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 7px 12px; background: #F7FCF9; border: 1px solid #CFE5D8;
            border-radius: 999px; font-weight: 600; font-size: 13px;
        }
        .org-chip i { color: var(--green); }

        .content { padding: 32px; max-width: 1360px; }

        /* ---------- Boutons ---------- */
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
        .btn-sm { padding: 6px 12px; font-size: 12px; }

        /* ---------- En-tête agent ---------- */
        .hero {
            position: relative; overflow: hidden;
            display: flex; align-items: center; justify-content: space-between;
            gap: 24px; flex-wrap: wrap;
            margin-bottom: 24px; padding: 32px;
            border-radius: 16px; color: #fff;
            background:
                radial-gradient(520px 220px at 100% 0%, rgba(247,127,0,.38), transparent 70%),
                linear-gradient(120deg, #0A3B2C 0%, #11634A 100%);
        }
        .hero::before {
            content: ""; position: absolute; top: 0; left: 0; right: 0; height: 5px;
            background: linear-gradient(90deg, #F77F00 0 33.33%, #FFFFFF 33.33% 66.66%, #009E60 66.66% 100%);
        }
        .hero-main { display: flex; align-items: center; gap: 22px; min-width: 0; }
        .avatar-xl {
            flex: none; width: 88px; height: 88px; border-radius: 50%; overflow: hidden;
            display: grid; place-items: center;
            background: rgba(255,255,255,.14); border: 2px solid rgba(255,255,255,.5);
            font-weight: 700; font-size: 28px; letter-spacing: .5px;
        }
        .avatar-xl img { width: 100%; height: 100%; object-fit: cover; object-position: center; display: block; }
        .hero h1 { margin: 0; font-family: var(--font-display); font-weight: 400; font-size: clamp(28px, 4vw, 42px); line-height: 1.05; letter-spacing: -.01em; }
        .hero-chips { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px; }
        .hchip { display: inline-flex; align-items: center; gap: 8px; padding: 4px 12px; border-radius: 999px; background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.22); font-size: 12px; font-weight: 600; }
        .hchip i { color: #FFB066; }
        .hero-actions { display: flex; gap: 10px; flex-wrap: wrap; }

        /* ---------- Onglets (mobile) ---------- */
        .tabs { display: none; gap: 6px; overflow-x: auto; margin-bottom: 20px; padding-bottom: 2px; }
        .tab { flex: none; display: inline-flex; align-items: center; gap: 8px; padding: 8px 14px; border-radius: 999px; border: 1px solid var(--line); background: var(--surface); font-weight: 600; font-size: 13px; color: var(--muted); }
        .tab.active { background: var(--ink); border-color: var(--ink); color: #fff; }
        .tab .n { font-size: 12px; font-weight: 700; padding: 0 7px; border-radius: 999px; background: var(--paper); color: var(--muted); }
        .tab.active .n { background: rgba(255,255,255,.18); color: #fff; }

        /* ---------- KPI ---------- */
        .kpis {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            background: var(--surface); border: 1px solid var(--line);
            border-radius: 14px; margin-bottom: 24px; overflow: hidden;
        }
        .kpi { display: flex; align-items: center; gap: 16px; padding: 20px 24px; border-right: 1px solid var(--line); transition: filter .15s; }
        .kpi:last-child { border-right: 0; }
        .kpi:hover { filter: brightness(.98); }
        .kpi:nth-child(1) { background: linear-gradient(135deg, #E3ECFF, #FFFFFF 75%); }
        .kpi:nth-child(2) { background: linear-gradient(135deg, #FFF0DC, #FFFFFF 75%); }
        .kpi:nth-child(3) { background: linear-gradient(135deg, #E6F6EE, #FFFFFF 75%); }
        .kpi-icon { width: 42px; height: 42px; flex: none; display: grid; place-items: center; border-radius: 12px; font-size: 16px; color: #fff; box-shadow: 0 6px 14px rgba(0,0,0,.12); }
        .kpi-icon.i-blue  { background: var(--blue); }
        .kpi-icon.i-warm  { background: var(--accent); }
        .kpi-icon.i-green { background: var(--green); }
        .kpi-value { font-family: var(--font-display); font-size: 34px; line-height: 1; }
        .kpi-label { color: var(--muted); font-size: 13px; font-weight: 500; margin-top: 4px; }

        /* ---------- Panneaux ---------- */
        .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; }
        .panel + .panel { margin-top: 24px; }
        .panel-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 20px 24px; }
        .panel-title { margin: 0; font-size: 16px; font-weight: 700; display: flex; align-items: center; gap: 10px; }
        .panel-title i { color: var(--accent); font-size: 15px; }
        .panel-sub { margin: 4px 0 0; color: var(--muted); font-size: 13px; }

        .facts { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); border-top: 1px solid var(--line); }
        .fact { padding: 18px 24px; border-bottom: 1px solid var(--line-soft); }
        .fact dt { display: flex; align-items: center; gap: 8px; margin: 0 0 4px; color: var(--muted); font-size: 12px; font-weight: 600; }
        .fact dt i { color: var(--green); width: 14px; text-align: center; }
        .fact dd { margin: 0; font-weight: 600; font-size: 15px; word-break: break-word; }
        .fact dd small { display: block; margin-top: 2px; color: var(--muted); font-weight: 500; font-size: 12px; }
        .na { color: var(--muted); font-weight: 500; font-style: italic; }

        /* ---------- Tables ---------- */
        .table-wrap { overflow-x: auto; border-top: 1px solid var(--line); }
        table { width: 100%; border-collapse: collapse; }
        thead th {
            text-align: left; white-space: nowrap; padding: 12px 16px;
            font-size: 12px; font-weight: 600; color: var(--ink-2);
            background: #EEF5F0; border-bottom: 1px solid var(--line);
        }
        thead th:first-child, tbody td:first-child { padding-left: 24px; }
        thead th:last-child,  tbody td:last-child  { padding-right: 24px; }
        tbody td { padding: 14px 16px; border-bottom: 1px solid var(--line-soft); vertical-align: middle; }
        tbody tr:last-child td { border-bottom: 0; }
        tbody tr:hover { background: #F3FAF6; }
        .center { text-align: center; }

        .pill { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap; background: var(--accent-soft); color: var(--accent-dark); }
        .pill.green { background: var(--green-soft); color: #0B7A51; }
        .pill.violet { background: var(--violet-soft); color: #5B21B6; }

        .doc-link { display: inline-flex; align-items: center; gap: 8px; padding: 5px 12px; border-radius: 8px; background: var(--blue-soft); color: var(--blue); font-weight: 600; font-size: 12px; transition: background .15s, color .15s; }
        .doc-link:hover { background: var(--blue); color: #fff; }

        .act { width: 32px; height: 32px; display: inline-grid; place-items: center; border-radius: 8px; color: var(--muted); font-size: 13px; transition: background .15s, color .15s; }
        .act:hover { background: var(--paper); color: var(--text); }
        .act.edit:hover   { background: var(--amber-soft); color: var(--amber); }
        .act.delete:hover { background: var(--red-soft);   color: var(--red); }

        .empty { text-align: center; padding: 56px 24px !important; color: var(--muted); }
        .empty .empty-ico { width: 64px; height: 64px; margin: 0 auto 16px; display: grid; place-items: center; border-radius: 50%; background: var(--paper); color: #B5BEC9; font-size: 26px; }
        .empty strong { display: block; color: var(--text); font-size: 15px; margin-bottom: 4px; }
        .empty p { margin: 0 auto; max-width: 46ch; }
        .panel > .empty { border-top: 1px solid var(--line); }

        /* ---------- Parcours (timeline) ---------- */
        .timeline { list-style: none; margin: 0; padding: 8px 24px 24px 24px; border-top: 1px solid var(--line); }
        .tl-item { position: relative; padding: 0 0 22px 32px; }
        .tl-item:last-child { padding-bottom: 0; }
        .tl-item::before { content: ""; position: absolute; left: 7px; top: 22px; bottom: -4px; width: 2px; background: var(--line); }
        .tl-item:last-child::before { display: none; }
        .tl-dot { position: absolute; left: 0; top: 18px; width: 16px; height: 16px; border-radius: 50%; background: var(--surface); border: 3px solid #B5BEC9; }
        .tl-item.current .tl-dot { border-color: var(--green); background: var(--green); box-shadow: 0 0 0 4px rgba(14,159,104,.18); }
        .tl-card { margin-top: 10px; padding: 16px 18px; border: 1px solid var(--line); border-radius: 12px; background: var(--surface); }
        .tl-item.current .tl-card { border-color: #BFE3D0; background: #F7FCF9; }
        .tl-top { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
        .tl-org { font-weight: 700; font-size: 15px; }
        .tl-sub { margin-top: 2px; color: var(--muted); font-size: 13px; }
        .tl-meta { display: flex; gap: 18px; flex-wrap: wrap; margin-top: 10px; color: var(--muted); font-size: 13px; font-weight: 500; }
        .tl-meta i { color: var(--accent); margin-right: 6px; }
        .tl-actions { display: flex; gap: 4px; margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--line-soft); }

        .alert { display: flex; align-items: center; gap: 12px; margin: 0 24px 20px; padding: 12px 16px; border-radius: 10px; background: var(--red-soft); color: #8E2A22; border: 1px solid #F0C4BF; font-weight: 500; }

        .flash { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; padding: 12px 16px; border-radius: 10px; background: var(--green-soft); color: #14633F; border: 1px solid #BFE3D0; font-weight: 500; }
        .flash .close { margin-left: auto; background: none; border: 0; cursor: pointer; color: inherit; opacity: .6; }
        .flash .close:hover { opacity: 1; }

        .overlay { position: fixed; inset: 0; z-index: 50; background: rgba(10,59,44,.5); opacity: 0; pointer-events: none; transition: opacity .25s; }
        .overlay.show { opacity: 1; pointer-events: auto; }

        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); box-shadow: 0 0 40px rgba(0,0,0,.3); }
            .main { margin-left: 0; width: 100%; }
            #menuToggleBtn { display: inline-grid; }
            .topbar { padding: 12px 16px; }
            .content { padding: 20px 16px 32px; }
            .hero { padding: 26px 20px; }
            .hero-main { flex-wrap: wrap; }
            .tabs { display: flex; }
            .kpi { border-right: 0; border-bottom: 1px solid var(--line); }
            .kpi:last-child { border-bottom: 0; }
            .panel-head { padding-left: 16px; padding-right: 16px; }
            .fact { padding-left: 16px; padding-right: 16px; }
            .timeline { padding-left: 16px; padding-right: 16px; }
            thead th:first-child, tbody td:first-child { padding-left: 16px; }
            thead th:last-child,  tbody td:last-child  { padding-right: 16px; }
        }

        /* ---------- Impression de la fiche ---------- */
        @media print {
            body { background: #fff; }
            .sidebar, .topbar, .tabs, .hero-actions, .overlay, .kpis, .act, .tl-actions, .btn { display: none !important; }
            .main { margin: 0; width: 100%; }
            .content { padding: 0; max-width: none; }
            .hero { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .panel { break-inside: avoid; box-shadow: none; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { transition: none !important; animation: none !important; }
        }
    </style>
</head>
<body>

<div class="shell">

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar" aria-label="Navigation du dossier">
        <div class="brand">
            <div class="brand-mark"><i class="fa-solid fa-building-columns"></i></div>
            <div>
                <div class="brand-name">Ministère des Affaires étrangères</div>
                <div class="brand-sub">Ressources humaines</div>
            </div>
        </div>

        <nav class="nav">
            <?php if ($role_lecture): ?>
                <div class="nav-group">Général</div>
                <a href="admin_dashboard.php" class="nav-link">
                    <i class="fa-solid fa-chart-pie"></i> Tableau de bord
                </a>
                <a href="liste_fonctionnaires.php" class="nav-link">
                    <i class="fa-solid fa-table-list"></i> Liste complète
                </a>
            <?php endif; ?>

            <?php if ($agent): ?>
                <div class="nav-group">Dossier de l’agent</div>
                <a href="<?= $base ?>profil" class="nav-link <?= $section === 'profil' ? 'active' : '' ?>">
                    <i class="fa-solid fa-id-card"></i> Fiche agent
                </a>
                <a href="<?= $base ?>diplomes" class="nav-link <?= $section === 'diplomes' ? 'active' : '' ?>">
                    <i class="fa-solid fa-graduation-cap"></i> Diplômes
                    <span class="nav-count"><?= $stats['diplomes'] ?></span>
                </a>
                <a href="<?= $base ?>competences" class="nav-link <?= $section === 'competences' ? 'active' : '' ?>">
                    <i class="fa-solid fa-award"></i> Compétences
                    <span class="nav-count"><?= $stats['competences'] ?></span>
                </a>
                <a href="<?= $base ?>mutations" class="nav-link <?= $section === 'mutations' ? 'active' : '' ?>">
                    <i class="fa-solid fa-route"></i> Parcours
                    <span class="nav-count"><?= $stats['mutations'] ?></span>
                </a>
            <?php endif; ?>

            <div class="nav-group">Mon compte</div>
            <?php if ($peutBasculerAdmin): ?>
                <form method="post" action="changer_mode.php">
                    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf_mode']) ?>">
                    <input type="hidden" name="mode" value="<?= $role === 'admin_local' ? 'fonctionnaire' : 'admin_local' ?>">
                    <button type="submit" class="nav-link" style="width:100%;border:0;background:transparent;text-align:left;font:inherit;cursor:pointer;">
                        <i class="fa-solid <?= $role === 'admin_local' ? 'fa-id-card' : 'fa-user-shield' ?>"></i>
                        <?= $role === 'admin_local' ? 'Espace fonctionnaire' : 'Espace administrateur local' ?>
                    </button>
                </form>
            <?php endif; ?>
            <a href="modifier_mdp.php" class="nav-link">
                <i class="fa-solid fa-lock"></i> Sécurité et mot de passe
            </a>
        </nav>

        <div class="sidebar-user">
            <div class="avatar"><?= e(initiales($avatar_nom !== '' ? $avatar_nom : $nom_session, $avatar_prenom)) ?></div>
            <div>
                <div class="name"><?= e($nom_session) ?></div>
                <div class="role"><?= $est_admin ? ($role === 'admin' ? 'Administrateur central' : 'Administrateur local') : ($role === 'consultant' ? 'Consultant' : 'Agent') ?></div>
            </div>
        </div>
    </aside>
    <div class="overlay" id="overlay"></div>

    <main class="main">

        <header class="topbar">
            <div class="topbar-left">
                <button type="button" id="menuToggleBtn" class="icon-btn" aria-label="Ouvrir le menu" aria-controls="sidebar">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="org-chip">
                    <i class="fa-solid fa-folder-open"></i>
                    <span><?= $est_mon_profil ? 'Mon dossier' : 'Dossier de l’agent' ?></span>
                </div>
            </div>
            <div class="topbar-right">
                <a href="../logout.php" class="icon-btn danger" title="Se déconnecter" aria-label="Se déconnecter">
                    <i class="fa-solid fa-power-off"></i>
                </a>
            </div>
        </header>

        <div class="content">

        <?php if (!$agent): ?>

            <section class="panel">
                <div class="empty">
                    <div class="empty-ico"><i class="fa-solid fa-user-slash"></i></div>
                    <strong>Agent introuvable</strong>
                    <p>L’agent recherché n’existe pas ou a été supprimé.</p>
                    <?php if ($role_lecture): ?>
                        <p style="margin-top:18px;"><a href="liste_fonctionnaires.php" class="btn btn-ghost"><i class="fa-solid fa-arrow-left"></i> Retour à la liste</a></p>
                    <?php endif; ?>
                </div>
            </section>

        <?php else: ?>

            <?php if (isset($_GET['success']) || (($_GET['msg'] ?? '') === 'update_success')): ?>
                <div class="flash" role="status">
                    <i class="fa-solid fa-circle-check"></i>
                    <span>Les modifications ont bien été enregistrées.</span>
                    <button type="button" class="close" aria-label="Fermer" onclick="this.parentNode.remove()"><i class="fa-solid fa-xmark"></i></button>
                </div>
            <?php endif; ?>

            <!-- En-tête de l'agent -->
            <div class="hero">
                <div class="hero-main">
                    <div class="avatar-xl">
                        <?php if ($photo_ok): ?>
                            <img src="../uploads/photos/<?= e($agent['photo_fonc']) ?>" alt="<?= e($nom_complet) ?>">
                        <?php else: ?>
                            <?= e(initiales($agent['nom_fonc'], $agent['prenom_fonc'])) ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h1><?= e($nom_complet) ?></h1>
                        <div class="hero-chips">
                            <span class="hchip"><i class="fa-solid fa-hashtag"></i> Matricule <?= e($agent['matri']) ?></span>
                            <?php if (!empty($agent['nom_org'])): ?>
                                <span class="hchip"><i class="fa-solid fa-sitemap"></i> <?= e($agent['nom_org']) ?><?= !empty($agent['sigle_org']) ? ' (' . e($agent['sigle_org']) . ')' : '' ?></span>
                            <?php else: ?>
                                <span class="hchip"><i class="fa-solid fa-triangle-exclamation"></i> Non affecté</span>
                            <?php endif; ?>
                            <?php if (!empty($agent['gra_post'])): ?>
                                <span class="hchip"><i class="fa-solid fa-award"></i> Grade <?= e($agent['gra_post']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="hero-actions">
                    <?php if ($section === 'profil'): ?>
                        <button type="button" class="btn btn-light" onclick="window.print()">
                            <i class="fa-solid fa-print"></i> Imprimer
                        </button>
                    <?php endif; ?>
                    <?php if ($peut_modifier): ?>
                        <a href="modifier_agent.php?id=<?= (int)$id_agent ?>" class="btn btn-primary">
                            <i class="fa-solid fa-pen"></i> Modifier le profil
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Onglets (petits écrans) -->
            <nav class="tabs" aria-label="Sections du dossier">
                <a href="<?= $base ?>profil" class="tab <?= $section === 'profil' ? 'active' : '' ?>"><i class="fa-solid fa-id-card"></i> Fiche</a>
                <a href="<?= $base ?>diplomes" class="tab <?= $section === 'diplomes' ? 'active' : '' ?>">Diplômes <span class="n"><?= $stats['diplomes'] ?></span></a>
                <a href="<?= $base ?>competences" class="tab <?= $section === 'competences' ? 'active' : '' ?>">Compétences <span class="n"><?= $stats['competences'] ?></span></a>
                <a href="<?= $base ?>mutations" class="tab <?= $section === 'mutations' ? 'active' : '' ?>">Parcours <span class="n"><?= $stats['mutations'] ?></span></a>
            </nav>

            <?php if ($section === 'diplomes'): ?>

                <section class="panel" aria-labelledby="secTitre">
                    <div class="panel-head">
                        <div>
                            <h2 class="panel-title" id="secTitre"><i class="fa-solid fa-graduation-cap"></i> Diplômes et certifications</h2>
                            <p class="panel-sub">Tous les diplômes obtenus par l’agent</p>
                        </div>
                        <?php if ($peut_modifier): ?>
                            <a href="../saisie/ajouter_diplome.php?id=<?= (int)$agent['id_fonc'] ?>" class="btn btn-primary">
                                <i class="fa-solid fa-plus"></i> Ajouter un diplôme
                            </a>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($diplomes)): ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Diplôme</th>
                                    <th>Niveau d’étude</th>
                                    <th>Année</th>
                                    <th class="center">Justificatif</th>
                                    <?php if ($peut_modifier): ?><th style="text-align:right;">Actions</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($diplomes as $dp): ?>
                                <tr>
                                    <td><strong><?= e($dp['lib_dipl']) ?></strong></td>
                                    <td><?= e($dp['niv_etu']) ?></td>
                                    <td><?= e($dp['ann_obte']) ?></td>
                                    <td class="center">
                                        <?php if (!empty($dp['fich_url'])): ?>
                                            <a href="../uploads/diplomes/<?= e($dp['fich_url']) ?>" target="_blank" rel="noopener" class="doc-link">
                                                <i class="fa-solid fa-file-pdf"></i> Voir le PDF
                                            </a>
                                        <?php else: ?>
                                            <span class="na">Non fourni</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($peut_modifier): ?>
                                    <td style="text-align:right;">
                                        <a href="../saisie/modifier_diplome.php?id_agent=<?= (int)$id_agent ?>&id_dipl=<?= (int)$dp['id_dipl'] ?>" class="act edit" title="Modifier" aria-label="Modifier ce diplôme">
                                            <i class="fa-solid fa-pen"></i>
                                        </a>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                        <div class="empty">
                            <div class="empty-ico"><i class="fa-solid fa-folder-open"></i></div>
                            <strong>Aucun diplôme enregistré</strong>
                            <p>Les diplômes de l’agent apparaîtront ici.</p>
                        </div>
                    <?php endif; ?>
                </section>

            <?php elseif ($section === 'competences'): ?>

                <section class="panel" aria-labelledby="secTitre">
                    <div class="panel-head">
                        <div>
                            <h2 class="panel-title" id="secTitre"><i class="fa-solid fa-award"></i> Compétences</h2>
                            <p class="panel-sub">Expertises validées par l’agent</p>
                        </div>
                        <?php if ($peut_modifier): ?>
                            <a href="../saisie/ajouter_competence.php?id=<?= (int)$id_agent ?>" class="btn btn-primary">
                                <i class="fa-solid fa-plus"></i> Ajouter une compétence
                            </a>
                        <?php endif; ?>
                    </div>

                    <?php if ($comps_erreur): ?>
                        <div class="alert" role="alert"><i class="fa-solid fa-circle-exclamation"></i> Les compétences n’ont pas pu être chargées. Réessayez dans un instant.</div>
                    <?php elseif (!empty($comps)): ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Compétence</th>
                                    <th>Niveau de maîtrise</th>
                                    <th class="center">Justificatif</th>
                                    <?php if ($peut_modifier): ?><th style="text-align:right;">Actions</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($comps as $c): ?>
                                <tr>
                                    <td><strong><?= e($c['libelle_comp']) ?></strong></td>
                                    <td><span class="pill"><?= e($c['niveau']) ?></span></td>
                                    <td class="center">
                                        <?php if (!empty($c['justificatif_path'])): ?>
                                            <a href="../uploads/<?= e($c['justificatif_path']) ?>" target="_blank" rel="noopener" class="doc-link">
                                                <i class="fa-solid fa-file-pdf"></i> Voir
                                            </a>
                                        <?php else: ?>
                                            <span class="na">Aucun</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($peut_modifier): ?>
                                    <td style="text-align:right;">
                                        <a href="../saisie/modifier_competence.php?id_agent=<?= (int)$id_agent ?>&id_comp=<?= (int)$c['id_comp'] ?>" class="act edit" title="Modifier" aria-label="Modifier cette compétence">
                                            <i class="fa-solid fa-pen"></i>
                                        </a>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                        <div class="empty">
                            <div class="empty-ico"><i class="fa-solid fa-award"></i></div>
                            <strong>Aucune compétence enregistrée</strong>
                            <p>Les compétences de l’agent apparaîtront ici.</p>
                        </div>
                    <?php endif; ?>
                </section>

            <?php elseif ($section === 'mutations'): ?>

                <section class="panel" aria-labelledby="secTitre">
                    <div class="panel-head">
                        <div>
                            <h2 class="panel-title" id="secTitre"><i class="fa-solid fa-route"></i> Historique de carrière</h2>
                            <p class="panel-sub">Mouvements et affectations, du plus récent au plus ancien</p>
                        </div>
                        <?php if ($peut_parcours): ?>
                            <a href="../saisie/muter_agent.php?id=<?= (int)$id_agent ?>" class="btn btn-primary">
                                <i class="fa-solid fa-paper-plane"></i> Nouvelle mutation
                            </a>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($historique)): ?>
                    <ol class="timeline">
                        <?php foreach ($historique as $h):
                            $en_cours = empty($h['date_fin']);
                            $debut = fmt_date($h['date_debut']);
                            $fin   = fmt_date($h['date_fin']);
                        ?>
                        <li class="tl-item <?= $en_cours ? 'current' : '' ?>">
                            <span class="tl-dot"></span>
                            <div class="tl-card">
                                <div class="tl-top">
                                    <span class="tl-org"><?= e($h['nom_org']) ?></span>
                                    <?php if ($en_cours): ?><span class="pill green">En cours</span><?php endif; ?>
                                </div>
                                <div class="tl-sub">
                                    <?= e($h['gra_post']) ?><?= !empty($h['tit_post']) ? ' · ' . e($h['tit_post']) : '' ?>
                                </div>
                                <div class="tl-meta">
                                    <span><i class="fa-solid fa-location-dot"></i><?= e($h['vil_affec'] ?? 'Ville non précisée') ?></span>
                                    <span>
                                        <i class="fa-regular fa-calendar"></i>
                                        <?= $debut ? e($debut) : '—' ?> au <?= $en_cours ? 'aujourd’hui' : ($fin ? e($fin) : '—') ?>
                                    </span>
                                </div>
                                <?php if ($peut_parcours): ?>
                                <div class="tl-actions">
                                    <a href="../saisie/modifier_parcours.php?id_rec=<?= (int)$h['id_rec'] ?>&id_agent=<?= (int)$id_agent ?>" class="btn btn-ghost btn-sm">
                                        <i class="fa-solid fa-pen"></i> Modifier
                                    </a>
                                    <a href="../saisie/supprimer_parcours.php?id_rec=<?= (int)$h['id_rec'] ?>&id_agent=<?= (int)$id_agent ?>" class="btn btn-ghost btn-sm act delete" style="width:auto;height:auto;"
                                       data-confirm="Confirmez-vous la suppression de cette étape du parcours ? Utilisez cette action pour corriger une erreur.">
                                        <i class="fa-solid fa-trash-can"></i> Supprimer
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ol>
                    <?php else: ?>
                        <div class="empty">
                            <div class="empty-ico"><i class="fa-solid fa-route"></i></div>
                            <strong>Aucun historique de carrière</strong>
                            <p>Les affectations de l’agent apparaîtront ici.</p>
                        </div>
                    <?php endif; ?>
                </section>

            <?php else: ?>

                <section class="kpis" aria-label="Résumé du dossier">
                    <a href="<?= $base ?>diplomes" class="kpi">
                        <div class="kpi-icon i-blue"><i class="fa-solid fa-graduation-cap"></i></div>
                        <div>
                            <div class="kpi-value"><?= $stats['diplomes'] ?></div>
                            <div class="kpi-label">Diplômes</div>
                        </div>
                    </a>
                    <a href="<?= $base ?>competences" class="kpi">
                        <div class="kpi-icon i-warm"><i class="fa-solid fa-award"></i></div>
                        <div>
                            <div class="kpi-value"><?= $stats['competences'] ?></div>
                            <div class="kpi-label">Compétences</div>
                        </div>
                    </a>
                    <a href="<?= $base ?>mutations" class="kpi">
                        <div class="kpi-icon i-green"><i class="fa-solid fa-route"></i></div>
                        <div>
                            <div class="kpi-value"><?= $stats['mutations'] ?></div>
                            <div class="kpi-label">Étapes de carrière</div>
                        </div>
                    </a>
                </section>

                <section class="panel" aria-labelledby="persTitre">
                    <div class="panel-head">
                        <h2 class="panel-title" id="persTitre"><i class="fa-solid fa-address-card"></i> Données personnelles</h2>
                    </div>
                    <dl class="facts" style="margin:0;">
                        <div class="fact">
                            <dt><i class="fa-solid fa-user"></i> Nom complet</dt>
                            <dd><?= e(trim($agent['nom_fonc'] . ' ' . $agent['prenom_fonc'])) ?></dd>
                        </div>
                        <div class="fact">
                            <dt><i class="fa-solid fa-envelope"></i> E-mail</dt>
                            <dd><?= !empty($agent['email_perso']) ? e($agent['email_perso']) : '<span class="na">Non renseigné</span>' ?></dd>
                        </div>
                        <div class="fact">
                            <dt><i class="fa-solid fa-phone"></i> Téléphone</dt>
                            <dd><?= !empty($agent['cont']) ? e($agent['cont']) : '<span class="na">Non renseigné</span>' ?></dd>
                        </div>
                        <div class="fact">
                            <dt><i class="fa-solid fa-cake-candles"></i> Date de naissance</dt>
                            <dd><?= ($n = fmt_date($agent['date_naiss'] ?? null)) ? e($n) : '<span class="na">Non renseignée</span>' ?></dd>
                        </div>
                    </dl>
                </section>

                <section class="panel" aria-labelledby="proTitre">
                    <div class="panel-head">
                        <h2 class="panel-title" id="proTitre"><i class="fa-solid fa-briefcase"></i> Situation professionnelle</h2>
                    </div>
                    <dl class="facts" style="margin:0;">
                        <div class="fact">
                            <dt><i class="fa-solid fa-building"></i> Organisation</dt>
                            <dd>
                                <?php if (!empty($agent['nom_org'])): ?>
                                    <?= e($agent['nom_org']) ?>
                                    <?php if (!empty($agent['sigle_org'])): ?><small><?= e($agent['sigle_org']) ?></small><?php endif; ?>
                                <?php else: ?>
                                    <span class="na">Non affecté</span>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <div class="fact">
                            <dt><i class="fa-solid fa-chart-line"></i> Grade</dt>
                            <dd><?= !empty($agent['gra_post']) ? '<span class="pill">' . e($agent['gra_post']) . '</span>' : '<span class="na">Non défini</span>' ?></dd>
                        </div>
                        <div class="fact">
                            <dt><i class="fa-solid fa-location-dot"></i> Ville d’affectation</dt>
                            <dd><?= !empty($ville_agent) ? e($ville_agent) : '<span class="na">Non spécifiée</span>' ?></dd>
                        </div>
                        <div class="fact">
                            <dt><i class="fa-solid fa-calendar-check"></i> En poste depuis</dt>
                            <dd><?= ($n = fmt_date($agent['date_debut'] ?? null)) ? e($n) : '<span class="na">Non renseignée</span>' ?></dd>
                        </div>
                    </dl>
                </section>

            <?php endif; ?>

        <?php endif; ?>

        </div>
    </main>
</div>

<script>
    // Menu mobile
    (function () {
        const toggleBtn = document.getElementById('menuToggleBtn');
        const sidebar   = document.getElementById('sidebar');
        const overlay   = document.getElementById('overlay');
        if (!toggleBtn || !sidebar) return;
        function setOpen(open) {
            sidebar.classList.toggle('open', open);
            overlay.classList.toggle('show', open);
            toggleBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
        toggleBtn.addEventListener('click', () => setOpen(!sidebar.classList.contains('open')));
        overlay.addEventListener('click', () => setOpen(false));
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') setOpen(false); });
    })();

    // Confirmation avant suppression
    document.querySelectorAll('[data-confirm]').forEach(link => {
        link.addEventListener('click', (e) => {
            if (!confirm(link.getAttribute('data-confirm'))) e.preventDefault();
        });
    });
</script>
</body>
</html>
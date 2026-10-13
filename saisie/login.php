<?php
session_start();
require_once '../config.php';

if (!function_exists('e')) {
    function e($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
}

$error = null;
$user_input = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_input = isset($_POST['iden']) ? trim($_POST['iden']) : '';
    $pass_input = isset($_POST['password']) ? $_POST['password'] : '';

    try {
        $stmt = $db->prepare("
            SELECT u.*, o.sigle_org
            FROM UTILISATEURS u
            LEFT JOIN ORGANISATION o ON u.id_org = o.id_org
            WHERE u.iden = ?
        ");
        $stmt->execute([$user_input]);
        $comptes_trouves = $stmt->fetchAll();

        $u = null;

        foreach ($comptes_trouves as $compte) {
            $colonne_pwd = $compte['mot_de_pas'] ?? $compte['password'] ?? null;

            if ($colonne_pwd && password_verify($pass_input, $colonne_pwd)) {
                $u = $compte;
                break;
            }
        }

        if ($u) {
            session_regenerate_id(true);

            $_SESSION['id_user'] = $u['id_user'] ?? $u['id'];
            $_SESSION['rol']     = $u['rol'];
            $_SESSION['nom']     = $u['iden'];
            $_SESSION['id_fonc'] = $u['id_fonc'] ?? null;
            $_SESSION['id_org']  = $u['id_org'];
            $_SESSION['sigle']   = $u['sigle_org'];

            $action = "Connexion à la plateforme";
            $log = $db->prepare("INSERT INTO HISTORIQUE (id_user, action_desc, dat_hist) VALUES (?, ?, NOW())");
            $log->execute([$_SESSION['id_user'], $action]);

            if (in_array($u['rol'], ['admin', 'admin_local', 'gestionnaire'])) {
                header("Location: ../views/admin_dashboard.php");
            } else {
                header("Location: ../views/profil_agent.php?id=" . (int)($u['id_fonc'] ?? 0));
            }
            exit();

        } else {
            sleep(1);
            $error = "Identifiant ou mot de passe incorrect.";
        }
    } catch (Exception $ex) {
        error_log("login.php : " . $ex->getMessage());
        $error = "Le service est momentanément indisponible. Réessayez dans un instant.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion | Ministère des Affaires étrangères</title>
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
            min-height: 100vh;
            display: grid; place-items: center;
            padding: 24px;
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

        .card {
            width: 100%; max-width: 940px;
            display: grid; grid-template-columns: 5fr 6fr;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 24px 60px -24px rgba(10,59,44,.28);
        }

        /* Panneau gauche */
        .aside {
            position: relative; overflow: hidden;
            display: flex; flex-direction: column; justify-content: space-between; gap: 32px;
            padding: 40px 32px 32px;
            color: #fff;
            background:
                radial-gradient(420px 260px at 100% 0%, rgba(247,127,0,.38), transparent 70%),
                linear-gradient(150deg, #0A3B2C 0%, #11634A 100%);
        }
        .aside::before {
            content: ""; position: absolute; top: 0; left: 0; right: 0; height: 5px;
            background: linear-gradient(90deg, #F77F00 0 33.33%, #FFFFFF 33.33% 66.66%, #009E60 66.66% 100%);
        }
        .brand { display: flex; align-items: center; gap: 12px; }
        .brand-mark { width: 44px; height: 44px; flex: none; border-radius: 12px; background: var(--accent); display: grid; place-items: center; font-size: 19px; color: #fff; }
        .brand-name { font-weight: 700; font-size: 14px; line-height: 1.25; }
        .brand-sub  { font-size: 12px; color: rgba(255,255,255,.65); }

        .aside h2 { margin: 0 0 8px; font-family: var(--font-display); font-weight: 400; font-size: 34px; line-height: 1.08; letter-spacing: -.01em; }
        .aside p  { margin: 0 0 20px; color: rgba(255,255,255,.75); font-size: 13px; max-width: 34ch; }
        .points { list-style: none; margin: 0; padding: 0; display: grid; gap: 12px; }
        .points li { display: flex; align-items: center; gap: 12px; font-size: 13px; font-weight: 500; color: rgba(255,255,255,.88); }
        .points .ico { width: 30px; height: 30px; flex: none; display: grid; place-items: center; border-radius: 8px; background: rgba(255,255,255,.12); color: #FFB066; font-size: 13px; }

        /* Formulaire */
        .main { padding: 44px 40px 32px; display: flex; flex-direction: column; justify-content: center; }
        .main h1 { margin: 0; font-family: var(--font-display); font-weight: 400; font-size: 36px; line-height: 1.05; letter-spacing: -.01em; }
        .lead { margin: 8px 0 26px; color: var(--muted); }

        .alert { display: flex; align-items: flex-start; gap: 10px; padding: 12px 14px; margin-bottom: 18px; border-radius: 10px; background: var(--red-soft); color: #8E2A22; border: 1px solid #F0C4BF; font-weight: 500; }
        .alert i { margin-top: 3px; }

        .field { margin-bottom: 18px; }
        .field label { display: block; margin-bottom: 6px; font-size: 13px; font-weight: 600; }
        .control-wrap { position: relative; }
        .control-wrap > .lead-ico { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 13px; pointer-events: none; }
        .control {
            width: 100%; height: 46px; padding: 0 44px 0 40px;
            background: var(--surface); border: 1px solid #D5DBE3; border-radius: 9px;
            transition: border-color .15s, box-shadow .15s;
        }
        .control::placeholder { color: #9AA5B3; }
        .control:hover { border-color: #B9C2CE; }
        .control:focus { outline: none; border-color: var(--green); box-shadow: 0 0 0 3px rgba(14,159,104,.15); }

        .eye {
            position: absolute; right: 6px; top: 50%; transform: translateY(-50%);
            width: 34px; height: 34px; display: grid; place-items: center;
            border: 0; border-radius: 8px; background: none; cursor: pointer; color: var(--muted);
            transition: background .15s, color .15s;
        }
        .eye:hover { background: var(--paper); color: var(--text); }

        .caps { display: none; align-items: center; gap: 8px; margin-top: 8px; font-size: 12px; font-weight: 600; color: var(--accent-dark); }
        .caps.show { display: flex; }

        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            width: 100%; height: 48px; margin-top: 6px; border: 0; border-radius: 10px; cursor: pointer;
            background: var(--accent); color: #fff; font-weight: 700; font-size: 14px;
            box-shadow: 0 6px 16px rgba(247,127,0,.3);
            transition: background .15s, transform .1s;
        }
        .btn:hover { background: #FF8F1F; }
        .btn:active { transform: translateY(1px); }
        .btn[disabled] { opacity: .75; pointer-events: none; }
        .btn .spin { display: none; width: 16px; height: 16px; border: 2px solid rgba(255,255,255,.4); border-top-color: #fff; border-radius: 50%; animation: spin .7s linear infinite; }
        .btn.loading .spin { display: inline-block; }
        .btn.loading .ico { display: none; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .foot { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-top: 24px; padding-top: 18px; border-top: 1px solid var(--line); color: var(--muted); font-size: 12px; }
        .foot i { color: var(--green); margin-right: 6px; }
        .back { display: inline-flex; align-items: center; gap: 8px; font-weight: 600; font-size: 13px; transition: color .15s; }
        .back:hover { color: var(--text); }

        @media (max-width: 820px) {
            .card { grid-template-columns: 1fr; max-width: 520px; }
            .aside { padding: 32px 24px 24px; gap: 22px; }
            .aside p { margin-bottom: 14px; }
            .points { display: none; }
            .main { padding: 30px 24px 24px; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { transition: none !important; animation-duration: .001ms !important; }
        }
    </style>
</head>
<body>

<div class="card">

    <aside class="aside">
        <div class="brand">
            <div class="brand-mark"><i class="fa-solid fa-building-columns"></i></div>
            <div>
                <div class="brand-name">Ministère des Affaires étrangères</div>
                <div class="brand-sub">Ressources humaines</div>
            </div>
        </div>

        <div>
            <h2>Le portail RH du ministère</h2>
            <p>Un accès unique aux dossiers du personnel, aux archives et au suivi des affectations.</p>
            <ul class="points">
                <li><span class="ico"><i class="fa-solid fa-users"></i></span> Dossiers et carrières des agents</li>
                <li><span class="ico"><i class="fa-solid fa-folder-open"></i></span> Archives numériques des documents</li>
                <li><span class="ico"><i class="fa-solid fa-route"></i></span> Suivi des mutations et affectations</li>
            </ul>
        </div>
    </aside>

    <main class="main">
        <h1>Connexion</h1>
        <p class="lead">Saisissez vos identifiants pour accéder à votre espace.</p>

        <?php if ($error): ?>
            <div class="alert" role="alert">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" id="loginForm" autocomplete="on">
            <div class="field">
                <label for="iden">Identifiant ou matricule</label>
                <div class="control-wrap">
                    <i class="fa-solid fa-user lead-ico"></i>
                    <input type="text" id="iden" name="iden" class="control" value="<?= e($user_input) ?>" placeholder="Votre identifiant" autocomplete="username" autocapitalize="none" spellcheck="false" required <?= $error ? '' : 'autofocus' ?>>
                </div>
            </div>

            <div class="field">
                <label for="password">Mot de passe</label>
                <div class="control-wrap">
                    <i class="fa-solid fa-lock lead-ico"></i>
                    <input type="password" id="password" name="password" class="control" placeholder="Votre mot de passe" autocomplete="current-password" required <?= $error ? 'autofocus' : '' ?>>
                    <button type="button" class="eye" id="eyeBtn" aria-label="Afficher le mot de passe"><i class="fa-solid fa-eye"></i></button>
                </div>
                <div class="caps" id="capsWarn" role="status"><i class="fa-solid fa-triangle-exclamation"></i> Verr. Maj. activé</div>
            </div>

            <button type="submit" class="btn" id="submitBtn">
                <i class="fa-solid fa-right-to-bracket ico"></i>
                <span class="spin"></span>
                <span>Se connecter</span>
            </button>
        </form>

        <div class="foot">
            <span><i class="fa-solid fa-shield-halved"></i> Accès réservé au personnel autorisé. Les connexions sont enregistrées.</span>
            <a href="../index.php" class="back"><i class="fa-solid fa-arrow-left"></i> Retour à l’accueil</a>
        </div>
    </main>
</div>

<script>
(function () {
    const form = document.getElementById('loginForm');
    const pwd = document.getElementById('password');
    const eye = document.getElementById('eyeBtn');
    const caps = document.getElementById('capsWarn');
    const btn = document.getElementById('submitBtn');

    // Afficher / masquer le mot de passe
    eye.addEventListener('click', () => {
        const show = pwd.type === 'password';
        pwd.type = show ? 'text' : 'password';
        eye.setAttribute('aria-label', show ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
        eye.firstElementChild.className = show ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
    });

    // Alerte Verr. Maj.
    function checkCaps(e) {
        if (e.getModifierState) caps.classList.toggle('show', e.getModifierState('CapsLock'));
    }
    pwd.addEventListener('keydown', checkCaps);
    pwd.addEventListener('keyup', checkCaps);
    pwd.addEventListener('blur', () => caps.classList.remove('show'));

    // Évite le double envoi
    form.addEventListener('submit', () => {
        btn.classList.add('loading');
        setTimeout(() => btn.setAttribute('disabled', 'disabled'), 0);
    });
})();
</script>
</body>
</html>
<?php
session_start();
if (!isset($_SESSION['id_user'])) {
    header("Location: ../saisie/login.php");
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

// Nom de l'utilisateur pour l'affichage
$user_name = $_SESSION['nom'] ?? 'Utilisateur';
$role      = $_SESSION['rol'] ?? '';

// Jeton CSRF (à vérifier dans traitement_update_mdp.php avec hash_equals)
if (empty($_SESSION['csrf_mdp'])) {
    $_SESSION['csrf_mdp'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_mdp'];

// Lien de retour selon le profil (ces chemins fonctionnent depuis views/ comme depuis saisie/)
if (in_array($role, ['admin', 'admin_local', 'consultant'], true)) {
    $retour_url = '../views/admin_dashboard.php';
    $retour_txt = 'Retour au tableau de bord';
} elseif (!empty($_SESSION['id_fonc'])) {
    $retour_url = '../views/profil_agent.php?id=' . (int)$_SESSION['id_fonc'];
    $retour_txt = 'Retour à mon profil';
} else {
    $retour_url = '../views/admin_dashboard.php';
    $retour_txt = 'Retour';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sécurité | Modification du mot de passe</title>
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
            --amber: #D98A0B;
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

        /* ---------- Carte ---------- */
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
        .logo {
            width: 48px; height: 48px; flex: none; border-radius: 12px; overflow: hidden;
            display: grid; place-items: center;
            background: #fff; color: var(--ink); font-size: 20px;
        }
        .logo img { width: 80%; height: 80%; object-fit: contain; display: block; }
        .brand-name { font-weight: 700; font-size: 14px; line-height: 1.25; }
        .brand-sub  { font-size: 12px; color: rgba(255,255,255,.65); }

        .aside h2 { margin: 0 0 6px; font-family: var(--font-display); font-weight: 400; font-size: 30px; line-height: 1.1; letter-spacing: -.01em; }
        .aside p  { margin: 0 0 18px; color: rgba(255,255,255,.75); font-size: 13px; }

        .rules { list-style: none; margin: 0; padding: 0; display: grid; gap: 10px; }
        .rules li { display: flex; align-items: center; gap: 10px; font-size: 13px; font-weight: 500; color: rgba(255,255,255,.7); transition: color .2s; }
        .rules .tick {
            width: 20px; height: 20px; flex: none; display: grid; place-items: center;
            border-radius: 50%; font-size: 10px;
            border: 1.5px solid rgba(255,255,255,.35); color: transparent;
            transition: background .2s, border-color .2s, color .2s;
        }
        .rules li.ok { color: #fff; }
        .rules li.ok .tick { background: var(--green); border-color: var(--green); color: #fff; }

        /* Panneau droit */
        .main { padding: 40px 36px 32px; }
        .main h1 { margin: 0; font-family: var(--font-display); font-weight: 400; font-size: 34px; line-height: 1.05; letter-spacing: -.01em; }
        .user-chip {
            display: inline-flex; align-items: center; gap: 10px;
            margin: 14px 0 22px; padding: 4px 14px 4px 4px;
            background: var(--paper); border: 1px solid var(--line); border-radius: 999px;
            font-weight: 600; font-size: 13px;
        }
        .avatar { width: 28px; height: 28px; border-radius: 50%; display: grid; place-items: center; background: var(--green-soft); color: var(--green); font-size: 11px; font-weight: 700; }

        .alert { display: flex; align-items: flex-start; gap: 10px; padding: 12px 14px; margin-bottom: 18px; border-radius: 10px; background: var(--red-soft); color: #8E2A22; border: 1px solid #F0C4BF; font-weight: 500; }
        .alert i { margin-top: 3px; }
        .alert[hidden] { display: none; }

        .field { margin-bottom: 18px; }
        .field label { display: block; margin-bottom: 6px; font-size: 13px; font-weight: 600; }
        .control-wrap { position: relative; }
        .control-wrap > .lead { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 13px; pointer-events: none; }
        .control {
            width: 100%; height: 44px; padding: 0 44px 0 40px;
            background: var(--surface); border: 1px solid #D5DBE3; border-radius: 9px;
            transition: border-color .15s, box-shadow .15s;
        }
        .control::placeholder { color: #9AA5B3; }
        .control:hover { border-color: #B9C2CE; }
        .control:focus { outline: none; border-color: var(--green); box-shadow: 0 0 0 3px rgba(14,159,104,.15); }
        .control.ok  { border-color: var(--green); }
        .control.bad { border-color: var(--red); }

        .eye {
            position: absolute; right: 6px; top: 50%; transform: translateY(-50%);
            width: 32px; height: 32px; display: grid; place-items: center;
            border: 0; border-radius: 8px; background: none; cursor: pointer; color: var(--muted);
            transition: background .15s, color .15s;
        }
        .eye:hover { background: var(--paper); color: var(--text); }

        .divider { display: flex; align-items: center; gap: 12px; margin: 22px 0 18px; color: var(--muted); font-size: 12px; font-weight: 600; }
        .divider::before, .divider::after { content: ""; flex: 1; height: 1px; background: var(--line); }

        /* Jauge de force */
        .meter { display: flex; align-items: center; gap: 10px; margin-top: 10px; }
        .segs { flex: 1; display: grid; grid-template-columns: repeat(4, 1fr); gap: 4px; }
        .segs span { height: 5px; border-radius: 999px; background: var(--line); transition: background .2s; }
        .meter-label { min-width: 62px; text-align: right; font-size: 12px; font-weight: 700; color: var(--muted); }

        .hint { min-height: 18px; margin-top: 6px; font-size: 12px; font-weight: 600; color: var(--muted); }
        .hint.ok  { color: var(--green); }
        .hint.bad { color: var(--red); }

        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            width: 100%; height: 46px; border: 0; border-radius: 10px; cursor: pointer;
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

        .back { display: inline-flex; align-items: center; gap: 8px; margin-top: 18px; color: var(--muted); font-weight: 600; font-size: 13px; transition: color .15s; }
        .back:hover { color: var(--text); }

        @media (max-width: 820px) {
            .card { grid-template-columns: 1fr; max-width: 520px; }
            .aside { padding: 32px 24px 24px; gap: 22px; }
            .main { padding: 28px 24px 24px; }
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
            <div class="logo">
                <img src="../public/images.jpg" alt="" onerror="this.remove(); this.parentNode.innerHTML='<i class=&quot;fa-solid fa-building-columns&quot;></i>';">
            </div>
            <div>
                <div class="brand-name">Ministère des Affaires étrangères</div>
                <div class="brand-sub">Ressources humaines</div>
            </div>
        </div>

        <div>
            <h2>Un compte bien protégé</h2>
            <p>Choisissez un mot de passe que vous n’utilisez nulle part ailleurs.</p>
            <ul class="rules" id="rules">
                <li data-rule="len"><span class="tick"><i class="fa-solid fa-check"></i></span> Au moins 8 caractères</li>
                <li data-rule="upper"><span class="tick"><i class="fa-solid fa-check"></i></span> Une lettre majuscule</li>
                <li data-rule="digit"><span class="tick"><i class="fa-solid fa-check"></i></span> Un chiffre</li>
                <li data-rule="symbol"><span class="tick"><i class="fa-solid fa-check"></i></span> Un symbole (par exemple ! ? # @)</li>
            </ul>
        </div>
    </aside>

    <main class="main">
        <h1>Changer le mot de passe</h1>
        <div class="user-chip">
            <span class="avatar"><?= e(initiales($user_name)) ?></span>
            <?= e($user_name) ?>
        </div>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert" role="alert">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= e($_GET['error']) ?></span>
            </div>
        <?php endif; ?>

        <div class="alert" id="clientError" role="alert" hidden>
            <i class="fa-solid fa-circle-exclamation"></i>
            <span id="clientErrorText"></span>
        </div>

        <form id="passwordForm" action="../actions/traitement_update_mdp.php" method="POST" novalidate>
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

            <div class="field">
                <label for="oldPassword">Ancien mot de passe</label>
                <div class="control-wrap">
                    <i class="fa-solid fa-lock lead"></i>
                    <input class="control" type="password" name="old_password" id="oldPassword" autocomplete="current-password" required>
                    <button type="button" class="eye" data-target="oldPassword" aria-label="Afficher le mot de passe"><i class="fa-solid fa-eye"></i></button>
                </div>
            </div>

            <div class="divider">Nouveau mot de passe</div>

            <div class="field">
                <label for="newPassword">Nouveau mot de passe</label>
                <div class="control-wrap">
                    <i class="fa-solid fa-key lead"></i>
                    <input class="control" type="password" name="new_password" id="newPassword" autocomplete="new-password" minlength="8" required>
                    <button type="button" class="eye" data-target="newPassword" aria-label="Afficher le mot de passe"><i class="fa-solid fa-eye"></i></button>
                </div>
                <div class="meter" aria-live="polite">
                    <div class="segs" id="segs"><span></span><span></span><span></span><span></span></div>
                    <div class="meter-label" id="meterLabel"></div>
                </div>
            </div>

            <div class="field">
                <label for="confirmPassword">Confirmer le nouveau mot de passe</label>
                <div class="control-wrap">
                    <i class="fa-solid fa-shield-halved lead"></i>
                    <input class="control" type="password" name="confirm_password" id="confirmPassword" autocomplete="new-password" required>
                    <button type="button" class="eye" data-target="confirmPassword" aria-label="Afficher le mot de passe"><i class="fa-solid fa-eye"></i></button>
                </div>
                <div class="hint" id="matchMessage" aria-live="polite"></div>
            </div>

            <button type="submit" id="submitBtn" class="btn">
                <i class="fa-solid fa-floppy-disk ico"></i>
                <span class="spin"></span>
                <span>Enregistrer le mot de passe</span>
            </button>
        </form>

        <a href="<?= e($retour_url) ?>" class="back">
            <i class="fa-solid fa-arrow-left"></i> <?= e($retour_txt) ?>
        </a>
    </main>
</div>

<script>
(function () {
    const oldInput = document.getElementById('oldPassword');
    const newInput = document.getElementById('newPassword');
    const confInput = document.getElementById('confirmPassword');
    const segs = document.querySelectorAll('#segs span');
    const meterLabel = document.getElementById('meterLabel');
    const matchMsg = document.getElementById('matchMessage');
    const rules = document.querySelectorAll('#rules li');
    const form = document.getElementById('passwordForm');
    const submitBtn = document.getElementById('submitBtn');
    const clientError = document.getElementById('clientError');
    const clientErrorText = document.getElementById('clientErrorText');

    // Afficher / masquer
    document.querySelectorAll('.eye').forEach(btn => {
        btn.addEventListener('click', () => {
            const input = document.getElementById(btn.dataset.target);
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.setAttribute('aria-label', show ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
            btn.firstElementChild.className = show ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
        });
    });

    // Critères et force
    const LEVELS = [
        { n: 0, c: 'var(--line)',  t: '' },
        { n: 1, c: 'var(--red)',   t: 'Faible' },
        { n: 2, c: 'var(--amber)', t: 'Moyen' },
        { n: 3, c: 'var(--green)', t: 'Fort' },
        { n: 4, c: '#087A50',      t: 'Excellent' }
    ];

    function evaluate() {
        const v = newInput.value;
        const checks = {
            len:    v.length >= 8,
            upper:  /[A-Z]/.test(v),
            digit:  /[0-9]/.test(v),
            symbol: /[^A-Za-z0-9]/.test(v)
        };
        rules.forEach(li => li.classList.toggle('ok', !!checks[li.dataset.rule]));

        let score = Object.values(checks).filter(Boolean).length + (v.length >= 12 ? 1 : 0);
        let level;
        if (v.length === 0) level = LEVELS[0];
        else if (score <= 2) level = LEVELS[1];
        else if (score === 3) level = LEVELS[2];
        else if (score === 4) level = LEVELS[3];
        else level = LEVELS[4];

        segs.forEach((s, i) => { s.style.background = i < level.n ? level.c : 'var(--line)'; });
        meterLabel.textContent = level.t;
        meterLabel.style.color = level.n ? level.c : 'var(--muted)';
    }

    function checkMatch() {
        confInput.classList.remove('ok', 'bad');
        matchMsg.className = 'hint';
        if (!confInput.value) { matchMsg.textContent = ''; return; }
        if (confInput.value === newInput.value) {
            confInput.classList.add('ok'); matchMsg.classList.add('ok');
            matchMsg.textContent = 'Les mots de passe correspondent';
        } else {
            confInput.classList.add('bad'); matchMsg.classList.add('bad');
            matchMsg.textContent = 'Les mots de passe ne correspondent pas';
        }
    }

    newInput.addEventListener('input', () => { evaluate(); checkMatch(); });
    confInput.addEventListener('input', checkMatch);

    function showError(msg, field) {
        clientErrorText.textContent = msg;
        clientError.hidden = false;
        if (field) field.focus();
    }

    // Contrôles avant envoi
    form.addEventListener('submit', (e) => {
        clientError.hidden = true;

        if (!oldInput.value) { e.preventDefault(); showError('Saisissez votre ancien mot de passe.', oldInput); return; }
        if (newInput.value.length < 8) { e.preventDefault(); showError('Le nouveau mot de passe doit contenir au moins 8 caractères.', newInput); return; }
        if (newInput.value === oldInput.value) { e.preventDefault(); showError('Le nouveau mot de passe doit être différent de l’ancien.', newInput); return; }
        if (newInput.value !== confInput.value) { e.preventDefault(); showError('Les deux mots de passe ne correspondent pas.', confInput); return; }

        // Le formulaire part vers traitement_update_mdp.php ; on évite seulement le double clic
        submitBtn.classList.add('loading');
        setTimeout(() => submitBtn.setAttribute('disabled', 'disabled'), 0);
    });
})();
</script>
</body>
</html>
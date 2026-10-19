<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config.php';

// Le jeton est dans l'URL : on empêche sa fuite vers des sites tiers (polices, icônes) et sa mise en cache
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, max-age=0');

$token = trim((string)($_GET['token'] ?? ''));
$tokenValide = false;
if (preg_match('/\A[a-f0-9]{64}\z/i', $token)) {
    $stmt = $db->prepare('SELECT id_reset FROM PASSWORD_RESET_TOKENS WHERE token_hash = ? AND date_utilisation IS NULL AND date_expiration > NOW() LIMIT 1');
    $stmt->execute([hash('sha256', $token)]);
    $tokenValide = (bool)$stmt->fetchColumn();
}
if (empty($_SESSION['csrf_password_reset'])) {
    $_SESSION['csrf_password_reset'] = bin2hex(random_bytes(32));
}
$e = static fn($value) => htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
$error = (string)($_GET['error'] ?? '');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="no-referrer">
    <meta name="robots" content="noindex, nofollow">
    <title>Nouveau mot de passe | Ministère des Affaires étrangères</title>
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

        .card {
            width: 100%; max-width: 940px;
            display: grid; grid-template-columns: 5fr 6fr;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 24px 60px -24px rgba(10,59,44,.28);
        }

        .aside {
            position: relative; overflow: hidden;
            display: flex; flex-direction: column; justify-content: space-between; gap: 32px;
            padding: 40px 32px 32px;
            color: #fff;
            background:
                radial-gradient(420px 260px at 100% 0%, rgba(247,127,0,.38), transparent 70%),
                linear-gradient(150deg, #0A3B2C 0%, #11634A 100%);
        }
        .aside::before { content: ""; position: absolute; top: 0; left: 0; right: 0; height: 5px; background: linear-gradient(90deg, #F77F00 0 33.33%, #FFFFFF 33.33% 66.66%, #009E60 66.66% 100%); }
        .brand { display: flex; align-items: center; gap: 12px; }
        .brand-mark { width: 44px; height: 44px; flex: none; border-radius: 12px; background: var(--accent); display: grid; place-items: center; font-size: 19px; color: #fff; }
        .brand-name { font-weight: 700; font-size: 14px; line-height: 1.25; }
        .brand-sub  { font-size: 12px; color: rgba(255,255,255,.65); }

        .aside h2 { margin: 0 0 8px; font-family: var(--font-display); font-weight: 400; font-size: 34px; line-height: 1.08; letter-spacing: -.01em; }
        .aside p  { margin: 0 0 20px; color: rgba(255,255,255,.75); font-size: 13px; max-width: 34ch; }

        .rules { list-style: none; margin: 0; padding: 0; display: grid; gap: 10px; }
        .rules li { display: flex; align-items: center; gap: 10px; font-size: 13px; font-weight: 500; color: rgba(255,255,255,.7); transition: color .2s; }
        .rules .tick { width: 20px; height: 20px; flex: none; display: grid; place-items: center; border-radius: 50%; font-size: 10px; border: 1.5px solid rgba(255,255,255,.35); color: transparent; transition: background .2s, border-color .2s, color .2s; }
        .rules li.ok { color: #fff; }
        .rules li.ok .tick { background: var(--green); border-color: var(--green); color: #fff; }

        .main { padding: 44px 40px 32px; display: flex; flex-direction: column; justify-content: center; }
        .main h1 { margin: 0; font-family: var(--font-display); font-weight: 400; font-size: 36px; line-height: 1.05; letter-spacing: -.01em; }
        .lead { margin: 8px 0 24px; color: var(--muted); }

        .alert { display: flex; align-items: flex-start; gap: 10px; padding: 12px 14px; margin-bottom: 18px; border-radius: 10px; background: var(--red-soft); color: #8E2A22; border: 1px solid #F0C4BF; font-weight: 500; }
        .alert i { margin-top: 3px; }
        .alert[hidden] { display: none; }

        .field { margin-bottom: 18px; }
        .field label { display: block; margin-bottom: 6px; font-size: 13px; font-weight: 600; }
        .control-wrap { position: relative; }
        .control-wrap > .lead-ico { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 13px; pointer-events: none; }
        .control {
            width: 100%; height: 46px; padding: 0 44px 0 40px;
            background: var(--surface); border: 1px solid #D5DBE3; border-radius: 9px;
            transition: border-color .15s, box-shadow .15s;
        }
        .control:hover { border-color: #B9C2CE; }
        .control:focus { outline: none; border-color: var(--green); box-shadow: 0 0 0 3px rgba(14,159,104,.15); }
        .control.ok  { border-color: var(--green); }
        .control.bad { border-color: var(--red); }
        .eye { position: absolute; right: 6px; top: 50%; transform: translateY(-50%); width: 34px; height: 34px; display: grid; place-items: center; border: 0; border-radius: 8px; background: none; cursor: pointer; color: var(--muted); transition: background .15s, color .15s; }
        .eye:hover { background: var(--paper); color: var(--text); }

        .meter { display: flex; align-items: center; gap: 10px; margin-top: 10px; }
        .segs { flex: 1; display: grid; grid-template-columns: repeat(4, 1fr); gap: 4px; }
        .segs span { height: 5px; border-radius: 999px; background: var(--line); transition: background .2s; }
        .meter-label { min-width: 62px; text-align: right; font-size: 12px; font-weight: 700; color: var(--muted); }
        .hint { min-height: 18px; margin-top: 6px; font-size: 12px; font-weight: 600; color: var(--muted); }
        .hint.ok  { color: var(--green); }
        .hint.bad { color: var(--red); }

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

        .foot { margin-top: 24px; padding-top: 18px; border-top: 1px solid var(--line); font-size: 13px; }
        .back { display: inline-flex; align-items: center; gap: 8px; font-weight: 600; color: var(--muted); transition: color .15s; }
        .back:hover { color: var(--text); }

        @media (max-width: 820px) {
            .card { grid-template-columns: 1fr; max-width: 520px; }
            .aside { padding: 32px 24px 24px; gap: 22px; }
            .aside p { margin-bottom: 14px; }
            .rules { display: none; }
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
            <h2>Un nouveau départ, en sécurité</h2>
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
        <h1>Nouveau mot de passe</h1>

        <?php if (!$tokenValide): ?>

            <p class="lead">Ce lien ne peut plus être utilisé.</p>
            <div class="alert" role="alert">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span>Ce lien est invalide, a déjà été utilisé ou a expiré. Demandez un nouveau lien.</span>
            </div>
            <a href="mot_de_passe_oublie.php" class="btn" style="text-decoration:none;"><i class="fa-solid fa-arrow-rotate-right"></i> Demander un nouveau lien</a>
            <div class="foot"><a class="back" href="login.php"><i class="fa-solid fa-arrow-left"></i> Retour à la connexion</a></div>

        <?php else: ?>

            <p class="lead">Choisissez un mot de passe d’au moins 8 caractères.</p>

            <?php if ($error === 'password'): ?>
                <div class="alert" role="alert"><i class="fa-solid fa-circle-exclamation"></i><span>Les mots de passe doivent contenir au moins 8 caractères et être identiques.</span></div>
            <?php elseif ($error === 'invalid'): ?>
                <div class="alert" role="alert"><i class="fa-solid fa-circle-exclamation"></i><span>Ce lien n’est plus valide. Demandez-en un nouveau.</span></div>
            <?php endif; ?>

            <div class="alert" id="clientError" role="alert" hidden>
                <i class="fa-solid fa-circle-exclamation"></i><span id="clientErrorText"></span>
            </div>

            <form method="post" action="../actions/traitement_reinitialiser_mdp.php" id="resetForm" novalidate>
                <input type="hidden" name="csrf" value="<?= $e($_SESSION['csrf_password_reset']) ?>">
                <input type="hidden" name="token" value="<?= $e($token) ?>">

                <div class="field">
                    <label for="password">Nouveau mot de passe</label>
                    <div class="control-wrap">
                        <i class="fa-solid fa-key lead-ico"></i>
                        <input class="control" type="password" id="password" name="password" autocomplete="new-password" minlength="8" required autofocus>
                        <button type="button" class="eye" data-target="password" aria-label="Afficher le mot de passe"><i class="fa-solid fa-eye"></i></button>
                    </div>
                    <div class="meter" aria-live="polite">
                        <div class="segs" id="segs"><span></span><span></span><span></span><span></span></div>
                        <div class="meter-label" id="meterLabel"></div>
                    </div>
                </div>

                <div class="field">
                    <label for="confirm_password">Confirmer le mot de passe</label>
                    <div class="control-wrap">
                        <i class="fa-solid fa-shield-halved lead-ico"></i>
                        <input class="control" type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" minlength="8" required>
                        <button type="button" class="eye" data-target="confirm_password" aria-label="Afficher le mot de passe"><i class="fa-solid fa-eye"></i></button>
                    </div>
                    <div class="hint" id="matchMessage" aria-live="polite"></div>
                </div>

                <button type="submit" class="btn" id="submitBtn">
                    <i class="fa-solid fa-floppy-disk ico"></i><span class="spin"></span>
                    <span>Enregistrer le nouveau mot de passe</span>
                </button>
            </form>

            <div class="foot"><a class="back" href="login.php"><i class="fa-solid fa-arrow-left"></i> Retour à la connexion</a></div>

        <?php endif; ?>
    </main>
</div>

<?php if ($tokenValide): ?>
<script>
(function () {
    const form = document.getElementById('resetForm');
    const pwd = document.getElementById('password');
    const conf = document.getElementById('confirm_password');
    const segs = document.querySelectorAll('#segs span');
    const meterLabel = document.getElementById('meterLabel');
    const matchMsg = document.getElementById('matchMessage');
    const rules = document.querySelectorAll('#rules li');
    const btn = document.getElementById('submitBtn');
    const clientError = document.getElementById('clientError');
    const clientErrorText = document.getElementById('clientErrorText');
    let submitting = false;

    // Afficher / masquer
    document.querySelectorAll('.eye').forEach(b => {
        b.addEventListener('click', () => {
            const input = document.getElementById(b.dataset.target);
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            b.setAttribute('aria-label', show ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
            b.firstElementChild.className = show ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
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
        const v = pwd.value;
        const checks = { len: v.length >= 8, upper: /[A-Z]/.test(v), digit: /[0-9]/.test(v), symbol: /[^A-Za-z0-9]/.test(v) };
        rules.forEach(li => li.classList.toggle('ok', !!checks[li.dataset.rule]));
        const score = Object.values(checks).filter(Boolean).length + (v.length >= 12 ? 1 : 0);
        let level;
        if (!v.length) level = LEVELS[0];
        else if (score <= 2) level = LEVELS[1];
        else if (score === 3) level = LEVELS[2];
        else if (score === 4) level = LEVELS[3];
        else level = LEVELS[4];
        segs.forEach((s, i) => { s.style.background = i < level.n ? level.c : 'var(--line)'; });
        meterLabel.textContent = level.t;
        meterLabel.style.color = level.n ? level.c : 'var(--muted)';
    }
    function checkMatch() {
        conf.classList.remove('ok', 'bad');
        matchMsg.className = 'hint';
        if (!conf.value) { matchMsg.textContent = ''; return; }
        if (conf.value === pwd.value) {
            conf.classList.add('ok'); matchMsg.classList.add('ok');
            matchMsg.textContent = 'Les mots de passe correspondent';
        } else {
            conf.classList.add('bad'); matchMsg.classList.add('bad');
            matchMsg.textContent = 'Les mots de passe ne correspondent pas';
        }
    }
    pwd.addEventListener('input', () => { evaluate(); checkMatch(); });
    conf.addEventListener('input', checkMatch);

    function fail(msg, field) {
        clientErrorText.textContent = msg;
        clientError.hidden = false;
        field.focus();
    }

    form.addEventListener('submit', (e) => {
        if (submitting) { e.preventDefault(); return; }
        clientError.hidden = true;
        if (pwd.value.length < 8) { e.preventDefault(); fail('Le mot de passe doit contenir au moins 8 caractères.', pwd); return; }
        if (pwd.value !== conf.value) { e.preventDefault(); fail('Les deux mots de passe ne correspondent pas.', conf); return; }
        // Le formulaire part vers traitement_reinitialiser_mdp.php ; on évite seulement le double envoi
        submitting = true;
        btn.classList.add('loading');
    });
})();
</script>
<?php endif; ?>
</body>
</html>
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config.php';

if (empty($_SESSION['csrf_password_reset_request'])) {
    $_SESSION['csrf_password_reset_request'] = bin2hex(random_bytes(32));
}
$e = static fn($value) => htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
$sent = isset($_GET['sent']);

// Message d'erreur : seule une liste de cas connus est affichée (aucun texte venant de l'URL)
$message_erreur = null;
if (isset($_GET['error'])) {
    $message_erreur = ($_GET['error'] === 'limit')
        ? "Trop de demandes pour le moment. Patientez quelques minutes avant de réessayer."
        : "La demande n’a pas pu être traitée. Rechargez la page et réessayez.";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié | Ministère des Affaires étrangères</title>
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
        .steps { list-style: none; margin: 0; padding: 0; display: grid; gap: 12px; counter-reset: step; }
        .steps li { display: flex; align-items: center; gap: 12px; font-size: 13px; font-weight: 500; color: rgba(255,255,255,.88); counter-increment: step; }
        .steps li::before { content: counter(step); width: 28px; height: 28px; flex: none; display: grid; place-items: center; border-radius: 50%; background: rgba(255,255,255,.14); color: #FFB066; font-weight: 700; font-size: 13px; }

        .main { padding: 44px 40px 32px; display: flex; flex-direction: column; justify-content: center; }
        .main h1 { margin: 0; font-family: var(--font-display); font-weight: 400; font-size: 36px; line-height: 1.05; letter-spacing: -.01em; }
        .lead { margin: 8px 0 24px; color: var(--muted); }

        .alert { display: flex; align-items: flex-start; gap: 10px; padding: 12px 14px; margin-bottom: 18px; border-radius: 10px; background: var(--red-soft); color: #8E2A22; border: 1px solid #F0C4BF; font-weight: 500; }
        .alert.ok { background: var(--green-soft); color: #14633F; border-color: #BFE3D0; }
        .alert i { margin-top: 3px; }

        .field { margin-bottom: 8px; }
        .field label { display: block; margin-bottom: 6px; font-size: 13px; font-weight: 600; }
        .control-wrap { position: relative; }
        .control-wrap > i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 13px; pointer-events: none; }
        .control {
            width: 100%; height: 46px; padding: 0 14px 0 40px;
            background: var(--surface); border: 1px solid #D5DBE3; border-radius: 9px;
            transition: border-color .15s, box-shadow .15s;
        }
        .control::placeholder { color: #9AA5B3; }
        .control:hover { border-color: #B9C2CE; }
        .control:focus { outline: none; border-color: var(--green); box-shadow: 0 0 0 3px rgba(14,159,104,.15); }
        .hint { margin-top: 8px; color: var(--muted); font-size: 12px; }

        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            width: 100%; height: 48px; margin-top: 18px; border: 0; border-radius: 10px; cursor: pointer;
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
        .btn.ghost { background: var(--surface); border: 1px solid var(--line); color: var(--text); box-shadow: none; }
        .btn.ghost:hover { background: var(--paper); }
        @keyframes spin { to { transform: rotate(360deg); } }

        .foot { margin-top: 24px; padding-top: 18px; border-top: 1px solid var(--line); font-size: 13px; }
        .back { display: inline-flex; align-items: center; gap: 8px; font-weight: 600; color: var(--muted); transition: color .15s; }
        .back:hover { color: var(--text); }
        .again { margin-top: 14px; display: inline-block; font-weight: 600; color: var(--green); }
        .again:hover { text-decoration: underline; }

        @media (max-width: 820px) {
            .card { grid-template-columns: 1fr; max-width: 520px; }
            .aside { padding: 32px 24px 24px; gap: 22px; }
            .aside p { margin-bottom: 14px; }
            .steps { display: none; }
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
            <h2>Retrouvez l’accès à votre compte</h2>
            <p>Un lien de réinitialisation vous sera envoyé par e-mail.</p>
            <ol class="steps">
                <li>Indiquez votre e-mail ou votre matricule</li>
                <li>Ouvrez le message reçu et cliquez sur le lien</li>
                <li>Choisissez un nouveau mot de passe</li>
            </ol>
        </div>
    </aside>

    <main class="main">
        <h1>Mot de passe oublié</h1>

        <?php if ($sent): ?>

            <p class="lead">Vérifiez votre messagerie.</p>
            <div class="alert ok" role="status">
                <i class="fa-solid fa-circle-check"></i>
                <span>Si un compte correspond à ces informations, un lien de réinitialisation vient d’être envoyé. Pensez à vérifier vos courriers indésirables.</span>
            </div>
            <a href="login.php" class="btn ghost"><i class="fa-solid fa-arrow-left"></i> Retour à la connexion</a>
            <a href="mot_de_passe_oublie.php" class="again">Renvoyer un lien</a>

        <?php else: ?>

            <p class="lead">Saisissez votre e-mail ou matricule. Si un compte correspond, un lien de réinitialisation sera envoyé à l’adresse associée.</p>

            <?php if ($message_erreur): ?>
                <div class="alert" role="alert"><i class="fa-solid fa-circle-exclamation"></i><span><?= $e($message_erreur) ?></span></div>
            <?php endif; ?>

            <form method="post" action="../actions/traitement_mdp_oublie.php" id="resetForm">
                <input type="hidden" name="csrf" value="<?= $e($_SESSION['csrf_password_reset_request']) ?>">

                <div class="field">
                    <label for="identifier">E-mail ou matricule</label>
                    <div class="control-wrap">
                        <i class="fa-solid fa-envelope"></i>
                        <input class="control" type="text" id="identifier" name="identifier" autocomplete="username" autocapitalize="none" spellcheck="false" required maxlength="255" autofocus>
                    </div>
                    <div class="hint">Pour un e-mail partagé, un lien distinct sera envoyé pour chaque compte correspondant.</div>
                </div>

                <button type="submit" class="btn" id="submitBtn">
                    <i class="fa-solid fa-paper-plane ico"></i><span class="spin"></span>
                    <span>Envoyer le lien</span>
                </button>
            </form>

            <div class="foot">
                <a class="back" href="login.php"><i class="fa-solid fa-arrow-left"></i> Retour à la connexion</a>
            </div>

        <?php endif; ?>
    </main>
</div>

<script>
(function () {
    const form = document.getElementById('resetForm');
    if (!form) return;
    const btn = document.getElementById('submitBtn');
    // Évite les envois multiples (un double clic enverrait plusieurs e-mails)
    form.addEventListener('submit', () => {
        btn.classList.add('loading');
        setTimeout(() => btn.setAttribute('disabled', 'disabled'), 0);
    });
})();
</script>
</body>
</html>
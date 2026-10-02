<?php
session_start();
if (!isset($_SESSION['id_user'])) { 
    header("Location: login.php"); 
    exit(); 
}

// Récupération du nom de l'utilisateur pour affichage
$user_name = $_SESSION['nom'] ?? 'Utilisateur';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sécurité | Modification du mot de passe</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --primary: #0A2540;
            --primary-dark: #051a2c;
            --primary-light: #1a3d5c;
            --secondary: #D4AF37;
            --bg: #F8FAFC;
            --white: #FFFFFF;
            --text-light: #64748B;
            --danger: #EF4444;
            --success: #10B981;
            --shadow-lg: 0 25px 50px -12px rgba(0,0,0,0.25);
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow-x: hidden;
            position: relative;
        }

       

        @keyframes float {
            0% { transform: translateY(100vh) rotate(0deg); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { transform: translateY(-100vh) rotate(360deg); opacity: 0; }
        }

        .login-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            width: 100%;
            max-width: 460px;
            padding: 40px;
            border-radius: 32px;
            box-shadow: var(--shadow-lg);
            text-align: center;
            z-index: 1;
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .logo-container {
            width: 100px;
            height: 100px;
            margin: 0 auto 20px;
            background: #fff;
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 20px rgba(0,0,0,0.05);
            overflow: hidden;
            border: 1px solid rgba(0,0,0,0.03);
        }

        .logo-container img {
            width: 80%;
            height: 80%;
            object-fit: contain;
        }

        h2 { 
            color: var(--primary); 
            font-weight: 800; 
            font-size: 1.6rem;
            margin-bottom: 5px;
        }

        .user-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--bg);
            padding: 6px 15px;
            border-radius: 30px;
            font-size: 0.75rem;
            color: var(--primary);
            margin-bottom: 20px;
            font-weight: 600;
        }

        .input-group {
            position: relative;
            margin-bottom: 20px;
            text-align: left;
            transition: transform 0.3s ease;
        }

        .input-group i:not(.toggle-password) {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #94A3B8;
        }

        .input-group input {
            width: 100%;
            padding: 14px 16px 14px 48px;
            border: 2px solid #E2E8F0;
            border-radius: 16px;
            outline: none;
            font-family: inherit;
            background: var(--bg);
            transition: all 0.3s ease;
        }

        .input-group input:focus {
            border-color: var(--secondary);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(212, 175, 55, 0.1);
        }

        .toggle-password {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--text-light);
        }

        .separator {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 20px 0;
            color: var(--text-light);
            font-size: 0.75rem;
        }

        .separator-line { flex: 1; height: 1px; background: #E2E8F0; }

        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            border: none;
            border-radius: 18px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(10, 37, 64, 0.3);
        }

        .btn-loader {
            display: none;
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        .password-strength { height: 4px; background: #E2E8F0; border-radius: 2px; margin-top: 8px; overflow: hidden; }
        .strength-bar { height: 100%; width: 0; transition: all 0.3s ease; }
        .strength-text { font-size: 0.7rem; margin-top: 5px; text-align: right; height: 15px; }

        .error-msg {
            background: #FEF2F2;
            color: var(--danger);
            padding: 12px;
            border-radius: 12px;
            font-size: 0.85rem;
            margin-bottom: 20px;
            border-left: 4px solid var(--danger);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: var(--text-light);
            text-decoration: none;
            font-size: 0.85rem;
            transition: color 0.3s;
        }

        .back-link:hover { color: var(--primary); }
    </style>
</head>
<body>
<?php include '../views/includes/navbar.php'; ?>
<div class="login-card">
    <div class="logo-container">
        <img src="../public/images.jpg" alt="Logo">
    </div>

    <h2>Sécurité</h2>
    <div class="user-badge">
        <i class="fa-regular fa-circle-user"></i>
        <?= htmlspecialchars($user_name) ?>
    </div>
    
    <?php if(isset($_GET['error'])): ?>
        <div class="error-msg" id="errorMsg">
            <i class="fa-solid fa-circle-exclamation"></i> 
            <?= htmlspecialchars(urldecode($_GET['error'])) ?>
        </div>
    <?php endif; ?>

    <form id="passwordForm" action="../actions/traitement_update_mdp.php" method="POST">
        <div class="input-group">
            <i class="fa-solid fa-lock"></i>
            <input type="password" name="old_password" id="oldPassword" placeholder="Ancien mot de passe" required>
            <i class="fa-solid fa-eye toggle-password" data-target="oldPassword"></i>
        </div>
        
        <div class="separator">
            <div class="separator-line"></div>
            <span>Nouveau mot de passe</span>
            <div class="separator-line"></div>
        </div>

        <div class="input-group">
            <i class="fa-solid fa-key"></i>
            <input type="password" name="new_password" id="newPassword" placeholder="Nouveau mot de passe" required>
            <i class="fa-solid fa-eye toggle-password" data-target="newPassword"></i>
            <div class="password-strength"><div class="strength-bar" id="strengthBar"></div></div>
            <div class="strength-text" id="strengthText"></div>
        </div>

        <div class="input-group">
            <i class="fa-solid fa-shield-halved"></i>
            <input type="password" name="confirm_password" id="confirmPassword" placeholder="Confirmer le nouveau" required>
            <i class="fa-solid fa-eye toggle-password" data-target="confirmPassword"></i>
        </div>
        <div id="matchMessage" style="font-size: 0.7rem; margin-top: -15px; margin-bottom: 15px; text-align: left;"></div>
        
        <button type="submit" id="submitBtn" class="btn-login">
            <i class="fa-solid fa-save"></i>
            <span>Enregistrer</span>
            <div class="btn-loader" id="loader"></div>
        </button>
    </form>

    
</div>

<script>
// Gestion de la visibilité
document.querySelectorAll('.toggle-password').forEach(toggle => {
    toggle.addEventListener('click', function() {
        const input = document.getElementById(this.getAttribute('data-target'));
        const isPwd = input.type === 'password';
        input.type = isPwd ? 'text' : 'password';
        this.classList.toggle('fa-eye', !isPwd);
        this.classList.toggle('fa-eye-slash', isPwd);
    });
});

// Force du mot de passe
const newPwdInput = document.getElementById('newPassword');
const strengthBar = document.getElementById('strengthBar');
const strengthText = document.getElementById('strengthText');

newPwdInput.addEventListener('input', () => {
    const val = newPwdInput.value;
    let s = 0;
    if(val.length > 5) s++;
    if(val.length > 9) s++;
    if(/[A-Z]/.test(val)) s++;
    if(/[0-9]/.test(val)) s++;
    if(/[^A-Za-z0-9]/.test(val)) s++;

    const config = [
        { w: '0%', c: '#E2E8F0', t: '' },
        { w: '25%', c: '#EF4444', t: 'Faible' },
        { w: '50%', c: '#F59E0B', t: 'Moyen' },
        { w: '75%', c: '#10B981', t: 'Fort' },
        { w: '100%', c: '#059669', t: 'Excellent' }
    ];
    
    const res = val.length === 0 ? config[0] : config[Math.min(s, 4)];
    strengthBar.style.width = res.w;
    strengthBar.style.background = res.c;
    strengthText.textContent = res.t;
    strengthText.style.color = res.c;
});

// Soumission du formulaire
document.getElementById('passwordForm').addEventListener('submit', function(e) {
    const old = document.getElementById('oldPassword').value;
    const newP = newPwdInput.value;
    const conf = document.getElementById('confirmPassword').value;

    if (newP !== conf) {
        e.preventDefault();
        showNotification('Les mots de passe ne correspondent pas', 'error');
        return;
    }

    if (old === newP) {
        e.preventDefault();
        showNotification('Le nouveau mot de passe doit être différent', 'error');
        return;
    }

    // Effet visuel de chargement
    document.getElementById('loader').style.display = 'block';
    document.querySelector('#submitBtn span').style.display = 'none';
    document.querySelector('#submitBtn i').style.display = 'none';
});

function showNotification(msg, type) {
    const toast = document.createElement('div');
    toast.style.cssText = `position:fixed;top:20px;right:20px;padding:15px 25px;background:${type==='error'?'#EF4444':'#10B981'};color:white;border-radius:12px;z-index:1000;box-shadow:0 10px 20px rgba(0,0,0,0.2);animation:slideIn 0.3s ease forwards;`;
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

// Animation particules
function createParticles() {
    for(let i = 0; i < 20; i++) {
        const p = document.createElement('div');
        p.className = 'particle';
        const size = Math.random() * 5 + 2;
        p.style.width = p.style.height = size + 'px';
        p.style.left = Math.random() * 100 + '%';
        p.style.animationDuration = (Math.random() * 10 + 5) + 's';
        p.style.animationDelay = (Math.random() * 5) + 's';
        document.body.appendChild(p);
    }
}
createParticles();
</script>
</body>
</html>
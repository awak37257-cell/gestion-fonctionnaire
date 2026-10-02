<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Récupération des infos de session
$role = isset($_SESSION['rol']) ? trim($_SESSION['rol']) : '';

// 2. On récupère l'identifiant pour la redirection
// On utilise 'id_fonc' si dispo, sinon on utilise 'nom' (qui contient 'iden' dans votre login.php)
$id_pour_redirect = $_SESSION['id_fonc'] ?? $_SESSION['nom'];

// 3. Logique de redirection intelligente
if ($role === 'admin' || $role === 'admin_local') {
    // Redirection admin
    $lien_retour = "/fonctionnaire_ministere/views/admin_dashboard.php";
} elseif (!empty($_SESSION['id_user'])) { 
    // C'est un fonctionnaire (car il n'est pas admin, mais il est connecté)
    // On utilise la même logique que dans le login.php
    $lien_retour = "/fonctionnaire_ministere/views/profil_agent.php?id=" . $id_pour_redirect;
} else {
    // Sécurité : si la session est expirée
    $lien_retour = "/fonctionnaire_ministere/saisie/login.php";
}
?>

<style>
    .back-nav {
        position: fixed; /* Fixed pour rester visible même au scroll */
        top: 20px;
        left: 20px;
        z-index: 1000;
    }
    
    .btn-back {
        display: inline-flex;
        align-items: center;
        text-decoration: none;
        color: #003366;
        font-family: 'Poppins', sans-serif;
        font-weight: 600;
        background: #ffffff;
        padding: 10px 22px;
        border-radius: 12px; /* Un peu plus carré pour coller aux cartes du formulaire */
        box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: 1px solid rgba(0,51,102,0.1);
    }

    .btn-back:hover {
        transform: translateY(-2px);
        background: #003366;
        color: #ffffff;
        box-shadow: 0 10px 25px rgba(0,51,102,0.2);
    }

    .btn-back i {
        margin-right: 12px;
        font-size: 1.1rem;
    }
</style>

<div class="back-nav">
    <a href="<?= $lien_retour ?>" class="btn-back">
        <i class="fa-solid fa-arrow-left-long"></i> Retour
    </a>
</div>
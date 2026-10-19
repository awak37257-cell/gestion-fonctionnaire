<?php
/**
 * Sidebar admin partagé.
 *
 * Variables attendues (définies AVANT l'include) :
 *   $sidebar_page  (string) – identifiant de la page courante parmi :
 *                  'dashboard' | 'ajouter_gestionnaire' | 'ajouter_direction' |
 *                  'admin_historique' | 'liste_fonctionnaires' | 'ajouter_agent' |
 *                  'gestion_documents' | 'notifications' | 'modifier_mdp'
 *   $sidebar_base  (string) – préfixe de chemin relatif, ex. '' pour views/, '../' pour saisie/
 *
 * Variables session attendues (déjà définies dans le fichier appelant) :
 *   $user_role          – rôle de l'utilisateur ('admin', 'admin_local', 'consultant', …)
 *   $nom_session        – nom affiché dans la zone utilisateur
 *   $libelle_role       – libellé textuel du rôle
 *   $notifications_a_verifier (int, optionnel) – badge de notifications
 */

// Valeurs par défaut sécurisées
$sidebar_page = $sidebar_page ?? '';
$sidebar_base = $sidebar_base ?? '';
$notifications_a_verifier = $notifications_a_verifier ?? 0;

// Helper pour générer la classe active d'un lien
function _sb_cls(string $page, string $current): string {
    return $page === $current
        ? 'nav-link active" aria-current="page'
        : 'nav-link';
}

$v  = $sidebar_base . 'views/';   // ex. '' → 'views/' pour saisie/   |  '../' → '../views/'
$s  = $sidebar_base . 'saisie/';  // ex. '' → 'saisie/' pour saisie/  |  '../' → '../saisie/'
$vl = '';                          // chemin relatif pour un fichier déjà dans views/
$sl = '';                          // chemin relatif pour un fichier déjà dans saisie/

// Si sidebar_base est vide → on est dans views/ → les vues sont locales, saisie est ../saisie/
// Si sidebar_base est '../' → on est dans saisie/ → les vues sont ../views/, saisie est locale
if ($sidebar_base === '') {
    $v  = '';         // on est dans views/ → chemin local
    $s  = '../saisie/';
} else {
    $v  = '../views/';
    $s  = '';         // on est dans saisie/ → chemin local
}

$profil_fonctionnaire_id = (int)($_SESSION['id_fonc'] ?? 0);
if ($profil_fonctionnaire_id < 1 && $user_role === 'admin_local') {
    $id_user_session = (int)($_SESSION['id_user'] ?? $_SESSION['user_id'] ?? 0);
    if ($id_user_session > 0 && isset($db)) {
        $stmtProfil = $db->prepare("SELECT id_fonc FROM UTILISATEURS WHERE id_user = ? AND rol = 'admin_local'");
        $stmtProfil->execute([$id_user_session]);
        $profil_fonctionnaire_id = (int)$stmtProfil->fetchColumn();
    }
}
if ($user_role === 'admin_local' && $profil_fonctionnaire_id > 0 && empty($_SESSION['csrf_mode'])) {
    $_SESSION['csrf_mode'] = bin2hex(random_bytes(32));
}
?>
<!-- Sidebar admin partagé -->
<aside class="sidebar" id="sidebarAdmin" aria-label="Navigation principale">
    <div class="brand">
        <div class="brand-mark"><i class="fa-solid fa-building-columns"></i></div>
        <div>
            <div class="brand-name">Ministère des Affaires étrangères</div>
            <div class="brand-sub">Ressources humaines</div>
        </div>
    </div>

    <nav class="nav">
        <div class="nav-group">Général</div>
        <a href="<?= $v ?>admin_dashboard.php" class="<?= _sb_cls('dashboard', $sidebar_page) ?>">
            <i class="fa-solid fa-chart-pie"></i> Tableau de bord
        </a>

        <?php if ($user_role === 'admin'): ?>
            <div class="nav-group">Administration</div>
            <a href="<?= $s ?>ajouter_gestionnaire.php" class="<?= _sb_cls('ajouter_gestionnaire', $sidebar_page) ?>">
                <i class="fa-solid fa-user-shield"></i> Créer un accès local
            </a>
            <a href="<?= $s ?>ajouter_direction.php" class="<?= _sb_cls('ajouter_direction', $sidebar_page) ?>">
                <i class="fa-solid fa-sitemap"></i> Gérer les directions
            </a>
            <a href="<?= $v ?>admin_historique.php" class="<?= _sb_cls('admin_historique', $sidebar_page) ?>">
                <i class="fa-solid fa-clock-rotate-left"></i> Historique des actions
            </a>
        <?php endif; ?>

        <div class="nav-group">Ressources humaines</div>
        <a href="<?= $v ?>liste_fonctionnaires.php" class="<?= _sb_cls('liste_fonctionnaires', $sidebar_page) ?>">
            <i class="fa-solid fa-table-list"></i> Liste complète
        </a>
        <?php if (in_array($user_role, ['admin', 'admin_local'], true)): ?>
            <a href="<?= $v ?>notifications.php" class="<?= _sb_cls('notifications', $sidebar_page) ?>">
                <i class="fa-solid fa-bell"></i> Déclarations à vérifier
                <?php if ($notifications_a_verifier > 0): ?>
                    <span class="pill"><?= (int)$notifications_a_verifier ?></span>
                <?php endif; ?>
            </a>
        <?php endif; ?>
        <a href="<?= $s ?>ajouter_agent.php" class="<?= _sb_cls('ajouter_agent', $sidebar_page) ?>">
            <i class="fa-solid fa-user-plus"></i> Nouveau fonctionnaire
        </a>
        <a href="<?= $v ?>gestion_documents.php" class="<?= _sb_cls('gestion_documents', $sidebar_page) ?>">
            <i class="fa-solid fa-folder-open"></i> Archive des documents
        </a>

        <div class="nav-group">Mon compte</div>
        <?php if ($user_role === 'admin_local' && $profil_fonctionnaire_id > 0): ?>
            <form method="post" action="<?= $v ?>changer_mode.php">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_mode'], ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="mode" value="fonctionnaire">
                <button type="submit" class="nav-link" style="width:100%;border:0;background:transparent;text-align:left;font:inherit;cursor:pointer;">
                    <i class="fa-solid fa-id-card"></i> Espace fonctionnaire
                </button>
            </form>
        <?php endif; ?>
        <a href="<?= $v ?>modifier_mdp.php" class="<?= _sb_cls('modifier_mdp', $sidebar_page) ?>">
            <i class="fa-solid fa-lock"></i> Sécurité et mot de passe
        </a>
    </nav>

    <div class="sidebar-user">
        <div class="avatar"><?php echo e(initiales($nom_session)); ?></div>
        <div>
            <div class="name"><?php echo e($nom_session); ?></div>
            <div class="role"><?php echo e($libelle_role); ?></div>
        </div>
    </div>
</aside>
<div class="overlay" id="overlay"></div>

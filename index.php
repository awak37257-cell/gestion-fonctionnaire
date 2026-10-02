<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Répertoire Officiel | Ministère de la Fonction Publique</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="public/css/style.css">
</head>
<body>

<header class="main-header">
    <div class="brand-container">
        <div class="brand-logo">
            <i class="fa-solid fa-building-columns"></i>
        </div>
        <div class="brand-info">
            <h1>Ministère des Affaires Étrangères</h1>
            <p>République de Côte d'Ivoire</p>
        </div>
    </div>
    
    <div class="nav-actions">
        <a href="index.php" class="btn-custom btn-outline active" style="border-color: var(--secondary);">
            <i class="fa-solid fa-house"></i> Accueil
        </a>
        <?php if (isset($_SESSION['id_user'])): ?>
            <?php 
                $role = $_SESSION['rol'] ?? '';
                $lien_dash = in_array($role, ['admin', 'admin_local', 'gestionnaire']) 
                    ? 'views/admin_dashboard.php' 
                    : 'views/profil_agent.php?id=' . ($_SESSION['id_fonc'] ?? $_SESSION['nom']);
            ?>
            <a href="<?php echo $lien_dash; ?>" class="btn-custom btn-primary">
                <i class="fa-solid fa-gauge-high"></i> Mon Espace
            </a>
        <?php else: ?>
            <a href="saisie/login.php" class="btn-custom btn-primary">
                <i class="fa-solid fa-right-to-bracket"></i> Connexion
            </a>
        <?php endif; ?>
    </div>
</header>

<main class="main-content" style="padding: 2.5rem 5%; max-width: 1400px; margin: auto;">
    
    <!-- Hero Section -->
    <section class="hero-section" style="text-align: center; margin-bottom: 3.5rem;">
        <div style="display: inline-block; padding: 0.4rem 1.2rem; background: var(--secondary-glow); border: 1px solid var(--secondary); border-radius: var(--radius-full); color: var(--secondary-dark); font-size: 0.8rem; font-weight: 600; margin-bottom: 1rem;">
            <i class="fa-solid fa-shield-halved"></i> Portail Officiel de la Fonction Publique
        </div>
        <h1 style="font-size: 2.4rem; font-weight: 800; color: var(--primary); margin-bottom: 0.8rem; letter-spacing: -0.5px;">
            Répertoire National des Fonctionnaires
        </h1>
        <p style="color: var(--text-muted); font-size: 1rem; max-width: 650px; margin: 0 auto 2rem;">
            Recherchez et consultez en temps réel la liste officielle des agents publics de l'État, leurs structures d'affectation et leurs grades.
        </p>
        
        <div class="search-wrapper">
            <div class="search-input-group">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="searchInput" data-live-search="agentTableBody" placeholder="Rechercher par nom, matricule, grade ou structure...">
                <button class="btn-custom btn-secondary" style="padding: 0.5rem 1.2rem;">Rechercher</button>
            </div>
        </div>
    </section>

    <!-- Stats Grid -->
    <section style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.5rem; margin-bottom: 3.5rem;">
        <div class="glass-card" style="padding: 1.5rem; display: flex; align-items: center; gap: 1.2rem;">
            <div style="width: 58px; height: 58px; border-radius: var(--radius-md); background: linear-gradient(135deg, rgba(10,37,64,0.1) 0%, rgba(10,37,64,0.05) 100%); display: flex; align-items: center; justify-content: center; color: var(--primary); font-size: 1.6rem;">
                <i class="fa-solid fa-users"></i>
            </div>
            <div>
                <span style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); letter-spacing: 0.5px;">Total Agents</span>
                <?php $total_agents = $db->query("SELECT COUNT(*) FROM FONCTIONNAIRE")->fetchColumn(); ?>
                <h3 style="font-size: 1.8rem; font-weight: 800; color: var(--primary); margin: 0;" data-counter-target="<?php echo $total_agents; ?>">
                    <?php echo number_format($total_agents); ?>
                </h3>
            </div>
        </div>

        <div class="glass-card" style="padding: 1.5rem; display: flex; align-items: center; gap: 1.2rem;">
            <div style="width: 58px; height: 58px; border-radius: var(--radius-md); background: linear-gradient(135deg, rgba(212,175,55,0.15) 0%, rgba(212,175,55,0.05) 100%); display: flex; align-items: center; justify-content: center; color: var(--secondary-dark); font-size: 1.6rem;">
                <i class="fa-solid fa-building"></i>
            </div>
            <div>
                <span style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); letter-spacing: 0.5px;">Structures & Org.</span>
                <?php $total_orgs = $db->query("SELECT COUNT(*) FROM ORGANISATION")->fetchColumn(); ?>
                <h3 style="font-size: 1.8rem; font-weight: 800; color: var(--primary); margin: 0;" data-counter-target="<?php echo $total_orgs; ?>">
                    <?php echo number_format($total_orgs); ?>
                </h3>
            </div>
        </div>

        <div class="glass-card" style="padding: 1.5rem; display: flex; align-items: center; gap: 1.2rem;">
            <div style="width: 58px; height: 58px; border-radius: var(--radius-md); background: linear-gradient(135deg, rgba(16,185,129,0.15) 0%, rgba(16,185,129,0.05) 100%); display: flex; align-items: center; justify-content: center; color: var(--success); font-size: 1.6rem;">
                <i class="fa-solid fa-graduation-cap"></i>
            </div>
            <div>
                <span style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--text-muted); letter-spacing: 0.5px;">Diplômes Reconnus</span>
                <?php $total_dipl = $db->query("SELECT COUNT(*) FROM DIPLOME")->fetchColumn(); ?>
                <h3 style="font-size: 1.8rem; font-weight: 800; color: var(--primary); margin: 0;" data-counter-target="<?php echo $total_dipl; ?>">
                    <?php echo number_format($total_dipl); ?>
                </h3>
            </div>
        </div>
    </section>

    <!-- Table Section -->
    <section class="glass-card" style="padding: 1.8rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.2rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--primary); margin: 0;">
                    <i class="fa-solid fa-list-check" style="color: var(--secondary); margin-right: 8px;"></i>
                    Liste Générale des Agents
                </h2>
                <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0;">Mise à jour en temps réel selon les derniers mouvements d'affectation</p>
            </div>
        </div>

        <div class="table-container">
            <table class="table-modern">
                <thead>
                    <tr>
                        <th><i class="fa-regular fa-id-card"></i> Matricule</th>
                        <th><i class="fa-solid fa-user"></i> Nom & Prénoms</th>
                        <th><i class="fa-solid fa-building"></i> Structure / Ministère</th>
                        <th><i class="fa-solid fa-award"></i> Grade / Poste</th>
                       
                    </tr>
                </thead>
                <tbody id="agentTableBody">
                    <?php
                    $sql = "SELECT f.*, a.gra_post, o.nom_org
                            FROM FONCTIONNAIRE f
                            LEFT JOIN RECEVOIR r ON f.id_fonc = r.id_fonc AND r.date_fin IS NULL
                            LEFT JOIN AFFECTATION a ON r.id_affec = a.id_affec
                            LEFT JOIN ORGANISATION o ON r.id_org = o.id_org
                            ORDER BY f.nom_fonc ASC";
                    
                    $res = $db->query($sql);
                    while($row = $res->fetch()): ?>
                    <tr data-searchable-item>
                        <td>
                            <span style="font-family: monospace; font-weight: 700; color: var(--secondary-dark); background: var(--secondary-glow); padding: 4px 10px; border-radius: 6px;">
                                <?php echo htmlspecialchars($row['matri']); ?>
                            </span>
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem;">
                                    <?php echo strtoupper(substr($row['nom_fonc'], 0, 1)); ?>
                                </div>
                                <div>
                                    <strong style="color: var(--primary); display: block; line-height: 1.2;">
                                        <?php echo strtoupper(htmlspecialchars($row['nom_fonc'])); ?>
                                    </strong>
                                    <span style="font-size: 0.8rem; color: var(--text-muted);">
                                        <?php echo htmlspecialchars($row['prenom_fonc']); ?>
                                    </span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span style="font-weight: 500; color: var(--text-main);">
                                <?php echo htmlspecialchars($row['nom_org'] ?? 'Non affecté'); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge-status badge-gold">
                                <?php echo htmlspecialchars($row['gra_post'] ?? 'Agent State'); ?>
                            </span>
                        </td>
                        
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        
        <div id="search-empty-message" style="display: none; text-align: center; padding: 2rem; color: var(--text-muted);">
            <i class="fa-solid fa-magnifying-glass" style="font-size: 2rem; color: var(--secondary); margin-bottom: 0.5rem; opacity: 0.5;"></i>
            <p>Aucun agent ne correspond à votre recherche.</p>
        </div>
    </section>
</main>

<footer style="background: #ffffff; border-top: 1px solid var(--border-color); text-align: center; padding: 1.8rem 5%; margin-top: 4rem; color: var(--text-muted); font-size: 0.8rem;">
    <p><i class="fa-regular fa-copyright"></i> 2026 République de Côte d'Ivoire - Ministère de la Fonction Publique</p>
</footer>

<script src="public/js/app.js"></script>
</body>
</html>
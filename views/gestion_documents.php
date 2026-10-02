<?php 
session_start();
require_once '../config.php';

if (!isset($_SESSION['rol'])) {
    header("Location: saisie/login.php");
    exit();
}

$agents = $db->query("
    SELECT f.id_fonc, f.nom_fonc, f.prenom_fonc, f.matri, r.id_affec 
    FROM FONCTIONNAIRE f 
    LEFT JOIN RECEVOIR r ON f.id_fonc = r.id_fonc 
    GROUP BY f.id_fonc
    ORDER BY f.nom_fonc ASC
")->fetchAll();

$sqlDocs = "SELECT d.id_doc, d.lib_doc, d.date_enre, d.fich_url, f.nom_fonc, f.prenom_fonc, f.matri, a.tit_post, a.vil_affec
FROM DOCUMENT d 
JOIN FONCTIONNAIRE f ON d.id_fonc = f.id_fonc 
LEFT JOIN RECEVOIR r ON f.id_fonc = r.id_fonc
LEFT JOIN AFFECTATION a ON r.id_affec = a.id_affec
GROUP BY d.id_doc
ORDER BY d.date_enre DESC";
$documents = $db->query($sqlDocs)->fetchAll();

// Statistiques
$total_docs = count($documents);
$total_agents = count($agents);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion Documentaire | Système RH</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #0A2540;
            --primary-dark: #051a2c;
            --primary-light: #1a3d5c;
            --secondary: #D4AF37;
            --secondary-dark: #b8960c;
            --bg: #F8FAFC;
            --white: #FFFFFF;
            --text: #1E293B;
            --text-light: #64748B;
            --border: #E2E8F0;
            --success: #10B981;
            --success-dark: #059669;
            --danger: #EF4444;
            --danger-dark: #DC2626;
            --info: #3B82F6;
            --accent: #F59E0B;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 10px 25px -5px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 20px 35px -10px rgba(0, 0, 0, 0.15);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body { 
            font-family: 'Poppins', sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: var(--text);
            margin: 0;
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }

        /* Animation de fond */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="rgba(255,255,255,0.05)" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,154.7C960,171,1056,181,1152,165.3C1248,149,1344,107,1392,85.3L1440,64L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>') repeat-x bottom;
            background-size: cover;
            opacity: 0.1;
            pointer-events: none;
        }

        /* Particules */
        .particle {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            pointer-events: none;
            animation: float linear infinite;
        }

        @keyframes float {
            0% {
                transform: translateY(100vh) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(-100vh) rotate(360deg);
                opacity: 0;
            }
        }

        /* ========== CONTAINER PRINCIPAL ========== */
        .container { 
            max-width: 1400px; 
            margin: 100px auto 40px; 
            padding: 0 30px; 
            display: grid; 
            grid-template-columns: 400px 1fr; 
            gap: 30px; 
            position: relative;
            z-index: 1;
        }

        /* ========== STYLES DES CARTES ========== */
        .card { 
            background: rgba(255,255,255,0.98);
            backdrop-filter: blur(10px);
            border-radius: 28px; 
            box-shadow: var(--shadow-lg); 
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            border: 1px solid rgba(255,255,255,0.2);
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card:hover {
            transform: translateY(-8px);
            box-shadow: 0 35px 55px -15px rgba(0, 0, 0, 0.2);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            padding: 25px 30px;
            position: relative;
            overflow: hidden;
        }

        .card-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(212, 175, 55, 0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .card-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--secondary), var(--success), var(--secondary));
        }

        .card-title {
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--white);
            font-size: 1.2rem;
            font-weight: 700;
            margin: 0;
            position: relative;
            z-index: 1;
        }

        .card-title i {
            color: var(--secondary);
            font-size: 1.4rem;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .card-body {
            padding: 30px;
        }

        /* Statistiques mini */
        .stats-mini {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .stat-mini {
            flex: 1;
            background: linear-gradient(135deg, #F1F5F9, #E2E8F0);
            padding: 15px;
            border-radius: 16px;
            text-align: center;
        }

        .stat-mini .number {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary);
        }

        .stat-mini .label {
            font-size: 0.65rem;
            color: var(--text-light);
            text-transform: uppercase;
        }

        /* ========== FORMULAIRE ========== */
        .form-group { 
            margin-bottom: 24px; 
            animation: fadeInUp 0.5s ease-out forwards;
            opacity: 0;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(15px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .form-group:nth-child(1) { animation-delay: 0.1s; }
        .form-group:nth-child(2) { animation-delay: 0.2s; }
        .form-group:nth-child(3) { animation-delay: 0.3s; }

        label { 
            display: block; 
            margin-bottom: 8px; 
            font-weight: 700; 
            font-size: 0.75rem; 
            color: var(--text);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        label i {
            margin-right: 8px;
            color: var(--secondary);
            transition: transform 0.3s ease;
        }

        .form-group:hover label i {
            transform: translateX(3px);
        }

        input[type="text"], select { 
            width: 100%; 
            padding: 14px 18px; 
            border: 2px solid var(--border); 
            border-radius: 16px; 
            font-family: inherit;
            font-size: 0.9rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: var(--white);
        }

        input:focus, select:focus { 
            outline: none; 
            border-color: var(--secondary); 
            box-shadow: 0 0 0 4px rgba(212, 175, 55, 0.1);
        }

        select:hover, input:hover {
            border-color: var(--secondary);
            transform: translateY(-1px);
        }

        /* Custom File Upload */
        .file-upload {
            position: relative;
            display: block;
            padding: 30px 20px;
            border: 2px dashed var(--border);
            border-radius: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: var(--bg);
        }

        .file-upload:hover { 
            border-color: var(--secondary); 
            background: linear-gradient(135deg, #FFFBEB, #FEF3C7);
            transform: translateY(-3px);
        }

        .file-upload i { 
            font-size: 2.5rem; 
            color: var(--secondary);
            margin-bottom: 12px;
            transition: transform 0.3s ease;
        }

        .file-upload:hover i {
            transform: translateY(-3px);
        }

        .file-upload p { 
            margin: 8px 0 0;
            font-size: 0.85rem;
            color: var(--text-light);
        }

        .file-upload input { 
            position: absolute; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            opacity: 0; 
            cursor: pointer; 
        }

        .btn-submit { 
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white; 
            border: none; 
            padding: 16px 20px; 
            border-radius: 20px; 
            cursor: pointer; 
            font-weight: 700; 
            width: 100%; 
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 0.95rem;
            position: relative;
            overflow: hidden;
            margin-top: 10px;
        }

        .btn-submit::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .btn-submit:hover::before {
            width: 400px;
            height: 400px;
        }

        .btn-submit:hover { 
            transform: translateY(-3px);
            box-shadow: 0 15px 30px -8px rgba(10, 37, 64, 0.4);
        }

        .btn-submit:active {
            transform: translateY(1px);
        }

        /* Loader */
        .btn-loader {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .btn-submit.loading {
            pointer-events: none;
            opacity: 0.8;
        }

        .btn-submit.loading .btn-loader {
            display: inline-block;
        }

        .btn-submit.loading i {
            display: none;
        }

        /* ========== RECHERCHE ========== */
        .search-box {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }

        .search-box input {
            flex: 1;
            padding: 12px 16px;
            border: 2px solid var(--border);
            border-radius: 14px;
            font-family: inherit;
        }

        .search-box input:focus {
            border-color: var(--secondary);
            outline: none;
        }

        /* ========== TABLEAU DES ARCHIVES ========== */
        .table-container { 
            overflow-x: auto; 
        }

        table { 
            width: 100%; 
            border-collapse: collapse; 
        }

        th { 
            text-align: left; 
            padding: 15px 16px; 
            font-size: 0.7rem; 
            color: var(--text-light); 
            text-transform: uppercase; 
            letter-spacing: 1px;
            border-bottom: 2px solid var(--border);
            font-weight: 700;
        }

        td { 
            padding: 16px; 
            border-bottom: 1px solid var(--border); 
            vertical-align: middle;
            font-size: 0.85rem;
            transition: all 0.2s ease;
        }

        tr:hover td {
            background: var(--bg);
        }

        .agent-info { 
            display: flex; 
            flex-direction: column; 
        }

        .agent-info .name { 
            font-weight: 700; 
            color: var(--primary); 
        }

        .agent-info .matri { 
            font-size: 0.7rem; 
            color: var(--text-light); 
        }

        .doc-link {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--text);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .doc-link:hover {
            color: var(--secondary);
            transform: translateX(3px);
        }

        .doc-link i { 
            color: var(--danger); 
            font-size: 1.2rem;
        }

        .date-badge { 
            font-size: 0.8rem; 
            color: var(--text-light);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .actions { 
            display: flex; 
            gap: 8px; 
            justify-content: flex-end;
        }

        .action-btn { 
            width: 36px; 
            height: 36px; 
            border-radius: 12px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            text-decoration: none; 
            transition: all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .btn-view { background: #ECFDF5; color: var(--success); }
        .btn-edit { background: #EFF6FF; color: var(--info); }
        .btn-del { background: #FEF2F2; color: var(--danger); }

        .action-btn:hover { 
            transform: scale(1.15) translateY(-2px);
            box-shadow: 0 5px 10px rgba(0,0,0,0.1);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .container {
                grid-template-columns: 1fr;
                margin-top: 80px;
                padding: 0 20px;
            }
        }

        @media (max-width: 768px) {
            .card-body {
                padding: 20px;
            }
            .card-header {
                padding: 18px 20px;
            }
            .stats-mini {
                flex-direction: column;
            }
            .actions {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>

    <?php include 'includes/navbar.php'; ?>

    <!-- Particules -->
    <script>
        function createParticles() {
            const particleCount = 30;
            for(let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.classList.add('particle');
                const size = Math.random() * 5 + 2;
                particle.style.width = size + 'px';
                particle.style.height = size + 'px';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.animationDuration = Math.random() * 10 + 5 + 's';
                particle.style.animationDelay = Math.random() * 5 + 's';
                document.body.appendChild(particle);
            }
        }
        createParticles();
    </script>

    <div class="container">
        <!-- Carte d'ajout de document -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                    Nouveau Document
                </div>
            </div>
            <div class="card-body">
                <div class="stats-mini">
                    <div class="stat-mini">
                        <div class="number"><?= $total_agents ?></div>
                        <div class="label">Agents</div>
                    </div>
                    <div class="stat-mini">
                        <div class="number"><?= $total_docs ?></div>
                        <div class="label">Documents</div>
                    </div>
                </div>

                <form id="documentForm" action="../actions/traitement_document.php" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label><i class="fa-solid fa-user-tie"></i> Agent titulaire</label>
                        <select name="id_fonc" id="agentSelect" required>
                            <option value="">-- Sélectionner l'agent --</option>
                            <?php foreach($agents as $a): ?>
                                <option value="<?= $a['id_fonc'] ?>" data-aff="<?= $a['id_affec'] ?>">
                                    <?= strtoupper(htmlspecialchars($a['nom_fonc'])) ?> <?= htmlspecialchars($a['prenom_fonc']) ?> (<?= htmlspecialchars($a['matri']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <input type="hidden" name="id_affec" id="id_aff_hidden">

                    <div class="form-group">
                        <label><i class="fa-solid fa-tag"></i> Désignation du document</label>
                        <input type="text" name="lib_doc" id="libDoc" placeholder="ex: Arrêté de nomination, Contrat, Diplôme..." required autocomplete="off">
                        <small id="libDocHelp" style="display: block; margin-top: 6px; font-size: 0.7rem;"></small>
                    </div>

                    <div class="form-group">
                        <label><i class="fa-solid fa-file-pdf"></i> Fichier numérique</label>
                        <div class="file-upload" id="fileUpload">
                            <i class="fa-solid fa-cloud-arrow-up"></i>
                            <p id="file-name">Cliquez ou déposez le fichier (PDF, image)</p>
                            <input type="file" name="document_file" id="documentFile" required accept=".pdf,.jpg,.jpeg,.png" onchange="updateFileName(this)">
                        </div>
                        <div id="fileInfo" style="margin-top: 8px; font-size: 0.7rem;"></div>
                    </div>

                    <button type="submit" id="submitBtn" class="btn-submit">
                        <i class="fa-solid fa-save"></i>
                        <span>Archiver le document</span>
                        <span class="btn-loader"></span>
                    </button>
                </form>
            </div>
        </div>

        <!-- Carte des archives -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fa-solid fa-folder-tree"></i>
                    Archives Numériques du Personnel
                </div>
            </div>
            <div class="card-body">
                <div class="search-box">
                    <input type="text" id="searchDocs" placeholder="🔍 Rechercher par agent, document ou matricule...">
                </div>
                <div class="table-container">
                    <table id="docsTable">
                        <thead>
                            <tr>
                                <th><i class="fa-solid fa-user"></i> Agent</th>
                                <th><i class="fa-solid fa-file"></i> Document</th>
                                <th><i class="fa-regular fa-calendar"></i> Enregistré le</th>
                                <th style="text-align: right;"><i class="fa-solid fa-gear"></i> Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($documents as $doc): ?>
                            <tr data-agent="<?= strtolower(htmlspecialchars($doc['nom_fonc'] . ' ' . $doc['prenom_fonc'])) ?>" 
                                data-doc="<?= strtolower(htmlspecialchars($doc['lib_doc'])) ?>"
                                data-matri="<?= strtolower(htmlspecialchars($doc['matri'])) ?>">
                                <td>
                                    <div class="agent-info">
                                        <span class="name"><?= strtoupper(htmlspecialchars($doc['nom_fonc'])) ?> <?= htmlspecialchars($doc['prenom_fonc']) ?></span>
                                        <span class="matri"><i class="fa-regular fa-id-card"></i> Matricule: <?= htmlspecialchars($doc['matri']) ?></span>
                                    </div>
                                 </td>
                                <td>
                                    <a href="<?= htmlspecialchars($doc['fich_url']) ?>" target="_blank" class="doc-link">
                                        <i class="fa-solid fa-file-pdf"></i>
                                        <?= htmlspecialchars($doc['lib_doc']) ?>
                                    </a>
                                 </td>
                                <td>
                                    <span class="date-badge">
                                        <i class="fa-regular fa-clock"></i>
                                        <?= date('d/m/Y H:i', strtotime($doc['date_enre'])) ?>
                                    </span>
                                 </td>
                                <td>
                                    <div class="actions">
                                        <a href="<?= htmlspecialchars($doc['fich_url']) ?>" target="_blank" class="action-btn btn-view" title="Visualiser">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                        <a href="../saisie/modifier_doc.php?id=<?= $doc['id_doc'] ?>" class="action-btn btn-edit" title="Modifier">
                                            <i class="fa-solid fa-pen"></i>
                                        </a>
                                        <a href="supprimer_doc.php?id=<?= $doc['id_doc'] ?>" class="action-btn btn-del" onclick="return confirmDelete(event, this)" title="Supprimer">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </a>
                                    </div>
                                 </td>
                             </tr>
                            <?php endforeach; ?>
                            <?php if(empty($documents)): ?>
                                <tr class="empty-row">
                                    <td colspan="4" style="text-align: center; padding: 60px; color: var(--text-light);">
                                        <i class="fa-solid fa-folder-open" style="font-size: 3rem; margin-bottom: 15px; display: block; opacity: 0.5;"></i>
                                        Aucune archive trouvée
                                     </td>
                                 </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Met à jour l'ID d'affectation caché
        const agentSelect = document.querySelector('select[name="id_fonc"]');
        if(agentSelect) {
            agentSelect.addEventListener('change', function() {
                let selectedOption = this.options[this.selectedIndex];
                const affInput = document.getElementById('id_aff_hidden');
                if(affInput) {
                    affInput.value = selectedOption.getAttribute('data-aff');
                }
            });
        }

        // Validation du libellé
        const libDoc = document.getElementById('libDoc');
        const libDocHelp = document.getElementById('libDocHelp');

        libDoc.addEventListener('input', function(e) {
            const value = e.target.value.trim();
            
            if(value.length > 0) {
                if(value.length >= 5) {
                    e.target.style.borderColor = '#10B981';
                    libDocHelp.innerHTML = '<i class="fa-solid fa-check-circle"></i> Libellé valide';
                    libDocHelp.style.color = '#10B981';
                } else {
                    e.target.style.borderColor = '#EF4444';
                    libDocHelp.innerHTML = '<i class="fa-solid fa-exclamation-circle"></i> Minimum 5 caractères';
                    libDocHelp.style.color = '#EF4444';
                }
            } else {
                e.target.style.borderColor = '';
                libDocHelp.innerHTML = '';
            }
        });

        // Gestion du fichier
        const fileInput = document.getElementById('documentFile');
        const fileNameSpan = document.getElementById('file-name');
        const fileInfo = document.getElementById('fileInfo');

        function updateFileName(input) {
            if(input.files && input.files[0]) {
                const file = input.files[0];
                const fileName = file.name;
                const fileSizeMB = (file.size / 1024 / 1024).toFixed(2);
                const validTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
                
                if(validTypes.includes(file.type) && fileSizeMB <= 5) {
                    fileNameSpan.innerHTML = `<i class="fa-solid fa-check-circle" style="color:#10B981"></i> ${fileName} (${fileSizeMB} MB)`;
                    fileNameSpan.style.color = "#065F46";
                    fileInfo.innerHTML = '<i class="fa-solid fa-check" style="color:#10B981"></i> Fichier valide';
                    fileInfo.style.color = '#10B981';
                    document.getElementById('fileUpload').style.borderColor = '#10B981';
                } else if(fileSizeMB > 5) {
                    fileNameSpan.innerHTML = 'Cliquez ou déposez le fichier (PDF, image)';
                    fileNameSpan.style.color = "var(--text-light)";
                    fileInfo.innerHTML = '<i class="fa-solid fa-exclamation-triangle" style="color:#EF4444"></i> Fichier trop grand (max 5MB)';
                    fileInfo.style.color = '#EF4444';
                    input.value = '';
                    showNotification('❌ Le fichier ne doit pas dépasser 5MB', 'error');
                } else {
                    fileNameSpan.innerHTML = 'Cliquez ou déposez le fichier (PDF, image)';
                    fileNameSpan.style.color = "var(--text-light)";
                    fileInfo.innerHTML = '<i class="fa-solid fa-exclamation-triangle" style="color:#EF4444"></i> Format non supporté (PDF, JPG, PNG)';
                    fileInfo.style.color = '#EF4444';
                    input.value = '';
                    showNotification('❌ Format de fichier non supporté', 'error');
                }
            } else {
                fileNameSpan.innerHTML = 'Cliquez ou déposez le fichier (PDF, image)';
                fileNameSpan.style.color = "var(--text-light)";
                fileInfo.innerHTML = '';
                document.getElementById('fileUpload').style.borderColor = '';
            }
        }

        // Recherche dynamique
        const searchInput = document.getElementById('searchDocs');
        if(searchInput) {
            searchInput.addEventListener('keyup', function() {
                const filter = this.value.toLowerCase();
                const rows = document.querySelectorAll('#docsTable tbody tr');
                
                rows.forEach(row => {
                    if(row.querySelector('td')) {
                        const agent = row.getAttribute('data-agent') || '';
                        const doc = row.getAttribute('data-doc') || '';
                        const matri = row.getAttribute('data-matri') || '';
                        const text = agent + ' ' + doc + ' ' + matri;
                        
                        if(text.indexOf(filter) > -1) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    }
                });
            });
        }

        // Confirmation de suppression
        function confirmDelete(event, element) {
            event.preventDefault();
            if(confirm('⚠️ Voulez-vous vraiment supprimer définitivement cette archive ? Cette action est irréversible.')) {
                window.location.href = element.href;
            }
            return false;
        }

        // Validation du formulaire
        document.getElementById('documentForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById('submitBtn');
            const agent = agentSelect.value;
            const libelle = libDoc.value.trim();
            const file = fileInput.files[0];
            
            if(!agent) {
                showNotification('❌ Veuillez sélectionner un agent', 'error');
                agentSelect.focus();
                return;
            }
            
            if(libelle.length < 5) {
                showNotification('❌ Le libellé doit contenir au moins 5 caractères', 'error');
                libDoc.focus();
                return;
            }
            
            if(!file) {
                showNotification('❌ Veuillez sélectionner un fichier', 'error');
                return;
            }
            
            submitBtn.classList.add('loading');
            
            // Simuler l'envoi (retirez ce setTimeout pour l'envoi réel)
            setTimeout(() => {
                showNotification('✅ Document archivé avec succès !', 'success');
                setTimeout(() => {
                    // Décommentez pour l'envoi réel
                    // e.target.submit();
                    submitBtn.classList.remove('loading');
                    e.target.reset();
                    libDocHelp.innerHTML = '';
                    fileInfo.innerHTML = '';
                    fileNameSpan.innerHTML = 'Cliquez ou déposez le fichier (PDF, image)';
                }, 1000);
            }, 1500);
        });

        // Notification toast
        function showNotification(message, type) {
            const existingNotifications = document.querySelectorAll('.notification-toast');
            existingNotifications.forEach(notif => notif.remove());
            
            const colors = { success: '#10B981', error: '#EF4444', info: '#3B82F6' };
            
            const notification = document.createElement('div');
            notification.className = `notification-toast ${type}`;
            notification.innerHTML = `
                <i class="fa-solid ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
                <span>${message}</span>
            `;
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${colors[type]};
                color: white;
                padding: 15px 20px;
                border-radius: 12px;
                font-weight: 500;
                z-index: 1000;
                animation: slideInRight 0.3s ease-out;
                box-shadow: 0 5px 15px rgba(0,0,0,0.2);
                display: flex;
                align-items: center;
                gap: 10px;
                font-family: 'Poppins', sans-serif;
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOutRight 0.3s ease-out';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        // Style pour les notifications
        const notificationStyle = document.createElement('style');
        notificationStyle.textContent = `
            @keyframes slideInRight {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOutRight {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
        `;
        document.head.appendChild(notificationStyle);

        // Animation des champs au focus
        document.querySelectorAll('input, select').forEach(field => {
            field.addEventListener('focus', function() {
                this.parentElement.style.transform = 'translateX(5px)';
            });
            
            field.addEventListener('blur', function() {
                this.parentElement.style.transform = 'translateX(0)';
            });
        });
    </script>
</body>
</html>
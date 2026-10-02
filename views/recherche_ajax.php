<?php
// Connexion à votre base SQLite
$db = new PDO('mysql:database.mysql');

$search = isset($_GET['q']) ? $_GET['q'] : '';
$category = isset($_GET['cat']) ? $_GET['cat'] : 'all';

// Construction de la requête SQL de base
$sql = "SELECT * FROM fonctionnaires WHERE 1=1";
$params = [];

if (!empty($search)) {
    $searchTerm = "%$search%";
    
    if ($category === 'competence') {
        $sql .= " AND competence LIKE ?";
        $params[] = $searchTerm;
    } elseif ($category === 'diplome') {
        $sql .= " AND diplome LIKE ?";
        $params[] = $searchTerm;
    } else {
        // Recherche globale
        $sql .= " AND (nom LIKE ? OR prenom LIKE ? OR competence LIKE ? OR diplome LIKE ?)";
        $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
    }
}

$query = $db->prepare($sql);
$query->execute($params);
$results = $query->fetchAll(PDO::FETCH_ASSOC);

// On renvoie les résultats en JSON pour que le JavaScript puisse les lire
echo json_encode($results);
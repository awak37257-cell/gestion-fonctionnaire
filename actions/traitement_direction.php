<?php
session_start();
require_once '../config.php';

// Sécurité Admin
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header("Location: ../saisie/login.php"); 
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Nettoyage des données
    $nom   = trim($_POST['nom_org']);
    $sigle = trim($_POST['sigle_org']);
    $siege = trim($_POST['siege']);
    
    try {
        // Insertion simple dans ORGANISATION (Table référentielle)
        $sql = "INSERT INTO ORGANISATION (nom_org, sigle_org, siege_soci) 
                VALUES (:nom, :sigle, :siege)";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':nom'   => $nom,
            ':sigle' => $sigle,
            ':siege' => $siege
        ]);

        echo "<script>
                alert('Organisation \"$sigle\" créée avec succès !'); 
                window.location.href='../saisie/ajouter_direction.php';
              </script>";

    } catch (PDOException $e) {
        // Gestion des erreurs (ex: duplicata de sigle)
        echo "Erreur lors de l'enregistrement : " . $e->getMessage();
    }
}
?>
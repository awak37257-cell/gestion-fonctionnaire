<?php
require_once '../config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Récupération des données
    $id_fonctionnaire = $_POST['id_fonc'];
    $libelle = htmlspecialchars($_POST['lib_doc']);
    $date_actuelle = date('Y-m-d'); 

    // 2. Gestion du dossier de stockage
    $target_dir = __DIR__ . "/../uploads/";
    
    if (!file_exists($target_dir)) { 
        mkdir($target_dir, 0777, true); 
    }

    // 3. Préparation du fichier
    if (isset($_FILES["document_file"]) && $_FILES["document_file"]["error"] == 0) {
        $file_name = $_FILES["document_file"]["name"];
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $new_file_name = "doc_" . $id_fonctionnaire . "_" . time() . "." . $file_extension;
        $target_file_full = $target_dir . $new_file_name; 
        
        $autorises = ['pdf', 'jpg', 'jpeg', 'png', 'docx'];
        if (!in_array($file_extension, $autorises)) {
            die("Erreur : Seuls les fichiers PDF, Images et Word sont autorisés.");
        }

        // 4. Transfert physique
        if (move_uploaded_file($_FILES["document_file"]["tmp_name"], $target_file_full)) {
            
            // 5. Insertion en BDD (Alignée sur le MLD)
            // On retire id_affec qui n'est pas dans la structure DOCUMENT
            try {
                $sql = "INSERT INTO DOCUMENT (lib_doc, fich_url, date_enre, id_fonc) 
                        VALUES (?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $libelle, 
                    $new_file_name, 
                    $date_actuelle, 
                    $id_fonctionnaire
                ]);

                header("Location: ../views/gestion_documents.php?upload=success");
                exit();
            } catch (PDOException $e) {
                unlink($target_file_full); // Sécurité : on nettoie le fichier si la DB échoue
                echo "Erreur BDD : " . $e->getMessage();
            }
        } else {
            echo "Erreur : Impossible de copier le fichier. Vérifiez les droits d'écriture.";
        }
    } else {
        echo "Erreur : Aucun fichier reçu.";
    }
}
?>
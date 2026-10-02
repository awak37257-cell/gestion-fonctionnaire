<?php
session_start();
require_once '../config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $id_f   = $_POST['id_f'];
    $libelle = trim($_POST['libelle']);
    $niveau  = $_POST['niveau'];
    if ($niveau === 'Autre' && !empty($_POST['niveau_custom'])) {
        $niveau = trim($_POST['niveau_custom']);
    }
    $annee   = intval($_POST['annee']);
    $nom_fichier_final = null;

    try {
        $db->beginTransaction();

        // 1. Gestion de l'upload
        if (isset($_FILES['scan_diplome']) && $_FILES['scan_diplome']['error'] === 0) {
            $upload_dir = "../uploads/diplomes/";
            if (!file_exists($upload_dir)) { mkdir($upload_dir, 0777, true); }

            $extension = strtolower(pathinfo($_FILES['scan_diplome']['name'], PATHINFO_EXTENSION));
            $nom_fichier_final = "dip_" . $id_f . "_" . time() . "." . $extension;
            $chemin_complet = $upload_dir . $nom_fichier_final;

            if (!move_uploaded_file($_FILES['scan_diplome']['tmp_name'], $chemin_complet)) {
                throw new Exception("Impossible d'enregistrer le fichier.");
            }
        }

        // 2. Insertion DIPLOME
        $sql1 = "INSERT INTO DIPLOME (lib_dipl, niv_etu, fich_url) VALUES (:lib, :niv, :fich)";
        $stmt1 = $db->prepare($sql1);
        $stmt1->execute([
            ':lib'  => $libelle,
            ':niv'  => $niveau,
            ':fich' => $nom_fichier_final
        ]);
        $id_d = $db->lastInsertId(); // ID du diplôme créé

        // 3. Insertion OBTENIR (Table de liaison)
        $sql2 = "INSERT INTO OBTENIR (id_fonc, id_dipl, ann_obte) VALUES (:id_f, :id_d, :annee)";
        $stmt2 = $db->prepare($sql2);
        $stmt2->execute([
            ':id_f'  => $id_f,
            ':id_d'  => $id_d,
            ':annee' => $annee
        ]);

        // 4. Insertion DOCUMENT (Corrigée pour inclure id_dipl)
        if ($nom_fichier_final) {
            // Note : on ajoute id_dipl pour respecter le MLD
            $sqlDoc = "INSERT INTO DOCUMENT (id_fonc, id_dipl, lib_doc, fich_url, date_enre) 
                       VALUES (:id_f, :id_d, :lib_doc, :url, NOW())";
            $stmtDoc = $db->prepare($sqlDoc);
            $stmtDoc->execute([
                ':id_f'    => $id_f,
                ':id_d'    => $id_d, // Lien avec le diplôme
                ':lib_doc' => "Diplôme : " . $libelle,
                ':url'     => $nom_fichier_final
            ]);
        }

        $db->commit();
        header("Location: ../views/profil_agent.php?id=" . $id_f . "&section=diplome&msg=add_ok");
        exit();

    } catch (Exception $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
        
        if ($nom_fichier_final && file_exists("../uploads/diplomes/" . $nom_fichier_final)) {
            unlink("../uploads/diplomes/" . $nom_fichier_final);
        }

        echo "Erreur système : " . $e->getMessage();
    }
}
?>
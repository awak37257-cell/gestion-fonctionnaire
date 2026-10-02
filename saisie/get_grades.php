<?php
require_once '../config.php'; 

// On affiche tous les grades disponibles, sans filtrer par organisation
try {
    $stmt = $db->query("SELECT id_affec, gra_post, tit_post FROM AFFECTATION ORDER BY gra_post ASC");
    $grades = $stmt->fetchAll();

    if (count($grades) > 0) {
        echo '<option value="">-- Sélectionner le grade --</option>';
        foreach ($grades as $row) {
            // [Sigle] Titre complet du poste
            echo "<option value='". htmlspecialchars($row['id_affec']) ."'>" 
                 . "[" . htmlspecialchars($row['gra_post']) . "] " 
                 . htmlspecialchars($row['tit_post']) . "</option>";
        }
    } else {
        echo '<option value="">Aucun grade disponible</option>';
    }
} catch (Exception $e) {
    echo '<option value="">Erreur de chargement</option>';
}
?>
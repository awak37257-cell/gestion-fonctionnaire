<?php
function notifierAdministrateurs(PDO $db, int $idFonctionnaire, string $type, int $idElement, string $titre, string $details, array $organisations): void
{
    $role = strtolower(trim((string)($_SESSION['rol'] ?? '')));
    $idSessionFonctionnaire = (int)($_SESSION['id_fonc'] ?? 0);

    if (!$idSessionFonctionnaire || $idSessionFonctionnaire !== $idFonctionnaire || in_array($role, ['admin', 'admin_local', 'gestionnaire'], true)) {
        return;
    }

    $organisations = array_values(array_unique(array_filter(array_map('intval', $organisations))));
    $sql = "SELECT id_user, id_org FROM UTILISATEURS WHERE rol = 'admin'";
    $params = [];
    if ($organisations) {
        $placeholders = implode(',', array_fill(0, count($organisations), '?'));
        $sql .= " OR (rol = 'admin_local' AND id_org IN ($placeholders))";
        $params = $organisations;
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $destinataires = $stmt->fetchAll();

    $insert = $db->prepare("INSERT INTO NOTIFICATIONS (id_user, id_fonc, type_element, id_element, id_org, titre, details) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($destinataires as $destinataire) {
        $insert->execute([
            (int)$destinataire['id_user'],
            $idFonctionnaire,
            $type,
            $idElement,
            $destinataire['id_org'] === null ? null : (int)$destinataire['id_org'],
            $titre,
            $details,
        ]);
    }
}

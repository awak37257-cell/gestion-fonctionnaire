CREATE TABLE IF NOT EXISTS NOTIFICATIONS (
    id_notification INT NOT NULL AUTO_INCREMENT,
    id_user INT NOT NULL,
    id_fonc INT NOT NULL,
    type_element ENUM('diplome', 'competence', 'mutation') NOT NULL,
    id_element INT NOT NULL,
    id_org INT DEFAULT NULL,
    titre VARCHAR(255) NOT NULL,
    details TEXT NOT NULL,
    statut ENUM('a_verifier', 'verifie', 'supprime') NOT NULL DEFAULT 'a_verifier',
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_traitement DATETIME DEFAULT NULL,
    id_traiteur INT DEFAULT NULL,
    PRIMARY KEY (id_notification),
    KEY idx_notifications_user_statut (id_user, statut, date_creation),
    KEY idx_notifications_element (type_element, id_element)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

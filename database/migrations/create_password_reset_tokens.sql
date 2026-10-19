CREATE TABLE IF NOT EXISTS PASSWORD_RESET_TOKENS (
    id_reset INT NOT NULL AUTO_INCREMENT,
    id_user INT NOT NULL,
    token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_expiration DATETIME NOT NULL,
    date_utilisation DATETIME DEFAULT NULL,
    PRIMARY KEY (id_reset),
    UNIQUE KEY uq_password_reset_token_hash (token_hash),
    KEY idx_password_reset_user_expiry (id_user, date_expiration),
    CONSTRAINT fk_password_reset_user FOREIGN KEY (id_user)
        REFERENCES UTILISATEURS (id_user) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
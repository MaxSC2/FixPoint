-- 018: public technician profile settings. Missing rows use safe public defaults in read queries.
CREATE TABLE technician_profiles (
    technician_id BIGINT UNSIGNED NOT NULL,
    headline VARCHAR(140) NULL,
    bio TEXT NULL,
    is_public TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (technician_id),
    KEY idx_technician_profiles_public (is_public, updated_at),
    CONSTRAINT fk_technician_profiles_user
        FOREIGN KEY (technician_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

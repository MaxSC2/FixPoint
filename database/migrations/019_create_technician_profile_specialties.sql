-- 019: normalized profile-to-service-category specialties.
CREATE TABLE technician_profile_specialties (
    technician_id BIGINT UNSIGNED NOT NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (technician_id, category_id),
    KEY idx_technician_profile_specialties_category (category_id, technician_id),
    CONSTRAINT fk_technician_specialties_profile
        FOREIGN KEY (technician_id) REFERENCES technician_profiles(technician_id) ON DELETE CASCADE,
    CONSTRAINT fk_technician_specialties_category
        FOREIGN KEY (category_id) REFERENCES service_categories(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

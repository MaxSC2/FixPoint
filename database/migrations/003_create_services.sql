-- 003: services catalogue. Prices use DECIMAL, never FLOAT.
CREATE TABLE services (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(160) NOT NULL,
    slug VARCHAR(180) NOT NULL,
    short_description VARCHAR(255) NULL,
    description TEXT NULL,
    price_from DECIMAL(10, 2) NULL,
    estimated_minutes INT UNSIGNED NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_services_slug (slug),
    KEY idx_services_category_active (category_id, is_active),
    KEY idx_services_active (is_active),
    CONSTRAINT fk_services_category FOREIGN KEY (category_id) REFERENCES service_categories (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

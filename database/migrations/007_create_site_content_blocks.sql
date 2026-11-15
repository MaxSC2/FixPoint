-- 007: editable public content blocks managed by administrators.
CREATE TABLE site_content_blocks (
    block_key VARCHAR(64) NOT NULL,
    title VARCHAR(180) NOT NULL,
    lead VARCHAR(255) NOT NULL,
    section_heading VARCHAR(180) NOT NULL,
    section_body TEXT NOT NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (block_key),
    KEY idx_site_content_updated_by (updated_by),
    CONSTRAINT fk_site_content_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

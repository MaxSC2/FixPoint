-- 020: one verified rating per completed repair request.
CREATE TABLE technician_reviews (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    technician_id BIGINT UNSIGNED NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    review_text VARCHAR(1500) NULL,
    status ENUM('published', 'hidden') NOT NULL DEFAULT 'published',
    moderated_by BIGINT UNSIGNED NULL,
    moderation_reason VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_technician_reviews_request (request_id),
    KEY idx_technician_reviews_public (technician_id, status, created_at),
    KEY idx_technician_reviews_customer (customer_id, created_at),
    CONSTRAINT chk_technician_reviews_rating CHECK (rating BETWEEN 1 AND 5),
    CONSTRAINT fk_technician_reviews_request
        FOREIGN KEY (request_id) REFERENCES repair_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_technician_reviews_customer
        FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_technician_reviews_technician
        FOREIGN KEY (technician_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_technician_reviews_moderator
        FOREIGN KEY (moderated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 010: chat messages inside a repair request.
-- The customer of the request, the assigned technician and administrators
-- share one thread, so support can answer without changing the workflow.
CREATE TABLE request_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id BIGINT UNSIGNED NOT NULL,
    author_id BIGINT UNSIGNED NOT NULL,
    body TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_request_messages_request (request_id, id),
    CONSTRAINT fk_request_messages_request FOREIGN KEY (request_id) REFERENCES repair_requests (id),
    CONSTRAINT fk_request_messages_author FOREIGN KEY (author_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

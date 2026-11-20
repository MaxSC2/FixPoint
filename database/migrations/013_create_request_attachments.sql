-- 013: private images attached to a request or to one chat message.
CREATE TABLE request_attachments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id BIGINT UNSIGNED NOT NULL,
    message_id BIGINT UNSIGNED NULL,
    uploader_id BIGINT UNSIGNED NOT NULL,
    storage_key VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    byte_size INT UNSIGNED NOT NULL,
    width INT UNSIGNED NOT NULL,
    height INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_request_attachments_storage_key (storage_key),
    KEY idx_request_attachments_request (request_id, message_id, id),
    KEY idx_request_attachments_message (message_id, id),
    CONSTRAINT fk_request_attachments_request FOREIGN KEY (request_id) REFERENCES repair_requests (id) ON DELETE CASCADE,
    CONSTRAINT fk_request_attachments_message FOREIGN KEY (message_id) REFERENCES request_messages (id) ON DELETE CASCADE,
    CONSTRAINT fk_request_attachments_uploader FOREIGN KEY (uploader_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

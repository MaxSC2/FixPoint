-- Per-user read cursor for each repair-request conversation.
CREATE TABLE chat_reads (
    request_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    last_read_message_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (request_id, user_id),
    KEY idx_chat_reads_user_updated (user_id, updated_at),
    CONSTRAINT fk_chat_reads_request FOREIGN KEY (request_id) REFERENCES repair_requests (id),
    CONSTRAINT fk_chat_reads_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

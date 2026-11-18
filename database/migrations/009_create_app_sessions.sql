-- 009: server-side sessions for deployments with ephemeral container filesystems.
CREATE TABLE app_sessions (
    session_id VARCHAR(128) NOT NULL,
    payload MEDIUMBLOB NOT NULL,
    expires_at BIGINT UNSIGNED NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (session_id),
    KEY idx_app_sessions_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

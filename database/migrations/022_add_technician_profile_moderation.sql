-- 022: separate technician preference from administrative profile moderation.
ALTER TABLE technician_profiles
    ADD COLUMN admin_is_visible TINYINT(1) NOT NULL DEFAULT 1 AFTER is_public,
    ADD COLUMN admin_moderation_reason VARCHAR(500) NULL AFTER admin_is_visible,
    ADD COLUMN admin_moderated_by BIGINT UNSIGNED NULL AFTER admin_moderation_reason,
    ADD KEY idx_technician_profiles_admin_visibility (admin_is_visible, admin_moderated_by),
    ADD CONSTRAINT fk_technician_profiles_admin_moderator
        FOREIGN KEY (admin_moderated_by) REFERENCES users(id) ON DELETE SET NULL;

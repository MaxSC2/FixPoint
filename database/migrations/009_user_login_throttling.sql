-- 009: throttle repeated login attempts and support password changes.
ALTER TABLE users
    ADD COLUMN login_failures INT UNSIGNED NOT NULL DEFAULT 0 AFTER is_active,
    ADD COLUMN login_locked_until DATETIME NULL AFTER login_failures;

-- 023: private storage key for technician profile avatar.
ALTER TABLE technician_profiles
    ADD COLUMN avatar_storage_key VARCHAR(255) NULL AFTER bio;

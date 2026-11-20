-- 017: technician availability calendar and customer appointments.
-- Reserved for the calendar stream; 018-020 belong to the profiles/reviews
-- stream. All appointment/block instants are stored in UTC so a technician's
-- display timezone never changes the underlying reservation.
--
-- This is the only migration number CAL-01 owns, so the calendar schema is
-- created here as an ordered group of DDL statements. Each statement is
-- IF NOT EXISTS: if a deployment is interrupted halfway, rerunning the same
-- immutable file completes the missing tables instead of failing on the ones
-- that already exist. No table is dropped and no calendar data is rewritten.

CREATE TABLE IF NOT EXISTS technician_calendar_settings (
    technician_id BIGINT UNSIGNED NOT NULL,
    timezone VARCHAR(64) NOT NULL DEFAULT 'UTC',
    slot_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 60,
    min_notice_minutes INT UNSIGNED NOT NULL DEFAULT 120,
    pending_hold_minutes INT UNSIGNED NOT NULL DEFAULT 1440,
    cancellation_cutoff_minutes INT UNSIGNED NOT NULL DEFAULT 120,
    is_configured TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (technician_id),
    CONSTRAINT fk_calendar_settings_technician
        FOREIGN KEY (technician_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS technician_calendar_availability (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    technician_id BIGINT UNSIGNED NOT NULL,
    weekday TINYINT UNSIGNED NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_calendar_availability_tech_day (technician_id, weekday, start_time),
    CONSTRAINT fk_calendar_availability_technician
        FOREIGN KEY (technician_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS technician_calendar_blocks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    technician_id BIGINT UNSIGNED NOT NULL,
    starts_at_utc DATETIME NOT NULL,
    ends_at_utc DATETIME NOT NULL,
    kind ENUM('vacation', 'holiday', 'personal', 'other') NOT NULL DEFAULT 'personal',
    title VARCHAR(160) NOT NULL,
    notes TEXT NULL,
    is_all_day TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_calendar_blocks_tech_range (technician_id, starts_at_utc, ends_at_utc),
    CONSTRAINT fk_calendar_blocks_technician
        FOREIGN KEY (technician_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS technician_appointments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    technician_id BIGINT UNSIGNED NOT NULL,
    meeting_type ENUM('device_dropoff', 'device_pickup', 'consultation') NOT NULL,
    status ENUM(
        'pending', 'confirmed', 'declined', 'reschedule_proposed',
        'cancelled', 'completed', 'no_show'
    ) NOT NULL DEFAULT 'pending',
    starts_at_utc DATETIME NOT NULL,
    ends_at_utc DATETIME NOT NULL,
    timezone VARCHAR(64) NOT NULL,
    purpose VARCHAR(200) NOT NULL,
    customer_comment TEXT NULL,
    technician_note TEXT NULL,
    proposed_starts_at_utc DATETIME NULL,
    proposed_ends_at_utc DATETIME NULL,
    proposed_by BIGINT UNSIGNED NULL,
    reschedule_from_status VARCHAR(40) NULL,
    pending_expires_at_utc DATETIME NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    confirmed_at_utc DATETIME NULL,
    declined_at_utc DATETIME NULL,
    cancelled_at_utc DATETIME NULL,
    completed_at_utc DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_appointments_technician_start (technician_id, starts_at_utc),
    KEY idx_appointments_customer_start (customer_id, starts_at_utc),
    KEY idx_appointments_request (request_id, created_at),
    KEY idx_appointments_status_expiry (status, pending_expires_at_utc),
    CONSTRAINT fk_appointments_request
        FOREIGN KEY (request_id) REFERENCES repair_requests (id) ON DELETE CASCADE,
    CONSTRAINT fk_appointments_customer
        FOREIGN KEY (customer_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_appointments_technician
        FOREIGN KEY (technician_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_appointments_created_by
        FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_appointments_proposed_by
        FOREIGN KEY (proposed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS technician_appointment_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    appointment_id BIGINT UNSIGNED NOT NULL,
    actor_id BIGINT UNSIGNED NULL,
    action VARCHAR(40) NOT NULL,
    from_status VARCHAR(40) NULL,
    to_status VARCHAR(40) NOT NULL,
    previous_starts_at_utc DATETIME NULL,
    previous_ends_at_utc DATETIME NULL,
    new_starts_at_utc DATETIME NULL,
    new_ends_at_utc DATETIME NULL,
    note TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_appointment_events_appointment (appointment_id, created_at, id),
    CONSTRAINT fk_appointment_events_appointment
        FOREIGN KEY (appointment_id) REFERENCES technician_appointments (id) ON DELETE CASCADE,
    CONSTRAINT fk_appointment_events_actor
        FOREIGN KEY (actor_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

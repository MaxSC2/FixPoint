-- 021: retain the published troubleshooting guide that led to a customer request.
ALTER TABLE repair_requests
    ADD COLUMN source_guide_id BIGINT UNSIGNED NULL AFTER service_id,
    ADD KEY idx_repair_requests_source_guide (source_guide_id),
    ADD CONSTRAINT fk_repair_requests_source_guide
        FOREIGN KEY (source_guide_id) REFERENCES repair_guides(id) ON DELETE SET NULL;

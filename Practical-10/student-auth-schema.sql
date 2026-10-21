-- Run once against the studenthub database.
ALTER TABLE students
    ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'student',
    ADD COLUMN remember_selector CHAR(32) NULL,
    ADD COLUMN remember_token_hash CHAR(64) NULL,
    ADD COLUMN remember_expires_at DATETIME NULL,
    ADD UNIQUE KEY uq_students_remember_selector (remember_selector);

-- To grant administrator access, use phpMyAdmin to set role = 'admin'
-- for the intended account. New registrations remain 'student' by default.

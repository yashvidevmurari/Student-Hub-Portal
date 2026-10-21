-- Run once against the studenthub database before using student-signup.php.
-- Resolve any duplicate emails before adding the unique email key.
ALTER TABLE students
    ADD COLUMN username VARCHAR(30) NULL AFTER name,
    ADD UNIQUE KEY uq_students_username (username),
    ADD UNIQUE KEY uq_students_email (email);

-- Run once against the studenthub database after the events table exists.
-- Adds the optional poster URL used by admin-events.php and events.php.
ALTER TABLE events
    ADD COLUMN poster_path VARCHAR(255) NULL,
    MODIFY COLUMN event_name VARCHAR(150) NOT NULL,
    MODIFY COLUMN description TEXT NULL;

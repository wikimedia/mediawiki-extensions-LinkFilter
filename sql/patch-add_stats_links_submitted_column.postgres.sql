-- Tracks submitted links
ALTER TABLE user_stats ADD COLUMN stats_links_submitted INTEGER NOT NULL default 0;

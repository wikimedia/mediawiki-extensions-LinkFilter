-- Tracks all approved links that have been submitted by the user
ALTER TABLE user_stats ADD COLUMN stats_links_approved INTEGER NOT NULL default 0;

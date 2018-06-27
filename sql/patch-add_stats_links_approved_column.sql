-- Tracks all approved links that have been submitted by the user
ALTER TABLE /*_*/user_stats ADD COLUMN stats_links_approved int(11) NOT NULL default 0;

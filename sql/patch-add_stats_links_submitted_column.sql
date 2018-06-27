-- Tracks submitted links
ALTER TABLE /*_*/user_stats ADD COLUMN stats_links_submitted int(11) NOT NULL default 0;

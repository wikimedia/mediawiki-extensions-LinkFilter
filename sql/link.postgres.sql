DROP SEQUENCE IF EXISTS link_link_id_seq CASCADE;
CREATE SEQUENCE link_link_id_seq;

CREATE TABLE link (
  link_id INTEGER NOT NULL PRIMARY KEY DEFAULT nextval('link_link_id_seq'),
  link_name TEXT NOT NULL,
  link_description TEXT NOT NULL,
  link_page_id INTEGER NOT NULL default 0,
  link_url TEXT,
  link_type SMALLINT NOT NULL default 0,
  link_status SMALLINT NOT NULL default 0,
  link_submitter_actor INTEGER NOT NULL,
  link_submit_date TIMESTAMPTZ default NULL,
  link_approved_date TIMESTAMPTZ default NULL,
  link_comment_count INTEGER NOT NULL default 0
);

ALTER SEQUENCE link_link_id_seq OWNED BY link.link_id;

CREATE INDEX link_approved_date ON link (link_approved_date);
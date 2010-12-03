CREATE TABLE IF NOT EXISTS plugin_trackback(
	num INTEGER PRIMARY KEY,
	pagename TEXT,
	title TEXT,
	excerpt TEXT,
	url TEXT,
	blog_name TEXT,
	timestamp INTEGER
);
CREATE INDEX IF NOT EXISTS plugin_trackback_index_pagename ON plugin_trackback(pagename);

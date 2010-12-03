CREATE TABLE IF NOT EXISTS plugin_referrer(
	pagename TEXT,
	url TEXT,
	count INTEGER
);
CREATE INDEX IF NOT EXISTS plugin_referrer_index_pagename ON plugin_referrer(pagename);

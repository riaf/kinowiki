CREATE TABLE IF NOT EXISTS plugin_counter(
	pagename TEXT PRIMARY KEY,
	total INTEGER,
	today INTEGER,
	yesterday INTEGER,
	date TEXT
);

CREATE TABLE IF NOT EXISTS settings (
    id INTEGER PRIMARY KEY CHECK(id=1),
    username TEXT NOT NULL,
    password TEXT NOT NULL,
    path TEXT NOT NULL DEFAULT '/admin',
    floating INTEGER NOT NULL DEFAULT 0,
    version INTEGER NOT NULL DEFAULT 1
);
CREATE TABLE IF NOT EXISTS providers (
    name TEXT PRIMARY KEY,
    secret TEXT NOT NULL,
    model TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS attempts (
    address TEXT PRIMARY KEY,
    failures INTEGER NOT NULL,
    until_at INTEGER NOT NULL
);
CREATE TABLE IF NOT EXISTS jobs (
    id TEXT PRIMARY KEY,
    request_id TEXT UNIQUE NOT NULL,
    prompt TEXT NOT NULL,
    provider TEXT NOT NULL,
    model TEXT NOT NULL,
    status TEXT NOT NULL,
    response TEXT,
    proposal TEXT,
    base TEXT,
    error TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

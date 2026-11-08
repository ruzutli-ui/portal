CREATE TABLE IF NOT EXISTS kunden (
    id       INTEGER PRIMARY KEY AUTOINCREMENT,
    email    TEXT NOT NULL UNIQUE,
    firma    TEXT NOT NULL DEFAULT '',
    vorname  TEXT NOT NULL DEFAULT '',
    name     TEXT NOT NULL DEFAULT '',
    strasse  TEXT NOT NULL DEFAULT '',
    plz      TEXT NOT NULL DEFAULT '',
    ort      TEXT NOT NULL DEFAULT ''
);

CREATE TABLE IF NOT EXISTS produkte (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    artikelnr     TEXT NOT NULL UNIQUE,
    kategorie     TEXT NOT NULL,
    bezeichnung   TEXT NOT NULL,
    beschreibung  TEXT NOT NULL DEFAULT '',
    einheit       TEXT NOT NULL DEFAULT 'Stk.',
    preis         REAL NOT NULL
);

CREATE TABLE IF NOT EXISTS offerten (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    nummer     TEXT NOT NULL UNIQUE,
    kunde_id   INTEGER NOT NULL REFERENCES kunden(id),
    titel      TEXT NOT NULL DEFAULT '',
    bemerkung  TEXT NOT NULL DEFAULT '',
    status     TEXT NOT NULL DEFAULT 'offen',
    erstellt   TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
);

CREATE TABLE IF NOT EXISTS offert_positionen (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    offerte_id   INTEGER NOT NULL REFERENCES offerten(id) ON DELETE CASCADE,
    artikelnr    TEXT NOT NULL,
    bezeichnung  TEXT NOT NULL,
    einheit      TEXT NOT NULL,
    preis        REAL NOT NULL,
    menge        INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS einstellungen (
    schluessel  TEXT PRIMARY KEY,
    wert        TEXT NOT NULL
);

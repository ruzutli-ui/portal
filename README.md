# Alpvolt Kundenportal (Prototyp)

Kunden stellen im Shop Produkte und Dienstleistungen zusammen und erstellen daraus eigene Offerten. Alpvolt verwaltet Offerten, Kunden und Sortiment im Admin-Bereich.

## Starten

Voraussetzung: PHP 8.1+ mit `pdo_sqlite`.

```bash
php -S localhost:8080
```

- Kundenportal: http://localhost:8080/
- Admin: http://localhost:8080/admin/ (Standardpasswort `alpvolt-admin`, für den Betrieb `ALPVOLT_ADMIN_PASSWORT` setzen)

Die SQLite-Datenbank `portal.db` wird beim ersten Aufruf angelegt und mit dem Demo-Sortiment aus `seed.sql` gefüllt.

## Funktionen

**Kunden:** Anmeldung per E-Mail · Shop mit Kategorien · Offert-Korb · Offerte mit MwSt. 8.1 %, Gültigkeit 30 Tage, Druck/PDF · annehmen, ablehnen, als Vorlage verwenden

**Admin:** Übersicht mit Kennzahlen · Offerten filtern, Status ändern, interne Notiz · Kunden mit Offerten · Produkte erfassen, bearbeiten, aus-/einblenden

## Bekannte Einschränkungen

- Kunden-Login ohne Passwort, ein gemeinsames Admin-Passwort
- Keine E-Mail-Benachrichtigungen
- Sortiment und Preise sind Platzhalter

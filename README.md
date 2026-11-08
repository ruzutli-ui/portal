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

**Admin:** Übersicht mit Kennzahlen · Offerten filtern, Status ändern, interne Notiz · Kunden mit Offerten · Produkte erfassen, bearbeiten, aus-/einblenden · Import aus der Sales Machine

## Preise: UVP und Kundenpreis

Jedes Produkt hat eine **UVP** und einen **Kundenpreis**. Im Shop wird die UVP angezeigt; ist der Kundenpreis tiefer, erscheint die UVP durchgestrichen mit Rabatt in Prozent. Offerten rechnen mit dem Kundenpreis und weisen die Ersparnis gegenüber UVP aus.

## Import aus der Sales Machine

Admin → Produkte → **Import aus Sales Machine**:

1. Produktliste aus der Sales Machine als **CSV** oder **Excel (.xlsx)** exportieren und hochladen.
2. Spalten werden automatisch erkannt (z. B. «Art.-Nr.», «Artikelbezeichnung», «UVP», «Kundenpreis», «Warengruppe») und lassen sich anpassen.
3. Vorschau prüfen (neu / Preis geändert / übersprungen) und importieren.

- Pflicht: Artikelnummer, Bezeichnung, UVP. Optional: Kundenpreis, Kategorie, Beschreibung, Einheit.
- Ohne Kundenpreis gilt: Kundenpreis = UVP − Standardrabatt (einstellbar, auf 5 Rappen gerundet).
- Bestehende Produkte werden über die Artikelnummer aktualisiert; bestehende Offerten behalten ihre Preise.
- Optional werden früher importierte Produkte, die im neuen Export fehlen, im Shop ausgeblendet.
- Beträge wie `1'234.50`, `1’290.–` oder `1.234,50` werden erkannt, CSV mit `;`, `,` oder Tab, UTF-8 oder Windows-Zeichensatz.

Eine Beispieldatei liegt unter `beispiel/sales-machine-export.csv`.

## Bekannte Einschränkungen

- Kunden-Login ohne Passwort, ein gemeinsames Admin-Passwort
- Keine E-Mail-Benachrichtigungen
- Sortiment und Preise sind Platzhalter

-- Demo-Sortiment (Platzhalter, Preise in CHF exkl. MwSt.)
INSERT OR IGNORE INTO produkte (artikelnr, kategorie, bezeichnung, beschreibung, einheit, preis) VALUES
('PV-430', 'Solarmodule', 'Solarmodul 430 Wp Full Black', 'Monokristallin, 108 Halbzellen, 25 Jahre Produktgarantie', 'Stk.', 189.00),
('PV-500', 'Solarmodule', 'Solarmodul 500 Wp Glas-Glas', 'Bifazial, n-Type, ideal für Flachdach und Fassade', 'Stk.', 239.00),
('UK-SD',  'Solarmodule', 'Unterkonstruktion Schrägdach', 'Schienen, Dachhaken und Klemmen pro Modul', 'Set', 68.00),
('UK-FD',  'Solarmodule', 'Unterkonstruktion Flachdach', 'Ost-West-Aufständerung mit Ballastwannen pro Modul', 'Set', 84.00),
('WR-8',   'Wechselrichter', 'Hybrid-Wechselrichter 8 kW', 'Dreiphasig, 2 MPP-Tracker, batteriefähig', 'Stk.', 2450.00),
('WR-15',  'Wechselrichter', 'Wechselrichter 15 kW', 'Dreiphasig, 2 MPP-Tracker, für grössere Anlagen', 'Stk.', 2980.00),
('BS-10',  'Speicher', 'Batteriespeicher 10 kWh', 'LiFePO4, modular erweiterbar, 10 Jahre Garantie', 'Stk.', 6900.00),
('BS-MOD', 'Speicher', 'Speichererweiterung 5 kWh', 'Zusatzmodul für Batteriespeicher', 'Stk.', 3150.00),
('WB-11',  'E-Mobilität', 'Wallbox 11 kW', 'Mit PV-Überschussladen und App-Steuerung', 'Stk.', 1290.00),
('WB-22',  'E-Mobilität', 'Wallbox 22 kW mit Lastmanagement', 'Für Mehrfamilienhäuser und Firmenparkplätze', 'Stk.', 1890.00),
('EM-SM',  'Zubehör', 'Smart Meter dreiphasig', 'Für Eigenverbrauchsoptimierung und Monitoring', 'Stk.', 420.00),
('MON-1',  'Zubehör', 'Monitoring-Abo 1 Jahr', 'Online-Portal mit Ertrags- und Störungsmeldungen', 'Jahr', 120.00),
('DL-MON', 'Dienstleistungen', 'Montage pro Modul', 'Fachgerechte Montage inkl. Verkabelung', 'Stk.', 95.00),
('DL-ELE', 'Dienstleistungen', 'Elektroinstallation & Inbetriebnahme', 'AC-Anschluss, Sicherheitsnachweis, Inbetriebnahme', 'Pauschal', 1850.00),
('DL-GER', 'Dienstleistungen', 'Gerüst bis 2 Stockwerke', 'Inkl. Auf- und Abbau', 'Pauschal', 1400.00),
('DL-ADM', 'Dienstleistungen', 'Bewilligung & Fördergesuch', 'Meldeverfahren, Pronovo-Anmeldung, EVU-Anschlussgesuch', 'Pauschal', 650.00);

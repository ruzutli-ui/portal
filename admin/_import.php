<?php
// Liest CSV- und Excel-Dateien (z. B. Export aus der Sales Machine) als Tabelle.

/** Zielfelder des Imports mit typischen Spaltennamen zur automatischen Zuordnung. */
const IMPORT_FELDER = [
    'artikelnr'    => ['label' => 'Artikelnummer', 'pflicht' => true,
                       'namen' => ['artikelnr', 'artikelnummer', 'artnr', 'art', 'sku', 'produktnummer', 'produktnr', 'nummer', 'nr', 'itemno', 'itemnumber', 'code', 'artikelcode']],
    'bezeichnung'  => ['label' => 'Bezeichnung', 'pflicht' => true,
                       'namen' => ['bezeichnung', 'artikelbezeichnung', 'produktbezeichnung', 'produktname', 'artikelname', 'name', 'titel', 'produkt', 'artikel', 'kurztext']],
    'uvp'          => ['label' => 'UVP', 'pflicht' => true,
                       'namen' => ['uvp', 'uvpchf', 'listenpreis', 'richtpreis', 'verkaufspreis', 'vkpreis', 'vk', 'bruttopreis', 'katalogpreis', 'preis', 'preischf', 'price', 'msrp', 'rrp']],
    'kundenpreis'  => ['label' => 'Kundenpreis', 'pflicht' => false,
                       'namen' => ['kundenpreis', 'nettopreis', 'ihrpreis', 'rabattpreis', 'aktionspreis', 'sonderpreis', 'portalpreis', 'endpreis']],
    'kategorie'    => ['label' => 'Kategorie', 'pflicht' => false,
                       'namen' => ['kategorie', 'warengruppe', 'produktgruppe', 'artikelgruppe', 'gruppe', 'category', 'sortiment']],
    'beschreibung' => ['label' => 'Beschreibung', 'pflicht' => false,
                       'namen' => ['beschreibung', 'kurzbeschreibung', 'langtext', 'produktbeschreibung', 'details', 'text', 'description']],
    'einheit'      => ['label' => 'Einheit', 'pflicht' => false,
                       'namen' => ['einheit', 'mengeneinheit', 'me', 'verkaufseinheit', 'unit']],
];

function spaltenname_normalisieren(string $s): string
{
    $s = mb_strtolower(trim($s));
    $s = strtr($s, ['ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss']);
    return preg_replace('/[^a-z0-9]/', '', $s);
}

/** Ordnet jedem Zielfeld die passendste Spalte zu (Index oder null). */
function spalten_erraten(array $kopf): array
{
    $normal = array_map('spaltenname_normalisieren', $kopf);
    $zuordnung = [];
    $vergeben = [];
    // Spalten, die exakt einem bestimmten Feld entsprechen, werden nicht per Teiltreffer anderen Feldern zugeordnet.
    $exaktFeld = [];
    foreach ($normal as $i => $spalte) {
        foreach (IMPORT_FELDER as $f => $d) {
            if (in_array($spalte, $d['namen'], true)) {
                $exaktFeld[$i] = $f;
                break;
            }
        }
    }
    foreach (IMPORT_FELDER as $feld => $def) {
        $zuordnung[$feld] = null;
        // Zuerst exakte Treffer in der Reihenfolge der Synonyme, dann Teiltreffer.
        foreach ([true, false] as $exakt) {
            foreach ($def['namen'] as $name) {
                foreach ($normal as $i => $spalte) {
                    if (isset($vergeben[$i]) || $spalte === '' || (!$exakt && ($exaktFeld[$i] ?? $feld) !== $feld)) {
                        continue;
                    }
                    if ($exakt ? $spalte === $name : (strlen($name) >= 4 && str_contains($spalte, $name))) {
                        $zuordnung[$feld] = $i;
                        $vergeben[$i] = true;
                        continue 4;
                    }
                }
            }
        }
    }
    return $zuordnung;
}

/** Liefert ['kopf' => [...], 'zeilen' => [[...], ...]] oder wirft eine Exception mit Klartext. */
function tabelle_lesen(string $pfad, string $dateiname): array
{
    $endung = strtolower(pathinfo($dateiname, PATHINFO_EXTENSION));
    $zeilen = match ($endung) {
        'xlsx'               => xlsx_lesen($pfad),
        'csv', 'txt', 'tsv'  => csv_lesen($pfad),
        'xls'                => throw new RuntimeException('Alte Excel-Dateien (.xls) werden nicht unterstützt. Bitte als .xlsx oder .csv speichern.'),
        default              => throw new RuntimeException('Bitte eine .csv- oder .xlsx-Datei hochladen.'),
    };
    // Leere Zeilen entfernen, Werte trimmen
    $zeilen = array_values(array_filter(
        array_map(fn ($z) => array_map(fn ($w) => is_string($w) ? trim($w) : $w, $z), $zeilen),
        fn ($z) => implode('', array_map('strval', $z)) !== ''
    ));
    if (count($zeilen) < 2) {
        throw new RuntimeException('Die Datei enthält keine Produktzeilen (erwartet: Kopfzeile + mindestens eine Zeile).');
    }
    $kopf = array_map('strval', array_shift($zeilen));
    $breite = count($kopf);
    foreach ($zeilen as &$z) {
        $z = array_slice(array_pad($z, $breite, ''), 0, $breite);
    }
    return ['kopf' => $kopf, 'zeilen' => $zeilen];
}

function csv_lesen(string $pfad): array
{
    $inhalt = file_get_contents($pfad);
    $inhalt = preg_replace('/^\xEF\xBB\xBF/', '', $inhalt); // BOM
    if (!mb_check_encoding($inhalt, 'UTF-8')) {
        $inhalt = mb_convert_encoding($inhalt, 'UTF-8', 'Windows-1252'); // typischer Excel-Export
    }
    $ersteZeile = strtok($inhalt, "\r\n") ?: '';
    $trenner = ';';
    $max = -1;
    foreach ([';', ',', "\t", '|'] as $t) {
        if (substr_count($ersteZeile, $t) > $max) {
            $max = substr_count($ersteZeile, $t);
            $trenner = $t;
        }
    }
    $f = fopen('php://memory', 'r+');
    fwrite($f, $inhalt);
    rewind($f);
    $zeilen = [];
    while (($z = fgetcsv($f, 0, $trenner, '"', '')) !== false) {
        $zeilen[] = $z;
    }
    fclose($f);
    return $zeilen;
}

function xlsx_lesen(string $pfad): array
{
    if (!class_exists(ZipArchive::class)) {
        throw new RuntimeException('Excel-Dateien können auf diesem Server nicht gelesen werden (PHP-Erweiterung zip fehlt). Bitte als .csv speichern.');
    }
    $zip = new ZipArchive();
    if ($zip->open($pfad) !== true) {
        throw new RuntimeException('Die Excel-Datei konnte nicht geöffnet werden.');
    }
    $texte = [];
    if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
        $sst = simplexml_load_string($xml);
        foreach ($sst->si as $si) {
            // Text kann direkt in <t> oder verteilt in <r><t> (Formatierungen) stehen
            $texte[] = isset($si->t) ? (string) $si->t : implode('', array_map('strval', $si->xpath('.//*[local-name()="t"]')));
        }
    }
    $blatt = erstes_blatt($zip);
    $xml = $zip->getFromName($blatt);
    $zip->close();
    if ($xml === false) {
        throw new RuntimeException('Das erste Tabellenblatt wurde nicht gefunden.');
    }
    $sheet = simplexml_load_string($xml);
    $zeilen = [];
    foreach ($sheet->sheetData->row as $row) {
        $zeile = [];
        foreach ($row->c as $c) {
            $index = spalte_index(preg_replace('/\d+/', '', (string) $c['r']));
            $typ = (string) $c['t'];
            $wert = match ($typ) {
                's'         => $texte[(int) $c->v] ?? '',
                'inlineStr' => (string) $c->is->t,
                'b'         => (string) $c->v === '1' ? 'WAHR' : 'FALSCH',
                default     => isset($c->v) ? (is_numeric((string) $c->v) ? (float) (string) $c->v : (string) $c->v) : '',
            };
            $zeile[$index] = $wert;
        }
        if ($zeile) {
            $max = max(array_keys($zeile));
            $zeilen[(int) $row['r'] - 1] = array_replace(array_fill(0, $max + 1, ''), $zeile);
        }
    }
    ksort($zeilen);
    return array_values($zeilen);
}

function erstes_blatt(ZipArchive $zip): string
{
    $wb = $zip->getFromName('xl/workbook.xml');
    $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
    if ($wb && $rels) {
        $wbXml = simplexml_load_string($wb);
        $relXml = simplexml_load_string($rels);
        $sheets = $wbXml->xpath('//*[local-name()="sheet"]');
        if ($sheets) {
            $rid = (string) $sheets[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            foreach ($relXml->Relationship as $rel) {
                if ((string) $rel['Id'] === $rid) {
                    $ziel = ltrim((string) $rel['Target'], '/');
                    return str_starts_with($ziel, 'xl/') ? $ziel : 'xl/' . $ziel;
                }
            }
        }
    }
    return 'xl/worksheets/sheet1.xml';
}

function spalte_index(string $buchstaben): int
{
    $n = 0;
    foreach (str_split(strtoupper($buchstaben)) as $b) {
        $n = $n * 26 + (ord($b) - 64);
    }
    return $n - 1;
}

/**
 * Wandelt die Dateizeilen gemäss Zuordnung in Produkte um.
 * Ohne Kundenpreis-Spalte gilt: Kundenpreis = UVP minus Standardrabatt (auf 5 Rp. gerundet).
 */
function import_auswerten(array $tabelle, array $zuordnung, float $rabatt): array
{
    $ergebnis = ['gueltig' => [], 'fehler' => []];
    $gesehen = [];
    foreach ($tabelle['zeilen'] as $nr => $z) {
        $wert = fn (string $feld) => $zuordnung[$feld] === null ? '' : $z[$zuordnung[$feld]];
        $zeile = $nr + 2; // +1 Kopfzeile, +1 weil Excel bei 1 beginnt
        $artikelnr = trim((string) $wert('artikelnr'));
        if (is_float($wert('artikelnr')) && floor($wert('artikelnr')) == $wert('artikelnr')) {
            $artikelnr = (string) (int) $wert('artikelnr'); // 1001.0 → "1001"
        }
        $bezeichnung = trim((string) $wert('bezeichnung'));
        $uvp = betrag_lesen($wert('uvp'));
        $kundenpreis = $zuordnung['kundenpreis'] === null ? null : betrag_lesen($wert('kundenpreis'));

        $grund = match (true) {
            $artikelnr === ''                => 'Artikelnummer fehlt',
            $bezeichnung === ''              => 'Bezeichnung fehlt',
            $uvp === null || $uvp < 0        => 'UVP fehlt oder ist ungültig',
            isset($gesehen[$artikelnr])      => 'Artikelnummer doppelt (Zeile ' . $gesehen[$artikelnr] . ')',
            default                          => null,
        };
        if ($grund !== null) {
            $ergebnis['fehler'][] = ['zeile' => $zeile, 'grund' => $grund, 'artikelnr' => $artikelnr, 'bezeichnung' => $bezeichnung];
            continue;
        }
        $gesehen[$artikelnr] = $zeile;
        $preis = $kundenpreis !== null && $kundenpreis >= 0 ? $kundenpreis : runden_5rp($uvp * (1 - $rabatt / 100));
        $ergebnis['gueltig'][] = [
            'artikelnr'    => $artikelnr,
            'bezeichnung'  => $bezeichnung,
            'uvp'          => round($uvp, 2),
            'preis'        => round($preis, 2),
            'kategorie'    => trim((string) $wert('kategorie')),
            'beschreibung' => trim((string) $wert('beschreibung')),
            'einheit'      => trim((string) $wert('einheit')),
        ];
    }
    return $ergebnis;
}

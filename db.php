<?php
// Gemeinsame Funktionen für das Kundenportal (Prototyp).
session_start();

const MWST_SATZ = 0.081;

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . __DIR__ . '/portal.db');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec(file_get_contents(__DIR__ . '/schema.sql'));
        spalte_sicherstellen($pdo, 'produkte', 'aktiv', 'INTEGER NOT NULL DEFAULT 1');
        spalte_sicherstellen($pdo, 'offerten', 'interne_notiz', "TEXT NOT NULL DEFAULT ''");
        spalte_sicherstellen($pdo, 'offerten', 'geaendert', 'TEXT');
        spalte_sicherstellen($pdo, 'produkte', 'quelle', "TEXT NOT NULL DEFAULT ''");
        spalte_sicherstellen($pdo, 'produkte', 'importiert', 'TEXT');
        spalte_sicherstellen($pdo, 'offert_positionen', 'uvp', 'REAL');
        $neueUvp = spalte_sicherstellen($pdo, 'produkte', 'uvp', 'REAL');
        $pdo->exec(file_get_contents(__DIR__ . '/seed.sql'));
        if ($neueUvp) {
            // Bisherige Preise gelten als UVP, solange nichts anderes importiert wurde.
            $pdo->exec('UPDATE produkte SET uvp = preis WHERE uvp IS NULL');
        }
    }
    return $pdo;
}

/** Ergänzt eine Spalte in bestehenden Datenbanken (einfache Migration). */
function spalte_sicherstellen(PDO $pdo, string $tabelle, string $spalte, string $definition): bool
{
    $spalten = $pdo->query("PRAGMA table_info($tabelle)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (in_array($spalte, $spalten, true)) {
        return false;
    }
    $pdo->exec("ALTER TABLE $tabelle ADD COLUMN $spalte $definition");
    return true;
}

function einstellung(string $schluessel, ?string $standard = null): ?string
{
    $stmt = db()->prepare('SELECT wert FROM einstellungen WHERE schluessel = ?');
    $stmt->execute([$schluessel]);
    $wert = $stmt->fetchColumn();
    return $wert === false ? $standard : $wert;
}

function einstellung_setzen(string $schluessel, string $wert): void
{
    db()->prepare('INSERT INTO einstellungen (schluessel, wert) VALUES (?, ?)
                   ON CONFLICT(schluessel) DO UPDATE SET wert = excluded.wert')->execute([$schluessel, $wert]);
}

/**
 * Liest Beträge in Schweizer und deutscher Schreibweise:
 * "1'234.50", "1’234.–", "CHF 1 234,50", "1.234,50", "239".
 */
function betrag_lesen(mixed $wert): ?float
{
    if (is_int($wert) || is_float($wert)) {
        return (float) $wert;
    }
    $s = preg_replace('/[.,]\s*[-–—]+\s*$/u', '', trim((string) $wert)); // "1'234.–"
    $s = preg_replace('/[^0-9.,\-]/', '', $s);
    if ($s === '' || $s === '-') {
        return null;
    }
    $komma = strrpos($s, ',');
    $punkt = strrpos($s, '.');
    if ($komma !== false && $punkt !== false) {
        // Das hintere Zeichen ist das Dezimaltrennzeichen.
        $s = $komma > $punkt ? str_replace(['.', ','], ['', '.'], $s) : str_replace(',', '', $s);
    } elseif ($komma !== false) {
        $s = preg_match('/,\d{1,2}$/', $s) && substr_count($s, ',') === 1 ? str_replace(',', '.', $s) : str_replace(',', '', $s);
    } elseif ($punkt !== false && substr_count($s, '.') > 1) {
        $s = str_replace('.', '', $s);
    }
    return is_numeric($s) ? (float) $s : null;
}

/** Rabatt in Prozent gegenüber UVP (0, wenn kein Rabatt). */
function rabatt_prozent(?float $uvp, float $preis): int
{
    return $uvp && $uvp > $preis ? (int) round((1 - $preis / $uvp) * 100) : 0;
}

/** Rundet auf 5 Rappen. */
function runden_5rp(float $betrag): float
{
    return round($betrag * 20) / 20;
}

const STATUS = ['offen' => 'Offen', 'angenommen' => 'Angenommen', 'abgelehnt' => 'Abgelehnt'];

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function chf(float $betrag): string
{
    return 'CHF ' . number_format($betrag, 2, '.', "\u{2019}");
}

function redirect(string $ziel): never
{
    header('Location: ' . $ziel);
    exit;
}

// ─── Anmeldung ──────────────────────────────────────────────
function kunde(): ?array
{
    if (empty($_SESSION['kunde_id'])) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM kunden WHERE id = ?');
    $stmt->execute([$_SESSION['kunde_id']]);
    return $stmt->fetch() ?: null;
}

function login_erforderlich(): array
{
    $k = kunde();
    if (!$k) {
        redirect('login.php');
    }
    return $k;
}

// ─── CSRF-Schutz ────────────────────────────────────────────
function csrf_token(): string
{
    $_SESSION['csrf'] ??= bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}

function csrf_feld(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_pruefen(): void
{
    if (!hash_equals(csrf_token(), $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('Ungültige Anfrage. Bitte Seite neu laden.');
    }
}

// ─── Offert-Korb (in der Session) ───────────────────────────
function korb(): array
{
    return $_SESSION['korb'] ?? [];
}

function korb_setzen(int $produktId, int $menge): void
{
    if ($menge > 0) {
        $_SESSION['korb'][$produktId] = min($menge, 9999);
    } else {
        unset($_SESSION['korb'][$produktId]);
    }
}

function korb_anzahl(): int
{
    return array_sum(korb());
}

/** Liefert die Positionen des Korbs mit Produktdaten und Zeilentotal. */
function korb_positionen(): array
{
    $korb = korb();
    if (!$korb) {
        return [];
    }
    $platzhalter = implode(',', array_fill(0, count($korb), '?'));
    $stmt = db()->prepare("SELECT * FROM produkte WHERE id IN ($platzhalter) AND aktiv = 1 ORDER BY kategorie, bezeichnung");
    $stmt->execute(array_keys($korb));
    $positionen = [];
    foreach ($stmt->fetchAll() as $p) {
        $p['menge'] = $korb[$p['id']];
        $p['total'] = $p['preis'] * $p['menge'];
        $positionen[] = $p;
    }
    return $positionen;
}

/** Zwischensumme, MwSt. und Total für eine Liste von Positionen. */
function summen(array $positionen): array
{
    $netto = 0.0;
    foreach ($positionen as $p) {
        $netto += $p['preis'] * $p['menge'];
    }
    $uvp = 0.0;
    foreach ($positionen as $p) {
        $uvp += ($p['uvp'] ?? $p['preis']) * $p['menge'];
    }
    $mwst = runden_5rp($netto * MWST_SATZ);
    return ['netto' => $netto, 'mwst' => $mwst, 'brutto' => $netto + $mwst, 'ersparnis' => max(0.0, $uvp - $netto)];
}

function flash(?string $text = null): ?string
{
    if ($text !== null) {
        $_SESSION['flash'] = $text;
        return null;
    }
    $t = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $t;
}

/** Preisanzeige im Shop: UVP, bei Rabatt durchgestrichen mit Kundenpreis. */
function preis_block(array $p): string
{
    $uvp = (float) ($p['uvp'] ?? $p['preis']);
    $preis = (float) $p['preis'];
    $einheit = '<small>/ ' . e($p['einheit']) . '</small>';
    if ($r = rabatt_prozent($uvp, $preis)) {
        return '<div class="preis">'
            . '<div class="uvp">UVP <s>' . chf($uvp) . '</s> <span class="rabatt">−' . $r . ' %</span></div>'
            . '<div class="ihr-preis">' . chf($preis) . ' ' . $einheit . '</div>'
            . '</div>';
    }
    return '<div class="preis"><div class="uvp">UVP</div><div class="ihr-preis">' . chf($preis) . ' ' . $einheit . '</div></div>';
}

/** Zeile «Ihre Ersparnis gegenüber UVP» für Summenblöcke. */
function ersparnis_zeile(array $summen, int $spalten, string $label = 'Ihre Ersparnis gegenüber UVP'): string
{
    if ($summen['ersparnis'] < 0.005) {
        return '';
    }
    return '<tr class="ersparnis"><td colspan="' . $spalten . '">' . e($label) . '</td><td class="num">' . chf($summen['ersparnis']) . '</td></tr>';
}

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
        $pdo->exec(file_get_contents(__DIR__ . '/seed.sql'));
    }
    return $pdo;
}

/** Ergänzt eine Spalte in bestehenden Datenbanken (einfache Migration). */
function spalte_sicherstellen(PDO $pdo, string $tabelle, string $spalte, string $definition): void
{
    $spalten = $pdo->query("PRAGMA table_info($tabelle)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array($spalte, $spalten, true)) {
        $pdo->exec("ALTER TABLE $tabelle ADD COLUMN $spalte $definition");
    }
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
    $mwst = round($netto * MWST_SATZ / 0.05) * 0.05; // auf 5 Rappen gerundet
    return ['netto' => $netto, 'mwst' => $mwst, 'brutto' => $netto + $mwst];
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

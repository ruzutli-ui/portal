<?php
// Gemeinsame Grundlage für den Admin-Bereich.
require __DIR__ . '/../db.php';

const ADMIN_STANDARD_PASSWORT = 'alpvolt-admin';

function admin_passwort(): string
{
    return getenv('ALPVOLT_ADMIN_PASSWORT') ?: ADMIN_STANDARD_PASSWORT;
}

function admin_erforderlich(): void
{
    if (empty($_SESSION['admin'])) {
        redirect('login.php');
    }
}

function status_badge(string $status): string
{
    return '<span class="status status-' . e($status) . '">' . e(STATUS[$status] ?? $status) . '</span>';
}

function datum(?string $wert, bool $zeit = false): string
{
    return $wert ? date($zeit ? 'd.m.Y H:i' : 'd.m.Y', strtotime($wert)) : '–';
}

function kunde_name(array $k): string
{
    $name = trim(($k['vorname'] ?? '') . ' ' . ($k['name'] ?? ''));
    return $name !== '' ? $name : ($k['email'] ?? '');
}

function admin_kopf(string $titel, string $aktiv = '', ?string $lead = null): void
{
    $nachricht = flash();
    $offen = empty($_SESSION['admin']) ? 0
        : (int) db()->query("SELECT COUNT(*) FROM offerten WHERE status = 'offen'")->fetchColumn();
    ?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Alpvolt Admin – <?= e($titel) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&family=Open+Sans:wght@400;600&display=swap">
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="../portal.css">
</head>
<body class="admin">
    <nav>
        <div class="nav-inner">
            <a href="index.php" class="admin-marke">
                <img src="../img/alpvolt-logo.png" alt="Alpvolt" class="logo" width="198" height="36">
                <span class="admin-tag">Admin</span>
            </a>
            <?php if (!empty($_SESSION['admin'])): ?>
                <div class="nav-links">
                    <a href="index.php" class="<?= $aktiv === 'start' ? 'aktiv' : '' ?>">Übersicht</a>
                    <a href="offerten.php" class="<?= $aktiv === 'offerten' ? 'aktiv' : '' ?>">
                        Offerten<?php if ($offen): ?> <span class="badge"><?= $offen ?></span><?php endif; ?>
                    </a>
                    <a href="kunden.php" class="<?= $aktiv === 'kunden' ? 'aktiv' : '' ?>">Kunden</a>
                    <a href="produkte.php" class="<?= $aktiv === 'produkte' ? 'aktiv' : '' ?>">Produkte</a>
                    <a href="logout.php" class="nav-logout">Abmelden</a>
                </div>
            <?php endif; ?>
        </div>
    </nav>

    <header class="hero">
        <div class="hero-inner">
            <div class="eyebrow">Alpvolt intern</div>
            <h1><?= e($titel) ?></h1>
            <?php if ($lead): ?><p><?= e($lead) ?></p><?php endif; ?>
        </div>
    </header>

    <main>
        <?php if ($nachricht): ?>
            <div class="flash"><?= e($nachricht) ?></div>
        <?php endif; ?>
<?php
}

function admin_fuss(): void
{
    ?>
    </main>
    <footer>© <?= date('Y') ?> Alpvolt Schweiz AG · Admin Kundenportal (Prototyp)</footer>
</body>
</html>
<?php
}

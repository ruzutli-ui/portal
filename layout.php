<?php
// Kopf- und Fussbereich für alle Portalseiten.
function kopf(string $titel, string $aktiv = '', ?string $eyebrow = null, ?string $lead = null): void
{
    $k = kunde();
    $anzahl = korb_anzahl();
    $nachricht = flash();
    ?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Alpvolt Kundenportal – <?= e($titel) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&family=Open+Sans:wght@400;600&display=swap">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="portal.css">
</head>
<body>
    <nav>
        <div class="nav-inner">
            <a href="index.php"><img src="img/alpvolt-logo.png" alt="Alpvolt" class="logo" width="198" height="36"></a>
            <?php if ($k): ?>
                <div class="nav-links">
                    <a href="index.php" class="<?= $aktiv === 'shop' ? 'aktiv' : '' ?>">Shop</a>
                    <a href="offerten.php" class="<?= $aktiv === 'offerten' ? 'aktiv' : '' ?>">Meine Offerten</a>
                    <a href="korb.php" class="korb-link <?= $aktiv === 'korb' ? 'aktiv' : '' ?>">
                        Offert-Korb<?php if ($anzahl): ?> <span class="badge"><?= $anzahl ?></span><?php endif; ?>
                    </a>
                    <a href="profil.php" class="nav-user <?= $aktiv === 'profil' ? 'aktiv' : '' ?>" title="<?= e($k['email']) ?>">
                        <?= e(trim($k['vorname'] . ' ' . $k['name']) ?: $k['email']) ?>
                    </a>
                    <a href="logout.php" class="nav-logout">Abmelden</a>
                </div>
            <?php endif; ?>
        </div>
    </nav>

    <header class="hero">
        <div class="hero-inner">
            <div class="eyebrow"><?= e($eyebrow ?? 'Kundenportal') ?></div>
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

function fuss(): void
{
    ?>
    </main>
    <footer>© <?= date('Y') ?> Alpvolt Schweiz AG · Kundenportal (Prototyp)</footer>
</body>
</html>
<?php
}


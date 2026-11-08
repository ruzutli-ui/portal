<?php
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

$k = login_erforderlich();
$adresseKomplett = $k['vorname'] !== '' && $k['name'] !== '' && $k['strasse'] !== '' && $k['plz'] !== '' && $k['ort'] !== '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_pruefen();
    $aktion = $_POST['aktion'] ?? '';

    if ($aktion === 'aktualisieren') {
        foreach ((array) ($_POST['menge'] ?? []) as $id => $menge) {
            if (isset(korb()[(int) $id])) {
                korb_setzen((int) $id, (int) $menge);
            }
        }
        redirect('korb.php');
    }

    if ($aktion === 'entfernen') {
        korb_setzen((int) ($_POST['produkt_id'] ?? 0), 0);
        redirect('korb.php');
    }

    if ($aktion === 'leeren') {
        $_SESSION['korb'] = [];
        redirect('korb.php');
    }

    if ($aktion === 'offerte' && korb() && $adresseKomplett) {
        $pdo = db();
        $pdo->beginTransaction();
        $jahr = date('Y');
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM offerten WHERE nummer LIKE ?");
        $stmt->execute(["AV-$jahr-%"]);
        $nummer = sprintf('AV-%s-%04d', $jahr, $stmt->fetchColumn() + 1);

        $pdo->prepare('INSERT INTO offerten (nummer, kunde_id, titel, bemerkung) VALUES (?, ?, ?, ?)')
            ->execute([$nummer, $k['id'], trim($_POST['titel'] ?? ''), trim($_POST['bemerkung'] ?? '')]);
        $offerteId = (int) $pdo->lastInsertId();

        // Preise und Bezeichnungen werden eingefroren, damit spätere Preisänderungen die Offerte nicht verändern.
        $ins = $pdo->prepare('INSERT INTO offert_positionen (offerte_id, artikelnr, bezeichnung, einheit, preis, menge) VALUES (?, ?, ?, ?, ?, ?)');
        foreach (korb_positionen() as $p) {
            $ins->execute([$offerteId, $p['artikelnr'], $p['bezeichnung'], $p['einheit'], $p['preis'], $p['menge']]);
        }
        $pdo->commit();

        $_SESSION['korb'] = [];
        flash("Offerte $nummer wurde erstellt.");
        redirect('offerte.php?id=' . $offerteId);
    }
    redirect('korb.php');
}

$positionen = korb_positionen();
$s = summen($positionen);

kopf('Offert-Korb', 'korb', 'Kundenportal', 'Mengen prüfen und daraus eine verbindliche Offerte erstellen.');
?>
        <?php if (!$positionen): ?>
            <section class="card leer">
                <h2>Ihr Offert-Korb ist leer</h2>
                <p>Wählen Sie im Shop die gewünschten Produkte und Dienstleistungen aus.</p>
                <a href="index.php" class="btn-sun">Zum Shop</a>
            </section>
        <?php else: ?>
            <section class="card">
                <h2>Positionen</h2>
                <form method="post" id="korb-form">
                    <?= csrf_feld() ?>
                    <input type="hidden" name="aktion" value="aktualisieren">
                </form>
                <div class="tabelle-wrap">
                    <table class="positionen">
                        <thead>
                            <tr><th>Art.-Nr.</th><th>Bezeichnung</th><th class="num">Preis</th><th class="num">Menge</th><th class="num">Total</th><th></th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($positionen as $p): ?>
                                <tr>
                                    <td class="id"><?= e($p['artikelnr']) ?></td>
                                    <td><?= e($p['bezeichnung']) ?></td>
                                    <td class="num"><?= chf($p['preis']) ?></td>
                                    <td class="num">
                                        <input form="korb-form" type="number" name="menge[<?= (int) $p['id'] ?>]" value="<?= (int) $p['menge'] ?>" min="0" max="9999" class="menge" aria-label="Menge">
                                        <span class="einheit"><?= e($p['einheit']) ?></span>
                                    </td>
                                    <td class="num"><?= chf($p['total']) ?></td>
                                    <td class="aktionen">
                                        <form method="post">
                                            <?= csrf_feld() ?>
                                            <input type="hidden" name="aktion" value="entfernen">
                                            <input type="hidden" name="produkt_id" value="<?= (int) $p['id'] ?>">
                                            <button type="submit" class="btn-small btn-danger" title="Entfernen">✕</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr><td colspan="4">Zwischensumme exkl. MwSt.</td><td class="num"><?= chf($s['netto']) ?></td><td></td></tr>
                            <tr><td colspan="4">MwSt. <?= number_format(MWST_SATZ * 100, 1) ?> %</td><td class="num"><?= chf($s['mwst']) ?></td><td></td></tr>
                            <tr class="total"><td colspan="4">Total inkl. MwSt.</td><td class="num"><?= chf($s['brutto']) ?></td><td></td></tr>
                        </tfoot>
                    </table>
                </div>
                <div class="knopfleiste">
                    <button type="submit" form="korb-form" class="btn-small btn-ghost">Mengen aktualisieren</button>
                    <form method="post" onsubmit="return confirm('Offert-Korb wirklich leeren?');">
                        <?= csrf_feld() ?>
                        <input type="hidden" name="aktion" value="leeren">
                        <button type="submit" class="btn-small btn-danger">Korb leeren</button>
                    </form>
                    <a href="index.php" class="btn-small btn-ghost">Weitere Produkte</a>
                </div>
            </section>

            <section class="card">
                <h2>Offerte erstellen</h2>
                <?php if (!$adresseKomplett): ?>
                    <p class="fehler">Bitte zuerst Ihre <a href="profil.php">Kontaktdaten ergänzen</a> – sie werden auf der Offerte aufgeführt.</p>
                <?php else: ?>
                    <p class="adresse-klein">
                        Offerte an: <?= e($k['firma'] ? $k['firma'] . ', ' : '') ?><?= e($k['vorname'] . ' ' . $k['name']) ?>,
                        <?= e($k['strasse']) ?>, <?= e($k['plz'] . ' ' . $k['ort']) ?>
                        · <a href="profil.php">ändern</a>
                    </p>
                <?php endif; ?>
                <form method="post" class="form-stack">
                    <?= csrf_feld() ?>
                    <input type="hidden" name="aktion" value="offerte">
                    <label>Projektbezeichnung (optional)
                        <input name="titel" placeholder="z. B. PV-Anlage Einfamilienhaus Musterstrasse">
                    </label>
                    <label>Bemerkung (optional)
                        <textarea name="bemerkung" rows="3" placeholder="Wünsche, Dachart, gewünschter Termin …"></textarea>
                    </label>
                    <button type="submit" class="btn-sun" <?= $adresseKomplett ? '' : 'disabled' ?>>Offerte erstellen</button>
                </form>
            </section>
        <?php endif; ?>
<?php fuss();

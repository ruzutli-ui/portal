<?php
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

$k = login_erforderlich();
$felder = ['firma', 'vorname', 'name', 'strasse', 'plz', 'ort'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_pruefen();
    $werte = [];
    foreach ($felder as $f) {
        $werte[] = trim($_POST[$f] ?? '');
    }
    $werte[] = $k['id'];
    db()->prepare('UPDATE kunden SET firma = ?, vorname = ?, name = ?, strasse = ?, plz = ?, ort = ? WHERE id = ?')
        ->execute($werte);
    flash('Kontaktdaten gespeichert.');
    redirect(korb_anzahl() ? 'korb.php' : 'index.php');
}

kopf('Kontaktdaten', 'profil', 'Mein Konto', $k['email']);
?>
        <section class="card">
            <h2>Rechnungs- und Offertadresse</h2>
            <form method="post" class="form-grid">
                <?= csrf_feld() ?>
                <label class="span-2">Firma (optional)
                    <input name="firma" value="<?= e($k['firma']) ?>">
                </label>
                <label>Vorname
                    <input name="vorname" value="<?= e($k['vorname']) ?>" required>
                </label>
                <label>Name
                    <input name="name" value="<?= e($k['name']) ?>" required>
                </label>
                <label class="span-2">Strasse, Nr.
                    <input name="strasse" value="<?= e($k['strasse']) ?>" required>
                </label>
                <label>PLZ
                    <input name="plz" value="<?= e($k['plz']) ?>" inputmode="numeric" required>
                </label>
                <label>Ort
                    <input name="ort" value="<?= e($k['ort']) ?>" required>
                </label>
                <div class="span-2"><button type="submit" class="btn-sun">Speichern</button></div>
            </form>
        </section>
<?php fuss();

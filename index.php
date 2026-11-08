<?php
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

login_erforderlich();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_pruefen();
    $id = (int) ($_POST['produkt_id'] ?? 0);
    $menge = max(1, (int) ($_POST['menge'] ?? 1));
    $stmt = db()->prepare('SELECT bezeichnung FROM produkte WHERE id = ? AND aktiv = 1');
    $stmt->execute([$id]);
    if ($bez = $stmt->fetchColumn()) {
        korb_setzen($id, (korb()[$id] ?? 0) + $menge);
        flash("$menge × $bez zum Offert-Korb hinzugefügt.");
    }
    $kat = $_POST['kategorie'] ?? '';
    redirect('index.php' . ($kat !== '' ? '?kategorie=' . urlencode($kat) : '') . '#p' . $id);
}

$pdo = db();
$kategorien = $pdo->query('SELECT DISTINCT kategorie FROM produkte WHERE aktiv = 1 ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
$aktiv = $_GET['kategorie'] ?? '';
if (!in_array($aktiv, $kategorien, true)) {
    $aktiv = '';
}
if ($aktiv !== '') {
    $stmt = $pdo->prepare('SELECT * FROM produkte WHERE kategorie = ? AND aktiv = 1 ORDER BY id');
    $stmt->execute([$aktiv]);
} else {
    $stmt = $pdo->query('SELECT * FROM produkte WHERE aktiv = 1 ORDER BY id');
}
$produkte = $stmt->fetchAll();
$korb = korb();

kopf('Shop', 'shop', 'Kundenportal', 'Produkte und Dienstleistungen auswählen, im Offert-Korb sammeln und daraus Ihre eigene Offerte erstellen.');
?>
        <section class="card">
            <div class="filter">
                <a href="index.php" class="chip <?= $aktiv === '' ? 'aktiv' : '' ?>">Alle</a>
                <?php foreach ($kategorien as $kat): ?>
                    <a href="index.php?kategorie=<?= urlencode($kat) ?>" class="chip <?= $aktiv === $kat ? 'aktiv' : '' ?>"><?= e($kat) ?></a>
                <?php endforeach; ?>
            </div>

            <div class="produkte">
                <?php foreach ($produkte as $p): ?>
                    <article class="produkt" id="p<?= (int) $p['id'] ?>">
                        <div class="produkt-kopf">
                            <span class="kategorie"><?= e($p['kategorie']) ?></span>
                            <span class="artikelnr"><?= e($p['artikelnr']) ?></span>
                        </div>
                        <h3><?= e($p['bezeichnung']) ?></h3>
                        <p><?= e($p['beschreibung']) ?></p>
                        <div class="produkt-fuss">
                            <?= preis_block($p) ?>
                            <form method="post" class="in-korb">
                                <?= csrf_feld() ?>
                                <input type="hidden" name="produkt_id" value="<?= (int) $p['id'] ?>">
                                <input type="hidden" name="kategorie" value="<?= e($aktiv) ?>">
                                <input type="number" name="menge" value="1" min="1" max="9999" aria-label="Menge">
                                <button type="submit" class="btn-small btn-primary">Hinzufügen</button>
                            </form>
                        </div>
                        <?php if (!empty($korb[$p['id']])): ?>
                            <div class="im-korb"><?= (int) $korb[$p['id']] ?> im Offert-Korb</div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <?php if (korb_anzahl()): ?>
            <div class="sticky-korb">
                <span><?= korb_anzahl() ?> Artikel · <?= chf(summen(korb_positionen())['netto']) ?> exkl. MwSt.</span>
                <a href="korb.php" class="btn-sun">Zum Offert-Korb</a>
            </div>
        <?php endif; ?>
<?php fuss();

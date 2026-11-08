<?php
require __DIR__ . '/_init.php';
admin_erforderlich();

$pdo = db();
$felder = ['artikelnr', 'kategorie', 'bezeichnung', 'beschreibung', 'einheit', 'uvp', 'preis'];
$fehler = '';
$formular = null; // Produkt, das gerade bearbeitet oder neu erfasst wird

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_pruefen();
    $aktion = $_POST['aktion'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($aktion === 'umschalten' && $id > 0) {
        $pdo->prepare('UPDATE produkte SET aktiv = 1 - aktiv WHERE id = ?')->execute([$id]);
        redirect('produkte.php#p' . $id);
    }

    if ($aktion === 'speichern') {
        $werte = [];
        foreach ($felder as $f) {
            $werte[$f] = trim($_POST[$f] ?? '');
        }
        $werte['einheit'] = $werte['einheit'] ?: 'Stk.';
        $uvp = betrag_lesen($werte['uvp']);
        // Leerer Kundenpreis = UVP (kein Rabatt)
        $preis = $werte['preis'] === '' ? $uvp : betrag_lesen($werte['preis']);

        if ($werte['artikelnr'] === '' || $werte['kategorie'] === '' || $werte['bezeichnung'] === '') {
            $fehler = 'Artikelnummer, Kategorie und Bezeichnung sind Pflichtfelder.';
        } elseif ($uvp === null || $uvp < 0) {
            $fehler = 'Bitte eine gültige UVP eingeben.';
        } elseif ($preis === null || $preis < 0) {
            $fehler = 'Bitte einen gültigen Kundenpreis eingeben.';
        } else {
            $dup = $pdo->prepare('SELECT id FROM produkte WHERE artikelnr = ? AND id <> ?');
            $dup->execute([$werte['artikelnr'], $id]);
            if ($dup->fetchColumn()) {
                $fehler = 'Diese Artikelnummer ist bereits vergeben.';
            }
        }

        if ($fehler === '') {
            $werte['uvp'] = round($uvp, 2);
            $werte['preis'] = round($preis, 2);
            if ($id > 0) {
                $pdo->prepare('UPDATE produkte SET artikelnr = ?, kategorie = ?, bezeichnung = ?, beschreibung = ?, einheit = ?, uvp = ?, preis = ? WHERE id = ?')
                    ->execute([...array_values($werte), $id]);
                flash('Produkt «' . $werte['bezeichnung'] . '» gespeichert. Bestehende Offerten behalten ihre Preise.');
            } else {
                $pdo->prepare('INSERT INTO produkte (artikelnr, kategorie, bezeichnung, beschreibung, einheit, uvp, preis) VALUES (?, ?, ?, ?, ?, ?, ?)')
                    ->execute(array_values($werte));
                $id = (int) $pdo->lastInsertId();
                flash('Produkt «' . $werte['bezeichnung'] . '» erfasst und im Shop sichtbar.');
            }
            redirect('produkte.php#p' . $id);
        }
        $formular = $werte + ['id' => $id];
    }
}

if ($formular === null && isset($_GET['neu'])) {
    $formular = ['id' => 0, 'einheit' => 'Stk.'] + array_fill_keys($felder, '');
} elseif ($formular === null && isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM produkte WHERE id = ?');
    $stmt->execute([(int) $_GET['id']]);
    $formular = $stmt->fetch() ?: null;
}

$produkte = $pdo->query('SELECT * FROM produkte ORDER BY kategorie, bezeichnung')->fetchAll();
$kategorien = array_values(array_unique(array_column($produkte, 'kategorie')));

admin_kopf('Produkte', 'produkte', 'Sortiment und Preise für den Kunden-Shop pflegen.');
?>
        <?php if ($formular !== null): ?>
            <section class="card" id="formular">
                <h2><?= $formular['id'] ? 'Produkt bearbeiten' : 'Neues Produkt' ?></h2>
                <?php if ($fehler): ?><p class="fehler"><?= e($fehler) ?></p><?php endif; ?>
                <form method="post" class="form-grid">
                    <?= csrf_feld() ?>
                    <input type="hidden" name="aktion" value="speichern">
                    <input type="hidden" name="id" value="<?= (int) $formular['id'] ?>">
                    <label>Artikelnummer
                        <input name="artikelnr" value="<?= e($formular['artikelnr']) ?>" required <?= $formular['id'] ? '' : 'autofocus' ?>>
                    </label>
                    <label>Kategorie
                        <input name="kategorie" value="<?= e($formular['kategorie']) ?>" list="kategorien" required>
                        <datalist id="kategorien">
                            <?php foreach ($kategorien as $kat): ?><option value="<?= e($kat) ?>"><?php endforeach; ?>
                        </datalist>
                    </label>
                    <label class="span-2">Bezeichnung
                        <input name="bezeichnung" value="<?= e($formular['bezeichnung']) ?>" required>
                    </label>
                    <label class="span-2">Beschreibung
                        <textarea name="beschreibung" rows="2"><?= e($formular['beschreibung']) ?></textarea>
                    </label>
                    <label>UVP CHF (exkl. MwSt.)
                        <input name="uvp" value="<?= e((string) $formular['uvp']) ?>" inputmode="decimal" placeholder="0.00" required>
                    </label>
                    <label>Kundenpreis CHF (exkl. MwSt.)
                        <input name="preis" value="<?= e((string) $formular['preis']) ?>" inputmode="decimal" placeholder="leer = UVP">
                    </label>
                    <?php if (($formular['quelle'] ?? '') === 'salesmachine'): ?>
                        <p class="hinweis span-2">Aus der Sales Machine importiert (<?= datum($formular['importiert'], true) ?>). Der nächste Import überschreibt Bezeichnung, UVP und Kundenpreis.</p>
                    <?php endif; ?>
                    <label>Einheit
                        <input name="einheit" value="<?= e($formular['einheit']) ?>" list="einheiten" placeholder="Stk.">
                        <datalist id="einheiten">
                            <option value="Stk."><option value="Set"><option value="Pauschal"><option value="Jahr"><option value="m"><option value="h">
                        </datalist>
                    </label>
                    <div class="span-2 knopfleiste">
                        <button type="submit" class="btn-sun">Speichern</button>
                        <a href="produkte.php" class="btn-small btn-ghost">Abbrechen</a>
                    </div>
                </form>
            </section>
        <?php endif; ?>

        <section class="card">
            <div class="werkzeugleiste">
                <h2>Sortiment <span class="count"><?= count($produkte) ?></span></h2>
                <div class="knopfleiste ohne-abstand">
                    <a href="import.php" class="btn-small btn-ghost">Import aus Sales Machine</a>
                    <a href="produkte.php?neu=1#formular" class="btn-sun">+ Neues Produkt</a>
                </div>
            </div>
            <div class="tabelle-wrap">
                <table>
                    <thead>
                        <tr><th>Art.-Nr.</th><th>Bezeichnung</th><th>Kategorie</th><th class="num">UVP</th><th class="num">Kundenpreis</th><th>Im Shop</th><th class="aktionen">Aktionen</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($produkte as $p): ?>
                            <tr id="p<?= (int) $p['id'] ?>" class="<?= $p['aktiv'] ? '' : 'inaktiv' ?>">
                                <td class="id"><?= e($p['artikelnr']) ?></td>
                                <td>
                                    <?= e($p['bezeichnung']) ?>
                                    <?php if ($p['quelle'] === 'salesmachine'): ?><span class="quelle" title="Importiert <?= e(datum($p['importiert'], true)) ?>">Sales Machine</span><?php endif; ?>
                                    <br><small class="gedimmt"><?= e($p['beschreibung']) ?></small>
                                </td>
                                <td><?= e($p['kategorie']) ?></td>
                                <td class="num"><?= chf((float) $p['uvp']) ?> <small class="gedimmt">/ <?= e($p['einheit']) ?></small></td>
                                <td class="num">
                                    <?= chf((float) $p['preis']) ?>
                                    <?php if ($r = rabatt_prozent((float) $p['uvp'], (float) $p['preis'])): ?><br><small class="rabatt-text">−<?= $r ?> %</small><?php endif; ?>
                                </td>
                                <td><?= $p['aktiv'] ? '<span class="status status-angenommen">Sichtbar</span>' : '<span class="status status-offen">Ausgeblendet</span>' ?></td>
                                <td class="aktionen">
                                    <a href="produkte.php?id=<?= (int) $p['id'] ?>#formular" class="btn-small btn-ghost">Bearbeiten</a>
                                    <form method="post">
                                        <?= csrf_feld() ?>
                                        <input type="hidden" name="aktion" value="umschalten">
                                        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                        <button type="submit" class="btn-small btn-ghost"><?= $p['aktiv'] ? 'Ausblenden' : 'Einblenden' ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
<?php admin_fuss();

<?php
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

$k = login_erforderlich();
$pdo = db();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM offerten WHERE id = ? AND kunde_id = ?');
$stmt->execute([$id, $k['id']]);
$o = $stmt->fetch();
if (!$o) {
    http_response_code(404);
    kopf('Offerte nicht gefunden', 'offerten');
    echo '<section class="card"><p>Diese Offerte existiert nicht. <a href="offerten.php">Zur Übersicht</a></p></section>';
    fuss();
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM offert_positionen WHERE offerte_id = ? ORDER BY id');
$stmt->execute([$id]);
$positionen = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_pruefen();
    $aktion = $_POST['aktion'] ?? '';
    if (in_array($aktion, ['angenommen', 'abgelehnt'], true) && $o['status'] === 'offen') {
        $pdo->prepare("UPDATE offerten SET status = ?, geaendert = datetime('now', 'localtime') WHERE id = ?")->execute([$aktion, $id]);
        flash($aktion === 'angenommen'
            ? 'Vielen Dank! Ihre Bestellung ist bei uns eingegangen – wir melden uns in Kürze.'
            : 'Die Offerte wurde als abgelehnt markiert.');
    } elseif ($aktion === 'kopieren') {
        // Positionen über die Artikelnummer zurück in den Korb laden (zu aktuellen Preisen).
        $such = $pdo->prepare('SELECT id FROM produkte WHERE artikelnr = ? AND aktiv = 1');
        foreach ($positionen as $p) {
            $such->execute([$p['artikelnr']]);
            if ($pid = (int) $such->fetchColumn()) {
                korb_setzen($pid, (korb()[$pid] ?? 0) + (int) $p['menge']);
            }
        }
        flash('Positionen in den Offert-Korb übernommen. Preise wurden aktualisiert.');
        redirect('korb.php');
    }
    redirect('offerte.php?id=' . $id);
}

$s = summen($positionen);
$erstellt = strtotime($o['erstellt']);
$gueltigBis = strtotime('+30 days', $erstellt);

kopf('Offerte ' . $o['nummer'], 'offerten', 'Offerte', $o['titel'] ?: null);
?>
        <section class="card offerte">
            <div class="offerte-kopf">
                <div>
                    <img src="img/alpvolt-logo.png" alt="Alpvolt" class="logo nur-druck" width="198" height="36">
                    <div class="adresse">
                        <?php if ($k['firma']): ?><?= e($k['firma']) ?><br><?php endif; ?>
                        <?= e($k['vorname'] . ' ' . $k['name']) ?><br>
                        <?= e($k['strasse']) ?><br>
                        <?= e($k['plz'] . ' ' . $k['ort']) ?>
                    </div>
                </div>
                <dl class="meta">
                    <dt>Offerte</dt><dd><?= e($o['nummer']) ?></dd>
                    <dt>Datum</dt><dd><?= date('d.m.Y', $erstellt) ?></dd>
                    <dt>Gültig bis</dt><dd><?= date('d.m.Y', $gueltigBis) ?></dd>
                    <dt>Status</dt><dd><span class="status status-<?= e($o['status']) ?>"><?= e(ucfirst($o['status'])) ?></span></dd>
                </dl>
            </div>

            <h2 class="nur-druck">Offerte <?= e($o['nummer']) ?><?= $o['titel'] ? ' – ' . e($o['titel']) : '' ?></h2>

            <div class="tabelle-wrap">
                <table class="positionen">
                    <thead>
                        <tr><th>Pos.</th><th>Art.-Nr.</th><th>Bezeichnung</th><th class="num">Menge</th><th class="num">UVP</th><th class="num">Ihr Preis</th><th class="num">Total</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($positionen as $i => $p): ?>
                            <tr>
                                <td class="id"><?= $i + 1 ?></td>
                                <td class="id"><?= e($p['artikelnr']) ?></td>
                                <td><?= e($p['bezeichnung']) ?></td>
                                <td class="num"><?= (int) $p['menge'] ?> <?= e($p['einheit']) ?></td>
                                <td class="num gedimmt"><?= chf((float) ($p['uvp'] ?? $p['preis'])) ?></td>
                                <td class="num"><?= chf($p['preis']) ?></td>
                                <td class="num"><?= chf($p['preis'] * $p['menge']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr><td colspan="6">Zwischensumme exkl. MwSt.</td><td class="num"><?= chf($s['netto']) ?></td></tr>
                        <tr><td colspan="6">MwSt. <?= number_format(MWST_SATZ * 100, 1) ?> %</td><td class="num"><?= chf($s['mwst']) ?></td></tr>
                        <tr class="total"><td colspan="6">Total inkl. MwSt.</td><td class="num"><?= chf($s['brutto']) ?></td></tr>
                        <?= ersparnis_zeile($s, 6) ?>
                    </tfoot>
                </table>
            </div>

            <?php if ($o['bemerkung']): ?>
                <div class="bemerkung"><strong>Bemerkung:</strong> <?= nl2br(e($o['bemerkung'])) ?></div>
            <?php endif; ?>

            <p class="kleingedrucktes">
                Diese Offerte wurde im Alpvolt Kundenportal erstellt und ist bis <?= date('d.m.Y', $gueltigBis) ?> gültig.
                Die definitive Auslegung erfolgt nach einer Vor-Ort-Besichtigung. Es gelten die AGB der Alpvolt Schweiz AG.
            </p>

            <div class="knopfleiste nicht-drucken">
                <?php if ($o['status'] === 'offen'): ?>
                    <form method="post" onsubmit="return confirm('Offerte verbindlich annehmen und bestellen?');">
                        <?= csrf_feld() ?>
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <input type="hidden" name="aktion" value="angenommen">
                        <button type="submit" class="btn-sun">Offerte annehmen</button>
                    </form>
                    <form method="post">
                        <?= csrf_feld() ?>
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <input type="hidden" name="aktion" value="abgelehnt">
                        <button type="submit" class="btn-small btn-danger">Ablehnen</button>
                    </form>
                <?php endif; ?>
                <button type="button" class="btn-small btn-ghost" onclick="window.print()">Drucken / PDF</button>
                <form method="post">
                    <?= csrf_feld() ?>
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="hidden" name="aktion" value="kopieren">
                    <button type="submit" class="btn-small btn-ghost">Als Vorlage verwenden</button>
                </form>
                <a href="offerten.php" class="btn-small btn-ghost">Zur Übersicht</a>
            </div>
        </section>
<?php fuss();

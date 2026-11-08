<?php
require __DIR__ . '/_init.php';
admin_erforderlich();

$pdo = db();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

$stmt = $pdo->prepare('SELECT o.*, k.id AS kid, k.email, k.firma, k.vorname, k.name, k.strasse, k.plz, k.ort
                       FROM offerten o JOIN kunden k ON k.id = o.kunde_id WHERE o.id = ?');
$stmt->execute([$id]);
$o = $stmt->fetch();
if (!$o) {
    http_response_code(404);
    admin_kopf('Offerte nicht gefunden', 'offerten');
    echo '<section class="card"><p>Diese Offerte existiert nicht. <a href="offerten.php">Zur Übersicht</a></p></section>';
    admin_fuss();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_pruefen();
    $neuerStatus = $_POST['status'] ?? $o['status'];
    if (!isset(STATUS[$neuerStatus])) {
        $neuerStatus = $o['status'];
    }
    $pdo->prepare("UPDATE offerten SET status = ?, interne_notiz = ?, geaendert = datetime('now', 'localtime') WHERE id = ?")
        ->execute([$neuerStatus, trim($_POST['interne_notiz'] ?? ''), $id]);
    flash('Offerte ' . $o['nummer'] . ' gespeichert.');
    redirect('offerte.php?id=' . $id);
}

$stmt = $pdo->prepare('SELECT * FROM offert_positionen WHERE offerte_id = ? ORDER BY id');
$stmt->execute([$id]);
$positionen = $stmt->fetchAll();
$s = summen($positionen);
$gueltigBis = strtotime('+30 days', strtotime($o['erstellt']));
$abgelaufen = $o['status'] === 'offen' && $gueltigBis < time();

admin_kopf('Offerte ' . $o['nummer'], 'offerten', $o['titel'] ?: null);
?>
        <section class="card">
                <h2>Positionen</h2>
                <div class="tabelle-wrap">
                    <table class="positionen">
                        <thead>
                            <tr><th>Pos.</th><th>Art.-Nr.</th><th>Bezeichnung</th><th class="num">Menge</th><th class="num">UVP</th><th class="num">Kundenpreis</th><th class="num">Total</th></tr>
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
                        <?= ersparnis_zeile($s, 6, 'Rabatt gegenüber UVP') ?>
                        </tfoot>
                    </table>
                </div>
                <?php if ($o['bemerkung']): ?>
                    <div class="bemerkung"><strong>Bemerkung des Kunden:</strong><br><?= nl2br(e($o['bemerkung'])) ?></div>
                <?php endif; ?>
            </section>

            <div class="zweispaltig">
                <section class="card">
                    <h2>Kunde</h2>
                    <div class="adresse">
                        <?php if ($o['firma']): ?><strong><?= e($o['firma']) ?></strong><br><?php endif; ?>
                        <?= e(kunde_name($o)) ?><br>
                        <?= e($o['strasse']) ?><br>
                        <?= e(trim($o['plz'] . ' ' . $o['ort'])) ?>
                    </div>
                    <p class="kontakt"><a href="mailto:<?= e($o['email']) ?>?subject=<?= rawurlencode('Ihre Offerte ' . $o['nummer']) ?>"><?= e($o['email']) ?></a></p>
                    <a href="kunde.php?id=<?= (int) $o['kid'] ?>" class="btn-small btn-ghost kein-rand-links">Alle Offerten des Kunden</a>
                </section>

                <section class="card">
                    <h2>Bearbeitung</h2>
                    <dl class="meta">
                        <dt>Erstellt</dt><dd><?= datum($o['erstellt'], true) ?></dd>
                        <dt>Gültig bis</dt><dd><?= date('d.m.Y', $gueltigBis) ?><?php if ($abgelaufen): ?> <span class="status status-abgelehnt">abgelaufen</span><?php endif; ?></dd>
                        <dt>Geändert</dt><dd><?= datum($o['geaendert'], true) ?></dd>
                        <dt>Status</dt><dd><?= status_badge($o['status']) ?></dd>
                    </dl>
                    <form method="post" class="form-stack abstand-oben">
                        <?= csrf_feld() ?>
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <label>Status
                            <select name="status">
                                <?php foreach (STATUS as $key => $label): ?>
                                    <option value="<?= e($key) ?>" <?= $o['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Interne Notiz <small class="gedimmt">(für Kunden nicht sichtbar)</small>
                            <textarea name="interne_notiz" rows="4" placeholder="z. B. Besichtigung am … vereinbart"><?= e($o['interne_notiz']) ?></textarea>
                        </label>
                        <button type="submit" class="btn-sun">Speichern</button>
                    </form>
                </section>
            </div>
        </div>
<?php admin_fuss();

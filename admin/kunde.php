<?php
require __DIR__ . '/_init.php';
admin_erforderlich();

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM kunden WHERE id = ?');
$stmt->execute([$id]);
$k = $stmt->fetch();
if (!$k) {
    http_response_code(404);
    admin_kopf('Kunde nicht gefunden', 'kunden');
    echo '<section class="card"><p>Dieser Kunde existiert nicht. <a href="kunden.php">Zur Übersicht</a></p></section>';
    admin_fuss();
    exit;
}

$stmt = $pdo->prepare(
    'SELECT o.*, COALESCE(SUM(p.preis * p.menge), 0) AS netto, COUNT(p.id) AS anzahl
     FROM offerten o LEFT JOIN offert_positionen p ON p.offerte_id = o.id
     WHERE o.kunde_id = ? GROUP BY o.id ORDER BY o.id DESC'
);
$stmt->execute([$id]);
$offerten = $stmt->fetchAll();

admin_kopf(kunde_name($k), 'kunden', $k['firma'] ?: null);
?>
        <div class="zweispaltig breit-rechts">
            <section class="card">
                <h2>Kontakt</h2>
                <div class="adresse">
                    <?php if ($k['firma']): ?><strong><?= e($k['firma']) ?></strong><br><?php endif; ?>
                    <?= e(trim($k['vorname'] . ' ' . $k['name']) ?: '–') ?><br>
                    <?= e($k['strasse'] ?: '') ?><br>
                    <?= e(trim($k['plz'] . ' ' . $k['ort'])) ?>
                </div>
                <p class="kontakt"><a href="mailto:<?= e($k['email']) ?>"><?= e($k['email']) ?></a></p>
            </section>

            <section class="card">
                <h2>Offerten <span class="count"><?= count($offerten) ?></span></h2>
                <div class="tabelle-wrap">
                    <table>
                        <thead>
                            <tr><th>Nummer</th><th>Datum</th><th>Projekt</th><th class="num">Total exkl. MwSt.</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            <?php if (!$offerten): ?>
                                <tr><td colspan="5" class="empty">Noch keine Offerten.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($offerten as $o): ?>
                                <tr class="klickbar" onclick="location.href='offerte.php?id=<?= (int) $o['id'] ?>'">
                                    <td><a href="offerte.php?id=<?= (int) $o['id'] ?>"><strong><?= e($o['nummer']) ?></strong></a></td>
                                    <td><?= datum($o['erstellt']) ?></td>
                                    <td><?= e($o['titel'] ?: '–') ?></td>
                                    <td class="num"><?= chf((float) $o['netto']) ?></td>
                                    <td><?= status_badge($o['status']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
<?php admin_fuss();

<?php
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

$k = login_erforderlich();

$stmt = db()->prepare(
    'SELECT o.*, COALESCE(SUM(p.preis * p.menge), 0) AS netto, COUNT(p.id) AS anzahl
     FROM offerten o LEFT JOIN offert_positionen p ON p.offerte_id = o.id
     WHERE o.kunde_id = ? GROUP BY o.id ORDER BY o.id DESC'
);
$stmt->execute([$k['id']]);
$offerten = $stmt->fetchAll();

kopf('Meine Offerten', 'offerten', 'Kundenportal', 'Alle Offerten, die Sie im Portal erstellt haben.');
?>
        <section class="card">
            <h2>Offerten <span class="count"><?= count($offerten) ?></span></h2>
            <div class="tabelle-wrap">
                <table>
                    <thead>
                        <tr><th>Nummer</th><th>Datum</th><th>Projekt</th><th class="num">Positionen</th><th class="num">Total exkl. MwSt.</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php if (!$offerten): ?>
                            <tr><td colspan="6" class="empty">Noch keine Offerten. <a href="index.php">Jetzt im Shop starten</a>.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($offerten as $o): ?>
                            <tr class="klickbar" onclick="location.href='offerte.php?id=<?= (int) $o['id'] ?>'">
                                <td><a href="offerte.php?id=<?= (int) $o['id'] ?>"><strong><?= e($o['nummer']) ?></strong></a></td>
                                <td><?= e(date('d.m.Y', strtotime($o['erstellt']))) ?></td>
                                <td><?= e($o['titel'] ?: '–') ?></td>
                                <td class="num"><?= (int) $o['anzahl'] ?></td>
                                <td class="num"><?= chf((float) $o['netto']) ?></td>
                                <td><span class="status status-<?= e($o['status']) ?>"><?= e(ucfirst($o['status'])) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
<?php fuss();

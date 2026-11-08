<?php
require __DIR__ . '/_init.php';
admin_erforderlich();

$status = $_GET['status'] ?? '';
if (!isset(STATUS[$status])) {
    $status = '';
}
$suche = trim($_GET['q'] ?? '');

$where = [];
$params = [];
if ($status !== '') {
    $where[] = 'o.status = ?';
    $params[] = $status;
}
if ($suche !== '') {
    $where[] = '(o.nummer LIKE ? OR o.titel LIKE ? OR k.email LIKE ? OR k.firma LIKE ? OR k.vorname LIKE ? OR k.name LIKE ? OR k.ort LIKE ?)';
    array_push($params, ...array_fill(0, 7, "%$suche%"));
}
$stmt = db()->prepare(
    'SELECT o.*, k.vorname, k.name, k.firma, k.email, k.ort, COALESCE(SUM(p.preis * p.menge), 0) AS netto
     FROM offerten o JOIN kunden k ON k.id = o.kunde_id LEFT JOIN offert_positionen p ON p.offerte_id = o.id'
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
    . ' GROUP BY o.id ORDER BY o.id DESC'
);
$stmt->execute($params);
$offerten = $stmt->fetchAll();

$anzahl = db()->query('SELECT status, COUNT(*) FROM offerten GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);

function filter_link(string $s, string $suche): string
{
    $q = array_filter(['status' => $s, 'q' => $suche]);
    return 'offerten.php' . ($q ? '?' . http_build_query($q) : '');
}

admin_kopf('Offerten', 'offerten', 'Alle von Kunden im Portal erstellten Offerten.');
?>
        <section class="card">
            <div class="werkzeugleiste">
                <div class="filter">
                    <a href="<?= e(filter_link('', $suche)) ?>" class="chip <?= $status === '' ? 'aktiv' : '' ?>">Alle <?= array_sum($anzahl) ?></a>
                    <?php foreach (STATUS as $key => $label): ?>
                        <a href="<?= e(filter_link($key, $suche)) ?>" class="chip <?= $status === $key ? 'aktiv' : '' ?>"><?= e($label) ?> <?= (int) ($anzahl[$key] ?? 0) ?></a>
                    <?php endforeach; ?>
                </div>
                <form method="get" class="suche">
                    <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
                    <input type="search" name="q" value="<?= e($suche) ?>" placeholder="Nummer, Kunde, Ort …" aria-label="Suchen">
                    <button type="submit" class="btn-small btn-primary">Suchen</button>
                </form>
            </div>

            <div class="tabelle-wrap">
                <table>
                    <thead>
                        <tr><th>Nummer</th><th>Datum</th><th>Kunde</th><th>Projekt</th><th class="num">Total exkl. MwSt.</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php if (!$offerten): ?>
                            <tr><td colspan="6" class="empty">Keine Offerten gefunden.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($offerten as $o): ?>
                            <tr class="klickbar" onclick="location.href='offerte.php?id=<?= (int) $o['id'] ?>'">
                                <td><a href="offerte.php?id=<?= (int) $o['id'] ?>"><strong><?= e($o['nummer']) ?></strong></a></td>
                                <td><?= datum($o['erstellt']) ?></td>
                                <td>
                                    <?= e(kunde_name($o)) ?>
                                    <br><small class="gedimmt"><?= e(implode(' · ', array_filter([$o['firma'], $o['ort']]))) ?></small>
                                </td>
                                <td><?= e($o['titel'] ?: '–') ?></td>
                                <td class="num"><?= chf((float) $o['netto']) ?></td>
                                <td><?= status_badge($o['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
<?php admin_fuss();

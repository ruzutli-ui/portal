<?php
require __DIR__ . '/_init.php';
admin_erforderlich();

$suche = trim($_GET['q'] ?? '');
$sql = 'SELECT k.*, COUNT(DISTINCT o.id) AS offerten, MAX(o.erstellt) AS letzte,
               COALESCE(SUM(CASE WHEN o.status = \'angenommen\' THEN p.preis * p.menge END), 0) AS umsatz
        FROM kunden k
        LEFT JOIN offerten o ON o.kunde_id = k.id
        LEFT JOIN offert_positionen p ON p.offerte_id = o.id';
$params = [];
if ($suche !== '') {
    $sql .= ' WHERE k.email LIKE ? OR k.firma LIKE ? OR k.vorname LIKE ? OR k.name LIKE ? OR k.ort LIKE ?';
    $params = array_fill(0, 5, "%$suche%");
}
$stmt = db()->prepare($sql . ' GROUP BY k.id ORDER BY letzte IS NULL, letzte DESC, k.id DESC');
$stmt->execute($params);
$kunden = $stmt->fetchAll();

admin_kopf('Kunden', 'kunden', 'Alle Kunden, die sich im Portal angemeldet haben.');
?>
        <section class="card">
            <div class="werkzeugleiste">
                <h2>Kunden <span class="count"><?= count($kunden) ?></span></h2>
                <form method="get" class="suche">
                    <input type="search" name="q" value="<?= e($suche) ?>" placeholder="Name, Firma, E-Mail, Ort …" aria-label="Suchen">
                    <button type="submit" class="btn-small btn-primary">Suchen</button>
                </form>
            </div>
            <div class="tabelle-wrap">
                <table>
                    <thead>
                        <tr><th>Kunde</th><th>E-Mail</th><th>Ort</th><th class="num">Offerten</th><th>Letzte Offerte</th><th class="num">Angenommen</th></tr>
                    </thead>
                    <tbody>
                        <?php if (!$kunden): ?>
                            <tr><td colspan="6" class="empty">Keine Kunden gefunden.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($kunden as $k): ?>
                            <tr class="klickbar" onclick="location.href='kunde.php?id=<?= (int) $k['id'] ?>'">
                                <td>
                                    <a href="kunde.php?id=<?= (int) $k['id'] ?>"><strong><?= e(kunde_name($k)) ?></strong></a>
                                    <?php if ($k['firma']): ?><br><small class="gedimmt"><?= e($k['firma']) ?></small><?php endif; ?>
                                </td>
                                <td><?= e($k['email']) ?></td>
                                <td><?= e(trim($k['plz'] . ' ' . $k['ort']) ?: '–') ?></td>
                                <td class="num"><?= (int) $k['offerten'] ?></td>
                                <td><?= datum($k['letzte']) ?></td>
                                <td class="num"><?= chf((float) $k['umsatz']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
<?php admin_fuss();

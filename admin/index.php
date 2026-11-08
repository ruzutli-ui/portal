<?php
require __DIR__ . '/_init.php';
admin_erforderlich();

$pdo = db();
$volumen = "SELECT COALESCE(SUM(p.preis * p.menge), 0) FROM offert_positionen p JOIN offerten o ON o.id = p.offerte_id";
$kennzahlen = [
    'Offene Offerten'      => (int) $pdo->query("SELECT COUNT(*) FROM offerten WHERE status = 'offen'")->fetchColumn(),
    'Offenes Volumen'      => chf((float) $pdo->query("$volumen WHERE o.status = 'offen'")->fetchColumn()),
    'Angenommen (Volumen)' => chf((float) $pdo->query("$volumen WHERE o.status = 'angenommen'")->fetchColumn()),
    'Kunden'               => (int) $pdo->query('SELECT COUNT(*) FROM kunden')->fetchColumn(),
];
$total = (int) $pdo->query('SELECT COUNT(*) FROM offerten')->fetchColumn();
$angenommen = (int) $pdo->query("SELECT COUNT(*) FROM offerten WHERE status = 'angenommen'")->fetchColumn();
$entschieden = (int) $pdo->query("SELECT COUNT(*) FROM offerten WHERE status <> 'offen'")->fetchColumn();

$neueste = $pdo->query(
    'SELECT o.*, k.vorname, k.name, k.firma, k.email, COALESCE(SUM(p.preis * p.menge), 0) AS netto
     FROM offerten o JOIN kunden k ON k.id = o.kunde_id LEFT JOIN offert_positionen p ON p.offerte_id = o.id
     GROUP BY o.id ORDER BY o.id DESC LIMIT 8'
)->fetchAll();

$topProdukte = $pdo->query(
    "SELECT p.bezeichnung, SUM(p.menge) AS menge, SUM(p.preis * p.menge) AS umsatz
     FROM offert_positionen p JOIN offerten o ON o.id = p.offerte_id
     GROUP BY p.artikelnr ORDER BY umsatz DESC LIMIT 5"
)->fetchAll();

admin_kopf('Übersicht', 'start', 'Alle Offerten aus dem Kundenportal auf einen Blick.');
?>
        <section class="kennzahlen">
            <?php foreach ($kennzahlen as $label => $wert): ?>
                <div class="kennzahl">
                    <div class="kennzahl-label"><?= e($label) ?></div>
                    <div class="kennzahl-wert"><?= e((string) $wert) ?></div>
                </div>
            <?php endforeach; ?>
        </section>

        <section class="card">
            <h2>Neueste Offerten <span class="count"><?= $total ?></span></h2>
            <div class="tabelle-wrap">
                <table>
                    <thead>
                        <tr><th>Nummer</th><th>Datum</th><th>Kunde</th><th>Projekt</th><th class="num">Total exkl. MwSt.</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php if (!$neueste): ?>
                            <tr><td colspan="6" class="empty">Noch keine Offerten im Portal erstellt.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($neueste as $o): ?>
                            <tr class="klickbar" onclick="location.href='offerte.php?id=<?= (int) $o['id'] ?>'">
                                <td><a href="offerte.php?id=<?= (int) $o['id'] ?>"><strong><?= e($o['nummer']) ?></strong></a></td>
                                <td><?= datum($o['erstellt']) ?></td>
                                <td><?= e(kunde_name($o)) ?><?php if ($o['firma']): ?><br><small class="gedimmt"><?= e($o['firma']) ?></small><?php endif; ?></td>
                                <td><?= e($o['titel'] ?: '–') ?></td>
                                <td class="num"><?= chf((float) $o['netto']) ?></td>
                                <td><?= status_badge($o['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="knopfleiste"><a href="offerten.php" class="btn-small btn-ghost">Alle Offerten</a></div>
        </section>

        <div class="zweispaltig">
            <section class="card">
                <h2>Abschlussquote</h2>
                <?php if ($entschieden): ?>
                    <div class="quote"><?= round($angenommen / $entschieden * 100) ?> %</div>
                    <p class="gedimmt"><?= $angenommen ?> von <?= $entschieden ?> entschiedenen Offerten angenommen.</p>
                <?php else: ?>
                    <p class="gedimmt">Noch keine Offerte angenommen oder abgelehnt.</p>
                <?php endif; ?>
            </section>
            <section class="card">
                <h2>Meistofferierte Produkte</h2>
                <?php if (!$topProdukte): ?>
                    <p class="gedimmt">Noch keine Daten.</p>
                <?php else: ?>
                    <table>
                        <tbody>
                            <?php foreach ($topProdukte as $t): ?>
                                <tr>
                                    <td><?= e($t['bezeichnung']) ?></td>
                                    <td class="num gedimmt"><?= (int) $t['menge'] ?>×</td>
                                    <td class="num"><?= chf((float) $t['umsatz']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
        </div>
<?php admin_fuss();

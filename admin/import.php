<?php
require __DIR__ . '/_init.php';
require __DIR__ . '/_import.php';
admin_erforderlich();

$pdo = db();
$fehler = '';

function import_datei(): ?string
{
    $token = $_SESSION['import']['token'] ?? '';
    $pfad = sys_get_temp_dir() . '/alpvolt-import-' . preg_replace('/[^a-f0-9]/', '', $token) . '.json';
    return $token !== '' && is_file($pfad) ? $pfad : null;
}

function import_verwerfen(): void
{
    if ($pfad = import_datei()) {
        unlink($pfad);
    }
    unset($_SESSION['import']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_pruefen();
    $aktion = $_POST['aktion'] ?? '';

    if ($aktion === 'hochladen') {
        $datei = $_FILES['datei'] ?? null;
        $rabatt = betrag_lesen($_POST['rabatt'] ?? '0') ?? 0.0;
        try {
            if (!$datei || $datei['error'] === UPLOAD_ERR_NO_FILE) {
                throw new RuntimeException('Bitte eine Datei auswählen.');
            }
            if ($datei['error'] !== UPLOAD_ERR_OK || $datei['size'] > 10 * 1024 * 1024) {
                throw new RuntimeException('Die Datei konnte nicht hochgeladen werden (max. 10 MB).');
            }
            if ($rabatt < 0 || $rabatt >= 100) {
                throw new RuntimeException('Der Rabatt muss zwischen 0 und 99 % liegen.');
            }
            $tabelle = tabelle_lesen($datei['tmp_name'], $datei['name']);
            import_verwerfen();
            $token = bin2hex(random_bytes(16));
            file_put_contents(sys_get_temp_dir() . "/alpvolt-import-$token.json", json_encode($tabelle));
            $_SESSION['import'] = [
                'token'     => $token,
                'datei'     => $datei['name'],
                'zuordnung' => spalten_erraten($tabelle['kopf']),
                'rabatt'    => $rabatt,
                'ausblenden'=> false,
            ];
            einstellung_setzen('import_rabatt', (string) $rabatt);
            redirect('import.php');
        } catch (RuntimeException $ex) {
            $fehler = $ex->getMessage();
        }
    }

    if ($aktion === 'abbrechen') {
        import_verwerfen();
        redirect('import.php');
    }

    if (in_array($aktion, ['vorschau', 'ausfuehren'], true) && import_datei()) {
        $tabelle = json_decode(file_get_contents(import_datei()), true);
        $zuordnung = [];
        foreach (IMPORT_FELDER as $feld => $_) {
            $i = $_POST['zuordnung'][$feld] ?? '';
            $zuordnung[$feld] = $i === '' || !isset($tabelle['kopf'][(int) $i]) ? null : (int) $i;
        }
        $rabatt = betrag_lesen($_POST['rabatt'] ?? '0') ?? 0.0;
        $rabatt = max(0.0, min(99.0, $rabatt));
        $_SESSION['import']['zuordnung'] = $zuordnung;
        $_SESSION['import']['rabatt'] = $rabatt;
        $_SESSION['import']['ausblenden'] = !empty($_POST['ausblenden']);
        einstellung_setzen('import_rabatt', (string) $rabatt);

        if ($aktion === 'ausfuehren') {
            $fehlend = array_filter(IMPORT_FELDER, fn ($d, $f) => $d['pflicht'] && $zuordnung[$f] === null, ARRAY_FILTER_USE_BOTH);
            $auswertung = import_auswerten($tabelle, $zuordnung, $rabatt);
            if (!$fehlend && $auswertung['gueltig']) {
                $jetzt = date('Y-m-d H:i:s');
                $such = $pdo->prepare('SELECT id FROM produkte WHERE artikelnr = ?');
                $update = $pdo->prepare(
                    "UPDATE produkte SET bezeichnung = ?, uvp = ?, preis = ?,
                        kategorie = COALESCE(NULLIF(?, ''), kategorie),
                        beschreibung = COALESCE(NULLIF(?, ''), beschreibung),
                        einheit = COALESCE(NULLIF(?, ''), einheit),
                        quelle = 'salesmachine', importiert = ?, aktiv = 1
                     WHERE id = ?"
                );
                $insert = $pdo->prepare(
                    "INSERT INTO produkte (artikelnr, kategorie, bezeichnung, beschreibung, einheit, uvp, preis, quelle, importiert)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 'salesmachine', ?)"
                );
                $neu = $aktualisiert = 0;
                $pdo->beginTransaction();
                foreach ($auswertung['gueltig'] as $p) {
                    $such->execute([$p['artikelnr']]);
                    if ($id = $such->fetchColumn()) {
                        $update->execute([$p['bezeichnung'], $p['uvp'], $p['preis'], $p['kategorie'], $p['beschreibung'], $p['einheit'], $jetzt, $id]);
                        $aktualisiert++;
                    } else {
                        $insert->execute([$p['artikelnr'], $p['kategorie'] ?: 'Weitere Produkte', $p['bezeichnung'], $p['beschreibung'], $p['einheit'] ?: 'Stk.', $p['uvp'], $p['preis'], $jetzt]);
                        $neu++;
                    }
                }
                $ausgeblendet = 0;
                if ($_SESSION['import']['ausblenden']) {
                    // Nur früher importierte Produkte, die im neuen Export fehlen; manuell erfasste bleiben unberührt.
                    $stmt = $pdo->prepare("UPDATE produkte SET aktiv = 0 WHERE quelle = 'salesmachine' AND aktiv = 1 AND (importiert IS NULL OR importiert <> ?)");
                    $stmt->execute([$jetzt]);
                    $ausgeblendet = $stmt->rowCount();
                }
                $pdo->commit();
                einstellung_setzen('import_letzter', json_encode([
                    'zeit' => $jetzt, 'datei' => $_SESSION['import']['datei'], 'neu' => $neu,
                    'aktualisiert' => $aktualisiert, 'fehler' => count($auswertung['fehler']), 'ausgeblendet' => $ausgeblendet,
                ]));
                import_verwerfen();
                flash("Import abgeschlossen: $neu neu, $aktualisiert aktualisiert"
                    . ($ausgeblendet ? ", $ausgeblendet ausgeblendet" : '')
                    . ($auswertung['fehler'] ? ', ' . count($auswertung['fehler']) . ' Zeilen übersprungen' : '') . '.');
                redirect('produkte.php');
            }
        }
        redirect('import.php');
    }
}

// ─── Anzeige ────────────────────────────────────────────────
$import = import_datei() ? $_SESSION['import'] : null;
$letzter = json_decode(einstellung('import_letzter', 'null') ?? 'null', true);

if ($import) {
    $tabelle = json_decode(file_get_contents(import_datei()), true);
    $zuordnung = $import['zuordnung'];
    $fehlend = array_keys(array_filter(IMPORT_FELDER, fn ($d, $f) => $d['pflicht'] && $zuordnung[$f] === null, ARRAY_FILTER_USE_BOTH));
    $auswertung = $fehlend ? ['gueltig' => [], 'fehler' => []] : import_auswerten($tabelle, $zuordnung, $import['rabatt']);

    // Vergleich mit dem aktuellen Sortiment
    $bestand = [];
    foreach ($pdo->query('SELECT artikelnr, uvp, preis, aktiv FROM produkte') as $b) {
        $bestand[$b['artikelnr']] = $b;
    }
    $anzahlNeu = $anzahlGeaendert = 0;
    foreach ($auswertung['gueltig'] as &$p) {
        $alt = $bestand[$p['artikelnr']] ?? null;
        $p['alt'] = $alt;
        if (!$alt) {
            $anzahlNeu++;
        } elseif ((float) $alt['uvp'] !== $p['uvp'] || (float) $alt['preis'] !== $p['preis']) {
            $anzahlGeaendert++;
        }
    }
    unset($p);
}

admin_kopf('Import aus Sales Machine', 'produkte', 'Produkte mit UVP aus einem CSV- oder Excel-Export übernehmen.');
?>
        <?php if (!$import): ?>
            <section class="card">
                <h2>1. Datei hochladen</h2>
                <p class="hinweis">
                    In der Sales Machine die Produktliste als <strong>CSV</strong> oder <strong>Excel (.xlsx)</strong> exportieren.
                    Benötigt werden mindestens Artikelnummer, Bezeichnung und UVP. Optional: Kundenpreis, Kategorie, Beschreibung, Einheit.
                    Die Spalten werden im nächsten Schritt automatisch erkannt und können angepasst werden.
                </p>
                <?php if ($fehler): ?><p class="fehler"><?= e($fehler) ?></p><?php endif; ?>
                <form method="post" enctype="multipart/form-data" class="form-grid">
                    <?= csrf_feld() ?>
                    <input type="hidden" name="aktion" value="hochladen">
                    <label class="span-2">Datei (.csv oder .xlsx)
                        <input type="file" name="datei" accept=".csv,.txt,.xlsx" required>
                    </label>
                    <label>Standardrabatt auf UVP in %
                        <input name="rabatt" value="<?= e(einstellung('import_rabatt', '0')) ?>" inputmode="decimal">
                    </label>
                    <p class="hinweis feld-hinweis">Gilt für Produkte ohne eigenen Kundenpreis in der Datei. Kundenpreis = UVP − Rabatt, auf 5 Rappen gerundet.</p>
                    <div class="span-2"><button type="submit" class="btn-sun">Datei prüfen</button></div>
                </form>
            </section>

            <?php if ($letzter): ?>
                <section class="card">
                    <h2>Letzter Import</h2>
                    <p>
                        <?= datum($letzter['zeit'], true) ?> · <?= e($letzter['datei']) ?><br>
                        <span class="gedimmt">
                            <?= (int) $letzter['neu'] ?> neu, <?= (int) $letzter['aktualisiert'] ?> aktualisiert<?= $letzter['ausgeblendet'] ? ', ' . (int) $letzter['ausgeblendet'] . ' ausgeblendet' : '' ?><?= $letzter['fehler'] ? ', ' . (int) $letzter['fehler'] . ' Zeilen übersprungen' : '' ?>
                        </span>
                    </p>
                </section>
            <?php endif; ?>
        <?php else: ?>
            <form method="post" id="import-form">
                <?= csrf_feld() ?>
                <section class="card">
                    <div class="werkzeugleiste">
                        <h2>2. Spalten zuordnen</h2>
                        <span class="gedimmt"><?= e($import['datei']) ?> · <?= count($tabelle['zeilen']) ?> Zeilen</span>
                    </div>
                    <div class="zuordnung">
                        <?php foreach (IMPORT_FELDER as $feld => $def): ?>
                            <label><?= e($def['label']) ?><?= $def['pflicht'] ? ' *' : '' ?>
                                <select name="zuordnung[<?= e($feld) ?>]" class="<?= in_array($feld, $fehlend, true) ? 'fehlt' : '' ?>" onchange="this.form.requestSubmit(document.getElementById('btn-vorschau'))">
                                    <option value="">– nicht importieren –</option>
                                    <?php foreach ($tabelle['kopf'] as $i => $spalte): ?>
                                        <option value="<?= $i ?>" <?= $zuordnung[$feld] === $i ? 'selected' : '' ?>><?= e($spalte !== '' ? $spalte : 'Spalte ' . ($i + 1)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        <?php endforeach; ?>
                        <label>Rabatt auf UVP in %
                            <input name="rabatt" value="<?= e((string) $import['rabatt']) ?>" inputmode="decimal" <?= $zuordnung['kundenpreis'] !== null ? 'title="Nur für Zeilen ohne Kundenpreis"' : '' ?>>
                        </label>
                    </div>
                    <p class="hinweis feld-hinweis">
                        <?= $zuordnung['kundenpreis'] !== null
                            ? 'Der Kundenpreis wird aus der Datei übernommen; der Rabatt gilt nur für Zeilen ohne Kundenpreis.'
                            : 'Ohne Kundenpreis-Spalte gilt: Kundenpreis = UVP − ' . e((string) $import['rabatt']) . ' %, auf 5 Rappen gerundet.' ?>
                    </p>
                    <label class="checkbox">
                        <input type="checkbox" name="ausblenden" value="1" <?= $import['ausblenden'] ? 'checked' : '' ?>>
                        Früher importierte Produkte, die in dieser Datei fehlen, im Shop ausblenden
                    </label>
                    <div class="knopfleiste">
                        <button type="submit" name="aktion" value="vorschau" id="btn-vorschau" class="btn-small btn-ghost">Vorschau aktualisieren</button>
                    </div>
                </section>

                <section class="card">
                    <h2>3. Vorschau prüfen</h2>
                    <?php if ($fehlend): ?>
                        <p class="fehler">Bitte zuordnen: <?= e(implode(', ', array_map(fn ($f) => IMPORT_FELDER[$f]['label'], $fehlend))) ?>.</p>
                    <?php else: ?>
                        <div class="kennzahlen klein">
                            <div class="kennzahl"><div class="kennzahl-label">Gültige Produkte</div><div class="kennzahl-wert"><?= count($auswertung['gueltig']) ?></div></div>
                            <div class="kennzahl"><div class="kennzahl-label">Neu</div><div class="kennzahl-wert"><?= $anzahlNeu ?></div></div>
                            <div class="kennzahl"><div class="kennzahl-label">Preis geändert</div><div class="kennzahl-wert"><?= $anzahlGeaendert ?></div></div>
                            <div class="kennzahl"><div class="kennzahl-label">Übersprungen</div><div class="kennzahl-wert <?= $auswertung['fehler'] ? 'warnung' : '' ?>"><?= count($auswertung['fehler']) ?></div></div>
                        </div>

                        <div class="tabelle-wrap abstand-oben">
                            <table class="positionen">
                                <thead>
                                    <tr><th>Art.-Nr.</th><th>Bezeichnung</th><th>Kategorie</th><th class="num">UVP</th><th class="num">Kundenpreis</th><th class="num">Rabatt</th><th>Status</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($auswertung['gueltig'], 0, 25) as $p): ?>
                                        <tr>
                                            <td class="id"><?= e($p['artikelnr']) ?></td>
                                            <td><?= e($p['bezeichnung']) ?></td>
                                            <td><?= e($p['kategorie'] ?: ($p['alt'] ? '(unverändert)' : 'Weitere Produkte')) ?></td>
                                            <td class="num"><?= chf($p['uvp']) ?></td>
                                            <td class="num"><?= chf($p['preis']) ?></td>
                                            <td class="num"><?= rabatt_prozent($p['uvp'], $p['preis']) ? '−' . rabatt_prozent($p['uvp'], $p['preis']) . ' %' : '–' ?></td>
                                            <td>
                                                <?php if (!$p['alt']): ?>
                                                    <span class="status status-angenommen">Neu</span>
                                                <?php elseif ((float) $p['alt']['uvp'] !== $p['uvp'] || (float) $p['alt']['preis'] !== $p['preis']): ?>
                                                    <span class="status status-offen" title="bisher UVP <?= e(chf((float) $p['alt']['uvp'])) ?> / Kundenpreis <?= e(chf((float) $p['alt']['preis'])) ?>">Preis geändert</span>
                                                <?php else: ?>
                                                    <span class="gedimmt">unverändert</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if (count($auswertung['gueltig']) > 25): ?>
                            <p class="hinweis abstand-oben">… und <?= count($auswertung['gueltig']) - 25 ?> weitere Produkte.</p>
                        <?php endif; ?>

                        <?php if ($auswertung['fehler']): ?>
                            <h3 class="unter-titel">Übersprungene Zeilen</h3>
                            <ul class="fehlerliste">
                                <?php foreach (array_slice($auswertung['fehler'], 0, 20) as $f): ?>
                                    <li>Zeile <?= (int) $f['zeile'] ?>: <?= e($f['grund']) ?><?= $f['artikelnr'] || $f['bezeichnung'] ? ' – ' . e(trim($f['artikelnr'] . ' ' . $f['bezeichnung'])) : '' ?></li>
                                <?php endforeach; ?>
                                <?php if (count($auswertung['fehler']) > 20): ?><li>… und <?= count($auswertung['fehler']) - 20 ?> weitere</li><?php endif; ?>
                            </ul>
                        <?php endif; ?>
                    <?php endif; ?>

                    <div class="knopfleiste">
                        <button type="submit" name="aktion" value="ausfuehren" class="btn-sun" <?= $fehlend || !$auswertung['gueltig'] ? 'disabled' : '' ?>
                            onclick="return confirm('<?= count($auswertung['gueltig']) ?> Produkte importieren?');">
                            <?= count($auswertung['gueltig']) ?> Produkte importieren
                        </button>
                        <button type="submit" name="aktion" value="abbrechen" class="btn-small btn-ghost" formnovalidate>Abbrechen</button>
                    </div>
                </section>
            </form>
        <?php endif; ?>
<?php admin_fuss();

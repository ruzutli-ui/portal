<?php
require __DIR__ . '/_init.php';

if (!empty($_SESSION['admin'])) {
    redirect('index.php');
}

$fehler = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_pruefen();
    if (hash_equals(admin_passwort(), (string) ($_POST['passwort'] ?? ''))) {
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        redirect('index.php');
    }
    usleep(500000); // bremst einfaches Durchprobieren
    $fehler = 'Passwort ist falsch.';
}

admin_kopf('Admin-Anmeldung', '', 'Interner Bereich für Offerten, Kunden und Produkte.');
?>
        <section class="card login-card">
            <h2>Anmelden</h2>
            <?php if (!getenv('ALPVOLT_ADMIN_PASSWORT')): ?>
                <p class="hinweis">Prototyp: Es ist das Standardpasswort <code><?= e(ADMIN_STANDARD_PASSWORT) ?></code> aktiv. Für den Betrieb die Umgebungsvariable <code>ALPVOLT_ADMIN_PASSWORT</code> setzen.</p>
            <?php endif; ?>
            <?php if ($fehler): ?><p class="fehler"><?= e($fehler) ?></p><?php endif; ?>
            <form method="post" class="form-stack">
                <?= csrf_feld() ?>
                <label>Passwort
                    <input type="password" name="passwort" required autofocus>
                </label>
                <button type="submit" class="btn-sun">Anmelden</button>
            </form>
        </section>
<?php admin_fuss();

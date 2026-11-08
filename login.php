<?php
require __DIR__ . '/db.php';
require __DIR__ . '/layout.php';

if (kunde()) {
    redirect('index.php');
}

$fehler = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_pruefen();
    $email = strtolower(trim($_POST['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $fehler = 'Bitte eine gültige E-Mail-Adresse eingeben.';
    } else {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT id FROM kunden WHERE email = ?');
        $stmt->execute([$email]);
        $id = $stmt->fetchColumn();
        $neu = false;
        if (!$id) {
            $pdo->prepare('INSERT INTO kunden (email) VALUES (?)')->execute([$email]);
            $id = $pdo->lastInsertId();
            $neu = true;
        }
        session_regenerate_id(true);
        $_SESSION['kunde_id'] = (int) $id;
        if ($neu) {
            flash('Willkommen! Bitte ergänzen Sie Ihre Kontaktdaten – sie erscheinen auf Ihren Offerten.');
            redirect('profil.php');
        }
        redirect('index.php');
    }
}

kopf('Anmelden', '', 'Kundenportal', 'Stellen Sie Ihre Solaranlage selbst zusammen und erhalten Sie sofort eine Offerte.');
?>
        <section class="card login-card">
            <h2>Mit E-Mail anmelden</h2>
            <p class="hinweis">Prototyp: Die Anmeldung erfolgt nur mit der E-Mail-Adresse, ohne Passwort. Neue Adressen werden automatisch als Kunde angelegt.</p>
            <?php if ($fehler): ?><p class="fehler"><?= e($fehler) ?></p><?php endif; ?>
            <form method="post" class="form-stack">
                <?= csrf_feld() ?>
                <label>E-Mail
                    <input type="email" name="email" placeholder="name@firma.ch" value="<?= e($_POST['email'] ?? '') ?>" required autofocus>
                </label>
                <button type="submit" class="btn-sun">Weiter</button>
            </form>
        </section>
<?php fuss();

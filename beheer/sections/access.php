<section class="admin-panel">
    <div class="panel-head">
        <div>
            <p class="eyebrow">Admin</p>
            <h2>E-mail en wachtwoord</h2>
        </div>
    </div>
    <form class="field-grid" method="post" action="<?= e(admin_section_url($section)) ?>">
        <?php beheer_hidden_fields($csrfToken, $section, 'save-credentials'); ?>
        <label class="wide">E-mailadres <input type="email" name="username" value="<?= e($adminUsername) ?>" required autocomplete="username"></label>
        <label class="wide">Nieuw wachtwoord <input type="password" name="password" minlength="10" autocomplete="new-password"></label>
        <label class="wide">Herhaal nieuw wachtwoord <input type="password" name="confirm_password" minlength="10" autocomplete="new-password"></label>
        <div class="form-actions"><button type="submit">Toegang bewaren</button></div>
    </form>
</section>
<section class="admin-panel">
    <div class="panel-head">
        <div>
            <p class="eyebrow">Admin</p>
            <h2>Wachtwoord reset link</h2>
        </div>
    </div>
    <form class="field-grid" method="post" action="<?= e(admin_section_url($section)) ?>">
        <?php beheer_hidden_fields($csrfToken, $section, 'generate-password-reset-link'); ?>
        <p class="wide">Genereer een eenmalige link om een nieuw wachtwoord in te stellen. De link verloopt na <?= e((string) ($passwordReset['expiresMinutes'] ?? 60)) ?> minuten.</p>
        <?php if (($passwordReset['generatedLink'] ?? '') !== ''): ?>
            <label class="wide">
                Nieuwe resetlink
                <input value="<?= e((string) $passwordReset['generatedLink']) ?>" readonly onclick="this.select()">
            </label>
        <?php endif; ?>
        <div class="form-actions"><button type="submit">Resetlink genereren</button></div>
    </form>
</section>

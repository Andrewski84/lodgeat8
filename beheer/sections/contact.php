<?php $page = $config['pages']['contact']; ?>
<?php $mailSettings = app_normalized_mail_settings($mailSettings ?? []); ?>
<section class="admin-panel">
    <div class="panel-head">
        <div>
            <p class="eyebrow">Contact</p>
            <h2>Adresgegevens en formulier</h2>
        </div>
        <button type="button" class="secondary-button" data-technical-modal-open>Technische instellingen</button>
    </div>
    <form class="admin-form" method="post" action="<?= e(admin_section_url($section)) ?>">
        <?php beheer_hidden_fields($csrfToken, $section, 'save-general'); ?>
        <div class="field-grid">
            <label>Eigenaar <input name="site[owner]" value="<?= e($config['site']['owner'] ?? '') ?>"></label>
            <label>Bedrijf <input name="site[company]" value="<?= e($config['site']['company'] ?? '') ?>"></label>
            <label>Adres <input name="site[address]" value="<?= e($config['site']['address'] ?? '') ?>"></label>
            <label>Telefoon <input name="site[phone]" value="<?= e($config['site']['phone'] ?? '') ?>"></label>
            <label>E-mail <input type="email" name="site[email]" value="<?= e($config['site']['email'] ?? '') ?>"></label>
            <label>Ondernemingsnummer <input name="site[business_number]" value="<?= e($config['site']['business_number'] ?? '') ?>"></label>
            <label>IBAN <input name="site[iban]" value="<?= e($config['site']['iban'] ?? '') ?>"></label>
        </div>
        <div class="save-bar"><span>Adresgegevens bewaren</span><button type="submit">Bewaren</button></div>
    </form>
</section>
<section class="admin-panel">
    <div class="panel-head">
        <div>
            <p class="eyebrow">Contact</p>
            <h2>Teksten per taal</h2>
        </div>
    </div>
    <form class="admin-form" method="post" action="<?= e(admin_section_url($section)) ?>">
        <?php beheer_hidden_fields($csrfToken, $section, 'save-page'); ?>
        <input type="hidden" name="page_key" value="contact">
        <label class="checkbox-field">
            <input type="hidden" name="contact_form_enabled" value="0">
            <input type="checkbox" name="contact_form_enabled" value="1"<?= ($page['contact_form_enabled'] ?? true) ? ' checked' : '' ?>>
            Contactformulier tonen
        </label>
        <div class="language-grid">
            <?php beheer_page_fields($page, true, true); ?>
        </div>
        <div class="save-bar"><span>Contactpagina bewaren</span><button type="submit">Bewaren</button></div>
    </form>
</section>

<div class="technical-modal" data-technical-modal hidden>
    <div class="technical-modal-backdrop" data-technical-modal-close></div>
    <section class="technical-modal-panel" role="dialog" aria-modal="true" aria-labelledby="technical-modal-title">
        <div class="technical-modal-head">
            <div>
                <p class="eyebrow">Contact</p>
                <h2 id="technical-modal-title">Technische instellingen</h2>
            </div>
            <div class="technical-modal-tools">
                <button
                    type="button"
                    class="technical-help-toggle"
                    data-technical-help-toggle
                    aria-expanded="false"
                    aria-controls="technical-mail-help"
                    aria-label="Uitleg over mailinstellingen"
                >i</button>
                <button type="button" class="icon-button" data-technical-modal-close aria-label="Sluiten">&times;</button>
            </div>
        </div>
        <section class="technical-help" id="technical-mail-help" data-technical-help hidden>
            <dl>
                <div>
                    <dt>SMTP via PHPMailer gebruiken</dt>
                    <dd>Zet dit pas aan wanneer PHPMailer op de server staat. Uitgeschakeld gebruikt de site de gewone PHP <code>mail()</code> fallback.</dd>
                </div>
                <div>
                    <dt>SMTP-host en poort</dt>
                    <dd>De mailserver van je provider. Meestal poort <code>587</code> met STARTTLS of <code>465</code> met SMTPS.</dd>
                </div>
                <div>
                    <dt>SMTP-authenticatie</dt>
                    <dd>Gebruik dit wanneer je mailprovider een login vereist. De gebruikersnaam is vaak het volledige e-mailadres.</dd>
                </div>
                <div>
                    <dt>Wachtwoord</dt>
                    <dd>Leeg laten behoudt het bestaande SMTP-wachtwoord. Een nieuw wachtwoord overschrijft het vorige.</dd>
                </div>
                <div>
                    <dt>Opgeslagen SMTP-wachtwoord wissen</dt>
                    <dd>Verwijdert het bestaande wachtwoord uit <code>storage/mail-settings.json</code>, bijvoorbeeld bij providerwissel.</dd>
                </div>
                <div>
                    <dt>Afzender</dt>
                    <dd>Leeg laten gebruikt de gewone sitenaam en contactmail. Invullen wijzigt de From-naam en From-mail voor contact- en resetmails.</dd>
                </div>
            </dl>
            <p>Wijzigingen worden meteen gebruikt voor nieuwe contactmails en wachtwoordresetmails.</p>
        </section>
        <form class="admin-form" method="post" action="<?= e(admin_section_url($section)) ?>">
            <?php beheer_hidden_fields($csrfToken, $section, 'save-mail-settings'); ?>
            <p class="technical-modal-help">
                Deze gegevens worden lokaal in <code>storage/mail-settings.json</code> bewaard. Laat SMTP uit zolang PHPMailer nog niet op de server staat.
            </p>
            <label class="checkbox-field">
                <input type="hidden" name="mail_settings[enabled]" value="0">
                <input type="checkbox" name="mail_settings[enabled]" value="1"<?= $mailSettings['enabled'] ? ' checked' : '' ?>>
                SMTP via PHPMailer gebruiken
            </label>
            <div class="field-grid">
                <label>SMTP-host <input name="mail_settings[host]" value="<?= e($mailSettings['host']) ?>" placeholder="smtp.jouwdomein.be"></label>
                <label>Poort <input type="number" min="1" max="65535" name="mail_settings[port]" value="<?= e((string) $mailSettings['port']) ?>" placeholder="587"></label>
                <label class="checkbox-field">
                    <input type="hidden" name="mail_settings[smtp_auth]" value="0">
                    <input type="checkbox" name="mail_settings[smtp_auth]" value="1"<?= $mailSettings['smtp_auth'] ? ' checked' : '' ?>>
                    SMTP-authenticatie
                </label>
                <label>Beveiliging
                    <select name="mail_settings[encryption]">
                        <option value="starttls"<?= $mailSettings['encryption'] === 'starttls' ? ' selected' : '' ?>>STARTTLS</option>
                        <option value="smtps"<?= $mailSettings['encryption'] === 'smtps' ? ' selected' : '' ?>>SMTPS</option>
                        <option value="none"<?= $mailSettings['encryption'] === 'none' ? ' selected' : '' ?>>Geen</option>
                    </select>
                </label>
                <label>Gebruikersnaam <input name="mail_settings[username]" value="<?= e($mailSettings['username']) ?>" placeholder="info@lodgingat8.be" autocomplete="username"></label>
                <label>
                    Wachtwoord
                    <input type="password" name="mail_settings[password]" value="" placeholder="<?= $mailPasswordSet ? 'Ingesteld - leeg laten om te behouden' : 'SMTP-wachtwoord' ?>" autocomplete="new-password">
                </label>
                <label>Afzender e-mail <input type="email" name="mail_settings[from_email]" value="<?= e($mailSettings['from_email']) ?>" placeholder="<?= e($config['site']['email'] ?? 'info@lodgingat8.be') ?>"></label>
                <label>Afzender naam <input name="mail_settings[from_name]" value="<?= e($mailSettings['from_name']) ?>" placeholder="<?= e($config['site']['name'] ?? 'Lodging at 8') ?>"></label>
                <label class="checkbox-field wide">
                    <input type="hidden" name="mail_settings[clear_password]" value="0">
                    <input type="checkbox" name="mail_settings[clear_password]" value="1">
                    Opgeslagen SMTP-wachtwoord wissen
                </label>
            </div>
            <div class="technical-modal-actions">
                <button type="submit">Bewaren</button>
                <button type="button" class="secondary-button" data-technical-modal-close>Annuleren</button>
            </div>
        </form>
    </section>
</div>

<?php $page = $config['pages']['contact']; ?>
<section class="admin-panel">
    <div class="panel-head">
        <div>
            <p class="eyebrow">Contact</p>
            <h2>Adresgegevens en formulier</h2>
        </div>
    </div>
    <form class="admin-form" method="post" action="<?= e(admin_section_url($section)) ?>">
        <?php beheer_hidden_fields($csrfToken, $section, 'save-general'); ?>
        <div class="field-grid">
            <label>Eigenaar <input name="site[owner]" value="<?= e($config['site']['owner'] ?? '') ?>"></label>
            <label>Bedrijf <input name="site[company]" value="<?= e($config['site']['company'] ?? '') ?>"></label>
            <label>Adres <input name="site[address]" value="<?= e($config['site']['address'] ?? '') ?>"></label>
            <label>Telefoon <input name="site[phone]" value="<?= e($config['site']['phone'] ?? '') ?>"></label>
            <label>E-mail <input name="site[email]" value="<?= e($config['site']['email'] ?? '') ?>"></label>
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
        <div class="language-grid">
            <?php beheer_page_fields($page, true, true); ?>
        </div>
        <div class="save-bar"><span>Contactpagina bewaren</span><button type="submit">Bewaren</button></div>
    </form>
</section>

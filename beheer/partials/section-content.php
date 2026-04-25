<?php
if ($section === 'algemeen'):
?>
    <section class="admin-panel">
        <div class="panel-head">
            <div>
                <p class="eyebrow">Algemeen</p>
                <h2>Website instellingen</h2>
            </div>
        </div>
        <form class="admin-form" method="post" action="<?= e(admin_section_url($section)) ?>" enctype="multipart/form-data">
            <?php beheer_hidden_fields($csrfToken, $section, 'save-general'); ?>
            <div class="field-grid">
                <label>Website naam <input name="site[name]" value="<?= e($config['site']['name'] ?? '') ?>"></label>
                <div class="logo-manager wide" data-logo-manager>
                    <input type="hidden" name="site[logo]" value="<?= e($siteLogo) ?>" data-logo-current>
                    <input type="hidden" name="site[logo_remove]" value="0" data-logo-remove-input>
                    <div class="logo-manager-head">
                        <span>Logo</span>
                    </div>
                    <div class="logo-tile<?= $siteLogo === '' ? ' is-empty' : '' ?>" role="button" tabindex="0" data-logo-tile>
                        <input class="logo-file-input" type="file" name="logo_upload" accept="image/*" data-logo-input>
                        <div class="logo-tile-media" data-logo-preview>
                            <img src="<?= $siteLogo !== '' ? e('../' . image_path($siteLogo)) : '' ?>" alt="" data-logo-preview-image<?= $siteLogo === '' ? ' hidden' : '' ?>>
                            <span class="logo-empty" data-logo-empty<?= $siteLogo !== '' ? ' hidden' : '' ?>>Logo kiezen</span>
                        </div>
                        <small class="logo-filename" data-logo-preview-name<?= $siteLogo === '' ? ' hidden' : '' ?>><?= e($siteLogo) ?></small>
                        <div class="logo-tile-actions">
                            <button type="button" class="secondary-button logo-edit-button" data-logo-edit>Wijzig</button>
                            <button type="button" class="icon-button is-danger logo-delete-button" data-logo-remove aria-label="Logo verwijderen"<?= $siteLogo === '' ? ' hidden' : '' ?>>&times;</button>
                        </div>
                    </div>
                </div>
                <label class="wide">
                    Reservatie fallback link
                    <input name="site[reservation_url]" value="<?= e($config['site']['reservation_url'] ?? '') ?>">
                </label>
                <div class="wide">
                    <?php beheer_photo_grid('backgrounds', $config['backgrounds'] ?? [], 'background_uploads', 'Achtergrond foto\'s', 'Sleep foto\'s om de volgorde te wijzigen. Nieuwe uploads worden achteraan toegevoegd.'); ?>
                </div>
                <label class="checkbox-field">
                    <input type="checkbox" name="booking_widget[enabled]" value="1"<?= $bookingWidget['enabled'] ? ' checked' : '' ?>>
                    Booking dropdown tonen
                </label>
                <label>Dropdown titel <input name="booking_widget[title]" value="<?= e($bookingWidget['title']) ?>"></label>
                <label>Knoptekst <input name="booking_widget[button_label]" value="<?= e($bookingWidget['button_label']) ?>"></label>
                <label class="wide">
                    Booking module code
                    <textarea name="booking_widget[embed_code]" rows="14"><?= e($bookingWidget['embed_code']) ?></textarea>
                </label>
            </div>
            <div class="save-bar">
                <span>Algemene instellingen bewaren</span>
                <button type="submit">Bewaren</button>
            </div>
        </form>
    </section>
<?php
elseif (in_array($section, ['leuven', 'locatie', 'voorwaarden'], true)):
    $page = $config['pages'][$section];
?>
    <section class="admin-panel">
        <div class="panel-head">
            <div>
                <p class="eyebrow">Pagina</p>
                <h2><?= e(admin_sections()[$section]) ?></h2>
            </div>
        </div>
        <form class="admin-form" method="post" action="<?= e(admin_section_url($section)) ?>" enctype="multipart/form-data">
            <?php beheer_hidden_fields($csrfToken, $section, 'save-page'); ?>
            <input type="hidden" name="page_key" value="<?= e($section) ?>">
            <div class="language-grid">
                <?php beheer_page_fields($page); ?>
            </div>
            <?php if ($section === 'locatie'): ?>
                <label class="wide">
                    Google Maps URL
                    <input name="map_url" value="<?= e($page['map_url'] ?? '') ?>" placeholder="https://www.google.com/maps/...">
                    <small>Plak hier de Google Maps link of de src-url uit de Google Maps insluitcode.</small>
                </label>
            <?php endif; ?>
            <?php if ($section === 'leuven'): ?>
                <?php beheer_photo_grid('gallery', $config['galleries']['leuven'] ?? [], 'gallery_uploads', 'Carousel foto\'s', 'Deze foto\'s worden rechts op de Leuven-pagina getoond.'); ?>
                <input type="hidden" name="gallery_key" value="leuven">
            <?php endif; ?>
            <div class="save-bar"><span>Pagina bewaren</span><button type="submit">Bewaren</button></div>
        </form>
    </section>
<?php
elseif (in_array($section, ['kamer-1', 'kamer-2', 'kamer-3'], true)):
    $room = $config['rooms'][$section];
?>
    <section class="admin-panel">
        <div class="panel-head">
            <div>
                <p class="eyebrow">Kamer</p>
                <h2><?= e(admin_sections()[$section]) ?></h2>
            </div>
        </div>
        <form class="admin-form" method="post" action="<?= e(admin_section_url($section)) ?>" enctype="multipart/form-data">
            <?php beheer_hidden_fields($csrfToken, $section, 'save-room'); ?>
            <input type="hidden" name="room_key" value="<?= e($section) ?>">
            <div class="language-grid">
                <?php beheer_room_fields($room); ?>
            </div>
            <?php beheer_photo_grid('gallery', $config['galleries'][$section] ?? [], 'gallery_uploads', 'Kamer carousel', 'Deze foto\'s worden rechts op de kamerpagina getoond.'); ?>
            <div class="save-bar"><span>Kamer bewaren</span><button type="submit">Bewaren</button></div>
        </form>
    </section>
<?php
elseif ($section === 'contact'):
    $page = $config['pages']['contact'];
?>
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
<?php
elseif ($section === 'links'):
    $page = $config['pages']['links'];
?>
    <section class="admin-panel">
        <div class="panel-head">
            <div>
                <p class="eyebrow">Links</p>
                <h2>Links beheren</h2>
            </div>
        </div>
        <form class="admin-form" method="post" action="<?= e(admin_section_url($section)) ?>">
            <?php beheer_hidden_fields($csrfToken, $section, 'save-links'); ?>
            <div class="language-grid">
                <?php beheer_links_fields($page); ?>
            </div>
            <div class="save-bar"><span>Links bewaren</span><button type="submit">Bewaren</button></div>
        </form>
    </section>
<?php
elseif ($section === 'toegang'):
?>
    <section class="admin-panel">
        <div class="panel-head">
            <div>
                <p class="eyebrow">Admin</p>
                <h2>Login en wachtwoord</h2>
            </div>
        </div>
        <form class="field-grid" method="post" action="<?= e(admin_section_url($section)) ?>">
            <?php beheer_hidden_fields($csrfToken, $section, 'save-credentials'); ?>
            <label class="wide">Login <input name="username" value="<?= e($adminUsername) ?>" required autocomplete="username"></label>
            <label class="wide">Nieuw wachtwoord <input type="password" name="password" minlength="8" autocomplete="new-password"></label>
            <label class="wide">Herhaal nieuw wachtwoord <input type="password" name="confirm_password" minlength="8" autocomplete="new-password"></label>
            <div class="form-actions"><button type="submit">Toegang bewaren</button></div>
        </form>
    </section>
<?php
endif;

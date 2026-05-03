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
            <label class="wide">Website naam <input name="site[name]" value="<?= e($config['site']['name'] ?? '') ?>"></label>
            <div class="logo-manager" data-logo-manager>
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
            <div class="logo-manager" data-logo-manager>
                <input type="hidden" name="site[favicon]" value="<?= e($siteFavicon) ?>" data-logo-current>
                <input type="hidden" name="site[favicon_remove]" value="0" data-logo-remove-input>
                <div class="logo-manager-head">
                    <span>Favicon</span>
                </div>
                <div class="logo-tile<?= $siteFavicon === '' ? ' is-empty' : '' ?>" role="button" tabindex="0" data-logo-tile>
                    <input class="logo-file-input" type="file" name="favicon_upload" accept="image/*" data-logo-input>
                    <div class="logo-tile-media" data-logo-preview>
                        <img src="<?= $siteFavicon !== '' ? e('../' . image_path($siteFavicon)) : '' ?>" alt="" data-logo-preview-image<?= $siteFavicon === '' ? ' hidden' : '' ?>>
                        <span class="logo-empty" data-logo-empty<?= $siteFavicon !== '' ? ' hidden' : '' ?>>Favicon kiezen</span>
                    </div>
                    <small class="logo-filename" data-logo-preview-name<?= $siteFavicon === '' ? ' hidden' : '' ?>><?= e($siteFavicon) ?></small>
                    <div class="logo-tile-actions">
                        <button type="button" class="secondary-button logo-edit-button" data-logo-edit>Wijzig</button>
                        <button type="button" class="icon-button is-danger logo-delete-button" data-logo-remove aria-label="Favicon verwijderen"<?= $siteFavicon === '' ? ' hidden' : '' ?>>&times;</button>
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

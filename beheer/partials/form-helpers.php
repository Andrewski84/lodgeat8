<?php
declare(strict_types=1);

function beheer_hidden_fields(string $csrfToken, string $section, string $action): void
{
    ?>
    <input type="hidden" name="action" value="<?= e($action) ?>">
    <input type="hidden" name="section" value="<?= e($section) ?>">
    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
    <?php
}

function beheer_language_title(string $code, string $label): void
{
    ?>
    <div class="language-title">
        <span><?= e(strtoupper($code)) ?></span>
        <strong><?= e($label) ?></strong>
    </div>
    <?php
}

function beheer_language_tabs(callable $renderPanel): void
{
    $languages = supported_languages();
    $firstLanguage = '';

    foreach ($languages as $language => $_label) {
        $firstLanguage = $language;
        break;
    }
    ?>
    <div class="language-tabs" data-language-tabs>
        <div class="language-tab-list" role="tablist" aria-label="Talen">
            <?php foreach ($languages as $language => $label): ?>
                <button
                    type="button"
                    class="language-tab"
                    data-language-tab="<?= e($language) ?>"
                    aria-selected="<?= $language === $firstLanguage ? 'true' : 'false' ?>"
                >
                    <span><?= e(strtoupper($language)) ?></span>
                    <small><?= e($label) ?></small>
                </button>
            <?php endforeach; ?>
        </div>
        <div class="language-tab-panels" data-language-panels>
            <?php foreach ($languages as $language => $label): ?>
                <section class="language-editor" data-language-panel="<?= e($language) ?>"<?= $language === $firstLanguage ? '' : ' hidden' ?>>
                    <?php beheer_language_title($language, $label); ?>
                    <?php $renderPanel($language, $label); ?>
                </section>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

function beheer_page_fields(array $page, bool $withIntro = true, bool $withSuccess = false): void
{
    beheer_language_tabs(function (string $language, string $label) use ($page, $withIntro, $withSuccess): void {
        ?>
        <label>
            Paginatitel
            <input name="translations[<?= e($language) ?>][title]" value="<?= e(admin_translated_text($page, 'title', $language)) ?>">
        </label>
        <?php if ($withIntro): ?>
            <label>
                Tekst
                <textarea name="translations[<?= e($language) ?>][intro]" rows="9"><?= e(admin_text_from_lines(admin_translated_lines($page, 'intro', $language))) ?></textarea>
            </label>
        <?php endif; ?>
        <?php if ($withSuccess): ?>
            <label>
                Bericht na verzenden
                <textarea name="translations[<?= e($language) ?>][success_message]" rows="3"><?= e(admin_translated_text($page, 'success_message', $language)) ?></textarea>
            </label>
        <?php endif; ?>
        <?php
    });
}

function beheer_list_editor(string $name, array $items, string $label, string $placeholder): void
{
    $cleanItems = [];

    foreach ($items as $item) {
        $item = trim((string) $item);

        if ($item !== '') {
            $cleanItems[] = $item;
        }
    }

    $items = $cleanItems;

    if ($items === []) {
        $items[] = '';
    }
    ?>
    <div class="list-editor" data-list-editor>
        <div class="list-editor-head">
            <span><?= e($label) ?></span>
            <button type="button" class="secondary-button" data-list-add>Toevoegen</button>
        </div>
        <div class="list-items" data-list-items>
            <?php foreach ($items as $item): ?>
                <div class="list-row" data-list-row>
                    <span class="list-bullet" aria-hidden="true"></span>
                    <input name="<?= e($name) ?>[]" value="<?= e($item) ?>" placeholder="<?= e($placeholder) ?>">
                    <button type="button" class="icon-button is-danger" data-list-remove aria-label="Voorziening verwijderen">&times;</button>
                </div>
            <?php endforeach; ?>
        </div>
        <template data-list-template>
            <div class="list-row" data-list-row>
                <span class="list-bullet" aria-hidden="true"></span>
                <input name="<?= e($name) ?>[]" value="" placeholder="<?= e($placeholder) ?>">
                <button type="button" class="icon-button is-danger" data-list-remove aria-label="Voorziening verwijderen">&times;</button>
            </div>
        </template>
    </div>
    <?php
}

function beheer_room_fields(array $room): void
{
    beheer_language_tabs(function (string $language, string $label) use ($room): void {
        ?>
        <label>
            Kamernaam
            <input name="translations[<?= e($language) ?>][title]" value="<?= e(admin_translated_text($room, 'title', $language)) ?>">
        </label>
        <label>
            Korte tekst
            <textarea name="translations[<?= e($language) ?>][summary]" rows="3"><?= e(admin_translated_text($room, 'summary', $language)) ?></textarea>
        </label>
        <label>
            Booking link voor "Beschikbaarheid & reservatie"
            <input name="translations[<?= e($language) ?>][booking_url]" value="<?= e(admin_translated_text($room, 'booking_url', $language)) ?>" placeholder="https://">
        </label>
        <?php beheer_list_editor('translations[' . $language . '][features]', admin_translated_lines($room, 'features', $language), 'Voorzieningen', 'Nieuwe voorziening'); ?>
        <label>
            Titel boven prijzen
            <input name="translations[<?= e($language) ?>][prices_heading]" value="<?= e(admin_translated_text($room, 'prices_heading', $language)) ?>">
        </label>
        <label>
            Kamerprijzen
            <textarea name="translations[<?= e($language) ?>][prices]" rows="4"><?= e(admin_text_from_pairs(admin_translated_pairs($room, 'prices', $language))) ?></textarea>
        </label>
        <label>
            Extra info
            <textarea name="translations[<?= e($language) ?>][extra_info]" rows="5"><?= e(admin_text_from_lines(admin_translated_lines($room, 'extra_info', $language))) ?></textarea>
        </label>
        <?php
    });
}

function beheer_links_fields(array $page): void
{
    beheer_language_tabs(function (string $language, string $label) use ($page): void {
        $columns = admin_translated_value($page, 'columns', $language, $page['columns'] ?? []);
        $sections = is_array($columns) ? admin_link_sections_from_columns($columns) : admin_link_sections_from_columns([]);
        ?>
        <label>
            Paginatitel
            <input name="translations[<?= e($language) ?>][title]" value="<?= e(admin_translated_text($page, 'title', $language)) ?>">
        </label>
        <?php foreach ($sections as $index => $section): ?>
            <?php $rows = $section['rows'] === [] ? [['label' => '', 'url' => '']] : $section['rows']; ?>
            <section class="link-section-editor" data-link-section-editor>
                <div class="link-section-head">
                    <label>
                        Header
                        <input name="translations[<?= e($language) ?>][sections][<?= e((string) $index) ?>][heading]" value="<?= e($section['heading']) ?>">
                    </label>
                    <button type="button" class="secondary-button" data-link-row-add>Rij toevoegen</button>
                </div>
                <div class="link-table">
                    <div class="link-table-head">
                        <span>Label</span>
                        <span>URL</span>
                        <span></span>
                    </div>
                    <div class="link-table-body" data-link-row-list>
                        <?php foreach ($rows as $row): ?>
                            <div class="link-row" data-link-row>
                                <input name="translations[<?= e($language) ?>][sections][<?= e((string) $index) ?>][links][label][]" value="<?= e($row['label']) ?>" placeholder="Leuven">
                                <input name="translations[<?= e($language) ?>][sections][<?= e((string) $index) ?>][links][url][]" value="<?= e($row['url']) ?>" placeholder="https://">
                                <button type="button" class="icon-button is-danger" data-link-row-remove aria-label="Rij verwijderen">&times;</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <template data-link-row-template>
                    <div class="link-row" data-link-row>
                        <input name="translations[<?= e($language) ?>][sections][<?= e((string) $index) ?>][links][label][]" value="" placeholder="Leuven">
                        <input name="translations[<?= e($language) ?>][sections][<?= e((string) $index) ?>][links][url][]" value="" placeholder="https://">
                        <button type="button" class="icon-button is-danger" data-link-row-remove aria-label="Rij verwijderen">&times;</button>
                    </div>
                </template>
            </section>
        <?php endforeach; ?>
        <?php
    });
}

function beheer_photo_grid(string $fieldName, array $items, string $uploadName, string $title, string $help): void
{
    $items = admin_normalize_media_items($items);
    ?>
    <section class="photo-manager" data-photo-manager>
        <div class="photo-manager-head">
            <div>
                <h3><?= e($title) ?></h3>
                <p><?= e($help) ?></p>
            </div>
        </div>
        <div class="photo-autosave" data-photo-autosave hidden>
            <div class="photo-progress-line">
                <div class="photo-progress" data-photo-progress-track aria-hidden="true">
                    <span data-photo-progress></span>
                </div>
                <strong data-photo-progress-value>0%</strong>
            </div>
            <span data-photo-status>Automatisch bewaren...</span>
        </div>
        <label class="photo-drop-zone" data-photo-drop-zone>
            <input type="file" name="<?= e($uploadName) ?>[]" accept="image/*" multiple data-photo-input>
            <span data-photo-drop-label>Sleep foto's hierheen</span>
            <small>of klik om foto's te kiezen</small>
        </label>
        <div class="photo-grid" data-photo-grid>
            <?php foreach ($items as $item): ?>
                <article class="photo-card" draggable="true" data-photo-card>
                    <div class="photo-thumb">
                        <img src="<?= e('../' . image_path($item['file'])) ?>" alt="">
                    </div>
                    <input type="hidden" name="<?= e($fieldName) ?>[file][]" value="<?= e($item['file']) ?>">
                    <label>
                        Titel / alt tekst
                        <input name="<?= e($fieldName) ?>[title][]" value="<?= e($item['title']) ?>">
                    </label>
                    <div class="photo-card-actions">
                        <button type="button" class="icon-button" data-photo-up aria-label="Foto omhoog">&uarr;</button>
                        <button type="button" class="icon-button" data-photo-down aria-label="Foto omlaag">&darr;</button>
                        <div class="photo-delete" data-photo-delete>
                            <button type="button" class="icon-button is-danger" data-photo-delete-toggle aria-label="Foto verwijderen">&#128465;</button>
                            <div class="delete-confirm" data-photo-delete-confirm hidden>
                                <span>Ben je zeker?</span>
                                <button type="button" class="confirm-yes" data-photo-delete-yes>Ja</button>
                                <button type="button" class="confirm-no" data-photo-delete-no>Nee</button>
                            </div>
                            <input type="hidden" name="<?= e($fieldName) ?>[remove][]" value="<?= e($item['file']) ?>" disabled data-photo-delete-input>
                        </div>
                    </div>
                    <small><?= e($item['file']) ?></small>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
}

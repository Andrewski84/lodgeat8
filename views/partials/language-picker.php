<?php
/*
 * Shared language picker.
 *
 * Callers can optionally set $languagePickerClass or $languagePickerLabel before
 * including this partial. The variables are unset at the end so one include
 * cannot accidentally affect the next picker on the same page.
 */
$languagePickerClass = trim('language-picker ' . (string) ($languagePickerClass ?? ''));
$languagePickerLabel = (string) ($languagePickerLabel ?? 'Select language');
?>
<details class="<?= e($languagePickerClass) ?>" aria-label="<?= e($languagePickerLabel) ?>">
    <summary class="language-picker-summary" aria-label="<?= e($languagePickerLabel) ?>">
        <span class="language-picker-label"><?= e(get_language_display($currentLanguage, true)) ?></span>
        <span class="language-picker-icon" aria-hidden="true"></span>
    </summary>
    <div class="language-menu">
        <?php foreach (get_languages_detailed() as $code => $language): ?>
            <a
                href="<?= e(create_language_url($pageKey, $code)) ?>"
                class="language-menu-item"
                <?= $code === $currentLanguage ? 'aria-current="true"' : '' ?>
            >
                <span class="language-menu-abbr"><?= e($language['abbr']) ?></span>
                <span class="language-menu-name"><?= e($language['name']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</details>
<?php
unset($languagePickerClass, $languagePickerLabel);

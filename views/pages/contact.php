<?php
$contactDetails = [];
$contactOwner = trim((string) ($config['site']['owner'] ?? ''));
$contactCompany = trim((string) ($config['site']['company'] ?? ''));
$contactAddress = trim((string) ($config['site']['address'] ?? ''));
$contactBusinessNumber = trim((string) ($config['site']['business_number'] ?? ''));
$contactIban = trim((string) ($config['site']['iban'] ?? ''));
$contactPhone = trim((string) ($config['site']['phone'] ?? ''));
$contactEmail = trim((string) ($config['site']['email'] ?? ''));

if ($contactOwner !== '') {
    $contactDetails[] = ['', $contactOwner];
}

if ($contactCompany !== '') {
    $contactDetails[] = ['', $contactCompany];
}

if ($contactAddress !== '') {
    $contactDetails[] = ['', $contactAddress];
}

if ($contactBusinessNumber !== '') {
    $contactDetails[] = ['Ondernemingsnummer', $contactBusinessNumber];
}

if ($contactIban !== '') {
    $contactDetails[] = ['IBAN', $contactIban];
}
?>
<?php if ($contactDetails !== []): ?>
    <address>
        <?php foreach ($contactDetails as $index => [$label, $value]): ?>
            <?= $label !== '' ? e($label) . ' ' : '' ?><?= e($value) ?><?= $index < count($contactDetails) - 1 ? '<br>' : '' ?>
        <?php endforeach; ?>
    </address>
<?php endif; ?>
<?php if ($contactPhone !== ''): ?>
    <?php $phoneHref = preg_replace('/[^+0-9]/', '', $contactPhone); ?>
    <p><a href="tel:<?= e($phoneHref) ?>"><?= e($contactPhone) ?></a></p>
<?php endif; ?>
<?php if ($contactEmail !== ''): ?>
    <p><a href="mailto:<?= e($contactEmail) ?>"><?= e($contactEmail) ?></a></p>
<?php endif; ?>
<?php if (isset($page['intro']) && is_array($page['intro'])): ?>
    <?php render_intro($page['intro']); ?>
<?php endif; ?>
<?php if ($contactResult['success']): ?>
    <p class="notice"><?= rich_text_html((string) ($page['success_message'] ?? 'Bedankt, je bericht werd verzonden.')) ?></p>
<?php endif; ?>
<?php if ($contactResult['errors'] !== []): ?>
    <div class="notice is-error">
        <?php foreach ($contactResult['errors'] as $error): ?>
            <p><?= e($error) ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<form class="contact-form" method="post" action="<?= e(url_for('contact')) ?>">
    <label class="form-trap">Website <input name="website" tabindex="-1" autocomplete="off"></label>
    <label><?= e(ui_text('form_name')) ?> <input required name="name" autocomplete="name" value="<?= e($contactResult['values']['name']) ?>"></label>
    <label><?= e(ui_text('form_email')) ?> <input required type="email" name="email" autocomplete="email" value="<?= e($contactResult['values']['email']) ?>"></label>
    <label><?= e(ui_text('form_phone')) ?> <input required name="phone" autocomplete="tel" value="<?= e($contactResult['values']['phone']) ?>"></label>
    <label><?= e(ui_text('form_subject')) ?> <input name="subject" value="<?= e($contactResult['values']['subject']) ?>"></label>
    <label class="wide"><?= e(ui_text('form_message')) ?> <textarea required name="message" rows="6"><?= e($contactResult['values']['message']) ?></textarea></label>
    <button type="submit"><?= e(ui_text('form_send')) ?></button>
</form>

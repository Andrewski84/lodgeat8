<address>
    Steven Frooninckx<br>
    B&amp;B Lodging At 8 V.O.F.<br>
    <?= e($config['site']['address']) ?><br>
    Ondernemingsnummer BE 0898 536 239<br>
    IBAN BE84-0682-4977-8259
</address>
<?php $phoneHref = preg_replace('/[^+0-9]/', '', (string) $config['site']['phone']); ?>
<p><a href="tel:<?= e($phoneHref) ?>"><?= e($config['site']['phone']) ?></a></p>
<p><a href="mailto:<?= e($config['site']['email']) ?>"><?= e($config['site']['email']) ?></a></p>
<?php if (isset($page['intro']) && is_array($page['intro'])): ?>
    <?php render_intro($page['intro']); ?>
<?php endif; ?>
<?php if ($contactResult['success']): ?>
    <p class="notice"><?= e($page['success_message'] ?? 'Bedankt, je bericht werd verzonden.') ?></p>
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

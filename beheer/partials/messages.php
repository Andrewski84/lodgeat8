<?php if ($messages !== []): ?>
    <div class="message-stack is-toast" data-admin-toast data-admin-toast-autohide>
        <?php foreach ($messages as $message): ?>
            <div class="message is-success">
                <span><?= e($message) ?></span>
                <button type="button" class="message-close" data-admin-toast-close aria-label="Melding sluiten">&times;</button>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($errors !== []): ?>
    <div class="message-stack is-toast is-error" data-admin-toast>
        <?php foreach ($errors as $error): ?>
            <div class="message is-error">
                <span><?= e($error) ?></span>
                <button type="button" class="message-close" data-admin-toast-close aria-label="Melding sluiten">&times;</button>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

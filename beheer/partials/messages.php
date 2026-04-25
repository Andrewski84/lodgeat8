<?php if ($messages !== []): ?>
    <div class="message-stack">
        <?php foreach ($messages as $message): ?>
            <p class="message is-success"><?= e($message) ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($errors !== []): ?>
    <div class="message-stack">
        <?php foreach ($errors as $error): ?>
            <p class="message is-error"><?= e($error) ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

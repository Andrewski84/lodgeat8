<?php $room = $config['rooms'][$section]; ?>
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

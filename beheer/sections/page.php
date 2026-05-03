<?php $page = $config['pages'][$section]; ?>
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

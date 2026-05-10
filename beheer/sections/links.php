<?php
/*
 * Links admin section.
 *
 * The public links page stores grouped link columns. The admin UI edits those
 * as named sections with rows, then converts them back to the public JSON model
 * during save.
 */
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

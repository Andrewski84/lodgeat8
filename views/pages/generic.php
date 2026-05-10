<?php
/*
 * Generic page template.
 *
 * Most text-heavy pages only need localized intro blocks. Keeping this fallback
 * tiny lets content-only pages render without a dedicated template file.
 */
?>
<?php if (isset($page['intro']) && is_array($page['intro'])): ?>
    <?php render_intro($page['intro']); ?>
<?php endif; ?>

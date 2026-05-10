<?php
/*
 * Location page template.
 *
 * The editable map value can be either a normal Google Maps URL or an embed
 * URL. map_embed_url() normalizes it for the iframe while the fallback link
 * remains safe for opening in a new tab.
 */
?>
<?php render_intro($page['intro']); ?>
<?php $mapUrl = trim((string) ($page['map_url'] ?? '')); ?>
<?php $mapEmbedUrl = map_embed_url($mapUrl); ?>
<?php $mapLinkUrl = is_safe_web_url($mapUrl) ? $mapUrl : $mapEmbedUrl; ?>
<?php if ($mapEmbedUrl !== ''): ?>
    <div class="map-embed" aria-label="Kaart van Lodging at 8 in Leuven">
        <iframe
            title="Lodging at 8 op de kaart"
            src="<?= e($mapEmbedUrl) ?>"
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
        ></iframe>
    </div>
    <p class="map-link">
        <a href="<?= e($mapLinkUrl) ?>" target="_blank" rel="noopener">Open in Google Maps</a>
    </p>
<?php endif; ?>

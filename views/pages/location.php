<?php render_intro($page['intro']); ?>
<?php $mapUrl = trim((string) ($page['map_url'] ?? '')); ?>
<?php $mapEmbedUrl = map_embed_url($mapUrl); ?>
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
        <a href="<?= e($mapUrl) ?>" target="_blank" rel="noopener">Open in Google Maps</a>
    </p>
<?php endif; ?>

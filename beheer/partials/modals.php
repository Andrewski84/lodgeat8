<?php
/*
 * Shared admin modals.
 *
 * These dialogs are included once by the authenticated admin shell. JavaScript
 * opens them for unsaved-change navigation, optional browser-side photo
 * resizing, background focus selection and rich-text link insertion.
 */
?>
<div class="unsaved-modal" data-unsaved-modal hidden>
    <div class="unsaved-modal-backdrop" data-unsaved-stay></div>
    <section class="unsaved-modal-panel" role="dialog" aria-modal="true" aria-labelledby="unsaved-modal-title">
        <h2 id="unsaved-modal-title">Niet-opgeslagen wijzigingen</h2>
        <p>Je hebt niet-opgeslagen wijzigingen. Wat wil je doen?</p>
        <div class="unsaved-modal-actions">
            <button type="button" data-unsaved-save>Opslaan</button>
            <button type="button" data-unsaved-leave>Niet opslaan</button>
            <button type="button" class="secondary-button" data-unsaved-stay>Terug</button>
        </div>
    </section>
</div>

<div class="photo-resize-modal" data-photo-resize-modal hidden>
    <div class="photo-resize-modal-backdrop" data-photo-resize-no></div>
    <section class="photo-resize-modal-panel" role="dialog" aria-modal="true" aria-labelledby="photo-resize-modal-title">
        <h2 id="photo-resize-modal-title">Grote JPG-foto's</h2>
        <p data-photo-resize-summary></p>
        <p data-photo-resize-detail></p>
        <div class="photo-resize-modal-actions">
            <button type="button" data-photo-resize-yes>Verkleinen</button>
            <button type="button" class="secondary-button" data-photo-resize-no>Origineel uploaden</button>
        </div>
    </section>
</div>

<div class="background-focus-modal" data-background-focus-modal hidden>
    <div class="background-focus-modal-backdrop" data-background-focus-close></div>
    <section class="background-focus-modal-panel" role="dialog" aria-modal="true" aria-labelledby="background-focus-modal-title">
        <div class="background-focus-modal-head">
            <div>
                <h2 id="background-focus-modal-title">Focuspunt kiezen</h2>
                <p>Klik op het deel van de foto dat belangrijk moet blijven bij cropped weergave.</p>
            </div>
            <button type="button" class="icon-button" data-background-focus-close aria-label="Sluiten">&times;</button>
        </div>
        <div class="background-focus-modal-body">
            <button type="button" class="background-focus-modal-picker" data-background-focus-modal-picker aria-label="Focuspunt plaatsen">
                <img src="" alt="" data-background-focus-modal-image>
                <span class="background-focus-marker" aria-hidden="true"></span>
            </button>
            <div
                class="background-focus-preview background-focus-modal-preview"
                data-background-focus-modal-preview
            >
                <span>Voorbeeld op site</span>
            </div>
        </div>
        <div class="background-focus-modal-actions">
            <output data-background-focus-modal-value>50% / 50%</output>
            <button type="button" data-background-focus-close>Klaar</button>
        </div>
    </section>
</div>

<div class="link-modal" data-link-modal hidden>
    <div class="link-modal-backdrop" data-link-modal-close></div>
    <section class="link-modal-panel" role="dialog" aria-modal="true" aria-labelledby="link-modal-title">
        <h2 id="link-modal-title">Link invoegen</h2>
        <form data-link-form>
            <label>
                Link URL
                <input type="url" name="url" placeholder="https://example.com" required data-link-input>
            </label>
            <label>
                Weergave tekst (optioneel)
                <input type="text" name="text" placeholder="Klik hier" data-link-text>
            </label>
            <div class="link-modal-actions">
                <button type="submit" data-link-submit>Invoegen</button>
                <button type="button" class="secondary-button" data-link-modal-close>Annuleren</button>
            </div>
        </form>
    </section>
</div>

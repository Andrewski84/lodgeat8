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

<div class="link-modal" data-link-modal hidden>
    <div class="link-modal-backdrop" data-link-modal-close></div>
    <section class="link-modal-panel" role="dialog" aria-modal="true" aria-labelledby="link-modal-title">
        <h2 id="link-modal-title">Link invoegen</h2>
        <form data-link-form>
            <label>
                Link URL
                <input type="text" name="url" placeholder="https://example.com" required data-link-input>
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

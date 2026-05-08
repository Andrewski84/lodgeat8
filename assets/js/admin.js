const photoRequests = new WeakMap();
const photoSaveTimers = new WeakMap();
const photoHideTimers = new WeakMap();
const activePhotoManagers = new Set();
const saveFeedbackVisibleMs = 3000;
const photoUploadTimeoutMs = 180000;
const photoResizeThresholdBytes = 1024 * 1024;
const photoResizeTargetBytes = photoResizeThresholdBytes;
const photoResizeMaxDimension = 1920;
const photoResizeMinDimension = 1280;
const photoResizeQualities = [0.84, 0.78, 0.72, 0.66];

// Shared dirty-state handling. Any editable admin form can reveal its save bar
// and trigger the unsaved-changes modal before admin navigation, refresh keys
// or browser back/forward navigation.
let hasUnsavedChanges = false;
let pendingNavigationUrl = '';
let pendingNavigationHandler = null;
let isSubmittingForm = false;
const dirtyForms = new Set();
let lastDirtyForm = null;
const adminSessionTimeoutMs = Math.max(0, Number.parseInt(document.body?.dataset?.adminSessionTimeout || '0', 10) || 0) * 1000;
let adminSessionLastRefreshAt = Date.now();
let adminSessionWarningShown = false;
let adminSessionExpiredShown = false;

const refreshAdminSessionTimer = () => {
    adminSessionLastRefreshAt = Date.now();
    adminSessionWarningShown = false;
    adminSessionExpiredShown = false;
};

const unsavedModal = document.querySelector('[data-unsaved-modal]');
const unsavedSaveButton = unsavedModal?.querySelector('[data-unsaved-save]');
const unsavedStayButton = unsavedModal?.querySelector('button[data-unsaved-stay]');
const unsavedLeaveButton = unsavedModal?.querySelector('[data-unsaved-leave]');
const photoResizeModal = document.querySelector('[data-photo-resize-modal]');
const photoResizeSummary = photoResizeModal?.querySelector('[data-photo-resize-summary]');
const photoResizeDetail = photoResizeModal?.querySelector('[data-photo-resize-detail]');
const photoResizeYesButton = photoResizeModal?.querySelector('[data-photo-resize-yes]');
let photoResizeModalResolve = null;
const backgroundFocusModal = document.querySelector('[data-background-focus-modal]');
const backgroundFocusModalPicker = backgroundFocusModal?.querySelector('[data-background-focus-modal-picker]');
const backgroundFocusModalImage = backgroundFocusModal?.querySelector('[data-background-focus-modal-image]');
const backgroundFocusModalPreview = backgroundFocusModal?.querySelector('[data-background-focus-modal-preview]');
const backgroundFocusModalValue = backgroundFocusModal?.querySelector('[data-background-focus-modal-value]');
let activeBackgroundFocusCard = null;
let activeBackgroundFocusManager = null;

const formFromSource = (source) => {
    if (!source) {
        return null;
    }

    if (source.matches?.('form')) {
        return source;
    }

    return source.closest?.('form') || null;
};

const markUnsavedChanges = (source = null) => {
    hasUnsavedChanges = true;

    const form = formFromSource(source);

    if (form) {
        dirtyForms.add(form);
        lastDirtyForm = form;
        form.classList.add('has-unsaved-changes');
    }
};

const clearUnsavedChanges = (source = null) => {
    const form = formFromSource(source);

    if (form) {
        dirtyForms.delete(form);
        form.classList.remove('has-unsaved-changes');
        hasUnsavedChanges = dirtyForms.size > 0;
        lastDirtyForm = lastDirtyForm === form ? Array.from(dirtyForms).pop() || null : lastDirtyForm;
        pendingNavigationUrl = '';
        pendingNavigationHandler = null;

        return;
    }

    dirtyForms.forEach((dirtyForm) => dirtyForm.classList.remove('has-unsaved-changes'));
    dirtyForms.clear();
    lastDirtyForm = null;
    hasUnsavedChanges = false;
    pendingNavigationUrl = '';
    pendingNavigationHandler = null;
};

const showUnsavedModal = (url, handler = null) => {
    if (!unsavedModal) {
        return false;
    }

    pendingNavigationUrl = url;
    pendingNavigationHandler = typeof handler === 'function' ? handler : null;
    unsavedModal.hidden = false;
    document.body.classList.add('has-unsaved-modal');
    unsavedSaveButton?.focus();

    return true;
};

const setRedirectAfterSave = (form, url) => {
    form.querySelectorAll('input[name="redirect_after_save"]').forEach((input) => input.remove());

    if (url === '') {
        return null;
    }

    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'redirect_after_save';
    input.value = url;
    form.append(input);

    return input;
};

const hideUnsavedModal = () => {
    if (!unsavedModal) {
        return;
    }

    unsavedModal.hidden = true;
    document.body.classList.remove('has-unsaved-modal');
};

const isTrackableControl = (target) => {
    if (!target?.matches?.('input, textarea, select')) {
        return false;
    }

    const type = (target.getAttribute('type') || '').toLowerCase();

    return !['button', 'submit', 'reset', 'hidden'].includes(type);
};

const navigationUrlFromLink = (link) => {
    if (!link || (link.target && link.target !== '_self') || link.hasAttribute('download')) {
        return null;
    }

    const rawHref = link.getAttribute('href') || '';

    if (rawHref === '' || rawHref.startsWith('#')) {
        return null;
    }

    let url;

    try {
        url = new URL(link.href, window.location.href);
    } catch (_error) {
        return null;
    }

    if (!['http:', 'https:'].includes(url.protocol)) {
        return null;
    }

    return url.href;
};

const formHasActivePhotoSave = (form) => Array.from(activePhotoManagers)
    .some((manager) => manager.closest('form') === form);

const setPhotoSaveActive = (manager, active) => {
    const form = manager?.closest('form');

    if (!form) {
        return;
    }

    if (active) {
        activePhotoManagers.add(manager);
    } else {
        activePhotoManagers.delete(manager);
    }

    form.classList.toggle('is-photo-autosaving', formHasActivePhotoSave(form));
};

const finishPhotoRequest = (manager, xhr) => {
    if (photoRequests.get(manager) !== xhr) {
        return false;
    }

    photoRequests.delete(manager);
    setPhotoSaveActive(manager, false);

    return true;
};

const removePhotoManagerFields = (formData, manager) => {
    const fieldName = manager?.dataset?.photoField || '';

    if (fieldName === '') {
        return;
    }

    [
        `${fieldName}[file][]`,
        `${fieldName}[key][]`,
        `${fieldName}[title][]`,
        `${fieldName}[focus_x][]`,
        `${fieldName}[focus_y][]`,
        `${fieldName}[remove][]`,
    ]
        .forEach((name) => formData.delete(name));

    Array.from(formData.keys()).forEach((name) => {
        if (
            name.startsWith(`${fieldName}[pages][`)
            || name.startsWith(`${fieldName}[display][`)
            || name.startsWith(`${fieldName}[focus_x][`)
            || name.startsWith(`${fieldName}[focus_y][`)
        ) {
            formData.delete(name);
        }
    });
};

const photoUploadLimit = (manager) => {
    const limit = Number.parseInt(manager?.dataset?.photoUploadLimit || '0', 10);

    return Number.isFinite(limit) && limit > 0 ? limit : 0;
};

const formatBytes = (bytes) => {
    if (bytes >= 1024 * 1024) {
        return `${(bytes / (1024 * 1024)).toFixed(1).replace('.', ',').replace(',0', '')} MB`;
    }

    if (bytes >= 1024) {
        return `${(bytes / 1024).toFixed(1).replace('.', ',').replace(',0', '')} KB`;
    }

    return `${bytes} bytes`;
};

const validatePhotoFiles = (manager, files) => {
    const limit = photoUploadLimit(manager);

    if (limit <= 0) {
        return true;
    }

    const oversized = files.find((file) => file.size > limit);

    if (!oversized) {
        return true;
    }

    setPhotoStatus(
        manager,
        `${oversized.name} is groter dan de serverlimiet (${formatBytes(limit)}). Verklein de foto en probeer opnieuw.`,
        'error',
        100,
    );

    return false;
};

const photoCanResizeJpeg = () => {
    const canvas = document.createElement('canvas');

    return typeof canvas.toBlob === 'function';
};

const photoFileIsJpeg = (file) => (
    file.type === 'image/jpeg'
    || /\.jpe?g$/i.test(file.name || '')
);

const photoResizeCandidates = (files) => files.filter((file) => (
    photoFileIsJpeg(file)
    && file.size >= photoResizeThresholdBytes
));

const loadPhotoImage = (file) => new Promise((resolve, reject) => {
    const image = new Image();
    const url = URL.createObjectURL(file);

    image.onload = () => {
        URL.revokeObjectURL(url);
        resolve(image);
    };

    image.onerror = () => {
        URL.revokeObjectURL(url);
        reject(new Error('Afbeelding kon niet worden gelezen.'));
    };

    image.src = url;
});

const canvasToJpegBlob = (canvas, quality) => new Promise((resolve) => {
    canvas.toBlob(resolve, 'image/jpeg', quality);
});

const photoResizeTargetDimensions = (longestSide) => {
    const dimensions = [
        Math.min(longestSide, photoResizeMaxDimension),
        1600,
        photoResizeMinDimension,
    ];

    return dimensions
        .filter((dimension) => dimension > 0 && dimension <= longestSide)
        .filter((dimension, index, all) => all.indexOf(dimension) === index);
};

const drawPhotoToCanvas = (image, width, height, targetLongestSide) => {
    const scale = Math.min(1, targetLongestSide / Math.max(width, height));
    const canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.round(width * scale));
    canvas.height = Math.max(1, Math.round(height * scale));

    const context = canvas.getContext('2d', { alpha: false });

    if (!context) {
        return null;
    }

    context.drawImage(image, 0, 0, canvas.width, canvas.height);

    return canvas;
};

const compressedPhotoBlob = async (canvas) => {
    let bestBlob = null;

    for (const quality of photoResizeQualities) {
        const blob = await canvasToJpegBlob(canvas, quality);

        if (!blob) {
            continue;
        }

        if (!bestBlob || blob.size < bestBlob.size) {
            bestBlob = blob;
        }

        if (blob.size <= photoResizeTargetBytes) {
            break;
        }
    }

    return bestBlob;
};

const resizedPhotoFile = async (file) => {
    const image = await loadPhotoImage(file);
    const width = image.naturalWidth || image.width;
    const height = image.naturalHeight || image.height;
    const longestSide = Math.max(width, height);

    if (!width || !height) {
        return file;
    }

    let bestBlob = null;

    for (const targetLongestSide of photoResizeTargetDimensions(longestSide)) {
        const canvas = drawPhotoToCanvas(image, width, height, targetLongestSide);

        if (!canvas) {
            continue;
        }

        const blob = await compressedPhotoBlob(canvas);

        if (!blob) {
            continue;
        }

        if (!bestBlob || blob.size < bestBlob.size) {
            bestBlob = blob;
        }

        if (blob.size <= photoResizeTargetBytes) {
            break;
        }
    }

    if (!bestBlob || bestBlob.size >= file.size) {
        return file;
    }

    try {
        return new File([bestBlob], file.name, {
            type: 'image/jpeg',
            lastModified: file.lastModified,
        });
    } catch (_error) {
        bestBlob.name = file.name;
        bestBlob.lastModified = file.lastModified;

        return bestBlob;
    }
};

const photoResizeConfirmText = (candidates) => {
    const totalSize = candidates.reduce((sum, file) => sum + file.size, 0);
    const photoLabel = candidates.length === 1 ? '1 grote JPG' : `${candidates.length} grote JPG's`;

    return {
        detail: `We bewaren hoge kwaliteit en beperken grote foto's tot max. ${photoResizeMaxDimension}px.`,
        message: `${photoLabel} boven ${formatBytes(photoResizeThresholdBytes)} gevonden (${formatBytes(totalSize)} totaal).\n\nWil je verkleinen voor sneller uploaden?`,
        summary: `${photoLabel} boven ${formatBytes(photoResizeThresholdBytes)} gevonden (${formatBytes(totalSize)} totaal).`,
    };
};

const closePhotoResizeModal = (shouldResize) => {
    if (!photoResizeModal) {
        return;
    }

    photoResizeModal.hidden = true;
    document.body.classList.remove('has-photo-resize-modal');

    if (photoResizeModalResolve) {
        photoResizeModalResolve(shouldResize);
        photoResizeModalResolve = null;
    }
};

const confirmPhotoResize = (candidates) => {
    if (candidates.length === 0) {
        return Promise.resolve(false);
    }

    const text = photoResizeConfirmText(candidates);

    if (!photoResizeModal || !photoResizeYesButton) {
        return Promise.resolve(window.confirm(text.message + '\n' + text.detail));
    }

    if (photoResizeSummary) {
        photoResizeSummary.textContent = text.summary;
    }

    if (photoResizeDetail) {
        photoResizeDetail.textContent = text.detail;
    }

    photoResizeModal.hidden = false;
    document.body.classList.add('has-photo-resize-modal');
    photoResizeYesButton.focus();

    return new Promise((resolve) => {
        photoResizeModalResolve = resolve;
    });
};

const preparePhotoFilesForUpload = async (manager, files) => {
    const candidates = photoCanResizeJpeg() ? photoResizeCandidates(files) : [];

    if (candidates.length === 0) {
        return files;
    }

    setPhotoStatus(manager, candidates.length === 1 ? 'Foto optimaliseren...' : 'Foto\'s optimaliseren...', 'busy', null);

    const candidateSet = new Set(candidates);
    const preparedFiles = [];

    for (const file of files) {
        if (!candidateSet.has(file)) {
            preparedFiles.push(file);
            continue;
        }

        try {
            preparedFiles.push(await resizedPhotoFile(file));
        } catch (_error) {
            preparedFiles.push(file);
        }
    }

    return preparedFiles;
};

const photoMessageIsDeleteFailure = (message) => {
    const normalized = message.toLowerCase();

    return normalized.includes('foto') && normalized.includes('niet verwijderd');
};

const photoMessageHasDeleteDetails = (message) => {
    const normalized = message.toLowerCase();

    if (photoMessageIsDeleteFailure(message)) {
        return false;
    }

    return (
        (normalized.includes('foto') && normalized.includes('verwijderd'))
        || normalized.includes('verwijderd uit assets')
        || normalized.includes('stond al niet meer in assets/img')
        || normalized.includes('stonden al niet meer in assets/img')
    );
};

const photoMessageIsTechnicalMediaDetail = (message) => (
    photoMessageHasDeleteDetails(message)
    || message.includes('assets/img')
    || message.includes('bestandsrechten')
);

const photoMessageIsSaveConfirmation = (message) => (
    /^(Kamer|Pagina) is bewaard\.$/.test(message)
    || message === 'Algemene instellingen zijn bewaard.'
);

const photoRequestErrorMessage = (xhr, response) => {
    if (Array.isArray(response.errors) && response.errors.length) {
        const errors = response.errors.map((message) => String(message).trim()).filter(Boolean);
        const visibleErrors = errors.filter((message) => !photoMessageIsTechnicalMediaDetail(message));

        if (visibleErrors.length > 0) {
            return visibleErrors.join(' ');
        }

        return 'De foto kon niet volledig verwijderd worden. Probeer opnieuw of contacteer je webbeheerder.';
    }

    if (xhr.status === 413) {
        return 'De upload is te groot voor deze server. Upload minder foto\'s tegelijk of verklein de bestanden.';
    }

    if (xhr.status === 401 || xhr.status === 403) {
        return 'Je beheersessie is verlopen. Meld je opnieuw aan en probeer de upload opnieuw.';
    }

    if (xhr.status >= 500) {
        return `De server kon de upload niet verwerken (HTTP ${xhr.status}). Probeer opnieuw of contacteer je webbeheerder.`;
    }

    if (xhr.status > 0) {
        return `Automatisch bewaren is mislukt (HTTP ${xhr.status}).`;
    }

    return 'De upload werd onderbroken. Controleer je verbinding en probeer opnieuw met minder of kleinere foto\'s.';
};

const photoSuccessMessage = (response, fallback, isUpload = false) => {
    const messages = Array.isArray(response.messages)
        ? response.messages.map((message) => String(message).trim()).filter(Boolean)
        : [];

    if (messages.length === 0) {
        return fallback;
    }

    const hasFailedPhotoDelete = messages.some(photoMessageIsDeleteFailure);

    if (hasFailedPhotoDelete) {
        const hasMultipleFailedDeletes = messages.some((message) => (
            message.toLowerCase().includes('foto\'s niet verwijderd')
        ));

        return hasMultipleFailedDeletes ? 'Foto\'s niet verwijderd.' : 'Foto niet verwijderd.';
    }

    const hasDeletedPhoto = messages.some(photoMessageHasDeleteDetails);

    if (hasDeletedPhoto) {
        const hasMultipleDeletedPhotos = messages.some((message) => (
            message.toLowerCase().includes('foto\'s verwijderd')
        ));

        return hasMultipleDeletedPhotos ? 'Foto\'s verwijderd.' : 'Foto verwijderd.';
    }

    const visibleMessages = messages.filter((message) => (
        !photoMessageIsTechnicalMediaDetail(message)
        && !photoMessageIsSaveConfirmation(message)
        && !message.includes('server')
    ));

    if (visibleMessages.length > 0) {
        return visibleMessages.join(' ');
    }

    return isUpload ? 'Foto\'s opgeslagen.' : fallback;
};

const adminAjaxUrl = (form) => {
    try {
        const url = new URL(window.location.href);
        const section = new FormData(form).get('section');

        url.hash = '';

        if (typeof section === 'string' && section !== '') {
            url.searchParams.set('section', section);
        }

        return url.toString();
    } catch (_error) {
        return form?.action || window.location.href;
    }
};

const adminCsrfToken = (form = null) => {
    const formToken = form?.querySelector?.('input[name="csrf_token"]')?.value || '';
    const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    return formToken || metaToken;
};

const hidePhotoStatus = (manager) => {
    const autosave = manager?.querySelector('[data-photo-autosave]');

    if (!autosave) {
        return;
    }

    window.clearTimeout(photoHideTimers.get(manager));
    autosave.hidden = true;
    syncAdminToastOffset();
};

const setPhotoStatus = (manager, message, state = 'busy', progress = null, showProgress = false) => {
    const autosave = manager?.querySelector('[data-photo-autosave]');
    const status = manager?.querySelector('[data-photo-status]');
    const progressTrack = manager?.querySelector('[data-photo-progress-track]');
    const progressBar = manager?.querySelector('[data-photo-progress]');
    const progressValue = manager?.querySelector('[data-photo-progress-value]');

    if (!autosave || !status || !progressTrack || !progressBar || !progressValue) {
        return;
    }

    window.clearTimeout(photoHideTimers.get(manager));
    autosave.hidden = false;
    autosave.classList.toggle('is-error', state === 'error');
    status.textContent = message;
    const progressLine = progressTrack.closest('.photo-progress-line');

    progressLine?.toggleAttribute('hidden', !showProgress);
    progressTrack.classList.toggle('is-indeterminate', progress === null && state === 'busy');

    const normalizedProgress = typeof progress === 'number' ? Math.max(0, Math.min(100, progress)) : null;

    if (normalizedProgress !== null) {
        progressBar.style.width = `${normalizedProgress}%`;
        progressValue.textContent = `${Math.round(normalizedProgress)}%`;
    } else if (state === 'success') {
        progressBar.style.width = '100%';
        progressValue.textContent = '100%';
    } else if (state === 'busy') {
        progressBar.style.width = '0%';
        progressValue.textContent = '...';
    } else {
        progressValue.textContent = '';
    }

    syncAdminToastOffset();

    if (state === 'success') {
        const timer = window.setTimeout(() => {
            hidePhotoStatus(manager);
        }, saveFeedbackVisibleMs);

        photoHideTimers.set(manager, timer);
    }
};

// Manual close support for upload/autosave feedback cards.
document.addEventListener('click', (event) => {
    const closeButton = event.target.closest('[data-photo-status-close]');

    if (!closeButton) {
        return;
    }

    hidePhotoStatus(closeButton.closest('[data-photo-manager]'));
});

document.querySelectorAll('.admin-layout form').forEach((form) => {
    form.addEventListener('input', (event) => {
        if (isTrackableControl(event.target)) {
            markUnsavedChanges(event.target);
        }
    });

    form.addEventListener('change', (event) => {
        if (isTrackableControl(event.target)) {
            markUnsavedChanges(event.target);
        }
    });

    form.addEventListener('submit', (event) => {
        if (formHasActivePhotoSave(form)) {
            event.preventDefault();
            return;
        }

        isSubmittingForm = true;
        clearUnsavedChanges();
    });
});

document.addEventListener('click', (event) => {
    const link = event.target.closest('a[href]');
    const navigationUrl = navigationUrlFromLink(link);

    if (!navigationUrl || !hasUnsavedChanges || isSubmittingForm || event.defaultPrevented) {
        return;
    }

    event.preventDefault();

    if (!showUnsavedModal(navigationUrl)) {
        clearUnsavedChanges();
        window.location.href = navigationUrl;
    }
});

unsavedSaveButton?.addEventListener('click', () => {
    const formToSave = lastDirtyForm || Array.from(dirtyForms).pop() || null;
    const targetUrl = pendingNavigationUrl;

    pendingNavigationUrl = '';
    pendingNavigationHandler = null;
    hideUnsavedModal();

    if (!formToSave) {
        clearUnsavedChanges();
        return;
    }

    const redirectInput = setRedirectAfterSave(formToSave, targetUrl);

    if (typeof formToSave.requestSubmit === 'function') {
        formToSave.requestSubmit();
        window.setTimeout(() => redirectInput?.remove(), 0);
        return;
    }

    isSubmittingForm = true;
    clearUnsavedChanges(formToSave);
    formToSave.submit();
});

unsavedStayButton?.addEventListener('click', () => {
    pendingNavigationUrl = '';
    pendingNavigationHandler = null;
    hideUnsavedModal();
});

unsavedModal?.addEventListener('click', (event) => {
    if (!event.target.matches('.unsaved-modal-backdrop')) {
        return;
    }

    pendingNavigationUrl = '';
    pendingNavigationHandler = null;
    hideUnsavedModal();
});

unsavedLeaveButton?.addEventListener('click', () => {
    const targetUrl = pendingNavigationUrl;
    const targetHandler = pendingNavigationHandler;

    clearUnsavedChanges();
    hideUnsavedModal();

    if (targetHandler) {
        targetHandler();
    } else if (targetUrl !== '') {
        window.location.href = targetUrl;
    }
});

photoResizeYesButton?.addEventListener('click', () => closePhotoResizeModal(true));

photoResizeModal?.querySelectorAll('[data-photo-resize-no]').forEach((element) => {
    element.addEventListener('click', () => closePhotoResizeModal(false));
});

const closeBackgroundFocusModal = () => {
    if (!backgroundFocusModal) {
        return;
    }

    backgroundFocusModal.hidden = true;
    document.body.classList.remove('has-background-focus-modal');
    activeBackgroundFocusCard = null;
    activeBackgroundFocusManager = null;
};

backgroundFocusModal?.querySelectorAll('[data-background-focus-close]').forEach((element) => {
    element.addEventListener('click', closeBackgroundFocusModal);
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && backgroundFocusModal && !backgroundFocusModal.hidden) {
        closeBackgroundFocusModal();
        return;
    }

    if (event.key === 'Escape' && photoResizeModal && !photoResizeModal.hidden) {
        closePhotoResizeModal(false);
        return;
    }

    if (event.key === 'Escape' && unsavedModal && !unsavedModal.hidden) {
        pendingNavigationUrl = '';
        pendingNavigationHandler = null;
        hideUnsavedModal();
        return;
    }

    const key = event.key.toLowerCase();
    const isRefreshShortcut = event.key === 'F5' || ((event.ctrlKey || event.metaKey) && key === 'r');

    if (!isRefreshShortcut || !hasUnsavedChanges || isSubmittingForm || event.defaultPrevented) {
        return;
    }

    event.preventDefault();

    if (!showUnsavedModal('', () => window.location.reload())) {
        clearUnsavedChanges();
        window.location.reload();
    }
});

if (window.history && typeof window.history.pushState === 'function') {
    const guardedHistoryState = Object.assign({}, window.history.state || {}, { adminUnsavedGuard: true });

    try {
        window.history.replaceState(guardedHistoryState, '', window.location.href);
        window.history.pushState(guardedHistoryState, '', window.location.href);
    } catch (_error) {
        // History state can be unavailable in unusual browser/privacy modes.
    }

    window.addEventListener('popstate', () => {
        if (isSubmittingForm) {
            return;
        }

        if (!hasUnsavedChanges) {
            window.history.back();
            return;
        }

        try {
            window.history.pushState(guardedHistoryState, '', window.location.href);
        } catch (_error) {
            // If re-adding the guard fails, keep the user on the page and still
            // show the custom modal for the action they attempted.
        }

        if (!showUnsavedModal('', () => window.history.back())) {
            clearUnsavedChanges();
            window.history.back();
        }
    });
}

// Toasts are used for regular form saves; photo uploads use the same duration
// but keep their own progress state. All visible toasts share one bottom-right
// stack so messages never overlap.
const adminToasts = Array.from(document.querySelectorAll('[data-admin-toast]'));
let adminToastSequence = 0;
const adminToastOrder = new WeakMap();
const adminToastGap = () => {
    const value = Number.parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--admin-toast-gap'));

    return Number.isFinite(value) && value >= 0 ? value : 12;
};

const adminToastElements = () => Array.from(new Set([
    ...adminToasts,
    ...document.querySelectorAll('[data-photo-autosave]'),
]));

const syncAdminToastOffset = () => {
    const visibleToasts = [];

    adminToastElements().forEach((toast) => {
        if (!toast.isConnected || toast.hidden) {
            adminToastOrder.delete(toast);
            toast.style.removeProperty('--admin-toast-stack-offset');
            return;
        }

        if (!adminToastOrder.has(toast)) {
            adminToastSequence += 1;
            adminToastOrder.set(toast, adminToastSequence);
        }

        visibleToasts.push(toast);
    });

    visibleToasts.sort((a, b) => (adminToastOrder.get(a) || 0) - (adminToastOrder.get(b) || 0));

    let offset = 0;
    const gap = adminToastGap();

    visibleToasts.forEach((toast) => {
        toast.style.setProperty('--admin-toast-stack-offset', `${offset}px`);
        offset += toast.getBoundingClientRect().height + gap;
    });

    document.documentElement.style.setProperty('--admin-toast-offset', `${offset}px`);
};

adminToasts.forEach((toast) => {
    let hideTimer = 0;
    let isHovering = false;
    const openedAt = Date.now();

    const clearHideTimer = () => {
        if (hideTimer === 0) {
            return;
        }

        window.clearTimeout(hideTimer);
        hideTimer = 0;
    };

    const hideToast = () => {
        clearHideTimer();
        toast.hidden = true;
        syncAdminToastOffset();
    };

    const scheduleHideToast = () => {
        if (!toast.hasAttribute('data-admin-toast-autohide') || toast.hidden) {
            return;
        }

        clearHideTimer();

        const elapsed = Date.now() - openedAt;
        const delay = Math.max(0, saveFeedbackVisibleMs - elapsed);

        hideTimer = window.setTimeout(() => {
            hideTimer = 0;

            if (isHovering) {
                return;
            }

            hideToast();
        }, delay);
    };

    toast.querySelectorAll('[data-admin-toast-close]').forEach((button) => {
        button.addEventListener('click', hideToast);
    });

    if (toast.hasAttribute('data-admin-toast-autohide')) {
        toast.addEventListener('mouseenter', () => {
            isHovering = true;
            clearHideTimer();
        });

        toast.addEventListener('mouseleave', () => {
            isHovering = false;
            scheduleHideToast();
        });

        scheduleHideToast();
    }
});

syncAdminToastOffset();
window.addEventListener('resize', syncAdminToastOffset);

const showAdminNotice = (message, state = 'success', autoHide = false) => {
    const stack = document.createElement('div');
    const item = document.createElement('div');
    const text = document.createElement('span');
    const close = document.createElement('button');

    stack.className = `message-stack is-toast${state === 'error' ? ' is-error' : ''}`;
    stack.setAttribute('data-admin-toast', '');
    item.className = `message is-${state === 'error' ? 'error' : 'success'}`;
    close.type = 'button';
    close.className = 'message-close';
    close.setAttribute('aria-label', 'Melding sluiten');
    close.textContent = '\u00d7';
    text.textContent = message;
    item.append(text, close);
    stack.append(item);
    document.body.append(stack);
    adminToasts.push(stack);
    syncAdminToastOffset();

    const hide = () => {
        stack.hidden = true;
        stack.remove();
        const index = adminToasts.indexOf(stack);

        if (index !== -1) {
            adminToasts.splice(index, 1);
        }

        syncAdminToastOffset();
    };

    close.addEventListener('click', hide);

    if (autoHide) {
        window.setTimeout(hide, saveFeedbackVisibleMs);
    }

    return stack;
};

if (adminSessionTimeoutMs > 0) {
    const sessionWarningLeadMs = Math.min(5 * 60 * 1000, Math.max(60 * 1000, Math.floor(adminSessionTimeoutMs / 4)));

    window.setInterval(() => {
        const remainingMs = adminSessionTimeoutMs - (Date.now() - adminSessionLastRefreshAt);

        if (remainingMs <= 0) {
            if (!adminSessionExpiredShown) {
                adminSessionExpiredShown = true;
                showAdminNotice(
                    hasUnsavedChanges
                        ? 'Je beheersessie is waarschijnlijk verlopen. Meld opnieuw aan in een nieuw tabblad voordat je verder werkt.'
                        : 'Je beheersessie is waarschijnlijk verlopen. Meld opnieuw aan om verder te werken.',
                    'error'
                );
            }

            return;
        }

        if (remainingMs <= sessionWarningLeadMs && !adminSessionWarningShown) {
            adminSessionWarningShown = true;
            const minutes = Math.max(1, Math.ceil(remainingMs / 60000));
            showAdminNotice(`Je beheersessie verloopt over ongeveer ${minutes} minuten. Bewaar je wijzigingen tijdig.`, 'error');
        }
    }, 30000);
}

document.querySelectorAll('[data-select-on-focus]').forEach((input) => {
    input.addEventListener('focus', () => input.select());
    input.addEventListener('click', () => input.select());
});

// Rich-text editor with a strict HTML subset and explicit toolbar commands.
const escapeHtml = (value) => value
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');

const decodeHtmlEntities = (value) => {
    const textarea = document.createElement('textarea');
    textarea.innerHTML = value;

    return textarea.value;
};

const decodeStoredRichText = (value) => {
    let decoded = value;

    for (let index = 0; index < 4 && /&(?:lt|gt|quot|apos|amp);/i.test(decoded); index += 1) {
        const next = decodeHtmlEntities(decoded);

        if (next === decoded) {
            break;
        }

        decoded = next;
    }

    return decoded;
};

const richAllowedTags = new Set(['A', 'B', 'BR', 'DIV', 'EM', 'FONT', 'I', 'LI', 'OL', 'P', 'SPAN', 'STRONG', 'U', 'UL']);
const richAllowedColor = /^(#[0-9a-f]{3,8}|[a-z]+|rgba?\([\d\s.,%]+\))$/i;

const unwrapElement = (element) => {
    const parent = element.parentNode;

    if (!parent) {
        return;
    }

    while (element.firstChild) {
        parent.insertBefore(element.firstChild, element);
    }

    parent.removeChild(element);
};

const normalizedRichColor = (value) => {
    const color = (value || '').trim();

    return richAllowedColor.test(color) ? color : '';
};

const normalizedRichHref = (value) => {
    const trimmed = (value || '').trim();

    if (trimmed === '') {
        return '';
    }

    if (/^(https?:|mailto:|tel:|\/|#)/i.test(trimmed)) {
        return trimmed;
    }

    return /^\S+$/i.test(trimmed) ? `https://${trimmed}` : '';
};

const richColorFromElement = (element) => {
    const styleColor = (element.getAttribute('style') || '').match(/(?:^|;)\s*color\s*:\s*([^;]+)/i)?.[1] || '';
    const attributeColor = element.getAttribute('color') || '';

    return normalizedRichColor(styleColor) || normalizedRichColor(attributeColor);
};

const styleWithoutColor = (styleValue) => styleValue
    .split(';')
    .map((rule) => rule.trim())
    .filter((rule) => rule !== '' && !/^color\s*:/i.test(rule))
    .join('; ');

const clearElementColor = (element) => {
    if (!(element instanceof Element)) {
        return;
    }

    const cleanedStyle = styleWithoutColor(element.getAttribute('style') || '');

    if (cleanedStyle === '') {
        element.removeAttribute('style');
    } else {
        element.setAttribute('style', cleanedStyle);
    }

    element.removeAttribute('color');

    if (element.tagName === 'SPAN' && element.attributes.length === 0) {
        unwrapElement(element);
    }
};

const sanitizeRichHtml = (html) => {
    const template = document.createElement('template');
    template.innerHTML = html;

    Array.from(template.content.querySelectorAll('*')).forEach((element) => {
        if (['SCRIPT', 'STYLE'].includes(element.tagName)) {
            element.remove();
            return;
        }

        if (!richAllowedTags.has(element.tagName)) {
            unwrapElement(element);
            return;
        }

        if (element.tagName === 'A') {
            const href = normalizedRichHref(element.getAttribute('href') || '');

            Array.from(element.attributes).forEach((attribute) => {
                element.removeAttribute(attribute.name);
            });

            if (href !== '') {
                element.setAttribute('href', href);
            } else {
                unwrapElement(element);
            }

            return;
        }

        if (element.tagName === 'FONT') {
            const color = richColorFromElement(element);
            const span = document.createElement('span');

            if (color !== '') {
                span.setAttribute('style', `color: ${color}`);
            }

            while (element.firstChild) {
                span.append(element.firstChild);
            }

            element.replaceWith(span);

            if (span.attributes.length === 0) {
                unwrapElement(span);
            }

            return;
        }

        if (element.tagName === 'SPAN') {
            const color = richColorFromElement(element);

            Array.from(element.attributes).forEach((attribute) => {
                element.removeAttribute(attribute.name);
            });

            if (color !== '') {
                element.setAttribute('style', `color: ${color}`);
            } else if (element.attributes.length === 0) {
                unwrapElement(element);
            }

            return;
        }

        Array.from(element.attributes).forEach((attribute) => {
            element.removeAttribute(attribute.name);
        });
    });

    let sanitized = template.innerHTML;
    sanitized = sanitized.replace(/<(\/?)b\b[^>]*>/gi, '<$1strong>');
    sanitized = sanitized.replace(/<(\/?)i\b[^>]*>/gi, '<$1em>');
    sanitized = sanitized.replace(/<br\b[^>]*>/gi, '<br>');

    return sanitized;
};

const richTextHasHtml = (value) => /<\/?(a|b|strong|u|span|font|br|em|i|div|p|ul|ol|li)\b/i.test(value);
const richTextBlockLine = (line) => /^\s*<(div|p|ul|ol)\b/i.test(line);

const richLineToHtml = (line) => {
    const decodedLine = decodeStoredRichText(line);

    return richTextHasHtml(decodedLine) ? sanitizeRichHtml(decodedLine) : escapeHtml(decodedLine);
};

const textareaValueToRichHtml = (value) => {
    const decodedValue = decodeStoredRichText(value).replace(/\r\n?/g, '\n');

    if (decodedValue.trim() === '') {
        return '<div><br></div>';
    }

    return decodedValue
        .split('\n')
        .map((line) => {
            const trimmedLine = line.trim();

            if (trimmedLine === '') {
                return '<div><br></div>';
            }

            const html = richTextBlockLine(trimmedLine)
                ? sanitizeRichHtml(trimmedLine)
                : `<div>${richLineToHtml(line)}</div>`;

            return html.trim() === '' ? '<div><br></div>' : html;
        })
        .join('');
};

const richEditorValue = (editor) => {
    const lines = [];
    const template = document.createElement('template');
    template.innerHTML = sanitizeRichHtml(editor.innerHTML).replace(/\u200B/g, '');

    template.content.childNodes.forEach((node) => {
        if (node.nodeType === Node.TEXT_NODE) {
            const text = node.textContent?.trim() || '';

            if (text !== '') {
                lines.push(escapeHtml(text));
            }

            return;
        }

        if (node.nodeType !== Node.ELEMENT_NODE) {
            return;
        }

        const tag = node.tagName;

        if (tag === 'DIV' || tag === 'P') {
            const html = sanitizeRichHtml(node.innerHTML).replace(/^<br>$/i, '').trim();

            if (html !== '') {
                lines.push(html);
            }

            return;
        }

        if (tag === 'UL' || tag === 'OL') {
            const html = sanitizeRichHtml(node.outerHTML).trim();

            if (html !== '') {
                lines.push(html);
            }

            return;
        }

        const html = sanitizeRichHtml(node.outerHTML || node.textContent || '').trim();

        if (html !== '') {
            lines.push(html);
        }
    });

    return lines.join('\n').trim();
};

document.querySelectorAll('.admin-layout form').forEach((form) => {
    form.addEventListener('submit', () => {
        form.querySelectorAll('textarea[data-rich-text]').forEach((textarea) => {
            const editor = textarea.closest('.rich-text')?.querySelector('.rich-text-editable');

            if (editor) {
                textarea.value = richEditorValue(editor);
            }
        });
    });
});

const linkModal = document.querySelector('[data-link-modal]');
const linkForm = document.querySelector('[data-link-form]');
const linkInput = document.querySelector('[data-link-input]');
const linkText = document.querySelector('[data-link-text]');
let currentEditorContext = null;

const closeLinkModal = () => {
    if (!linkModal) {
        return;
    }

    linkModal.hidden = true;
    document.body.classList.remove('has-link-modal');
    currentEditorContext?.restoreSelection?.();
    currentEditorContext?.editor?.focus();
    currentEditorContext = null;
};

if (linkModal && linkForm && linkInput && linkText) {
    linkForm.addEventListener('submit', (event) => {
        event.preventDefault();

        if (!currentEditorContext) {
            return;
        }

        const href = normalizedRichHref(linkInput.value);

        if (href === '') {
            linkInput.focus();
            return;
        }

        const manualText = linkText.value.trim();
        const displayText = manualText || currentEditorContext.selectedText() || href;

        currentEditorContext.insertLink(href, displayText, manualText !== '');
        closeLinkModal();
    });

    document.querySelectorAll('[data-link-modal-close]').forEach((element) => {
        element.addEventListener('click', closeLinkModal);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !linkModal.hidden) {
            closeLinkModal();
        }
    });
}

const technicalModal = document.querySelector('[data-technical-modal]');
const technicalModalOpen = document.querySelector('[data-technical-modal-open]');
const technicalHelpToggle = document.querySelector('[data-technical-help-toggle]');
const technicalHelp = document.querySelector('[data-technical-help]');

const closeTechnicalModal = () => {
    if (!technicalModal) {
        return;
    }

    technicalModal.hidden = true;
    document.body.classList.remove('has-technical-modal');
};

if (technicalModal && technicalModalOpen) {
    technicalModalOpen.addEventListener('click', () => {
        technicalModal.hidden = false;
        document.body.classList.add('has-technical-modal');
        technicalModal.querySelector('form input, form select, form textarea, form button')?.focus();
    });

    technicalHelpToggle?.addEventListener('click', () => {
        if (!technicalHelp) {
            return;
        }

        const isOpening = technicalHelp.hidden;

        technicalHelp.hidden = !isOpening;
        technicalHelpToggle.setAttribute('aria-expanded', String(isOpening));
    });

    technicalModal.querySelectorAll('[data-technical-modal-close]').forEach((element) => {
        element.addEventListener('click', closeTechnicalModal);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !technicalModal.hidden) {
            closeTechnicalModal();
        }
    });
}

document.querySelectorAll('textarea[data-rich-text]').forEach((textarea) => {
    const wrapper = document.createElement('div');
    const toolbar = document.createElement('div');
    const editor = document.createElement('div');
    const colorInput = document.createElement('input');
    const commandButtons = {};
    let colorClearButton = null;
    let savedRange = null;
    let modalSavedRange = null;
    let internalEditorCommandDepth = 0;

    wrapper.className = 'rich-text';
    toolbar.className = 'rich-text-toolbar';
    editor.className = 'rich-text-editable';
    editor.contentEditable = 'true';
    editor.spellcheck = true;
    editor.innerHTML = textareaValueToRichHtml(textarea.value);
    editor.style.minHeight = `${Math.max(Number(textarea.getAttribute('rows') || 3) * 26, 92)}px`;

    textarea.classList.add('rich-text-source');
    textarea.before(wrapper);
    // Keep the hidden textarea first in DOM order because this wrapper lives
    // inside <label> elements. Otherwise the first toolbar button becomes the
    // label target and clicks in empty editor space can fire bold unexpectedly.
    wrapper.append(textarea, toolbar, editor);

    const syncTextarea = (trackChanges = true) => {
        textarea.value = richEditorValue(editor);

        if (!trackChanges) {
            return;
        }

        markUnsavedChanges(textarea);
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
    };

    const withInternalEditorCommand = (callback) => {
        internalEditorCommandDepth += 1;

        try {
            return callback();
        } finally {
            internalEditorCommandDepth -= 1;
        }
    };

    const selectionRange = () => {
        const selection = window.getSelection();

        if (!selection || selection.rangeCount === 0) {
            return null;
        }

        const range = selection.getRangeAt(0);

        return editor.contains(range.commonAncestorContainer) ? range : null;
    };

    const selectionInsideEditor = () => selectionRange() !== null;
    const selectionHasTextRange = () => {
        const range = selectionRange();

        return Boolean(range && !range.collapsed && range.toString().trim() !== '');
    };

    const saveSelection = () => {
        const range = selectionRange();

        if (!range) {
            const linkModalOpenForEditor = Boolean(
                linkModal
                && !linkModal.hidden
                && currentEditorContext
                && currentEditorContext.editor === editor
            );

            if (document.activeElement !== editor && !linkModalOpenForEditor) {
                savedRange = null;
            }
            return;
        }

        savedRange = range.cloneRange();
    };

    const placeCaretAtEnd = () => {
        const range = document.createRange();
        range.selectNodeContents(editor);
        range.collapse(false);
        const selection = window.getSelection();
        selection?.removeAllRanges();
        selection?.addRange(range);
        savedRange = range.cloneRange();
    };

    const restoreSelection = () => {
        editor.focus();

        if (savedRange && editor.contains(savedRange.commonAncestorContainer)) {
            const selection = window.getSelection();
            selection?.removeAllRanges();
            selection?.addRange(savedRange.cloneRange());
        } else {
            placeCaretAtEnd();
        }

        return selectionRange();
    };

    const restoreSpecificRange = (rangeToRestore) => {
        editor.focus();

        if (rangeToRestore && editor.contains(rangeToRestore.commonAncestorContainer)) {
            const selection = window.getSelection();
            const clone = rangeToRestore.cloneRange();
            selection?.removeAllRanges();
            selection?.addRange(clone);
            savedRange = clone.cloneRange();

            return selectionRange();
        }

        return restoreSelection();
    };

    const selectedText = () => selectionRange()?.toString().trim() || '';

    const selectedLinkNode = () => {
        const range = selectionRange();

        if (!range) {
            return null;
        }

        let node = range.startContainer.nodeType === Node.ELEMENT_NODE
            ? range.startContainer
            : range.startContainer.parentElement;

        while (node && node !== editor) {
            if (node.tagName === 'A') {
                return node;
            }

            node = node.parentElement;
        }

        if (!range.collapsed) {
            const matchingLink = Array.from(editor.querySelectorAll('a[href]'))
                .find((link) => range.intersectsNode(link));

            if (matchingLink) {
                return matchingLink;
            }
        }

        return null;
    };

    const selectedLinkHref = () => selectedLinkNode()?.getAttribute('href') || '';

    const runEditorCommand = (command, value = null, options = {}) => {
        const range = restoreSelection();

        if (!range) {
            return;
        }

        if (options.requireTextSelection && !selectionHasTextRange()) {
            updateToolbarState();
            return;
        }

        withInternalEditorCommand(() => {
            document.execCommand('styleWithCSS', false, false);
            document.execCommand(command, false, value);
        });
        saveSelection();
        syncTextarea();
        updateToolbarState();
        editor.focus();
    };

    const clearColorFromSelection = () => {
        const range = restoreSelection();

        if (!range) {
            return;
        }

        if (range.collapsed) {
            let node = range.startContainer.nodeType === Node.ELEMENT_NODE
                ? range.startContainer
                : range.startContainer.parentElement;

            while (node && node !== editor) {
                if (normalizedRichColor(richColorFromElement(node)) !== '') {
                    clearElementColor(node);
                    break;
                }

                node = node.parentElement;
            }
        } else {
            const fragment = range.extractContents();
            const fragmentElements = Array.from(fragment.querySelectorAll('*'));
            fragmentElements.forEach((element) => clearElementColor(element));

            const insertedNodes = Array.from(fragment.childNodes);
            range.insertNode(fragment);

            if (insertedNodes.length > 0) {
                const caretRange = document.createRange();
                caretRange.setStartAfter(insertedNodes[insertedNodes.length - 1]);
                caretRange.collapse(true);
                const selection = window.getSelection();
                selection?.removeAllRanges();
                selection?.addRange(caretRange);
                savedRange = caretRange.cloneRange();
            } else {
                saveSelection();
            }
        }

        syncTextarea();
        updateToolbarState();
        editor.focus();
    };

    const updateToolbarState = () => {
        const hasSelection = selectionInsideEditor();
        const hasTextSelection = selectionHasTextRange();
        const setActive = (key, active) => {
            const button = commandButtons[key];

            if (!button) {
                return;
            }

            button.classList.toggle('is-active', active);
        };

        if (!hasSelection) {
            setActive('bold', false);
            setActive('underline', false);
            setActive('bullets', false);

            if (commandButtons.bold) {
                commandButtons.bold.disabled = true;
            }

            if (commandButtons.underline) {
                commandButtons.underline.disabled = true;
            }

            if (commandButtons.bullets) {
                commandButtons.bullets.disabled = false;
            }

            if (commandButtons.unlink) {
                commandButtons.unlink.disabled = true;
            }

            if (colorClearButton) {
                colorClearButton.disabled = true;
            }

            colorInput.disabled = true;
            return;
        }

        setActive('bold', hasTextSelection && document.queryCommandState('bold'));
        setActive('underline', hasTextSelection && document.queryCommandState('underline'));
        setActive('bullets', document.queryCommandState('insertUnorderedList'));

        if (commandButtons.bold) {
            commandButtons.bold.disabled = !hasTextSelection;
        }

        if (commandButtons.underline) {
            commandButtons.underline.disabled = !hasTextSelection;
        }

        colorInput.disabled = !hasTextSelection;

        if (commandButtons.unlink) {
            commandButtons.unlink.disabled = selectedLinkNode() === null;
        }

        if (colorClearButton) {
            colorClearButton.disabled = !hasTextSelection;
        }
    };

    const addToolbarButton = (label, title, handler, key = '') => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'rich-text-button';
        button.textContent = label;
        button.title = title;
        button.addEventListener('mousedown', (event) => {
            event.preventDefault();
            saveSelection();
        });
        button.addEventListener('click', (event) => {
            event.preventDefault();
            handler();
        });
        toolbar.append(button);

        if (key !== '') {
            commandButtons[key] = button;
        }

        return button;
    };

    addToolbarButton('B', 'Vet', () => runEditorCommand('bold', null, { requireTextSelection: true }), 'bold');
    addToolbarButton('U', 'Onderlijnen', () => runEditorCommand('underline', null, { requireTextSelection: true }), 'underline');
    addToolbarButton('Bullets', 'Bulletlijst', () => runEditorCommand('insertUnorderedList'), 'bullets');

    addToolbarButton('Link', 'Link invoegen', () => {
        if (!linkModal || !linkInput || !linkText) {
            return;
        }

        saveSelection();
        modalSavedRange = savedRange ? savedRange.cloneRange() : null;

        currentEditorContext = {
            editor,
            restoreSelection: () => restoreSpecificRange(modalSavedRange),
            selectedText,
            insertLink: (href, text, forceText = false) => {
                const range = restoreSpecificRange(modalSavedRange);

                if (!range) {
                    return;
                }

                if (forceText || range.collapsed) {
                    range.deleteContents();
                    const textNode = document.createTextNode(text || href);
                    range.insertNode(textNode);

                    const linkRange = document.createRange();
                    linkRange.selectNodeContents(textNode);
                    const selection = window.getSelection();
                    selection?.removeAllRanges();
                    selection?.addRange(linkRange);
                    savedRange = linkRange.cloneRange();
                }

                withInternalEditorCommand(() => {
                    document.execCommand('createLink', false, href);
                });
                saveSelection();
                modalSavedRange = savedRange ? savedRange.cloneRange() : null;
                syncTextarea();
                updateToolbarState();
                editor.focus();
            },
        };

        linkInput.value = selectedLinkHref();
        linkText.value = selectedText();
        linkModal.hidden = false;
        document.body.classList.add('has-link-modal');
        window.setTimeout(() => linkInput.focus(), 0);
    });

    addToolbarButton('Link weg', 'Link verwijderen', () => runEditorCommand('unlink'), 'unlink');

    colorInput.type = 'color';
    colorInput.className = 'rich-text-color';
    colorInput.title = 'Tekstkleur';
    colorInput.value = '#161616';
    colorInput.addEventListener('mousedown', () => {
        saveSelection();
    });
    colorInput.addEventListener('input', () => {
        const color = normalizedRichColor(colorInput.value);

        if (color === '' || !selectionHasTextRange()) {
            return;
        }

        runEditorCommand('foreColor', color, { requireTextSelection: true });
    });
    toolbar.append(colorInput);

    colorClearButton = addToolbarButton('Kleur uit', 'Tekstkleur verwijderen', clearColorFromSelection);

    editor.addEventListener('input', () => {
        syncTextarea();
        updateToolbarState();
    });
    editor.addEventListener('beforeinput', (event) => {
        if (!event.inputType?.startsWith('format')) {
            return;
        }

        if (internalEditorCommandDepth > 0) {
            return;
        }

        // Keep formatting deterministic: only toolbar actions may format text.
        event.preventDefault();
    });
    editor.addEventListener('keyup', () => {
        saveSelection();
        updateToolbarState();
    });
    editor.addEventListener('mouseup', () => {
        saveSelection();
        updateToolbarState();
    });
    editor.addEventListener('focus', () => {
        saveSelection();
        updateToolbarState();
    });
    editor.addEventListener('mousedown', (event) => {
        if (event.target !== editor) {
            return;
        }

        window.setTimeout(() => {
            const range = selectionRange();

            if (!range) {
                placeCaretAtEnd();
            } else {
                saveSelection();
            }

            updateToolbarState();
        }, 0);
    });
    editor.addEventListener('blur', saveSelection);
    editor.addEventListener('paste', (event) => {
        event.preventDefault();
        restoreSelection();
        const text = event.clipboardData?.getData('text/plain') || '';
        withInternalEditorCommand(() => {
            document.execCommand('insertText', false, text);
        });
        saveSelection();
        syncTextarea();
        updateToolbarState();
    });

    document.addEventListener('selectionchange', () => {
        if (selectionInsideEditor()) {
            saveSelection();
        }

        updateToolbarState();
    });

    syncTextarea(false);
    updateToolbarState();
});

// Photo grids autosave ordering, captions, deletes and uploads. Each manager is
// independent so the same code works for Leuven and all room galleries.
const autoSavePhotoManager = (manager, options = {}) => new Promise((resolve) => {
    const form = manager?.closest('form');

    if (!manager || !form) {
        resolve({ success: false });
        return;
    }

    window.clearTimeout(photoSaveTimers.get(manager));

    const previousRequest = photoRequests.get(manager);

    if (previousRequest) {
        previousRequest.abort();
    }

    const xhr = new XMLHttpRequest();
    const formData = new FormData(form);
    const isUpload = options.upload === true;
    const reloadAfterSuccess = options.reloadAfterSuccess === true;
    const uploadFiles = Array.isArray(options.files) ? options.files : [];
    const uploadName = options.uploadName || '';
    const statusMessage = options.statusMessage || (isUpload ? 'Foto\'s uploaden...' : 'Foto\'s opslaan...');
    const showBusyStatus = options.showBusyStatus !== false;
    const showProgress = Object.prototype.hasOwnProperty.call(options, 'showProgress')
        ? options.showProgress !== false
        : isUpload;
    const progressStart = typeof options.progressStart === 'number' ? Math.max(0, Math.min(100, options.progressStart)) : 0;
    const progressEnd = typeof options.progressEnd === 'number' ? Math.max(0, Math.min(100, options.progressEnd)) : 100;
    const progressRange = Math.max(0, progressEnd - progressStart);

    if (isUpload && uploadName !== '') {
        formData.delete(uploadName);
        uploadFiles.forEach((file) => formData.append(uploadName, file, file.name));

        if (options.appendOnly === true) {
            removePhotoManagerFields(formData, manager);
        }
    }

    photoRequests.set(manager, xhr);
    setPhotoSaveActive(manager, true);

    if (showBusyStatus) {
        setPhotoStatus(manager, statusMessage, 'busy', isUpload ? progressStart : null, showProgress);
    }

    xhr.upload.addEventListener('progress', (event) => {
        if (!isUpload || !event.lengthComputable) {
            return;
        }

        setPhotoStatus(manager, statusMessage, 'busy', progressStart + ((event.loaded / event.total) * progressRange), showProgress);
    });

    xhr.upload.addEventListener('load', () => {
        if (!isUpload) {
            return;
        }

        setPhotoStatus(manager, statusMessage.replace('uploaden', 'verwerken'), 'busy', progressEnd, showProgress);
    });

    xhr.addEventListener('load', () => {
        if (!finishPhotoRequest(manager, xhr)) {
            resolve({ success: false, ignored: true });
            return;
        }

        let response = { success: xhr.status >= 200 && xhr.status < 300 };

        try {
            response = JSON.parse(xhr.responseText);
        } catch (_error) {
            response.success = xhr.status >= 200 && xhr.status < 300;
        }

        if (response === null || typeof response !== 'object' || Array.isArray(response)) {
            response = { success: xhr.status >= 200 && xhr.status < 300 };
        }

        if (xhr.status >= 200 && xhr.status < 300 && response.success !== false) {
            refreshAdminSessionTimer();

            const successMessage = photoSuccessMessage(
                response,
                options.successMessage || 'Foto\'s opgeslagen.',
                isUpload
            );

            if (options.deferSuccessStatus !== true) {
                setPhotoStatus(manager, successMessage, 'success', 100, showProgress);
            }

            clearUnsavedChanges(form);

            if (reloadAfterSuccess) {
                window.setTimeout(() => window.location.reload(), 850);
            }

            resolve({ success: true, response });
            return;
        }

        const errorMessage = photoRequestErrorMessage(xhr, response);

        setPhotoStatus(manager, errorMessage, 'error', 100, showProgress);
        resolve({ success: false, response, error: errorMessage, status: xhr.status });
    });

    xhr.addEventListener('error', () => {
        if (!finishPhotoRequest(manager, xhr)) {
            resolve({ success: false, ignored: true });
            return;
        }

        const errorMessage = photoRequestErrorMessage(xhr, {});
        setPhotoStatus(manager, errorMessage, 'error', 100, showProgress);
        resolve({ success: false, error: errorMessage, status: xhr.status });
    });

    xhr.addEventListener('timeout', () => {
        if (!finishPhotoRequest(manager, xhr)) {
            resolve({ success: false, ignored: true });
            return;
        }

        const errorMessage = 'De server reageert te traag op de upload. Probeer opnieuw met minder of kleinere foto\'s.';
        setPhotoStatus(manager, errorMessage, 'error', 100, showProgress);
        resolve({ success: false, error: errorMessage, timeout: true });
    });

    xhr.addEventListener('abort', () => {
        const wasCurrent = finishPhotoRequest(manager, xhr);
        resolve({ success: false, aborted: wasCurrent });
    });

    xhr.open((form.method || 'POST').toUpperCase(), adminAjaxUrl(form), true);
    xhr.timeout = options.timeoutMs || photoUploadTimeoutMs;
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.setRequestHeader('X-CSRF-Token', adminCsrfToken(form));
    xhr.send(formData);
});

const uploadPhotoFiles = async (manager, files, uploadName) => {
    const form = manager?.closest('form');

    if (!manager || !form || !files.length) {
        return;
    }

    files = await preparePhotoFilesForUpload(manager, files);

    if (!validatePhotoFiles(manager, files)) {
        return;
    }

    const totalBytes = files.reduce((sum, file) => sum + file.size, 0);
    let uploadedBytes = 0;

    for (let index = 0; index < files.length; index += 1) {
        const file = files[index];
        const progressStart = totalBytes > 0 ? (uploadedBytes / totalBytes) * 100 : (index / files.length) * 100;
        const progressEnd = totalBytes > 0
            ? ((uploadedBytes + file.size) / totalBytes) * 100
            : ((index + 1) / files.length) * 100;
        const statusMessage = files.length > 1
            ? `Foto ${index + 1}/${files.length} uploaden...`
            : 'Foto\'s uploaden...';

        const result = await autoSavePhotoManager(manager, {
            appendOnly: true,
            deferSuccessStatus: true,
            files: [file],
            progressEnd,
            progressStart,
            showProgress: true,
            statusMessage,
            upload: true,
            uploadName,
        });

        if (!result.success) {
            return;
        }

        uploadedBytes += file.size;
    }

    const message = files.length === 1
        ? 'Foto opgeslagen.'
        : 'Foto\'s opgeslagen.';

    setPhotoStatus(manager, message, 'success', 100, true);
    clearUnsavedChanges(form);
    window.setTimeout(() => window.location.reload(), 850);
};

const schedulePhotoAutosave = (manager, options = {}) => {
    if (!manager) {
        return;
    }

    window.clearTimeout(photoSaveTimers.get(manager));
    setPhotoSaveActive(manager, true);

    const timer = window.setTimeout(() => {
        autoSavePhotoManager(manager, options);
    }, options.delay ?? 450);

    photoSaveTimers.set(manager, timer);
};

const photoOrderSignature = (grid) => Array.from(grid.querySelectorAll('[data-photo-card]:not(.is-marked-delete) input[name$="[file][]"]'))
    .map((input) => input.value)
    .join('|');

const backgroundPageCheckboxes = (card) => Array.from(card?.querySelectorAll('[data-background-page]') || []);

const syncBackgroundAllCheckbox = (card) => {
    const all = card?.querySelector('[data-background-all]');
    const pages = backgroundPageCheckboxes(card);

    if (!all || pages.length === 0) {
        return;
    }

    const checkedCount = pages.filter((input) => input.checked).length;
    all.checked = checkedCount === pages.length;
    all.indeterminate = checkedCount > 0 && checkedCount < pages.length;
};

const setBackgroundPageCheckboxes = (card, checked) => {
    backgroundPageCheckboxes(card).forEach((input) => {
        input.checked = checked;
    });

    syncBackgroundAllCheckbox(card);
};

const backgroundFocusValue = (value) => {
    const number = Number.parseFloat(value);

    return Number.isFinite(number) ? Math.max(0, Math.min(100, number)) : 50;
};

const backgroundFocusPositionText = (x, y) => `${x.toFixed(2).replace(/\.?0+$/, '')}% ${y.toFixed(2).replace(/\.?0+$/, '')}%`;

const isBackgroundFocusCard = (card) => card?.querySelector('[data-background-display]')?.value === 'cover-focus';

const syncBackgroundFocusCardState = (card) => {
    card?.classList.toggle('has-background-focus-trigger', isBackgroundFocusCard(card));
};

const syncBackgroundFocusFields = (card) => {
    const inputX = card?.querySelector('[data-background-focus-x]');
    const inputY = card?.querySelector('[data-background-focus-y]');

    if (!inputX || !inputY) {
        return;
    }

    const x = backgroundFocusValue(inputX.value);
    const y = backgroundFocusValue(inputY.value);

    inputX.value = x.toFixed(2).replace(/\.?0+$/, '');
    inputY.value = y.toFixed(2).replace(/\.?0+$/, '');
};

const syncBackgroundFocusModal = () => {
    const card = activeBackgroundFocusCard;
    const inputX = card?.querySelector('[data-background-focus-x]');
    const inputY = card?.querySelector('[data-background-focus-y]');
    const display = card?.querySelector('[data-background-display]');

    if (!backgroundFocusModalPicker || !backgroundFocusModalImage || !backgroundFocusModalPreview || !inputX || !inputY) {
        return;
    }

    const x = backgroundFocusValue(inputX.value);
    const y = backgroundFocusValue(inputY.value);
    const option = display?.selectedOptions?.[0] || null;
    const isFocusMode = display?.value === 'cover-focus';
    const backgroundSize = option?.dataset.backgroundSize || 'cover';
    const backgroundPosition = isFocusMode
        ? backgroundFocusPositionText(x, y)
        : option?.dataset.backgroundPosition || 'center center';

    backgroundFocusModalPreview.style.backgroundImage = `url("${backgroundFocusModalImage.src}")`;
    backgroundFocusModalPreview.style.backgroundSize = backgroundSize;
    backgroundFocusModalPreview.style.backgroundPosition = backgroundPosition;
    backgroundFocusModalPicker.style.setProperty('--focus-x', `${x}%`);
    backgroundFocusModalPicker.style.setProperty('--focus-y', `${y}%`);

    const marker = backgroundFocusModalPicker.querySelector('.background-focus-marker');
    const pickerBox = backgroundFocusModalPicker.getBoundingClientRect();
    const imageBox = backgroundFocusModalImage.getBoundingClientRect();

    if (marker && pickerBox.width > 0 && pickerBox.height > 0 && imageBox.width > 0 && imageBox.height > 0) {
        marker.style.left = `${(imageBox.left - pickerBox.left) + ((imageBox.width * x) / 100)}px`;
        marker.style.top = `${(imageBox.top - pickerBox.top) + ((imageBox.height * y) / 100)}px`;
    }

    if (backgroundFocusModalValue) {
        backgroundFocusModalValue.textContent = `${Math.round(x)}% / ${Math.round(y)}%`;
    }
};

const openBackgroundFocusModal = (card, manager) => {
    const display = card?.querySelector('[data-background-display]');
    const imageUrl = display?.dataset.backgroundFocusImage || '';

    if (!backgroundFocusModal || !backgroundFocusModalImage || !display || imageUrl === '') {
        return;
    }

    activeBackgroundFocusCard = card;
    activeBackgroundFocusManager = manager;
    backgroundFocusModalImage.src = imageUrl;
    backgroundFocusModal.hidden = false;
    document.body.classList.add('has-background-focus-modal');
    syncBackgroundFocusFields(card);

    window.requestAnimationFrame(() => {
        syncBackgroundFocusModal();
        backgroundFocusModalPicker?.focus();
    });
};

const setBackgroundFocusPoint = (card, clientX, clientY) => {
    const image = backgroundFocusModalImage;
    const box = image?.getBoundingClientRect();
    const inputX = card?.querySelector('[data-background-focus-x]');
    const inputY = card?.querySelector('[data-background-focus-y]');
    const display = card?.querySelector('[data-background-display]');

    if (!card || !box || box.width <= 0 || box.height <= 0 || !inputX || !inputY) {
        return;
    }

    const x = backgroundFocusValue(((clientX - box.left) / box.width) * 100);
    const y = backgroundFocusValue(((clientY - box.top) / box.height) * 100);

    inputX.value = x.toFixed(2).replace(/\.?0+$/, '');
    inputY.value = y.toFixed(2).replace(/\.?0+$/, '');

    if (display?.querySelector('option[value="cover-focus"]')) {
        display.value = 'cover-focus';
    }

    syncBackgroundFocusFields(card);
    syncBackgroundFocusModal();
};

const syncBackgroundMenuLayer = (menu) => {
    menu?.closest('[data-photo-card]')?.classList.toggle('has-open-background-menu', menu.open);
};

const backgroundMenuAutosaveOptions = {
    showBusyStatus: false,
    showProgress: false,
    successMessage: 'Opgeslagen.',
};

backgroundFocusModalImage?.addEventListener('load', syncBackgroundFocusModal);
window.addEventListener('resize', () => {
    if (backgroundFocusModal && !backgroundFocusModal.hidden) {
        syncBackgroundFocusModal();
    }
});

backgroundFocusModalPicker?.addEventListener('pointerdown', (event) => {
    if (!activeBackgroundFocusCard) {
        return;
    }

    event.preventDefault();
    setBackgroundFocusPoint(activeBackgroundFocusCard, event.clientX, event.clientY);
    markUnsavedChanges(activeBackgroundFocusCard);
    schedulePhotoAutosave(activeBackgroundFocusManager, backgroundMenuAutosaveOptions);
});

document.querySelectorAll('[data-photo-grid]').forEach((grid) => {
    let draggedCard = null;
    let dragStartOrder = '';
    const manager = grid.closest('[data-photo-manager]');

    const findSiblingCard = (card, direction) => {
        let sibling = direction === 'up' ? card?.previousElementSibling : card?.nextElementSibling;

        while (sibling && !sibling.matches('[data-photo-card]')) {
            sibling = direction === 'up' ? sibling.previousElementSibling : sibling.nextElementSibling;
        }

        return sibling;
    };

    const moveCard = (card, direction) => {
        if (!card) {
            return false;
        }

        const sibling = findSiblingCard(card, direction);

        if (direction === 'up' && sibling) {
            grid.insertBefore(card, sibling);
            return true;
        }

        if (direction === 'down' && sibling) {
            grid.insertBefore(card, sibling.nextElementSibling);
            return true;
        }

        return false;
    };

    grid.addEventListener('dragstart', (event) => {
        const card = event.target.closest('[data-photo-card]');

        if (!card || event.target.closest('[data-background-menu]')) {
            event.preventDefault();
            return;
        }

        draggedCard = card;
        dragStartOrder = photoOrderSignature(grid);
        card.classList.add('is-dragging');
        event.dataTransfer.effectAllowed = 'move';
    });

    grid.addEventListener('dragend', () => {
        draggedCard?.classList.remove('is-dragging');
        draggedCard = null;

        if (dragStartOrder !== '' && dragStartOrder !== photoOrderSignature(grid)) {
            markUnsavedChanges(manager);
            schedulePhotoAutosave(manager);
        }

        dragStartOrder = '';
    });

    grid.addEventListener('dragover', (event) => {
        event.preventDefault();

        const target = event.target.closest('[data-photo-card]');

        if (!draggedCard || !target || target === draggedCard) {
            return;
        }

        const targetBox = target.getBoundingClientRect();
        const placeAfter = event.clientY > targetBox.top + (targetBox.height / 2);
        grid.insertBefore(draggedCard, placeAfter ? target.nextSibling : target);
    });

    grid.addEventListener('click', (event) => {
        const card = event.target.closest('[data-photo-card]');
        const thumb = event.target.closest('.photo-thumb');

        if (event.target.closest('[data-background-menu]')) {
            event.stopPropagation();
        }

        if (thumb && card && isBackgroundFocusCard(card)) {
            event.preventDefault();
            openBackgroundFocusModal(card, manager);
            return;
        }

        if (event.target.closest('[data-photo-up]')) {
            if (moveCard(card, 'up')) {
                markUnsavedChanges(manager);
                schedulePhotoAutosave(manager);
            }
        }

        if (event.target.closest('[data-photo-down]')) {
            if (moveCard(card, 'down')) {
                markUnsavedChanges(manager);
                schedulePhotoAutosave(manager);
            }
        }

        const deleteToggle = event.target.closest('[data-photo-delete-toggle]');
        const deleteYes = event.target.closest('[data-photo-delete-yes]');
        const deleteNo = event.target.closest('[data-photo-delete-no]');

        if (deleteToggle) {
            const deleteBox = deleteToggle.closest('[data-photo-delete]');
            const confirm = deleteBox?.querySelector('[data-photo-delete-confirm]');

            grid.querySelectorAll('[data-photo-delete-confirm]').forEach((otherConfirm) => {
                if (otherConfirm !== confirm) {
                    otherConfirm.hidden = true;
                }
            });

            if (confirm) {
                confirm.hidden = !confirm.hidden;
            }
        }

        if (deleteNo) {
            deleteNo.closest('[data-photo-delete-confirm]').hidden = true;
        }

        if (deleteYes) {
            const deleteBox = deleteYes.closest('[data-photo-delete]');
            const deleteInput = deleteBox?.querySelector('[data-photo-delete-input]');

            if (deleteInput) {
                deleteInput.disabled = false;
            }

            card?.classList.add('is-marked-delete');
            markUnsavedChanges(manager);
            schedulePhotoAutosave(manager, { delay: 80 });
        }
    });

    grid.addEventListener('change', (event) => {
        const card = event.target.closest('[data-photo-card]');

        if (event.target.matches('[data-background-all]')) {
            setBackgroundPageCheckboxes(card, event.target.checked);
            markUnsavedChanges(event.target);
            schedulePhotoAutosave(manager, backgroundMenuAutosaveOptions);
            return;
        }

        if (event.target.matches('[data-background-page]')) {
            syncBackgroundAllCheckbox(card);
            markUnsavedChanges(event.target);
            schedulePhotoAutosave(manager, backgroundMenuAutosaveOptions);
            return;
        }

        if (event.target.matches('[data-background-display]')) {
            syncBackgroundFocusFields(card);
            syncBackgroundFocusCardState(card);
            if (card === activeBackgroundFocusCard) {
                syncBackgroundFocusModal();
            }
            markUnsavedChanges(event.target);
            schedulePhotoAutosave(manager, backgroundMenuAutosaveOptions);
            if (event.target.value === 'cover-focus') {
                openBackgroundFocusModal(card, manager);
            }
            return;
        }

        if (event.target.matches('input[name$="[title][]"]')) {
            markUnsavedChanges(event.target);
            schedulePhotoAutosave(manager);
        }
    });

    grid.querySelectorAll('[data-photo-card]').forEach((card) => {
        syncBackgroundAllCheckbox(card);
        syncBackgroundFocusFields(card);
        syncBackgroundFocusCardState(card);
    });
    grid.querySelectorAll('[data-background-menu]').forEach((menu) => {
        syncBackgroundMenuLayer(menu);
        menu.addEventListener('toggle', () => {
            if (menu.open) {
                grid.querySelectorAll('[data-background-menu]').forEach((otherMenu) => {
                    if (otherMenu !== menu) {
                        otherMenu.open = false;
                        syncBackgroundMenuLayer(otherMenu);
                    }
                });
            }

            syncBackgroundMenuLayer(menu);
        });
    });
});

document.querySelectorAll('[data-photo-drop-zone]').forEach((dropZone) => {
    const input = dropZone.querySelector('[data-photo-input]');
    const label = dropZone.querySelector('[data-photo-drop-label]');
    const manager = dropZone.closest('[data-photo-manager]');
    const defaultLabel = label?.textContent || "Foto's toevoegen";

    if (!input) {
        return;
    }

    const updateLabel = (countOverride = null) => {
        if (!label) {
            return;
        }

        const count = countOverride ?? input.files?.length ?? 0;
        label.textContent = count > 0 ? `${count} foto${count === 1 ? '' : "'s"} geselecteerd` : defaultLabel;
    };

    const openFilePicker = () => {
        input.click();
    };

    dropZone.addEventListener('click', (event) => {
        if (event.target === input) {
            return;
        }

        event.preventDefault();
        openFilePicker();
    });

    dropZone.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }

        event.preventDefault();
        openFilePicker();
    });

    ['dragenter', 'dragover'].forEach((eventName) => {
        dropZone.addEventListener(eventName, (event) => {
            event.preventDefault();
            dropZone.classList.add('is-drag-over');
        });
    });

    ['dragleave', 'drop'].forEach((eventName) => {
        dropZone.addEventListener(eventName, () => {
            dropZone.classList.remove('is-drag-over');
        });
    });

    dropZone.addEventListener('drop', (event) => {
        event.preventDefault();

        const files = Array.from(event.dataTransfer?.files || []).filter((file) => (
            file.type.startsWith('image/')
            || /\.(jpe?g|png|gif|webp)$/i.test(file.name || '')
        ));

        if (!files.length) {
            return;
        }

        updateLabel(files.length);
        markUnsavedChanges(dropZone);
        uploadPhotoFiles(manager, files, input.name);
    });

    input.addEventListener('change', () => {
        const files = Array.from(input.files || []);
        updateLabel(files.length);

        if (files.length) {
            markUnsavedChanges(input);
            uploadPhotoFiles(manager, files, input.name);
        }
    });
});

const resizeLanguageTabs = (tabs) => {
    if (!tabs) {
        return;
    }

    const panelHolder = tabs.querySelector('[data-language-panels]');
    const panels = Array.from(tabs.querySelectorAll('[data-language-panel]'));

    if (!panelHolder || !panels.length) {
        return;
    }

    const originalStates = panels.map((panel) => ({
        hidden: panel.hidden,
        style: panel.getAttribute('style'),
    }));
    let maxHeight = 0;
    const width = panelHolder.clientWidth;

    panels.forEach((panel) => {
        panel.hidden = false;
        panel.style.position = 'absolute';
        panel.style.visibility = 'hidden';
        panel.style.pointerEvents = 'none';
        panel.style.width = `${width}px`;
        maxHeight = Math.max(maxHeight, panel.scrollHeight);
    });

    panels.forEach((panel, index) => {
        panel.hidden = originalStates[index].hidden;

        if (originalStates[index].style === null) {
            panel.removeAttribute('style');
        } else {
            panel.setAttribute('style', originalStates[index].style);
        }
    });

    panelHolder.style.minHeight = `${maxHeight}px`;
};

document.querySelectorAll('[data-language-tabs]').forEach((tabs) => {
    const tabButtons = Array.from(tabs.querySelectorAll('[data-language-tab]'));
    const panels = Array.from(tabs.querySelectorAll('[data-language-panel]'));

    resizeLanguageTabs(tabs);

    tabButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const language = button.dataset.languageTab;

            tabButtons.forEach((tabButton) => {
                tabButton.setAttribute('aria-selected', String(tabButton === button));
            });

            panels.forEach((panel) => {
                panel.hidden = panel.dataset.languagePanel !== language;
            });

            resizeLanguageTabs(tabs);
        });
    });

    tabs.addEventListener('input', () => resizeLanguageTabs(tabs));
});

window.addEventListener('resize', () => {
    document.querySelectorAll('[data-language-tabs]').forEach(resizeLanguageTabs);
});

// Repeatable admin controls for room facilities and links.
document.querySelectorAll('[data-list-editor]').forEach((editor) => {
    const items = editor.querySelector('[data-list-items]');
    const template = editor.querySelector('[data-list-template]');
    const addButton = editor.querySelector('[data-list-add]');
    const languageTabs = editor.closest('[data-language-tabs]');

    const addRow = () => {
        if (!items || !template) {
            return;
        }

        const fragment = template.content.cloneNode(true);
        const input = fragment.querySelector('input');

        items.appendChild(fragment);
        input?.focus();
        markUnsavedChanges(editor);
        resizeLanguageTabs(languageTabs);
    };

    addButton?.addEventListener('click', addRow);

    editor.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-list-remove]');

        if (!removeButton) {
            return;
        }

        removeButton.closest('[data-list-row]')?.remove();
        markUnsavedChanges(editor);
        resizeLanguageTabs(languageTabs);
    });
});

document.querySelectorAll('[data-price-editor]').forEach((editor) => {
    const list = editor.querySelector('[data-price-list]');
    const template = editor.querySelector('[data-price-template]');
    const addButton = editor.querySelector('[data-price-add]');
    const languageTabs = editor.closest('[data-language-tabs]');

    const addRow = () => {
        if (!list || !template) {
            return;
        }

        const fragment = template.content.cloneNode(true);
        const input = fragment.querySelector('input');

        list.appendChild(fragment);
        input?.focus();
        markUnsavedChanges(editor);
        resizeLanguageTabs(languageTabs);
    };

    addButton?.addEventListener('click', addRow);

    editor.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-price-remove]');

        if (!removeButton || !list) {
            return;
        }

        const row = removeButton.closest('[data-price-row]');
        const rows = Array.from(list.querySelectorAll('[data-price-row]'));

        if (!row) {
            return;
        }

        if (rows.length <= 1) {
            row.querySelectorAll('input').forEach((input) => {
                input.value = '';
            });
            row.querySelector('input')?.focus();
        } else {
            row.remove();
        }

        markUnsavedChanges(editor);
        resizeLanguageTabs(languageTabs);
    });
});

document.querySelectorAll('[data-link-section-editor]').forEach((editor) => {
    const list = editor.querySelector('[data-link-row-list]');
    const template = editor.querySelector('[data-link-row-template]');
    const addButton = editor.querySelector('[data-link-row-add]');
    const languageTabs = editor.closest('[data-language-tabs]');

    addButton?.addEventListener('click', () => {
        if (!list || !template) {
            return;
        }

        const fragment = template.content.cloneNode(true);
        const input = fragment.querySelector('input');

        list.appendChild(fragment);
        input?.focus();
        markUnsavedChanges(editor);
        resizeLanguageTabs(languageTabs);
    });

    editor.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-link-row-remove]');

        if (!removeButton) {
            return;
        }

        removeButton.closest('[data-link-row]')?.remove();
        markUnsavedChanges(editor);
        resizeLanguageTabs(languageTabs);
    });
});

document.querySelectorAll('[data-logo-manager]').forEach((manager) => {
    // Single-image tile picker used for logo and favicon: click or Edit opens
    // the file picker, Delete marks the current file for removal on save.
    const currentInput = manager.querySelector('[data-logo-current]');
    const removeInput = manager.querySelector('[data-logo-remove-input]');
    const removeButton = manager.querySelector('[data-logo-remove]');
    const editButton = manager.querySelector('[data-logo-edit]');
    const tile = manager.querySelector('[data-logo-tile]');
    const preview = manager.querySelector('[data-logo-preview]');
    const previewImage = manager.querySelector('[data-logo-preview-image]');
    const previewName = manager.querySelector('[data-logo-preview-name]');
    const empty = manager.querySelector('[data-logo-empty]');
    const fileInput = manager.querySelector('[data-logo-input]');

    const showPreview = (file) => {
        if (!file) {
            return;
        }

        if (removeInput) {
            removeInput.value = '0';
        }

        tile?.classList.remove('is-empty');

        if (previewImage) {
            previewImage.src = URL.createObjectURL(file);
            previewImage.hidden = false;
        }

        if (previewName) {
            previewName.textContent = file.name;
            previewName.hidden = false;
        }

        if (preview) {
            preview.hidden = false;
        }

        if (empty) {
            empty.hidden = true;
        }

        if (removeButton) {
            removeButton.hidden = false;
        }
    };

    const openLogoPicker = () => {
        fileInput?.click();
    };

    removeButton?.addEventListener('click', () => {
        if (currentInput) {
            currentInput.value = '';
        }

        if (removeInput) {
            removeInput.value = '1';
        }

        if (fileInput) {
            fileInput.value = '';
        }

        if (preview) {
            preview.hidden = false;
        }

        if (previewImage) {
            previewImage.hidden = true;
            previewImage.removeAttribute('src');
        }

        if (previewName) {
            previewName.textContent = '';
            previewName.hidden = true;
        }

        if (empty) {
            empty.hidden = false;
        }

        if (removeButton) {
            removeButton.hidden = true;
        }

        tile?.classList.add('is-empty');
        markUnsavedChanges(manager);
    });

    tile?.addEventListener('click', (event) => {
        if (event.target.closest('button') || event.target === fileInput) {
            return;
        }

        event.preventDefault();
        openLogoPicker();
    });

    tile?.addEventListener('keydown', (event) => {
        if (event.target.closest('button') || event.target === fileInput) {
            return;
        }

        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }

        event.preventDefault();
        openLogoPicker();
    });

    editButton?.addEventListener('click', (event) => {
        event.preventDefault();
        openLogoPicker();
    });

    fileInput?.addEventListener('change', () => {
        const file = fileInput.files?.[0];

        if (!file) {
            return;
        }

        showPreview(file);
        markUnsavedChanges(fileInput);
    });
});

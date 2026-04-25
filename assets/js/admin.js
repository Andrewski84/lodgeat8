const photoRequests = new WeakMap();
const photoSaveTimers = new WeakMap();
const photoHideTimers = new WeakMap();

const setPhotoStatus = (manager, message, state = 'busy', progress = null) => {
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

    if (state === 'success') {
        const timer = window.setTimeout(() => {
            autosave.hidden = true;
        }, 1600);

        photoHideTimers.set(manager, timer);
    }
};

const autoSavePhotoManager = (manager, options = {}) => {
    const form = manager?.closest('form');

    if (!manager || !form) {
        return;
    }

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

    if (isUpload && uploadName !== '') {
        formData.delete(uploadName);
        uploadFiles.forEach((file) => formData.append(uploadName, file, file.name));
    }

    photoRequests.set(manager, xhr);
    setPhotoStatus(manager, isUpload ? 'Foto\'s uploaden...' : 'Foto\'s automatisch bewaren...', 'busy', isUpload ? 0 : null);

    xhr.upload.addEventListener('progress', (event) => {
        if (!isUpload || !event.lengthComputable) {
            return;
        }

        setPhotoStatus(manager, 'Foto\'s uploaden...', 'busy', (event.loaded / event.total) * 100);
    });

    xhr.addEventListener('load', () => {
        if (photoRequests.get(manager) !== xhr) {
            return;
        }

        photoRequests.delete(manager);

        let response = { success: xhr.status >= 200 && xhr.status < 300 };

        try {
            response = JSON.parse(xhr.responseText);
        } catch (_error) {
            response.success = xhr.status >= 200 && xhr.status < 300;
        }

        if (xhr.status >= 200 && xhr.status < 300 && response.success !== false) {
            setPhotoStatus(manager, isUpload ? 'Upload opgeslagen. Foto’s laden...' : 'Automatisch opgeslagen.', 'success', 100);

            if (reloadAfterSuccess) {
                window.setTimeout(() => window.location.reload(), 850);
            }

            return;
        }

        const errorMessage = Array.isArray(response.errors) && response.errors.length
            ? response.errors.join(' ')
            : 'Automatisch bewaren is mislukt.';

        setPhotoStatus(manager, errorMessage, 'error', 100);
    });

    xhr.addEventListener('error', () => {
        photoRequests.delete(manager);
        setPhotoStatus(manager, 'Automatisch bewaren is mislukt.', 'error', 100);
    });

    xhr.addEventListener('abort', () => {
        if (photoRequests.get(manager) === xhr) {
            photoRequests.delete(manager);
        }
    });

    xhr.open((form.method || 'POST').toUpperCase(), form.action || window.location.href, true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.send(formData);
};

const schedulePhotoAutosave = (manager, options = {}) => {
    if (!manager) {
        return;
    }

    window.clearTimeout(photoSaveTimers.get(manager));

    const timer = window.setTimeout(() => {
        autoSavePhotoManager(manager, options);
    }, options.delay ?? 450);

    photoSaveTimers.set(manager, timer);
};

const photoOrderSignature = (grid) => Array.from(grid.querySelectorAll('[data-photo-card]:not(.is-marked-delete) input[name$="[file][]"]'))
    .map((input) => input.value)
    .join('|');

document.querySelectorAll('[data-photo-grid]').forEach((grid) => {
    let draggedCard = null;
    let dragStartOrder = '';
    const manager = grid.closest('[data-photo-manager]');

    const moveCard = (card, direction) => {
        if (!card) {
            return false;
        }

        if (direction === 'up' && card.previousElementSibling) {
            grid.insertBefore(card, card.previousElementSibling);
            return true;
        }

        if (direction === 'down' && card.nextElementSibling) {
            grid.insertBefore(card.nextElementSibling, card);
            return true;
        }

        return false;
    };

    grid.addEventListener('dragstart', (event) => {
        const card = event.target.closest('[data-photo-card]');

        if (!card) {
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

        if (event.target.closest('[data-photo-up]')) {
            if (moveCard(card, 'up')) {
                schedulePhotoAutosave(manager);
            }
        }

        if (event.target.closest('[data-photo-down]')) {
            if (moveCard(card, 'down')) {
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
            schedulePhotoAutosave(manager, { delay: 80 });
        }
    });

    grid.addEventListener('change', (event) => {
        if (event.target.matches('input[name$="[title][]"]')) {
            schedulePhotoAutosave(manager);
        }
    });
});

document.querySelectorAll('[data-photo-drop-zone]').forEach((dropZone) => {
    const input = dropZone.querySelector('[data-photo-input]');
    const label = dropZone.querySelector('[data-photo-drop-label]');
    const manager = dropZone.closest('[data-photo-manager]');

    if (!input) {
        return;
    }

    const updateLabel = (countOverride = null) => {
        if (!label) {
            return;
        }

        const count = countOverride ?? input.files?.length ?? 0;
        label.textContent = count > 0 ? `${count} foto${count === 1 ? '' : "'s"} geselecteerd` : "Sleep foto's hierheen";
    };

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

        const files = Array.from(event.dataTransfer?.files || []).filter((file) => file.type.startsWith('image/'));

        if (!files.length) {
            return;
        }

        updateLabel(files.length);
        autoSavePhotoManager(manager, {
            files,
            upload: true,
            uploadName: input.name,
            reloadAfterSuccess: true,
        });
    });

    input.addEventListener('change', () => {
        const files = Array.from(input.files || []);
        updateLabel(files.length);

        if (files.length) {
            autoSavePhotoManager(manager, {
                files,
                upload: true,
                uploadName: input.name,
                reloadAfterSuccess: true,
            });
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
        resizeLanguageTabs(languageTabs);
    };

    addButton?.addEventListener('click', addRow);

    editor.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-list-remove]');

        if (!removeButton) {
            return;
        }

        removeButton.closest('[data-list-row]')?.remove();
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
        resizeLanguageTabs(languageTabs);
    });

    editor.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-link-row-remove]');

        if (!removeButton) {
            return;
        }

        removeButton.closest('[data-link-row]')?.remove();
        resizeLanguageTabs(languageTabs);
    });
});

document.querySelectorAll('[data-logo-manager]').forEach((manager) => {
    const currentInput = manager.querySelector('[data-logo-current]');
    const removeInput = manager.querySelector('[data-logo-remove-input]');
    const removeButton = manager.querySelector('[data-logo-remove]');
    const preview = manager.querySelector('[data-logo-preview]');
    const previewImage = manager.querySelector('[data-logo-preview-image]');
    const previewName = manager.querySelector('[data-logo-preview-name]');
    const empty = manager.querySelector('[data-logo-empty]');
    const fileInput = manager.querySelector('[data-logo-input]');

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
            preview.hidden = true;
        }

        if (previewImage) {
            previewImage.hidden = true;
            previewImage.removeAttribute('src');
        }

        if (previewName) {
            previewName.textContent = '';
        }

        if (empty) {
            empty.hidden = false;
        }
    });

    fileInput?.addEventListener('change', () => {
        const hasFile = Boolean(fileInput.files?.[0]);

        if (removeInput) {
            removeInput.value = '0';
        }

        if (previewImage && hasFile) {
            previewImage.src = URL.createObjectURL(fileInput.files[0]);
            previewImage.hidden = false;
        }

        if (previewName && hasFile) {
            previewName.textContent = fileInput.files[0].name;
        }

        if (preview && hasFile) {
            preview.hidden = false;
        }

        if (empty && hasFile) {
            empty.hidden = true;
        }
    });
});

const photoRequests = new WeakMap();
const photoSaveTimers = new WeakMap();
const photoHideTimers = new WeakMap();
const saveFeedbackVisibleMs = 2000;

// Shared dirty-state handling. Any editable admin form can reveal its save bar
// and trigger the unsaved-changes modal before internal navigation.
let hasUnsavedChanges = false;
let pendingNavigationUrl = '';
let isSubmittingForm = false;
const dirtyForms = new Set();
let lastDirtyForm = null;

const unsavedModal = document.querySelector('[data-unsaved-modal]');
const unsavedSaveButton = unsavedModal?.querySelector('[data-unsaved-save]');
const unsavedStayButton = unsavedModal?.querySelector('button[data-unsaved-stay]');
const unsavedLeaveButton = unsavedModal?.querySelector('[data-unsaved-leave]');

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

        return;
    }

    dirtyForms.forEach((dirtyForm) => dirtyForm.classList.remove('has-unsaved-changes'));
    dirtyForms.clear();
    lastDirtyForm = null;
    hasUnsavedChanges = false;
    pendingNavigationUrl = '';
};

const showUnsavedModal = (url) => {
    if (!unsavedModal) {
        return false;
    }

    pendingNavigationUrl = url;
    unsavedModal.hidden = false;
    document.body.classList.add('has-unsaved-modal');
    unsavedSaveButton?.focus();

    return true;
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

const isInternalAdminUrl = (href) => {
    let url;

    try {
        url = new URL(href, window.location.href);
    } catch (_error) {
        return false;
    }

    if (url.origin !== window.location.origin) {
        return false;
    }

    const currentPath = window.location.pathname.replace(/\\/g, '/');
    const targetPath = url.pathname.replace(/\\/g, '/');

    return currentPath.includes('/beheer/')
        && (targetPath.includes('/beheer/') || targetPath === currentPath);
};

const hidePhotoStatus = (manager) => {
    const autosave = manager?.querySelector('[data-photo-autosave]');

    if (!autosave) {
        return;
    }

    window.clearTimeout(photoHideTimers.get(manager));
    autosave.hidden = true;
};

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

    form.addEventListener('submit', () => {
        isSubmittingForm = true;
        clearUnsavedChanges();
    });
});

document.addEventListener('click', (event) => {
    const link = event.target.closest('a[href]');

    if (!link || !hasUnsavedChanges || isSubmittingForm || event.defaultPrevented) {
        return;
    }

    if (link.target && link.target !== '_self') {
        return;
    }

    if (!isInternalAdminUrl(link.href)) {
        return;
    }

    event.preventDefault();

    if (!showUnsavedModal(link.href)) {
        clearUnsavedChanges();
        window.location.href = link.href;
    }
});

unsavedSaveButton?.addEventListener('click', () => {
    const formToSave = lastDirtyForm || Array.from(dirtyForms).pop() || null;

    pendingNavigationUrl = '';
    hideUnsavedModal();

    if (!formToSave) {
        clearUnsavedChanges();
        return;
    }

    if (typeof formToSave.requestSubmit === 'function') {
        formToSave.requestSubmit();
        return;
    }

    isSubmittingForm = true;
    clearUnsavedChanges(formToSave);
    formToSave.submit();
});

unsavedStayButton?.addEventListener('click', () => {
    pendingNavigationUrl = '';
    hideUnsavedModal();
});

unsavedModal?.addEventListener('click', (event) => {
    if (!event.target.matches('.unsaved-modal-backdrop')) {
        return;
    }

    pendingNavigationUrl = '';
    hideUnsavedModal();
});

unsavedLeaveButton?.addEventListener('click', () => {
    const targetUrl = pendingNavigationUrl;
    clearUnsavedChanges();
    hideUnsavedModal();

    if (targetUrl !== '') {
        window.location.href = targetUrl;
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && unsavedModal && !unsavedModal.hidden) {
        pendingNavigationUrl = '';
        hideUnsavedModal();
    }
});

window.addEventListener('beforeunload', (event) => {
    if (!hasUnsavedChanges || isSubmittingForm) {
        return;
    }

    event.preventDefault();
    event.returnValue = '';
});

// Toasts are used for regular form saves; photo uploads use the same duration
// but keep their own progress state.
document.querySelectorAll('[data-admin-toast]').forEach((toast) => {
    const hideToast = () => {
        toast.hidden = true;
    };

    toast.querySelectorAll('[data-admin-toast-close]').forEach((button) => {
        button.addEventListener('click', hideToast);
    });

    if (toast.hasAttribute('data-admin-toast-autohide')) {
        window.setTimeout(hideToast, saveFeedbackVisibleMs);
    }
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
            clearUnsavedChanges(form);

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
        if (event.target.matches('input[name$="[title][]"]')) {
            markUnsavedChanges(event.target);
            schedulePhotoAutosave(manager);
        }
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

        const files = Array.from(event.dataTransfer?.files || []).filter((file) => file.type.startsWith('image/'));

        if (!files.length) {
            return;
        }

        updateLabel(files.length);
        markUnsavedChanges(dropZone);
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
            markUnsavedChanges(input);
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
    // Logo management is intentionally a single-image tile: click or Edit opens
    // the file picker, Delete marks the current logo for removal on save.
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

const backgroundSlides = Array.from(document.querySelectorAll('.background-slide'));
let backgroundIndex = 0;

if (backgroundSlides.length > 1) {
    window.setInterval(() => {
        backgroundSlides[backgroundIndex].classList.remove('is-visible');
        backgroundIndex = (backgroundIndex + 1) % backgroundSlides.length;
        backgroundSlides[backgroundIndex].classList.add('is-visible');
    }, 8000);
}

document.querySelectorAll('[data-gallery]').forEach((gallery) => {
    const imageButtons = Array.from(gallery.querySelectorAll('[data-lightbox-trigger]'));
    const images = imageButtons.map((button) => button.querySelector('img')).filter(Boolean);
    const prev = gallery.querySelector('[data-gallery-prev]');
    const next = gallery.querySelector('[data-gallery-next]');
    let index = 0;

    const show = (nextIndex) => {
        if (!images.length) {
            return;
        }

        imageButtons[index]?.classList.remove('is-visible');
        index = (nextIndex + images.length) % images.length;
        imageButtons[index]?.classList.add('is-visible');
    };

    prev?.addEventListener('click', () => show(index - 1));
    next?.addEventListener('click', () => show(index + 1));

    if (images.length > 1) {
        window.setInterval(() => show(index + 1), 5000);
    }
});

const lightbox = document.createElement('div');
lightbox.className = 'lightbox';
lightbox.hidden = true;
lightbox.innerHTML = `
    <button type="button" class="lightbox-button lightbox-close" data-lightbox-close aria-label="Sluiten">&times;</button>
    <button type="button" class="lightbox-button lightbox-prev" data-lightbox-prev aria-label="Vorige foto">&lsaquo;</button>
    <figure class="lightbox-figure">
        <img class="lightbox-image" data-lightbox-image alt="">
        <figcaption class="lightbox-caption" data-lightbox-caption></figcaption>
    </figure>
    <button type="button" class="lightbox-button lightbox-next" data-lightbox-next aria-label="Volgende foto">&rsaquo;</button>
`;
document.body.appendChild(lightbox);

const lightboxImage = lightbox.querySelector('[data-lightbox-image]');
const lightboxCaption = lightbox.querySelector('[data-lightbox-caption]');
let lightboxItems = [];
let lightboxIndex = 0;
let lastFocusedElement = null;
let touchStartX = 0;
let touchStartY = 0;
let lightboxCloseTimer = null;

const showLightboxImage = (nextIndex) => {
    if (!lightboxItems.length) {
        return;
    }

    lightboxIndex = (nextIndex + lightboxItems.length) % lightboxItems.length;
    const item = lightboxItems[lightboxIndex];
    lightboxImage.src = item.src;
    lightboxImage.alt = item.alt;
    lightboxCaption.textContent = item.alt;
};

const closeLightbox = () => {
    if (lightbox.hidden || lightbox.classList.contains('is-closing')) {
        return;
    }

    lightbox.classList.remove('is-open');
    lightbox.classList.add('is-closing');
    window.clearTimeout(lightboxCloseTimer);

    lightboxCloseTimer = window.setTimeout(() => {
        lightbox.hidden = true;
        lightbox.classList.remove('is-closing');
        document.body.classList.remove('has-lightbox');
        lightboxImage.removeAttribute('src');
        lastFocusedElement?.focus();
    }, 190);
};

const openLightbox = (items, startIndex, trigger) => {
    lightboxItems = items;
    lastFocusedElement = trigger;
    showLightboxImage(startIndex);
    window.clearTimeout(lightboxCloseTimer);
    lightbox.hidden = false;
    lightbox.classList.remove('is-closing');
    document.body.classList.add('has-lightbox');

    window.requestAnimationFrame(() => {
        lightbox.classList.add('is-open');
        lightbox.querySelector('[data-lightbox-close]').focus();
    });
};

document.querySelectorAll('[data-gallery]').forEach((gallery) => {
    const triggers = Array.from(gallery.querySelectorAll('[data-lightbox-trigger]'));
    const items = triggers.map((trigger) => {
        const image = trigger.querySelector('img');

        return {
            src: image?.currentSrc || image?.src || '',
            alt: image?.alt || '',
        };
    }).filter((item) => item.src !== '');

    triggers.forEach((trigger, index) => {
        trigger.addEventListener('click', () => openLightbox(items, index, trigger));
    });
});

lightbox.querySelector('[data-lightbox-close]').addEventListener('click', closeLightbox);
lightbox.querySelector('[data-lightbox-prev]').addEventListener('click', () => showLightboxImage(lightboxIndex - 1));
lightbox.querySelector('[data-lightbox-next]').addEventListener('click', () => showLightboxImage(lightboxIndex + 1));

lightbox.addEventListener('click', (event) => {
    if (event.target === lightbox) {
        closeLightbox();
    }
});

document.addEventListener('keydown', (event) => {
    if (lightbox.hidden || lightbox.classList.contains('is-closing')) {
        return;
    }

    if (event.key === 'Escape') {
        closeLightbox();
    }

    if (event.key === 'ArrowLeft') {
        showLightboxImage(lightboxIndex - 1);
    }

    if (event.key === 'ArrowRight') {
        showLightboxImage(lightboxIndex + 1);
    }
});

lightbox.addEventListener('touchstart', (event) => {
    const touch = event.changedTouches[0];
    touchStartX = touch.clientX;
    touchStartY = touch.clientY;
}, { passive: true });

lightbox.addEventListener('touchend', (event) => {
    const touch = event.changedTouches[0];
    const diffX = touch.clientX - touchStartX;
    const diffY = touch.clientY - touchStartY;

    if (Math.abs(diffX) < 45 || Math.abs(diffX) < Math.abs(diffY)) {
        return;
    }

    showLightboxImage(lightboxIndex + (diffX < 0 ? 1 : -1));
}, { passive: true });

const navToggle = document.querySelector('[data-nav-toggle]');
const nav = document.querySelector('[data-nav]');
const siteHeader = document.querySelector('[data-site-header]');
const mobileBreakpoint = window.matchMedia('(max-width: 980px)');
const detailsAnimationDuration = 280;
const animatedDetails = Array.from(document.querySelectorAll('[data-booking-dropdown], .language-picker'));
const detailsTimers = new WeakMap();

const setNavOpen = (shouldOpen) => {
    if (!nav || !navToggle) {
        return;
    }

    nav.classList.toggle('is-open', shouldOpen);
    navToggle.classList.toggle('is-open', shouldOpen);
    navToggle.setAttribute('aria-expanded', String(shouldOpen));
    document.body.classList.toggle('has-mobile-nav', shouldOpen && mobileBreakpoint.matches);
};

navToggle?.addEventListener('click', () => {
    const shouldOpen = !nav?.classList.contains('is-open');
    setNavOpen(shouldOpen);
});

Array.from(nav?.querySelectorAll('a') ?? []).forEach((link) => {
    link.addEventListener('click', () => {
        if (mobileBreakpoint.matches) {
            setNavOpen(false);
        }
    });
});

const clearDetailsTimer = (details) => {
    const timer = detailsTimers.get(details);

    if (timer) {
        window.clearTimeout(timer);
        detailsTimers.delete(details);
    }
};

const openDetails = (details) => {
    clearDetailsTimer(details);
    details.classList.remove('is-closing');
    details.classList.add('is-opening');
    details.open = true;

    window.requestAnimationFrame(() => {
        details.classList.remove('is-opening');
    });
};

const closeDetails = (details) => {
    if (!details.open || details.classList.contains('is-closing')) {
        return;
    }

    clearDetailsTimer(details);
    details.classList.remove('is-opening');
    details.classList.add('is-closing');

    const timer = window.setTimeout(() => {
        details.open = false;
        details.classList.remove('is-closing');
        detailsTimers.delete(details);
    }, detailsAnimationDuration);

    detailsTimers.set(details, timer);
};

animatedDetails.forEach((details) => {
    const summary = details.querySelector('summary');

    summary?.addEventListener('click', (event) => {
        event.preventDefault();

        const shouldOpen = !details.open || details.classList.contains('is-closing');
        animatedDetails.forEach((otherDetails) => {
            if (otherDetails !== details) {
                closeDetails(otherDetails);
            }
        });

        if (shouldOpen) {
            openDetails(details);
        } else {
            closeDetails(details);
        }
    });
});

document.addEventListener('click', (event) => {
    if (
        mobileBreakpoint.matches
        && nav?.classList.contains('is-open')
        && siteHeader
        && !siteHeader.contains(event.target)
    ) {
        setNavOpen(false);
    }

    animatedDetails.forEach((details) => {
        if (!details.contains(event.target)) {
            closeDetails(details);
        }
    });
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
        return;
    }

    setNavOpen(false);
    animatedDetails.forEach(closeDetails);
});

const syncMobileState = () => {
    if (!mobileBreakpoint.matches) {
        setNavOpen(false);
    }
};

if (typeof mobileBreakpoint.addEventListener === 'function') {
    mobileBreakpoint.addEventListener('change', syncMobileState);
} else if (typeof mobileBreakpoint.addListener === 'function') {
    mobileBreakpoint.addListener(syncMobileState);
}

const formatDateInput = (date) => {
    const offsetDate = new Date(date.getTime() - (date.getTimezoneOffset() * 60000));
    return offsetDate.toISOString().slice(0, 10);
};

document.querySelectorAll('[data-fastbooker]').forEach((form) => {
    const arrival = form.querySelector('[data-arrival]');
    const departure = form.querySelector('[data-departure]');
    const today = new Date();
    const tomorrow = new Date(today);

    tomorrow.setDate(today.getDate() + 1);

    if (arrival && !arrival.value) {
        arrival.value = formatDateInput(today);
        arrival.min = formatDateInput(today);
    }

    if (departure && !departure.value) {
        departure.value = formatDateInput(tomorrow);
        departure.min = formatDateInput(tomorrow);
    }

    arrival?.addEventListener('change', () => {
        const nextDay = new Date(`${arrival.value}T00:00:00`);
        nextDay.setDate(nextDay.getDate() + 1);

        if (departure) {
            departure.min = formatDateInput(nextDay);

            if (!departure.value || departure.value <= arrival.value) {
                departure.value = formatDateInput(nextDay);
            }
        }
    });

    form.addEventListener('submit', (event) => {
        event.preventDefault();

        const url = new URL(form.action);
        url.searchParams.set('lang', form.dataset.language || 'en');
        url.searchParams.set('Arrival', arrival?.value || formatDateInput(today));
        url.searchParams.set('Departure', departure?.value || formatDateInput(tomorrow));
        window.location.href = url.toString();
    });
});

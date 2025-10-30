'use strict';

(function () {
    const MODAL_ID = 'streamingModal';
    const IFRAME_ID = 'streamingModalFrame';
    const CLOSE_ID = 'streamingModalClose';
    const STREAMING_FILENAME = 'streaming-audio-player.html';

    const modal = document.getElementById(MODAL_ID);
    const iframe = document.getElementById(IFRAME_ID);
    const closeBtn = document.getElementById(CLOSE_ID);

    if (!modal || !iframe || !closeBtn) {
        return;
    }

    const streamingUrl = new URL(STREAMING_FILENAME, window.location.origin);
    const streamingHref = streamingUrl.href;
    const streamingFilenameLower = STREAMING_FILENAME.toLowerCase();

    function matchesStreamingLink(rawHref) {
        if (!rawHref) {
            return false;
        }

        const normalized = rawHref.trim();
        if (!normalized || normalized === '#') {
            return false;
        }

        const lower = normalized.toLowerCase();
        if (
            lower === streamingFilenameLower ||
            lower === `/${streamingFilenameLower}` ||
            lower.endsWith(`/${streamingFilenameLower}`) ||
            lower.endsWith(streamingFilenameLower)
        ) {
            return true;
        }

        try {
            const resolved = new URL(normalized, window.location.href);
            const path = resolved.pathname.toLowerCase();
            return (
                path === `/${streamingFilenameLower}` ||
                path.endsWith(`/${streamingFilenameLower}`)
            );
        } catch (error) {
            return false;
        }
    }

    function openModal() {
        if (!iframe.src || !iframe.src.toLowerCase().endsWith(streamingFilenameLower)) {
            iframe.src = streamingHref;
        }
        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        trapFocus();
    }

    function closeModal() {
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        iframe.contentWindow?.postMessage({ type: 'STREAMING_MODAL_CLOSE' }, '*');
        closeBtn.blur();
    }

    function handleMenuClick(event) {
        const target = event.target.closest('a[href]');
        if (!target) {
            return;
        }

        const href = target.getAttribute('href');
        if (matchesStreamingLink(href)) {
            event.preventDefault();
            openModal();
        }
    }

    function onKeyDown(event) {
        if (event.key === 'Escape') {
            closeModal();
        }
    }

    function onOverlayClick(event) {
        if (event.target === modal) {
            closeModal();
        }
    }

    function trapFocus() {
        const focusable = modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (!first || !last) {
            return;
        }

        function handleFocusTrap(event) {
            if (event.key !== 'Tab') {
                return;
            }

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }

        modal.addEventListener('keydown', handleFocusTrap);
        first.focus({ preventScroll: true });
    }

    document.addEventListener('click', handleMenuClick, true);
    modal.addEventListener('click', onOverlayClick);
    closeBtn.addEventListener('click', closeModal);
    document.addEventListener('keydown', onKeyDown);

    window.addEventListener('message', (event) => {
        if (event.data?.type === 'STREAMING_MODAL_CLOSE') {
            closeModal();
        }
    });

    window.StreamingModal = {
        open: openModal,
        close: closeModal
    };
})();

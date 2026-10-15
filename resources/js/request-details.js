let requestDetailsListeners = null;

const initializeRequestDetails = () => {
    requestDetailsListeners?.abort();
    requestDetailsListeners = new AbortController();
    const { signal } = requestDetailsListeners;
    const modal = document.getElementById('request-detail-dialog');

    if (!modal) return;
    const panel = modal.querySelector('[role="dialog"]');
    const body = document.getElementById('request-modal-body');
    const actions = document.getElementById('request-modal-actions');
    const fullPageLink = document.getElementById('request-modal-full-page');
    const closeButton = document.getElementById('request-modal-close');
    const ticket = document.getElementById('request-modal-ticket');
    const status = document.getElementById('request-modal-status');
    const cancelOpenButton = document.getElementById('request-modal-cancel-open');
    const cancelPanel = document.getElementById('request-modal-cancel-panel');
    const cancelFormSlot = document.getElementById('request-modal-cancel-form');
    const cancelButtons = document.getElementById('request-modal-cancel-buttons');
    const cancelKeepButton = document.getElementById('request-modal-cancel-keep');
    const footerLinks = document.getElementById('request-modal-footer-links');
    const priority = document.getElementById('request-modal-priority');
    let activeRow = null;
    let preview = null;
    let previewTrigger = null;
    let previewButtons = [];
    let previewIndex = 0;
    let oldDocumentOverflow = '';
    let oldBodyOverflow = '';
    let closeTimer = null;
    let cancelForm = null;
    let cancelReason = null;

    const closePreview = () => {
        if (!preview) return false;
        preview.remove();
        preview = null;
        previewButtons = [];
        previewTrigger?.focus();
        previewTrigger = null;
        return true;
    };

    const openPreview = (trigger) => {
        closePreview();
        const container = trigger.closest('#request-modal-content') || document;
        previewButtons = [...container.querySelectorAll('[data-request-image-preview]')];
        previewIndex = previewButtons.indexOf(trigger);
        previewTrigger = trigger;
        preview = document.getElementById('request-image-preview-template').content.firstElementChild.cloneNode(true);
        document.body.append(preview);

        const renderPreview = (nextIndex) => {
            previewIndex = (nextIndex + previewButtons.length) % previewButtons.length;
            const button = previewButtons[previewIndex];
            const image = preview.querySelector('[data-preview-image]');
            image.src = button.dataset.previewUrl;
            image.alt = button.dataset.fileName;
            preview.querySelector('[data-preview-name]').textContent = button.dataset.fileName;
            preview.querySelector('[data-preview-original]').href = button.dataset.previewUrl;
            preview.querySelector('[data-preview-previous]').hidden = previewButtons.length < 2;
            preview.querySelector('[data-preview-next]').hidden = previewButtons.length < 2;
        };

        preview.addEventListener('click', (event) => {
            if (event.target === preview || event.target.closest('[data-preview-close]')) closePreview();
            if (event.target.closest('[data-preview-previous]')) renderPreview(previewIndex - 1);
            if (event.target.closest('[data-preview-next]')) renderPreview(previewIndex + 1);
        });
        renderPreview(previewIndex);
        preview.querySelector('[data-preview-close]').focus();
    };

    const setBadge = (element, textValue, classes) => {
        element.textContent = textValue;
        element.className = `inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${classes}`;
    };

    const openModal = async (row) => {
        clearTimeout(closeTimer);
        activeRow = row;
        const url = row.dataset.requestModalUrl;
        fullPageLink.href = url;
        body.innerHTML = '<p class="text-sm text-gray-600">Loading request…</p>';
        actions.replaceChildren();
        cancelOpenButton.classList.add('hidden');
        cancelPanel.classList.add('hidden');
        cancelFormSlot.replaceChildren();
        cancelButtons.replaceChildren(cancelKeepButton);
        footerLinks.classList.remove('hidden');
        oldDocumentOverflow = document.documentElement.style.overflow;
        oldBodyOverflow = document.body.style.overflow;
        document.documentElement.style.overflow = 'hidden';
        document.body.style.overflow = 'hidden';
        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');
        requestAnimationFrame(() => {
            modal.classList.remove('opacity-0');
            modal.classList.add('opacity-100');
            panel.classList.remove('opacity-0');
            panel.classList.add('opacity-100');
        });
        closeButton.focus();

        try {
            const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'text/html' } });
            if (!response.ok) throw new Error(`Request details could not be loaded (${response.status}).`);
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            const content = page.querySelector('#request-modal-content');
            const metadata = page.querySelector('#request-detail-metadata');
            if (!content || !metadata) throw new Error('Request details could not be loaded.');

            ticket.textContent = metadata.dataset.ticket;
            setBadge(status, metadata.dataset.status, metadata.dataset.statusClass);
            setBadge(priority, metadata.dataset.priority, metadata.dataset.priorityClass);
            const sourceCancelForm = content.querySelector('[data-request-cancel-form]');
            if (sourceCancelForm) {
                sourceCancelForm.remove();
                if (['submitted', 'pending approval'].includes(metadata.dataset.status.toLowerCase())) {
                    cancelForm = sourceCancelForm.cloneNode(true);
                    cancelForm.className = 'space-y-3';
                    const csrfInputs = [...cancelForm.querySelectorAll('input[type="hidden"]')];
                    const submitButton = cancelForm.querySelector('button[type="submit"]');
                    cancelForm.replaceChildren(...csrfInputs);
                    const label = document.createElement('label');
                    label.htmlFor = 'request-modal-cancel-reason';
                    label.className = 'block text-sm font-medium text-gray-700';
                    label.textContent = 'Reason (optional)';
                    cancelReason = document.createElement('textarea');
                    cancelReason.id = label.htmlFor;
                    cancelReason.name = 'reason';
                    cancelReason.rows = 3;
                    cancelReason.maxLength = 300;
                    cancelReason.placeholder = 'Tell us why (optional)';
                    cancelReason.className = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600';
                    const counter = document.createElement('p');
                    counter.className = 'text-right text-xs text-gray-500';
                    counter.setAttribute('aria-live', 'polite');
                    const updateCounter = () => { counter.textContent = `${cancelReason.value.length}/300`; };
                    cancelReason.addEventListener('input', updateCounter);
                    updateCounter();
                    cancelForm.append(label, cancelReason, counter);
                    submitButton.type = 'submit';
                    submitButton.textContent = 'Cancel request';
                    submitButton.className = 'rounded-md bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-500 disabled:cursor-not-allowed disabled:opacity-60';
                    submitButton.removeAttribute('name');
                    submitButton.removeAttribute('value');
                    cancelButtons.prepend(submitButton);
                    cancelForm.append(cancelButtons);
                    cancelFormSlot.append(cancelForm);
                    cancelOpenButton.classList.remove('hidden');
                    cancelForm.addEventListener('submit', submitCancellation);
                }
            }
            content.querySelectorAll('[data-request-footer-action]').forEach((form) => {
                if (form.hasAttribute('data-request-cancel-form')) return;
                actions.append(form.cloneNode(true));
                form.remove();
            });
            body.replaceChildren(...[...content.childNodes]);
        } catch (error) {
            body.innerHTML = '<p class="rounded-md bg-red-50 p-3 text-sm text-red-700">Unable to load this request. You can open the full page to check access.</p>';
        }
    };

    const closeModal = () => {
        if (closePreview()) return;
        if (modal.classList.contains('hidden')) return;
        modal.classList.remove('opacity-100');
        modal.classList.add('opacity-0');
        panel.classList.remove('opacity-100');
        panel.classList.add('opacity-0');
        modal.setAttribute('aria-hidden', 'true');
        closeTimer = setTimeout(() => {
            modal.classList.add('hidden');
            document.documentElement.style.overflow = oldDocumentOverflow;
            document.body.style.overflow = oldBodyOverflow;
            body.replaceChildren();
            actions.replaceChildren();
            activeRow?.focus();
            activeRow = null;
        }, 150);
    };

    const showCancellationBanner = (kind, message) => {
        body.querySelector('[data-cancellation-banner]')?.remove();
        const banner = document.createElement('div');
        banner.dataset.cancellationBanner = '';
        banner.className = kind === 'success'
            ? 'mb-4 rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-800'
            : 'mb-4 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800';
        banner.setAttribute('role', kind === 'success' ? 'status' : 'alert');
        banner.textContent = message;
        body.prepend(banner);
    };

    const submitCancellation = async (event) => {
        event.preventDefault();
        if (cancelForm.dataset.submitting === 'true') return;
        cancelForm.dataset.submitting = 'true';
        const submitButton = cancelForm.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        const originalText = submitButton.textContent;
        submitButton.textContent = 'Cancelling…';

        try {
            const response = await fetch(cancelForm.action, {
                method: 'POST',
                body: new FormData(cancelForm),
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const result = await response.json();
            if (!response.ok) {
                const validationMessage = Object.values(result.errors || {}).flat()[0];
                throw new Error(validationMessage || result.message || 'The request could not be cancelled.');
            }

            cancelPanel.classList.add('hidden');
            cancelOpenButton.classList.add('hidden');
            footerLinks.classList.remove('hidden');
            setBadge(status, 'Cancelled', 'bg-red-100 text-red-800');
            body.querySelectorAll('dt').forEach((term) => {
                if (term.textContent.trim() === 'Status') term.nextElementSibling.textContent = 'Cancelled';
            });
            showCancellationBanner('success', result.message || 'Request cancelled successfully.');
            if (cancelReason.value.trim()) {
                const reason = document.createElement('div');
                reason.className = 'mt-4 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800';
                reason.innerHTML = '<p class="font-semibold">Cancellation reason</p>';
                const text = document.createElement('p');
                text.className = 'mt-1 whitespace-pre-wrap';
                text.textContent = cancelReason.value.trim();
                reason.append(text);
                body.prepend(reason);
            }
        } catch (error) {
            showCancellationBanner('error', error.message || 'The request could not be cancelled.');
            submitButton.disabled = false;
            submitButton.textContent = originalText;
            cancelForm.dataset.submitting = 'false';
        }
    };

    document.addEventListener('click', (event) => {
        const previewButton = event.target.closest('[data-request-image-preview]');
        if (previewButton) {
            openPreview(previewButton);
            return;
        }
        const trigger = event.target.closest('[data-request-modal-trigger]');
        if (trigger) {
            event.preventDefault();
            openModal(trigger.closest('tr[data-request-modal-url]'));
            return;
        }
        if (event.target.closest('a,button')) return;
        const row = event.target.closest('[data-request-modal-url]');
        if (row) openModal(row);
        if (event.target === modal) closeModal();
    }, { signal });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            if (preview) { event.preventDefault(); closePreview(); }
            else if (!modal.classList.contains('hidden')) { event.preventDefault(); closeModal(); }
            return;
        }
        if (preview && event.key === 'ArrowLeft' && previewButtons.length > 1) {
            event.preventDefault();
            preview.querySelector('[data-preview-previous]').click();
        }
        if (preview && event.key === 'ArrowRight' && previewButtons.length > 1) {
            event.preventDefault();
            preview.querySelector('[data-preview-next]').click();
        }
        if (event.key === 'Enter' && !event.target.closest('a,button')) {
            const row = event.target.closest('[data-request-modal-url]');
            if (row) openModal(row);
        }
    }, { signal });

    closeButton.addEventListener('click', closeModal, { signal });
    cancelOpenButton.addEventListener('click', () => {
        cancelPanel.classList.remove('hidden');
        footerLinks.classList.add('hidden');
        actions.classList.add('hidden');
        cancelReason.focus();
    }, { signal });
    cancelKeepButton.addEventListener('click', () => {
        cancelPanel.classList.add('hidden');
        footerLinks.classList.remove('hidden');
        actions.classList.remove('hidden');
        cancelReason.value = '';
        cancelReason.dispatchEvent(new Event('input'));
        cancelOpenButton.focus();
    }, { signal });
};

initializeRequestDetails();
document.addEventListener('livewire:navigated', initializeRequestDetails);

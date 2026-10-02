const mobileNavigation = document.querySelector('[data-mobile-nav]');
const mobileNavigationToggle = document.querySelector('[data-mobile-nav-toggle]');
const mobileNavigationClose = document.querySelector('[data-mobile-nav-close]');
const mobileNavigationBackdrop = document.querySelector('[data-mobile-nav-backdrop]');

if (mobileNavigation && mobileNavigationToggle && mobileNavigationBackdrop) {
    const closeNavigation = () => {
        mobileNavigation.classList.add('-translate-x-full');
        mobileNavigationBackdrop.classList.add('hidden');
        mobileNavigationToggle.setAttribute('aria-expanded', 'false');
    };

    const openNavigation = () => {
        mobileNavigation.classList.remove('-translate-x-full');
        mobileNavigationBackdrop.classList.remove('hidden');
        mobileNavigationToggle.setAttribute('aria-expanded', 'true');
        mobileNavigationClose?.focus();
    };

    mobileNavigationToggle.addEventListener('click', openNavigation);
    mobileNavigationClose?.addEventListener('click', closeNavigation);
    mobileNavigationBackdrop.addEventListener('click', closeNavigation);
    mobileNavigation.querySelectorAll('[data-mobile-nav-link]').forEach((link) => {
        link.addEventListener('click', closeNavigation);
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && mobileNavigationToggle.getAttribute('aria-expanded') === 'true') {
            closeNavigation();
            mobileNavigationToggle.focus();
        }
    });
    mobileNavigationToggle.disabled = false;
}

const confirmationModal = document.querySelector('[data-confirm-modal]');
const previewModal = document.querySelector('[data-preview-modal]');
let confirmationForm = null;
let confirmationTrigger = null;

const returnFocus = (element) => {
    if (element instanceof HTMLElement && document.contains(element)) element.focus();
};

document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (form.dataset.confirmed === 'true') {
            return;
        }

        event.preventDefault();

        if (!confirmationModal?.showModal) {
            if (window.confirm(form.dataset.confirm)) {
                form.dataset.confirmed = 'true';
                form.requestSubmit();
            }

            return;
        }

        confirmationModal.querySelector('[data-confirm-message]').textContent = form.dataset.confirm;
        confirmationForm = form;
        confirmationTrigger = document.activeElement;
        confirmationModal.showModal();
        confirmationModal.querySelector('[data-confirm-cancel]')?.focus();
    });
});

confirmationModal?.querySelector('[data-confirm-submit]')?.addEventListener('click', () => {
    const form = confirmationForm;
    confirmationModal.close();
    confirmationForm = null;
    if (form) { form.dataset.confirmed = 'true'; form.requestSubmit(); }
});
confirmationModal?.addEventListener('close', () => {
    if (confirmationForm) { confirmationForm = null; returnFocus(confirmationTrigger); }
    confirmationTrigger = null;
});

const previewFrame = previewModal?.querySelector('[data-preview-frame]');
let previewTrigger = null;
document.querySelectorAll('[data-media-preview]').forEach((link) => {
    link.addEventListener('click', (event) => {
        if (!previewModal?.showModal || !previewFrame) return;
        event.preventDefault();
        previewTrigger = link;
        previewFrame.src = link.href;
        previewModal.querySelector('[data-preview-title]').textContent = link.dataset.previewName || 'Attachment preview';
        previewModal.showModal();
        previewModal.querySelector('[data-preview-close]')?.focus();
    });
});
previewModal?.addEventListener('close', () => {
    previewFrame?.removeAttribute('src');
    returnFocus(previewTrigger);
    previewTrigger = null;
});
previewModal?.querySelector('[data-preview-close]')?.addEventListener('click', () => previewModal.close());

document.querySelectorAll('[data-character-counter]').forEach((field) => {
    const counter = document.querySelector(`[data-character-count-for="${field.id}"]`);
    if (!counter) return;
    const update = () => { counter.textContent = `${field.value.length} / ${field.maxLength}`; };
    field.addEventListener('input', update);
    update();
});

document.querySelectorAll('[data-project-key]').forEach((field) => {
    field.addEventListener('input', () => {
        field.value = field.value.toUpperCase();
    });
});

document.querySelectorAll('[data-copy-text]').forEach((button) => {
    button.addEventListener('click', async () => {
        const value = button.dataset.copyText;
        if (!value) return;
        try { await navigator.clipboard.writeText(value); button.dataset.copyState = 'copied'; button.textContent = 'Copied'; }
        catch { button.dataset.copyState = 'failed'; button.textContent = 'Copy failed'; }
    });
});

document.querySelectorAll('[data-one-time-token]').forEach((container) => {
    const remove = () => container.remove();
    container.querySelector('[data-token-close]')?.addEventListener('click', remove);
    container.addEventListener('close', remove);
});

const board = document.querySelector('[data-board]');
if (board) {
    const feedback = board.parentElement.querySelector('[data-board-feedback]');
    let draggedCard = null;
    const report = (message, isError = false) => {
        if (!feedback) return;
        feedback.hidden = false; feedback.textContent = message;
        feedback.classList.toggle('text-rose-700', isError); feedback.classList.toggle('text-emerald-700', !isError);
    };
    const requestMove = async (url, payload) => {
        const response = await fetch(url, {
            method: 'PATCH',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(payload),
        });

        if (response.status === 409) {
            report('The board changed while you were moving this task. Refresh the board and try again.', true);

            return false;
        }
        if (!response.ok) {
            report('Move could not be completed. Refresh the board and try again.', true);

            return false;
        }

        return true;
    };
    const sameColumnPosition = (card, zone, event) => {
        const cards = [...zone.querySelectorAll('[data-board-card]')].filter((candidate) => candidate !== card);
        const target = event.target.closest?.('[data-board-card]');
        let insertAt = cards.length;

        if (target === card) return null;
        if (target && cards.includes(target)) {
            insertAt = cards.indexOf(target);
            if (event.clientY > target.getBoundingClientRect().top + (target.getBoundingClientRect().height / 2)) insertAt++;
        }

        return {
            before_task_id: insertAt > 0 ? Number(cards[insertAt - 1].dataset.task) : null,
            after_task_id: insertAt < cards.length ? Number(cards[insertAt].dataset.task) : null,
            expected_version: Number(card.dataset.version),
        };
    };
    const move = async (card, zone, event) => {
        const targetStatus = zone.closest('[data-column]')?.dataset.column;
        if (!card || !targetStatus) return;

        const sameColumn = card.closest('[data-column]') === zone.closest('[data-column]');
        let url;
        let payload;

        if (sameColumn) {
            if (!card.dataset.rankUrl) {
                report('Only project managers can reorder a column.', true);

                return;
            }
            url = card.dataset.rankUrl;
            payload = sameColumnPosition(card, zone, event);
            if (!payload) {
                report('Task position unchanged.');

                return;
            }
        } else {
            const transitions = JSON.parse(card.dataset.transitions || '[]');
            if (!transitions.includes(targetStatus)) {
                report('That workflow transition is not available for this task.', true);

                return;
            }
            url = card.dataset.statusUrl;
            payload = {status: targetStatus, expected_version: Number(card.dataset.version)};
        }

        report('Moving task…');
        try {
            if (await requestMove(url, payload)) window.location.reload();
        } catch { report('Network error. The task was not moved; refresh and try again.', true); }
    };
    board.querySelectorAll('[data-board-card]').forEach((card) => {
        card.addEventListener('dragstart', (event) => { draggedCard = card; event.dataTransfer.effectAllowed = 'move'; event.dataTransfer.setData('text/plain', card.dataset.task); });
        card.addEventListener('dragend', () => { draggedCard = null; });
    });
    board.querySelectorAll('[data-cards]').forEach((zone) => {
        zone.addEventListener('dragover', (event) => event.preventDefault());
        zone.addEventListener('drop', (event) => { event.preventDefault(); move(draggedCard, zone, event); });
    });
}

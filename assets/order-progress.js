/* Customer order details: refresh status and rider links without reloading. */
(() => {
    'use strict';

    const panel = document.getElementById('order-progress');
    if (!panel) return;

    const endpoint = panel.dataset.progressUrl;
    const trackingLink = document.getElementById('delivery-tracking-link');
    const trackingNote = document.getElementById('delivery-tracking-note');
    let loading = false;

    function element(tag, text, className = '') {
        const node = document.createElement(tag);
        node.textContent = text;
        if (className) node.className = className;
        return node;
    }

    function updateTracking(url) {
        const valid = typeof url === 'string' && /^https?:\/\//i.test(url);
        trackingLink.hidden = !valid;
        if (valid) trackingLink.href = url;
        else trackingLink.removeAttribute('href');
        trackingNote.textContent = valid
            ? 'Open the rider or courier link for delivery updates.'
            : 'Your tracking link will appear here when the shop adds it.';
    }

    function renderProgress(data) {
        const content = document.createDocumentFragment();
        content.append(element('h2', 'Order progress: ' + data.status, 'h5'));

        const steps = ['Placed', 'Preparing', 'Ready', 'Completed'];
        const position = ['Served', 'Delivered', 'Completed'].includes(data.status)
            ? 3 : steps.indexOf(data.status);
        const stages = element('ol', '', 'd-flex flex-wrap gap-4 ps-3');
        steps.forEach((step, index) => {
            const item = element('li', step, index <= position ? 'fw-bold text-success' : '');
            if (index === position) item.setAttribute('aria-current', 'step');
            stages.append(item);
        });
        content.append(stages);
        content.append(element('p', data.fulfillment_method === 'Delivery'
            ? 'Order type: Delivery'
            : 'Pickup: ' + (data.pickup_at || 'As soon as ready')));

        for (const kind of ['payment', 'id']) {
            const label = kind === 'id' ? 'Senior / PWD ID: ' : 'Payment review: ';
            const reason = data[kind + '_review_reason'];
            content.append(element('p', label + data[kind + '_review'] + (reason ? ' — ' + reason : '')));
        }
        if (data.payment_review === 'Rejected' || data.id_review === 'Rejected') {
            content.append(element('p', 'Contact the shop about the rejected document before your order can proceed.', 'text-danger'));
        }
        const history = element('ul', '');
        data.history.forEach(entry => history.append(element('li', entry.status + ' · ' + entry.created_at)));
        content.append(history);
        panel.replaceChildren(content);
    }

    async function refreshProgress() {
        if (loading || document.hidden) return;
        loading = true;
        try {
            const response = await fetch(endpoint, {cache: 'no-store'});
            if (!response.ok) throw new Error('Unable to load progress');
            const data = await response.json();
            updateTracking(data.delivery_tracking_url);
            renderProgress(data);
        } catch (_) {
            // Keep the last successful status visible during a temporary outage.
            if (!panel.querySelector('h2')) {
                panel.textContent = 'Progress is temporarily unavailable. Refresh the page to try again.';
            }
        } finally {
            loading = false;
        }
    }

    refreshProgress();
    setInterval(refreshProgress, 15000);
    document.addEventListener('visibilitychange', refreshProgress);
})();

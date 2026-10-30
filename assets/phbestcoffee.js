$(function () {
    'use strict';

    const CART_KEY = 'phbestcoffee-cart';
    const MAX_QTY = 50;
    const MAX_LINES = 50;
    const MAX_PROOF_BYTES = 5 * 1024 * 1024;
    const PROOF_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    const $drawer = $('#cart-modal');
    const $checkout = $('#checkout-modal');
    const $confirmation = $('#confirmation-modal');
    const signedIn = String($drawer.data('signed-in')) === '1';
    const $loyalty = $('#loyalty-program');
    let rewards = $loyalty.data('rewards') || {};
    const cupIds = new Set($loyalty.data('cup-ids') || []);
    let loyaltyBalance = Number($loyalty.data('balance')) || 0;

    function selectedReward() {
        return rewards[$('#loyalty-reward').val()] || { points: Infinity, cents: 0, stock: null, label: '' };
    }

    function updateLoyaltyBalance(balance) {
        loyaltyBalance = Number(balance) || 0;
        $('#loyalty-balance').text(loyaltyBalance);
        const $select = $('#loyalty-reward');
        let firstAvailable = null;
        $select.find('option').each(function () {
            const reward = rewards[this.value];
            if (!reward) return;
            const eligible = loyaltyBalance >= reward.points && reward.stock !== 0;
            this.disabled = !eligible;
            if (eligible && firstAvailable === null) firstAvailable = this.value;
        });
        const current = rewards[$select.val()];
        if (!current || loyaltyBalance < current.points || current.stock === 0) {
            $select.val(firstAvailable || '');
            $('#redeem-loyalty').prop('checked', false);
        }
        $select.prop('disabled', firstAvailable === null);
        $select.find('option[value=""]').text(firstAvailable === null ? 'No rewards available for your points' : 'Choose an available reward');
        const selected = selectedReward();
        $('#reward-merchandise-preview').toggleClass('d-none', !selected.image);
        if (selected.image) $('#reward-merchandise-preview img').attr('src', selected.image).attr('alt', selected.label);
        $('#reward-merchandise-preview span').text(selected.label);
        const unavailable = loyaltyBalance < selected.points || selected.stock === 0;
        $('#reward-stock-note').text(selected.image ? (selected.stock === null ? 'Available' : selected.stock === 0 ? 'Sold out' : selected.stock + ' left available') : '');
        $('#redeem-loyalty').prop('disabled', unavailable);
        if (unavailable) $('#redeem-loyalty').prop('checked', false);
        refreshCheckoutTotals();
    }

    const paymentMethods = {
        Cash: {
            label: 'Cash'
        },
        GCash: {
            label: 'GCash',
            image: 'assets/payments/gcash-qr.jpg',
            alt: 'GCash payment QR code',
            account: ''
        },
        Maya: {
            label: 'Maya',
            image: 'assets/payments/maya-qr.jpg',
            alt: 'Maya payment QR code',
            account: 'Kenneth Cole Alcantara · @koruu · +63 *** *** 6925'
        },
        GoTyme: {
            label: 'GoTyme',
            image: 'assets/payments/gotyme-qr.jpg',
            alt: 'GoTyme Bank transfer QR code',
            account: 'Kenneth Cole Alcantara · GoTyme Bank •••••• 5276'
        }
    };

    let paymentMethod = '';

    let cart = [];
    let slideIndex = 0;
    let lastFocused = null;
    let proofPreviewUrl = null;

    const peso = value => '₱' + (Number(value) || 0).toFixed(2);
    const toCents = value => Math.round((Number(value) || 0) * 100);

    // Cart storage

    function saveCart() {
        try {
            localStorage.setItem(CART_KEY, JSON.stringify(cart));
        } catch (error) {}
    }

    function normalizeCart(raw) {
        if (!Array.isArray(raw) || raw.length > MAX_LINES) return null;

        const seen = new Set();
        const clean = [];

        for (let index = 0; index < raw.length; index += 1) {
            const item = raw[index] || {};
            const id = Number(item.id);
            const price = Number(item.price);
            const quantity = Number(item.quantity);
            const priceCents = price * 100;

            if (!Number.isSafeInteger(id) || id < 1 || seen.has(id)) return null;
            if (typeof item.name !== 'string' || item.name.trim() === '') return null;
            if (!Number.isFinite(price) || price < 0) return null;
            if (Math.abs(priceCents - Math.round(priceCents)) > 0.0001) return null;
            if (!Number.isInteger(quantity) || quantity < 1 || quantity > MAX_QTY) return null;

            seen.add(id);
            clean.push({
                id: id,
                name: item.name.trim().slice(0, 120),
                price: Math.round(priceCents) / 100,
                quantity: quantity,
                image: typeof item.image === 'string' ? item.image : ''
            });
        }

        return clean;
    }

    (function loadCart() {
        let raw = [];

        try {
            raw = JSON.parse(localStorage.getItem(CART_KEY) || '[]');
        } catch (error) {
            raw = [];
        }

        const clean = normalizeCart(raw);

        if (clean === null) {
            cart = [];
            saveCart();
            window.setTimeout(function () {
                showToast('Your saved cart had invalid items and was cleared.', { icon: '!', tone: 'warning' });
            }, 700);
        } else {
            cart = clean;
        }
    }());

    function itemCount() {
        return cart.reduce((sum, item) => sum + item.quantity, 0);
    }

    function subtotalCents() {
        return cart.reduce((sum, item) => sum + toCents(item.price) * item.quantity, 0);
    }

    function subtotal() {
        return subtotalCents() / 100;
    }

    function inputAmountCents(value) {
        const text = String(value).trim();
        if (!/^\d+(?:\.\d{1,2})?$/.test(text)) return null;

        const amount = Number(text);
        if (!Number.isFinite(amount) || amount > 99999999.99) return null;

        return Math.round(amount * 100);
    }

    // Toasts

    function showToast(message, options) {
        const settings = options || {};
        const $toast = $('<div class="toast toast-coffee" role="status" aria-live="polite" aria-atomic="true"></div>');
        const $body = $('<div class="toast-body d-flex align-items-center gap-2"></div>').appendTo($toast);

        if (settings.tone === 'warning') $toast.addClass('is-warning');

        $('<span class="toast-icon" aria-hidden="true"></span>')
            .text(settings.icon || '✓')
            .appendTo($body);

        $('<div class="toast-message flex-grow-1"></div>')
            .text(message)
            .appendTo($body);

        if (settings.actionLabel) {
            $('<button type="button" class="btn btn-toast-action flex-shrink-0"></button>')
                .text(settings.actionLabel)
                .on('click', function () {
                    if (typeof settings.onAction === 'function') settings.onAction();
                    toast.hide();
                })
                .appendTo($body);
        }

        $('<button type="button" class="btn-close ms-1 flex-shrink-0" data-bs-dismiss="toast" aria-label="Close notification"></button>')
            .appendTo($body);

        $('#toast-stack').append($toast);

        const toast = new bootstrap.Toast($toast[0], {
            delay: settings.delay || 3400,
            autohide: true
        });

        $toast.on('hidden.bs.toast', function () {
            $toast.remove();
        });

        toast.show();
    }

    // Slideshow, tabs, hero

    const $slides = $('.slide');

    function showSlide(index) {
        $slides.removeClass('active').eq(index).addClass('active');
    }

    if ($slides.length) {
        showSlide(slideIndex);
        window.setInterval(function () {
            slideIndex = (slideIndex + 1) % $slides.length;
            showSlide(slideIndex);
        }, 5000);
    }

    $('.tab-btn').on('click', function () {
        const target = $(this).data('tab');
        $('.tab-btn').removeClass('active');
        $(this).addClass('active');
        $('.tab-content').addClass('d-none');
        $('#' + target).removeClass('d-none');
    });

    $('#view-best').on('click', function () {
        document.getElementById('menu')?.scrollIntoView({ behavior: 'smooth' });
    });

    // Menu quantity steppers

    $('.quantity-minus').on('click', function () {
        const $input = $(this).siblings('.menu-quantity-input');
        $input.val(Math.max(1, (parseInt($input.val(), 10) || 1) - 1));
    });

    $('.quantity-plus').on('click', function () {
        const $input = $(this).siblings('.menu-quantity-input');
        const max = Math.min(MAX_QTY, parseInt($input.attr('max'), 10) || MAX_QTY);
        $input.val(Math.min(max, (parseInt($input.val(), 10) || 1) + 1));
    });

    $('.menu-quantity-input').on('change', function () {
        const $input = $(this);
        const max = Math.min(MAX_QTY, parseInt($input.attr('max'), 10) || MAX_QTY);
        const quantity = Number($input.val());
        $input.val(Number.isInteger(quantity) ? Math.min(max, Math.max(1, quantity)) : 1);
    });

    // Cart badge

    function updateCount(pop) {
        const count = itemCount();
        const $badge = $('#cart-count');

        $badge.text(count).toggleClass('is-empty', count === 0);
        $('#cart-open-btn').attr('aria-label', count ? 'Open cart, ' + count + ' item' + (count === 1 ? '' : 's') : 'Open cart, empty');

        if (pop) {
            $badge.removeClass('pop');
            void $badge[0].offsetWidth;
            $badge.addClass('pop');
        }
    }

    // Cart drawer

    function anyOverlayOpen() {
        return $drawer.hasClass('show') || $checkout.hasClass('is-open') || $confirmation.hasClass('is-open');
    }

    function syncScrollLock() {
        $('body').toggleClass('cart-scroll-lock', anyOverlayOpen());
    }

    function openCart() {
        if (!$drawer.hasClass('show')) lastFocused = document.activeElement;
        renderCart();

        $('#cart-backdrop').addClass('show');
        $drawer.addClass('show').attr('aria-hidden', 'false');
        $('#cart-open-btn, #floating-cart-btn').attr('aria-expanded', 'true');
        syncScrollLock();

        window.setTimeout(function () {
            document.getElementById('close-cart-btn')?.focus();
        }, 160);
    }

    function closeCart(options) {
        const settings = options || {};

        $('#cart-backdrop').removeClass('show');
        $drawer.removeClass('show').attr('aria-hidden', 'true');
        $('#cart-open-btn, #floating-cart-btn').attr('aria-expanded', 'false');
        syncScrollLock();

        if (settings.restoreFocus !== false && lastFocused && typeof lastFocused.focus === 'function') {
            lastFocused.focus();
        }
    }

    function cartLine(item) {
        const $line = $('<article class="cart-line"></article>').attr('data-id', item.id);
        const $thumbWrap = $('<div class="cart-line-thumb-wrap"></div>');

        if (item.image) {
            $('<img class="cart-line-thumb" alt="">')
                .attr('src', item.image)
                .on('error', function () {
                    $(this).replaceWith('<span class="cart-line-thumb cart-line-thumb-fallback" aria-hidden="true">☕</span>');
                })
                .appendTo($thumbWrap);
        } else {
            $('<span class="cart-line-thumb cart-line-thumb-fallback" aria-hidden="true">☕</span>').appendTo($thumbWrap);
        }

        const $main = $('<div class="cart-line-main"></div>');
        $('<h5 class="cart-line-name"></h5>').text(item.name).appendTo($main);
        $('<p class="cart-line-price"></p>').text(peso(item.price) + ' each').appendTo($main);

        const $qty = $('<div class="cart-qty" role="group"></div>').attr('aria-label', 'Quantity for ' + item.name);
        $('<button type="button" class="cart-qty-btn" data-cart-action="dec" aria-label="Decrease quantity">−</button>')
            .prop('disabled', item.quantity <= 1)
            .appendTo($qty);
        $('<span class="cart-qty-value" aria-live="polite"></span>').text(item.quantity).appendTo($qty);
        $('<button type="button" class="cart-qty-btn" data-cart-action="inc" aria-label="Increase quantity">+</button>')
            .prop('disabled', item.quantity >= MAX_QTY)
            .appendTo($qty);
        $main.append($qty);

        const $side = $('<div class="cart-line-side"></div>');
        $('<strong class="cart-line-total"></strong>').text(peso(item.price * item.quantity)).appendTo($side);
        $('<button type="button" class="cart-line-remove" data-cart-action="remove"></button>')
            .text('Remove')
            .attr('aria-label', 'Remove ' + item.name + ' from cart')
            .appendTo($side);

        return $line.append($thumbWrap, $main, $side);
    }

    function renderCart() {
        const $items = $('#cart-items').empty();
        const count = itemCount();

        $('#cart-item-count').text(count === 1 ? '1 item' : count + ' items');

        if (!cart.length) {
            const $empty = $('<div class="cart-empty"></div>');
            $('<div class="cart-empty-icon" aria-hidden="true">☕</div>').appendTo($empty);
            $('<h4 class="title-font h5 mb-1"></h4>').text('Your cart is empty').appendTo($empty);
            $('<p class="text-secondary small mb-4"></p>')
                .text('Add a drink or a snack from the menu to get started.')
                .appendTo($empty);
            $('<button type="button" class="btn btn-coffee px-4"></button>')
                .text('Browse the menu')
                .on('click', function () {
                    closeCart({ restoreFocus: false });
                    document.getElementById('menu')?.scrollIntoView({ behavior: 'smooth' });
                })
                .appendTo($empty);

            $items.append($empty);
            $('#cart-summary').addClass('d-none');
            $('#clear-cart-btn').addClass('d-none');
            $('.cart-drawer-links').addClass('is-empty');
            $('#checkout-btn').prop('disabled', true).text('Checkout');
        } else {
            cart.forEach(function (item) {
                $items.append(cartLine(item));
            });

            $('#cart-summary').removeClass('d-none');
            $('#clear-cart-btn').removeClass('d-none');
            $('.cart-drawer-links').removeClass('is-empty');
            $('#checkout-btn')
                .prop('disabled', false)
                .text(signedIn ? 'Checkout · ' + peso(subtotal()) : 'Sign in to Checkout');

            $('#cart-subtotal, #cart-total').text(peso(subtotal()));
        }
    }

    $('#cart-items').on('click', '[data-cart-action]', function () {
        const $line = $(this).closest('.cart-line');
        const id = Number($line.attr('data-id'));
        const item = cart.find(row => row.id === id);
        if (!item) return;

        const action = $(this).attr('data-cart-action');

        if (action === 'inc' && item.quantity < MAX_QTY) {
            item.quantity += 1;
        } else if (action === 'dec' && item.quantity > 1) {
            item.quantity -= 1;
        } else if (action === 'remove') {
            const removed = item;
            const position = cart.indexOf(item);

            cart = cart.filter(row => row !== item);
            saveCart();
            renderCart();
            updateCount();

            showToast(removed.name + ' removed', {
                icon: '×',
                actionLabel: 'Undo',
                onAction: function () {
                    if (cart.length >= MAX_LINES || cart.some(row => row.id === removed.id)) return;
                    cart.splice(Math.min(position, cart.length), 0, removed);
                    saveCart();
                    renderCart();
                    updateCount();
                }
            });
            return;
        }

        saveCart();
        renderCart();
        updateCount();
    });

    $('#cart-open-btn, #floating-cart-btn').on('click', openCart);
    $('#close-cart-btn, #continue-shopping-btn').on('click', function () {
        closeCart();
    });
    $('#cart-backdrop').on('click', function () {
        closeCart();
    });

    $('#clear-cart-btn').on('click', function () {
        if (!cart.length) return;

        const snapshot = cart.slice();
        cart = [];

        saveCart();
        renderCart();
        updateCount();

        showToast('Cart cleared', {
            icon: '×',
            actionLabel: 'Undo',
            onAction: function () {
                cart = snapshot;
                saveCart();
                renderCart();
                updateCount();
            }
        });
    });

    // Add to cart

    $('.add-cart').on('click', function () {
        const $button = $(this);

        if ($button.is(':disabled') || $button.attr('data-available') === 'false') {
            showToast($button.data('name') + ' is currently unavailable.', { icon: '!', tone: 'warning' });
            return;
        }

        const id = Number($button.data('id'));
        const name = String($button.data('name'));
        const image = String($button.data('image') || '');
        const price = Number($button.data('price'));
        const $quantityInput = $button.closest('.product-card').find('.menu-quantity-input');
        const rawQuantity = Number($quantityInput.val());

        if (
            !Number.isSafeInteger(id) || id < 1 ||
            !Number.isFinite(price) || price < 0 ||
            !Number.isInteger(rawQuantity) || rawQuantity < 1 || rawQuantity > MAX_QTY
        ) {
            showToast('Enter a whole-number quantity from 1 to 50.', { icon: '!', tone: 'warning' });
            $quantityInput.trigger('focus');
            return;
        }

        if (cart.length >= MAX_LINES && !cart.some(item => item.id === id)) {
            showToast('Your cart can contain up to 50 different products.', { icon: '!', tone: 'warning' });
            return;
        }

        const quantity = rawQuantity;
        const found = cart.find(item => item.id === id);
        const stockRaw = $button.attr('data-stock');
        const stock = stockRaw === undefined || stockRaw === '' ? null : Number(stockRaw);

        if (stock !== null) {
            if (!Number.isInteger(stock) || stock < 1) {
                showToast(name + ' is sold out.', { icon: '!', tone: 'warning' });
                return;
            }

            const inCart = found ? found.quantity : 0;

            if (quantity > stock) {
                $quantityInput.val(stock);
                showToast('Only ' + stock + ' left of ' + name + '.', { icon: '!', tone: 'warning' });
                return;
            }

            if (inCart + quantity > stock) {
                showToast('Only ' + stock + ' left of ' + name + ' and you already have ' + inCart + ' in your cart.', { icon: '!', tone: 'warning' });
                return;
            }
        }

        if (found) {
            if (found.quantity + quantity > MAX_QTY) {
                showToast('A maximum of 50 units is allowed per product.', { icon: '!', tone: 'warning' });
                return;
            }
            found.quantity += quantity;
        } else {
            cart.push({
                id: id,
                name: name,
                price: price,
                quantity: quantity,
                image: image
            });
        }

        $quantityInput.val(1);
        saveCart();
        updateCount(true);

        if ($drawer.hasClass('show')) renderCart();

        showToast(quantity + ' × ' + name + ' added to cart', {
            icon: '🛒'
        });
    });

    // Checkout

    function totalsNow() {
        const subCents = subtotalCents();
        const reward = $('#redeem-loyalty').is(':checked');
        const choice = selectedReward();
        const discountCents = reward ? Math.min(choice.cents, subCents) : ($('#senior-pwd').is(':checked') ? Math.round(subCents * 0.2) : 0);

        return {
            reward,
            rewardPoints: choice.points,
            rewardLabel: choice.label,
            rewardImage: choice.image || '',
            sub: subCents / 100,
            discount: discountCents / 100,
            total: (subCents - discountCents) / 100
        };
    }

    function renderCheckout() {
        const $items = $('#receipt-items').empty();

        cart.forEach(function (item) {
            const $row = $('<div class="checkout-item"></div>');
            $('<span class="checkout-item-qty"></span>').text(item.quantity + '×').appendTo($row);
            $('<span class="checkout-item-name"></span>').text(item.name).appendTo($row);
            $('<span class="checkout-item-total"></span>').text(peso(item.price * item.quantity)).appendTo($row);
            $items.append($row);
        });

        refreshCheckoutTotals();
    }

    function confirmLabel() {
        return paymentMethod === 'Cash'
            ? (fulfillmentMethod() === 'Delivery' ? 'Place order · Cash on delivery' : 'Place order · Pay at counter')
            : 'Place order';
    }

    function refreshCheckoutTotals() {
        const totals = totalsNow();

        const cups = cart.reduce((sum, item) => sum + (cupIds.has(Number(item.id)) ? item.quantity : 0), 0);
        $('#loyalty-estimate').text('This order earns ' + cups + ' point(s) after fulfillment.');
        $('#receipt-discount-label').text(totals.reward ? 'Loyalty reward (' + totals.rewardPoints + ' points)' : 'Senior / PWD discount');
        const merchandise = totals.reward && totals.rewardImage;
        $('#receipt-merchandise').toggleClass('d-none', !merchandise);
        $('#receipt-merchandise-name').text(merchandise ? totals.rewardLabel + ' · ' + totals.rewardPoints + ' points' : '');
        $('#receipt-subtotal').text(peso(totals.sub));
        $('#receipt-total').text(peso(totals.total));
        $('#receipt-discount-row').toggleClass('d-none', totals.discount <= 0);
        $('#receipt-discount').text('−' + peso(totals.discount));
        $('#qr-amount').text(peso(totals.total));

        updateConfirmState();
    }

    function paymentIsReady() {
        return !!paymentMethod && (paymentMethod === 'Cash' || $('#qr-confirm').is(':checked'));
    }

    function updateConfirmState() {
        const $confirm = $('#confirm-payment-btn');
        if ($confirm.data('busy')) return;
        $confirm.prop('disabled', !cart.length || !paymentIsReady());
    }

    function selectPaymentMethod(method) {
        if (method !== '' && !paymentMethods[method]) return;

        paymentMethod = method;
        const config = paymentMethods[method];
        $('input[name="payment-method"]').prop('checked', false).filter(function () { return this.value === method; }).prop('checked', true);
        $('#qr-confirm').prop('checked', false);

        $('.payment-method').removeClass('is-active').filter(function () {
            return $(this).find('input[name="payment-method"]').val() === method;
        }).addClass('is-active');

        if (!method) {
            $('#cash-panel, #qr-panel, #qr-note, #qr-confirm-row, #proof-upload').addClass('d-none');
            resetProof();
        } else if (method === 'Cash') {
            $('#cash-panel').removeClass('d-none');
            $('#qr-panel, #qr-note, #qr-confirm-row, #proof-upload').addClass('d-none');
            resetProof();
        } else {
            $('#cash-panel').addClass('d-none');
            $('#qr-panel, #qr-note, #qr-confirm-row, #proof-upload').removeClass('d-none');

            $('#qr-image').attr('src', config.image).attr('alt', config.alt);
            $('#qr-method-name').text(config.label);
            $('#qr-account').text(config.account).toggleClass('d-none', !config.account);
        }

        $('#checkout-error').addClass('d-none').text('');
        $('#confirm-payment-btn').text(confirmLabel());

        refreshCheckoutTotals();
    }

    function showCheckoutError(message) {
        $('#checkout-error').removeClass('d-none').text(message);
    }

    function hideCheckoutError() {
        $('#checkout-error').addClass('d-none').text('');
    }

    function resetProofPreview() {
        $('#proof-preview').addClass('d-none').empty();

        if (proofPreviewUrl) {
            URL.revokeObjectURL(proofPreviewUrl);
            proofPreviewUrl = null;
        }
    }

    function resetProof() {
        $('#payment-proof').val('');
        resetProofPreview();
    }

    function selectedProofFile() {
        const input = document.getElementById('payment-proof');
        return input && input.files && input.files[0] ? input.files[0] : null;
    }

    function resetCheckoutFields() {
        $('#redeem-loyalty').prop('checked', false);
        $('#senior-pwd').prop('checked', false).trigger('change');

        $('#pickup-at').val('');
        $('input[name="pickup-timing"][value="now"]').prop('checked', true).trigger('change');
        $('input[name="fulfillment-method"][value="Pickup"]').prop('checked', true).trigger('change');
        $('.delivery-required, #delivery-landmark').val('');
        $('#qr-confirm').prop('checked', false);
        resetProof();
    }

    function openCheckout() {
        if (!cart.length) {
            showToast('Your cart is empty.', { icon: '!', tone: 'warning' });
            return;
        }

        if (!signedIn) {
            window.location.href = 'customer/login.php';
            return;
        }

        renderCheckout();
        resetCheckoutFields();
        hideCheckoutError();

        $.getJSON('customer/loyalty.php').done(data => { rewards = data.rewards; updateLoyaltyBalance(data.balance); });
        closeCart({ restoreFocus: false });
        $checkout.addClass('is-open').attr('aria-hidden', 'false');
        syncScrollLock();

        selectPaymentMethod('');

        window.setTimeout(function () {
            document.querySelector('input[name="payment-method"]')?.focus();
        }, 180);
    }

    function closeCheckout() {
        $checkout.removeClass('is-open').attr('aria-hidden', 'true');
        hideCheckoutError();
        syncScrollLock();
    }

    function closeConfirmation() {
        $confirmation.removeClass('is-open').attr('aria-hidden', 'true');
        syncScrollLock();
    }

    $('#checkout-btn').on('click', openCheckout);

    $('#cancel-receipt-btn').on('click', closeCheckout);

    $('#back-to-cart-btn').on('click', function () {
        closeCheckout();
        openCart();
    });

    let discountPreviewUrl = null;
    function resetDiscountProof() {
        $('#discount-proof').val('');
        $('#discount-proof-preview').addClass('d-none').removeAttr('src');
        if (discountPreviewUrl) URL.revokeObjectURL(discountPreviewUrl);
        discountPreviewUrl = null;
    }
    function fulfillmentMethod() {
        return $('input[name="fulfillment-method"]:checked').val() || 'Pickup';
    }
    function syncPickupTiming() {
        const later = fulfillmentMethod() === 'Pickup' && $('input[name="pickup-timing"]:checked').val() === 'later';
        $('#pickup-schedule').toggleClass('d-none', !later);
        $('#pickup-at').prop('disabled', !later).prop('required', later);
        if (!later) $('#pickup-at').val('');
    }
    $('input[name="pickup-timing"]').on('change', syncPickupTiming);
    $('input[name="fulfillment-method"]').on('change', function () {
        const delivery = fulfillmentMethod() === 'Delivery';
        $('#delivery-address-panel').toggleClass('d-none', !delivery);
        $('.delivery-required').prop('required', delivery);
        $('#pickup-time-panel').toggleClass('d-none', delivery);
        syncPickupTiming();
        if (delivery) $('#pickup-at').val('');
        const $cashOption = $('input[name="payment-method"][value="Cash"]').closest('.payment-method');
        $cashOption.find('.payment-method-name').text(delivery ? 'COD' : 'Cash');
        $cashOption.find('.payment-method-note').text(delivery ? 'Pay the rider upon delivery' : 'Pay at the counter');
        $('#cash-panel .cash-feedback').empty()
            .append($('<strong class="d-block mb-1"></strong>').text(delivery ? 'Pay in cash when your order arrives.' : 'Please proceed to the counter to pay.'))
            .append(document.createTextNode(delivery ? 'Place your order, then pay the rider upon delivery.' : 'Place your order, then show your order number to the cashier and pay in cash.'))
            .append($('<span class="d-block mt-2 small"></span>').text('Your order number will appear after you place the order.'));
        $('#confirm-payment-btn').text(confirmLabel());
    });
    $('#loyalty-reward').on('change', function () {
        const reward = selectedReward();
        $('#reward-merchandise-preview').toggleClass('d-none', !reward.image);
        if (reward.image) $('#reward-merchandise-preview img').attr('src', reward.image).attr('alt', reward.label);
        $('#reward-merchandise-preview span').text(reward.label);
        updateLoyaltyBalance(loyaltyBalance);
    });
    $('#redeem-loyalty').on('change', function () {
        if ($(this).is(':checked')) $('#senior-pwd').prop('checked', false).trigger('change');
        refreshCheckoutTotals();
    });
    $('#senior-pwd').on('change', function () {
        const enabled = $(this).is(':checked');
        if (enabled) $('#redeem-loyalty').prop('checked', false);
        $('#discount-proof-upload').toggleClass('d-none', !enabled);
        $('#discount-proof').prop('required', enabled);
        if (!enabled) resetDiscountProof();
        refreshCheckoutTotals();
    });
    $('#discount-proof').on('change', function () {
        const file = this.files[0];
        $('#discount-proof-preview').addClass('d-none').removeAttr('src');
        if (discountPreviewUrl) URL.revokeObjectURL(discountPreviewUrl);
        discountPreviewUrl = null;
        if (!file) return;
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) {
            $(this).val('');
            showCheckoutError('Upload a JPG, PNG, or WebP ID photo, 5 MB or smaller.');
            return;
        }
        hideCheckoutError();
        discountPreviewUrl = URL.createObjectURL(file);
        $('#discount-proof-preview').attr('src', discountPreviewUrl).removeClass('d-none');
    });


    $('.payment-methods').on('change', 'input[name="payment-method"]', function () {
        selectPaymentMethod(String($(this).val()));
    });

    $('#qr-confirm').on('change', updateConfirmState);

    $('#payment-proof').on('change', function () {
        resetProofPreview();

        const file = selectedProofFile();
        if (!file) return;

        if (!PROOF_TYPES.includes(file.type)) {
            $(this).val('');
            showCheckoutError('Proof of payment must be a JPG, PNG, or WebP image.');
            return;
        }

        if (file.size > MAX_PROOF_BYTES) {
            $(this).val('');
            showCheckoutError('Proof of payment must be 5 MB or smaller.');
            return;
        }

        hideCheckoutError();

        proofPreviewUrl = URL.createObjectURL(file);

        $('#proof-preview')
            .removeClass('d-none')
            .append($('<img alt="Selected proof of payment">').attr('src', proofPreviewUrl))
            .append($('<span class="proof-preview-name"></span>').text(file.name));
    });

    $('#confirm-payment-btn').on('click', function () {
        const $button = $(this);
        if ($button.data('busy')) return;

        const totals = totalsNow();
        if (fulfillmentMethod() === 'Pickup' && $('input[name="pickup-timing"]:checked').val() === 'later' && !document.getElementById('pickup-at').value) {
            showCheckoutError('Choose a date and time for pickup later.');
            document.getElementById('pickup-at').focus();
            return;
        }
        const method = paymentMethods[paymentMethod];
        if (!method) {
            showCheckoutError('Choose a payment method.');
            return;
        }
        let cash;

        if (!cart.length) {
            showCheckoutError('Your cart is empty. Add a product and try again.');
            return;
        }

        if (paymentMethod === 'Cash') {
            cash = 0;
        } else {
            if (!$('#qr-confirm').is(':checked')) {
                showCheckoutError('Please confirm that you have sent the ' + method.label + ' payment.');
                return;
            }

            cash = totals.total;
        }

        const change = 0;

        hideCheckoutError();
        $button.data('busy', true).prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Processing…');

        if (paymentMethod !== 'Cash' && !selectedProofFile()) {
            showCheckoutError('Upload a payment receipt for staff review.');
            $button.data('busy', false).prop('disabled', false).html(confirmLabel());
            return;
        }
        const missingAddress = $('.delivery-required').toArray().find(field => !field.value.trim() || !field.checkValidity());
        if (fulfillmentMethod() === 'Delivery' && missingAddress) {
            showCheckoutError('Complete your street address, barangay, city / municipality, and province.');
            missingAddress.focus();
            $button.data('busy', false).prop('disabled', false).html(confirmLabel());
            return;
        }
        if (totals.reward && toCents(totals.sub) < (selectedReward().minimum_spend_cents || 0)) {
            showCheckoutError('This reward requires a minimum spend of ' + peso(selectedReward().minimum_spend_cents / 100) + ' before discounts.');
            $button.data('busy', false).prop('disabled', false).html(confirmLabel());
            return;
        }
        if (totals.reward && (loyaltyBalance < selectedReward().points || toCents(totals.sub) <= selectedReward().cents)) {
            showCheckoutError('You need enough points, and your order total must exceed the selected reward amount.');
            $button.data('busy', false).prop('disabled', false).html(confirmLabel());
            return;
        }
        const discountFile = document.getElementById('discount-proof').files[0];
        if ($('#senior-pwd').is(':checked') && !discountFile) {
            showCheckoutError('Upload your Senior or PWD ID photo to use the discount.');
            $button.data('busy', false).prop('disabled', false).html(confirmLabel());
            return;
        }
        const formData = new FormData();
        if ($('#senior-pwd').is(':checked') && discountFile) formData.append('discount_proof', discountFile);
        formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);

        formData.append('payload', JSON.stringify({
            items: cart.map(item => ({
                id: Number(item.id),
                quantity: item.quantity
            })),
            subtotal: totals.sub,
            discount: totals.discount,
            redeem_loyalty: totals.reward,
            loyalty_reward: $('#loyalty-reward').val(),
            total: totals.total,
            cash_received: cash,
            payment_method: paymentMethod,
            pickup_at: fulfillmentMethod() === 'Pickup' ? document.getElementById('pickup-at').value : '',
            fulfillment_method: fulfillmentMethod(),
            delivery_address_parts: fulfillmentMethod() === 'Delivery' ? {
                street: $('#delivery-address').val().trim(),
                barangay: $('#delivery-barangay').val().trim(),
                city: $('#delivery-city').val().trim(),
                province: $('#delivery-province').val().trim(),
                landmark: $('#delivery-landmark').val().trim()
            } : null
        }));

        const proofFile = paymentMethod === 'Cash' ? null : selectedProofFile();
        if (proofFile) formData.append('payment_proof', proofFile);

        $.ajax({
            url: 'orders/create.php',
            method: 'POST',
            dataType: 'json',
            processData: false,
            contentType: false,
            data: formData
        })
        .done(function (savedOrder) {
            if (!savedOrder || !savedOrder.ok || !savedOrder.order_id) {
                showCheckoutError(savedOrder && savedOrder.message ? savedOrder.message : 'The order could not be completed.');
                return;
            }

            rewards = savedOrder.rewards || rewards;
            updateLoyaltyBalance(savedOrder.loyalty_balance);
            completeOrder(savedOrder.order_id, totals, cash, change, paymentMethod);
        })
        .fail(function (xhr) {
            if (xhr.status === 401) {
                showCheckoutError('Please sign in before placing an order. Your cart is saved.');
                window.setTimeout(function () {
                    window.location.href = 'customer/login.php';
                }, 1200);
                return;
            }

            const message = (xhr.responseJSON && xhr.responseJSON.message)
                ? xhr.responseJSON.message
                : 'Could not place your order. Please try again.';

            showCheckoutError(message);
        })
        .always(function () {
            $button.data('busy', false).html(confirmLabel());
            updateConfirmState();
        });
    });

    function completeOrder(orderId, totals, cash, change, method) {
        const cashOnDelivery = method === 'Cash' && fulfillmentMethod() === 'Delivery';
        $('#cash-order-number-panel p').text(cashOnDelivery ? 'Keep this order number and pay the rider in cash upon delivery.' : 'Please show this number at the counter and pay in cash.');
        const $ongoing = $('#ongoing-order-indicator');
        const ongoingCount = (Number($ongoing.attr('data-count')) || 0) + 1;
        $ongoing.attr('data-count', ongoingCount).text('You have ' + ongoingCount + ' ongoing order' + (ongoingCount === 1 ? '' : 's'));
        $('#cash-order-number-panel').toggleClass('d-none', method !== 'Cash');
        $('#cash-order-number').text(method === 'Cash' ? '#' + orderId : '');
        const $summary = $('#confirmation-body').empty();
        const $receipt = $('<section class="receipt-preview"></section>');
        const $watermark = $('<img class="receipt-watermark" alt="" aria-hidden="true">')
            .attr('src', new URL('assets/Bestcoffee.jpg', window.location.href).href);
        const $content = $('<div class="receipt-preview-content"></div>');
        const $brand = $('<header class="receipt-brand"></header>');

        $('<img class="receipt-brand-logo" alt="Philippines’ Best Coffee logo">')
            .attr('src', new URL('assets/Bestcoffee.jpg', window.location.href).href)
            .appendTo($brand);
        $('<h3 class="title-font"></h3>')
            .text("Philippines' Best Coffee")
            .appendTo($brand);
        $('<p></p>').text('Brewed with love · Made to share').appendTo($brand);

        const $meta = $('<div class="receipt-meta"></div>');
        $('<div><span>Order</span></div>')
            .append($('<strong></strong>').text('#' + orderId))
            .appendTo($meta);
        $('<div class="text-end"><span>Date</span></div>')
            .append($('<strong></strong>').text(new Date().toLocaleString()))
            .appendTo($meta);

        const $items = $('<div class="receipt-preview-items"></div>');
        cart.forEach(function (item) {
            const $row = $('<div class="receipt-preview-item"></div>');
            const $details = $('<div class="receipt-item-details"></div>');

            $('<strong></strong>').text(item.name).appendTo($details);
            $('<span></span>')
                .text(item.quantity + ' × ' + peso(item.price))
                .appendTo($details);
            $('<strong class="receipt-item-total"></strong>')
                .text(peso(item.price * item.quantity))
                .appendTo($row);
            $row.prepend($details).appendTo($items);
        });

        const $totals = $('<div class="receipt-preview-totals"></div>');
        const appendTotal = function (label, amount, className) {
            const $row = $('<div class="receipt-total-row"></div>');
            if (className) $row.addClass(className);
            $('<span></span>').text(label).appendTo($row);
            $('<strong></strong>').text(amount).appendTo($row);
            $row.appendTo($totals);
        };

        appendTotal('Subtotal', peso(totals.sub));
        if (totals.reward && totals.rewardImage) {
            appendTotal('Reward: ' + totals.rewardLabel, totals.rewardPoints + ' points', 'receipt-discount');
        }
        if (totals.discount > 0) {
            appendTotal(totals.reward ? 'Loyalty reward (' + totals.rewardPoints + ' points)' : 'Senior / PWD discount', '−' + peso(totals.discount), 'receipt-discount');
        }
        appendTotal('Total', peso(totals.total), 'receipt-grand-total');

        if (method === 'Cash') {
            appendTotal('Payment method', cashOnDelivery ? 'Cash on delivery (COD)' : 'Cash');
            if (cashOnDelivery) appendTotal('Amount due on delivery', peso(totals.total));
            appendTotal('Cash received', peso(cash));
            appendTotal('Change', peso(change));
        } else {
            appendTotal('Payment method', method);
            appendTotal('Amount paid', peso(totals.total));
        }

        $content.append($brand, $meta, $items, $totals);
        $('<p class="receipt-thanks"></p>')
            .text('Thank you for stopping by!')
            .appendTo($content);
        $('<p class="receipt-footer-note"></p>')
            .text('We hope to see you again soon')
            .appendTo($content);
        $receipt.append($watermark, $content);

        $summary.append($receipt);
        $('#confirmation-message').text(
            method === 'Cash'
                ? 'Order #' + orderId + (cashOnDelivery ? ' has been saved to your account. Please pay the rider in cash upon delivery.' : ' has been saved to your account. Please pay at the counter.')
                : 'Order #' + orderId + ' has been saved · payment received via ' + method + '.'
        );

        cart = [];
        saveCart();
        updateCount();

        $('#checkout-modal').removeClass('is-open').attr('aria-hidden', 'true');
        resetCheckoutFields();
        hideCheckoutError();

        $confirmation.addClass('is-open').attr('aria-hidden', 'false');
        syncScrollLock();

        document.getElementById('confirmation-body')?.scrollIntoView({ block: 'nearest' });

        showToast('Order #' + orderId + ' placed successfully.', { icon: '✓' });
    }

    $('#close-confirmation-btn').on('click', closeConfirmation);

    $('#print-receipt-btn').on('click', function () {
        window.print();
    });

    // Overlay behaviours

    $checkout.on('mousedown', function (event) {
        if (event.target === this) {
            closeCheckout();
            openCart();
        }
    });

    $confirmation.on('mousedown', function (event) {
        if (event.target === this) closeConfirmation();
    });

    $(document).on('keydown', function (event) {
        if (event.key !== 'Escape') return;

        if ($confirmation.hasClass('is-open')) {
            closeConfirmation();
        } else if ($checkout.hasClass('is-open')) {
            closeCheckout();
            openCart();
        } else if ($drawer.hasClass('show')) {
            closeCart();
        }
    });

    window.addEventListener('beforeprint', function () {
        const receipt = document.querySelector('.receipt-preview');
        if (!receipt) return;

        receipt.style.zoom = '';
        const printableHeight = 1000;
        const receiptHeight = receipt.getBoundingClientRect().height;

        if (receiptHeight > printableHeight) {
            receipt.style.zoom = String(printableHeight / receiptHeight);
        }
    });

    window.addEventListener('afterprint', function () {
        const receipt = document.querySelector('.receipt-preview');
        if (receipt) receipt.style.zoom = '';
    });

    updateCount();
    updateLoyaltyBalance(loyaltyBalance);
});

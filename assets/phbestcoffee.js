$(function () {
    const $slides = $('.slide');
    let slideIndex = 0;
    let cart = [];

    try {
        const saved = localStorage.getItem('phbestcoffee-cart');
        cart = saved ? JSON.parse(saved) : [];
        if (!Array.isArray(cart)) cart = [];
    } catch (error) {
        cart = [];
    }

    const peso = amount => '₱' + Number(amount || 0).toFixed(2);

    const saveCart = () => {
        try {
            localStorage.setItem('phbestcoffee-cart', JSON.stringify(cart));
        } catch (error) {}
    };

    const itemCount = () => cart.reduce((sum, item) => sum + item.quantity, 0);
    const subtotalCents = () => cart.reduce(
        (sum, item) => sum + Math.round(item.price * 100) * item.quantity,
        0
    );
    const subtotal = () => subtotalCents() / 100;

    function cartIsValid() {
        const seenIds = new Set();

        return cart.length > 0 && cart.length <= 50 && cart.every(function (item) {
            const id = Number(item.id);
            const priceCents = Number(item.price) * 100;

            if (
                !Number.isSafeInteger(id) || id < 1 ||
                !Number.isInteger(item.quantity) || item.quantity < 1 || item.quantity > 50 ||
                typeof item.name !== 'string' || item.name.trim() === '' ||
                !Number.isFinite(priceCents) || !Number.isSafeInteger(Math.round(priceCents)) ||
                Math.abs(priceCents - Math.round(priceCents)) > 0.0001 ||
                item.price < 0 || seenIds.has(id)
            ) {
                return false;
            }

            seenIds.add(id);
            return true;
        });
    }

    if (cart.length && !cartIsValid()) {
        cart = [];
        saveCart();
        alert('Your saved cart contained invalid items or quantities and was cleared. Please add the products again.');
    }

    function inputAmountCents(value) {
        const text = String(value).trim();
        if (!/^\d+(?:\.\d{1,2})?$/.test(text)) return null;

        const amount = Number(text);
        if (!Number.isFinite(amount) || amount > 99999999.99) return null;

        return Math.round(amount * 100);
    }

    function showSlide(index) {
        $slides.removeClass('active').eq(index).addClass('active');
    }

    if ($slides.length) {
        showSlide(slideIndex);
        setInterval(function () {
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

    function updateCount() {
        $('#cart-count').text(itemCount());
    }

    function renderCart() {
        const $items = $('#cart-items').empty();

        if (!cart.length) {
            $items.html('<p class="text-secondary">Your cart is empty.</p>');
        } else {
            cart.forEach(function (item, index) {
                const $row = $('<div class="d-flex justify-content-between align-items-center gap-3 mb-3 border-bottom pb-3"></div>');
                const $details = $('<div></div>');
                const $actions = $('<div class="d-flex align-items-center gap-1 flex-shrink-0"></div>');

                $('<div class="fw-semibold"></div>').text(item.name).appendTo($details);
                $('<div class="small text-secondary"></div>')
                    .text(peso(item.price) + ' × ' + item.quantity)
                    .appendTo($details);

                $('<button type="button" class="btn btn-sm btn-outline-secondary" aria-label="Decrease quantity">−</button>')
                    .on('click', function () {
                        item.quantity = Math.max(1, item.quantity - 1);
                        saveCart();
                        renderCart();
                        updateCount();
                    })
                    .appendTo($actions);

                $('<button type="button" class="btn btn-sm btn-outline-secondary" aria-label="Increase quantity">+</button>')
                    .on('click', function () {
                        if (item.quantity >= 50) {
                            alert('A maximum of 50 units is allowed per product.');
                            return;
                        }
                        item.quantity += 1;
                        saveCart();
                        renderCart();
                        updateCount();
                    })
                    .prop('disabled', item.quantity >= 50)
                    .appendTo($actions);

                $('<button type="button" class="btn btn-sm btn-outline-danger">Remove</button>')
                    .on('click', function () {
                        cart.splice(index, 1);
                        saveCart();
                        renderCart();
                        updateCount();
                    })
                    .appendTo($actions);

                $row.append($details, $actions).appendTo($items);
            });
        }

        $('#cart-total').text('Total: ' + peso(subtotal()));
    }

    $('.quantity-minus').on('click', function () {
        const $input = $(this).siblings('.menu-quantity-input');
        $input.val(Math.max(1, (parseInt($input.val(), 10) || 1) - 1));
    });

    $('.quantity-plus').on('click', function () {
        const $input = $(this).siblings('.menu-quantity-input');
        $input.val(Math.min(50, (parseInt($input.val(), 10) || 1) + 1));
    });

    $('.menu-quantity-input').on('change', function () {
        const quantity = Number($(this).val());
        $(this).val(Number.isInteger(quantity) ? Math.min(50, Math.max(1, quantity)) : 1);
    });

    $('.add-cart').on('click', function () {
        const $button = $(this);

        if ($button.is(':disabled') || $button.attr('data-available') === 'false') {
            alert($button.data('name') + ' is currently unavailable.');
            return;
        }

        const id = Number($button.data('id'));
        const name = String($button.data('name'));
        const price = Number($button.data('price'));
        const $quantityInput = $button.closest('.product-card').find('.menu-quantity-input');
        const rawQuantity = Number($quantityInput.val());

        if (
            !Number.isSafeInteger(id) || id < 1 ||
            !Number.isFinite(price) || price < 0 ||
            !Number.isInteger(rawQuantity) || rawQuantity < 1 || rawQuantity > 50
        ) {
            alert('Enter a whole-number quantity from 1 to 50.');
            $quantityInput.trigger('focus');
            return;
        }

        if (cart.length >= 50 && !cart.some(item => Number(item.id) === id)) {
            alert('Your cart can contain up to 50 different products.');
            return;
        }

        const quantity = rawQuantity;
        const found = cart.find(item => Number(item.id) === id);

        if (found) {
            if (found.quantity + quantity > 50) {
                alert('A maximum of 50 units is allowed per product.');
                return;
            }
            found.quantity += quantity;
        } else {
            cart.push({ id, name, price, quantity });
        }

        $quantityInput.val(1);
        saveCart();
        updateCount();

        alert(quantity + ' × ' + name + ' added to cart!');
    });

    $('#cart-open-btn').on('click', function () {
        renderCart();
        $('#cart-modal').stop(true, true).fadeIn(150);
    });

    $('#close-cart-btn').on('click', function () {
        $('#cart-modal').fadeOut(150);
    });

    function totalsNow() {
        const subCents = subtotalCents();
        const discountCents = $('#senior-pwd').is(':checked')
            ? Math.round(subCents * 0.20)
            : 0;
        const totalCents = subCents - discountCents;

        return {
            sub: subCents / 100,
            discount: discountCents / 100,
            total: totalCents / 100
        };
    }

    function renderReceiptItems() {
        const $items = $('#receipt-items').empty();

        cart.forEach(function (item) {
            const $row = $('<div class="d-flex justify-content-between gap-3 mb-2"></div>');

            $('<span></span>')
                .text(item.name + ' × ' + item.quantity)
                .appendTo($row);

            $('<span></span>')
                .text(peso(item.price * item.quantity))
                .appendTo($row);

            $items.append($row);
        });

        updateReceiptTotal();
    }

    function updateReceiptTotal() {
        const totals = totalsNow();
        $('#receipt-total').text('Total: ' + peso(totals.total));
        return totals;
    }

    $('#checkout-btn').on('click', function () {
        if (!cartIsValid()) {
            alert(cart.length ? 'Your cart has invalid items or quantities. Remove them and try again.' : 'Your cart is empty!');
            return;
        }

        renderReceiptItems();
        $('#cash-amount').val('');
        $('#change-display').text('');
        $('#cart-modal').fadeOut(100);
        $('#receipt-modal').fadeIn(150);
    });

    $('#senior-pwd').on('change', updateReceiptTotal);

    $('#cash-amount').on('input', function () {
        const value = $(this).val();
        const cashCents = inputAmountCents(value);
        const total = totalsNow().total;

        $('#change-display').text(
            value !== '' && cashCents !== null && cashCents >= Math.round(total * 100)
                ? 'Change: ' + peso((cashCents - Math.round(total * 100)) / 100)
                : ''
        );
    });

    $('#confirm-payment-btn').on('click', function () {
        const $button = $(this);

        if ($button.prop('disabled')) return;

        if (!cartIsValid()) {
            alert(cart.length ? 'Your cart has invalid items or quantities. Review your cart and try again.' : 'Your cart is empty!');
            return;
        }

        const totals = updateReceiptTotal();
        const rawCash = $('#cash-amount').val();
        const cashCents = inputAmountCents(rawCash);

        if (cashCents === null || cashCents < 1) {
            alert('Enter a valid cash amount with no more than two decimal places.');
            return;
        }

        if (cashCents < Math.round(totals.total * 100)) {
            alert('Insufficient cash. Please enter an amount equal to or greater than the total.');
            return;
        }

        const cash = cashCents / 100;
        const change = (cashCents - Math.round(totals.total * 100)) / 100;

        $button.prop('disabled', true).text('Processing...');

        $.ajax({
            url: 'orders/create.php',
            method: 'POST',
            contentType: 'application/json; charset=utf-8',
            dataType: 'json',
            data: JSON.stringify({
                items: cart.map(item => ({
                    id: Number(item.id),
                    quantity: item.quantity
                })),
                subtotal: totals.sub,
                discount: totals.discount,
                total: totals.total,
                cash_received: cash
            })
        })
        .done(function (savedOrder) {
            if (!savedOrder || !savedOrder.ok || !savedOrder.order_id) {
                alert(savedOrder?.message || 'The order could not be completed.');
                return;
            }

            completeOrder(savedOrder.order_id, totals, cash, change);
        })
        .fail(function (xhr) {
            let message = 'Could not place your order. Please try again.';

            if (xhr.responseJSON?.message) {
                message = xhr.responseJSON.message;
            }

            if (xhr.status === 401) {
                alert('Please sign in before placing an order. Your cart will remain saved.');
                window.location.href = 'customer/login.php';
                return;
            }

            alert(message);
        })
        .always(function () {
            $button.prop('disabled', false).text('Confirm Payment');
        });
    });

    function completeOrder(orderId, totals, cash, change) {
        const $summary = $('#confirmation-body').empty();
        const $receipt = $('<section class="receipt-preview"></section>');
        const $watermark = $('<img class="receipt-watermark" alt="" aria-hidden="true">')
            .attr('src', new URL('Bestcoffee.jpg', window.location.href).href);
        const $content = $('<div class="receipt-preview-content"></div>');
        const $brand = $('<header class="receipt-brand"></header>');

        $('<img class="receipt-brand-logo" alt="Philippines’ Best Coffee logo">')
            .attr('src', new URL('Bestcoffee.jpg', window.location.href).href)
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
        if (totals.discount > 0) {
            appendTotal('Senior / PWD discount', '−' + peso(totals.discount), 'receipt-discount');
        }
        appendTotal('Total', peso(totals.total), 'receipt-grand-total');
        appendTotal('Cash received', peso(cash));
        appendTotal('Change', peso(change));

        $content.append($brand, $meta, $items, $totals);
        $('<p class="receipt-thanks"></p>')
            .text('Thank you for stopping by!')
            .appendTo($content);
        $('<p class="receipt-footer-note"></p>')
            .text('We hope to see you again soon')
            .appendTo($content);
        $receipt.append($watermark, $content);

        $('<p class="text-success small text-center fw-semibold mb-3"></p>')
            .text('Your order has been saved to your account.')
            .appendTo($summary);
        $summary.append($receipt);

        cart = [];
        saveCart();
        updateCount();

        $('#receipt-modal').fadeOut(100);
        $('#cash-amount').val('');
        $('#senior-pwd').prop('checked', false);
        $('#change-display').empty();
        $('#confirmation-modal').fadeIn(150);
        window.setTimeout(function () {
            window.print();
        }, 500);
    }

    $('#cancel-receipt-btn').on('click', function () {
        $('#receipt-modal').fadeOut(150);
    });

    $('#close-confirmation-btn').on('click', function () {
        $('#confirmation-modal').fadeOut(150);
    });

    $('#print-receipt-btn').on('click', function () {
        window.print();
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

    $('.cart-modal').on('click', function (event) {
        if (event.target === this) {
            $(this).fadeOut(150);
        }
    });

    updateCount();
});
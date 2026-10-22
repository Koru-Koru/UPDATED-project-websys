$(function () {
    const $slides = $('.slide');
    let slideIndex = 0;
    let cart = [];
    let lastReceipt = '';

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

    const itemCount = () => cart.reduce((sum, item) => sum + Number(item.quantity || 0), 0);
    const subtotal = () => cart.reduce((sum, item) => sum + Number(item.price || 0) * Number(item.quantity || 0), 0);

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
                        item.quantity = Math.min(999, item.quantity + 1);
                        saveCart();
                        renderCart();
                        updateCount();
                    })
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
        $input.val(Math.min(99, (parseInt($input.val(), 10) || 1) + 1));
    });

    $('.menu-quantity-input').on('change', function () {
        const quantity = parseInt($(this).val(), 10);
        $(this).val(Number.isFinite(quantity) ? Math.min(99, Math.max(1, quantity)) : 1);
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
        const quantity = Math.min(99, Math.max(1, parseInt($quantityInput.val(), 10) || 1));

        const found = cart.find(item => Number(item.id) === id);

        if (found) {
            found.quantity = Math.min(999, found.quantity + quantity);
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
        const sub = subtotal();
        const discount = $('#senior-pwd').is(':checked') ? sub * 0.20 : 0;

        return {
            sub: Number(sub.toFixed(2)),
            discount: Number(discount.toFixed(2)),
            total: Number((sub - discount).toFixed(2))
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
        if (!cart.length) {
            alert('Your cart is empty!');
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
        const cash = Number(value);
        const total = totalsNow().total;

        $('#change-display').text(
            value !== '' && Number.isFinite(cash) && cash >= total
                ? 'Change: ' + peso(cash - total)
                : ''
        );
    });

    $('#confirm-payment-btn').on('click', function () {
        const $button = $(this);

        if ($button.prop('disabled')) return;

        if (!cart.length) {
            alert('Your cart is empty!');
            return;
        }

        const totals = updateReceiptTotal();
        const rawCash = $('#cash-amount').val();
        const cash = Number(rawCash);

        if (rawCash === '' || !Number.isFinite(cash) || cash <= 0) {
            alert('Please enter a valid cash amount.');
            return;
        }

        if (cash < totals.total) {
            alert('Insufficient cash. Please enter an amount equal to or greater than the total.');
            return;
        }

        const change = Number((cash - totals.total).toFixed(2));

        $button.prop('disabled', true).text('Processing...');

        $.ajax({
    url: 'orders/create.php',
    method: 'POST',
    contentType: 'application/json; charset=utf-8',
    dataType: 'json',
    data: JSON.stringify({
        items: cart.map(item => ({
            id: Number(item.id || 0),
            quantity: Number(item.quantity)
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
        const receipt = [
            "Philippines' Best Coffee",
            'Receipt - Order #' + orderId,
            '--------------------------------',
            ...cart.map(item =>
                item.name + ' × ' + item.quantity + ' - ' +
                peso(item.price * item.quantity)
            ),
            '--------------------------------',
            'Subtotal: ' + peso(totals.sub)
        ];

        if (totals.discount > 0) {
            receipt.push('Senior/PWD Discount (20%): -' + peso(totals.discount));
        }

        receipt.push(
            'Total: ' + peso(totals.total),
            'Cash: ' + peso(cash),
            'Change: ' + peso(change),
            'Thank you for your purchase!'
        );

        lastReceipt = receipt.join('\n');

        const $summary = $('#confirmation-body').empty();

        $('<p class="text-success fw-semibold"></p>')
            .text('Your order #' + orderId + ' has been saved to your account.')
            .appendTo($summary);

        cart.forEach(function (item) {
            const $row = $('<div class="d-flex justify-content-between gap-3 mb-2"></div>');

            $('<span></span>')
                .text(item.name + ' × ' + item.quantity)
                .appendTo($row);

            $('<span></span>')
                .text(peso(item.price * item.quantity))
                .appendTo($row);

            $summary.append($row);
        });

        $('<hr>').appendTo($summary);

        [
            ['Subtotal', totals.sub],
            ['Senior/PWD Discount', -totals.discount],
            ['Total', totals.total],
            ['Cash', cash],
            ['Change', change]
        ].forEach(function (entry) {
            if (entry[0] === 'Senior/PWD Discount' && !totals.discount) return;

            const $row = $('<div class="d-flex justify-content-between gap-3 mb-1"></div>');

            $('<span></span>').text(entry[0]).appendTo($row);

            $('<span></span>')
                .text((entry[1] < 0 ? '-' : '') + peso(Math.abs(entry[1])))
                .appendTo($row);

            $summary.append($row);
        });

        cart = [];
        saveCart();
        updateCount();

        $('#receipt-modal').fadeOut(100);
        $('#cash-amount').val('');
        $('#senior-pwd').prop('checked', false);
        $('#change-display').empty();
        $('#confirmation-modal').fadeIn(150);
    }

    $('#cancel-receipt-btn').on('click', function () {
        $('#receipt-modal').fadeOut(150);
    });

    $('#close-confirmation-btn').on('click', function () {
        $('#confirmation-modal').fadeOut(150);
    });

    $('#print-receipt-btn').on('click', function () {
        const receiptWindow = window.open('', '_blank');

        if (!receiptWindow) {
            alert(lastReceipt);
            return;
        }

        const safeText = lastReceipt
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');

        const receiptContent = `<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receipt</title>
    <style>
        body {
            font: 14px Arial, sans-serif;
            max-width: 380px;
            margin: 25px auto;
            white-space: pre-line;
        }
        h1 {
            font-size: 18px;
        }
    </style>
</head>
<body>
    <h1>Philippines' Best Coffee</h1>
    ${safeText.replace(/^Philippines' Best Coffee\n/, '')}
</body>
</html>`;

        receiptWindow.document.write(receiptContent);
        receiptWindow.document.close();
        receiptWindow.focus();
        receiptWindow.print();
    });

    $('.cart-modal').on('click', function (event) {
        if (event.target === this) {
            $(this).fadeOut(150);
        }
    });

    updateCount();
});
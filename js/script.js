document.addEventListener('DOMContentLoaded', function() {
    const modalTexts = window.siteModalTexts || {
        messageTitle: 'Message',
        confirmTitle: 'Please confirm',
        ok: 'Close',
        yes: 'Yes',
        no: 'No'
    };
    let siteConfirmCallback = null;

    function ensureSiteModal() {
        let modal = document.getElementById('siteMessageModal');

        if (modal) {
            return modal;
        }

        modal = document.createElement('div');
        modal.id = 'siteMessageModal';
        modal.className = 'site-modal';
        modal.hidden = true;
        modal.innerHTML = [
            '<div class="site-modal-backdrop" data-site-modal-close></div>',
            '<div class="site-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="siteMessageModalTitle">',
            '<button type="button" class="site-modal-close" data-site-modal-close aria-label="' + modalTexts.ok + '">X</button>',
            '<h3 id="siteMessageModalTitle"></h3>',
            '<p id="siteMessageModalText"></p>',
            '<div class="site-modal-actions">',
            '<button type="button" class="btn payment-secondary-btn" data-site-modal-close data-site-modal-no>' + modalTexts.no + '</button>',
            '<button type="button" class="btn" data-site-modal-close data-site-modal-ok>' + modalTexts.ok + '</button>',
            '</div>',
            '</div>'
        ].join('');
        document.body.appendChild(modal);

        modal.querySelectorAll('[data-site-modal-close]').forEach(function(button) {
            button.addEventListener('click', function() {
                const isConfirmOk = button.hasAttribute('data-site-modal-ok') && modal.dataset.mode === 'confirm';
                const callback = siteConfirmCallback;

                modal.hidden = true;
                document.body.classList.remove('site-modal-open');
                siteConfirmCallback = null;

                if (isConfirmOk && typeof callback === 'function') {
                    callback();
                }
            });
        });

        return modal;
    }

    window.showSiteModal = function(options) {
        const modal = ensureSiteModal();
        const title = modal.querySelector('#siteMessageModalTitle');
        const text = modal.querySelector('#siteMessageModalText');
        const noButton = modal.querySelector('[data-site-modal-no]');
        const okButton = modal.querySelector('[data-site-modal-ok]');

        modal.dataset.mode = 'message';
        modal.dataset.type = options && options.type ? options.type : 'info';
        title.textContent = (options && options.title) || modalTexts.messageTitle;
        text.textContent = (options && options.message) || '';
        noButton.hidden = true;
        okButton.textContent = modalTexts.ok;
        modal.hidden = false;
        document.body.classList.add('site-modal-open');
        okButton.focus();
    };

    window.showSiteConfirm = function(message, onConfirm, title) {
        const modal = ensureSiteModal();
        const titleEl = modal.querySelector('#siteMessageModalTitle');
        const text = modal.querySelector('#siteMessageModalText');
        const noButton = modal.querySelector('[data-site-modal-no]');
        const okButton = modal.querySelector('[data-site-modal-ok]');

        modal.dataset.mode = 'confirm';
        modal.dataset.type = 'warning';
        titleEl.textContent = title || modalTexts.confirmTitle;
        text.textContent = message || modalTexts.confirmTitle;
        noButton.hidden = false;
        okButton.textContent = modalTexts.yes;
        siteConfirmCallback = onConfirm;
        modal.hidden = false;
        document.body.classList.add('site-modal-open');
        okButton.focus();
    };

    const phoneInputs = document.querySelectorAll('input[name="phone"]');
    phoneInputs.forEach(function(input) {
        const error = document.createElement('small');
        error.className = 'phone-validation-error';
        error.textContent = 'Please enter a valid phone number';
        error.hidden = true;
        input.insertAdjacentElement('afterend', error);

        function validatePhone(showWhenEmpty) {
            const originalValue = input.value;
            const digitsOnly = originalValue.replace(/\D/g, '').slice(0, 10);

            if (originalValue !== digitsOnly) {
                input.value = digitsOnly;
            }

            const hasValue = digitsOnly.length > 0;
            const isValid = /^07[0-9]{8}$/.test(digitsOnly);
            const shouldShowError = (hasValue || showWhenEmpty) && !isValid;

            input.classList.toggle('is-invalid', shouldShowError);
            input.setAttribute('aria-invalid', shouldShowError ? 'true' : 'false');
            error.hidden = !shouldShowError;

            if (input.required && shouldShowError) {
                input.setCustomValidity('Please enter a valid phone number');
            } else {
                input.setCustomValidity('');
            }

            return isValid || (!input.required && !hasValue);
        }

        input.setAttribute('maxlength', '10');
        input.setAttribute('inputmode', 'numeric');
        input.setAttribute('pattern', '07[0-9]{8}');

        input.addEventListener('input', function() {
            validatePhone(false);
        });

        input.addEventListener('blur', function() {
            validatePhone(false);
        });

        if (input.form) {
            input.form.addEventListener('submit', function(e) {
                if (!validatePhone(true)) {
                    e.preventDefault();
                    input.reportValidity();
                }
            });
        }
    });

    document.addEventListener('paste', function(e) {
        const input = e.target;
        if (input instanceof HTMLInputElement && input.name === 'phone') {
            e.preventDefault();
            const text = (e.clipboardData || window.clipboardData).getData('text');
            input.value = text.replace(/\D/g, '').slice(0, 10);
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }
    });

    document.addEventListener('keydown', function(e) {
        const input = e.target;
        if (input instanceof HTMLInputElement && input.name === 'phone') {
            const allowedKeys = ['Backspace', 'Delete', 'Tab', 'ArrowLeft', 'ArrowRight', 'Home', 'End'];
            const isShortcut = e.ctrlKey || e.metaKey;

            if (allowedKeys.includes(e.key) || isShortcut) {
                return;
            }

            if (!/^[0-9]$/.test(e.key)) {
                e.preventDefault();
                input.dispatchEvent(new Event('input', { bubbles: true }));
            }
        }
    });

    const deleteLinks = document.querySelectorAll('a[href*="delete"]');
    deleteLinks.forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            window.showSiteConfirm(link.dataset.confirmMessage || modalTexts.confirmTitle, function() {
                window.location.href = link.href;
            });
        });
    });

    const messages = document.querySelectorAll('.message');
    messages.forEach(function(message) {
        const text = message.innerText.trim();
        const isError = message.classList.contains('error') || message.classList.contains('alert-danger');
        const isSuccess = message.classList.contains('success') || message.classList.contains('alert-success');

        if (text) {
            window.showSiteModal({
                title: modalTexts.messageTitle,
                message: text,
                type: isError ? 'error' : (isSuccess ? 'success' : 'info')
            });
            message.hidden = true;
        }
    });

    document.querySelectorAll('a[data-confirm-message]').forEach(function(link) {
        link.addEventListener('click', function(event) {
            event.preventDefault();
            window.showSiteConfirm(link.dataset.confirmMessage, function() {
                window.location.href = link.href;
            }, link.dataset.confirmTitle);
        });
    });

    document.querySelectorAll('form[data-confirm-message]').forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (form.dataset.confirmed === '1') {
                return;
            }

            event.preventDefault();
            window.showSiteConfirm(form.dataset.confirmMessage, function() {
                form.dataset.confirmed = '1';
                form.submit();
            }, form.dataset.confirmTitle);
        });
    });

    const dateInput = document.querySelector('input[name="booking_date"]');
    if (dateInput) {
        dateInput.addEventListener('change', function() {
            const selectedDate = new Date(this.value);
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            if (selectedDate < today) {
                window.showSiteModal({
                    title: modalTexts.messageTitle,
                    message: 'Cannot book for past dates',
                    type: 'error'
                });
                this.value = '';
            }
        });
    }

    const roomSelect = document.querySelector('select[name="room_id"]');
    const hoursSelect = document.querySelector('select[name="hours"]');

    if (roomSelect && hoursSelect) {
        function calculateTotal() {
            const selectedRoom = roomSelect.options[roomSelect.selectedIndex];
            if (selectedRoom && selectedRoom.value) {
                const text = selectedRoom.text;
                const priceMatch = text.match(/\$(\d+\.?\d*)/);

                if (priceMatch && hoursSelect.value) {
                    const price = parseFloat(priceMatch[1]);
                    const hours = parseInt(hoursSelect.value);
                    const total = price * hours;

                    let totalDisplay = document.getElementById('total-price');
                    if (!totalDisplay) {
                        totalDisplay = document.createElement('div');
                        totalDisplay.id = 'total-price';
                        totalDisplay.style.cssText = 'background-color: #0f3460; padding: 1rem; margin-top: 1rem; border-radius: 5px; text-align: center; font-size: 1.2rem; color: #fff;';
                        hoursSelect.closest('.form-group').after(totalDisplay);
                    }

                    totalDisplay.innerHTML = '<strong>Estimated Total: $' + total.toFixed(2) + '</strong>';
                }
            }
        }

        roomSelect.addEventListener('change', calculateTotal);
        hoursSelect.addEventListener('change', calculateTotal);
    }

    document.querySelectorAll('input[type="number"].form-control').forEach(function(input) {
        if (input.closest('.number-input-wrap')) {
            return;
        }

        const wrapper = document.createElement('div');
        wrapper.className = 'number-input-wrap';
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);

        const controls = document.createElement('div');
        controls.className = 'number-input-controls';
        controls.innerHTML = '<button type="button" class="number-stepper" aria-label="Increase value">+</button><button type="button" class="number-stepper" aria-label="Decrease value">-</button>';
        wrapper.appendChild(controls);

        const buttons = controls.querySelectorAll('button');
        buttons[0].addEventListener('click', function() {
            input.stepUp();
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });

        buttons[1].addEventListener('click', function() {
            input.stepDown();
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });

    function drawBarcode(ctx, code, x, y, width, height) {
        let seed = 0;
        for (let i = 0; i < code.length; i++) {
            seed = (seed * 31 + code.charCodeAt(i)) >>> 0;
        }

        ctx.fillStyle = '#f8fbff';
        ctx.fillRect(x, y, width, height);
        ctx.fillStyle = '#071120';

        let currentX = x + 22;
        const maxX = x + width - 22;

        while (currentX < maxX) {
            seed = (seed * 1664525 + 1013904223) >>> 0;
            const barWidth = 2 + (seed % 5);
            const barHeight = height - 24 - (seed % 18);
            ctx.fillRect(currentX, y + height - 12 - barHeight, barWidth, barHeight);
            currentX += barWidth + 3 + (seed % 3);
        }
    }

    function downloadTicket(ticket) {
        const data = ticket.dataset;
        const canvas = document.createElement('canvas');
        canvas.width = 1200;
        canvas.height = 760;
        const ctx = canvas.getContext('2d');

        const gradient = ctx.createLinearGradient(0, 0, 1200, 760);
        gradient.addColorStop(0, '#121b2e');
        gradient.addColorStop(1, '#071120');
        ctx.fillStyle = gradient;
        ctx.fillRect(0, 0, 1200, 760);

        ctx.strokeStyle = '#89afff';
        ctx.lineWidth = 3;
        ctx.strokeRect(44, 44, 1112, 672);

        ctx.fillStyle = '#34e2a0';
        ctx.font = '700 28px Arial';
        ctx.fillText((data.ticketStatus || 'Confirmed').toUpperCase(), 900, 118);

        ctx.fillStyle = '#ffffff';
        ctx.font = '700 48px Arial';
        ctx.fillText('FAMOUS GAMING', 82, 120);
        ctx.font = '700 34px Arial';
        ctx.fillText('Booking Ticket', 82, 180);

        ctx.fillStyle = '#9fb0ca';
        ctx.font = '24px Arial';
        ctx.fillText('Show this barcode at the shop for your reservation.', 82, 222);

        drawBarcode(ctx, data.ticketCode || 'FAMOUS-GAMING', 82, 270, 1036, 170);

        const items = [
            ['Customer', data.ticketCustomer || 'Customer'],
            ['Device / Session', data.ticketDevice || 'Gaming Session'],
            ['Date', data.ticketDate || 'Booking Date'],
            ['Time', data.ticketTime || 'Booking Time']
        ];

        items.forEach(function(item, index) {
            const col = index % 2;
            const row = Math.floor(index / 2);
            const boxX = 82 + col * 524;
            const boxY = 490 + row * 96;

            ctx.fillStyle = 'rgba(255, 255, 255, 0.06)';
            ctx.fillRect(boxX, boxY, 486, 72);
            ctx.fillStyle = '#9fb0ca';
            ctx.font = '700 18px Arial';
            ctx.fillText(item[0].toUpperCase(), boxX + 22, boxY + 27);
            ctx.fillStyle = '#ffffff';
            ctx.font = '700 24px Arial';
            ctx.fillText(item[1], boxX + 22, boxY + 56);
        });

        const link = document.createElement('a');
        link.download = 'famous-gaming-booking-ticket.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
    }

    document.querySelectorAll('.download-ticket-btn').forEach(function(button) {
        button.addEventListener('click', function() {
            const ticket = button.closest('.booking-ticket');
            if (ticket) {
                downloadTicket(ticket);
            }
        });
    });

    const bookingTicketModal = document.querySelector('.booking-ticket-modal');
    if (bookingTicketModal) {
        document.body.classList.add('booking-ticket-modal-open');

        function closeBookingTicketModal() {
            bookingTicketModal.classList.add('is-hidden');
            document.body.classList.remove('booking-ticket-modal-open');
        }

        bookingTicketModal.querySelectorAll('[data-close-ticket-modal]').forEach(function(button) {
            button.addEventListener('click', closeBookingTicketModal);
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !bookingTicketModal.classList.contains('is-hidden')) {
                closeBookingTicketModal();
            }
        });
    }
});

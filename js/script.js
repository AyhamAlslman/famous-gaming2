document.addEventListener('DOMContentLoaded', function() {
    const phoneInputs = document.querySelectorAll('input[name="phone"]');
    phoneInputs.forEach(function(input) {
        input.addEventListener('blur', function() {
            const phone = this.value;
            if (phone && !phone.match(/^07[0-9]{8}$/)) {
                alert('Phone number must start with 07 and be 10 digits (Jordan format)');
                this.focus();
            }
        });
    });

    const deleteLinks = document.querySelectorAll('a[href*="delete"]');
    deleteLinks.forEach(function(link) {
        link.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to delete?')) {
                e.preventDefault();
            }
        });
    });

    const messages = document.querySelectorAll('.message');
    messages.forEach(function(message) {
        setTimeout(function() {
            message.style.transition = 'opacity 0.5s';
            message.style.opacity = '0';
            setTimeout(function() {
                message.style.display = 'none';
            }, 500);
        }, 5000);
    });

    const dateInput = document.querySelector('input[name="booking_date"]');
    if (dateInput) {
        dateInput.addEventListener('change', function() {
            const selectedDate = new Date(this.value);
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            if (selectedDate < today) {
                alert('Cannot book for past dates');
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
});

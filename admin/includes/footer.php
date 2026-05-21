    <footer class="footer">
        <div class="container">
            <p><?php echo t('admin_footer'); ?></p>
        </div>
    </footer>
    <div class="admin-confirm-modal" id="adminConfirmModal" hidden>
        <div class="admin-confirm-backdrop" data-admin-confirm-cancel></div>
        <div class="admin-confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="adminConfirmTitle">
            <h3 id="adminConfirmTitle"><?php echo t('modal_confirm_title'); ?></h3>
            <p id="adminConfirmMessage"></p>
            <div class="admin-confirm-actions">
                <button type="button" class="btn btn-secondary" data-admin-confirm-cancel><?php echo t('common_no'); ?></button>
                <button type="button" class="btn btn-danger" id="adminConfirmYes"><?php echo t('common_yes'); ?></button>
            </div>
        </div>
    </div>
    <script>
    (function () {
        const modal = document.getElementById('adminConfirmModal');
        const titleEl = document.getElementById('adminConfirmTitle');
        const messageEl = document.getElementById('adminConfirmMessage');
        const yesButton = document.getElementById('adminConfirmYes');
        const cancelButtons = Array.from(document.querySelectorAll('.admin-confirm-actions [data-admin-confirm-cancel]'));
        let confirmCallback = null;

        window.showAdminConfirm = function(message, onConfirm, title) {
            if (!modal || !messageEl || !yesButton) {
                if (window.confirm(message)) {
                    onConfirm();
                }
                return;
            }

            messageEl.textContent = message || '<?php echo addslashes(t('admin_delete_confirm')); ?>';
            if (titleEl) {
                titleEl.textContent = title || '<?php echo addslashes(t('modal_confirm_title')); ?>';
            }
            cancelButtons.forEach(function(button) {
                button.hidden = false;
            });
            yesButton.textContent = '<?php echo addslashes(t('common_yes')); ?>';
            confirmCallback = onConfirm;
            modal.hidden = false;
            document.body.classList.add('admin-confirm-open');
        };

        window.showAdminMessage = function(message, title) {
            if (!modal || !messageEl || !yesButton) {
                return;
            }

            messageEl.textContent = message || '';
            if (titleEl) {
                titleEl.textContent = title || '<?php echo addslashes(t('modal_message_title')); ?>';
            }
            cancelButtons.forEach(function(button) {
                button.hidden = true;
            });
            yesButton.textContent = '<?php echo addslashes(t('common_close')); ?>';
            confirmCallback = null;
            modal.hidden = false;
            document.body.classList.add('admin-confirm-open');
        };

        function closeConfirm() {
            if (modal) {
                modal.hidden = true;
            }
            document.body.classList.remove('admin-confirm-open');
            confirmCallback = null;
        }

        document.querySelectorAll('[data-admin-confirm-cancel]').forEach(function(button) {
            button.addEventListener('click', closeConfirm);
        });

        if (yesButton) {
            yesButton.addEventListener('click', function() {
                const callback = confirmCallback;
                closeConfirm();
                if (typeof callback === 'function') {
                    callback();
                }
            });
        }

        document.querySelectorAll('.message').forEach(function(message) {
            const text = message.innerText.trim();
            if (!text) {
                return;
            }

            window.showAdminMessage(text);
            message.hidden = true;
        });

        document.querySelectorAll('[data-admin-confirm-message]').forEach(function(link) {
            link.addEventListener('click', function(event) {
                event.preventDefault();
                window.showAdminConfirm(link.dataset.adminConfirmMessage, function() {
                    window.location.href = link.href;
                }, link.dataset.adminConfirmTitle);
            });
        });

        document.querySelectorAll('[data-admin-confirm-form]').forEach(function(form) {
            form.addEventListener('submit', function(event) {
                if (form.dataset.confirmed === '1') {
                    return;
                }

                event.preventDefault();
                window.showAdminConfirm(form.dataset.adminConfirmMessage, function() {
                    form.dataset.confirmed = '1';
                    form.submit();
                }, form.dataset.adminConfirmTitle);
            });
        });
    })();
    </script>
</body>
</html>

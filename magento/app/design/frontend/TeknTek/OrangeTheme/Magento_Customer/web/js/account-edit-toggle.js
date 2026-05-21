(function () {
    'use strict';

    function toggleByFlag(node, show) {
        if (!node) {
            return;
        }
        if (show) {
            node.style.setProperty('display', 'block', 'important');
            node.removeAttribute('hidden');
        } else {
            node.style.setProperty('display', 'none', 'important');
            node.setAttribute('hidden', 'hidden');
        }
    }

    function init() {
        var form = document.querySelector('form.form-edit-account');
        if (!form) {
            return;
        }

        var emailCheck = form.querySelector('[data-role="change-email"]');
        var passwordCheck = form.querySelector('[data-role="change-password"]');
        var passwordFieldset = form.querySelector('[data-container="change-email-password"]');
        var emailField = form.querySelector('[data-container="change-email"]');
        var newPasswordField = form.querySelector('[data-container="new-password"]');
        var confirmPasswordField = form.querySelector('[data-container="confirm-password"]');
        var titleNode = form.querySelector('[data-title="change-email-password"]');

        if (!emailCheck || !passwordCheck || !passwordFieldset) {
            return;
        }

        function sync() {
            var useEmail = !!emailCheck.checked;
            var usePassword = !!passwordCheck.checked;
            var showGroup = useEmail || usePassword;

            toggleByFlag(passwordFieldset, showGroup);
            toggleByFlag(emailField, useEmail);
            toggleByFlag(newPasswordField, usePassword);
            toggleByFlag(confirmPasswordField, usePassword);

            if (titleNode) {
                if (useEmail && usePassword) {
                    titleNode.textContent = 'Change Email and Password';
                } else if (useEmail) {
                    titleNode.textContent = 'Change Email';
                } else if (usePassword) {
                    titleNode.textContent = 'Change Password';
                }
            }
        }

        emailCheck.addEventListener('change', sync);
        passwordCheck.addEventListener('change', sync);
        sync();

        form.addEventListener('submit', function (event) {
            var usePassword = !!passwordCheck.checked;
            var currentPassword = form.querySelector('#current-password');
            var newPassword = form.querySelector('#password');
            var confirmPassword = form.querySelector('#password-confirmation');

            if (!usePassword) {
                if (newPassword) {
                    newPassword.setCustomValidity('');
                }
                if (confirmPassword) {
                    confirmPassword.setCustomValidity('');
                }
                return;
            }

            var newValue = newPassword ? (newPassword.value || '').trim() : '';
            var confirmValue = confirmPassword ? (confirmPassword.value || '').trim() : '';
            var currentValue = currentPassword ? (currentPassword.value || '').trim() : '';
            var ruleMessage = 'Password must be at least 8 characters, start with an uppercase letter, and contain at least one number.';

            if (!currentValue) {
                event.preventDefault();
                if (currentPassword) {
                    currentPassword.setCustomValidity('Please enter your current password.');
                    currentPassword.reportValidity();
                }
                return;
            }
            if (currentPassword) {
                currentPassword.setCustomValidity('');
            }

            if (newValue.length < 8 || !/^[A-Z]/.test(newValue) || !/\d/.test(newValue)) {
                event.preventDefault();
                if (newPassword) {
                    newPassword.setCustomValidity(ruleMessage);
                    newPassword.reportValidity();
                }
                return;
            }

            if (newPassword) {
                newPassword.setCustomValidity('');
            }

            if (newValue !== confirmValue) {
                event.preventDefault();
                if (confirmPassword) {
                    confirmPassword.setCustomValidity('New Password and Confirm New Password values did not match.');
                    confirmPassword.reportValidity();
                }
                return;
            }

            if (confirmPassword) {
                confirmPassword.setCustomValidity('');
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

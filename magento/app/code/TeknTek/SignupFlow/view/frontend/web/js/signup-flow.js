define(['jquery'], function ($) {
    'use strict';

    return function (config, element) {
        var $root = $(element);
        $root.attr('data-signup-flow-bound', '1');
        var $message = $root.find('[data-role="message"]');
        var $steps = $root.find('[data-step]');
        var $progress = $root.find('[data-role="progress-step"]');
        var countdownTimer = null;
        var expiresAt = 0;
        var verifiedEmail = '';
        var activeStep = String($root.data('initial-step') || 'email');
        var initialEmail = String($root.data('initial-email') || '');
        var initialExpiresAt = parseInt($root.data('initial-expires-at'), 10) || 0;

        function getFormKey() {
            var formKeyInput = $root.find('input[name="form_key"]').first();
            return formKeyInput.length ? formKeyInput.val() : '';
        }

        function showMessage(text, type) {
            if (!text) {
                $message.attr('hidden', true).removeClass('is-error is-success').text('');
                return;
            }

            $message
                .attr('hidden', false)
                .removeClass('is-error is-success')
                .addClass(type === 'error' ? 'is-error' : 'is-success')
                .text(text);
        }

        function setStep(step) {
            activeStep = step;

            $steps.each(function () {
                var $step = $(this);
                var isActive = $step.data('step') === step;
                $step.toggleClass('is-active', isActive).prop('hidden', !isActive);
            });

            $progress.each(function () {
                var index = parseInt($(this).data('step-index'), 10);
                var activeIndex = step === 'email' ? 0 : step === 'verify' ? 1 : 2;
                $(this)
                    .toggleClass('is-active', index === activeIndex)
                    .toggleClass('is-complete', index < activeIndex);
            });
        }

        function formatSeconds(seconds) {
            var safeSeconds = Math.max(0, seconds);
            var minutes = Math.floor(safeSeconds / 60);
            var remaining = safeSeconds % 60;
            var mm = minutes < 10 ? '0' + minutes : String(minutes);
            var ss = remaining < 10 ? '0' + remaining : String(remaining);
            return mm + ':' + ss;
        }

        function stopTimer() {
            if (countdownTimer) {
                window.clearInterval(countdownTimer);
                countdownTimer = null;
            }
        }

        function startTimer(newExpiresAt) {
            var $countdown = $root.find('[data-role="countdown"]');
            var $resend = $root.find('[data-role="resend-code"]');

            expiresAt = parseInt(newExpiresAt, 10) || 0;
            stopTimer();

            function tick() {
                var now = Math.floor(Date.now() / 1000);
                var remaining = expiresAt - now;
                $countdown.text(formatSeconds(remaining));

                if (remaining <= 0) {
                    stopTimer();
                    $resend.prop('disabled', false).removeClass('is-hidden');
                } else {
                    $resend.prop('disabled', true).removeClass('is-hidden');
                }
            }

            $resend.prop('disabled', true).removeClass('is-hidden');
            tick();
            countdownTimer = window.setInterval(tick, 1000);
        }

        function getDigitInputs() {
            return $root.find('[data-role="code-digit"]');
        }

        function focusDigitAt(index) {
            var $digits = getDigitInputs();
            var $target = $digits.eq(index);

            if ($target.length) {
                $target.trigger('focus').trigger('select');
            }
        }

        function fillDigitsFromValue(startIndex, rawValue) {
            var digits = String(rawValue || '').replace(/\D+/g, '');
            var $digits = getDigitInputs();
            var currentIndex = startIndex;

            digits.split('').forEach(function (digit) {
                var $target = $digits.eq(currentIndex);
                if (!$target.length) {
                    return;
                }

                $target.val(digit);
                currentIndex += 1;
            });

            updateHiddenCode();
            focusDigitAt(Math.min(currentIndex, $digits.length - 1));
        }

        function updateHiddenCode() {
            $root.find('[data-role="code-hidden"]').val(digitsToCode());
        }

        function requestCode(email) {
            showMessage('', 'success');

            return $.ajax({
                url: config.sendUrl,
                method: 'POST',
                dataType: 'json',
                data: {
                    form_key: getFormKey(),
                    email: email
                }
            });
        }

        function requestVerification(code) {
            showMessage('', 'success');

            return $.ajax({
                url: config.verifyUrl,
                method: 'POST',
                dataType: 'json',
                data: {
                    form_key: getFormKey(),
                    code: code
                }
            });
        }

        function digitsToCode() {
            var code = '';
            $root.find('[data-role="code-digit"]').each(function () {
                code += String($(this).val() || '').replace(/\D+/g, '');
            });
            return code;
        }

        function setEmailValue(email) {
            verifiedEmail = email || verifiedEmail;
            $root.find('[data-role="verified-email-hidden"]').val(verifiedEmail);
            $root.find('[data-role="final-email-hidden"]').val(verifiedEmail);
            $root.find('[data-role="verify-copy"]').text('Check your inbox. Enter the 6-digit verification code sent to ' + verifiedEmail + '. The code expires after 5 minutes.');
        }

        function isPasswordRuleValid(password) {
            return typeof password === 'string'
                && password.length >= 8
                && /^[A-Z]/.test(password)
                && /\d/.test(password);
        }

        $root.on('submit', '[data-role="email-form"]', function (event) {
            event.preventDefault();

            var email = $.trim($root.find('[data-role="email-input"]').val());
            var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!email || !emailPattern.test(email)) {
                showMessage('Please enter a valid email address.', 'error');
                return;
            }

            requestCode(email)
                .done(function (response) {
                    if (!response || !response.success) {
                        showMessage((response && response.message) || 'Unable to send verification code.', 'error');
                        return;
                    }

                    setEmailValue(response.email || email);
                    setStep('verify');
                    startTimer(response.expires_at || Math.floor(Date.now() / 1000) + config.expiresSeconds);
                    showMessage(response.message, 'success');
                    $root.find('[data-role="code-digit"]').first().trigger('focus');
                })
                .fail(function () {
                    showMessage('Unable to send verification code.', 'error');
                });
        });

        $root.on('click', '[data-role="resend-code"]', function (event) {
            event.preventDefault();

            if (!verifiedEmail) {
                return;
            }

            requestCode(verifiedEmail)
                .done(function (response) {
                    if (!response || !response.success) {
                        showMessage((response && response.message) || 'Unable to resend the code.', 'error');
                        return;
                    }

                    startTimer(response.expires_at || Math.floor(Date.now() / 1000) + config.expiresSeconds);
                    showMessage(response.message, 'success');
                })
                .fail(function () {
                    showMessage('Unable to resend the code.', 'error');
                });
        });

        $root.on('input', '[data-role="code-digit"]', function () {
            var $input = $(this);
            var rawValue = String($input.val() || '');
            var value = rawValue.replace(/\D+/g, '');

            if (rawValue && value !== rawValue) {
                showMessage('Verification code only accepts numbers.', 'error');
            } else {
                showMessage('', 'success');
            }

            if (value.length > 1) {
                fillDigitsFromValue(getDigitInputs().index($input), value);
                return;
            }

            value = value.slice(0, 1);
            $input.val(value);

            if (value) {
                var nextInput = $input.nextAll('[data-role="code-digit"]').first();
                if (nextInput.length) {
                    nextInput.trigger('focus').trigger('select');
                }
            }

            updateHiddenCode();
        });

        $root.on('paste', '[data-role="code-digit"]', function (event) {
            var clipboardData = (event.originalEvent && event.originalEvent.clipboardData) ? event.originalEvent.clipboardData : window.clipboardData;
            var pastedValue = clipboardData ? clipboardData.getData('text') : '';
            var $input = $(this);
            var startIndex = getDigitInputs().index($input);

            if (startIndex < 0) {
                return;
            }

            event.preventDefault();
            fillDigitsFromValue(startIndex, pastedValue);
        });

        $root.on('keydown', '[data-role="code-digit"]', function (event) {
            if (event.key === 'Backspace' && !$(this).val()) {
                var previousInput = $(this).prevAll('[data-role="code-digit"]').first();
                if (previousInput.length) {
                    previousInput.trigger('focus').trigger('select');
                }
            }
        });

        $root.on('submit', '[data-role="verify-form"]', function (event) {
            event.preventDefault();

            var code = digitsToCode();
            if (code.length !== 6) {
                showMessage('Please enter the 6-digit code.', 'error');
                return;
            }

            requestVerification(code)
                .done(function (response) {
                    if (!response || !response.success) {
                        showMessage((response && response.message) || 'The code is invalid or expired.', 'error');
                        return;
                    }

                    setEmailValue(response.email || verifiedEmail);
                    setStep('profile');
                    showMessage(response.message, 'success');
                })
                .fail(function () {
                    showMessage('Unable to verify the code.', 'error');
                });
        });

        $root.on('submit', '[data-role="profile-form"]', function (event) {
            event.preventDefault();

            var $form = $(this);
            var email = $.trim($form.find('[data-role="final-email-hidden"]').val());
            var password = String($form.find('input[name="password"]').val() || '');
            var passwordConfirmation = String($form.find('input[name="password_confirmation"]').val() || '');

            if (!email) {
                showMessage('Please verify your email address first.', 'error');
                setStep('email');
                return;
            }

            if (!isPasswordRuleValid(password)) {
                showMessage('Password must be at least 8 characters, start with an uppercase letter, and contain at least one number.', 'error');
                return;
            }

            $.ajax({
                url: config.createUrl,
                method: 'POST',
                dataType: 'json',
                data: $form.serialize()
            })
                .done(function (response) {
                    if (response && response.success === false) {
                        showMessage(response.message || 'Unable to create your account.', 'error');
                        return;
                    }

                    window.location.href = (response && response.redirect_url) ? response.redirect_url : config.successUrl;
                })
                .fail(function () {
                    showMessage('Unable to create your account.', 'error');
                });
        });

        if (initialEmail) {
            setEmailValue(initialEmail);
        }

        setStep(activeStep);

        if (activeStep === 'verify') {
            startTimer(initialExpiresAt || (Math.floor(Date.now() / 1000) + config.expiresSeconds));
        }

        if (activeStep === 'verify') {
            focusDigitAt(0);
        }

        // Fallback: if another script clears handlers, keep OTP UX usable.
        $root.attr('data-signup-flow-ready', '1');
    };
});
(function(){
    if (typeof window.calc123Data === 'undefined') {
        return;
    }

    var ajaxUrl = window.calc123Data.ajaxUrl;
    var nonce   = window.calc123Data.nonce;
    var messages = window.calc123Data.messages || {};

    function msg(key, fallback) {
        return messages[key] || fallback;
    }

    function refreshCaptcha(form, silent) {
        if (!form) return;
        var captchaWrapper = form.querySelector('.calc123-captcha');
        if (!captchaWrapper) return;

        var data = new FormData();
        data.append('action', 'calc123_new_captcha');
        data.append('security', nonce);
        data.append('calc_id', form.getAttribute('data-calc-id') || '');

        fetch(ajaxUrl, { method: 'POST', body: data })
            .then(function(response){
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(function(payload){
                if (!payload || !payload.success || !payload.data) {
                    if (!silent) {
                        console.warn('calc123: captcha refresh failed', payload);
                    }
                    return;
                }

                var label = captchaWrapper.querySelector('[data-role="captcha-label"]') || captchaWrapper.querySelector('label');
                var captchaLabel = msg('captchaLabel', 'Капча');
                var captchaTemplate = msg('captchaTemplate', '%1$s + %2$s = ');
                var equation = captchaTemplate.replace('%1$s', payload.data.a).replace('%2$s', payload.data.b);
                if (label) {
                    label.textContent = captchaLabel + ': ' + equation;
                }

                var answerInput = captchaWrapper.querySelector('input[name="captcha_answer"]');
                var inputA = captchaWrapper.querySelector('input[name="captcha_a"]');
                var inputB = captchaWrapper.querySelector('input[name="captcha_b"]');
                var inputToken = captchaWrapper.querySelector('input[name="captcha_token"]');

                if (answerInput) answerInput.value = '';
                if (inputA) inputA.value = payload.data.a;
                if (inputB) inputB.value = payload.data.b;
                if (inputToken) inputToken.value = payload.data.token;

                captchaWrapper.hidden = false;
            })
            .catch(function(err){
                if (!silent) {
                    console.error('calc123 captcha refresh error', err);
                }
            });
    }

    function validateForm(form) {
        var resultBox = form.querySelector('.calc123-result');
        var invalidMessage = '';

        form.querySelectorAll('input[name], select[name]').forEach(function(el){
            if (invalidMessage) return;
            if (el.type === 'hidden') return;

            if (el.hasAttribute('required')) {
                var v = el.value;
                if (v === null || typeof v === 'undefined' || String(v).trim() === '') {
                    invalidMessage = msg('fillRequired', 'Пожалуйста, заполните все обязательные поля.');
                    return;
                }
            }

            if (el.tagName.toLowerCase() === 'input' && el.type === 'number') {
                var vv = el.value;
                if (vv === null || vv === '') {
                    invalidMessage = msg('fillRequired', 'Пожалуйста, заполните все обязательные поля.');
                    return;
                }
                var n = Number(vv);
                if (!isFinite(n)) {
                    invalidMessage = msg('invalidNumber', 'Поля должны содержать корректные числа.');
                }
            }
        });

        return {
            valid: !invalidMessage,
            message: invalidMessage,
            resultBox: resultBox
        };
    }

    function setSubmitting(form, button, state) {
        if (!form) return;
        if (state) {
            form.dataset.calc123Submitting = '1';
            if (button) {
                button.disabled = true;
                button.classList.add('is-busy');
            }
        } else {
            delete form.dataset.calc123Submitting;
            if (button) {
                button.disabled = false;
                button.classList.remove('is-busy');
            }
        }
    }

    function submitForm(form, button) {
        if (form.dataset.calc123Submitting === '1') {
            return;
        }

        var validation = validateForm(form);
        var resultBox = validation.resultBox;
        if (!resultBox) return;

        if (!validation.valid) {
            resultBox.textContent = validation.message;
            return;
        }

        resultBox.textContent = msg('processing', 'Выполняется...');
        setSubmitting(form, button, true);

        var data = new FormData();
        data.append('action', 'calc123_compute');
        data.append('security', nonce);
        data.append('calc_id', form.getAttribute('data-calc-id') || '');

        form.querySelectorAll('input[name], select[name]').forEach(function(el){
            data.append(el.name, el.value);
        });

        fetch(ajaxUrl, { method: 'POST', body: data })
            .then(function(response){
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(function(payload){
                if (payload.success && payload.data) {
                    resultBox.textContent = payload.data.formatted;
                    if (form.getAttribute('data-requires-captcha') === '1') {
                        refreshCaptcha(form, true);
                    }
                    setSubmitting(form, button, false);
                    return;
                }

                var message = msg('serverError', 'Серверная ошибка');
                var errorPrefix = msg('errorPrefix', 'Ошибка: ');
                if (payload && payload.data && payload.data.message) {
                    message = errorPrefix + payload.data.message;
                }
                resultBox.textContent = message;

                if (payload && payload.data && payload.data.message && payload.data.message.indexOf('nonce') !== -1) {
                    // при ошибке nonce попробуем тихо обновить капчу (на случай, если токен сброшен)
                    if (form.getAttribute('data-requires-captcha') === '1') {
                        refreshCaptcha(form, true);
                    }
                }
                setSubmitting(form, button, false);
            })
            .catch(function(err){
                resultBox.textContent = msg('connectionError', 'Ошибка соединения');
                console.error('calc123 ajax error', err);
                setSubmitting(form, button, false);
            });
    }

    function initForm(form) {
        if (!form || form.dataset.calc123Bound) {
            return;
        }
        form.dataset.calc123Bound = '1';

        form.addEventListener('submit', function(e){ e.preventDefault(); });

        if (form.getAttribute('data-requires-captcha') === '1') {
            refreshCaptcha(form, true);
        }

        var button = form.querySelector('.calc123-calc-btn');
        if (!button) return;

        button.addEventListener('click', function(e){
            e.preventDefault();
            submitForm(form, button);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function(){
            document.querySelectorAll('.calc123-frontend-form').forEach(initForm);
        });
    } else {
        document.querySelectorAll('.calc123-frontend-form').forEach(initForm);
    }
})();

/*
 * Client-side validation for forms marked with data-validate. Plain JavaScript, no dependencies.
 *
 * This only improves usability. The Laravel Form Requests stay authoritative, and without JavaScript the
 * forms submit normally and are validated on the server.
 *
 * Rules are declared on each [data-field] wrapper and mirror the Form Requests and MoneyCalculator:
 *   data-required            value required (after Laravel's trimming)
 *   data-label               attribute name used in messages (default: field name with spaces)
 *   data-format              "amount" (data-decimals, data-max-minor, data-max-display) or "percentage" (data-max-bps)
 *   data-maxlength           maximum characters, counted like mb_strlen
 *   data-different-from      name of a field that must hold a different value
 *   data-only-when           "field=value"; otherwise the field is hidden, disabled (not submitted) and not validated
 *   data-<rule>-message      message override; may also be set on the form. ":attribute" is replaced by the label.
 * Selects and radio groups must also hold one of their non-empty options.
 *
 * data-submit="ajax" submits with fetch after those checks pass and expects JSON. A form-level alert is used for
 * business and network errors and is left in place when a field becomes locally valid. Without JavaScript the
 * form posts normally.
 */
(function () {
    'use strict';

    // Laravel's TrimStrings middleware (Str::trim): PCRE \s, NUL and Str::INVISIBLE_CHARACTERS, at either end only.
    var EDGE = '[\\s\\0\\u0085\\u00AD\\u034F\\u061C\\u115F\\u1160\\u17B4\\u17B5\\u180E\\u200B-\\u200F\\u2060-\\u2065'
        + '\\u206A-\\u206F\\u2800\\u3164\\uFEFF\\uFFA0\\u{1D159}\\u{1D173}-\\u{1D17A}\\u{E0020}]';
    var TRIM = new RegExp('^' + EDGE + '+|' + EDGE + '+$', 'gu');
    var DECIMAL = /^([0-9]+)(?:\.([0-9]+))?$/;

    var MESSAGES = {
        required: 'The :attribute field is required.',
        option: 'The selected :attribute is invalid.',
        maxlength: 'The :attribute field must not be greater than :max characters.',
        different: 'The :attribute field and :other must be different.'
    };

    function trimLikeLaravel(value) {
        return value.replace(TRIM, '');
    }

    // mb_strlen counts code points; browsers submit textarea line breaks as CRLF.
    function characterCount(value, multiline) {
        return Array.from(multiline ? value.replace(/\r\n|\r|\n/g, '\r\n') : value).length;
    }

    // Compares non-negative integer strings of any length without converting them to numbers.
    function compareIntegers(a, b) {
        a = a.replace(/^0+(?=.)/, '');
        b = b.replace(/^0+(?=.)/, '');
        if (a.length !== b.length) {
            return a.length < b.length ? -1 : 1;
        }
        return a < b ? -1 : (a > b ? 1 : 0);
    }

    var FORMATS = {
        // MoneyCalculator::parseAmount, in the same order.
        amount: function (value, data) {
            var decimals = Number(data.decimals);
            if (value.charAt(0) === '-') {
                return 'The eligible sale amount must be greater than zero.';
            }
            var parts = DECIMAL.exec(value);
            if (!parts) {
                return 'The eligible sale amount must be a plain decimal number such as 1250.50 (no spaces, commas, signs or exponents).';
            }
            var whole = parts[1];
            var fraction = parts[2] || '';
            if (whole.length > 1 && whole.charAt(0) === '0') {
                return 'The eligible sale amount must not have leading zeros.';
            }
            if (fraction.length > decimals) {
                return 'The eligible sale amount must have at most ' + decimals + ' decimal places.';
            }
            var minor = whole + fraction.padEnd(decimals, '0');
            if (compareIntegers(minor, data.maxMinor) > 0) {
                return 'The eligible sale amount must not exceed ' + data.maxDisplay + '.';
            }
            if (compareIntegers(minor, '0') === 0) {
                return 'The eligible sale amount must be greater than zero.';
            }
            return '';
        },

        // MoneyCalculator::parseRate, in the same order.
        percentage: function (value, data) {
            var parts = DECIMAL.exec(value);
            if (!parts) {
                return 'The rate must be a percentage between 0 and 100, such as 2.5 (no % sign).';
            }
            var whole = parts[1];
            var fraction = parts[2] || '';
            if (whole.length > 1 && whole.charAt(0) === '0') {
                return 'The rate must not have leading zeros.';
            }
            if (fraction.length > 2) {
                return 'The rate must have at most 2 decimal places.';
            }
            if (whole.length > 3 || compareIntegers(whole + fraction.padEnd(2, '0'), data.maxBps) > 0) {
                return 'The rate must be between 0% and 100%.';
            }
            return '';
        }
    };

    function parseCondition(condition) {
        var at = condition.indexOf('=');
        return { name: condition.slice(0, at), value: condition.slice(at + 1) };
    }

    function setToken(element, attribute, token, present) {
        var tokens = (element.getAttribute(attribute) || '').split(/\s+/).filter(function (t) {
            return t !== '' && t !== token;
        });
        if (present) {
            tokens.push(token);
        }
        if (tokens.length) {
            element.setAttribute(attribute, tokens.join(' '));
        } else {
            element.removeAttribute(attribute);
        }
    }

    function initForm(form) {
        var fields = [];
        var byName = {};

        Array.prototype.forEach.call(form.querySelectorAll('[data-field]'), function (wrapper) {
            var name = wrapper.getAttribute('data-field');
            var controls = Array.prototype.filter.call(wrapper.querySelectorAll('input, select, textarea'), function (c) {
                return c.name === name;
            });
            if (!controls.length) {
                return;
            }
            var isRadio = controls[0].type === 'radio';
            var errorId = (isRadio ? wrapper.id : controls[0].id) + '-error';
            var errorElement = wrapper.querySelector('#' + CSS.escape(errorId));
            var field = {
                name: name,
                wrapper: wrapper,
                data: wrapper.dataset,
                controls: controls,
                isRadio: isRadio,
                isChoice: isRadio || controls[0].tagName === 'SELECT',
                errorId: errorId,
                errorElement: errorElement,
                // A server error stays until the user changes this field (or a field it depends on), because the
                // server may know something the client does not.
                serverError: errorElement !== null,
                touched: false,
                changed: false
            };
            fields.push(field);
            byName[name] = field;
        });

        if (!fields.length) {
            return;
        }

        var status = document.createElement('p');
        status.className = 'visually-hidden';
        status.setAttribute('role', 'status');
        status.setAttribute('aria-live', 'polite');
        form.appendChild(status);

        function announce(text) {
            status.textContent = '';
            window.setTimeout(function () {
                status.textContent = text;
            }, 100);
        }

        function rawValue(field) {
            if (field.isRadio) {
                var checked = field.controls.filter(function (c) {
                    return c.checked;
                })[0];
                return checked ? checked.value : '';
            }
            return field.controls[0].value;
        }

        function conditionHolds(condition) {
            var parsed = parseCondition(condition);
            var controller = byName[parsed.name];
            return controller !== undefined && rawValue(controller) === parsed.value;
        }

        function isApplicable(field) {
            return !field.data.onlyWhen || conditionHolds(field.data.onlyWhen);
        }

        function labelOf(field) {
            return field.data.label || field.name.replace(/_/g, ' ');
        }

        function message(field, rule, replacements) {
            var key = rule + 'Message';
            var template = field.data[key] || form.dataset[key] || MESSAGES[rule];
            replacements = replacements || {};
            replacements.attribute = labelOf(field);
            return template.replace(/:(attribute|max|other)/g, function (match, name) {
                return replacements[name] !== undefined ? replacements[name] : match;
            });
        }

        function isOption(field, value) {
            var options = field.isRadio ? field.controls : Array.prototype.slice.call(field.controls[0].options);
            return options.some(function (option) {
                return option.value !== '' && option.value === value;
            });
        }

        function validate(field) {
            if (!isApplicable(field)) {
                return '';
            }
            var value = trimLikeLaravel(rawValue(field));
            if (value === '') {
                return 'required' in field.data ? message(field, 'required') : '';
            }
            if (field.isChoice && !isOption(field, value)) {
                return message(field, 'option');
            }
            if (field.data.format) {
                var formatError = FORMATS[field.data.format](value, field.data);
                if (formatError) {
                    return formatError;
                }
            }
            if (field.data.maxlength
                && characterCount(value, field.controls[0].tagName === 'TEXTAREA') > Number(field.data.maxlength)) {
                return message(field, 'maxlength', { max: field.data.maxlength });
            }
            var other = byName[field.data.differentFrom];
            if (other && trimLikeLaravel(rawValue(other)) === value) {
                return message(field, 'different', { other: labelOf(other) });
            }
            return '';
        }

        function showError(field, text, fromServer) {
            if (!field.errorElement) {
                field.errorElement = document.createElement('small');
                field.errorElement.className = 'error';
                field.errorElement.id = field.errorId;
                field.wrapper.appendChild(field.errorElement);
            }
            field.errorElement.textContent = text;
            field.serverError = fromServer === true;
            if (fromServer) {
                field.changed = false;
            }
            field.controls.forEach(function (control) {
                control.setAttribute('aria-invalid', 'true');
                setToken(control, 'aria-describedby', field.errorId, true);
            });
        }

        function formAlert() {
            var alert = form.querySelector('[data-form-error]');
            if (!alert) {
                alert = document.createElement('div');
                alert.className = 'alert';
                alert.setAttribute('role', 'alert');
                alert.setAttribute('data-form-error', '');
                alert.tabIndex = -1;
                form.insertBefore(alert, form.firstChild);
            }
            return alert;
        }

        function showFormError(text) {
            var alert = formAlert();
            alert.textContent = text;
        }

        function clearFormError() {
            var alert = form.querySelector('[data-form-error]');
            if (alert) {
                alert.remove();
            }
        }

        function clearError(field) {
            if (field.errorElement) {
                field.errorElement.remove();
                field.errorElement = null;
            }
            field.serverError = false;
            field.controls.forEach(function (control) {
                control.removeAttribute('aria-invalid');
                setToken(control, 'aria-describedby', field.errorId, false);
            });
        }

        // Returns true when the field is left showing an error.
        function render(field, announceNew) {
            var text = validate(field);
            if (text) {
                var previous = field.errorElement ? field.errorElement.textContent : '';
                showError(field, text);
                if (announceNew && text !== previous) {
                    var label = field.wrapper.querySelector('legend, label');
                    announce((label ? label.textContent.trim() + ': ' : '') + text);
                }
                return true;
            }
            if (field.serverError && !field.changed) {
                return false;
            }
            clearError(field);
            return false;
        }

        function applyConditions() {
            fields.forEach(function (field) {
                if (!field.data.onlyWhen) {
                    return;
                }
                var applicable = isApplicable(field);
                field.wrapper.hidden = !applicable;
                field.controls.forEach(function (control) {
                    control.disabled = !applicable;
                    if ('required' in field.data && applicable) {
                        control.setAttribute('aria-required', 'true');
                    } else {
                        control.removeAttribute('aria-required');
                    }
                });
                if (!applicable) {
                    clearError(field);
                    field.touched = false;
                    field.changed = false;
                }
            });
            Array.prototype.forEach.call(form.querySelectorAll('[data-show-when]'), function (element) {
                element.hidden = !conditionHolds(element.getAttribute('data-show-when'));
            });
        }

        function fieldFor(target) {
            var wrapper = target.closest ? target.closest('[data-field]') : null;
            return wrapper ? byName[wrapper.getAttribute('data-field')] : undefined;
        }

        function focusControl(field) {
            var checked = field.controls.filter(function (c) {
                return c.checked;
            })[0];
            (checked || field.controls[0]).focus();
        }

        function onEdit(event) {
            var field = fieldFor(event.target);
            if (!field || field.controls.indexOf(event.target) === -1) {
                return;
            }
            field.changed = true;
            if (field.touched || field.errorElement) {
                render(field, false);
            }
            fields.forEach(function (dependent) {
                if (dependent.data.differentFrom === field.name) {
                    dependent.changed = true;
                    if (dependent.touched || dependent.errorElement) {
                        render(dependent, false);
                    }
                }
            });
            if (form.querySelector('[data-only-when^="' + field.name + '="], [data-show-when^="' + field.name + '="]')) {
                applyConditions();
                fields.forEach(function (dependent) {
                    if (!dependent.data.onlyWhen || dependent.data.onlyWhen.indexOf(field.name + '=') !== 0 || !isApplicable(dependent)) {
                        return;
                    }
                    if (!(dependent.touched || dependent.errorElement || trimLikeLaravel(rawValue(dependent)) !== '')) {
                        return;
                    }
                    dependent.changed = true;
                    dependent.touched = true;
                    render(dependent, false);
                });
            }
        }

        form.addEventListener('input', onEdit);
        form.addEventListener('change', onEdit);

        form.addEventListener('focusout', function (event) {
            var field = fieldFor(event.target);
            if (!field || field.controls.indexOf(event.target) === -1 || field.controls.indexOf(event.relatedTarget) !== -1) {
                return; // Arrow keys move focus between radios of the same group.
            }
            field.touched = true;
            render(field, true);
        });

        function reveal(field) {
            if (!field.wrapper.hidden) {
                return;
            }
            field.wrapper.hidden = false;
            field.controls.forEach(function (control) {
                control.disabled = false;
            });
        }

        function applyServerFieldErrors(errors) {
            var first = null;
            var mapped = {};
            fields.forEach(function (field) {
                var messages = errors[field.name];
                if (messages && messages.length) {
                    mapped[field.name] = true;
                    reveal(field);
                    showError(field, messages[0], true);
                    field.touched = true;
                    if (!first) {
                        first = field;
                    }
                } else if (field.serverError) {
                    clearError(field);
                }
            });
            var leftovers = [];
            Object.keys(errors).forEach(function (key) {
                if (!mapped[key] && errors[key] && errors[key].length) {
                    leftovers.push(errors[key][0]);
                }
            });
            return { first: first, leftovers: leftovers };
        }

        function restoreSubmit() {
            form.removeAttribute('aria-busy');
            var button = form.querySelector('button[type="submit"]');
            if (button) {
                button.disabled = false;
            }
        }

        function submitAjax() {
            var button = form.querySelector('button[type="submit"]');
            if (button) {
                button.disabled = true;
            }
            form.setAttribute('aria-busy', 'true');

            var headers = {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            };
            var token = form.querySelector('input[name="_token"]');
            if (token) {
                headers['X-CSRF-TOKEN'] = token.value;
            }

            fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: headers,
                credentials: 'same-origin'
            }).then(function (response) {
                if (response.redirected) {
                    window.location.assign(response.url);
                    return null;
                }
                return response.json().then(function (data) {
                    return { status: response.status, data: data };
                }, function () {
                    return { status: response.status, data: null };
                });
            }).then(function (result) {
                if (!result) {
                    return;
                }
                var data = result.data || {};
                if (result.status >= 200 && result.status < 300 && result.data && result.data.redirect) {
                    announce(data.message || 'Saved.');
                    window.location.assign(data.redirect);
                    return;
                }
                if (result.status === 419) {
                    showFormError('Your session has expired. Reload the page and try again.');
                    formAlert().focus();
                    restoreSubmit();
                    return;
                }
                if (result.status === 422 && data.errors) {
                    var applied = applyServerFieldErrors(data.errors);
                    if (applied.leftovers.length) {
                        showFormError(applied.leftovers.join(' '));
                        formAlert().focus();
                    } else {
                        clearFormError();
                    }
                    if (applied.first) {
                        focusControl(applied.first);
                    }
                    var count = Object.keys(data.errors).length;
                    announce(count === 1 ? '1 field needs correcting.' : count + ' fields need correcting.');
                    restoreSubmit();
                    return;
                }
                showFormError((result.data && result.data.message) || (result.data === null
                    ? 'The update could not be saved because the server response could not be read.'
                    : 'The update could not be saved.'));
                formAlert().focus();
                restoreSubmit();
            }).catch(function () {
                showFormError('The update could not be saved. Check your connection and try again.');
                formAlert().focus();
                restoreSubmit();
            });
        }

        form.addEventListener('submit', function (event) {
            if (form.getAttribute('data-submit') === 'ajax' && form.getAttribute('aria-busy') === 'true') {
                event.preventDefault();
                return;
            }
            var invalid = fields.filter(function (field) {
                if (!isApplicable(field)) {
                    return false;
                }
                field.touched = true;
                return render(field, false);
            });
            if (invalid.length) {
                event.preventDefault();
                focusControl(invalid[0]);
                announce(invalid.length === 1
                    ? '1 field needs correcting.'
                    : invalid.length + ' fields need correcting.');
                return;
            }
            if (form.getAttribute('data-submit') !== 'ajax') {
                return;
            }
            event.preventDefault();
            submitAjax();
        });

        Array.prototype.forEach.call(form.querySelectorAll('[data-no-js]'), function (element) {
            element.hidden = true;
        });
        applyConditions();
    }

    Array.prototype.forEach.call(document.querySelectorAll('form[data-validate]'), initForm);
})();

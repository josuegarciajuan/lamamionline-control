(function () {
    function showToast(message, type) {
        var el = document.getElementById('floatingToast');
        if (!el || !message) return;
        el.textContent = message;
        el.style.background = type === 'error' ? 'rgba(239,68,68,.96)' : 'rgba(16,185,129,.96)';
        el.style.color = type === 'error' ? '#fff' : '#06280f';
        el.classList.add('show');
        setTimeout(function () {
            el.classList.remove('show');
        }, 3200);
    }

    function rainEffect(symbol, className) {
        var wrap = document.getElementById('moneyRain');
        if (!wrap) return;
        wrap.innerHTML = '';

        for (var i = 0; i < 28; i++) {
            var d = document.createElement('div');
            d.className = className;
            d.textContent = symbol;
            d.style.left = Math.floor(Math.random() * 100) + 'vw';
            d.style.animationDelay = (Math.random() * 0.7) + 's';
            d.style.fontSize = (22 + Math.random() * 24) + 'px';
            wrap.appendChild(d);
        }

        setTimeout(function () {
            wrap.innerHTML = '';
        }, 3600);
    }

    function euroRain() {
        rainEffect('€', 'euro-drop');
    }

    function sadRain() {
        rainEffect('☹', 'sad-drop');
    }

    function copyToClipboard(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text);
        }

        return new Promise(function (resolve, reject) {
            try {
                var tmp = document.createElement('textarea');
                tmp.value = text;
                tmp.setAttribute('readonly', 'readonly');
                tmp.style.position = 'fixed';
                tmp.style.opacity = '0';
                document.body.appendChild(tmp);
                tmp.select();
                tmp.setSelectionRange(0, tmp.value.length);
                var ok = document.execCommand('copy');
                document.body.removeChild(tmp);
                if (ok) resolve(); else reject();
            } catch (err) {
                reject(err);
            }
        });
    }

    function getFormMoneyValue(form, names, fallback) {
        for (var i = 0; i < names.length; i++) {
            var input = form.querySelector('[name="' + names[i] + '"]');
            if (input) {
                var value = (input.value || '').trim();
                if (value !== '') {
                    return value;
                }
            }
        }
        return fallback;
    }

    window.confirmLeadSubmit = function (form) {
        var amount = getFormMoneyValue(form, ['precio_lead', 'precio', 'importe'], '0');
        return confirm('¿Seguro que quieres añadir este importe de ' + amount + '€?');
    };

    window.confirmGastoSubmit = function (form) {
        var amount = getFormMoneyValue(form, ['cantidad'], '0');
        return confirm('¿Seguro que quieres añadir este gasto de ' + amount + '€?');
    };

    document.addEventListener('DOMContentLoaded', function () {
        var flash = document.querySelector('.flash');
        if (flash) {
            var message = flash.textContent || '';
            var type = flash.classList.contains('flash-error') ? 'error' : 'ok';
            showToast(message, type);
            var fx = flash.getAttribute('data-fx') || '';
            if (fx === 'money' || fx === 'celebrate') {
                euroRain();
            }
            if (fx === 'sadmoney') {
                sadRain();
            }
            if (fx === 'motivate') {
                setTimeout(function () {
                    showToast('Buen trabajo. Siguiente paso: convertirla.', 'ok');
                }, 700);
            }
        }

        document.querySelectorAll('.js-copy-snippet').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var targetId = btn.getAttribute('data-copy-target');
                var textarea = document.getElementById(targetId);
                if (!textarea) return;

                var text = textarea.value || '';
                if (!text) {
                    showToast('No hay contenido para copiar.', 'error');
                    return;
                }

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(function () {
                        showToast('Copiado al portapapeles.', 'ok');
                    }).catch(function () {
                        textarea.select();
                        textarea.setSelectionRange(0, textarea.value.length);
                        document.execCommand('copy');
                        showToast('Copiado al portapapeles.', 'ok');
                    });
                } else {
                    textarea.select();
                    textarea.setSelectionRange(0, textarea.value.length);
                    document.execCommand('copy');
                    showToast('Copiado al portapapeles.', 'ok');
                }
            });
        });

        document.querySelectorAll('.btn-copy-mini[data-copy]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var text = btn.getAttribute('data-copy') || '';
                if (!text) {
                    showToast('No hay contenido para copiar.', 'error');
                    return;
                }

                var originalText = btn.textContent;

                copyToClipboard(text).then(function () {
                    btn.textContent = 'Copiado';
                    btn.classList.add('copied');
                    showToast('Copiado al portapapeles.', 'ok');

                    setTimeout(function () {
                        btn.textContent = originalText;
                        btn.classList.remove('copied');
                    }, 1400);
                }).catch(function () {
                    showToast('No se pudo copiar.', 'error');
                });
            });
        });

        document.querySelectorAll('.js-live-filter').forEach(function (input) {
            input.addEventListener('input', function () {
                var selector = input.getAttribute('data-target-selector');
                if (!selector) return;

                var q = (input.value || '').toLowerCase().trim();
                document.querySelectorAll(selector).forEach(function (row) {
                    var text = (row.getAttribute('data-filter-text') || '').toLowerCase();
                    row.style.display = (q === '' || text.indexOf(q) !== -1) ? '' : 'none';
                });
            });
        });

        (function setupPublicistaCampaignForm() {
            var form = document.getElementById('publicistaCampaignForm');
            var planningSelect = document.querySelector('.js-publicista-campaign-planning');
            var strategyOptionSelect = document.querySelector('.js-publicista-campaign-option');
            var strategyOptionInfo = document.getElementById('publicistaCampaignOptionInfo');
            var requiredBox = document.getElementById('publicistaCampaignRequiredProducts');
            var requiredInline = document.getElementById('publicistaCampaignRequiredInline');
            var productChecks = Array.prototype.slice.call(document.querySelectorAll('.js-publicista-campaign-product'));
            var accountChecks = Array.prototype.slice.call(document.querySelectorAll('.js-publicista-campaign-account-toggle'));

            if (!planningSelect && productChecks.length === 0 && accountChecks.length === 0 && !strategyOptionSelect) return;

            function requiredProducts() {
                if (!planningSelect) return 0;
                var option = planningSelect.options[planningSelect.selectedIndex];
                var raw = option ? parseInt(option.getAttribute('data-required-products') || '0', 10) : 0;
                return isNaN(raw) ? 0 : Math.max(0, raw);
            }

            function checkedProducts() {
                return productChecks.filter(function (input) { return input.checked; });
            }

            function syncRequiredBox() {
                if (!requiredBox) return;
                var required = requiredProducts();
                requiredBox.setAttribute('data-required-products', String(required));
                requiredBox.innerHTML = '<strong>' + required + '</strong><br>Debes seleccionar exactamente este número de perfiles.';
                if (requiredInline) {
                    requiredInline.textContent = String(required);
                }
            }

            function syncStrategyOptionInfo() {
                if (!strategyOptionInfo || !strategyOptionSelect) return;
                var option = strategyOptionSelect.options[strategyOptionSelect.selectedIndex];
                if (!option || !option.value) {
                    strategyOptionInfo.innerHTML = '<strong>Selecciona la versión concreta antes de guardar.</strong><br><span class="muted">Se usará esta versión para calcular anuncios, costes y composición.</span>';
                    return;
                }
                var label = option.getAttribute('data-label') || option.textContent || option.value;
                var total = option.getAttribute('data-total') || '-';
                var profiles = option.getAttribute('data-profiles') || '0';
                var warnings = option.getAttribute('data-warnings') || '0';
                var help = option.getAttribute('data-help') || '';
                var note = option.getAttribute('data-note') || '';
                var lines = [];
                lines.push('<strong>' + label + '</strong> · ' + total + ' · ' + profiles + ' perfiles · ' + warnings + ' avisos');
                if (help) {
                    lines.push('<span class="muted">' + help + '</span>');
                }
                if (note) {
                    lines.push('<span class="muted">' + note + '</span>');
                }
                strategyOptionInfo.innerHTML = lines.join('<br>');
            }

            function populateStrategyOptionsFromPlanning() {
                if (!planningSelect || !strategyOptionSelect) return;
                var planningOption = planningSelect.options[planningSelect.selectedIndex];
                var enabled = !!(planningOption && planningOption.value);
                var currentValue = strategyOptionSelect.value || '';
                strategyOptionSelect.innerHTML = '';

                var placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = enabled ? 'Elige la versión...' : 'Elige primero una estrategia...';
                strategyOptionSelect.appendChild(placeholder);

                if (!enabled) {
                    strategyOptionSelect.disabled = true;
                    syncStrategyOptionInfo();
                    return;
                }

                var rawOptions = planningOption.getAttribute('data-strategy-options') || '{}';
                var defaultCode = planningOption.getAttribute('data-default-option') || 'recommended';
                var optionMap = {};
                try {
                    optionMap = JSON.parse(rawOptions) || {};
                } catch (err) {
                    optionMap = {};
                }

                ['accepted', 'recommended', 'optimal'].forEach(function (code) {
                    var meta = optionMap[code];
                    if (!meta) return;
                    var option = document.createElement('option');
                    option.value = code;
                    option.textContent = meta.label || code;
                    option.setAttribute('data-label', meta.label || code);
                    option.setAttribute('data-total', meta.grand_total ? String(meta.grand_total).replace('.', ',') + ' €' : '-');
                    option.setAttribute('data-profiles', String(meta.profiles_total || 0));
                    option.setAttribute('data-warnings', String(meta.warnings_count || 0));
                    option.setAttribute('data-help', meta.decision_help || '');
                    option.setAttribute('data-note', meta.comparison_note || '');
                    strategyOptionSelect.appendChild(option);
                });

                strategyOptionSelect.disabled = false;
                if (currentValue && strategyOptionSelect.querySelector('option[value="' + currentValue + '"]')) {
                    strategyOptionSelect.value = currentValue;
                } else if (strategyOptionSelect.querySelector('option[value="' + defaultCode + '"]')) {
                    strategyOptionSelect.value = defaultCode;
                } else if (strategyOptionSelect.options.length > 1) {
                    strategyOptionSelect.selectedIndex = 1;
                } else {
                    strategyOptionSelect.selectedIndex = 0;
                }
                syncStrategyOptionInfo();
            }

            function enforceProductCount(changedInput) {
                var required = requiredProducts();
                var checked = checkedProducts();
                if (required > 0 && checked.length > required && changedInput) {
                    changedInput.checked = false;
                    showToast('Esta estrategia solo permite ' + required + ' perfiles.', 'error');
                    checked = checkedProducts();
                }

                productChecks.forEach(function (input) {
                    var disableMore = required > 0 && checked.length >= required && !input.checked;
                    input.disabled = disableMore;
                });
            }

            function accountListingInputs(accountId) {
                return Array.prototype.slice.call(document.querySelectorAll('.js-publicista-campaign-listing[data-account-id="' + accountId + '"]'));
            }

            function syncAccountListingPickers(options) {
                var opts = options || {};
                accountChecks.forEach(function (accountInput) {
                    var accountId = accountInput.getAttribute('data-account-id') || '';
                    var enabled = !!accountInput.checked;
                    var listingInputs = accountListingInputs(accountId);

                    listingInputs.forEach(function (listingInput) {
                        listingInput.disabled = !enabled;
                        if (!enabled) {
                            listingInput.checked = false;
                        }
                    });

                    document.querySelectorAll('[data-account-picker="' + accountId + '"]').forEach(function (picker) {
                        picker.style.opacity = enabled ? '1' : '.55';
                    });
                });
            }

            function validateExactProducts() {
                var required = requiredProducts();
                var selected = checkedProducts().length;
                if (required <= 0) {
                    showToast('Debes elegir una estrategia válida.', 'error');
                    return false;
                }
                if (!strategyOptionSelect || strategyOptionSelect.disabled || !strategyOptionSelect.value) {
                    showToast('Debes elegir qué versión de la estrategia quieres usar.', 'error');
                    return false;
                }
                if (selected !== required) {
                    showToast('Debes seleccionar exactamente ' + required + ' perfiles.', 'error');
                    return false;
                }
                return true;
            }

            function selectedAccountChecks() {
                return accountChecks.filter(function (input) { return !!input.checked; });
            }

            function selectedListingChecks() {
                return Array.prototype.slice.call(document.querySelectorAll('.js-publicista-campaign-listing:checked'));
            }

            function validateCampaignGenerationInputs() {
                if (!validateExactProducts()) {
                    return false;
                }

                var selectedAccounts = selectedAccountChecks();
                if (selectedAccounts.length <= 0) {
                    showToast('Debes seleccionar al menos una cuenta de portal.', 'error');
                    return false;
                }

                var selectedListings = selectedListingChecks();
                if (selectedListings.length <= 0) {
                    showToast('Debes seleccionar al menos un ID interno para la campaña.', 'error');
                    return false;
                }

                var selectedAccountIds = {};
                selectedAccounts.forEach(function (input) {
                    var accountId = (input.getAttribute('data-account-id') || '').trim();
                    if (accountId !== '') {
                        selectedAccountIds[accountId] = true;
                    }
                });

                for (var i = 0; i < selectedListings.length; i++) {
                    var listingAccountId = (selectedListings[i].getAttribute('data-account-id') || '').trim();
                    if (listingAccountId !== '' && !selectedAccountIds[listingAccountId]) {
                        showToast('Hay IDs internos seleccionados en cuentas no marcadas.', 'error');
                        return false;
                    }
                }

                return true;
            }

            if (planningSelect) {
                planningSelect.addEventListener('change', function () {
                    syncRequiredBox();
                    populateStrategyOptionsFromPlanning();
                    var required = requiredProducts();
                    var checked = checkedProducts();
                    if (required > 0 && checked.length > required) {
                        checked.slice(required).forEach(function (input) {
                            input.checked = false;
                        });
                        showToast('La estrategia cambió. He ajustado la selección al nuevo máximo permitido.', 'ok');
                    }
                    enforceProductCount(null);
                });
            }

            if (strategyOptionSelect) {
                strategyOptionSelect.addEventListener('change', syncStrategyOptionInfo);
            }

            productChecks.forEach(function (input) {
                input.addEventListener('change', function () {
                    enforceProductCount(input);
                });
            });

            accountChecks.forEach(function (input) {
                input.addEventListener('change', function () {
                    syncAccountListingPickers({
                        autoSelectListingsForAccount: input.checked ? (input.getAttribute('data-account-id') || '') : ''
                    });
                });
            });

            if (form) {
                form.addEventListener('submit', function (event) {
                    if (!validateCampaignGenerationInputs()) {
                        event.preventDefault();
                    }
                });
            }

            syncRequiredBox();
            populateStrategyOptionsFromPlanning();
            syncStrategyOptionInfo();
            enforceProductCount(null);
            syncAccountListingPickers();
        })();

        document.querySelectorAll('.js-publicista-clienta-filter').forEach(function (input) {
            function applyClientaFilter() {
                var selector = input.getAttribute('data-target-select');
                if (!selector) return;
                var select = document.querySelector(selector);
                if (!select) return;

                var q = (input.value || '').toLowerCase().trim();
                Array.prototype.forEach.call(select.options, function (option, index) {
                    if (index === 0) {
                        option.hidden = false;
                        return;
                    }
                    var text = (option.getAttribute('data-search') || option.textContent || '').toLowerCase();
                    option.hidden = (q !== '' && text.indexOf(q) === -1);
                });
            }

            input.addEventListener('input', applyClientaFilter);
            applyClientaFilter();
        });

        var appBackdrop = document.getElementById('appBackdrop');
        var sidebar = document.getElementById('appSidebar');
        var appMain = document.getElementById('appMain');
        var mobileMenuToggle = document.getElementById('mobileMenuToggle');
        var mobileAvisosToggle = document.getElementById('mobileAvisosToggle');
        var avisosPanel = document.getElementById('avisosPanel');
        var isMobile = window.matchMedia('(max-width: 767px)').matches;
        var params = new URLSearchParams(window.location.search || '');

        function closeMobilePanels() {
            document.body.classList.remove('mobile-nav-open');
            document.body.classList.remove('mobile-avisos-open');
            if (mobileMenuToggle) mobileMenuToggle.setAttribute('aria-expanded', 'false');
            if (mobileAvisosToggle) mobileAvisosToggle.setAttribute('aria-expanded', 'false');
            if (appBackdrop) appBackdrop.hidden = true;
        }

        function syncBackdrop() {
            if (!appBackdrop) return;
            var open = document.body.classList.contains('mobile-nav-open') || document.body.classList.contains('mobile-avisos-open');
            appBackdrop.hidden = !open;
        }

        if (mobileMenuToggle && sidebar) {
            mobileMenuToggle.addEventListener('click', function () {
                var open = document.body.classList.toggle('mobile-nav-open');
                document.body.classList.remove('mobile-avisos-open');
                mobileMenuToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (mobileAvisosToggle) mobileAvisosToggle.setAttribute('aria-expanded', 'false');
                syncBackdrop();
            });
        }

        if (mobileAvisosToggle && avisosPanel) {
            mobileAvisosToggle.addEventListener('click', function () {
                var open = document.body.classList.toggle('mobile-avisos-open');
                document.body.classList.remove('mobile-nav-open');
                mobileAvisosToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (mobileMenuToggle) mobileMenuToggle.setAttribute('aria-expanded', 'false');
                syncBackdrop();
            });
        }

        if (appBackdrop) {
            appBackdrop.addEventListener('click', closeMobilePanels);
        }

        document.querySelectorAll('#appSidebar a').forEach(function (link) {
            link.addEventListener('click', function () {
                closeMobilePanels();
            });
        });

        function setupMobileCrudPanels() {
            if (!isMobile) return;

            document.querySelectorAll('.cards.two > .panel:first-child').forEach(function (panel) {
                if (panel.classList.contains('crud-mobile-ready')) return;
                var form = panel.querySelector('form.form-grid, form.inline-form + form.form-grid, form');
                if (!form) return;
                var parentCards = panel.parentElement;
                if (!parentCards || !parentCards.classList.contains('cards')) return;
                var sibling = panel.nextElementSibling;
                if (!sibling || !sibling.classList.contains('panel')) return;

                var firstHeading = panel.querySelector('h2');
                var secondHeading = sibling.querySelector('h2');
                if (!firstHeading || !secondHeading) return;
                var secondText = (secondHeading.textContent || '').toLowerCase();
                if (secondText.indexOf('listado') === -1 && secondText.indexOf('resumen') === -1) return;

                panel.classList.add('crud-mobile-ready');

                var bodyNodes = Array.prototype.slice.call(panel.childNodes);
                var wrapper = document.createElement('div');
                wrapper.className = 'crud-mobile-body';

                bodyNodes.forEach(function (node, index) {
                    if (index === 0 && node === firstHeading.parentElement && node.classList && node.classList.contains('section-head')) {
                        return;
                    }
                    if (node === firstHeading) return;
                    wrapper.appendChild(node);
                });

                panel.innerHTML = '';

                var header = document.createElement('div');
                header.className = 'crud-mobile-head';

                var title = document.createElement('div');
                title.className = 'crud-mobile-title';
                title.textContent = firstHeading.textContent || 'Formulario';

                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'crud-mobile-toggle';
                button.textContent = 'Nuevo';

                var forceOpen = params.has('edit') || params.has('convert') || params.has('new');
                if ((firstHeading.textContent || '').toLowerCase().indexOf('editar') !== -1 || (firstHeading.textContent || '').toLowerCase().indexOf('ficha') !== -1) {
                    forceOpen = true;
                    button.textContent = 'Editar';
                }

                if (forceOpen) {
                    panel.classList.add('is-open');
                    button.textContent = 'Ocultar';
                }

                button.addEventListener('click', function () {
                    var open = panel.classList.toggle('is-open');
                    button.textContent = open ? 'Ocultar' : ((firstHeading.textContent || '').toLowerCase().indexOf('editar') !== -1 || (firstHeading.textContent || '').toLowerCase().indexOf('ficha') !== -1 ? 'Editar' : 'Nuevo');
                    if (open) {
                        var firstInput = panel.querySelector('input, select, textarea');
                        if (firstInput) firstInput.focus();
                    }
                });

                header.appendChild(title);
                header.appendChild(button);
                panel.appendChild(header);
                panel.appendChild(wrapper);
            });
        }

        function setupVoiceCommandPanel() {
            var panel = document.getElementById('voiceCommandPanel');
            var panelBackdrop = document.getElementById('voiceCommandBackdrop');
            var processingOverlay = document.getElementById('voiceProcessingOverlay');
            var processingText = document.getElementById('voiceProcessingText');
            var closeBtn = document.getElementById('voiceCommandClose');
            var startBtn = document.getElementById('voiceStartButton');
            var stopBtn = document.getElementById('voiceStopButton');
            var clearBtn = document.getElementById('voiceClearButton');
            var sendBtn = document.getElementById('voiceSendButton');
            var input = document.getElementById('voiceCommandInput');
            var status = document.getElementById('voiceCommandStatus');
            var support = document.getElementById('voiceCommandSupport');
            var stage = document.getElementById('voiceCommandStage');
            var responseBox = document.getElementById('voiceCommandResponse');
            var toggleButtons = Array.prototype.slice.call(document.querySelectorAll('[data-voice-command-toggle]'));
            var RecognitionCtor = window.SpeechRecognition || window.webkitSpeechRecognition || null;
            var recognition = null;
            var recognitionRunId = 0;
            var isListening = false;
            var lastResponse = null;
            var currentPending = null;
            var finalSegmentMap = {};
            var speechAlternatives = [];
            var dictationActive = false;
            var manualStopRequested = false;
            var autoSendEnabled = false;
            var hasSpeech = false;
            var silenceTimer = null;
            var restartTimer = null;
            var lastSpeechAt = 0;
            var stopReason = '';
            var lastErrorCode = '';
            var autoSubmittedCapture = false;
            var silenceWindowMs = 2400;
            var restartDelayMs = 180;

            if (!panel || !input || !sendBtn) return;

            function escapeHtml(text) {
                var div = document.createElement('div');
                div.textContent = text == null ? '' : String(text);
                return div.innerHTML;
            }

            function normalizeTranscript(text) {
                return String(text || '').replace(/\s+/g, ' ').trim();
            }

            function normalizeSpeechToken(text) {
                var value = String(text || '').toLowerCase();
                if (value.normalize) {
                    value = value.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
                }
                return value.replace(/[^a-z0-9]+/g, '');
            }

            function sanitizeSpeechNoise(text) {
                var value = normalizeTranscript(text);
                if (!value) return '';

                var tokens = value.split(' ');
                var compact = [];
                tokens.forEach(function (token) {
                    var cleanToken = normalizeSpeechToken(token);
                    if (!cleanToken) return;

                    var lastToken = compact.length ? compact[compact.length - 1] : '';
                    if (lastToken && normalizeSpeechToken(lastToken) === cleanToken) {
                        return;
                    }

                    compact.push(token);
                });

                var changed = true;
                while (changed) {
                    changed = false;
                    for (var size = Math.min(4, Math.floor(compact.length / 2)); size >= 2; size--) {
                        for (var index = 0; index <= compact.length - (size * 2); index++) {
                            var matches = true;
                            for (var offset = 0; offset < size; offset++) {
                                if (normalizeSpeechToken(compact[index + offset]) !== normalizeSpeechToken(compact[index + size + offset])) {
                                    matches = false;
                                    break;
                                }
                            }
                            if (matches) {
                                compact.splice(index + size, size);
                                changed = true;
                                break;
                            }
                        }
                        if (changed) break;
                    }
                }

                return normalizeTranscript(compact.join(' '));
            }

            function getOrderedFinalSegments() {
                return Object.keys(finalSegmentMap).sort(function (a, b) {
                    var aParts = String(a).split(':');
                    var bParts = String(b).split(':');
                    var aRun = Number(aParts[0] || 0);
                    var bRun = Number(bParts[0] || 0);
                    var aIndex = Number(aParts[1] || 0);
                    var bIndex = Number(bParts[1] || 0);
                    if (aRun !== bRun) return aRun - bRun;
                    return aIndex - bIndex;
                }).map(function (key) {
                    return finalSegmentMap[key];
                }).filter(function (item) {
                    return !!item;
                });
            }

            function uniqueTranscriptList(items) {
                var out = [];
                (items || []).forEach(function (item) {
                    var value = sanitizeSpeechNoise(item);
                    if (value && out.indexOf(value) === -1) out.push(value);
                });
                return out;
            }

            function combineAlternatives(base, additions) {
                var safeBase = base && base.length ? base : [''];
                var safeAdditions = uniqueTranscriptList(additions);
                var out = [];

                if (!safeAdditions.length) return uniqueTranscriptList(base);

                safeBase.forEach(function (prefix) {
                    safeAdditions.forEach(function (suffix) {
                        var combined = sanitizeSpeechNoise((prefix ? prefix + ' ' : '') + suffix);
                        if (combined && out.indexOf(combined) === -1) out.push(combined);
                    });
                });

                return out.slice(0, 6);
            }

            function setToggleExpanded(expanded) {
                toggleButtons.forEach(function (btn) {
                    btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                });
            }

            function setToggleListening(listening) {
                toggleButtons.forEach(function (btn) {
                    btn.classList.toggle('is-listening', !!listening);
                });
            }

            function clearRecognitionTimers() {
                if (silenceTimer) {
                    clearTimeout(silenceTimer);
                    silenceTimer = null;
                }
                if (restartTimer) {
                    clearTimeout(restartTimer);
                    restartTimer = null;
                }
            }

            function syncRecorderButtons() {
                if (startBtn) startBtn.disabled = isListening;
                if (stopBtn) stopBtn.disabled = !isListening && !dictationActive;
                setToggleListening(isListening || dictationActive);
            }

            function setStatus(text, type) {
                if (!status) return;
                status.textContent = text;
                status.className = 'voice-command-status';
                status.classList.add('stage-' + (type || 'idle'));
            }

            function setStage(text) {
                if (!stage) return;
                stage.textContent = text;
            }

            function setProcessingOverlay(open, text) {
                if (!processingOverlay) return;
                processingOverlay.hidden = !open;
                processingOverlay.setAttribute('aria-hidden', open ? 'false' : 'true');
                document.body.classList.toggle('voice-processing-open', !!open);
                if (processingText) {
                    processingText.textContent = open
                        ? (text || 'Interpretando tu orden dentro del CRM…')
                        : 'Interpretando tu orden dentro del CRM…';
                }
            }

            function openPanel(options) {
                options = options || {};
                panel.hidden = false;
                panel.setAttribute('aria-hidden', 'false');
                document.body.classList.add('voice-command-open');
                if (panelBackdrop) panelBackdrop.hidden = false;
                setToggleExpanded(true);
                if (!options.skipFocus) {
                    setTimeout(function () { input.focus(); }, 40);
                }
            }

            function closePanel() {
                autoSendEnabled = false;
                dictationActive = false;
                manualStopRequested = true;
                stopReason = 'close';
                clearRecognitionTimers();
                setProcessingOverlay(false);
                panel.hidden = true;
                panel.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('voice-command-open');
                if (panelBackdrop) panelBackdrop.hidden = true;
                setToggleExpanded(false);
                syncRecorderButtons();
                if (recognition && isListening) recognition.stop();
            }

            function collectContext() {
                var search = new URLSearchParams(window.location.search || '');
                return {
                    page: document.body.getAttribute('data-page') || search.get('page') || 'dashboard',
                    tab: search.get('tab') || '',
                    edit: search.get('edit') || '',
                    view: search.get('view') || '',
                    convert: search.get('convert') || '',
                    avtab: search.get('avtab') || '',
                    from: search.get('from') || '',
                    to: search.get('to') || '',
                    rama: search.get('rama') || '',
                    tipo: search.get('tipo') || '',
                    cliente_id: search.get('cliente_id') || '',
                    dashboard_month: search.get('dashboard_month') || '',
                    query_string: search.toString(),
                    request_uri: window.location.pathname + window.location.search
                };
            }

            function humanStageLabel(stageValue) {
                var map = {
                    interpreted: 'Interpretada',
                    resolved: 'Resuelta',
                    executed: 'Ejecutada',
                    needs_confirmation: 'Pendiente de confirmar',
                    needs_clarification: 'Pendiente de aclarar',
                    error: 'Error'
                };
                return map[stageValue] || stageValue || 'Sin estado';
            }

            function buildAnalyticsHtml(analytics) {
                if (!analytics || !analytics.cards) return '';
                var html = '<div class="voice-analytics">';
                html += '<div class="voice-analytics-cards">';
                analytics.cards.forEach(function (card) {
                    html += '<div class="voice-analytics-card"><span>' + escapeHtml(card.label || '') + '</span><strong>' + escapeHtml(card.value || '') + '</strong></div>';
                });
                html += '</div>';
                if (analytics.branches) {
                    html += '<div class="voice-analytics-branches">';
                    Object.keys(analytics.branches).forEach(function (key) {
                        html += '<div class="voice-analytics-branch"><span>' + escapeHtml(key) + '</span><strong>' + escapeHtml(String(analytics.branches[key])) + '</strong></div>';
                    });
                    html += '</div>';
                }
                if (analytics.best_clienta && analytics.best_clienta.nombre) {
                    html += '<div class="voice-analytics-best"><strong>Mejor ficha:</strong> ' + escapeHtml(analytics.best_clienta.nombre) + ' · ' + escapeHtml(String(analytics.best_clienta.total || '')) + '</div>';
                }
                if (analytics.insights && analytics.insights.length) {
                    html += '<ul class="voice-analytics-insights">';
                    analytics.insights.forEach(function (insight) {
                        html += '<li>' + escapeHtml(insight) + '</li>';
                    });
                    html += '</ul>';
                }
                html += '</div>';
                return html;
            }

            function renderResponse(data) {
                lastResponse = data || null;
                if (!responseBox) return;
                if (!data) {
                    currentPending = null;
                    responseBox.innerHTML = '';
                    return;
                }

                currentPending = data.pending && data.pending.token ? data.pending : null;
                var html = '';
                html += '<div class="voice-response-card stage-' + escapeHtml(data.stage || 'interpreted') + '">';
                html += '<div class="voice-response-top">';
                html += '<strong>' + escapeHtml((data.ux && data.ux.headline) || data.intent_label || data.intent || 'Sin intención') + '</strong>';
                html += '<span class="voice-response-stage">' + escapeHtml(humanStageLabel(data.stage)) + '</span>';
                html += '</div>';

                if (data.ux && data.ux.detail) {
                    html += '<p class="voice-response-message">' + escapeHtml(data.ux.detail) + '</p>';
                } else if (data.message) {
                    html += '<p class="voice-response-message">' + escapeHtml(data.message) + '</p>';
                }

                if (data.transcript) {
                    html += '<div class="voice-response-review"><span>Entendido:</span> <strong>' + escapeHtml(data.transcript) + '</strong></div>';
                }

                if (data.resolved_entities && data.resolved_entities.length) {
                    html += '<div class="voice-response-chip-row">';
                    data.resolved_entities.forEach(function (item) {
                        html += '<span class="voice-response-chip">Resuelto: ' + escapeHtml(item.label || item.id || item.kind || '') + '</span>';
                    });
                    html += '</div>';
                }

                if (data.missing_fields && data.missing_fields.length) {
                    html += '<div class="voice-response-chip-row">';
                    data.missing_fields.forEach(function (field) {
                        html += '<span class="voice-response-chip">Falta: ' + escapeHtml(field) + '</span>';
                    });
                    html += '</div>';
                }

                if (data.ai && (data.ai.model || data.ai.used_fallback)) {
                    html += '<div class="voice-response-chip-row">';
                    if (data.ai.model) html += '<span class="voice-response-chip">Modelo: ' + escapeHtml(data.ai.model) + '</span>';
                    html += '<span class="voice-response-chip">Motor: ' + (data.ai.used_fallback ? 'respaldo local' : 'IA') + '</span>';
                    html += '</div>';
                }

                if (data.pending && data.pending.token) {
                    html += '<div class="voice-response-chip-row">';
                    html += '<span class="voice-response-chip">Pendiente: ' + escapeHtml(data.pending.kind || '') + '</span>';
                    if (data.pending.expires_at) html += '<span class="voice-response-chip">Caduca: ' + escapeHtml(data.pending.expires_at) + '</span>';
                    html += '</div>';
                }

                if (data.stage === 'needs_confirmation' && data.pending && data.pending.token) {
                    html += '<div class="voice-response-actions">';
                    html += '<button type="button" class="voice-response-btn voice-response-btn-confirm" data-followup-action="confirm">Confirmar</button>';
                    html += '<button type="button" class="voice-response-btn" data-followup-action="cancel">Cancelar</button>';
                    html += '</div>';
                }

                if (data.stage === 'needs_clarification' && data.options && data.options.length) {
                    html += '<div class="voice-response-actions voice-response-actions-stack">';
                    data.options.forEach(function (item) {
                        var selectionKey = (item.kind || '') + ':' + (item.id || '');
                        html += '<button type="button" class="voice-response-btn" data-followup-action="select_option" data-followup-value="' + escapeHtml(selectionKey) + '">Usar ' + escapeHtml(item.label || item.id || selectionKey) + '</button>';
                    });
                    html += '<button type="button" class="voice-response-btn" data-followup-action="cancel">Cancelar</button>';
                    html += '</div>';
                }

                if (data.analytics && data.execution_mode === 'readonly') {
                    html += buildAnalyticsHtml(data.analytics);
                }

                if (data.redirect_url) {
                    html += '<div class="voice-response-link-wrap"><a class="mini-link" href="' + encodeURI(data.redirect_url) + '">Abrir resultado</a></div>';
                }

                if (data.errors && data.errors.length) {
                    html += '<div class="voice-response-errors">' + data.errors.map(function (err) {
                        return '<span class="voice-response-error">' + escapeHtml(err) + '</span>';
                    }).join('') + '</div>';
                }

                html += '</div>';
                responseBox.innerHTML = html;

                responseBox.querySelectorAll('[data-followup-action]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        sendVoiceCommand({
                            text: '',
                            pendingToken: currentPending ? currentPending.token : '',
                            followupAction: btn.getAttribute('data-followup-action') || '',
                            followupValue: btn.getAttribute('data-followup-value') || '',
                            preserveInput: true
                        });
                    });
                });
            }

            function sendVoiceCommand(options) {
                options = options || {};
                var text = typeof options.text === 'string' ? sanitizeSpeechNoise(options.text) : sanitizeSpeechNoise(input.value || '');
                var pendingToken = options.pendingToken || (currentPending && currentPending.token ? currentPending.token : '');
                var followupAction = options.followupAction || '';
                var followupValue = options.followupValue || '';
                var alternatives = uniqueTranscriptList(options.alternatives || speechAlternatives);
                var source = options.source || (alternatives.length ? 'speech' : 'manual');
                if (!text && !pendingToken) {
                    setStatus('Escribe o dicta una orden primero.', 'error');
                    showToast('No hay ninguna orden para enviar.', 'error');
                    return;
                }

                var formData = new FormData();
                formData.append('action', 'voice_command');
                formData.append('voice_command_text', text);
                formData.append('voice_context_json', JSON.stringify(collectContext()));
                formData.append('voice_input_source', source);
                if (alternatives.length) formData.append('voice_alternatives_json', JSON.stringify(alternatives));
                if (pendingToken) formData.append('voice_pending_token', pendingToken);
                if (followupAction) formData.append('voice_followup_action', followupAction);
                if (followupValue) formData.append('voice_followup_value', followupValue);

                setStatus('Procesando orden…', 'processing');
                setStage('Procesando');
                sendBtn.disabled = true;
                setProcessingOverlay(true, pendingToken || followupAction
                    ? 'Resolviendo la siguiente acción dentro del CRM…'
                    : 'Interpretando tu orden dentro del CRM…');

                fetch(window.location.pathname + window.location.search, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }).then(function (response) {
                    return response.json();
                }).then(function (data) {
                    renderResponse(data);
                    setStage(humanStageLabel(data.stage));

                    if (data.stage === 'error') {
                        setStatus('Error en la orden.', 'error');
                        showToast('La orden devolvió un error.', 'error');
                    } else if (data.stage === 'needs_clarification') {
                        setStatus('Necesita aclaración.', 'processing');
                        showToast('Falta un dato para continuar.', 'error');
                    } else if (data.stage === 'needs_confirmation') {
                        setStatus('Esperando confirmación.', 'processing');
                        showToast('Revisa la acción antes de confirmarla.', 'ok');
                    } else if (data.ai && data.ai.used_fallback) {
                        setStatus('Orden procesada con parser de respaldo.', 'ok');
                        showToast('Orden procesada con respaldo local.', 'ok');
                    } else {
                        setStatus('Orden procesada.', 'ok');
                        showToast(data.execution_mode === 'readonly' ? 'Consulta lista.' : 'Orden procesada correctamente.', 'ok');
                    }

                    if (data.stage === 'executed' && data.redirect_url && data.execution_mode !== 'readonly') {
                        setTimeout(function () {
                            window.location.href = data.redirect_url;
                        }, 700);
                    }
                }).catch(function () {
                    setStatus('No se pudo procesar la orden.', 'error');
                    setStage('Error');
                    renderResponse({
                        stage: 'error',
                        intent: 'unsupported_command',
                        intent_label: 'Error de comunicación',
                        message: 'La petición al backend no devolvió una respuesta válida.',
                        ux: { headline: 'Error de comunicación', detail: 'La petición al backend no devolvió una respuesta válida.' },
                        errors: ['fetch_failed']
                    });
                    showToast('No se pudo procesar la orden.', 'error');
                }).finally(function () {
                    setProcessingOverlay(false);
                    sendBtn.disabled = false;
                    if (!options.preserveInput && currentPending == null && (lastResponse && lastResponse.stage === 'executed')) {
                        input.value = '';
                    }
                });
            }

            function scheduleSilenceStop() {
                if (!dictationActive) return;
                if (silenceTimer) clearTimeout(silenceTimer);
                silenceTimer = setTimeout(function () {
                    if (!dictationActive) return;
                    stopReason = 'silence';
                    if (recognition && isListening) {
                        recognition.stop();
                    } else {
                        finalizeCapture();
                    }
                }, silenceWindowMs);
            }

            function updateInputFromParts(interimParts) {
                var full = sanitizeSpeechNoise(getOrderedFinalSegments().join(' ') + ' ' + (interimParts || []).join(' '));
                input.value = full;
            }

            function resetCaptureState(clearText) {
                clearRecognitionTimers();
                recognitionRunId = 0;
                finalSegmentMap = {};
                speechAlternatives = [];
                dictationActive = false;
                manualStopRequested = false;
                hasSpeech = false;
                lastSpeechAt = 0;
                stopReason = '';
                lastErrorCode = '';
                autoSubmittedCapture = false;
                setProcessingOverlay(false);
                if (clearText) input.value = '';
                syncRecorderButtons();
            }

            function finalizeCapture() {
                clearRecognitionTimers();
                syncRecorderButtons();

                var text = sanitizeSpeechNoise(input.value || '');
                input.value = text;
                if (!text) {
                    setStatus('No he captado ninguna orden.', 'idle');
                    setStage('Sin enviar');
                    return;
                }

                if (autoSendEnabled && !autoSubmittedCapture) {
                    autoSubmittedCapture = true;
                    setStatus('Texto capturado. Enviando orden…', 'processing');
                    setStage('Procesando');
                    sendVoiceCommand({
                        text: text,
                        alternatives: uniqueTranscriptList([text].concat(speechAlternatives)),
                        source: 'speech'
                    });
                    return;
                }

                setStatus('Texto capturado.', 'ok');
                setStage('Texto listo');
            }

            function requestStopRecording(reason) {
                stopReason = reason || 'manual';
                manualStopRequested = true;
                if (recognition && isListening) {
                    recognition.stop();
                } else {
                    dictationActive = false;
                    finalizeCapture();
                }
            }

            function startVoiceCapture() {
                openPanel({ skipFocus: true });
                renderResponse(null);
                currentPending = null;
                resetCaptureState(false);
                autoSendEnabled = true;
                dictationActive = true;
                manualStopRequested = false;
                setStatus('Preparando micrófono…', 'processing');
                setStage('Preparando');
                syncRecorderButtons();

                try {
                    recognition.start();
                } catch (err) {
                    dictationActive = false;
                    syncRecorderButtons();
                    setStatus('No se pudo iniciar el micrófono.', 'error');
                    setStage('Error de voz');
                }
            }

            toggleButtons.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    if (dictationActive || isListening) {
                        requestStopRecording('manual');
                    } else {
                        startVoiceCapture();
                    }
                });
            });

            if (closeBtn) closeBtn.addEventListener('click', closePanel);
            if (panelBackdrop) panelBackdrop.addEventListener('click', closePanel);

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && !panel.hidden) closePanel();
            });

            if (clearBtn) {
                clearBtn.addEventListener('click', function () {
                    if (recognition && isListening) {
                        autoSendEnabled = false;
                        dictationActive = false;
                        recognition.stop();
                    }
                    resetCaptureState(true);
                    currentPending = null;
                    setStatus('Texto limpiado. Listo para escuchar.', 'idle');
                    setStage('Sin enviar');
                    renderResponse(null);
                });
            }

            if (sendBtn) {
                sendBtn.addEventListener('click', function () {
                    sendVoiceCommand({
                        source: 'manual',
                        alternatives: speechAlternatives
                    });
                });
            }

            if (input) {
                input.addEventListener('keydown', function (event) {
                    if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
                        sendVoiceCommand({
                            source: 'manual',
                            alternatives: speechAlternatives
                        });
                    }
                });
            }

            if (!RecognitionCtor) {
                if (support) support.textContent = 'Tu navegador no soporta reconocimiento de voz. Puedes escribir la orden manualmente.';
                if (startBtn) startBtn.disabled = true;
                setStatus('Reconocimiento de voz no disponible.', 'error');
                return;
            }

            if (support) support.textContent = 'Puedes hablar en español y el sistema esperará una pausa más larga antes de cortar. Además enviará alternativas a la IA para interpretar mejor el comando del CRM.';

            recognition = new RecognitionCtor();
            recognition.lang = 'es-ES';
            recognition.continuous = true;
            recognition.interimResults = true;
            recognition.maxAlternatives = 4;

            recognition.onstart = function () {
                recognitionRunId += 1;
                isListening = true;
                lastErrorCode = '';
                setStatus('Escuchando… habla ahora.', 'listening');
                setStage('Escuchando');
                syncRecorderButtons();
            };

            recognition.onresult = function (event) {
                var interimParts = [];
                for (var i = event.resultIndex; i < event.results.length; i++) {
                    var result = event.results[i];
                    if (!result) continue;

                    if (result.isFinal) {
                        var alternatives = [];
                        for (var j = 0; j < result.length; j++) {
                            if (result[j] && result[j].transcript) {
                                alternatives.push(sanitizeSpeechNoise(result[j].transcript));
                            }
                        }
                        alternatives = uniqueTranscriptList(alternatives);
                        if (alternatives.length) {
                            finalSegmentMap[recognitionRunId + ':' + i] = alternatives[0];
                            speechAlternatives = combineAlternatives(speechAlternatives, alternatives);
                            hasSpeech = true;
                            lastSpeechAt = Date.now();
                            lastErrorCode = '';
                        }
                    } else if (result[0] && result[0].transcript) {
                        interimParts.push(sanitizeSpeechNoise(result[0].transcript));
                    }
                }
                updateInputFromParts(interimParts);
                if (interimParts.length) {
                    hasSpeech = true;
                    lastSpeechAt = Date.now();
                }
                if (sanitizeSpeechNoise(input.value || '') !== '') scheduleSilenceStop();
            };

            recognition.onerror = function (event) {
                isListening = false;
                lastErrorCode = event.error || '';
                syncRecorderButtons();

                if (event.error === 'no-speech' && dictationActive && hasSpeech) {
                    setStatus('Pausa corta detectada, sigo escuchando…', 'listening');
                    setStage('Escuchando');
                    return;
                }

                var map = {
                    'not-allowed': 'El navegador no tiene permiso para usar el micrófono.',
                    'audio-capture': 'No se ha detectado ningún micrófono disponible.',
                    'no-speech': 'No se ha detectado voz. Prueba otra vez.',
                    'network': 'Error de red del reconocimiento de voz.'
                };
                setStatus(map[event.error] || 'Ha fallado el reconocimiento de voz.', 'error');
                setStage('Error de voz');
            };

            recognition.onend = function () {
                isListening = false;
                syncRecorderButtons();

                if (!dictationActive) {
                    return;
                }

                var now = Date.now();
                var elapsedSinceSpeech = lastSpeechAt ? (now - lastSpeechAt) : 999999;
                var shouldRestart = !manualStopRequested
                    && stopReason !== 'silence'
                    && stopReason !== 'close'
                    && hasSpeech
                    && elapsedSinceSpeech < silenceWindowMs
                    && lastErrorCode !== 'not-allowed'
                    && lastErrorCode !== 'audio-capture';

                if (shouldRestart) {
                    setStatus('Pausa corta detectada, sigo escuchando…', 'listening');
                    setStage('Escuchando');
                    restartTimer = setTimeout(function () {
                        if (!dictationActive) return;
                        try {
                            recognition.start();
                        } catch (err) {
                            dictationActive = false;
                            finalizeCapture();
                        }
                    }, restartDelayMs);
                    return;
                }

                dictationActive = false;
                finalizeCapture();
            };

            if (startBtn) {
                startBtn.addEventListener('click', function () {
                    startVoiceCapture();
                });
            }

            if (stopBtn) {
                stopBtn.addEventListener('click', function () {
                    requestStopRecording('manual');
                });
            }
        }

        setupMobileCrudPanels();
        setupVoiceCommandPanel();

        window.addEventListener('resize', function () {
            if (!window.matchMedia('(max-width: 767px)').matches) {
                closeMobilePanels();
            }
        });
    });
})();

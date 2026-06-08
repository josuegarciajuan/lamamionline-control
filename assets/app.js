(function () {
    function scrollActiveSubtabIntoView() {
        const active = document.querySelector('.subtabs .subtab.active, .subtabs a.subtab.active, .subtabs .subtab-split.is-active');
        if (active) {
            try {
                active.scrollIntoView({ inline: 'center', behavior: 'smooth', block: 'nearest' });
            } catch(e) {
                active.scrollIntoView(false);
            }
        }
    }

    // =========================================================================
    // MOBILE-REDESIGN F2: Universal table → card stack converter
    // Converts every <table> into a vertical .card-stack on mobile (≤767px).
    // Preserves inline forms, buttons, and interactive elements inside cells.
    // Skips tables with data-no-card-view, agent tables, or no <thead>.
    // =========================================================================
    function convertTablesToCards() {
        if (!window.matchMedia('(max-width: 767px)').matches) return;

        var wrappers = document.querySelectorAll('.table-wrap');
        wrappers.forEach(function (wrapper) {
            var table = wrapper.querySelector('table');
            if (!table) return;

            // Skip: tables explicitly excluded
            if (table.hasAttribute('data-no-card-view')) return;
            // Skip: agent table (has its own card mode at 640px)
            if (table.closest('.agent-table-wrap')) return;
            // Skip: already converted
            if (wrapper.parentNode && wrapper.parentNode.querySelector('.card-stack')) return;

            var thead = table.querySelector('thead');
            if (!thead) {
                // Tables without thead: just add scroll indicator and continue
                wrapper.style.webkitOverflowScrolling = 'touch';
                return;
            }

            // Get column labels from <thead>
            var labels = [];
            thead.querySelectorAll('th').forEach(function (th) {
                labels.push(th.textContent.trim());
            });
            if (labels.length === 0) return;

            // Build card stack
            var cardStack = document.createElement('div');
            cardStack.className = 'card-stack';

            var rows = table.querySelectorAll('tbody > tr');
            var cardCount = 0;

            rows.forEach(function (row) {
                // Skip hidden detail rows (expand/collapse rows)
                if (row.style.display === 'none') return;
                // Skip rows hidden by inline style
                if (row.classList.contains('run-log-detail')) return;

                var cells = row.querySelectorAll('td, th');
                if (cells.length === 0) return;

                // Check for colspan empty-state row
                var firstCell = cells[0];
                if (firstCell.hasAttribute('colspan') && cells.length === 1) {
                    var card = document.createElement('div');
                    card.className = 'card-stack-item card-stack-empty';
                    card.textContent = firstCell.textContent;
                    cardStack.appendChild(card);
                    return;
                }

                // Check for triage/section header rows
                if (row.classList.contains('commercial-triage-group-row')) {
                    var headerCard = document.createElement('div');
                    headerCard.className = 'card-stack-item card-stack-section-header';
                    headerCard.textContent = firstCell.textContent.trim();
                    cardStack.appendChild(headerCard);
                    return;
                }

                // Normal data row: create a card
                var card = document.createElement('div');
                card.className = 'card-stack-item';

                var hasActions = false;
                var actionsHtml = '';

                cells.forEach(function (td, i) {
                    var content = td.innerHTML.trim();
                    if (!content || content === '&nbsp;' || content === '&mdash;' || content === '—' || content === '-') return;

                    var label = labels[i] ? labels[i] : '';
                    // Skip "Acciones" column — render its content as bottom action bar
                    if (label.toLowerCase() === 'acciones' || label.toLowerCase() === 'acción' || label.toLowerCase() === 'action' || label === '' || label === '—') {
                        if (content) {
                            hasActions = true;
                            actionsHtml += content;
                        }
                        return;
                    }

                    var rowDiv = document.createElement('div');
                    rowDiv.className = 'card-stack-row';

                    var labelSpan = document.createElement('span');
                    labelSpan.className = 'card-stack-label';
                    labelSpan.textContent = label;

                    var valueDiv = document.createElement('div');
                    valueDiv.className = 'card-stack-value';
                    valueDiv.innerHTML = content;

                    rowDiv.appendChild(labelSpan);
                    rowDiv.appendChild(valueDiv);
                    card.appendChild(rowDiv);
                });

                // Append action buttons at the bottom of the card
                if (hasActions) {
                    var actionBar = document.createElement('div');
                    actionBar.className = 'card-stack-actions';
                    actionBar.innerHTML = actionsHtml;
                    card.appendChild(actionBar);
                }

                if (card.children.length > 0) {
                    cardStack.appendChild(card);
                    cardCount++;
                }
            });

            // Replace the table wrapper with the card stack
            if (cardCount > 0) {
                wrapper.parentNode.replaceChild(cardStack, wrapper);
            }
        });
    }

    // =========================================================================
    // MOBILE-REDESIGN F4: Subtab overflow dropdown (···)
    // If a .subtabs container has more than 6 visible chips, hide extras and
    // show a "···" toggle that expands them.
    // =========================================================================
    function setupSubtabOverflow() {
        if (!window.matchMedia('(max-width: 767px)').matches) return;

        document.querySelectorAll('.subtabs').forEach(function (container) {
            var items = container.querySelectorAll('.subtab, a.subtab, .subtab-split');
            if (items.length <= 6) return;

            // Hide items beyond 5
            var hiddenItems = [];
            for (var i = 5; i < items.length; i++) {
                items[i].style.display = 'none';
                hiddenItems.push(items[i]);
            }

            // Create "···" toggle button
            var moreBtn = document.createElement('button');
            moreBtn.type = 'button';
            moreBtn.className = 'subtab subtab-more';
            moreBtn.textContent = '···';
            moreBtn.style.cssText = items[0].getAttribute('style') || '';
            moreBtn.style.display = '';
            moreBtn.style.minWidth = 'auto';
            moreBtn.style.padding = '5px 10px';
            moreBtn.style.fontSize = '11px';
            moreBtn.style.borderRadius = '20px';
            moreBtn.style.fontWeight = '700';
            moreBtn.style.cursor = 'pointer';
            moreBtn.setAttribute('aria-expanded', 'false');
            moreBtn.setAttribute('aria-label', 'Mostrar más pestañas');

            container.appendChild(moreBtn);

            // Toggle hidden items
            var expanded = false;
            moreBtn.addEventListener('click', function () {
                expanded = !expanded;
                hiddenItems.forEach(function (item) {
                    item.style.display = expanded ? '' : 'none';
                });
                moreBtn.textContent = expanded ? '▲' : '···';
                moreBtn.setAttribute('aria-expanded', String(expanded));
            });
        });
    }

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

        if (mobileAvisosToggle) {
            mobileAvisosToggle.addEventListener('click', function () {
                // Empty or hidden panel: redirect to full avisos page
                if (!avisosPanel || !avisosPanel.children.length || avisosPanel.textContent.trim() === '') {
                    window.location.href = 'index.php?page=avisos';
                    return;
                }
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

        // =========================================================================
        // MOBILE-REDESIGN F3: Form bottom sheets + FAB
        // Converts form+table .cards.two layouts into bottom sheets on mobile.
        // Each form gets a FAB (floating button) that opens it as a sliding panel.
        // =========================================================================
        function setupFormSheets() {
            if (!isMobile) return;

            var sheetsCreated = 0;
            document.querySelectorAll('.cards.two').forEach(function (cardsContainer) {
                var panels = cardsContainer.querySelectorAll(':scope > .panel');
                if (panels.length < 2) return;

                var formPanel = panels[0];
                var tablePanel = panels[1];

                // Detect if first panel has a form
                var form = formPanel.querySelector('form');
                if (!form) return;

                // Detect if second panel has a listing/resumen table
                var secondHeading = tablePanel.querySelector('h2');
                if (!secondHeading) return;
                var secondText = (secondHeading.textContent || '').toLowerCase();
                if (secondText.indexOf('listado') === -1 && secondText.indexOf('resumen') === -1 && secondText.indexOf('registro') === -1) return;

                // Get form title
                var firstHeading = formPanel.querySelector('h2');
                var formTitle = firstHeading ? (firstHeading.textContent || 'Formulario').trim() : 'Formulario';

                // Create FAB
                var fab = document.createElement('button');
                fab.className = 'mobile-fab';
                fab.setAttribute('aria-label', formTitle);
                fab.textContent = '＋';
                document.body.appendChild(fab);

                // Create form sheet
                var sheet = document.createElement('div');
                sheet.className = 'mobile-form-sheet';
                sheet.hidden = true;

                var backdrop = document.createElement('div');
                backdrop.className = 'mobile-form-sheet-backdrop';

                var panel = document.createElement('div');
                panel.className = 'mobile-form-sheet-panel';

                var handle = document.createElement('div');
                handle.className = 'mobile-form-sheet-handle';

                var content = document.createElement('div');

                panel.appendChild(handle);
                panel.appendChild(content);
                sheet.appendChild(backdrop);
                sheet.appendChild(panel);
                document.body.appendChild(sheet);

                // Move form panel content into sheet
                var formCloned = formPanel.cloneNode(true);
                formCloned.style.display = '';
                // Strip IDs to avoid collisions with hidden original (security: duplicate DOM IDs)
                formCloned.querySelectorAll('[id]').forEach(function (el) {
                    el.removeAttribute('id');
                });
                content.appendChild(formCloned);

                // Hide and disable original form panel
                formPanel.style.display = 'none';
                var originalForm = formPanel.querySelector('form');
                if (originalForm) {
                    originalForm.addEventListener('submit', function (e) { e.preventDefault(); });
                }

                // Toggle: FAB opens sheet
                fab.addEventListener('click', function () {
                    sheet.hidden = false;
                    document.body.style.overflow = 'hidden';
                    // Focus first input for convenience
                    setTimeout(function () {
                        var firstInput = sheet.querySelector('input, select, textarea');
                        if (firstInput) firstInput.focus();
                    }, 350);
                });

                // Backdrop click closes
                backdrop.addEventListener('click', function () {
                    sheet.hidden = true;
                    document.body.style.overflow = '';
                });

                // Escape key closes sheet (a11y + security: prevent focus escape)
                sheet.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape' || e.keyCode === 27) {
                        sheet.hidden = true;
                        document.body.style.overflow = '';
                        fab.focus();
                    }
                });
                sheet.setAttribute('role', 'dialog');
                sheet.setAttribute('aria-modal', 'true');
                sheet.setAttribute('aria-label', formTitle);

                // Auto-open if URL has edit/new/convert params or form title indicates editing
                var forceOpen = params.has('edit') || params.has('convert') || params.has('new');
                var titleLower = formTitle.toLowerCase();
                if (titleLower.indexOf('editar') !== -1 || titleLower.indexOf('ficha') !== -1 || titleLower.indexOf('edición') !== -1) {
                    forceOpen = true;
                }
                if (forceOpen) {
                    sheet.hidden = false;
                    document.body.style.overflow = 'hidden';
                }

                sheetsCreated++;
            });

            // If no forms detected, also handle standalone form panels
            if (sheetsCreated === 0) {
                document.querySelectorAll('.cards.two > .panel:first-child form').forEach(function (form) {
                    var formPanel = form.closest('.panel');
                    if (!formPanel) return;
                    var formTitle = 'Formulario';
                    var heading = formPanel.querySelector('h2');
                    if (heading) formTitle = heading.textContent.trim();

                    var fab = document.createElement('button');
                    fab.className = 'mobile-fab';
                    fab.setAttribute('aria-label', formTitle);
                    fab.textContent = '＋';
                    document.body.appendChild(fab);

                    var sheet = document.createElement('div');
                    sheet.className = 'mobile-form-sheet';
                    sheet.hidden = true;
                    sheet.innerHTML = '<div class="mobile-form-sheet-backdrop"></div><div class="mobile-form-sheet-panel"><div class="mobile-form-sheet-handle"></div></div>';
                    var content = sheet.querySelector('.mobile-form-sheet-panel');
                    var clonedPanel = formPanel.cloneNode(true);
                    // Strip IDs to avoid collisions with hidden original
                    clonedPanel.querySelectorAll('[id]').forEach(function (el) {
                        el.removeAttribute('id');
                    });
                    content.appendChild(clonedPanel);
                    document.body.appendChild(sheet);

                    formPanel.style.display = 'none';
                    var origForm = formPanel.querySelector('form');
                    if (origForm) {
                        origForm.addEventListener('submit', function (e) { e.preventDefault(); });
                    }

                    var bg = sheet.querySelector('.mobile-form-sheet-backdrop');
                    fab.addEventListener('click', function () {
                        sheet.hidden = false;
                        document.body.style.overflow = 'hidden';
                    });
                    bg.addEventListener('click', function () {
                        sheet.hidden = true;
                        document.body.style.overflow = '';
                    });
                    // Escape key closes
                    sheet.addEventListener('keydown', function (e) {
                        if (e.key === 'Escape' || e.keyCode === 27) {
                            sheet.hidden = true;
                            document.body.style.overflow = '';
                            fab.focus();
                        }
                    });
                    sheet.setAttribute('role', 'dialog');
                    sheet.setAttribute('aria-modal', 'true');
                });
            }
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

            if (support) support.textContent = '';

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

        setupFormSheets();
        setupVoiceCommandPanel();

        window.addEventListener('resize', function () {
            if (!window.matchMedia('(max-width: 767px)').matches) {
                closeMobilePanels();
            }
        });
    });

    // ── Comercial > Líneas: búsqueda unificada ──
    function initLineasUnifiedSearch() {
        var searchInput = document.getElementById('lineas-unified-search');
        if (!searchInput) return;
        searchInput.addEventListener('input', function () {
            var query = this.value.toLowerCase().trim();
            var tbody = document.getElementById('lineasUnifiedTableBody');
            if (!tbody) return;
            var rows = tbody.querySelectorAll('tr');
            for (var i = 0; i < rows.length; i++) {
                if (query === '') {
                    rows[i].style.display = '';
                    continue;
                }
                var text = (rows[i].textContent || '').toLowerCase();
                rows[i].style.display = text.indexOf(query) !== -1 ? '' : 'none';
            }
        });
    }

    // ── Comercial > Líneas: modal nueva/editar ──
    function openLineasModal(lineData) {
        var overlay = document.getElementById('lineasModalOverlay');
        var form = document.getElementById('lineaForm');
        var title = document.getElementById('lineaModalTitle');
        var deleteBtn = document.getElementById('btnEliminarLinea');
        var deleteId = document.querySelector('#deleteLineaForm [name="id"]');
        if (!overlay || !form) return;

        if (lineData) {
            if (title) title.textContent = 'Ficha línea';
            setModalField('id', lineData.id);
            setModalField('nombre', lineData.nombre);
            setModalField('tfono', lineData.tfono);
            setModalField('uso', lineData.uso);
            setModalField('pin', lineData.pin);
            setModalField('compania', lineData.compania);
            setModalField('waha_port', lineData.waha_port);
            setModalField('waha', lineData.waha);
            setModalField('destacamos_id', lineData.destacamos_id);
            setModalField('notas', lineData.notas);
            if (deleteBtn) deleteBtn.style.display = 'inline-block';
            if (deleteId) deleteId.value = lineData.id || '';
        } else {
            if (title) title.textContent = 'Nueva línea';
            form.reset();
            var idField = form.querySelector('[name="id"]');
            if (idField) idField.value = '';
            if (deleteBtn) deleteBtn.style.display = 'none';
            if (deleteId) deleteId.value = '';
        }
        overlay.style.display = 'flex';
        document.body.classList.add('modal-open');
    }

    function closeLineasModal() {
        var overlay = document.getElementById('lineasModalOverlay');
        if (overlay) overlay.style.display = 'none';
        document.body.classList.remove('modal-open');
    }

    function setModalField(name, value) {
        var el = document.querySelector('#lineaForm [name="' + name + '"]');
        if (el) {
            el.value = (value === undefined || value === null) ? '' : value;
        }
    }

    function initLineasModal() {
        var btnNueva = document.getElementById('btnNuevaLinea');
        if (btnNueva) {
            btnNueva.addEventListener('click', function () { openLineasModal(null); });
        }

        var btnGuardar = document.getElementById('btnGuardarLinea');
        if (btnGuardar) {
            btnGuardar.addEventListener('click', function () {
                var form = document.getElementById('lineaForm');
                if (form) form.submit();
            });
        }

        var btnCancelar = document.getElementById('btnCancelarLinea');
        if (btnCancelar) {
            btnCancelar.addEventListener('click', closeLineasModal);
        }

        var btnClose = document.getElementById('btnModalClose');
        if (btnClose) {
            btnClose.addEventListener('click', closeLineasModal);
        }

        var overlay = document.getElementById('lineasModalOverlay');
        if (overlay) {
            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) closeLineasModal();
            });
        }

        document.addEventListener('keydown', function (e) {
            var overlay = document.getElementById('lineasModalOverlay');
            if (overlay && overlay.style.display === 'flex' && e.key === 'Escape') {
                closeLineasModal();
            }
        });

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.btn-lineas-edit');
            if (!btn) return;
            e.preventDefault();
            var tr = btn.closest('tr');
            if (!tr) return;
            var raw = tr.getAttribute('data-line');
            if (!raw) return;
            var lineData;
            try { lineData = JSON.parse(raw); } catch (_) { return; }
            openLineasModal(lineData);
        });
    }

    // --- Platform photo selection: visual feedback on click ---
    function initPlatformPhotoLabels() {
        document.querySelectorAll('.platform-photo-label').forEach(function (label) {
            label.addEventListener('click', function () {
                var cb = this.querySelector('input[type="checkbox"]');
                if (!cb) return;
                // Let browser toggle the checkbox first, then update border
                var self = this;
                setTimeout(function () {
                    self.style.borderColor = cb.checked ? '#6366f1' : '#e5e7eb';
                }, 10);
            });
        });
    }

    // ====================================================================
    // COMERCIAL AGENT TABLE — Simplified interaction logic
    // ====================================================================

    var AgentTable = {
        // Which filter is active: 'all', 'pending', 'done'
        activeFilter: 'pending',

        // Toast timer
        toastTimer: null,

        init: function () {
            var self = this;
            var table = document.querySelector('.agent-table-wrap');
            if (!table) return;

            this.bindFilters();
            this.bindAttendButtons();
            this.bindDiscardButtons();
            this.bindViewButtons();
            this.bindCopyButtons();
            this.bindFullscreenButton();
            this.updateCounters();
        },

        bindFilters: function () {
            var self = this;
            var btns = document.querySelectorAll('.agent-filter-btn');
            btns.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var filter = this.getAttribute('data-filter');
                    btns.forEach(function (b) { b.classList.remove('is-active'); });
                    this.classList.add('is-active');
                    self.activeFilter = filter;
                    self.applyFilter();
                    self.updateCounters();
                });
            });
        },

        applyFilter: function () {
            var rows = document.querySelectorAll('.agent-table tbody tr.agent-data-row');
            rows.forEach(function (row) {
                var status = row.getAttribute('data-agent-status');
                if (AgentTable.activeFilter === 'all') {
                    row.style.display = '';
                } else if (AgentTable.activeFilter === 'pending' && status === 'pending') {
                    row.style.display = '';
                } else if (AgentTable.activeFilter === 'done' && status === 'done') {
                    row.style.display = '';
                } else if (AgentTable.activeFilter === 'discarded' && status === 'discarded') {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
            // Hide inline chat rows that belong to hidden rows
            document.querySelectorAll('.agent-inline-chat').forEach(function (chatRow) {
                var prevRow = chatRow.previousElementSibling;
                if (prevRow && prevRow.style.display === 'none') {
                    chatRow.style.display = 'none';
                    chatRow.classList.remove('is-open');
                }
            });
        },

        updateCounters: function () {
            var allRows = document.querySelectorAll('.agent-table tbody tr.agent-data-row');
            var visibleCount = 0;
            var pendingCount = 0;
            var doneCount = 0;
            var discardedCount = 0;

            allRows.forEach(function (row) {
                var status = row.getAttribute('data-agent-status');
                if (status === 'pending') pendingCount++;
                if (status === 'done') doneCount++;
                if (status === 'discarded') discardedCount++;
            });

            // Update counter badge
            var counterEl = document.getElementById('agentPendingCount');
            if (counterEl) {
                var prevCount = parseInt(counterEl.textContent, 10);
                // Animate number change
                if (!isNaN(prevCount) && prevCount !== pendingCount) {
                    counterEl.style.transition = 'none';
                    counterEl.style.transform = 'scale(1.25)';
                    counterEl.style.color = pendingCount > 0 ? '#22c55e' : '#94a3b8';
                    setTimeout(function () {
                        counterEl.style.transition = 'transform .3s cubic-bezier(.16,1,.3,1)';
                        counterEl.style.transform = 'scale(1)';
                    }, 50);
                }
                counterEl.textContent = pendingCount;
            }

            // Update filter badges
            var badgeAll = document.querySelector('.agent-filter-btn[data-filter="all"] .badge');
            var badgePending = document.querySelector('.agent-filter-btn[data-filter="pending"] .badge');
            var badgeDone = document.querySelector('.agent-filter-btn[data-filter="done"] .badge');
            var badgeDiscarded = document.querySelector('.agent-filter-btn[data-filter="discarded"] .badge');

            if (badgeAll) badgeAll.textContent = allRows.length;
            if (badgePending) badgePending.textContent = pendingCount;
            if (badgeDone) badgeDone.textContent = doneCount;
            if (badgeDiscarded) badgeDiscarded.textContent = discardedCount;

            // Re-apply current filter to refresh visibility
            this.applyFilter();
        },

        bindAttendButtons: function () {
            var self = this;
            document.querySelectorAll('.agent-btn-attend').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    if (this.classList.contains('is-sent')) return;

                    var threadId = this.getAttribute('data-thread-id');
                    if (!threadId) return;

                    self.markAttended(this, threadId);
                });
            });
        },

        markAttended: function (btn, threadId) {
            var self = this;

            // Instant visual feedback
            btn.classList.add('is-sent');
            btn.innerHTML = '&#10003; Hecho';
            btn.disabled = true;

            // Update the row
            var row = btn.closest('tr.agent-data-row');
            if (row) {
                row.setAttribute('data-agent-status', 'done');
                row.classList.remove('agent-row-pending', 'agent-row-hot', 'agent-row-warm');
                row.classList.add('agent-row-done');

                // Update status pill
                var statusEl = row.querySelector('.agent-status');
                if (statusEl) {
                    statusEl.innerHTML = '<span class="status-dot dot-done"></span> Atendido';
                    statusEl.className = 'agent-status is-done';
                }

                // Disable discard button
                var discardBtn = row.querySelector('.agent-btn-discard');
                if (discardBtn) {
                    discardBtn.style.display = 'none';
                }
            }

            // Send to backend
            var csrfToken = document.querySelector('input[name="csrf_token"]');
            var csrfValue = csrfToken ? csrfToken.value : '';

            var formData = new FormData();
            formData.append('action', 'comercial_set_thread_stage');
            formData.append('thread_id', threadId);
            formData.append('stage', 'qualified');
            formData.append('csrf_token', csrfValue);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            }).then(function (resp) {
                return resp.text();
            }).then(function () {
                self.showToast('Marcado como atendido', 'ok');
                self.updateCounters();
            }).catch(function () {
                self.showToast('Error al marcar. Reintenta.', 'error');
                // Revert on error
                btn.classList.remove('is-sent');
                btn.innerHTML = '&#128222; Atendido';
                btn.disabled = false;
                if (row) {
                    row.setAttribute('data-agent-status', 'pending');
                    row.classList.add('agent-row-pending');
                    row.classList.remove('agent-row-done');
                    var statusEl = row.querySelector('.agent-status');
                    if (statusEl) {
                        statusEl.innerHTML = '<span class="status-dot dot-pending"></span> Pendiente';
                        statusEl.className = 'agent-status is-pending';
                    }
                }
            });
        },

        bindDiscardButtons: function () {
            var self = this;
            document.querySelectorAll('.agent-btn-discard').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    var threadId = this.getAttribute('data-thread-id');
                    if (!threadId) return;

                    // Two-step confirmation with inline button state change
                    if (!this.classList.contains('is-confirming')) {
                        // First click: ask for confirmation
                        this.classList.add('is-confirming');
                        this.innerHTML = '&#9888; &#191;Seguro?';
                        // Auto-reset after 3 seconds
                        var btnRef = this;
                        clearTimeout(this._confirmTimer);
                        this._confirmTimer = setTimeout(function () {
                            btnRef.classList.remove('is-confirming');
                            btnRef.innerHTML = '&#128465; Descartar';
                        }, 4000);
                        return;
                    }

                    // Second click: execute
                    self.discardThread(this, threadId);
                });
            });
        },

        discardThread: function (btn, threadId) {
            var self = this;
            clearTimeout(btn._confirmTimer);
            btn.classList.remove('is-confirming');
            btn.innerHTML = '&#8987; ...';
            btn.disabled = true;

            var row = btn.closest('tr.agent-data-row');
            var csrfToken = document.querySelector('input[name="csrf_token"]');
            var csrfValue = csrfToken ? csrfToken.value : '';

            var formData = new FormData();
            formData.append('action', 'comercial_set_thread_stage');
            formData.append('thread_id', threadId);
            formData.append('stage', 'discarded');
            formData.append('csrf_token', csrfValue);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            }).then(function (resp) {
                return resp.text();
            }).then(function () {
                if (row) {
                    row.setAttribute('data-agent-status', 'discarded');
                    row.classList.remove('agent-row-pending', 'agent-row-hot', 'agent-row-warm');
                    row.classList.add('agent-row-discarded');

                    var statusEl = row.querySelector('.agent-status');
                    if (statusEl) {
                        statusEl.innerHTML = '<span class="status-dot dot-discarded"></span> Descartado';
                        statusEl.className = 'agent-status is-discarded';
                    }

                    // Hide attend button
                    var attendBtn = row.querySelector('.agent-btn-attend');
                    if (attendBtn) attendBtn.style.display = 'none';

                    btn.innerHTML = '&#128465; Descartado';
                    btn.style.opacity = '0.5';
                    btn.style.pointerEvents = 'none';
                }
                self.showToast('Descartado correctamente', 'ok');
                self.updateCounters();
            }).catch(function () {
                self.showToast('Error al descartar. Reintenta.', 'error');
                btn.innerHTML = '&#128465; Descartar';
                btn.disabled = false;
            });
        },

        bindViewButtons: function () {
            var self = this;
            document.querySelectorAll('.agent-btn-view').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    var threadId = this.getAttribute('data-thread-id');
                    if (!threadId) return;

                    var row = this.closest('tr.agent-data-row');
                    if (!row) return;

                    // Toggle: close if already open
                    var chatRow = row.nextElementSibling;
                    if (chatRow && chatRow.classList.contains('agent-inline-chat')) {
                        if (chatRow.classList.contains('is-open')) {
                            chatRow.classList.remove('is-open');
                            chatRow.style.display = 'none';
                            btn.innerHTML = '&#128065; Ver';
                            return;
                        }
                        // Re-open and refresh
                        self.loadChatContent(chatRow, threadId);
                        chatRow.classList.add('is-open');
                        chatRow.style.display = '';
                        btn.innerHTML = '&#128065; Ocultar';
                        return;
                    }

                    // Create new chat row
                    self.createChatRow(row, threadId, btn);
                });
            });
        },

        createChatRow: function (row, threadId, btn) {
            var self = this;
            var chatRow = document.createElement('tr');
            chatRow.className = 'agent-inline-chat is-open';
            chatRow.innerHTML = '<td colspan="6"><div class="agent-chat-shell">'
                + '<div class="agent-chat-head">'
                + '<strong>Ultimos mensajes</strong>'
                + '<button type="button" class="agent-chat-close">Cerrar</button>'
                + '</div>'
                + '<div class="agent-chat-bubbles">'
                + '<div class="agent-chat-loading">Cargando conversacion...</div>'
                + '</div>'
                + '</div></td>';

            row.parentNode.insertBefore(chatRow, row.nextSibling);

            // Close button
            var closeBtn = chatRow.querySelector('.agent-chat-close');
            closeBtn.addEventListener('click', function () {
                chatRow.classList.remove('is-open');
                chatRow.style.display = 'none';
                btn.innerHTML = '&#128065; Ver';
            });

            btn.innerHTML = '&#128065; Ocultar';

            // Load content
            this.loadChatContent(chatRow, threadId);
        },

        loadChatContent: function (chatRow, threadId) {
            var bubblesEl = chatRow.querySelector('.agent-chat-bubbles');
            if (!bubblesEl) return;

            bubblesEl.innerHTML = '<div class="agent-chat-loading">Cargando conversacion...</div>';

            // Obtener URL base del feed desde el data attribute del contenedor
            var tableWrap = document.querySelector('.agent-table-wrap');
            var feedBase = tableWrap ? (tableWrap.getAttribute('data-feed-url') || '') : '';
            if (!feedBase) {
                feedBase = (window.location.origin || '') + '/comercial_thread_feed.php';
            }
            var feedUrl = feedBase + '?thread_id=' + encodeURIComponent(threadId) + '&_=' + Date.now();

            fetch(feedUrl, { credentials: 'same-origin' })
                .then(function (resp) { return resp.json(); })
                .then(function (data) {
                    if (!data.ok || !data.thread) {
                        bubblesEl.innerHTML = '<div class="agent-chat-loading">No se pudo cargar la conversacion.</div>';
                        return;
                    }

                    var timelineHtml = data.thread.timeline_html || '';
                    if (!timelineHtml || timelineHtml.trim() === '') {
                        bubblesEl.innerHTML = '<div class="agent-chat-loading">Sin mensajes todavia.</div>';
                        return;
                    }

                    // Parse the server-rendered HTML into chat bubbles
                    bubblesEl.innerHTML = '';

                    // The timeline HTML from the server contains .commercial-thread-entry elements.
                    // We parse them into our simpler chat format.
                    var tempDiv = document.createElement('div');
                    tempDiv.innerHTML = timelineHtml;

                    var entries = tempDiv.querySelectorAll('.commercial-thread-entry');
                    if (entries.length === 0) {
                        bubblesEl.innerHTML = '<div class="agent-chat-loading">Sin mensajes todavia.</div>';
                        return;
                    }

                    entries.forEach(function (entry) {
                        var isInbound = entry.classList.contains('in');
                        var isOutbound = entry.classList.contains('out');
                        var isMeta = entry.classList.contains('meta');

                        if (isMeta) {
                            var metaText = (entry.querySelector('.commercial-thread-entry-meta') || {}).textContent || entry.textContent || '';
                            var metaDiv = document.createElement('div');
                            metaDiv.className = 'agent-chat-msg';
                            metaDiv.style.alignSelf = 'center';
                            metaDiv.style.background = 'rgba(255,255,255,.03)';
                            metaDiv.style.maxWidth = '100%';
                            metaDiv.style.fontSize = '12px';
                            metaDiv.style.color = 'var(--muted)';
                            metaDiv.style.textAlign = 'center';
                            metaDiv.textContent = metaText.trim();
                            bubblesEl.appendChild(metaDiv);
                            return;
                        }

                        if (isInbound || isOutbound) {
                            var bubble = entry.querySelector('.commercial-bubble');
                            var text = bubble ? bubble.textContent.trim() : entry.textContent.trim();
                            var timeEl = entry.querySelector('.commercial-thread-entry-meta') || entry.querySelector('[style*="font-size:11px"]');
                            var time = timeEl ? timeEl.textContent.trim() : '';

                            var msgDiv = document.createElement('div');
                            msgDiv.className = 'agent-chat-msg ' + (isInbound ? 'is-inbound' : 'is-outbound');
                            msgDiv.textContent = text;
                            if (time) {
                                var timeSpan = document.createElement('span');
                                timeSpan.className = 'msg-time';
                                timeSpan.textContent = time;
                                msgDiv.appendChild(timeSpan);
                            }
                            bubblesEl.appendChild(msgDiv);
                        }
                    });

                    if (bubblesEl.children.length === 0) {
                        bubblesEl.innerHTML = '<div class="agent-chat-loading">Sin mensajes todavia.</div>';
                    }
                })
                .catch(function () {
                    bubblesEl.innerHTML = '<div class="agent-chat-loading">Error al cargar. Intentalo de nuevo.</div>';
                });
        },

        bindCopyButtons: function () {
            var self = this;
            document.querySelectorAll('.agent-copy-btn').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var phone = this.getAttribute('data-phone') || '';
                    if (!phone) return;

                    // Copiar al portapapeles
                    var cleaned = phone.replace(/[^0-9+]/g, '');
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(cleaned).then(function () {
                            self._flashCopyBtn(btn);
                        }).catch(function () {
                            self._fallbackCopy(cleaned, btn);
                        });
                    } else {
                        self._fallbackCopy(cleaned, btn);
                    }
                });
            });
        },

        _flashCopyBtn: function (btn) {
            var original = btn.innerHTML;
            btn.classList.add('is-copied');
            btn.innerHTML = '✓';
            setTimeout(function () {
                btn.classList.remove('is-copied');
                btn.innerHTML = original;
            }, 1200);
        },

        _fallbackCopy: function (text, btn) {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); } catch (e) {}
            document.body.removeChild(ta);
            this._flashCopyBtn(btn);
        },

        bindFullscreenButton: function () {
            var self = this;
            var btn = document.getElementById('agentFullscreenBtn');
            if (!btn) return;

            btn.addEventListener('click', function () {
                self.openFullscreen();
            });
        },

        openFullscreen: function () {
            var panel = document.getElementById('agentTablePanel');
            if (!panel) return;

            // Recoger todo el CSS de la página
            var styles = '';
            var sheets = document.styleSheets;
            for (var i = 0; i < sheets.length; i++) {
                try {
                    var rules = sheets[i].cssRules || sheets[i].rules;
                    if (rules) {
                        for (var j = 0; j < rules.length; j++) {
                            styles += rules[j].cssText + '\n';
                        }
                    }
                } catch (e) {
                    // Cross-origin stylesheets won't be readable — skip
                }
            }

            // También incluir <style> inline
            var inlineStyles = document.querySelectorAll('style');
            inlineStyles.forEach(function (s) {
                styles += s.textContent + '\n';
            });

            // Clonar el panel y quitar el botón fullscreen del clon
            var clone = panel.cloneNode(true);
            var fsBtn = clone.querySelector('#agentFullscreenBtn');
            if (fsBtn) fsBtn.remove();

            // HTML completo para la ventana emergente
            var html = '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">'
                + '<meta name="viewport" content="width=device-width,initial-scale=1">'
                + '<title>Bandeja del Comercial</title>'
                + '<style>' + styles + '</style>'
                + '</head><body style="margin:0;padding:0;background:#0a0f1a;overflow:hidden;">'
                + '<div style="position:fixed;top:10px;right:10px;z-index:99999;">'
                + '<button onclick="window.close()" style="padding:10px 20px;border:1px solid rgba(248,113,113,.30);border-radius:8px;background:rgba(15,23,42,.95);color:#fca5a5;cursor:pointer;font-size:14px;font-weight:700;">✕ Cerrar</button>'
                + '</div>'
                + clone.outerHTML
                + '</body></html>';

            // Abrir ventana emergente a pantalla completa
            var w = window.open('', '_blank', 'width=' + screen.width + ',height=' + screen.height + ',left=0,top=0');
            if (w) {
                w.document.write(html);
                w.document.close();

                // Intentar maximizar
                try { w.moveTo(0, 0); w.resizeTo(screen.width, screen.height); } catch (e) {}

                // Copiar los event listeners básicos al nuevo window
                var self = this;
                setTimeout(function () {
                    self._bindFsWindow(w);
                }, 200);
            }
        },

        _bindFsWindow: function (w) {
            var doc = w.document;
            // Filtros
            var filterBtns = doc.querySelectorAll('.agent-filter-btn');
            filterBtns.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var filter = this.getAttribute('data-filter');
                    filterBtns.forEach(function (b) { b.classList.remove('is-active'); });
                    this.classList.add('is-active');
                    var rows = doc.querySelectorAll('.agent-table tbody tr.agent-data-row');
                    rows.forEach(function (row) {
                        var s = row.getAttribute('data-agent-status');
                        if (filter === 'all' || s === filter) row.style.display = '';
                        else row.style.display = 'none';
                    });
                });
            });
            // Copiar teléfonos
            doc.querySelectorAll('.agent-copy-btn').forEach(function (b) {
                b.addEventListener('click', function (e) {
                    e.preventDefault();
                    var phone = (this.getAttribute('data-phone') || '').replace(/[^0-9+]/g, '');
                    if (phone && navigator.clipboard) {
                        navigator.clipboard.writeText(phone).then(function () {
                            b.classList.add('is-copied');
                            b.innerHTML = '✓';
                            setTimeout(function () { b.classList.remove('is-copied'); b.innerHTML = '📋'; }, 1200);
                        });
                    }
                });
            });
        },

        showToast: function (message, type) {
            var el = document.getElementById('agentToast');
            if (!el) {
                el = document.createElement('div');
                el.id = 'agentToast';
                el.className = 'agent-toast';
                document.body.appendChild(el);
            }

            el.textContent = message;
            el.className = 'agent-toast ' + (type || 'ok') + ' is-visible';

            clearTimeout(this.toastTimer);
            this.toastTimer = setTimeout(function () {
                el.classList.remove('is-visible');
            }, 2500);
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        initLineasUnifiedSearch();
        initLineasModal();
        initPlatformPhotoLabels();
        AgentTable.init();
        scrollActiveSubtabIntoView();
        convertTablesToCards();
        setupSubtabOverflow();

        // ── Dropdown popover toggles (MOBILE-REDESIGN: replaces Más sheet) ──
        var dropIds = ['dropCtrl', 'dropNeg', 'dropCom', 'dropSis'];
        var activePop = null;

        function closeAllPops() {
            dropIds.forEach(function (id) {
                var pop = document.getElementById(id + 'Pop');
                var btn = document.getElementById(id);
                if (pop) pop.hidden = true;
                if (btn) btn.setAttribute('aria-expanded', 'false');
            });
            activePop = null;
        }

        dropIds.forEach(function (id) {
            var btn = document.getElementById(id);
            var pop = document.getElementById(id + 'Pop');
            if (!btn || !pop) return;

            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                if (activePop === id) {
                    closeAllPops();
                } else {
                    closeAllPops();
                    pop.hidden = false;
                    btn.setAttribute('aria-expanded', 'true');
                    activePop = id;
                }
            });

            // Close when clicking a link inside the popover
            pop.querySelectorAll('.mobile-nav-popover-link').forEach(function (link) {
                link.addEventListener('click', function () {
                    closeAllPops();
                });
            });
        });

        // Click outside closes any open popover
        document.addEventListener('click', function () {
            if (activePop) closeAllPops();
        });
    });
})();

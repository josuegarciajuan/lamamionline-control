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

// ═══════════════════════════════════════════════════════════════════
// GPS COPILOT — Parking Memory + Auto Stop Detection (Fase 3)
// ═══════════════════════════════════════════════════════════════════

window._GpsCopilot = {
    // ── Parking Memory ──
    parking: null, // { lat, lng, ts, label }

    _loadParking: function () {
        var raw = localStorage.getItem('jefry_parking');
        if (raw) {
            try { this.parking = JSON.parse(raw); } catch (e) { this.parking = null; }
        }
    },

    _saveParking: function () {
        if (this.parking) {
            localStorage.setItem('jefry_parking', JSON.stringify(this.parking));
        } else {
            localStorage.removeItem('jefry_parking');
        }
    },

    saveParking: function () {
        var pos = window._gpsLastPos;
        if (!pos || !pos.lat) return 'No tengo señal GPS. Intenta en unos segundos.';

        this.parking = { lat: pos.lat, lng: pos.lng, ts: Date.now(), label: 'Parking' };
        this._saveParking();
        return 'Aparcado. GPS guardado.';
    },

    recallParking: function () {
        this._loadParking();
        if (!this.parking) return 'No tengo ningún parking guardado.';

        var pos = window._gpsLastPos;
        if (!pos || !pos.lat) return 'Tu coche está aparcado, pero no tengo tu posición actual.';

        var dist = this._dist2D(pos.lat, pos.lng, this.parking.lat, this.parking.lng);
        var dir = this._bearing(pos.lat, pos.lng, this.parking.lat, this.parking.lng);

        if (dist < 15) return 'Estás justo al lado del coche.';
        if (dist < 100) return 'El coche está a ' + Math.round(dist) + ' metros al ' + dir + '.';
        return 'El coche está a ' + Math.round(dist) + ' metros al ' + dir + '. ¿Quieres que te guíe?';
    },

    openParkingRoute: function () {
        this._loadParking();
        if (!this.parking) return;
        var url = 'https://www.google.com/maps/dir/?api=1&destination=' + this.parking.lat + ',' + this.parking.lng + '&travelmode=walking';
        window.open(url, '_blank');
    },

    _autoCleanParking: function () {
        if (!this.parking) return;
        var pos = window._gpsLastPos;
        if (!pos || !pos.lat) return;
        var dist = this._dist2D(pos.lat, pos.lng, this.parking.lat, this.parking.lng);
        if (dist > 500) {
            this.parking = null;
            this._saveParking();
        }
    },

    // ── Auto Stop Detection ──
    _stopStart: null,  // { lat, lng, ts } — when the stop began
    _stopAsked: 0,     // timestamp of last stop question (anti-spam)

    onGpsTick: function () {
        var pos = window._gpsLastPos;
        var prev = window._gpsPrevPos;
        if (!pos || !prev || !prev.ts) return;

        // Auto-clean parking if we moved far
        this._autoCleanParking();

        var timeGap = pos.ts - prev.ts; // ms between this and previous position
        var distMoved = window._gpsMovedMeters || 0;

        // Detect stop: gap of 2-10 minutes + little movement
        if (timeGap > 120000 && timeGap < 600000 && distMoved < 100) {
            if (!this._stopStart) {
                this._stopStart = prev; // mark the start of the stop
            }
        }

        // Detect resume of movement after a stop
        if (this._stopStart && distMoved > 50) {
            var stopDuration = pos.ts - this._stopStart.ts;
            this._onStopResumed(stopDuration);
            this._stopStart = null;
        }
    },

    _onStopResumed: function (durationMs) {
        var now = Date.now();
        // Anti-spam: max 1 question every 30 minutes
        if (now - this._stopAsked < 1800000) return;
        // Only for stops between 2-10 minutes
        if (durationMs < 120000 || durationMs > 600000) return;

        this._stopAsked = now;
        var minutes = Math.round(durationMs / 60000);

        if (window._voiceProactive) {
            window._voiceProactive.speak(
                'Has parado ' + minutes + ' minutos. ¿Apunto algo? Di "gasolina 30 euros" o "nada".',
                { duckMusic: true }
            );
        }
    },

    // ── Helpers ──
    _dist2D: function (aLat, aLng, bLat, bLng) {
        var dy = (bLat - aLat) * 111320;
        var dx = (bLng - aLng) * (111320 * Math.cos(aLat * 0.0174533));
        return Math.sqrt(dx * dx + dy * dy);
    },

    _bearing: function (lat1, lng1, lat2, lng2) {
        var dLng = (lng2 - lng1) * Math.PI / 180;
        var y = Math.sin(dLng) * Math.cos(lat2 * Math.PI / 180);
        var x = Math.cos(lat1 * Math.PI / 180) * Math.sin(lat2 * Math.PI / 180) -
                Math.sin(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.cos(dLng);
        var brng = Math.atan2(y, x) * 180 / Math.PI;
        var dirs = ['Norte', 'Noreste', 'Este', 'Sureste', 'Sur', 'Suroeste', 'Oeste', 'Noroeste'];
        return dirs[Math.round(((brng % 360) + 360) % 360 / 45) % 8];
    },

    init: function () {
        this._loadParking();
    }
};

// Hook GpsCopilot into GPS tick (called after each GPS position)
(function () {
    var _origTick = null;
    // We hook by patching navigator.geolocation.getCurrentPosition success callback
    // Simpler: poll window._gpsLastPos every 90s for changes
    var _lastProcessedTs = 0;
    setInterval(function () {
        var pos = window._gpsLastPos;
        if (pos && pos.ts > _lastProcessedTs && window._GpsCopilot) {
            _lastProcessedTs = pos.ts;
            window._GpsCopilot.onGpsTick();
        }
    }, 30000); // check every 30s (GPS is every 90s in lite)
})();

// ═══════════════════════════════════════════════════════════════════
// DJ JEFRY — Music recommendation ML (Fase 4)
// ═══════════════════════════════════════════════════════════════════

window._DjJefry = {
    // ── Profile structure ──
    profile: { channels: {}, keywords: {}, hours: {}, moods: {}, totalPlays: 0 },
    _weight: 0, // adaptive learning rate

    STORAGE_KEY: 'jefry_music_profile',
    // Pool of predefined search queries with keywords
    QUERIES: [
        { q: 'driving music energetic mix',    kw: ['driving','energetic','electronic'] },
        { q: 'night drive chill music',       kw: ['driving','chill','night'] },
        { q: 'road trip playlist music',      kw: ['driving','alegre'] },
        { q: 'car music bass boosted',        kw: ['driving','energetic','bass'] },
        { q: 'relaxing commute music',        kw: ['driving','chill','relax'] },
        { q: 'lofi hip hop radio beats relax', kw: ['lofi','chill'] },
        { q: 'reggaeton 2026 para el coche',  kw: ['reggaeton','alegre','energetic'] },
        { q: 'electronic music driving playlist', kw: ['electronic','driving','energetic'] },
        { q: 'acoustic morning drive',        kw: ['chill','acoustic'] },
        { q: 'spanish hits coche mix',        kw: ['alegre','reggaeton'] },
        { q: 'deep house music mix',          kw: ['electronic','house','driving'] },
        { q: 'indie rock road trip',          kw: ['rock','driving','alegre'] },
        { q: 'jazz instrumental relax',       kw: ['jazz','chill','relax'] },
        { q: 'pop español 2026',              kw: ['pop','alegre'] },
        { q: 'techno car music',              kw: ['techno','energetic','driving'] },
    ],

    // Events scored
    SCORE_LIKE:        { channel: 0.25, keywords: 0.20, hour: 0.15 },
    SCORE_AUTOCOMPLETE:{ channel: 0.08, keywords: 0.05, hour: 0.03 },
    SCORE_SKIP:        { channel: -0.15, keywords: -0.08, hour: -0.05 },
    SCORE_DISLIKE:     { channel: -0.40, keywords: -0.25, hour: -0.10 },

    // ── Init ──
    init: function () {
        this._load();
        this._weight = Math.max(0.2, 1.0 - (this.profile.totalPlays / 50));
    },

    _load: function () {
        var raw = localStorage.getItem(this.STORAGE_KEY);
        if (raw) {
            try {
                var data = JSON.parse(raw);
                this.profile.channels = data.channels || {};
                this.profile.keywords = data.keywords || {};
                this.profile.hours = data.hours || {};
                this.profile.moods = data.moods || {};
                this.profile.totalPlays = data.totalPlays || 0;
            } catch (e) {
                this.profile = { channels: {}, keywords: {}, hours: {}, moods: {}, totalPlays: 0 };
            }
        }
    },

    _save: function () {
        localStorage.setItem(this.STORAGE_KEY, JSON.stringify(this.profile));
    },

    // ── Get or create channel entry ──
    _ensureChannel: function (name) {
        if (!this.profile.channels[name]) {
            this.profile.channels[name] = { score: 0, plays: 0, skips: 0, dislikes: 0, last: '' };
        }
        return this.profile.channels[name];
    },

    _ensureKeyword: function (word) {
        if (!this.profile.keywords[word]) {
            this.profile.keywords[word] = { score: 0, uses: 0 };
        }
        return this.profile.keywords[word];
    },

    _ensureHour: function (h) {
        var key = String(h);
        if (!this.profile.hours[key]) {
            this.profile.hours[key] = { score: 0, plays: 0 };
        }
        return this.profile.hours[key];
    },

    _clamp: function (v) { return Math.max(-1, Math.min(1, v)); },

    // ── Update scores (core learning algorithm) ──
    // Feedback: 'like' | 'autocomplete' | 'skip' | 'dislike'
    registerFeedback: function (feedback) {
        var w = this._weight;
        var scoring = this['SCORE_' + feedback.toUpperCase()];
        if (!scoring) return;

        var channelName = this._getCurrentChannel();
        if (channelName) {
            var ch = this._ensureChannel(channelName);
            ch.score = this._clamp(ch.score + scoring.channel * w);
            if (feedback === 'like') ch.plays = (ch.plays || 0) + 1;
            if (feedback === 'skip') ch.skips = (ch.skips || 0) + 1;
            if (feedback === 'dislike') ch.dislikes = (ch.dislikes || 0) + 1;
            ch.last = new Date().toISOString().slice(0, 10);
        }

        // Update keywords based on current query keywords
        var currentKws = this._lastQueryKws || [];
        for (var k = 0; k < currentKws.length; k++) {
            var kw = this._ensureKeyword(currentKws[k]);
            kw.score = this._clamp(kw.score + scoring.keywords * w);
            kw.uses = (kw.uses || 0) + 1;
        }

        // Update hour preference
        var hour = new Date().getHours();
        var hr = this._ensureHour(hour);
        hr.score = this._clamp(hr.score + scoring.hour * w);
        hr.plays = (hr.plays || 0) + 1;

        if (feedback === 'like' || feedback === 'autocomplete') {
            this.profile.totalPlays++;
        }
        this._weight = Math.max(0.15, 1.0 - (this.profile.totalPlays / 50));
        this._save();
    },

    _getCurrentChannel: function () {
        if (typeof YTPlayer !== 'undefined' && YTPlayer.currentVideoChannel) {
            return YTPlayer.currentVideoChannel;
        }
        return null;
    },

    // ── Query selection (epsilon-greedy) ──
    _lastQueryKws: [],

    selectQuery: function (context) {
        context = context || {};
        var self = this;
        var hour = new Date().getHours();
        var now = Date.now();

        // Score each query
        var scored = this.QUERIES.map(function (item, idx) {
            var kwScore = 0;
            var kwCount = 0;
            for (var k = 0; k < item.kw.length; k++) {
                var kw = self.profile.keywords[item.kw[k]];
                if (kw) { kwScore += kw.score; kwCount++; }
            }
            var avgKw = kwCount > 0 ? kwScore / kwCount : 0;

            // Hour preference
            var hr = self.profile.hours[String(hour)];
            var hrScore = hr ? hr.score : 0;

            // Mood match (if context has mood)
            var moodScore = 0;
            if (context.mood) {
                var moodKws = context.mood.split(',');
                for (var m = 0; m < moodKws.length; m++) {
                    var mk = self.profile.keywords[moodKws[m].trim()];
                    if (mk) moodScore += mk.score;
                }
            }

            // Exploration noise
            var noise = (Math.random() - 0.5) * 0.2;

            var total = avgKw + hrScore * 0.3 + moodScore * 0.2 + noise;
            return { idx: idx, query: item.q, keywords: item.kw, score: total };
        });

        // Sort by score descending
        scored.sort(function (a, b) { return b.score - a.score; });

        // Epsilon-greedy: 85% best, 15% random
        var pick;
        if (Math.random() < 0.15) {
            pick = scored[Math.floor(Math.random() * scored.length)];
        } else {
            pick = scored[0];
        }

        this._lastQueryKws = pick.keywords;
        return { query: pick.query, confidence: Math.round((pick.score + 1) * 50) }; // 0-100
    },

    // ── Voice command detection (frontend, no backend) ──
    handleMusicCommand: function (text) {
        if (!text) return null;
        var t = text.toLowerCase().trim();
        var norm = t.normalize('NFD').replace(/[\u0300-\u036f]/g, '');

        // Like
        if (/^(me gusta|temazo|buena( cancion| rola)?|que tem[oó]n|me encanta)$/i.test(t) ||
            /^(me gusta|temazo|buena)$/i.test(norm)) {
            return { action: 'like' };
        }

        // Dislike
        if (/^(no me gusta|quita est[oa]|para est[oa]|vaya mierda|mal[ií]sima|horrible)$/i.test(t) ||
            /^(no me gusta|quita esto|para esto)$/i.test(norm)) {
            return { action: 'dislike' };
        }

        // Skip
        if (/^(siguiente|otra|next|skip|pasa|salta)$/i.test(t) || /^siguiente$/i.test(norm)) {
            return { action: 'skip' };
        }

        // Music search commands
        if (/^pon\s.*m[uú]sica|^m[uú]sica|^reproduce|^quiero\s.*m[uú]sica/i.test(t)) {
            // Extract mood/genre if specified
            var mood = null;
            if (/alegre|animad[ao]|marcha|fiesta|movid[ao]/i.test(t)) mood = 'alegre,energetic';
            else if (/tranquil[ao]|relax|chill|suave|calmado/i.test(t)) mood = 'chill,relax,tranquilo';
            else if (/electr[oó]nica|electronic|techno|house/i.test(t)) mood = 'electronic,energetic';
            else if (/reggaeton|regueton|reguet[oó]n/i.test(t)) mood = 'reggaeton,alegre';
            else if (/pop/i.test(t)) mood = 'pop,alegre';
            else if (/rock/i.test(t)) mood = 'rock,alegre';
            else if (/lofi|lo.fi/i.test(t)) mood = 'lofi,chill';
            return { action: 'play_music', mood: mood };
        }

        // Surprise me
        if (/sorpr[eé]ndeme|algo (diferente|nuevo|random|aleatorio)/i.test(t)) {
            return { action: 'play_random' };
        }

        // Play the usual
        if (/lo mismo de siempre|lo de siempre|mi musica|mi m[uú]sica/i.test(t)) {
            return { action: 'play_best' };
        }

        // What are my tastes?
        if (/qu[eé] gustos tengo|mi perfil musical|qu[eé] m[uú]sica me gusta|mis gustos/i.test(t)) {
            return { action: 'show_profile' };
        }

        return null;
    },

    // ── Execute music command ──
    executeCommand: function (cmd) {
        var self = this;
        switch (cmd.action) {
            case 'like':
                self.registerFeedback('like');
                return '¡Apuntado! Me gusta este estilo.';

            case 'dislike':
                self.registerFeedback('dislike');
                if (typeof YTPlayer !== 'undefined' && YTPlayer.playNext) {
                    setTimeout(function () { YTPlayer.playNext(); }, 300);
                }
                return 'Quitado. No volverá a sonar.';

            case 'skip':
                self.registerFeedback('skip');
                if (typeof YTPlayer !== 'undefined' && YTPlayer.playNext) {
                    setTimeout(function () { YTPlayer.playNext(); }, 300);
                }
                return '';

            case 'play_music':
            case 'play_best':
            case 'play_random':
                return self._playMusic(cmd);

            case 'show_profile':
                return self._getProfileSummary();

            default:
                return null;
        }
    },

    _playMusic: function (cmd) {
        var self = this;
        var pick;
        if (cmd.action === 'play_random') {
            pick = self.QUERIES[Math.floor(Math.random() * self.QUERIES.length)];
            self._lastQueryKws = pick.kw;
        } else if (cmd.action === 'play_best') {
            // Use best channel
            var bestChan = null;
            var bestScore = -999;
            for (var ch in self.profile.channels) {
                if (self.profile.channels[ch].score > bestScore) {
                    bestScore = self.profile.channels[ch].score;
                    bestChan = ch;
                }
            }
            if (bestChan) {
                // Search by channel name
                var chQuery = bestChan + ' music';
                if (typeof YTPlayer !== 'undefined' && YTPlayer.searchAndPlay) {
                    YTPlayer.searchAndPlay(chQuery);
                } else {
                    self._searchYoutube(chQuery);
                }
                self._lastQueryKws = [];
                return 'Poniendo lo tuyo.';
            }
            pick = self.selectQuery({});
        } else {
            pick = self.selectQuery({ mood: cmd.mood });
        }

        if (pick && pick.query) {
            var q = pick.query;
            var conf = pick.confidence || 50;
            if (typeof YTPlayer !== 'undefined' && YTPlayer.searchAndPlay) {
                YTPlayer.searchAndPlay(q);
            } else {
                self._searchYoutube(q);
            }
            self._updateConfidenceBar(conf);
            return 'Confianza: ' + conf + '%. ¡Al turrón!';
        }
        return 'No sé qué ponerte. Di "sorpréndeme".';
    },

    _searchYoutube: function (query) {
        if (typeof YTPlayer !== 'undefined' && YTPlayer.playVideo) {
            // Fallback: use YTPlayer search
            YTPlayer.searchAndPlay(query);
        }
    },

    _getProfileSummary: function () {
        var self = this;
        var topKws = [];
        for (var k in self.profile.keywords) {
            if (self.profile.keywords[k].score > 0.1) {
                topKws.push({ kw: k, score: self.profile.keywords[k].score });
            }
        }
        topKws.sort(function (a, b) { return b.score - a.score; });
        topKws = topKws.slice(0, 3);

        var bestChan = null;
        var bestScore = -999;
        for (var ch in self.profile.channels) {
            if (self.profile.channels[ch].score > bestScore) {
                bestScore = self.profile.channels[ch].score;
                bestChan = ch;
            }
        }

        var lines = [];
        lines.push('🎵 Tus gustos musicales:');
        if (bestChan) lines.push('- Canal fav: ' + bestChan + ' (' + Math.round(bestScore * 100) + '%)');
        if (topKws.length > 0) {
            var kwsStr = topKws.map(function (k) { return k.kw + ' (' + Math.round(k.score * 100) + '%)'; }).join(', ');
            lines.push('- Géneros: ' + kwsStr);
        }
        lines.push('- Canciones escuchadas: ' + self.profile.totalPlays);

        return lines.join('\n');
    },

    _updateConfidenceBar: function (pct) {
        var bar = document.getElementById('jefryConfidenceBar');
        var label = document.getElementById('jefryConfidencePct');
        if (bar) bar.style.width = Math.min(100, Math.max(0, pct)) + '%';
        if (label) label.textContent = Math.round(pct) + '%';
    }
};

// ═══ KITT Melody Player: Coche Fantástico intro + light effects ═══
window.KittPlayer = {
    _melodyEl: null,
    _overlayEl: null,
    _active: false,

    init: function () {
        window.KittPlayer._melodyEl = document.getElementById('kittMelody');
        window.KittPlayer._overlayEl = document.getElementById('kittOverlay');
        if (window.KittPlayer._melodyEl) {
            window.KittPlayer._melodyEl.addEventListener('ended', function () {
                window.KittPlayer.stop();
            });
        }
    },

    play: function () {
        var el = window.KittPlayer._melodyEl || document.getElementById('kittMelody');
        var overlay = window.KittPlayer._overlayEl || document.getElementById('kittOverlay');
        console.log('[KITT] play() called, el=' + !!el + ' overlay=' + !!overlay);
        if (!el) { console.warn('[KITT] audio element not found'); return; }

        // Stop radio (if playing) without touching UI
        if (typeof YTPlayer !== 'undefined' && YTPlayer._stopRadio) {
            YTPlayer._stopRadio();
        }
        // Pause YouTube video (keep controls visible)
        if (typeof YTPlayer !== 'undefined' && YTPlayer.player) {
            try {
                if (typeof YTPlayer.player.pauseVideo === 'function') YTPlayer.player.pauseVideo();
            } catch (e) {}
        }

        // Apply KITT glow class to the reproductor container for button backlight effect
        var reproductor = document.getElementById('youtubeReproductor');
        if (reproductor) reproductor.classList.add('kitt-buttons-glow');

        // Start melody
        el.currentTime = 0;
        el.play().then(function () {
            console.log('[KITT] melody playing OK');
        }).catch(function (err) {
            console.warn('[KITT] melody blocked:', err);
        });

        // Activate overlay effects
        if (overlay) overlay.classList.add('kitt-active');
        window.KittPlayer._active = true;
    },

    stop: function () {
        console.log('[KITT] stop() called');
        var el = window.KittPlayer._melodyEl || document.getElementById('kittMelody');
        var overlay = window.KittPlayer._overlayEl || document.getElementById('kittOverlay');
        if (el) {
            el.pause();
            el.currentTime = 0;
        }
        if (overlay) overlay.classList.remove('kitt-active');
        // Remove button glow
        var reproductor = document.getElementById('youtubeReproductor');
        if (reproductor) reproductor.classList.remove('kitt-buttons-glow');
        window.KittPlayer._active = false;
    },

    isPlaying: function () {
        return window.KittPlayer._active;
    }
};

// Init KittPlayer on DOM ready
(function () {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { window.KittPlayer.init(); });
    } else {
        window.KittPlayer.init();
    }
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
        var mobileAvisosToggle = document.getElementById('mobileAvisosToggle');
        var avisosPanel = document.getElementById('avisosPanel');
        var isMobile = window.matchMedia('(max-width: 767px)').matches;
        var params = new URLSearchParams(window.location.search || '');

        function closeMobilePanels() {
            document.body.classList.remove('mobile-nav-open');
            document.body.classList.remove('mobile-avisos-open');
            if (mobileAvisosToggle) mobileAvisosToggle.setAttribute('aria-expanded', 'false');
            if (appBackdrop) appBackdrop.hidden = true;
        }

        function syncBackdrop() {
            if (!appBackdrop) return;
            var open = document.body.classList.contains('mobile-nav-open') || document.body.classList.contains('mobile-avisos-open');
            appBackdrop.hidden = !open;
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

        // TTS globals — shared between voice panel and reminder polling
        window._ttsEnabled = true;
        window._ttsSpeak = function () {};

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
            var modoEurekaBtn = document.getElementById('voiceModoEurekaBtn');
            var modoEurekaStatus = document.getElementById('voiceModoEurekaStatus');
            var voiceRawTranscript = document.getElementById('voiceRawTranscript');
            var ttsEnabled = window._ttsEnabled;
            var ttsToggleBtn = document.getElementById('voiceTtsToggle');
            var ttsSpeaking = false;
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
            var safetyTimer = null;
            var lastSpeechAt = 0;
            var stopReason = '';
            var lastErrorCode = '';
            var autoSubmittedCapture = false;
            var silenceWindowMs = 5000;
            var restartDelayMs = 180;
            var modoEurekaEnabled = false;

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
                if (safetyTimer) {
                    clearTimeout(safetyTimer);
                    safetyTimer = null;
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

                if (data.raw_transcript && data.raw_transcript !== data.transcript) {
                    html += '<details class="voice-raw-details"><summary>Transcripción original</summary>';
                    html += '<div class="voice-raw-text">' + escapeHtml(data.raw_transcript) + '</div>';
                    html += '</details>';
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

                // Auto-speak response via TTS if enabled
                var ttsText = data.tts_text || '';
                if (!ttsText && data.ux && data.ux.detail) ttsText = data.ux.detail;
                if (!ttsText && data.message) ttsText = data.message;
                var importance = data.tts_importance || 'normal';
                if (ttsText && data.stage !== 'error') {
                    setTimeout(function () { ttsSpeak(ttsText, importance); }, 200);
                }

                // ── Jefry Whiteboard: show visual if response includes whiteboard data ──
                if (data.whiteboard) {
                    var wbDelay = data.whiteboard.mode === 'flash' ? 200 : 800;
                    setTimeout(function () {
                        if (window._jefryWhiteboard) window._jefryWhiteboard.show(data.whiteboard);
                    }, wbDelay);
                }

                // Suggestion chips
                if (data.suggestions && data.suggestions.length) {
                    html += '<div class="voice-suggestions">';
                    data.suggestions.forEach(function (s) {
                        html += '<button type="button" class="voice-suggestion-chip" data-suggestion-action="' + escapeHtml(s.action || '') + '" title="Clic para ejecutar">💡 ' + escapeHtml(s.label || '') + '</button>';
                    });
                    html += '</div>';
                }
                // Re-render with suggestions
                if (data.suggestions && data.suggestions.length) {
                    responseBox.innerHTML = html;
                }

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

                // Suggestion chip click → execute as voice command
                responseBox.querySelectorAll('[data-suggestion-action]').forEach(function (chip) {
                    chip.addEventListener('click', function () {
                        var actionText = chip.getAttribute('data-suggestion-action') || '';
                        if (!actionText) return;
                        input.value = actionText;
                        sendVoiceCommand({
                            text: actionText,
                            source: 'manual',
                            alternatives: []
                        });
                    });
                });

                // ── Apply system_actions from backend (LLM ordered actions) ──
                if (data.system_actions && data.system_actions.length) {
                    _applySystemActions(data.system_actions);
                }
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

                // ── All commands go through LLM now (no local keyword handlers) ──
                // The LLM uses unified TOOLs (send_whatsapp, set_mode, play_music, 
                // parking, voice_control, etc.) and returns system_actions for frontend.

                var formData = new FormData();
                formData.append('action', 'voice_command');
                formData.append('voice_command_text', text);
                formData.append('voice_context_json', JSON.stringify(collectContext()));
                formData.append('voice_input_source', source);
                if (alternatives.length) formData.append('voice_alternatives_json', JSON.stringify(alternatives));
                if (pendingToken) formData.append('voice_pending_token', pendingToken);
                if (followupAction) formData.append('voice_followup_action', followupAction);
                if (followupValue) formData.append('voice_followup_value', followupValue);
                if (modoEurekaEnabled) formData.append('voice_modo_eureka', '1');
                formData.append('voice_raw_transcript', input.value || '');

                setStatus('Procesando orden…', 'processing');
                setStage('Procesando');
                sendBtn.disabled = true;
                setProcessingOverlay(true, pendingToken || followupAction
                    ? 'Resolviendo la siguiente acción dentro del CRM…'
                    : 'Procesando su orden… 🧠');

                // Build reliable fetch URL: use './index.php' to avoid path issues
                var searchParams = new URLSearchParams(window.location.search);
                searchParams.delete('action'); // clean up query params
                var fetchUrl = 'index.php' + (searchParams.toString() ? '?' + searchParams.toString() : '');
                // If on a subdirectory, use the same-base URL
                if (window.location.pathname.indexOf('/control/') !== -1 || window.location.pathname.indexOf('/index.php') !== -1) {
                    fetchUrl = window.location.pathname + (searchParams.toString() ? '?' + searchParams.toString() : '');
                }

                window._voiceDebug('sendVoiceCommand', 'fetch to ' + fetchUrl + ' text=' + text.substring(0, 80));

                fetch(fetchUrl, {
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

                    // Show floating response notification (no popup, all feedback is here)
                    var headline = (data.ux && data.ux.headline) || humanStageLabel(data.stage);
                    var detail = (data.ux && data.ux.detail) || data.message || '';
                    var isError = (data.stage === 'error');
                    var isOk = (data.stage === 'executed' || data.stage === 'interpreted');
                    var icon = isError ? '❌' : (isOk ? '✅' : '🔔');
                    showToast(icon + ' ' + headline + (detail ? ': ' + detail : ''), isError ? 'error' : (isOk ? 'ok' : ''));

                    if (data.stage === 'error') {
                        setStatus('Error en la orden.', 'error');
                    } else if (data.stage === 'needs_clarification') {
                        setStatus('Necesita aclaración.', 'processing');
                    } else if (data.stage === 'needs_confirmation') {
                        setStatus('Esperando confirmación.', 'processing');
                    } else if (data.ai && data.ai.used_fallback) {
                        setStatus('Orden procesada con parser de respaldo.', 'ok');
                    } else {
                        setStatus('Orden procesada.', 'ok');
                    }

                    if (data.stage === 'executed' && data.redirect_url && data.execution_mode !== 'readonly') {
                        setTimeout(function () {
                            window.location.href = data.redirect_url;
                        }, 700);
                    }
                }).catch(function (err) {
                    console.error('[voice] fetch_failed:', err, 'URL:', fetchUrl);
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
                setToggleListening(false);
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

            function syncModoEurekaUI() {
                if (modoEurekaBtn) {
                    if (modoEurekaEnabled) {
                        modoEurekaBtn.classList.add('active');
                        modoEurekaBtn.textContent = '💡 Eureka ON';
                    } else {
                        modoEurekaBtn.classList.remove('active');
                        modoEurekaBtn.textContent = '💡 Modo Eureka';
                    }
                }
                if (modoEurekaStatus) {
                    modoEurekaStatus.textContent = modoEurekaEnabled ? 'Modo Eureka activo: todo lo que digas será una nueva eureka.' : '';
                }
                if (panel) {
                    if (modoEurekaEnabled) {
                        panel.classList.add('modo-eureka');
                    } else {
                        panel.classList.remove('modo-eureka');
                    }
                }
                if (input) {
                    input.placeholder = modoEurekaEnabled
                        ? 'Di tu idea en voz alta… se guardará como eureka automáticamente.'
                        : 'Ejemplo: muéstrame estadísticas de esta clienta';
                }
            }

            // ═══ TTS (Text-to-Speech) ═══
            function ttsPreprocess(text) {
                return (text || '').replace(/€/g, ' euros').replace(/°C/g, ' grados').replace(/%/g, ' por ciento');
            }

            function ttsSelectBestVoice() {
                var voices = speechSynthesis.getVoices();
                // Prefer es-ES female voices
                var preferred = ['Monica', 'Helena', 'Sara', 'Paulina', 'Marisol'];
                for (var p = 0; p < preferred.length; p++) {
                    for (var v = 0; v < voices.length; v++) {
                        if (voices[v].lang.indexOf('es') === 0 && voices[v].name.indexOf(preferred[p]) !== -1) {
                            return voices[v];
                        }
                    }
                }
                // Fallback: any es-ES
                for (var i = 0; i < voices.length; i++) {
                    if (voices[i].lang.indexOf('es-ES') === 0) return voices[i];
                }
                // Last resort: any Spanish
                for (var j = 0; j < voices.length; j++) {
                    if (voices[j].lang.indexOf('es') === 0) return voices[j];
                }
                return voices[0] || null;
            }

            function ttsSpeak(text, importance) {
                if (!ttsEnabled || !text) return;
                text = ttsPreprocess(text);

                if (importance === 'high' && text.length > 80) {
                    // Server-side TTS via OpenAI for important/long messages
                    var formData = new FormData();
                    formData.append('action', 'tts');
                    formData.append('text', text);

                    fetch(window.location.pathname + window.location.search, {
                        method: 'POST', body: formData, credentials: 'same-origin',
                        headers: { 'Accept': 'audio/mpeg', 'X-Requested-With': 'XMLHttpRequest' }
                    }).then(function (res) {
                        if (!res.ok) return;
                        return res.blob();
                    }).then(function (blob) {
                        if (!blob) return;
                        var url = URL.createObjectURL(blob);
                        var audio = new Audio(url);
                        audio.onended = function () { URL.revokeObjectURL(url); ttsSpeaking = false; window._voiceOnTtsEnded(); };
                        ttsSpeaking = true;
                        audio.play().catch(function () {});
                    }).catch(function () {});
                } else {
                    // Browser TTS for short/immediate feedback
                    speechSynthesis.cancel(); // Stop any current speech
                    var utter = new SpeechSynthesisUtterance(text);
                    var voice = ttsSelectBestVoice();
                    if (voice) utter.voice = voice;
                    utter.rate = 0.93;
                    utter.pitch = 1.0;
                    utter.volume = 1.0;
                    utter.onstart = function () { ttsSpeaking = true; };
                    utter.onend = function () { ttsSpeaking = false; window._voiceOnTtsEnded(); };
                    utter.onerror = function () { ttsSpeaking = false; window._voiceOnTtsEnded(); };
                    speechSynthesis.speak(utter);
                }
            }

            // Expose TTS to global scope for reminder polling
            window._ttsSpeak = ttsSpeak;

            function speakAndListen(text, interruptWords, onInterrupt) {
                ttsSpeaking = true;
                var handled = false;
                var utter = new SpeechSynthesisUtterance(text);
                var voice = ttsSelectBestVoice();
                if (voice) utter.voice = voice;
                utter.rate = 0.93;

                utter.onstart = function () { ttsSpeaking = true; };
                utter.onend = function () {
                    ttsSpeaking = false;
                    if (!handled && typeof onInterrupt === 'function') onInterrupt(null);
                };
                utter.onerror = function () { ttsSpeaking = false; };

                speechSynthesis.speak(utter);

                // Monitor recognition for interrupt words
                var checkInterval = setInterval(function () {
                    if (handled || !ttsSpeaking) { clearInterval(checkInterval); return; }
                    var currentText = (input.value || '').toLowerCase().trim();
                    if (!currentText) return;
                    for (var w = 0; w < (interruptWords || []).length; w++) {
                        if (currentText.indexOf(interruptWords[w]) !== -1) {
                            handled = true;
                            clearInterval(checkInterval);
                            speechSynthesis.cancel();
                            ttsSpeaking = false;
                            if (typeof onInterrupt === 'function') onInterrupt(interruptWords[w]);
                            return;
                        }
                    }
                }, 300);
            }

            function syncTtsUI() {
                if (ttsToggleBtn) {
                    if (ttsEnabled) {
                        ttsToggleBtn.classList.add('active');
                        ttsToggleBtn.title = 'Voz activada - clic para silenciar';
                    } else {
                        ttsToggleBtn.classList.remove('active');
                        ttsToggleBtn.title = 'Voz desactivada - clic para activar';
                    }
                }
            }

            function startVoiceCapture() {
                // No popup — direct listening with ripple effect on mic button
                // Copilot: pause music when voice activation starts (car mode)
                window._voicePauseMusic();
                window._voiceInteractionActive = true;
                window._voiceDebug('startVoiceCapture', 'called');

                // Track user interaction for proactive phrases
                if (window._voiceProactiveChecks) {
                    window._voiceProactiveChecks.onUserInteraction();
                }

                renderResponse(null);
                currentPending = null;
                resetCaptureState(false);
                autoSendEnabled = true;
                manualStopRequested = false;

                // Check capabilities BEFORE attempting to start
                if (!RecognitionCtor) {
                    window._voiceDebug('startVoiceCapture:fail', 'No SpeechRecognition API');
                    setStatus('Reconocimiento de voz no disponible. Escribe la orden manualmente.', 'error');
                    setStage('Error de voz');
                    return;
                }

                var isLocalhost = (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1');
                if (window.location.protocol !== 'https:' && !isLocalhost) {
                    window._voiceDebug('startVoiceCapture:fail', 'Not HTTPS — protocol=' + window.location.protocol);
                    setStatus('Voz no disponible en HTTP. Accede por HTTPS o escribe la orden manualmente.', 'error');
                    setStage('Error de voz');
                    return;
                }

                window._voiceDebug('startVoiceCapture:start', 'starting recognition...');
                dictationActive = true;
                setStatus('Preparando micrófono…', 'processing');
                setStage('Preparando');
                syncRecorderButtons();
                setToggleListening(true);

                safetyTimer = setTimeout(function () {
                    if (!dictationActive || hasSpeech) return;
                    dictationActive = false;
                    clearRecognitionTimers();
                    try { recognition.stop(); } catch (e) {}
                    syncRecorderButtons();
                    setStatus('El micrófono no ha captado audio. ¿Está activado y funcionando?', 'error');
                    setStage('Error de voz');
                }, 12000);

                try {
                    recognition.start();
                    window._voiceDebug('startVoiceCapture:recognition.start', 'ok');
                } catch (err) {
                    if (safetyTimer) { clearTimeout(safetyTimer); safetyTimer = null; }
                    dictationActive = false;
                    syncRecorderButtons();
                    window._voiceDebug('startVoiceCapture:recognition.start', 'ERROR: ' + (err.message || err));
                    setStatus('No se pudo iniciar el micrófono.', 'error');
                    setStage('Error de voz');
                }
            }

            // Mic toggle buttons: direct start/stop listening (no popup)
            toggleButtons.forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    if (dictationActive || isListening) {
                        requestStopRecording('manual');
                    } else {
                        startVoiceCapture();
                    }
                });
            });

            if (modoEurekaBtn) {
                modoEurekaBtn.addEventListener('click', function () {
                    modoEurekaEnabled = !modoEurekaEnabled;
                    syncModoEurekaUI();
                });
            }

            if (ttsToggleBtn) {
                ttsToggleBtn.addEventListener('click', function () {
                    ttsEnabled = !ttsEnabled;
                    window._ttsEnabled = ttsEnabled;
                    syncTtsUI();
                    if (!ttsEnabled) {
                        speechSynthesis.cancel();
                        ttsSpeaking = false;
                    }
                });
                syncTtsUI();
            }

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

            var voiceCapable = !!RecognitionCtor && window.location.protocol === 'https:';
            var isLocalhost = (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1');
            if (isLocalhost) voiceCapable = !!RecognitionCtor;

            if (!RecognitionCtor) {
                if (support) support.textContent = 'Tu navegador no soporta reconocimiento de voz. Puedes escribir la orden manualmente.';
                if (startBtn) startBtn.disabled = true;
                setStatus('Reconocimiento de voz no disponible. Escribe la orden abajo.', 'error');
            } else if (!voiceCapable && !isLocalhost) {
                if (support) {
                    support.textContent = '⚠️ La voz requiere HTTPS para funcionar. Escribe la orden manualmente o accede desde una conexión segura.';
                    support.style.color = '#fbbf24';
                }
                setStatus('Voz no disponible en HTTP. Escribe la orden abajo.', 'error');
            }

            if (!RecognitionCtor) {
                // Recognition not available — skip init but keep UI (send/clear/close) working
                // startVoiceCapture will guard against null recognition
            } else {
            recognition = new RecognitionCtor();
            recognition.lang = 'es-ES';
            recognition.continuous = true;
            recognition.interimResults = true;
            recognition.maxAlternatives = 4;

            recognition.onstart = function () {
                recognitionRunId += 1;
                isListening = true;
                lastErrorCode = '';
                window._voiceDebug('recognition:onstart', 'runId=' + recognitionRunId);
                setStatus('Escuchando… habla ahora.', 'listening');
                setStage('Escuchando');
                syncRecorderButtons();
                setToggleListening(true);
            };

            recognition.onresult = function (event) {
                window._voiceDebug('recognition:onresult', 'results=' + event.results.length + ' resultIndex=' + event.resultIndex);
                if (safetyTimer) { clearTimeout(safetyTimer); safetyTimer = null; }
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
                // Check for "cortoycambio" keyword to immediately stop listening and process
                var fullText = sanitizeSpeechNoise(input.value || '');
                if (fullText !== '' && /cort[oa]\s*y\s*cambio/i.test(fullText)) {
                    // Remove the keyword from text
                    var cleanedText = fullText.replace(/cort[oa]\s*y\s*cambio[.!?;:,\s]*/gi, '').trim();
                    input.value = cleanedText;
                    // Force stop and send immediately
                    stopReason = 'keyword';
                    if (recognition && isListening) {
                        recognition.stop();
                    } else {
                        dictationActive = false;
                        finalizeCapture();
                    }
                    return;
                }
                if (sanitizeSpeechNoise(input.value || '') !== '') scheduleSilenceStop();
            };

            recognition.onerror = function (event) {
                isListening = false;
                lastErrorCode = event.error || '';
                window._voiceDebug('recognition:onerror', 'error=' + event.error + ' message=' + (event.message || '') + ' hasSpeech=' + hasSpeech + ' dictationActive=' + dictationActive);
                syncRecorderButtons();

                if (event.error === 'no-speech' && dictationActive && hasSpeech) {
                    setStatus('Pausa corta detectada, sigo escuchando…', 'listening');
                    setStage('Escuchando');
                    return;
                }

                dictationActive = false;
                if (safetyTimer) { clearTimeout(safetyTimer); safetyTimer = null; }

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
                window._voiceDebug('recognition:onend', 'stopReason=' + stopReason + ' dictationActive=' + dictationActive + ' hasSpeech=' + hasSpeech);
                isListening = false;
                syncRecorderButtons();
                setToggleListening(false);

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
            } // end recognition init block
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

    // ── Josue > Teléfonos: modal nueva/editar ──
    function openTelefonosModal(lineData) {
        var overlay = document.getElementById('telefonosModalOverlay');
        var form = document.getElementById('telefonoForm');
        var title = document.getElementById('telefonoModalTitle');
        var deleteBtn = document.getElementById('btnEliminarTelefono');
        var deleteId = document.querySelector('#deleteTelefonoForm [name="id"]');
        if (!overlay || !form) return;

        if (lineData) {
            if (title) title.textContent = 'Ficha teléfono';
            setTelefonoModalField('id', lineData.id);
            setTelefonoModalField('nombre', lineData.nombre);
            setTelefonoModalField('tfono', lineData.tfono);
            setTelefonoModalField('uso', lineData.uso);
            setTelefonoModalField('pin', lineData.pin);
            setTelefonoModalField('compania', lineData.compania);
            setTelefonoModalField('waha_port', lineData.waha_port);
            setTelefonoModalField('waha', lineData.waha);
            setTelefonoModalField('destacamos_id', lineData.destacamos_id);
            setTelefonoModalField('notas', lineData.notas);
            if (deleteBtn) deleteBtn.style.display = 'inline-block';
            if (deleteId) deleteId.value = lineData.id || '';
        } else {
            if (title) title.textContent = 'Nuevo teléfono';
            form.reset();
            var idField = form.querySelector('[name="id"]');
            if (idField) idField.value = '';
            if (deleteBtn) deleteBtn.style.display = 'none';
            if (deleteId) deleteId.value = '';
        }
        overlay.style.display = 'flex';
        document.body.classList.add('modal-open');
    }

    function closeTelefonosModal() {
        var overlay = document.getElementById('telefonosModalOverlay');
        if (overlay) overlay.style.display = 'none';
        document.body.classList.remove('modal-open');
    }

    function setTelefonoModalField(name, value) {
        var el = document.querySelector('#telefonoForm [name="' + name + '"]');
        if (el) {
            el.value = (value === undefined || value === null) ? '' : value;
        }
    }

    function initTelefonosModal() {
        var btnNueva = document.getElementById('btnNuevoTelefono');
        if (btnNueva) {
            btnNueva.addEventListener('click', function () { openTelefonosModal(null); });
        }

        var btnGuardar = document.getElementById('btnGuardarTelefono');
        if (btnGuardar) {
            btnGuardar.addEventListener('click', function () {
                var form = document.getElementById('telefonoForm');
                if (form) form.submit();
            });
        }

        var btnCancelar = document.getElementById('btnCancelarTelefono');
        if (btnCancelar) {
            btnCancelar.addEventListener('click', closeTelefonosModal);
        }

        var btnClose = document.getElementById('btnTelefonoModalClose');
        if (btnClose) {
            btnClose.addEventListener('click', closeTelefonosModal);
        }

        var overlay = document.getElementById('telefonosModalOverlay');
        if (overlay) {
            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) closeTelefonosModal();
            });
        }

        document.addEventListener('keydown', function (e) {
            var overlay = document.getElementById('telefonosModalOverlay');
            if (overlay && overlay.style.display === 'flex' && e.key === 'Escape') {
                closeTelefonosModal();
            }
        });

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.btn-telefonos-edit');
            if (!btn) return;
            e.preventDefault();
            var tr = btn.closest('tr');
            if (!tr) return;
            var raw = tr.getAttribute('data-telefono');
            if (!raw) return;
            var lineData;
            try { lineData = JSON.parse(raw); } catch (_) { return; }
            openTelefonosModal(lineData);
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
        initTelefonosModal();
        initPlatformPhotoLabels();
        AgentTable.init();
        scrollActiveSubtabIntoView();
        convertTablesToCards();
        setupSubtabOverflow();
        setupReminderPolling();

        // ── Dropdown popover toggles (MOBILE-REDESIGN: replaces Más sheet) ──
        var dropIds = ['dropCtrl', 'dropNeg', 'dropCom', 'dropSis', 'liteMas'];
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

        // ── Voice Lite Toggle (bottom nav: inicia escucha con 1 tap) ──
        var liteVoiceBtn = document.querySelector('[data-voice-lite-toggle]');
        if (liteVoiceBtn) {
            liteVoiceBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                var panel = document.getElementById('voiceCommandPanel');
                window._voiceDebug('lite_btn_click', 'panel=' + !!panel);

                if (!panel) return;

                // No abrir panel — escucha directa sin ventana emergente
                // Copilot: pause music on manual mic tap
                window._voicePauseMusic();

                // Auto-start recognition
                var startBtn = document.getElementById('voiceStartButton');
                window._voiceDebug('lite_btn_startBtn', 'exists=' + !!startBtn + ' disabled=' + (startBtn ? startBtn.disabled : 'n/a'));
                if (startBtn && !startBtn.disabled) {
                    setTimeout(function () { startBtn.click(); }, 60);
                }
            });

            // Track listening state: when voiceStartButton is disabled, show pulse
            var voiceStartBtn = document.getElementById('voiceStartButton');
            if (voiceStartBtn) {
                var voiceObs = new MutationObserver(function () {
                    if (voiceStartBtn.disabled) {
                        liteVoiceBtn.classList.add('is-listening');
                    } else {
                        liteVoiceBtn.classList.remove('is-listening');
                    }
                });
                voiceObs.observe(voiceStartBtn, { attributes: true, attributeFilter: ['disabled'] });
            }

            // ── Init Wake Word Copilot (lite/car mode) ──
            if (window._WakeWordCopilot && document.body.classList.contains('is-lite')) {
                window._WakeWordCopilot.init();
            }

            // ── Init Proactive Checks (Fase 1: saludo, cierre, frases) ──
            if (window._voiceProactiveChecks && document.body.classList.contains('is-lite')) {
                window._voiceProactiveChecks.init();
                // Delay slightly so page finishes rendering before greeting
                setTimeout(function () {
                    window._voiceProactiveChecks.runAll();
                }, 2000);
            }
        }

        // ── YouTube Reproductor ──────────────────────────────────────
        initYoutubePlayer();
    });
})();

/* ═══════════════════════════════════════════════════════════════════
   YOUTUBE REPRODUCTOR
   ═══════════════════════════════════════════════════════════════════ */

var YTPlayer = {
    player: null,
    currentVideoId: '',
    currentVideoTitle: '',
    currentVideoThumbnail: '',
    currentVideoChannel: '',
    currentVideoPublished: '',
    lastSearchResults: [],
    history: [],
    ytReady: false,
    ytLoadQueue: [],
    _saveTimer: null,
};

// ── Dial state for dynamic positioning ─────────────────────────────
YTPlayer._dialSource = null;    // 'radio' | 'preset' | 'playlist' | null (wander)
YTPlayer._dialFreq = null;      // stored frequency for radio
YTPlayer._dialWanderTimer = null;

// ── Dial needle animation control ─────────────────────────────────
YTPlayer._freqToLeft = function (freqMHz) {
    // Map 88–108 MHz → 7%–82% left CSS
    var clamped = Math.max(88, Math.min(108, freqMHz));
    return 7 + ((clamped - 88) / 20) * 75;
};

// Derive a deterministic 88–108 MHz value from a channel ID string.
// Multiplicative hash with prime modulo for uniform spread across the 6 dial markers.
YTPlayer._freqFromChannelId = function (id) {
    var hash = 0;
    for (var i = 0; i < id.length; i++) {
        hash = (hash * 31 + id.charCodeAt(i)) % 1000003;
    }
    var markers = [88, 92, 96, 100, 104, 108];
    return markers[hash % markers.length];
};

YTPlayer._highlightNearestFreq = function (freqMHz) {
    var markers = document.querySelectorAll('.yt-radio-dial-freq');
    if (!markers.length) return;
    var nearest = null;
    var nearestDiff = Infinity;
    for (var i = 0; i < markers.length; i++) {
        var val = parseFloat(markers[i].textContent);
        if (!isNaN(val)) {
            var diff = Math.abs(val - freqMHz);
            if (diff < nearestDiff) { nearestDiff = diff; nearest = markers[i]; }
        }
    }
    for (var j = 0; j < markers.length; j++) markers[j].classList.remove('yt-radio-dial-active');
    if (nearest) nearest.classList.add('yt-radio-dial-active');
};

YTPlayer._computeDialFreq = function () {
    if ((YTPlayer._dialSource === 'radio' || YTPlayer._dialSource === 'preset') && YTPlayer._dialFreq !== null) {
        return YTPlayer._dialFreq;
    }
    if (YTPlayer._dialSource === 'preset' || YTPlayer._dialSource === 'playlist') {
        var total = YTPlayer.lastSearchResults.length;
        if (total === 0) return 100;
        var idx = -1;
        for (var i = 0; i < total; i++) {
            if (YTPlayer.lastSearchResults[i].video_id === YTPlayer.currentVideoId) { idx = i; break; }
        }
        if (idx < 0) idx = 0;
        return 88 + (idx / Math.max(total - 1, 1)) * 20;
    }
    return 100; // fallback default
};

YTPlayer._startDialWander = function () {
    YTPlayer._stopDialWander();
    YTPlayer._dialWanderTimer = setInterval(function () {
        if (YTPlayer._dialSource !== null) return; // don't wander if source is set
        var randFreq = 88 + Math.random() * 20;
        YTPlayer._setDialMode('tuned', randFreq);
    }, 10000);
};

YTPlayer._stopDialWander = function () {
    if (YTPlayer._dialWanderTimer) {
        clearInterval(YTPlayer._dialWanderTimer);
        YTPlayer._dialWanderTimer = null;
    }
};

YTPlayer._setDialMode = function (mode, freqMHz) {
    var needle = document.querySelector('.yt-radio-dial-needle');
    var dial = document.querySelector('.yt-radio-dial');
    if (!needle) return;
    if (mode === 'tuned') {
        if (typeof freqMHz === 'number') {
            needle.style.left = YTPlayer._freqToLeft(freqMHz) + '%';
            YTPlayer._highlightNearestFreq(freqMHz);
        } else {
            needle.style.left = '48%'; // default 100 MHz
            YTPlayer._highlightNearestFreq(100);
        }
        needle.classList.remove('scanning');
        needle.classList.add('tuned');
        if (dial) { dial.classList.add('tuned-dial'); dial.classList.remove('scanning-dial'); }
    } else {
        // Transitioning to scanning: play radio static between stations
        if (!needle.classList.contains('scanning')) SfxPlayer.dialStatic();
        needle.style.left = ''; // let CSS keyframes control position
        needle.classList.remove('tuned');
        needle.classList.add('scanning');
        if (dial) { dial.classList.remove('tuned-dial'); dial.classList.add('scanning-dial'); }
        YTPlayer._stopDialWander();
    }
};

// ── Playback position save/resume ──────────────────────────────────
YTPlayer._saveCurrentTime = function () {
    if (!YTPlayer.player || typeof YTPlayer.player.getCurrentTime !== 'function') return;
    if (!YTPlayer.currentVideoId) return;
    try {
        var t = YTPlayer.player.getCurrentTime();
        if (!isNaN(t) && t >= 0) {
            localStorage.setItem('yt_time_' + YTPlayer.currentVideoId, t);
        }
    } catch (e) { /* localStorage may fail in incognito */ }
};

YTPlayer._startSavingPlayback = function () {
    YTPlayer._stopSavingPlayback();
    YTPlayer._saveTimer = setInterval(function () {
        YTPlayer._saveCurrentTime();
    }, 5000); // cada 5 segundos
};

YTPlayer._stopSavingPlayback = function () {
    if (YTPlayer._saveTimer) {
        clearInterval(YTPlayer._saveTimer);
        YTPlayer._saveTimer = null;
    }
};

// ═══ SfxPlayer v2: Retro cassette deck sound effects ═════════════════
// All sounds generated procedural via Web Audio API — 0 external assets.
// Compatible with Chrome 95+ (WebView Lite).
// v2: triangle/square waves, multi-layer, motor wobble, full volume (1.0).
var SfxPlayer = {
    _ctx: null,
    _masterGain: null,
    _masterVolume: 1.0,

    _ensureContext: function () {
        if (SfxPlayer._ctx) return true;
        try {
            SfxPlayer._ctx = new (window.AudioContext || window.webkitAudioContext)();
            SfxPlayer._masterGain = SfxPlayer._ctx.createGain();
            SfxPlayer._masterGain.gain.value = SfxPlayer._masterVolume;
            SfxPlayer._masterGain.connect(SfxPlayer._ctx.destination);
            console.log('SfxPlayer: AudioContext ready (sampleRate=' + SfxPlayer._ctx.sampleRate + 'Hz)');
        } catch (e) {
            console.warn('SfxPlayer: AudioContext not available');
            return false;
        }
        return true;
    },

    _now: function () { return SfxPlayer._ctx.currentTime; },

    // ── Low-level generators ──────────────────────────────────────────

    // Bandpass-filtered noise burst — clicks, rattles, switches
    _click: function (duration, centerFreq, q, vol) {
        if (!SfxPlayer._ensureContext()) return;
        var t = SfxPlayer._now();
        var len = Math.min(duration, 0.5);
        var bufferSize = Math.ceil(SfxPlayer._ctx.sampleRate * len);
        var buffer = SfxPlayer._ctx.createBuffer(1, bufferSize, SfxPlayer._ctx.sampleRate);
        var data = buffer.getChannelData(0);
        for (var i = 0; i < bufferSize; i++) data[i] = (Math.random() * 2 - 1);
        var src = SfxPlayer._ctx.createBufferSource();
        src.buffer = buffer;
        var filter = SfxPlayer._ctx.createBiquadFilter();
        filter.type = 'bandpass';
        filter.frequency.value = centerFreq;
        filter.Q.value = q;
        var env = SfxPlayer._ctx.createGain();
        env.gain.setValueAtTime(0, t);
        env.gain.linearRampToValueAtTime(vol, t + 0.003);
        env.gain.exponentialRampToValueAtTime(0.001, t + duration);
        src.connect(filter);
        filter.connect(env);
        env.connect(SfxPlayer._masterGain);
        src.start(t);
        src.stop(t + duration);
    },

    // Tone with envelope — triangle/square for mechanical timbre
    _tone: function (freq, duration, vol, type) {
        if (!SfxPlayer._ensureContext()) return;
        type = type || 'triangle';
        var t = SfxPlayer._now();
        var osc = SfxPlayer._ctx.createOscillator();
        osc.type = type;
        osc.frequency.setValueAtTime(freq, t);
        var env = SfxPlayer._ctx.createGain();
        env.gain.setValueAtTime(0, t);
        env.gain.linearRampToValueAtTime(vol, t + 0.005);
        env.gain.exponentialRampToValueAtTime(0.001, t + duration);
        osc.connect(env);
        env.connect(SfxPlayer._masterGain);
        osc.start(t);
        osc.stop(t + duration);
    },

    // Frequency sweep — triangle for motor spin-up/down
    _sweep: function (startFreq, endFreq, duration, vol, type) {
        if (!SfxPlayer._ensureContext()) return;
        type = type || 'triangle';
        var t = SfxPlayer._now();
        var osc = SfxPlayer._ctx.createOscillator();
        osc.type = type;
        osc.frequency.setValueAtTime(startFreq, t);
        osc.frequency.exponentialRampToValueAtTime(Math.max(endFreq, 20), t + duration);
        var env = SfxPlayer._ctx.createGain();
        env.gain.setValueAtTime(0, t);
        env.gain.linearRampToValueAtTime(vol, t + 0.015);
        env.gain.exponentialRampToValueAtTime(0.001, t + duration);
        osc.connect(env);
        env.connect(SfxPlayer._masterGain);
        osc.start(t);
        osc.stop(t + duration);
    },

    // Motor hum with wobble (frequency modulation) — continuous
    _motorWobble: function (baseFreq, wobbleDepth, vol) {
        if (!SfxPlayer._ensureContext()) return null;
        var t = SfxPlayer._now();
        var osc = SfxPlayer._ctx.createOscillator();
        osc.type = 'triangle';
        osc.frequency.setValueAtTime(baseFreq, t);
        var wobbleTimer = setInterval(function () {
            if (!osc || !osc.frequency) { clearInterval(wobbleTimer); return; }
            var w = baseFreq + (Math.random() - 0.5) * wobbleDepth * 2;
            osc.frequency.linearRampToValueAtTime(w, SfxPlayer._ctx.currentTime + 0.08);
        }, 80);
        var env = SfxPlayer._ctx.createGain();
        env.gain.setValueAtTime(0, t);
        env.gain.linearRampToValueAtTime(vol, t + 0.04);
        osc.connect(env);
        env.connect(SfxPlayer._masterGain);
        osc.start(t);
        return { osc: osc, env: env, timer: wobbleTimer };
    },
    _stopMotor: function (m) {
        if (!m || !m.osc) return;
        if (m.timer) clearInterval(m.timer);
        try { m.osc.stop(); } catch (e) {}
    },

    // ── High-level sound effects ──────────────────────────────────────

    // 1. Tape insert: slide-in whoosh + settle thunk + rattle
    tapeInsert: function () {
        SfxPlayer._tone(60, 0.20, 0.55, 'triangle');           // heavy thunk
        // Slide-in whoosh: noise lowpass 200→2000Hz
        if (SfxPlayer._ensureContext()) {
            var t = SfxPlayer._now();
            var len = 0.28;
            var bufSize = Math.ceil(SfxPlayer._ctx.sampleRate * len);
            var buf = SfxPlayer._ctx.createBuffer(1, bufSize, SfxPlayer._ctx.sampleRate);
            var d = buf.getChannelData(0);
            for (var i = 0; i < bufSize; i++) d[i] = (Math.random() * 2 - 1);
            var src = SfxPlayer._ctx.createBufferSource(); src.buffer = buf;
            var f = SfxPlayer._ctx.createBiquadFilter(); f.type = 'lowpass';
            f.frequency.setValueAtTime(200, t);
            f.frequency.exponentialRampToValueAtTime(2000, t + len);
            var env = SfxPlayer._ctx.createGain();
            env.gain.setValueAtTime(0, t);
            env.gain.linearRampToValueAtTime(0.50, t + 0.01);
            env.gain.exponentialRampToValueAtTime(0.001, t + len + 0.03);
            src.connect(f); f.connect(env); env.connect(SfxPlayer._masterGain);
            src.start(t); src.stop(t + len + 0.03);
        }
        // Settle rattle cascade
        setTimeout(function () { SfxPlayer._click(0.025, 2500, 5, 0.45); }, 200);
        setTimeout(function () { SfxPlayer._click(0.020, 3000, 4, 0.40); }, 260);
        setTimeout(function () { SfxPlayer._click(0.015, 2800, 6, 0.35); }, 310);
    },

    // 2. Tape eject: release thunk + motor spindown + eject whoosh
    tapeEject: function () {
        SfxPlayer._tone(70, 0.15, 0.55, 'triangle');           // release thunk
        SfxPlayer._sweep(200, 45, 0.45, 0.45, 'triangle');    // motor spindown
        // Eject whoosh: noise highpass sweep
        if (SfxPlayer._ensureContext()) {
            var t = SfxPlayer._now() + 0.05;
            var len = 0.25;
            var bufSize = Math.ceil(SfxPlayer._ctx.sampleRate * len);
            var buf = SfxPlayer._ctx.createBuffer(1, bufSize, SfxPlayer._ctx.sampleRate);
            var d = buf.getChannelData(0);
            for (var i = 0; i < bufSize; i++) d[i] = (Math.random() * 2 - 1);
            var src = SfxPlayer._ctx.createBufferSource(); src.buffer = buf;
            var f = SfxPlayer._ctx.createBiquadFilter(); f.type = 'highpass';
            f.frequency.setValueAtTime(1000, t);
            f.frequency.exponentialRampToValueAtTime(6000, t + len);
            var env = SfxPlayer._ctx.createGain();
            env.gain.setValueAtTime(0, t);
            env.gain.linearRampToValueAtTime(0.45, t + 0.01);
            env.gain.exponentialRampToValueAtTime(0.001, t + len);
            src.connect(f); f.connect(env); env.connect(SfxPlayer._masterGain);
            src.start(t); src.stop(t + len);
        }
        // Hollow tail
        setTimeout(function () { SfxPlayer._click(0.06, 600, 8, 0.35); }, 250);
        setTimeout(function () { SfxPlayer._click(0.04, 800, 10, 0.30); }, 350);
    },

    // 3. Play: button thunk + solenoid engage + motor hum start
    play: function () {
        SfxPlayer._tone(90, 0.10, 0.55, 'triangle');          // button thunk
        SfxPlayer._click(0.015, 4000, 6, 0.45);               // solenoid click
        SfxPlayer._sweep(60, 100, 0.30, 0.40, 'triangle');    // motor start
        setTimeout(function () { SfxPlayer._tone(3000, 0.12, 0.08, 'sine'); }, 50); // ring
    },

    // 4. Pause: sharp click with short ring
    pause: function () {
        SfxPlayer._click(0.018, 6000, 8, 0.55);               // sharp click
        SfxPlayer._click(0.010, 3500, 4, 0.35);               // secondary
        setTimeout(function () { SfxPlayer._tone(4500, 0.08, 0.06, 'sine'); }, 15); // ring
    },

    // 5. Stop: solenoid disengage + motor spindown + tape slack rattle
    stop: function () {
        SfxPlayer._click(0.015, 10000, 3, 0.55);              // disengage click
        SfxPlayer._sweep(180, 35, 0.40, 0.40, 'triangle');   // motor spindown
        setTimeout(function () { SfxPlayer._click(0.040, 400, 12, 0.35); }, 200);
        setTimeout(function () { SfxPlayer._click(0.030, 300, 15, 0.30); }, 350);
        setTimeout(function () { SfxPlayer._click(0.025, 250, 18, 0.25); }, 480);
    },

    // 6. REC: double click mechanism + lock solenoid
    rec: function () {
        SfxPlayer._click(0.020, 2000, 5, 0.60);               // first click
        setTimeout(function () {
            SfxPlayer._click(0.025, 1500, 4, 0.50);           // second click (50ms delay)
            SfxPlayer._tone(55, 0.12, 0.45, 'triangle');      // lock solenoid thump
        }, 50);
        setTimeout(function () { SfxPlayer._click(0.015, 1800, 6, 0.35); }, 150); // engagement rattle
    },

    // 7. Fast Forward: wobbling motor whine + rapid click train
    _ffInterval: null,
    _ffMotor: null,
    ffStart: function () {
        SfxPlayer.ffStop();
        SfxPlayer._ffMotor = SfxPlayer._motorWobble(350, 20, 0.30);
        SfxPlayer._ffInterval = setInterval(function () {
            SfxPlayer._click(0.012, 3500, 5, 0.40);
        }, 150);
    },
    ffStop: function () {
        if (SfxPlayer._ffInterval) { clearInterval(SfxPlayer._ffInterval); SfxPlayer._ffInterval = null; }
        SfxPlayer._stopMotor(SfxPlayer._ffMotor);
        SfxPlayer._ffMotor = null;
    },

    // 8. Rewind: lower-pitch wobbling motor + click train
    _rwInterval: null,
    _rwMotor: null,
    rwStart: function () {
        SfxPlayer.rwStop();
        SfxPlayer._rwMotor = SfxPlayer._motorWobble(300, 15, 0.30);
        SfxPlayer._rwInterval = setInterval(function () {
            SfxPlayer._click(0.012, 3500, 5, 0.40);
        }, 170);
    },
    rwStop: function () {
        if (SfxPlayer._rwInterval) { clearInterval(SfxPlayer._rwInterval); SfxPlayer._rwInterval = null; }
        SfxPlayer._stopMotor(SfxPlayer._rwMotor);
        SfxPlayer._rwMotor = null;
    },

    // 9. Button click: mechanical switch + casing resonance
    btnClick: function () {
        SfxPlayer._tone(100, 0.08, 0.50, 'triangle');         // main thump
        SfxPlayer._click(0.020, 1200, 4, 0.55);               // switch noise
        SfxPlayer._tone(3500, 0.15, 0.12, 'sine');            // casing ring
        setTimeout(function () { SfxPlayer._click(0.010, 2500, 6, 0.35); }, 40); // secondary
    },

    // 10. Knob tick: crisp potentiometer detent
    knobTick: function () {
        SfxPlayer._click(0.010, 3000, 6, 0.50);
        SfxPlayer._tone(150, 0.015, 0.18, 'triangle');
    },

    // 11. Dial static: FM inter-station noise with fade in/out
    dialStatic: function () {
        if (!SfxPlayer._ensureContext()) return;
        var t = SfxPlayer._now();
        var len = 0.35;
        var bufSize = Math.ceil(SfxPlayer._ctx.sampleRate * len);
        var buf = SfxPlayer._ctx.createBuffer(1, bufSize, SfxPlayer._ctx.sampleRate);
        var d = buf.getChannelData(0);
        for (var i = 0; i < bufSize; i++) d[i] = (Math.random() * 2 - 1);
        var src = SfxPlayer._ctx.createBufferSource(); src.buffer = buf;
        var f = SfxPlayer._ctx.createBiquadFilter(); f.type = 'bandpass';
        f.frequency.value = 4500; f.Q.value = 2;
        var env = SfxPlayer._ctx.createGain();
        env.gain.setValueAtTime(0, t);
        env.gain.linearRampToValueAtTime(0.40, t + 0.04);
        env.gain.setValueAtTime(0.40, t + len - 0.05);
        env.gain.exponentialRampToValueAtTime(0.001, t + len);
        src.connect(f); f.connect(env); env.connect(SfxPlayer._masterGain);
        src.start(t); src.stop(t + len);
    },

    // 12. Counter click: tiny reel tick
    counterClick: function () {
        SfxPlayer._click(0.008, 5000, 8, 0.35);
    },

    // 13. Menu click: electronic square-wave beep with pitch bend
    menuClick: function () {
        if (!SfxPlayer._ensureContext()) return;
        var t = SfxPlayer._now();
        var osc = SfxPlayer._ctx.createOscillator();
        osc.type = 'square';
        osc.frequency.setValueAtTime(700, t);
        osc.frequency.exponentialRampToValueAtTime(500, t + 0.06);
        var env = SfxPlayer._ctx.createGain();
        env.gain.setValueAtTime(0, t);
        env.gain.linearRampToValueAtTime(0.40, t + 0.003);
        env.gain.exponentialRampToValueAtTime(0.001, t + 0.06);
        osc.connect(env); env.connect(SfxPlayer._masterGain);
        osc.start(t); osc.stop(t + 0.06);
    },

    // 14. Sidebar: drawer open/close + latch
    sidebarOpen: function () {
        // Drawer whoosh: noise lowpass sweep 200→1500Hz
        if (SfxPlayer._ensureContext()) {
            var t = SfxPlayer._now();
            var len = 0.25;
            var bufSize = Math.ceil(SfxPlayer._ctx.sampleRate * len);
            var buf = SfxPlayer._ctx.createBuffer(1, bufSize, SfxPlayer._ctx.sampleRate);
            var d = buf.getChannelData(0);
            for (var i = 0; i < bufSize; i++) d[i] = (Math.random() * 2 - 1);
            var src = SfxPlayer._ctx.createBufferSource(); src.buffer = buf;
            var f = SfxPlayer._ctx.createBiquadFilter(); f.type = 'lowpass';
            f.frequency.setValueAtTime(200, t);
            f.frequency.exponentialRampToValueAtTime(1500, t + len);
            var env = SfxPlayer._ctx.createGain();
            env.gain.setValueAtTime(0, t);
            env.gain.linearRampToValueAtTime(0.50, t + 0.02);
            env.gain.exponentialRampToValueAtTime(0.001, t + len + 0.03);
            src.connect(f); f.connect(env); env.connect(SfxPlayer._masterGain);
            src.start(t); src.stop(t + len + 0.03);
        }
        setTimeout(function () {
            SfxPlayer._click(0.020, 800, 5, 0.55);           // latch click
            SfxPlayer._tone(60, 0.08, 0.35, 'triangle');      // latch thump
        }, 220);
    },
    sidebarClose: function () {
        SfxPlayer._click(0.080, 2000, 3, 0.45);              // reverse whoosh
        SfxPlayer._click(0.018, 700, 5, 0.50);               // latch click
        SfxPlayer._tone(55, 0.06, 0.35, 'triangle');          // thump
    },

    // 15. Microphone: pop on/off
    micOn: function () {
        SfxPlayer._click(0.012, 2500, 4, 0.45);
    },
    micOff: function () {
        SfxPlayer._click(0.010, 2500, 4, 0.40);
    },
};

function initYoutubePlayer() {
    var container = document.getElementById('youtubeReproductor');
    if (!container) return;

    // Load YouTube IFrame API
    if (!window.YT) {
        var tag = document.createElement('script');
        tag.src = 'https://www.youtube.com/iframe_api';
        var firstScriptTag = document.getElementsByTagName('script')[0];
        firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
    }

    // Auto-search if ?play= param present
    var autoSearch = container.getAttribute('data-auto-search');
    if (autoSearch) {
        YTPlayer.searchAndPlay(autoSearch);
    } else {
        YTPlayer._setDialMode('scanning');
    }

    // Search via Enter
    var searchInput = document.getElementById('youtubeSearchInput');
    if (searchInput) {
        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                e.stopPropagation();
                var query = searchInput.value.trim();
                if (document.body.classList.contains('is-lite')) {
                    requestAnimationFrame(function () {
                        searchInput.blur();
                    });
                }
                YTPlayer.search(query);
            }
        });
    }

    // Search button (desktop)
    var searchBtn = document.getElementById('youtubeSearchBtn');
    if (searchBtn) {
        searchBtn.addEventListener('click', function () {
            YTPlayer.search(searchInput.value.trim());
        });
    }

    // ── Voice search mic button ────────────────────────────────────────
    (function () {
        var micBtn = document.getElementById('youtubeVoiceSearchBtn');
        if (!micBtn || !searchInput) return;

        var SpeechRecognitionCtor = window.SpeechRecognition || window.webkitSpeechRecognition || null;
        var recognition = null;
        var mediaRecorder = null;
        var audioChunks = [];
        var isRecording = false;
        var _nativeActive = !!SpeechRecognitionCtor;   // whether native is currently the active mode
        var _nativeDisabled = false;                    // native permanently disabled after hard error

        // ── Silence detection (artificial mode) ───────────────────────
        var _silenceCheckInterval = null;
        var _silenceAudioCtx = null;
        var _silenceStream = null;
        var _silenceMaxTimer = null;

        function _clearSilenceDetection() {
            if (_silenceCheckInterval) {
                clearInterval(_silenceCheckInterval);
                _silenceCheckInterval = null;
            }
            if (_silenceMaxTimer) {
                clearTimeout(_silenceMaxTimer);
                _silenceMaxTimer = null;
            }
            if (_silenceAudioCtx) {
                try { _silenceAudioCtx.close(); } catch (ignore) {}
                _silenceAudioCtx = null;
            }
            _silenceStream = null;
        }

        function _startSilenceDetection(stream) {
            _clearSilenceDetection();
            _silenceStream = stream;
            var AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;   // No silence detection on very old browsers

            try {
                _silenceAudioCtx = new AudioCtx();
                var analyser = _silenceAudioCtx.createAnalyser();
                analyser.fftSize = 256;
                var source = _silenceAudioCtx.createMediaStreamSource(stream);
                source.connect(analyser);

                var dataArray = new Uint8Array(analyser.frequencyBinCount);
                var silenceStart = null;
                var SILENCE_THRESHOLD = 5;    // normalised 0…128, very quiet
                var SILENCE_DURATION  = 2000;  // ms of silence to auto-stop

                _silenceCheckInterval = setInterval(function () {
                    if (!_silenceAudioCtx) return;
                    analyser.getByteTimeDomainData(dataArray);

                    var sumSq = 0;
                    for (var i = 0; i < dataArray.length; i++) {
                        var v = (dataArray[i] - 128) / 128;
                        sumSq += v * v;
                    }
                    var rms = Math.sqrt(sumSq / dataArray.length);

                    if (rms * 128 < SILENCE_THRESHOLD) {
                        if (silenceStart === null) silenceStart = Date.now();
                        else if (Date.now() - silenceStart > SILENCE_DURATION) {
                            // Silence detected → auto-stop
                            _clearSilenceDetection();
                            if (mediaRecorder && mediaRecorder.state === 'recording') {
                                mediaRecorder.stop();
                                isRecording = false;
                            }
                        }
                    } else {
                        silenceStart = null;   // reset on sound
                    }
                }, 150);

                // Safety net: max 15 s recording regardless of silence
                _silenceMaxTimer = setTimeout(function () {
                    _clearSilenceDetection();
                    if (mediaRecorder && mediaRecorder.state === 'recording') {
                        mediaRecorder.stop();
                        isRecording = false;
                    }
                }, 15000);

            } catch (e) {
                console.warn('Silence detection AudioContext error; using 8 s hard timeout', e);
                // Fallback: simple 8 s hard timeout
                _silenceMaxTimer = setTimeout(function () {
                    if (mediaRecorder && mediaRecorder.state === 'recording') {
                        mediaRecorder.stop();
                        isRecording = false;
                    }
                }, 8000);
            }
        }

        // ── Visual state helpers ──
        function setMicState(state) {
            micBtn.classList.remove('voice-recording', 'voice-processing');
            if (state === 'recording') {
                micBtn.classList.add('voice-recording');
                micBtn.title = 'Grabando... pulsa para parar';
            } else if (state === 'processing') {
                micBtn.classList.add('voice-processing');
                micBtn.title = 'Transcribiendo...';
            } else {
                micBtn.title = 'Buscar por voz';
            }
        }

        // ── Execute search with transcript ──
        function searchWithTranscript(transcript) {
            if (!transcript) return;
            searchInput.value = transcript;
            YTPlayer.search(transcript);
        }

        // ── Voice recognition timeout ──────────────────────────────────
        var _voiceRecognitionTimeout = null;

        function _clearVoiceTimeout() {
            if (_voiceRecognitionTimeout) {
                clearTimeout(_voiceRecognitionTimeout);
                _voiceRecognitionTimeout = null;
            }
        }

        function _startVoiceTimeout() {
            _clearVoiceTimeout();
            _voiceRecognitionTimeout = setTimeout(function () {
                console.warn('Voice search timed out (no result), switching to artificial fallback');
                if (isRecording && recognition) {
                    try { recognition.stop(); } catch (ignore) {}
                }
                _nativeDisabled = true;
                setMicState('');
                SfxPlayer.micOff();
            }, 10000);
        }

        // ── Native SpeechRecognition ───────────────────────────────────
        if (SpeechRecognitionCtor) {
            var lastInterim = '';

            recognition = new SpeechRecognitionCtor();
            recognition.lang = 'es-ES';
            recognition.interimResults = true;
            recognition.continuous = false;
            recognition.maxAlternatives = 1;

            recognition.onstart = function () {
                isRecording = true;
                setMicState('recording');
                SfxPlayer.micOn();
                lastInterim = '';
                _startVoiceTimeout();
            };

            recognition.onresult = function (event) {
                _clearVoiceTimeout();
                var finalTranscript = '';
                var interimTranscript = '';

                for (var i = event.resultIndex; i < event.results.length; i++) {
                    var result = event.results[i];
                    if (result.isFinal) {
                        finalTranscript += result[0].transcript;
                    } else {
                        interimTranscript += result[0].transcript;
                    }
                }

                // Show interim + final in the input in real time
                var combined = finalTranscript + interimTranscript;
                if (combined) {
                    searchInput.value = combined.trim();
                }
                lastInterim = interimTranscript;
            };

            recognition.onend = function () {
                _clearVoiceTimeout();
                isRecording = false;
                setMicState('');
                SfxPlayer.micOff();
                var text = searchInput.value.trim();
                if (text) {
                    YTPlayer.search(text);
                }
            };

            recognition.onerror = function (event) {
                _clearVoiceTimeout();
                isRecording = false;
                setMicState('');
                SfxPlayer.micOff();
                console.warn('Voice search SpeechRecognition error:', event.error);

                // Only permanently disable native for hard (non-recoverable) errors.
                // Transient errors (no-speech, aborted, network) → keep native available.
                var hardErrors = ['not-allowed', 'service-not-allowed', 'audio-capture'];
                if (hardErrors.indexOf(event.error) !== -1) {
                    console.log('SpeechRecognition hard error (' + event.error + '), disabling native permanently');
                    _nativeDisabled = true;
                }
            };
        }

        // ── Artificial fallback: MediaRecorder + Whisper API ──────────
        function startArtificialRecording() {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                alert('Tu navegador no soporta grabacion de audio. Escribe la busqueda manualmente.');
                return;
            }

            navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
                audioChunks = [];

                var mimeType = 'audio/webm;codecs=opus';
                if (!MediaRecorder.isTypeSupported(mimeType)) {
                    mimeType = 'audio/webm';
                }
                if (!MediaRecorder.isTypeSupported(mimeType)) {
                    mimeType = 'audio/mp4';
                }

                mediaRecorder = new MediaRecorder(stream, { mimeType: mimeType, audioBitsPerSecond: 32000 });

                mediaRecorder.ondataavailable = function (e) {
                    if (e.data && e.data.size > 0) {
                        audioChunks.push(e.data);
                    }
                };

                mediaRecorder.onstop = function () {
                    SfxPlayer.micOff();
                    // Stop all tracks
                    stream.getTracks().forEach(function (t) { t.stop(); });
                    _clearSilenceDetection();

                    if (audioChunks.length === 0) {
                        setMicState('');
                        return;
                    }

                    setMicState('processing');

                    var audioBlob = new Blob(audioChunks, { type: mimeType });
                    var formData = new FormData();
                    formData.append('action', 'youtube_voice_search');
                    formData.append('audio', audioBlob, 'audio.webm');

                    fetch('index.php', {
                        method: 'POST',
                        body: formData,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    })
                    .then(function (resp) { return resp.json(); })
                    .then(function (json) {
                        setMicState('');
                        if (json.ok && json.transcript) {
                            searchWithTranscript(json.transcript);
                        } else {
                            alert('No se pudo transcribir el audio. Intentalo de nuevo.');
                            console.warn('Whisper error:', json.error);
                        }
                    })
                    .catch(function (err) {
                        setMicState('');
                        alert('Error al transcribir: ' + err.message);
                        console.error('Voice search fallback error:', err);
                    });
                };

                mediaRecorder.start();
                isRecording = true;
                setMicState('recording');
                SfxPlayer.micOn();

                // Start silence detection for smart auto-stop
                _startSilenceDetection(stream);

            }).catch(function (err) {
                console.error('getUserMedia error:', err);
                alert('No se pudo acceder al microfono. Verifica los permisos.');
            });
        }

        function stopArtificialRecording() {
            _clearSilenceDetection();
            if (mediaRecorder && mediaRecorder.state === 'recording') {
                mediaRecorder.stop();
                SfxPlayer.micOff();
                isRecording = false;
            }
        }

        // ── Unified click handler ──────────────────────────────────────
        var nativeClick = function () {
            if (!recognition) return;
            if (isRecording) {
                recognition.stop();
                return;
            }
            try {
                recognition.start();
            } catch (e) {
                console.warn('SpeechRecognition start failed:', e);
            }
        };

        var artificialClick = function () {
            if (isRecording) {
                stopArtificialRecording();
            } else {
                startArtificialRecording();
            }
        };

        var unifiedClick = function () {
            // If native is available and not permanently disabled, try native first
            if (_nativeActive && !_nativeDisabled && recognition) {
                nativeClick();
            } else {
                artificialClick();
            }
        };

        micBtn.addEventListener('click', unifiedClick);
    })();

    // Suggest button
    var suggestBtn = document.getElementById('youtubeSuggestBtn');
    if (suggestBtn) {
        suggestBtn.addEventListener('click', YTPlayer.loadSuggestions);
    }

    // New playlist button
    var newPlBtn = document.getElementById('youtubeNewPlaylistBtn');
    var newPlInput = document.getElementById('youtubeNewPlaylistInput');
    if (newPlBtn && newPlInput) {
        newPlBtn.addEventListener('click', function () {
            var name = newPlInput.value.trim();
            if (!name) return;
            YTPlayer.createPlaylist(name);
        });
        newPlInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                var name = newPlInput.value.trim();
                if (!name) return;
                YTPlayer.createPlaylist(name);
            }
        });
    }

    // New topic channel
    var newTopicInput = document.getElementById('youtubeNewTopicInput');
    var newTopicBtn = document.getElementById('youtubeNewTopicBtn');
    if (newTopicBtn && newTopicInput) {
        newTopicBtn.addEventListener('click', function () {
            var concept = newTopicInput.value.trim();
            if (!concept) return;
            YTPlayer.createTopicChannel(concept);
        });
        newTopicInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                var concept = newTopicInput.value.trim();
                if (!concept) return;
                YTPlayer.createTopicChannel(concept);
            }
        });
    }

    // Seed channels on load if needed
    YTPlayer.seedChannels();

    // Control buttons
    document.getElementById('youtubePlayPauseBtn').addEventListener('click', function () {
        // Si hay radio activa, toggle pause/play en la radio
        if (YTPlayer._radioActive && YTPlayer._radioAudioEl) {
            if (YTPlayer._radioAudioEl.paused) {
                YTPlayer._radioAudioEl.play().catch(function () {});
                this.innerHTML = '&#10074;&#10074;';
                YTPlayer._setDialMode('tuned', YTPlayer._dialFreq);
                SfxPlayer.play(); SfxPlayer.btnClick();
            } else {
                YTPlayer._radioAudioEl.pause();
                this.innerHTML = '&#9654;';
                YTPlayer._setDialMode('scanning');
                SfxPlayer.pause(); SfxPlayer.btnClick();
            }
            return;
        }
        if (YTPlayer.player && typeof YTPlayer.player.getPlayerState === 'function') {
            var state = YTPlayer.player.getPlayerState();
            if (state === YT.PlayerState.PLAYING || state === YT.PlayerState.BUFFERING) {
                YTPlayer.player.pauseVideo();
                SfxPlayer.pause(); SfxPlayer.btnClick();
            } else {
                YTPlayer.player.playVideo();
                SfxPlayer.play(); SfxPlayer.btnClick();
            }
        } else if (document.body.classList.contains('is-lite') && window._ytLastVideo) {
            // No hay video cargado -> reanudar el último reproducido (lite)
            var lv = window._ytLastVideo;
            YTPlayer.playVideo(lv.video_id, lv.title, lv.thumbnail, lv.channel_name, lv.published_time);
            SfxPlayer.play(); SfxPlayer.btnClick();
        }
    });
    document.getElementById('youtubePrevBtn').addEventListener('click', function () {
        YTPlayer.playPrevious();
    });
    document.getElementById('youtubeNextBtn').addEventListener('click', function () {
        YTPlayer.playNext();
    });
    document.getElementById('youtubeVolDownBtn').addEventListener('click', function () {
        if (YTPlayer.player && YTPlayer.player.setVolume) {
            var v = YTPlayer.player.getVolume();
            YTPlayer.player.setVolume(Math.max(0, v - 5));
            YTPlayer._syncSliderFromPlayer();
        }
    });
    document.getElementById('youtubeVolUpBtn').addEventListener('click', function () {
        if (YTPlayer.player && YTPlayer.player.setVolume) {
            var v = YTPlayer.player.getVolume();
            YTPlayer.player.setVolume(Math.min(100, v + 5));
            YTPlayer._syncSliderFromPlayer();
        }
    });
    // Volume slider
    var volSlider = document.getElementById('youtubeVolumeSlider');
    var volLabel = document.getElementById('youtubeVolumeLabel');
    if (volSlider) {
        // Restaurar volumen guardado
        var savedVol = localStorage.getItem('yt_volume');
        if (savedVol !== null) {
            var vol = parseInt(savedVol, 10);
            if (!isNaN(vol) && vol >= 0 && vol <= 100) {
                volSlider.value = vol;
                if (volLabel) volLabel.textContent = vol + '%';
            }
        }
        volSlider.addEventListener('input', function () {
            var v = parseInt(volSlider.value, 10);
            if (volLabel) volLabel.textContent = v + '%';
            if (YTPlayer.player && YTPlayer.player.setVolume) {
                YTPlayer.player.setVolume(v);
            }
            if (YTPlayer._radioAudioEl) {
                YTPlayer._radioAudioEl.volume = v / 100;
            }
            localStorage.setItem('yt_volume', v);
        });
    }
    // ── Audio Boost ──────────────────────────────────────────────────
    YTPlayer._initAudioBoost();
    // ── Cassette Deck init (lite mode) ──
    if (document.body.classList.contains('is-lite')) YTPlayer._initCassette();

    // ── Lite mode: system volume hint ────────────────────────────────
    YTPlayer._maybeShowLiteVolumeHint();

    // Render last played video via JS
    YTPlayer.renderLastPlayed();

    // History scroll arrows
    var histScroll = document.getElementById('youtubeHistoryScroll');
    var histLeft = document.getElementById('youtubeHistLeft');
    var histRight = document.getElementById('youtubeHistRight');
    if (histScroll && histLeft && histRight) {
        histLeft.addEventListener('click', function () { histScroll.scrollBy({ left: -250, behavior: 'auto' }); });
        histRight.addEventListener('click', function () { histScroll.scrollBy({ left: 250, behavior: 'auto' }); });
    }
}

window.onYouTubeIframeAPIReady = function () {
    YTPlayer.ytReady = true;
    // Process queued loads
    for (var i = 0; i < YTPlayer.ytLoadQueue.length; i++) {
        YTPlayer.loadPlayer(YTPlayer.ytLoadQueue[i]);
    }
    YTPlayer.ytLoadQueue = [];
};

YTPlayer.loadPlayer = function (videoId) {
    if (!videoId) return;

    // Parar el timer de guardado del video anterior
    YTPlayer._stopSavingPlayback();

    if (!YTPlayer.ytReady) {
        YTPlayer.ytLoadQueue.push(videoId);
        return;
    }

    var placeholder = document.getElementById('youtubePlayerPlaceholder');
    var container = document.getElementById('youtubePlayerContainer');
    if (placeholder) placeholder.style.display = 'none';
    if (container) container.style.display = 'block';

    if (YTPlayer.player) {
        YTPlayer.player.loadVideoById(videoId);
    } else {
        container.innerHTML = '<div id="youtubePlayerElement"></div>';
        YTPlayer.player = new YT.Player('youtubePlayerElement', {
            videoId: videoId,
            playerVars: {
                autoplay: 1,
                controls: 0,
                modestbranding: 1,
                rel: 0,
                fs: 0,
                iv_load_policy: 3,
            },
            events: {
                onReady: function () {
                    // ── Force iframe pointer-events para que no bloquee clicks (Lite) ──
                    if (document.body.classList.contains('is-lite') && container) {
                        setTimeout(function () {
                            var iframe = container.querySelector('iframe');
                            if (iframe && !document.body.classList.contains('yt-fs-video')) {
                                iframe.style.pointerEvents = 'none';
                            }
                        }, 200);
                    }

                    // Volumen: usar valor guardado o maximo (100)
                    var savedVol = localStorage.getItem('yt_volume');
                    var vol = (savedVol !== null) ? parseInt(savedVol, 10) : 100;
                    if (isNaN(vol) || vol < 0) vol = 100;
                    if (vol > 100) vol = 100;
                    YTPlayer.player.setVolume(vol);
                    YTPlayer._syncSliderFromPlayer();

                    // ── Resume playback position ──────────────────────────
                    try {
                        var savedTime = parseFloat(localStorage.getItem('yt_time_' + videoId));
                        if (!isNaN(savedTime) && savedTime > 3) {
                            YTPlayer.player.seekTo(savedTime, true);
                        }
                    } catch (e) { /* ignore */ }
                    // Arrancar guardado periódico de la posición
                    YTPlayer._startSavingPlayback();

                    // Update play/pause icon to pause (autoplay starts)
                    var ppBtn = document.getElementById('youtubePlayPauseBtn');
                    if (ppBtn) ppBtn.innerHTML = '&#10074;&#10074;';
                },
                onStateChange: function (e) {
                    var ppBtn = document.getElementById('youtubePlayPauseBtn');
                    if (e.data === YT.PlayerState.PLAYING) {
                        if (ppBtn) ppBtn.innerHTML = '&#10074;&#10074;';
                        // Recompute dial on resume
                        if (YTPlayer._dialSource === 'preset' || YTPlayer._dialSource === 'playlist') {
                            YTPlayer._setDialMode('tuned', YTPlayer._computeDialFreq());
                        } else if (YTPlayer._dialSource !== 'radio') {
                            YTPlayer._startDialWander();
                        }
                    } else if (e.data === YT.PlayerState.PAUSED) {
                        if (ppBtn) ppBtn.innerHTML = '&#9654;';
                        YTPlayer._setDialMode('scanning');
                    } else if (e.data === YT.PlayerState.ENDED) {
                        if (ppBtn) ppBtn.innerHTML = '&#9654;';
                        YTPlayer._setDialMode('scanning');
                        // DJ Jefry: autocomplete feedback (song finished without skip)
                        if (window._DjJefry) {
                            window._DjJefry.registerFeedback('autocomplete');
                        }
                        // Al terminar el video, borrar posicion guardada y parar timer
                        YTPlayer._stopSavingPlayback();
                        try {
                            localStorage.removeItem('yt_time_' + YTPlayer.currentVideoId);
                        } catch (e) { /* ignore */ }
                        YTPlayer.playNext();
                    }
                }
            }
        });
    }

    var controls = document.getElementById('youtubeControls');
    if (controls) controls.style.display = 'flex';
    // Show DJ Jefry buttons/bar via CSS (yt-video-active on body)
    document.body.classList.add('yt-video-active');
    // In lite mode, disable iframe clicks so video doesn't pause on tap
    // (transport buttons handle play/pause; tap enters fullscreen instead)
    if (document.body.classList.contains('is-lite')) {
        var iframe = document.querySelector('#youtubePlayerContainer iframe');
        if (iframe) iframe.style.pointerEvents = 'none';
    }
};

YTPlayer.playVideo = function (videoId, title, thumbnail, channelName, publishedTime) {
    // Si habia radio activa, detenerla
    if (YTPlayer._radioActive) {
        YTPlayer._stopRadio();
    }

    YTPlayer.currentVideoId = videoId;
    YTPlayer.currentVideoTitle = title;
    YTPlayer.currentVideoThumbnail = thumbnail;
    YTPlayer.currentVideoChannel = channelName;
    YTPlayer.currentVideoPublished = publishedTime || '';

    YTPlayer.loadPlayer(videoId);

    // Si el Boost estaba activo, reiniciarlo para el nuevo video
    var boostCheckbox = document.getElementById('youtubeBoostCheckbox');
    if (boostCheckbox && boostCheckbox.checked) {
        YTPlayer._disableAudioBoost(true); // keepCheckbox
        // Esperar a que el player este listo para re-activar boost
        setTimeout(function () {
            if (boostCheckbox.checked) {
                YTPlayer._enableAudioBoost();
            }
        }, 1500);
    }

    var nowPlaying = document.getElementById('youtubeNowPlaying');
    var nowPlayingTitle = document.getElementById('youtubeNowPlayingTitle');
    if (nowPlaying) { nowPlaying.style.display = 'block'; nowPlaying.classList.remove('yt-radio-idle'); }
    if (nowPlayingTitle) {
        var npText = '';
        if (publishedTime) {
            var pt = publishedTime.replace(/^hace\s+/i, '');
            npText += pt + ' - ';
        }
        npText += title;
        if (channelName) npText += ' — ' + channelName;
        nowPlayingTitle.textContent = npText;
        // Lite marquee: deslizar texto si no cabe en el display
        if (document.body.classList.contains('is-lite')) {
            nowPlayingTitle.classList.remove('marquee');
            void nowPlayingTitle.offsetWidth; // force reflow
            var overflow = nowPlayingTitle.scrollWidth - nowPlayingTitle.clientWidth;
            if (overflow > 0) {
                nowPlayingTitle.classList.add('marquee');
                // Cancelar animación anterior
                if (nowPlayingTitle._marqueeTimer) clearInterval(nowPlayingTitle._marqueeTimer);
                nowPlayingTitle.style.transform = 'translateX(0px)';
                var pos = 0;
                var dir = -1;
                var speed = 0.5;        // px cada 50ms = ~10px/s
                var wait = 70;          // ~3.5s quieto al inicio
                var pause = 120;        // ~6s de pausa al llegar al extremo
                var gap = 50;           // ms entre ticks
                var timer = setInterval(function() {
                    if (--wait <= 0) {
                        pos += dir * speed;
                        if (pos <= -overflow) { pos = -overflow; dir = 1; wait = pause; }
                        else if (pos >= 0)   { pos = 0;          dir = -1; wait = pause; }
                        nowPlayingTitle.style.transform = 'translateX(' + pos + 'px)';
                    }
                }, gap);
                nowPlayingTitle._marqueeTimer = timer;
            }
        }
    }
    // Set dial position based on source
    if (YTPlayer._dialSource === 'preset' || YTPlayer._dialSource === 'playlist') {
        YTPlayer._setDialMode('tuned', YTPlayer._computeDialFreq());
    } else if (YTPlayer._dialSource !== 'radio') {
        // Search / suggestions / history → wander mode
        var initFreq = 88 + Math.random() * 20;
        YTPlayer._setDialMode('tuned', initFreq);
        YTPlayer._startDialWander();
    }
    // (radio sets dial itself via playRadioStation)

    // Log to history
    YTPlayer.logHistory(videoId, title, thumbnail, channelName, publishedTime);

    // Move to front of local history and re-render
    YTPlayer.updateLocalHistory(videoId, title, thumbnail, channelName, publishedTime);

    // Lite: cerrar sidebar al elegir un video
    if (document.body.classList.contains('is-lite')) {
        var sidebar = document.getElementById('ytRadioSidebar');
        var overlay = document.getElementById('ytRadioSidebarOverlay');
        if (sidebar) sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('visible');
        // Load tape animation
        YTPlayer._animateTapeLoad();
        // Reset tape counter + time
        YTPlayer._tapeCount = 0;
        var counter = document.getElementById('ytTapeCounter');
        var timeEl = document.getElementById('ytTapeTime');
        if (counter) counter.textContent = '000';
        if (timeEl) timeEl.textContent = '--:--';
        YTPlayer._startTapeCounter();
        // Stereo indicator
        var stereo = document.getElementById('ytStereoIndicator');
        if (stereo) stereo.classList.add('on');
    }
};

YTPlayer.logHistory = function (videoId, title, thumbnail, channelName, publishedTime) {
    var payload = {
        video_id: videoId,
        title: title,
        thumbnail: thumbnail,
        channel_name: channelName,
    };
    if (publishedTime) payload.published_time = publishedTime;
    _youtubePost('youtube_log_history', payload);
};

YTPlayer.updateLocalHistory = function (videoId, title, thumbnail, channelName, publishedTime) {
    var history = window._ytHistory || [];
    history = history.filter(function (v) { return v.video_id !== videoId; });
    history.unshift({
        video_id: videoId,
        title: title,
        thumbnail: thumbnail,
        channel_name: channelName,
        published_time: publishedTime || '',
    });
    if (history.length > 20) history = history.slice(0, 20);
    window._ytHistory = history;
    YTPlayer.renderLastPlayed();
};

YTPlayer.search = function (query, forceAutoPlay) {
    if (!query) return;

    var spinner = document.getElementById('youtubeSearchSpinner');
    var btn = document.getElementById('youtubeSearchBtn');
    if (spinner) spinner.style.display = 'inline-block';
    if (btn) btn.disabled = true;

    _youtubePost('youtube_search', { query: query }, function (data) {
        if (spinner) spinner.style.display = 'none';
        if (btn) btn.disabled = false;

        if (data.ok && data.results && data.results.length > 0) {
            YTPlayer.lastSearchResults = data.results;
            YTPlayer.renderResults(data.results, 'youtubeResultGrid');

            var isLite = document.body.classList.contains('is-lite');
            if (isLite && !forceAutoPlay) {
                // Modo Lite: mostrar resultados en sidebar sin auto-play
                YTPlayer._liteOpenSidebar();
            } else {
                // Desktop / forceAutoPlay: auto-play first result
                var first = data.results[0];
                YTPlayer.playVideo(first.video_id, first.title, first.thumbnail, first.channel_name, first.published_time);
            }
        }
    });
};

YTPlayer.searchAndPlay = function (query) {
    var input = document.getElementById('youtubeSearchInput');
    if (input) input.value = query;
    YTPlayer.search(query, true);
};

// ── Lite: open sidebar to show search results ───────────────────────
YTPlayer._liteOpenSidebar = function () {
    var sidebar = document.getElementById('ytRadioSidebar');
    if (!sidebar) return;
    var overlay = document.getElementById('ytRadioSidebarOverlay');
    if (!overlay && document.body.classList.contains('is-lite')) {
        overlay = document.createElement('div');
        overlay.id = 'ytRadioSidebarOverlay';
        overlay.className = 'yt-radio-sidebar-overlay';
        overlay.addEventListener('click', function () {
            sidebar.classList.remove('open');
            var p = document.getElementById('presintoniasPanel');
            if (p) p.classList.remove('open');
            var r = document.getElementById('radiosPanel');
            if (r) r.classList.remove('open');
            overlay.classList.remove('visible');
        });
        var reproductor = document.querySelector('.youtube-reproductor');
        if (reproductor) reproductor.appendChild(overlay);
        else document.body.appendChild(overlay);
    }
    sidebar.classList.add('open');
    if (overlay) overlay.classList.add('visible');
    SfxPlayer.sidebarOpen();
};

YTPlayer.playNext = function () {
    if (YTPlayer.lastSearchResults.length > 0) {
        var currentIdx = -1;
        for (var i = 0; i < YTPlayer.lastSearchResults.length; i++) {
            if (YTPlayer.lastSearchResults[i].video_id === YTPlayer.currentVideoId) {
                currentIdx = i;
                break;
            }
        }
        var nextIdx = currentIdx + 1;
        if (nextIdx < YTPlayer.lastSearchResults.length) {
            var next = YTPlayer.lastSearchResults[nextIdx];
            YTPlayer.playVideo(next.video_id, next.title, next.thumbnail, next.channel_name, next.published_time);
            return;
        }
    }
    // Loop: replay first
    if (YTPlayer.lastSearchResults.length > 0) {
        var first = YTPlayer.lastSearchResults[0];
        YTPlayer.playVideo(first.video_id, first.title, first.thumbnail, first.channel_name, first.published_time);
    }
};

YTPlayer.playPrevious = function () {
    if (YTPlayer.lastSearchResults.length > 0) {
        var currentIdx = -1;
        for (var i = 0; i < YTPlayer.lastSearchResults.length; i++) {
            if (YTPlayer.lastSearchResults[i].video_id === YTPlayer.currentVideoId) {
                currentIdx = i;
                break;
            }
        }
        var prevIdx = currentIdx - 1;
        if (prevIdx >= 0) {
            var prev = YTPlayer.lastSearchResults[prevIdx];
            YTPlayer.playVideo(prev.video_id, prev.title, prev.thumbnail, prev.channel_name, prev.published_time);
        }
    }
};

YTPlayer.renderResults = function (results, targetId) {
    var grid = document.getElementById(targetId);
    if (!grid) return;
    grid.innerHTML = '';

    for (var i = 0; i < results.length; i++) {
        var v = results[i];
        var card = document.createElement('div');
        card.className = 'youtube-video-card';
        card.setAttribute('data-video-id', v.video_id || '');
        card.addEventListener('click', (function (video) {
            return function () {
                YTPlayer.playVideo(video.video_id, video.title, video.thumbnail, video.channel_name, video.published_time);
            };
        })(v));
        card.innerHTML = _youtubeBuildCardHtml(v);
        grid.appendChild(card);
    }
};

function _youtubeBuildCardHtml(v) {
    var html = '';
    if (v.thumbnail) {
        html += '<div class="youtube-video-thumb"><img src="' + _youtubeEscapeAttr(v.thumbnail) + '" alt="" loading="lazy">';
        if (v.length_text) {
            html += '<span class="youtube-video-duration">' + _youtubeEscapeHtml(v.length_text) + '</span>';
        }
        html += '</div>';
    }
    html += '<div class="youtube-video-info">';
    html += '<div class="youtube-video-title">' + _youtubeEscapeHtml(v.title) + '</div>';
    if (v.channel_name) {
        html += '<div class="youtube-video-channel">' + _youtubeEscapeHtml(v.channel_name) + '</div>';
    }
    if (v.view_count) {
        html += '<div class="youtube-video-views">' + _youtubeEscapeHtml(v.view_count) + '</div>';
    }
    html += '</div>';
    html += '<div class="youtube-video-actions" onclick="event.stopPropagation()">';
    html += '<button type="button" class="youtube-mic-btn" title="Añadir a lista" onclick="YTPlayer.showAddToPlaylist(\'' + _youtubeEscapeAttr(v.video_id) + '\', \'' + _youtubeEscapeAttr(v.title) + '\', \'' + _youtubeEscapeAttr(v.thumbnail || '') + '\', \'' + _youtubeEscapeAttr(v.channel_name || '') + '\')">+</button>';
    html += '</div>';
    return html;
}

YTPlayer.loadSuggestions = function () {
    var btn = document.getElementById('youtubeSuggestBtn');
    var grid = document.getElementById('youtubeSuggestGrid');
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Generando sugerencias...';
    }
    if (grid) grid.innerHTML = '';

    _youtubePost('youtube_suggest', {}, function (data) {
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Generar sugerencias';
        }
        if (data.ok && data.suggestions && data.suggestions.length > 0) {
            var allVideos = [];
            var html = '';
            for (var i = 0; i < data.suggestions.length; i++) {
                var section = data.suggestions[i];
                if (!section.results || section.results.length === 0) continue;

                html += '<div class="youtube-section-title">' + _youtubeEscapeHtml(section.term) + '</div>';
                html += '<div class="youtube-result-grid" style="margin-bottom:16px">';
                for (var j = 0; j < section.results.length; j++) {
                    var v = section.results[j];
                    allVideos.push(v);
                    html += _youtubeBuildCardHtml(v);
                }
                html += '</div>';
            }
            if (grid) grid.innerHTML = html;
            YTPlayer.lastSearchResults = allVideos;
        } else {
            if (grid) grid.innerHTML = '<div class="youtube-empty">No se han podido cargar sugerencias en este momento. Inténtalo de nuevo en unos segundos.</div>';
        }
    });
};

// ── Playlists ──────────────────────────────────────────────────────

YTPlayer.createPlaylist = function (name) {
    _youtubePost('youtube_save_playlist', { name: name }, function (data) {
        if (data.ok) {
            var input = document.getElementById('youtubeNewPlaylistInput');
            if (input) input.value = '';
            YTPlayer.refreshPlaylists(data.playlists);
        }
    });
};

YTPlayer.deletePlaylist = function (id) {
    if (!confirm('Eliminar esta lista?')) return;
    _youtubePost('youtube_delete_playlist', { id: id }, function (data) {
        if (data.ok) {
            YTPlayer.refreshPlaylists(data.playlists);
        }
    });
};

YTPlayer.showAddToPlaylist = function (videoId, title, thumbnail, channelName) {
    var playlists = window._ytPlaylists || [];

    // Remove existing modal if any
    var existing = document.querySelector('.youtube-add-modal');
    if (existing) existing.remove();

    var modal = document.createElement('div');
    modal.className = 'youtube-add-modal';

    // Case A: No video playing
    if (!videoId) {
        modal.innerHTML = '<div class="youtube-add-modal-box">' +
            '<h3>Sin video</h3>' +
            '<p style="font-size:13px;color:var(--muted);margin-bottom:10px">Reproduce un video primero para añadirlo a una lista.</p>' +
            '<button type="button" class="youtube-search-btn" style="width:100%" onclick="this.closest(\'.youtube-add-modal\').remove()">Cerrar</button>' +
            '</div>';
        document.body.appendChild(modal);
        modal.addEventListener('click', function (e) { if (e.target === modal) modal.remove(); });
        return;
    }

    // Build clickable playlist list
    var listHtml = '';
    for (var i = 0; i < playlists.length; i++) {
        var pl = playlists[i];
        var count = pl.videos ? pl.videos.length : 0;
        listHtml += '<div class="youtube-add-modal-pl-item" data-pl-id="' + _youtubeEscapeAttr(pl.id) + '">' +
            '<span class="youtube-add-modal-pl-name">' + _youtubeEscapeHtml(pl.name) + '</span>' +
            '<span class="youtube-add-modal-pl-count">' + count + ' videos</span>' +
            '</div>';
    }
    if (listHtml === '') {
        listHtml = '<div class="youtube-empty">No tienes listas todavía.</div>';
    }

    modal.innerHTML = '<div class="youtube-add-modal-box">' +
        '<h3>Añadir a lista</h3>' +
        '<p class="youtube-add-modal-video-title">' + _youtubeEscapeHtml(title) + '</p>' +
        '<div class="youtube-add-modal-pl-list">' + listHtml + '</div>' +
        '<button type="button" class="youtube-add-modal-new-btn" id="ytNewPlToggle">+ Nueva lista</button>' +
        '<div id="ytNewPlInlineWrap" style="display:none;margin-top:8px">' +
            '<input type="text" id="ytNewPlInline" class="youtube-search-input" placeholder="Nombre de la lista..." style="width:100%;margin-bottom:6px">' +
            '<button type="button" class="youtube-search-btn" id="ytNewPlInlineBtn">Crear y añadir</button>' +
        '</div>' +
        '<button type="button" class="youtube-add-modal-cancel" onclick="this.closest(\'.youtube-add-modal\').remove()">Cancelar</button>' +
        '</div>';

    document.body.appendChild(modal);
    modal.addEventListener('click', function (e) { if (e.target === modal) modal.remove(); });

    // Bind: clicking a playlist item adds video immediately
    var plItems = modal.querySelectorAll('.youtube-add-modal-pl-item');
    for (var j = 0; j < plItems.length; j++) {
        plItems[j].addEventListener('click', function () {
            var plId = this.getAttribute('data-pl-id');
            _youtubePost('youtube_add_to_playlist', {
                playlist_id: plId,
                video_id: videoId,
                title: title,
                thumbnail: thumbnail,
                channel_name: channelName,
            }, function (data) {
                if (data.ok) {
                    window._ytPlaylists = data.playlists;
                    YTPlayer.refreshPlaylists(data.playlists);
                    _youtubeToast('Añadido a la lista!');
                }
            });
            modal.remove();
        });
    }

    // Handle inline create + add
    var inlineBtn = document.getElementById('ytNewPlInlineBtn');
    var inlineInput = document.getElementById('ytNewPlInline');
    var toggleBtn = document.getElementById('ytNewPlToggle');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function () {
            document.getElementById('ytNewPlInlineWrap').style.display = 'block';
            this.style.display = 'none';
            document.getElementById('ytNewPlInline').focus();
        });
    }
    if (inlineBtn && inlineInput) {
        inlineBtn.addEventListener('click', function () {
            var name = inlineInput.value.trim();
            if (!name) return;
            _youtubePost('youtube_save_playlist', { name: name }, function (data) {
                if (data.ok) {
                    var newPlId = data.playlists[data.playlists.length - 1].id;
                    _youtubePost('youtube_add_to_playlist', {
                        playlist_id: newPlId, video_id: videoId, title: title, thumbnail: thumbnail, channel_name: channelName
                    }, function (data2) {
                        if (data2.ok) {
                            window._ytPlaylists = data2.playlists;
                            YTPlayer.refreshPlaylists(data2.playlists);
                            _youtubeToast('Lista creada y video añadido!');
                        }
                    });
                }
            });
            modal.remove();
        });
    }
};

// Helper for onclick: add currently playing video to playlist
YTPlayer.addCurrentToPlaylist = function () {
    SfxPlayer.rec(); SfxPlayer.btnClick();
    try {
        if (YTPlayer.currentVideoId) {
            YTPlayer.showAddToPlaylist(YTPlayer.currentVideoId, YTPlayer.currentVideoTitle, YTPlayer.currentVideoThumbnail, YTPlayer.currentVideoChannel);
        } else {
            YTPlayer.showAddToPlaylist(null, null, null, null);
        }
    } catch (e) {
        _youtubeToast('Error al abrir listas: ' + e.message);
    }
};

YTPlayer.refreshPlaylists = function (playlists) {
    var list = document.getElementById('youtubePlaylistList');
    if (!list) return;
    window._ytPlaylists = playlists;

    if (!playlists || playlists.length === 0) {
        list.innerHTML = '<div class="youtube-empty">No hay listas. Crea una para guardar videos.</div>';
        return;
    }

    var html = '';
    for (var i = 0; i < playlists.length; i++) {
        var pl = playlists[i];
        var count = pl.videos ? pl.videos.length : 0;
        html += '<div class="youtube-playlist-item" data-playlist-id="' + _youtubeEscapeAttr(pl.id) + '">';
        html += '<span class="youtube-playlist-name" onclick="YTPlayer.openPlaylistDetail(\'' + _youtubeEscapeAttr(pl.id) + '\')" style="cursor:pointer" title="Ver lista completa">' + _youtubeEscapeHtml(pl.name) + ' <small>(' + count + ')</small></span>';
        html += '<div class="youtube-playlist-actions">';
        html += '<button type="button" class="youtube-mic-btn" onclick="YTPlayer.openPlaylistDetail(\'' + _youtubeEscapeAttr(pl.id) + '\')" title="Ver y gestionar">📋</button>';
        html += '<button type="button" class="youtube-mic-btn" onclick="YTPlayer.playPlaylist(\'' + _youtubeEscapeAttr(pl.id) + '\')" title="Reproducir">&#9654;</button>';
        html += '<button type="button" class="youtube-mic-btn youtube-delete-btn" onclick="YTPlayer.deletePlaylist(\'' + _youtubeEscapeAttr(pl.id) + '\')" title="Eliminar">&times;</button>';
        html += '</div>';
        html += '</div>';
    }
    list.innerHTML = html;
};

YTPlayer.renderLastPlayed = function () {
    var history = window._ytHistory;
    if (!history || history.length === 0) return;
    YTPlayer.renderResults(history.slice(0, 10), 'youtubeLastPlayed');
};

YTPlayer.playPlaylist = function (id, shuffle) {
    var playlists = window._ytPlaylists || [];
    var playlist = null;
    for (var i = 0; i < playlists.length; i++) {
        if (playlists[i].id === id) {
            playlist = playlists[i];
            break;
        }
    }
    if (!playlist || !playlist.videos || playlist.videos.length === 0) {
        alert('La lista esta vacia.');
        return;
    }
    var videos = shuffle ? _shuffleArray(playlist.videos.slice()) : playlist.videos.slice();
    YTPlayer.lastSearchResults = videos;
    YTPlayer.renderResults(videos, 'youtubeResultGrid');
    YTPlayer._dialSource = 'playlist';
    YTPlayer._stopDialWander();
    var first = videos[0];
    YTPlayer.playVideo(first.video_id, first.title, first.thumbnail || '', first.channel_name || '', first.published_time || '');
    var input = document.getElementById('youtubeSearchInput');
    if (input) input.value = playlist.name;
};

YTPlayer.openPlaylistDetail = function (id) {
    var playlists = window._ytPlaylists || [];
    var playlist = null;
    var plIdx = -1;
    for (var i = 0; i < playlists.length; i++) {
        if (playlists[i].id === id) { playlist = playlists[i]; plIdx = i; break; }
    }
    if (!playlist) return;

    // Remove existing modal
    var existing = document.querySelector('.youtube-pl-detail-modal');
    if (existing) existing.remove();

    var modal = document.createElement('div');
    modal.className = 'youtube-pl-detail-modal';
    modal.innerHTML = '<div class="youtube-pl-detail-box">' +
        '<div class="youtube-pl-detail-header">' +
        '<h3>' + _youtubeEscapeHtml(playlist.name) + ' <small style="color:var(--muted);font-weight:400;font-size:13px">(' + (playlist.videos ? playlist.videos.length : 0) + ' videos)</small></h3>' +
        '<button class="youtube-pl-close" onclick="this.closest(\'.youtube-pl-detail-modal\').remove()">&times;</button>' +
        '</div>' +
        '<div class="youtube-pl-detail-actions">' +
        '<button class="youtube-ctrl-btn" onclick="YTPlayer.playPlaylist(\'' + _youtubeEscapeAttr(id) + '\', false);this.closest(\'.youtube-pl-detail-modal\').remove()">&#9654; En orden</button>' +
        '<button class="youtube-ctrl-btn" onclick="YTPlayer.playPlaylist(\'' + _youtubeEscapeAttr(id) + '\', true);this.closest(\'.youtube-pl-detail-modal\').remove()">🔀 Aleatorio</button>' +
        '</div>' +
        '<div class="youtube-pl-detail-list" id="ytPlDetailList"></div>' +
        '</div>';

    document.body.appendChild(modal);
    modal.addEventListener('click', function (e) { if (e.target === modal) modal.remove(); });

    // Vincular este modal a la reproduccion secuencial
    YTPlayer._currentPlaylistId = id;
    if (playlist.videos && playlist.videos.length > 0) {
        YTPlayer.lastSearchResults = playlist.videos.slice();
    }

    YTPlayer._renderPlDetailItems(id, playlist);
};

YTPlayer._playFromDetailModal = function (videoId, title, thumbnail, channel, published) {
    // Asegurar que lastSearchResults apunta a la playlist activa para playNext()
    var plId = YTPlayer._currentPlaylistId;
    var playlists = window._ytPlaylists || [];
    for (var i = 0; i < playlists.length; i++) {
        if (playlists[i].id === plId && playlists[i].videos) {
            YTPlayer.lastSearchResults = playlists[i].videos.slice();
            break;
        }
    }
    YTPlayer.playVideo(videoId, title, thumbnail, channel, published);
    // Re-renderizar items para marcar el video activo
    setTimeout(function () {
        YTPlayer._highlightActiveInDetail();
    }, 500);
};

YTPlayer._highlightActiveInDetail = function () {
    var items = document.querySelectorAll('.youtube-pl-detail-item');
    for (var i = 0; i < items.length; i++) {
        var vid = items[i].getAttribute('data-video-id');
        if (vid === YTPlayer.currentVideoId) {
            items[i].classList.add('youtube-pl-playing');
        } else {
            items[i].classList.remove('youtube-pl-playing');
        }
    }
};

// Hook into onStateChange ENDED to update modal highlight on auto-play
if (!YTPlayer._playNextPatched) {
    YTPlayer._playNextPatched = true;
    YTPlayer._originalPlayNext = YTPlayer.playNext;
    YTPlayer.playNext = function () {
        YTPlayer._originalPlayNext();
        setTimeout(function () {
            YTPlayer._highlightActiveInDetail();
        }, 500);
    };
}

YTPlayer._renderPlDetailItems = function (plId, playlist) {
    var list = document.getElementById('ytPlDetailList');
    if (!list) return;
    var videos = playlist.videos || [];
    if (videos.length === 0) {
        list.innerHTML = '<div class="youtube-empty">La lista esta vacia.</div>';
        return;
    }
    var html = '';
    for (var i = 0; i < videos.length; i++) {
        var v = videos[i];
        html += '<div class="youtube-pl-detail-item" data-video-id="' + _youtubeEscapeAttr(v.video_id) + '" onclick="YTPlayer._playFromDetailModal(\'' + _youtubeEscapeAttr(v.video_id) + '\', \'' + _youtubeEscapeAttr(v.title || '') + '\', \'' + _youtubeEscapeAttr(v.thumbnail || '') + '\', \'' + _youtubeEscapeAttr(v.channel_name || '') + '\', \'' + _youtubeEscapeAttr(v.published_time || '') + '\')">';
        if (v.thumbnail) html += '<img src="' + _youtubeEscapeAttr(v.thumbnail) + '" class="youtube-pl-detail-thumb" alt="">';
        html += '<div class="youtube-pl-detail-info">';
        html += '<div class="youtube-pl-detail-title">' + _youtubeEscapeHtml(v.title) + '</div>';
        if (v.channel_name) html += '<div class="youtube-pl-detail-channel">' + _youtubeEscapeHtml(v.channel_name) + '</div>';
        html += '</div>';
        html += '<div class="youtube-pl-detail-order">';
        html += '<button title="Subir" onclick="event.stopPropagation();YTPlayer.reorderPlaylistMove(\'' + _youtubeEscapeAttr(plId) + '\',' + i + ',-1)">▲</button>';
        html += '<button title="Bajar" onclick="event.stopPropagation();YTPlayer.reorderPlaylistMove(\'' + _youtubeEscapeAttr(plId) + '\',' + i + ',1)">▼</button>';
        html += '</div>';
        html += '<button class="youtube-pl-detail-remove" title="Quitar de la lista" onclick="event.stopPropagation();YTPlayer.removeFromPlaylist(\'' + _youtubeEscapeAttr(plId) + '\',\'' + _youtubeEscapeAttr(v.video_id) + '\')">&times;</button>';
        html += '</div>';
    }
    list.innerHTML = html;
};

YTPlayer.removeFromPlaylist = function (plId, videoId) {
    _youtubePost('youtube_remove_from_playlist', { playlist_id: plId, video_id: videoId }, function (data) {
        if (data.ok) {
            window._ytPlaylists = data.playlists;
            YTPlayer.refreshPlaylists(data.playlists);
            // Refresh detail modal
            var playlist = null;
            for (var i = 0; i < data.playlists.length; i++) {
                if (data.playlists[i].id === plId) { playlist = data.playlists[i]; break; }
            }
            if (playlist) {
                YTPlayer._renderPlDetailItems(plId, playlist);
                // Update header count
                var header = document.querySelector('.youtube-pl-detail-header h3 small');
                if (header) header.textContent = '(' + (playlist.videos ? playlist.videos.length : 0) + ' videos)';
            }
        }
    });
};

YTPlayer.reorderPlaylistMove = function (plId, currentIdx, direction) {
    var playlists = window._ytPlaylists || [];
    var playlist = null;
    for (var i = 0; i < playlists.length; i++) {
        if (playlists[i].id === plId) { playlist = playlists[i]; break; }
    }
    if (!playlist || !playlist.videos) return;

    var videos = playlist.videos;
    var newIdx = currentIdx + direction;
    if (newIdx < 0 || newIdx >= videos.length) return;

    // Swap
    var temp = videos[currentIdx];
    videos[currentIdx] = videos[newIdx];
    videos[newIdx] = temp;
    playlist.videos = videos;

    // Save to backend
    _youtubePost('youtube_reorder_playlist', {
        playlist_id: plId,
        video_ids: videos.map(function (v) { return v.video_id; })
    }, function (data) {
        if (data.ok) {
            window._ytPlaylists = data.playlists;
            YTPlayer.refreshPlaylists(data.playlists);
            YTPlayer._renderPlDetailItems(plId, playlist);
        }
    });
};

function _shuffleArray(arr) {
    for (var i = arr.length - 1; i > 0; i--) {
        var j = Math.floor(Math.random() * (i + 1));
        var temp = arr[i]; arr[i] = arr[j]; arr[j] = temp;
    }
    return arr;
}

// ── Topic Channels ──────────────────────────────────────────────────

YTPlayer.seedChannels = function () {
    _youtubePost('youtube_seed_channels', {}, function (data) {
        if (data.ok) {
            // Build all channels (predefined + ai suggested)
            var allChannels = data.channels || [];
            if (data.ai_suggested && data.ai_suggested.length > 0) {
                allChannels = allChannels.concat(data.ai_suggested);
            }
            window._ytChannels = allChannels;
            YTPlayer.renderChannelTags(allChannels);
        }
    });
};

YTPlayer.loadTopicChannel = function (channelId) {
    _youtubePost('youtube_topic_channel_videos', { id: channelId }, function (data) {
        if (data.ok && data.videos && data.videos.length > 0) {
            YTPlayer.lastSearchResults = data.videos;
            YTPlayer.renderResults(data.videos, 'youtubeResultGrid');
            YTPlayer._dialSource = 'preset';
            YTPlayer._dialFreq = YTPlayer._freqFromChannelId(channelId);
            YTPlayer._stopDialWander();
            var first = data.videos[0];
            YTPlayer.playVideo(first.video_id, first.title, first.thumbnail, first.channel_name || '', first.published_time || '');
            var input = document.getElementById('youtubeSearchInput');
            if (input) input.value = data.channel_name || data.query || '';
        }
    });
};

YTPlayer.createTopicChannel = function (concept) {
    var input = document.getElementById('youtubeNewTopicInput');
    var btn = document.getElementById('youtubeNewTopicBtn');
    if (input) input.value = '';
    if (btn) { btn.disabled = true; btn.textContent = 'Creando...'; }

    _youtubePost('youtube_create_topic_channel', { concept: concept }, function (data) {
        if (btn) { btn.disabled = false; btn.textContent = 'Crear canal'; }

        if (data.ok) {
            // Mostrar el toast con la query generada por la IA
            _youtubeToast('Canal "' + concept + '" creado!');
            if (data.query_used) {
                _youtubeToast('IA optimizo a: ' + data.query_used, 3500);
            }

            // Actualizar lista de canales
            window._ytChannels = data.channels;
            YTPlayer.renderChannelTags(data.channels);

            // Cargar los videos del nuevo canal
            if (data.videos && data.videos.length > 0) {
                YTPlayer.lastSearchResults = data.videos;
                YTPlayer.renderResults(data.videos, 'youtubeResultGrid');
                YTPlayer._dialSource = 'preset';
                YTPlayer._dialFreq = YTPlayer._freqFromChannelId(concept);
                YTPlayer._stopDialWander();
                var first = data.videos[0];
                YTPlayer.playVideo(first.video_id, first.title, first.thumbnail, first.channel_name || '', first.published_time || '');
            }
        }
    });
};

YTPlayer.deleteTopicChannel = function (channelId) {
    if (!confirm('Eliminar este canal tematico?')) return;
    _youtubePost('youtube_delete_topic_channel', { id: channelId }, function (data) {
        if (data.ok) {
            window._ytChannels = data.channels;
            YTPlayer.renderChannelTags(data.channels);
        }
    });
};

YTPlayer.renderChannelTags = function (channels) {
    var grid = document.getElementById('youtubeChannelGrid');
    if (!grid) return;
    window._ytChannels = channels;

    if (!channels || channels.length === 0) {
        grid.innerHTML = '<div class="youtube-channel-tag yt-radio-channel-tag youtube-channel-seed" onclick="YTPlayer.seedChannels()"><span class="youtube-channel-icon">✨</span><span>Cargar canales</span></div>';
        return;
    }

    var html = '';
    for (var i = 0; i < channels.length; i++) {
        var ch = channels[i];
        var icon = ch.icon || '📺';
        var name = _youtubeEscapeHtml(ch.name || '');
        var chId = _youtubeEscapeAttr(ch.id || '');
        var type = ch.type || '';

        html += '<div class="youtube-channel-tag yt-radio-channel-tag';
        if (type === 'ai_suggested') html += ' youtube-channel-ai';
        html += '" data-channel-id="' + chId + '" onclick="YTPlayer.loadTopicChannel(\'' + chId + '\')">';
        html += '<span class="youtube-channel-icon">' + icon + '</span>';
        html += '<span>' + name + '</span>';
        if (type === 'custom' || type === 'ai_suggested') {
            html += '<button type="button" class="youtube-channel-del" onclick="event.stopPropagation();YTPlayer.deleteTopicChannel(\'' + chId + '\')" title="Eliminar">&times;</button>';
        }
        html += '</div>';
    }
    grid.innerHTML = html;
};

// ── Audio Boost: Web Audio API amplification ────────────────────────

/**
 * Inicializa el sistema de Audio Boost.
 * Hace health check al proxy, y configura los listeners del toggle + slider de boost.
 */
YTPlayer._initAudioBoost = function () {
    var boostCheckbox = document.getElementById('youtubeBoostCheckbox');
    var boostSlider = document.getElementById('youtubeBoostSlider');
    var boostValue = document.getElementById('youtubeBoostValue');
    var boostStatus = document.getElementById('youtubeBoostStatus');
    var boostToggle = document.getElementById('youtubeBoostToggle');

    if (!boostCheckbox) return;

    // Health check al proxy de audio (cacheado 30 min en backend)
    _youtubePost('youtube_audio_health', {}, function (data) {
        if (data.ok && data.proxy_working) {
            if (boostToggle) boostToggle.style.opacity = '1';
            if (boostStatus) {
                boostStatus.textContent = '✓ Disponible';
                boostStatus.style.color = '#4ade80';
            }
        } else {
            // Proxy caido: deshabilitar boost
            if (boostToggle) {
                boostToggle.style.opacity = '0.5';
                boostToggle.style.textDecoration = 'line-through';
                boostToggle.title = 'Audio Boost no disponible. Fallo detectado — el administrador sera notificado.';
            }
            if (boostCheckbox) boostCheckbox.disabled = true;
            if (boostStatus) {
                boostStatus.textContent = '✗ No disponible';
                boostStatus.style.color = '#f87171';
            }
        }
    });

    boostCheckbox.addEventListener('change', function () {
        if (boostCheckbox.checked) {
            // ── AudioContext + GainNode en el gesture actual ──
            if (!YTPlayer._boostAudioCtx) {
                var AudioCtx = window.AudioContext || window.webkitAudioContext;
                YTPlayer._boostAudioCtx = new AudioCtx();
            }
            if (YTPlayer._boostAudioCtx.state === 'suspended') {
                YTPlayer._boostAudioCtx.resume();
            }

            // GainNode (se crea aquí si no existe)
            if (!YTPlayer._boostGainNode) {
                YTPlayer._boostGainNode = YTPlayer._boostAudioCtx.createGain();
                YTPlayer._boostGainNode.gain.value = 1.5;
                YTPlayer._boostGainNode.connect(YTPlayer._boostAudioCtx.destination);
            }

            // ── Crear Audio element + MediaElementSourceNode AQUI (user gesture) ──
            // El navegador hace progressive download → sin esperas ni cortes.
            YTPlayer._boostStartStream();

            YTPlayer._enableAudioBoost();
        } else {
            YTPlayer._disableAudioBoost();
        }
    });

    if (boostSlider) {
        boostSlider.addEventListener('input', function () {
            var g = parseInt(boostSlider.value, 10);
            if (boostValue) boostValue.textContent = g + '%';
            localStorage.setItem('yt_boost_gain', g);
            if (YTPlayer._boostGainNode) {
                YTPlayer._boostGainNode.gain.value = g / 100;
            }
        });
        // Restaurar ganancia guardada
        var savedGain = localStorage.getItem('yt_boost_gain');
        if (savedGain !== null) {
            var gain = parseInt(savedGain, 10);
            if (!isNaN(gain) && gain >= 50 && gain <= 300) {
                boostSlider.value = gain;
                if (boostValue) boostValue.textContent = gain + '%';
            }
        }
    }
};

/**
 * Crea el Audio element y lo conecta al grafo de Web Audio.
 * DEBE llamarse durante un user gesture (checkbox change)
 * para que el play() posterior no sea bloqueado por autoplay.
 */
YTPlayer._boostStartStream = function () {
    if (!YTPlayer.currentVideoId || !YTPlayer._boostAudioCtx || !YTPlayer._boostGainNode) return;

    // Limpiar audio element anterior si existe
    if (YTPlayer._boostAudioEl) {
        try { YTPlayer._boostAudioEl.pause(); } catch(e) {}
        YTPlayer._boostAudioEl.src = '';
        YTPlayer._boostAudioEl.load();
        YTPlayer._boostAudioEl = null;
    }
    if (YTPlayer._boostMediaSource) {
        try { YTPlayer._boostMediaSource.disconnect(); } catch(e) {}
        YTPlayer._boostMediaSource = null;
    }
    YTPlayer._boostPlaying = false;
    YTPlayer._boostVideoId = YTPlayer.currentVideoId;

    // Construir URL del proxy (determinista, no requiere llamada al backend)
    var proxyUrl = 'index.php?action=youtube_audio_proxy&video_id=' + encodeURIComponent(YTPlayer.currentVideoId);

    // Crear Audio element para streaming progresivo
    var audioEl = new Audio();
    audioEl.crossOrigin = 'anonymous';
    audioEl.volume = 1; // Ganancia la maneja el GainNode
    audioEl.preload = 'auto';
    audioEl.src = proxyUrl;

    // Crear MediaElementSourceNode (solo una vez por audio element)
    var mediaSource = YTPlayer._boostAudioCtx.createMediaElementSource(audioEl);
    mediaSource.connect(YTPlayer._boostGainNode);

    // Guardar referencias
    YTPlayer._boostAudioEl = audioEl;
    YTPlayer._boostMediaSource = mediaSource;
};

/**
 * Activa el modo Boost: silencia el iframe de YouTube,
 * y reproduce el audio via <audio> + MediaElementSourceNode → GainNode.
 * El streaming progresivo evita los cortes del enfoque anterior
 * (que descargaba el archivo entero antes de empezar a sonar).
 */
YTPlayer._enableAudioBoost = function () {
    if (!YTPlayer.currentVideoId) {
        alert('Reproduce un video primero antes de activar el Boost.');
        document.getElementById('youtubeBoostCheckbox').checked = false;
        return;
    }

    var boostSlider = document.getElementById('youtubeBoostSlider');
    var boostValue = document.getElementById('youtubeBoostValue');
    var boostStatus = document.getElementById('youtubeBoostStatus');

    // Mostrar slider de boost (solo desktop; en lite la ruleta lo maneja)
    if (boostSlider && !document.body.classList.contains('is-lite')) boostSlider.style.display = 'inline-block';
    if (boostValue && !document.body.classList.contains('is-lite')) boostValue.style.display = 'inline';
    if (boostStatus) boostStatus.textContent = 'Activando...';

    // Ajustar ganancia inicial
    var initialGain = parseInt(boostSlider ? boostSlider.value : 150, 10) / 100;
    if (YTPlayer._boostGainNode) {
        YTPlayer._boostGainNode.gain.value = initialGain;
    }

    var audioEl = YTPlayer._boostAudioEl;
    if (!audioEl) {
        if (boostStatus) boostStatus.textContent = '✗ Error: streaming no inicializado';
        document.getElementById('youtubeBoostCheckbox').checked = false;
        return;
    }

    // ── Health check al proxy en background (también cachea la URL en sesión) ──
    _youtubePost('youtube_audio_stream', { video_id: YTPlayer.currentVideoId }, function (data) {
        if (!data.ok || !data.url) {
            if (boostStatus) {
                boostStatus.textContent = '✗ Fallo: ' + (data.msg || 'no se pudo obtener audio');
                boostStatus.style.color = '#f87171';
            }
            document.getElementById('youtubeBoostCheckbox').checked = false;
            YTPlayer._disableAudioBoost();
            return;
        }
        // Proxy healthy — solo actualizar UI (el audio ya está sonando)
        if (boostStatus && boostStatus.textContent.indexOf('✗') !== 0) {
            boostStatus.textContent = '✓ Boost ON';
            boostStatus.style.color = '#f59e0b';
        }
    });

    // ── Callbacks del audio element ──
    audioEl.addEventListener('loadedmetadata', function () {
        if (boostStatus && boostStatus.textContent.indexOf('✓') !== 0) {
            boostStatus.textContent = '✓ Boost ON';
            boostStatus.style.color = '#f59e0b';
        }
    });

    audioEl.addEventListener('error', function () {
        console.error('Audio Boost playback error:', audioEl.error);
        if (boostStatus) {
            boostStatus.textContent = '✗ Error reproducción';
            boostStatus.style.color = '#f87171';
        }
    });

    // ── Silenciar iframe de YouTube ──
    if (YTPlayer.player && YTPlayer.player.mute) {
        YTPlayer.player.mute();
    }

    // ── Iniciar reproducción (user gesture del checkbox lo permite) ──
    var playPromise = audioEl.play();
    if (playPromise !== undefined) {
        playPromise.then(function () {
            YTPlayer._boostPlaying = true;
            // Sincronizar posición inicial con el iframe de YouTube
            if (YTPlayer.player && YTPlayer.player.getCurrentTime) {
                var ytTime = YTPlayer.player.getCurrentTime();
                if (ytTime > 0 && Math.abs((audioEl.currentTime || 0) - ytTime) > 0.5) {
                    audioEl.currentTime = ytTime;
                }
            }
        }).catch(function (err) {
            console.warn('Audio Boost autoplay prevented:', err);
            YTPlayer._boostPlaying = false;
            if (boostStatus) {
                boostStatus.textContent = '⚠ Click para activar';
                boostStatus.style.color = '#fbbf24';
            }
        });
    }

    // ── Sincronizar con el iframe de YouTube cada 2s ──
    if (YTPlayer._boostSyncInterval) clearInterval(YTPlayer._boostSyncInterval);
    YTPlayer._boostSyncInterval = setInterval(function () {
        var el = YTPlayer._boostAudioEl;
        if (!YTPlayer.player || !el) return;
        var ytState = YTPlayer.player.getPlayerState();

        // Si cambió el video mientras boost estaba activo, reiniciar stream
        if (YTPlayer._boostVideoId && YTPlayer._boostVideoId !== YTPlayer.currentVideoId) {
            YTPlayer._boostStartStream();
            el = YTPlayer._boostAudioEl;
            if (!el) return;
            el.play().catch(function () {});
        }

        if (ytState === YT.PlayerState.PLAYING) {
            if (el.paused) {
                el.play().catch(function () {});
            }
            var ytTime = YTPlayer.player.getCurrentTime() || 0;
            var boostTime = el.currentTime || 0;
            var drift = Math.abs(boostTime - ytTime);
            if (drift > 1.5) {
                el.currentTime = ytTime;
            }
        } else if (ytState === YT.PlayerState.PAUSED || ytState === YT.PlayerState.ENDED) {
            if (!el.paused) {
                el.pause();
            }
        }
    }, 2000);
};

/**
 * Desactiva el modo Boost: limpia el audio element, reactiva el iframe de YT.
 */
YTPlayer._disableAudioBoost = function (keepCheckbox) {
    // Parar sync
    if (YTPlayer._boostSyncInterval) {
        clearInterval(YTPlayer._boostSyncInterval);
        YTPlayer._boostSyncInterval = null;
    }

    // Limpiar Audio element + MediaElementSourceNode
    if (YTPlayer._boostAudioEl) {
        try { YTPlayer._boostAudioEl.pause(); } catch(e) {}
        YTPlayer._boostAudioEl.src = '';
        YTPlayer._boostAudioEl.load();
        YTPlayer._boostAudioEl = null;
    }
    if (YTPlayer._boostMediaSource) {
        try { YTPlayer._boostMediaSource.disconnect(); } catch(e) {}
        YTPlayer._boostMediaSource = null;
    }

    YTPlayer._boostPlaying = false;
    YTPlayer._boostVideoId = null;

    // Silenciar ganancia sin desconectar (grafo sigue intacto para reutilizar)
    if (YTPlayer._boostGainNode) {
        YTPlayer._boostGainNode.gain.value = 0;
    }

    // Reactivar el sonido del iframe de YouTube
    if (!keepCheckbox && YTPlayer.player && YTPlayer.player.unMute) {
        YTPlayer.player.unMute();
    }

    // Ocultar slider de boost (solo desktop; en lite lo maneja la ruleta)
    var boostSlider = document.getElementById('youtubeBoostSlider');
    var boostValue = document.getElementById('youtubeBoostValue');
    var boostStatus = document.getElementById('youtubeBoostStatus');
    if (boostSlider && !document.body.classList.contains('is-lite')) boostSlider.style.display = 'none';
    if (boostValue && !document.body.classList.contains('is-lite')) boostValue.style.display = 'none';
    if (boostStatus) {
        boostStatus.textContent = '';
    }
};

/**
 * Sincroniza el slider de volumen desde el estado actual del player.
 */
YTPlayer._syncSliderFromPlayer = function () {
    var slider = document.getElementById('youtubeVolumeSlider');
    var label = document.getElementById('youtubeVolumeLabel');
    if (YTPlayer.player && YTPlayer.player.getVolume && slider) {
        var v = YTPlayer.player.getVolume();
        slider.value = v;
        if (label) label.textContent = v + '%';
        localStorage.setItem('yt_volume', v);
    }
};

/**
 * Muestra una pista de volumen del sistema para usuarios en modo lite (coche).
 * Solo se muestra una vez por sesion.
 */
YTPlayer._maybeShowLiteVolumeHint = function () {
    // Detectar modo lite via data-gps-interval
    var body = document.body;
    var isLite = body && body.getAttribute('data-gps-interval') === '90';
    if (!isLite) return;

    // Solo una vez por sesion
    if (sessionStorage.getItem('yt_vol_hint_shown')) return;
    sessionStorage.setItem('yt_vol_hint_shown', '1');

    // Esperar un poco a que la pagina cargue
    setTimeout(function () {
        var hint = document.createElement('div');
        hint.className = 'youtube-volume-hint-toast';
        hint.innerHTML = '<span>🔊</span> Para mejor sonido, sube el volumen del dispositivo al maximo y usa el deslizante para ajustar.';
        hint.style.cssText = 'position:fixed;bottom:100px;left:50%;transform:translateX(-50%);background:#1a2a1a;color:#4ade80;border:1px solid #4ade80;padding:12px 20px;border-radius:10px;font-size:14px;z-index:9999;max-width:90vw;text-align:center;box-shadow:0 4px 20px rgba(0,0,0,.5);animation:ytHintFadeIn .4s ease';
        document.body.appendChild(hint);

        // Auto-dismiss tras 8 segundos
        setTimeout(function () {
            hint.style.transition = 'opacity .5s';
            hint.style.opacity = '0';
            setTimeout(function () { if (hint.parentNode) hint.parentNode.removeChild(hint); }, 500);
        }, 8000);

        // Tambien cerrar al tocar
        hint.addEventListener('click', function () {
            hint.style.opacity = '0';
            setTimeout(function () { if (hint.parentNode) hint.parentNode.removeChild(hint); }, 300);
        });
    }, 2000);
};



// ── Cassette Deck: Rotary Knobs ──────────────────────────────────────
YTPlayer._initRotaryKnobs = function () {
    var knobs = document.querySelectorAll('.yt-knob');
    for (var i = 0; i < knobs.length; i++) {
        (function (knob) {
            var type = knob.getAttribute('data-knob'); // 'volume' | 'boost'
            var sliderId = type === 'volume' ? 'youtubeVolumeSlider' : 'youtubeBoostSlider';
            var slider = document.getElementById(sliderId);
            var valEl = type === 'volume' ? document.getElementById('ytVolVal') : document.getElementById('ytBoostVal');
            if (!slider) return;

            var min = parseInt(slider.min, 10) || 0;
            var max = parseInt(slider.max, 10) || 100;
            var angleMin = -135; // 7 o'clock
            var angleMax = 135;  // 5 o'clock

            function valueToAngle(v) {
                return angleMin + ((v - min) / (max - min)) * (angleMax - angleMin);
            }
            function angleToValue(a) {
                var pct = (a - angleMin) / (angleMax - angleMin);
                var v = Math.round(min + pct * (max - min));
                return Math.max(min, Math.min(max, v));
            }

            function updateKnob(val) {
                var angle = valueToAngle(val);
                knob.style.transform = 'rotate(' + angle + 'deg)';
                if (valEl) {
                    valEl.style.transform = 'translate(-50%,-50%) rotate(' + (-angle) + 'deg)';
                    valEl.textContent = val;
                }
            }

            // Init from saved value
            var savedVal = localStorage.getItem(type === 'volume' ? 'yt_volume' : 'yt_boost_gain');
            if (savedVal !== null) {
                var v = parseInt(savedVal, 10);
                if (!isNaN(v)) { slider.value = v; updateKnob(v); }
            }
            updateKnob(parseInt(slider.value, 10));

            var dragging = false;
            var startY = 0;
            var startVal = 0;
            var _lastTickVal = parseInt(slider.value, 10);

            function onStart(e) {
                dragging = true;
                startY = e.touches ? e.touches[0].clientY : e.clientY;
                startVal = parseInt(slider.value, 10);
                _lastTickVal = startVal;
                e.preventDefault();
            }
            function onMove(e) {
                if (!dragging) return;
                var y = e.touches ? e.touches[0].clientY : e.clientY;
                var deltaY = startY - y;
                var sensitivity = (max - min) / 120; // 120px drag = full range
                var newVal = Math.round(startVal + deltaY * sensitivity);
                newVal = Math.max(min, Math.min(max, newVal));
                // Audible detent tick only when integer value changes
                if (newVal !== _lastTickVal) { _lastTickVal = newVal; SfxPlayer.knobTick(); }
                slider.value = newVal;
                updateKnob(newVal);

                if (type === 'volume') {
                    if (YTPlayer.player && YTPlayer.player.setVolume) YTPlayer.player.setVolume(newVal);
                    if (YTPlayer._radioAudioEl) YTPlayer._radioAudioEl.volume = newVal / 100;
                    localStorage.setItem('yt_volume', newVal);
                } else {
                    localStorage.setItem('yt_boost_gain', newVal);
                    if (YTPlayer._boostGainNode) YTPlayer._boostGainNode.gain.value = newVal / 100;
                    // Auto-enable boost when turning up
                    var cb = document.getElementById('youtubeBoostCheckbox');
                    if (cb && !cb.checked && newVal > 50) {
                        cb.checked = true;
                        cb.dispatchEvent(new Event('change'));
                    } else if (cb && cb.checked && newVal <= 50) {
                        cb.checked = false;
                        cb.dispatchEvent(new Event('change'));
                    }
                }
            }
            function onEnd() {
                if (!dragging) return;
                dragging = false;
            }

            knob.addEventListener('mousedown', onStart);
            knob.addEventListener('touchstart', onStart, { passive: false });
            document.addEventListener('mousemove', onMove);
            document.addEventListener('touchmove', onMove, { passive: false });
            document.addEventListener('mouseup', onEnd);
            document.addEventListener('touchend', onEnd);
        })(knobs[i]);
    }

    // Sync knob on volume slider input (desktop fallback)
    var volSlider = document.getElementById('youtubeVolumeSlider');
    if (volSlider) {
        volSlider.addEventListener('input', function () {
            var knob = document.getElementById('ytVolKnob');
            var valEl = document.getElementById('ytVolVal');
            if (knob) {
                var v = parseInt(volSlider.value, 10);
                var angleMin = -135, angleMax = 135;
                var min = 0, max = 100;
                var angle = angleMin + ((v - min) / (max - min)) * (angleMax - angleMin);
                knob.style.transform = 'rotate(' + angle + 'deg)';
                if (valEl) {
                    valEl.style.transform = 'translate(-50%,-50%) rotate(' + (-angle) + 'deg)';
                    valEl.textContent = v;
                }
            }
        });
    }

    // Sync boost knob on boost slider input
    var boostSlider = document.getElementById('youtubeBoostSlider');
    if (boostSlider) {
        boostSlider.addEventListener('input', function () {
            var knob = document.getElementById('ytBoostKnob');
            var valEl = document.getElementById('ytBoostVal');
            if (knob) {
                var v = parseInt(boostSlider.value, 10);
                var angleMin = -135, angleMax = 135;
                var min = 50, max = 300;
                var angle = angleMin + ((v - min) / (max - min)) * (angleMax - angleMin);
                knob.style.transform = 'rotate(' + angle + 'deg)';
                if (valEl) {
                    valEl.style.transform = 'translate(-50%,-50%) rotate(' + (-angle) + 'deg)';
                    valEl.textContent = v;
                }
            }
        });
    }
};

// ── Cassette Deck: Tape load/eject animations ────────────────────────
YTPlayer._animateTapeLoad = function () {
    var tape = document.getElementById('ytCassetteTape');
    var empty = document.getElementById('ytCassetteEmpty');
    var reels = document.querySelectorAll('.yt-cassette-reel');
    if (!tape) return;
    // Guard: debounce within same event-loop tick (playVideo + loadPlayer double-call)
    if (tape._tapeLoadSfxPending) return;
    tape._tapeLoadSfxPending = true;
    setTimeout(function () { if (tape) tape._tapeLoadSfxPending = false; }, 0);
    SfxPlayer.tapeInsert();
    if (empty) empty.classList.add('hidden');
    tape.classList.add('loaded');
    tape.classList.remove('ejecting');
    // Start reels spinning
    for (var i = 0; i < reels.length; i++) {
        reels[i].classList.add('spinning');
        reels[i].classList.remove('spinning-fast', 'spinning-turbo');
    }
};

YTPlayer._animateTapeEject = function () {
    var tape = document.getElementById('ytCassetteTape');
    var empty = document.getElementById('ytCassetteEmpty');
    var reels = document.querySelectorAll('.yt-cassette-reel');
    if (!tape) return;
    SfxPlayer.tapeEject();
    tape.classList.add('ejecting');
    tape.classList.remove('loaded');
    // Stop reels
    for (var i = 0; i < reels.length; i++) {
        reels[i].classList.remove('spinning', 'spinning-fast', 'spinning-turbo');
    }
    setTimeout(function () {
        if (empty) empty.classList.remove('hidden');
    }, 300);
};

YTPlayer._setSpoolSpeed = function (multiplier) {
    var reels = document.querySelectorAll('.yt-cassette-reel');
    for (var i = 0; i < reels.length; i++) {
        reels[i].classList.remove('spinning-fast', 'spinning-turbo');
        if (multiplier >= 8) {
            reels[i].classList.add('spinning-turbo');
        } else if (multiplier >= 3) {
            reels[i].classList.add('spinning-fast');
        } else if (multiplier >= 1) {
            reels[i].classList.add('spinning');
        }
    }
};

// ── Cassette Deck: Stop button ───────────────────────────────────────
YTPlayer._handleStop = function () {
    SfxPlayer.stop();
    // Stop YouTube
    if (YTPlayer.player && typeof YTPlayer.player.stopVideo === 'function') {
        YTPlayer.player.stopVideo();
    }
    // Stop radio
    if (YTPlayer._radioActive) {
        YTPlayer._stopRadio();
    }
    // Stop boost
    YTPlayer._disableAudioBoost();

    // Eject tape
    YTPlayer._animateTapeEject();

    // Hide DJ Jefry buttons/bar via CSS (remove yt-video-active from body)
    document.body.classList.remove('yt-video-active');

    // Reset UI
    YTPlayer.currentVideoId = '';
    YTPlayer.currentVideoTitle = '';
    YTPlayer.currentVideoThumbnail = '';
    var nowPlaying = document.getElementById('youtubeNowPlaying');
    var nowPlayingTitle = document.getElementById('youtubeNowPlayingTitle');
    if (nowPlaying) { nowPlaying.style.display = 'block'; nowPlaying.classList.add('yt-radio-idle'); }
    if (nowPlayingTitle) { nowPlayingTitle.textContent = 'Sintoniza una emisora'; nowPlayingTitle.classList.remove('marquee'); if (nowPlayingTitle._marqueeTimer) { clearInterval(nowPlayingTitle._marqueeTimer); nowPlayingTitle._marqueeTimer = null; } nowPlayingTitle.style.transform = ''; }

    // Reset tape counter and time
    var counter = document.getElementById('ytTapeCounter');
    var timeEl = document.getElementById('ytTapeTime');
    if (counter) counter.textContent = '000';
    if (timeEl) timeEl.textContent = '--:--';
    clearInterval(YTPlayer._tapeTimer);

    // Stereo indicator off
    var stereo = document.getElementById('ytStereoIndicator');
    if (stereo) stereo.classList.remove('on');

    // Dial back to scanning
    YTPlayer._setDialMode('scanning');
    YTPlayer._startDialWander();
};

// ── Cassette Deck: Tape counter + time display ───────────────────────
YTPlayer._tapeTimer = null;
YTPlayer._tapeCount = 0;

YTPlayer._startTapeCounter = function () {
    clearInterval(YTPlayer._tapeTimer);
    YTPlayer._tapeCount = 0;
    YTPlayer._tapeTimer = setInterval(function () {
        if (!YTPlayer.player) return;
        var state = YTPlayer.player.getPlayerState ? YTPlayer.player.getPlayerState() : -1;
        if (state !== YT.PlayerState.PLAYING && state !== YT.PlayerState.BUFFERING) return;

        // Update tape counter (fake, cycles 000-999)
        YTPlayer._tapeCount = (YTPlayer._tapeCount + 1) % 1000;
        var counter = document.getElementById('ytTapeCounter');
        if (counter) {
            counter.textContent = String(YTPlayer._tapeCount).padStart(3, '0');
        }

        // Update real time display
        YTPlayer._updateTimeDisplay();
    }, 1000);
};

YTPlayer._updateTimeDisplay = function () {
    var timeEl = document.getElementById('ytTapeTime');
    if (!timeEl || !YTPlayer.player) return;
    try {
        var secs = Math.floor(YTPlayer.player.getCurrentTime());
        if (isNaN(secs)) return;
        var m = Math.floor(secs / 60);
        var s = secs % 60;
        timeEl.textContent = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
    } catch (e) {}
};

// ── Cassette Deck: Long-press FF/RW progressive ──────────────────────
YTPlayer._initLongPressSeek = function () {
    var prevBtn = document.getElementById('youtubePrevBtn');
    var nextBtn = document.getElementById('youtubeNextBtn');
    var speedBadge = document.getElementById('ytSpeedBadge');

    function setupLongPress(btn, isForward) {
        if (!btn) return;
        var pressTimer = null;
        var pressStart = 0;
        var tickTimer = null;
        var speedLevel = 0;
        var wasLongPress = false;

        function getDeltaByElapsed(elapsed) {
            if (elapsed < 1500) return { delta: 1.5, label: 'x2', multiplier: 2 };
            if (elapsed < 3000) return { delta: 3, label: 'x4', multiplier: 4 };
            if (elapsed < 5000) return { delta: 6, label: 'x8', multiplier: 8 };
            return { delta: 12, label: 'x16', multiplier: 16 };
        }

        function doSeekTick() {
            if (!YTPlayer.player || !YTPlayer.player.seekTo) return;
            var elapsed = Date.now() - pressStart;
            var info = getDeltaByElapsed(elapsed);
            var currentTime = YTPlayer.player.getCurrentTime() || 0;
            var newTime = isForward ? currentTime + info.delta : currentTime - info.delta;
            newTime = Math.max(0, newTime);
            YTPlayer.player.seekTo(newTime, true);
            YTPlayer._setSpoolSpeed(info.multiplier);

            // Update time display
            YTPlayer._updateTimeDisplay();

            if (speedBadge && info.label !== 'x2') {
                speedBadge.textContent = isForward ? '▶▶ ' + info.label : info.label + ' ◀◀';
                speedBadge.classList.add('show');
            }
        }

        btn.addEventListener('mousedown', function (e) {
            // Only long-press if there's a video playing
            if (!YTPlayer.currentVideoId) return;
            wasLongPress = false;
            pressStart = Date.now();
            pressTimer = setTimeout(function () {
                wasLongPress = true;
                if (isForward) SfxPlayer.ffStart(); else SfxPlayer.rwStart();
                // First tick immediately
                doSeekTick();
                // Then every 200ms
                tickTimer = setInterval(doSeekTick, 200);
            }, 300); // 300ms threshold to distinguish click from long-press
        });

        btn.addEventListener('touchstart', function (e) {
            if (!YTPlayer.currentVideoId) return;
            wasLongPress = false;
            pressStart = Date.now();
            pressTimer = setTimeout(function () {
                wasLongPress = true;
                if (isForward) SfxPlayer.ffStart(); else SfxPlayer.rwStart();
                doSeekTick();
                tickTimer = setInterval(doSeekTick, 200);
            }, 300);
        });

        function onRelease() {
            clearTimeout(pressTimer);
            clearInterval(tickTimer);
            YTPlayer._setSpoolSpeed(1);
            if (speedBadge) speedBadge.classList.remove('show');

            if (wasLongPress) {
                if (isForward) SfxPlayer.ffStop(); else SfxPlayer.rwStop();
            } else {
                // Short click: normal prev/next behavior + mechanical button click sound
                SfxPlayer.btnClick();
                if (isForward) YTPlayer.playNext();
                else YTPlayer.playPrevious();
            }
        }

        btn.addEventListener('mouseup', onRelease);
        btn.addEventListener('mouseleave', onRelease);
        btn.addEventListener('touchend', onRelease);
        btn.addEventListener('touchcancel', onRelease);
    }

    setupLongPress(prevBtn, false);
    setupLongPress(nextBtn, true);
};

// ── Cassette Deck: Init all cassette-related features ────────────────
YTPlayer._initCassette = function () {
    // Show controls and load tape when video starts
    var origLoadPlayer = YTPlayer.loadPlayer;
    YTPlayer.loadPlayer = function (videoId) {
        origLoadPlayer.call(YTPlayer, videoId);
        // Show mechanical controls
        var controls = document.getElementById('youtubeControls');
        if (controls) controls.style.display = 'flex';
        // Show DJ Jefry buttons/bar via CSS (yt-video-active on body)
        document.body.classList.add('yt-video-active');
        // Disable iframe clicks in lite non-FS mode (transport buttons handle playback)
        if (document.body.classList.contains('is-lite')) {
            var iframe = document.querySelector('#youtubePlayerContainer iframe');
            if (iframe) iframe.style.pointerEvents = 'none';
        }
        // Load tape animation
        YTPlayer._animateTapeLoad();
        // Start tape counter
        YTPlayer._startTapeCounter();
        // Stereo indicator on
        var stereo = document.getElementById('ytStereoIndicator');
        if (stereo) stereo.classList.add('on');
    };

    // Reel spin based on play state (via existing onStateChange)
    var origOnReady = null;
    // Hook into existing event loop
    setInterval(function () {
        if (!YTPlayer.player || !YTPlayer.currentVideoId) return;
        try {
            var state = YTPlayer.player.getPlayerState();
            var reels = document.querySelectorAll('.yt-cassette-reel');
            if (state === YT.PlayerState.PLAYING || state === YT.PlayerState.BUFFERING) {
                for (var i = 0; i < reels.length; i++) {
                    if (!reels[i].classList.contains('spinning') &&
                        !reels[i].classList.contains('spinning-fast') &&
                        !reels[i].classList.contains('spinning-turbo')) {
                        reels[i].classList.add('spinning');
                    }
                }
                // Update time display every second
                if (!YTPlayer._timeUpdater) {
                    YTPlayer._timeUpdater = setInterval(function () {
                        if (!YTPlayer.currentVideoId) { clearInterval(YTPlayer._timeUpdater); YTPlayer._timeUpdater = null; return; }
                        YTPlayer._updateTimeDisplay();
                    }, 1000);
                }
            } else if (state === YT.PlayerState.PAUSED) {
                for (var j = 0; j < reels.length; j++) {
                    reels[j].classList.remove('spinning', 'spinning-fast', 'spinning-turbo');
                }
            }
        } catch (e) {}
    }, 500);

    // Init stop button
    var stopBtn = document.getElementById('youtubeStopBtn');
    if (stopBtn) {
        stopBtn.addEventListener('click', function () {
            YTPlayer._handleStop();
        });
    }

    // Init rotary knobs
    YTPlayer._initRotaryKnobs();

    // Init long-press seek
    YTPlayer._initLongPressSeek();

    // Init speed badge dismiss on click
    var speedBadge = document.getElementById('ytSpeedBadge');
    if (speedBadge) {
        speedBadge.addEventListener('click', function () {
            speedBadge.classList.remove('show');
        });
    }
};

// ── Lite: Abrir modal "Añadir a lista" desde botón REC ───────────────
YTPlayer._openLiteAddModal = function () {
    SfxPlayer.rec(); SfxPlayer.btnClick();
    var m = document.getElementById('addPlModalLite');
    if (!m) return;
    var vid = YTPlayer.currentVideoId || '';
    var ttl = YTPlayer.currentVideoTitle || '';
    var thm = YTPlayer.currentVideoThumbnail || '';
    var chn = YTPlayer.currentVideoChannel || '';
    var vi = document.getElementById('addPlVid');
    var vt = document.getElementById('addPlVTitle');
    var vth = document.getElementById('addPlVThumb');
    var vc = document.getElementById('addPlVChan');
    var hi = document.getElementById('addPlVideoHint');
    if (vi) vi.value = vid;
    if (vt) vt.value = ttl;
    if (vth) vth.value = thm;
    if (vc) vc.value = chn;
    if (hi) {
        hi.textContent = vid ? 'Video: ' + ttl : 'Reproduce un video primero para anadirlo a una lista.';
        hi.style.color = vid ? '' : '#ef4444';
    }
    m.style.display = 'flex';
    var led = document.getElementById('ytRecLed');
    if (led) { led.classList.add('blink'); setTimeout(function () { led.classList.remove('blink'); }, 900); }
};

// ── Helpers ────────────────────────────────────────────────────────

function _youtubePost(action, data, callback) {
    data = data || {};
    data.action = action;

    var formData = new FormData();
    for (var key in data) {
        if (data.hasOwnProperty(key)) {
            formData.append(key, data[key]);
        }
    }

    fetch('index.php', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
        },
    })
    .then(function (resp) { return resp.json(); })
    .then(function (json) {
        if (callback) callback(json);
    })
    .catch(function (err) {
        console.error('YT API error:', err);
        if (callback) callback({ ok: false, error: err.message });
    });
}

function _youtubeFetchPlaylists(callback) {
    // Use the global data from PHP
    callback(window._ytPlaylists || []);
}

function _youtubeEscapeHtml(str) {
    if (!str) return '';
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function _youtubeToast(msg, duration) {
    duration = duration || 2500;
    var existing = document.querySelector('.youtube-toast');
    if (existing) existing.remove();

    var toast = document.createElement('div');
    toast.className = 'youtube-toast';
    toast.textContent = msg;
    document.body.appendChild(toast);

    setTimeout(function () {
        if (toast.parentNode) toast.remove();
    }, duration + 400);
}

// ── Radio en directo ─────────────────────────────────────────────

YTPlayer._showToast = _youtubeToast;

YTPlayer._radioAudioEl = null;
YTPlayer._radioActive = false;
YTPlayer._radioCurrentUrl = null;

/**
 * Reproduce una emisora de radio en directo.
 * @param {HTMLElement} tagEl - Elemento del tag clickeado
 * @param {string} url - URL del stream de radio
 * @param {string} name - Nombre de la emisora
 * @param {string} icon - Emoji del icono
 */
YTPlayer.playRadioStation = function (tagEl, url, name, icon) {
    // Compute FM frequency for dial — prefer data-freq attr, else position-based
    var freq = null;
    if (tagEl && tagEl.getAttribute('data-freq')) {
        freq = parseFloat(tagEl.getAttribute('data-freq'));
    }
    if (!freq && tagEl) {
        var radioTags = document.querySelectorAll('.youtube-radio-tag');
        var radioIdx = -1;
        for (var r = 0; r < radioTags.length; r++) {
            if (radioTags[r] === tagEl) { radioIdx = r; break; }
        }
        if (radioIdx >= 0 && radioTags.length > 0) {
            freq = 88 + (radioIdx / Math.max(radioTags.length - 1, 1)) * 20;
        }
    }

    // Si ya estamos escuchando esta misma emisora, toggle pause/play
    if (YTPlayer._radioActive && YTPlayer._radioAudioEl && YTPlayer._radioCurrentUrl === url) {
        if (YTPlayer._radioAudioEl.paused) {
            YTPlayer._radioAudioEl.play().catch(function () {
                YTPlayer._showToast('Error al reanudar ' + name, 3000);
            });
            var ppBtn = document.getElementById('youtubePlayPauseBtn');
            if (ppBtn) ppBtn.innerHTML = '&#10074;&#10074;';
            YTPlayer._dialSource = 'radio';
            YTPlayer._dialFreq = freq;
            YTPlayer._stopDialWander();
            YTPlayer._setDialMode('tuned', freq);
        } else {
            YTPlayer._radioAudioEl.pause();
            var ppBtn = document.getElementById('youtubePlayPauseBtn');
            if (ppBtn) ppBtn.innerHTML = '&#9654;';
            YTPlayer._setDialMode('scanning');
        }
        return;
    }

    // Detener lo que esté sonando (YouTube, Boost, otra radio)
    YTPlayer._stopAllAudio();

    // Marcar el tag como activo
    var allTags = document.querySelectorAll('.youtube-radio-tag');
    for (var i = 0; i < allTags.length; i++) {
        allTags[i].classList.remove('youtube-radio-active');
    }
    if (tagEl) {
        tagEl.classList.add('youtube-radio-active');
    }

    // Deshabilitar prev/next (radio en directo no tiene avance/retroceso)
    var prevBtn = document.getElementById('youtubePrevBtn');
    var nextBtn = document.getElementById('youtubeNextBtn');
    if (prevBtn) { prevBtn.disabled = true; prevBtn.classList.add('youtube-ctrl-disabled'); }
    if (nextBtn) { nextBtn.disabled = true; nextBtn.classList.add('youtube-ctrl-disabled'); }

    // Mostrar now-playing
    var nowPlaying = document.getElementById('youtubeNowPlaying');
    var nowPlayingTitle = document.getElementById('youtubeNowPlayingTitle');
    if (nowPlaying) { nowPlaying.style.display = 'block'; nowPlaying.classList.remove('yt-radio-idle'); }
    if (nowPlayingTitle) {
        nowPlayingTitle.textContent = (icon || '📻') + ' ' + name;
    }

    // Crear elemento de audio
    YTPlayer._radioAudioEl = new Audio();
    YTPlayer._radioAudioEl.src = url;
    YTPlayer._radioAudioEl.preload = 'none';

    // Volumen desde slider
    var volSlider = document.getElementById('youtubeVolumeSlider');
    if (volSlider) {
        YTPlayer._radioAudioEl.volume = parseInt(volSlider.value, 10) / 100;
    }

    YTPlayer._radioCurrentUrl = url;
    YTPlayer._radioActive = true;

    // Evento de error
    YTPlayer._radioAudioEl.addEventListener('error', function () {
        var errMsg = 'Error de conexión';
        if (YTPlayer._radioAudioEl && YTPlayer._radioAudioEl.error) {
            var e = YTPlayer._radioAudioEl.error;
            if (e.code === MediaError.MEDIA_ERR_NETWORK) errMsg = 'Error de red';
            else if (e.code === MediaError.MEDIA_ERR_DECODE) errMsg = 'Formato no soportado';
            else if (e.code === MediaError.MEDIA_ERR_SRC_NOT_SUPPORTED) errMsg = 'Formato no soportado';
        }
        YTPlayer._showToast(errMsg + ' — ' + name, 4000);
    });

    // Reproducir
    YTPlayer._radioAudioEl.play().then(function () {
        var ppBtn = document.getElementById('youtubePlayPauseBtn');
        if (ppBtn) ppBtn.innerHTML = '&#10074;&#10074;';
        YTPlayer._dialSource = 'radio';
        YTPlayer._dialFreq = freq;
        YTPlayer._stopDialWander();
        YTPlayer._setDialMode('tuned', freq);
        var controls = document.getElementById('youtubeControls');
        if (controls) controls.style.display = 'flex';
    }).catch(function () {
        YTPlayer._showToast('No se pudo conectar a ' + name + '. Stream caído o formato no soportado.', 5000);
        YTPlayer._stopRadio();
    });
};

/**
 * Detiene la reproducción de radio y limpia el estado.
 */
YTPlayer._stopRadio = function () {
    if (YTPlayer._radioAudioEl) {
        YTPlayer._radioAudioEl.pause();
        YTPlayer._radioAudioEl.src = '';
        YTPlayer._radioAudioEl = null;
    }
    YTPlayer._radioActive = false;
    YTPlayer._radioCurrentUrl = null;
    YTPlayer._dialSource = null;
    YTPlayer._dialFreq = null;
    YTPlayer._stopDialWander();

    // Re-habilitar prev/next
    var prevBtn = document.getElementById('youtubePrevBtn');
    var nextBtn = document.getElementById('youtubeNextBtn');
    if (prevBtn) { prevBtn.disabled = false; prevBtn.classList.remove('youtube-ctrl-disabled'); }
    if (nextBtn) { nextBtn.disabled = false; nextBtn.classList.remove('youtube-ctrl-disabled'); }

    // Quitar active de tags
    var allTags = document.querySelectorAll('.youtube-radio-tag');
    for (var i = 0; i < allTags.length; i++) {
        allTags[i].classList.remove('youtube-radio-active');
    }
};

/**
 * Detiene TODO el audio: YouTube + Boost + Radio.
 * Usado antes de cambiar de fuente de audio.
 */
YTPlayer._stopAllAudio = function () {
    YTPlayer._stopRadio();

    // Detener YouTube
    if (YTPlayer.player && YTPlayer.player.stopVideo) {
        YTPlayer.player.stopVideo();
    } else if (YTPlayer.player && YTPlayer.player.pauseVideo) {
        YTPlayer.player.pauseVideo();
    }

    // Limpiar Boost
    if (YTPlayer._boostAudioEl || YTPlayer._boostGainNode || YTPlayer._boostAudioCtx) {
        YTPlayer._disableAudioBoost();
        var boostCheckbox = document.getElementById('youtubeBoostCheckbox');
        if (boostCheckbox) boostCheckbox.checked = false;
    }

    // Reset UI a estado inicial
    var placeholder = document.getElementById('youtubePlayerPlaceholder');
    var container = document.getElementById('youtubePlayerContainer');
    var nowPlaying = document.getElementById('youtubeNowPlaying');
    var nowPlayingTitle = document.getElementById('youtubeNowPlayingTitle');
    var controls = document.getElementById('youtubeControls');
    var ppBtn = document.getElementById('youtubePlayPauseBtn');
    if (placeholder) placeholder.style.display = 'block';
    if (container) container.style.display = 'none';
    if (nowPlaying) { nowPlaying.classList.add('yt-radio-idle'); nowPlaying.style.display = ''; }
    if (nowPlayingTitle) { nowPlayingTitle.textContent = 'Sintoniza una emisora'; nowPlayingTitle.classList.remove('marquee'); if (nowPlayingTitle._marqueeTimer) { clearInterval(nowPlayingTitle._marqueeTimer); nowPlayingTitle._marqueeTimer = null; } nowPlayingTitle.style.transform = ''; }
    if (controls) controls.style.display = 'none';
    if (ppBtn) ppBtn.innerHTML = '&#9654;';
    YTPlayer._setDialMode('scanning');
};

function _youtubeEscapeAttr(str) {
    if (!str) return '';
    return str.replace(/&/g, '&amp;').replace(/'/g, '\\\'').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\\/g, '\\\\');
}

// ═══ Reminder Polling ═══

function setupReminderPolling() {
    var banner = document.getElementById('voiceReminderBanner');
    var bannerText = document.getElementById('voiceReminderText');
    var bannerPlay = document.getElementById('voiceReminderPlay');
    var bannerClose = document.getElementById('voiceReminderClose');
    var deliveredIds = {};
    var pendingQueue = [];
    var hideTimer = null;
    var fetching = false;
    var currentText = '';

    if (!banner || !bannerText) return;

    function showNext() {
        clearTimeout(hideTimer);
        hideTimer = null;

        if (pendingQueue.length === 0) {
            banner.hidden = true;
            currentText = '';
            return;
        }

        var r = pendingQueue.shift();
        currentText = (r.descripcion || 'Recordatorio');
        bannerText.textContent = '🔔 ' + currentText;
        banner.hidden = false;

        // Auto-hide after 15 seconds, then show next
        hideTimer = setTimeout(function () { showNext(); }, 15000);
    }

    function speakReminder(text) {
        if (!text) return;
        // Prefer browser SpeechSynthesis, works on user gesture in all major browsers
        if (window.speechSynthesis) {
            speechSynthesis.cancel();
            var utter = new SpeechSynthesisUtterance(text);
            // Select best Spanish voice
            var voices = speechSynthesis.getVoices();
            var preferred = ['Monica', 'Helena', 'Sara', 'Paulina', 'Marisol'];
            for (var p = 0; p < preferred.length; p++) {
                for (var v = 0; v < voices.length; v++) {
                    if (voices[v].lang.indexOf('es') === 0 && voices[v].name.indexOf(preferred[p]) !== -1) {
                        utter.voice = voices[v];
                        break;
                    }
                }
                if (utter.voice) break;
            }
            if (!utter.voice) {
                for (var j = 0; j < voices.length; j++) {
                    if (voices[j].lang.indexOf('es') === 0) { utter.voice = voices[j]; break; }
                }
            }
            utter.rate = 0.93;
            utter.pitch = 1.0;
            utter.volume = 1.0;
            speechSynthesis.speak(utter);
            // Chrome bug workaround: resume if paused silently
            setTimeout(function () {
                if (speechSynthesis && speechSynthesis.paused) speechSynthesis.resume();
            }, 100);
            return;
        }
        // Fallback: server-side TTS via OpenAI for environments without SpeechSynthesis
        var ttsForm = new FormData();
        ttsForm.append('action', 'tts');
        ttsForm.append('text', text);
        fetch(window.location.pathname + window.location.search, {
            method: 'POST', body: ttsForm, credentials: 'same-origin',
            headers: { 'Accept': 'audio/mpeg', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) {
            if (!res.ok) return;
            return res.blob();
        }).then(function (blob) {
            if (!blob) return;
            var url = URL.createObjectURL(blob);
            var audio = new Audio(url);
            audio.onended = function () { URL.revokeObjectURL(url); };
            audio.play().catch(function () {});
        }).catch(function () {});
    }

    if (bannerPlay) {
        bannerPlay.addEventListener('click', function () {
            if (!currentText) return;
            speakReminder('Recordatorio: ' + currentText);
        });
    }

    if (bannerClose) {
        bannerClose.addEventListener('click', function () {
            clearTimeout(hideTimer);
            showNext();
        });
    }

    function checkReminders() {
        if (fetching) return;
        fetching = true;

        var formData = new FormData();
        formData.append('action', 'voice_check_reminders');

        fetch(window.location.pathname + window.location.search, {
            method: 'POST', body: formData, credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) { return res.json(); })
        .then(function (reminders) {
            fetching = false;
            if (!reminders || !reminders.length) return;

            var wasEmpty = pendingQueue.length === 0 && banner.hidden;

            for (var i = 0; i < reminders.length; i++) {
                var r = reminders[i];
                if (deliveredIds[r.id]) continue;
                deliveredIds[r.id] = true;
                pendingQueue.push(r);
            }

            // If banner was idle, kick off the queue
            if (wasEmpty && pendingQueue.length > 0) {
                showNext();
            }
        }).catch(function () { fetching = false; });
    }

    // Check immediately, then every 30 seconds
    checkReminders();
    setInterval(checkReminders, 30000);
}

// ═══ GPS Position Tracking ═══════════════════════════════════════════════════
// Mínimo uso de recursos: getCurrentPosition en vez de watchPosition
// (el chip GPS solo se activa unos segundos por poll y luego se apaga).
// Lite (coche, Android viejo): intervalo 90s, diff plano, sin trigonométricas.
// Resto (móvil, laptop): intervalo 60s.
//
// ⚠️  El GPS NO arranca automáticamente. Espera a que el usuario toque la pantalla
// o interactúe con la página. Esto evita el bucle de permisos en Android 8.1
// (error "choose a bubble from overlay of other apps") y el diálogo intrusivo.

(function () {
    'use strict';
    if (!navigator.geolocation) return;

    var body         = document.body;
    var iLite        = body ? body.getAttribute('data-gps-interval') : null;
    var isCarDevice  = body ? body.getAttribute('data-is-car-device') === '1' : false;
    var interval     = (iLite === '90') ? 90000 : 60000;     // ms entre polls
    var maxAge    = isCarDevice ? 0 : 30000;  // coche: forzar fix fresco; resto: reusar hasta 30s
    var timeout   = isCarDevice ? 30000 : ((iLite === '90') ? 18000 : 15000);  // coche: 30s para cold start GPS

    var lastLat   = 0;
    var lastLng   = 0;
    var lastTs    = 0;
    var gpsTimer  = null;         // referencia al setInterval (para poder pararlo)
    var gpsStopped = false;       // true si el usuario denegó permiso → no reintentar
    var gpsStarted = false;       // true cuando el GPS ya está en marcha
    var gpsPending = false;       // evita solapamiento de polls
    var _gpsFailCount = 0;        // fallos fetch consecutivos → parar si >= 3
    var watchId      = null;      // watchPosition handle (coche, tiempo real)
    var _lastSentTs  = 0;         // throttle: última vez que enviamos al servidor
    var _fixLat      = 0;         // último fix GPS válido (para GpsCopilot)
    var _fixLng      = 0;
    var _fixTs       = 0;

    function dist2D(aLat, aLng, bLat, bLng) {
        var dy = (bLat - aLat) * 111320;
        var dx = (bLng - aLng) * (111320 * Math.cos(aLat * 0.0174533));
        return Math.sqrt(dx * dx + dy * dy);
    }

    // ── Helper: enviar posición al servidor (compartido poll + watchPosition) ──
    function _sendGps(lat, lng, acc) {
        var gpsUrl = window.location.pathname + '?action=touch_gps&lat=' + lat.toFixed(6) + '&lng=' + lng.toFixed(6) + '&acc=' + acc.toFixed(1);
        fetch(gpsUrl, { credentials: 'same-origin' })
            .then(function (r) { if (r.ok) { _gpsFailCount = 0; } else { _gpsFailCount++; } })
            .catch(function () { _gpsFailCount++; })
            .then(function () {
                if (_gpsFailCount >= 3) {
                    gpsStopped = true;
                    if (watchId !== null) { navigator.geolocation.clearWatch(watchId); watchId = null; }
                    if (gpsTimer) { clearInterval(gpsTimer); gpsTimer = null; }
                }
            });
    }

    var _firstTick = true;

    // ── Restaurar estado GPS desde sessionStorage (sobrevive recargas de página) ──
    (function () {
        var raw = null;
        try { raw = sessionStorage.getItem('gps_state'); } catch (e) {}
        if (raw) {
            try {
                var st = JSON.parse(raw);
                if (st && st.lat && st.lng && st.ts && (Date.now() - st.ts) < 120000) {
                    lastLat = st.lat;
                    lastLng = st.lng;
                    lastTs  = st.ts;
                    _firstTick = false;  // ya tenemos posición reciente → alta precisión desde el inicio
                }
            } catch (e) {}
        }
    })();

    function tick() {
        if (gpsStopped || gpsPending) return;
        if (document.hidden) return;
        gpsPending = true;

        var useHighAcc = isCarDevice ? true : !_firstTick;  // coche: siempre alta precisión (chip GPS)
        if (_firstTick) _firstTick = false;

        navigator.geolocation.getCurrentPosition(function (pos) {
            gpsPending = false;
            var lat = pos.coords.latitude;
            var lng = pos.coords.longitude;
            var now = Date.now();
            var m   = dist2D(lastLat, lastLng, lat, lng);

            // Expose position for GpsCopilot (parking, stop detection)
            window._gpsLastPos = { lat: lat, lng: lng, ts: now, accuracy: pos.coords.accuracy || 0 };
            window._gpsPrevPos = { lat: lastLat, lng: lastLng, ts: lastTs };
            window._gpsMovedMeters = m;

            if ((pos.coords.accuracy || 0) > 50) {
                if (isCarDevice && window.console) console.debug('[GPS] descartada por precisión >50m:', pos.coords.accuracy);
                return;
            }

            if (m < 20 && (now - lastTs) < 120000) return;

            lastLat = lat;
            lastLng = lng;
            lastTs  = now;

            // Update GPS coord display if overlay is open
            var gpsCoordsEl = document.getElementById('gpsCoordsDisplay');
            if (gpsCoordsEl) {
                gpsCoordsEl.textContent = lat.toFixed(5) + ', ' + lng.toFixed(5);
            }

            // Feed position to GpsRadar if active
            if (window.GpsRadar && window.GpsRadar.running) {
                window.GpsRadar.updatePosition(lat, lng, pos.coords.accuracy || 0, now);
            }

            // Persistir para sobrevivir recargas de página (SW updates, navegación)
            try { sessionStorage.setItem('gps_state', JSON.stringify({lat: lat, lng: lng, ts: now})); } catch (e) {}

            _sendGps(lat, lng, pos.coords.accuracy || 0);
        }, function (err) {
            gpsPending = false;
            // PERMISSION_DENIED (code 1): el usuario dijo que no → PARAR para siempre
            if (err && err.code === 1) {
                gpsStopped = true;
                if (gpsTimer) { clearInterval(gpsTimer); gpsTimer = null; }
                return;
            }
            // Android 8.1 overlay bug: "ask for your permissions" / "bubble"
            if (err && err.message && /bubble|overlay|ask for your permissions|permission/i.test(err.message)) {
                gpsStopped = true;
                if (gpsTimer) { clearInterval(gpsTimer); gpsTimer = null; }
                return;
            }
            // code 2 (POSITION_UNAVAILABLE) o 3 (TIMEOUT): error temporal → reintentar
        }, {
            enableHighAccuracy: useHighAcc,
            maximumAge: maxAge,
            timeout: timeout
        });
    }

    function startGps() {
        if (gpsStarted || gpsStopped) return;
        gpsStarted = true;
        tick();                           // primer poll inmediato (bajo demanda)
        gpsTimer = setInterval(tick, interval);
    }

    // ── Modo coche (tiempo real): watchPosition continuo ────────────────
    // El chip GPS queda encendido. El navegador dispara el callback
    // automáticamente cada 1-5s al detectar movimiento. Throttle de envío
    // al servidor: mínimo 5s entre envíos, o más de 20m recorridos.
    function startWatchGps() {
        if (gpsStarted || gpsStopped) return;
        gpsStarted = true;

        watchId = navigator.geolocation.watchPosition(function (pos) {
            var lat = pos.coords.latitude;
            var lng = pos.coords.longitude;
            var now = Date.now();
            var acc = pos.coords.accuracy || 0;

            // ── Exponer siempre para GpsCopilot (parking, stop, radar) ──
            window._gpsLastPos = { lat: lat, lng: lng, ts: now, accuracy: acc };

            // Update GPS coord display if overlay is open
            var gpsCoordsEl = document.getElementById('gpsCoordsDisplay');
            if (gpsCoordsEl) {
                gpsCoordsEl.textContent = lat.toFixed(5) + ', ' + lng.toFixed(5);
            }

            // Feed position to GpsRadar if active
            if (window.GpsRadar && window.GpsRadar.running) {
                window.GpsRadar.updatePosition(lat, lng, acc, now);
            }

            // ── Filtrar por precisión ──
            if (acc > 50) {
                if (window.console) console.debug('[GPS] descartada por precisión >50m:', acc);
                return;
            }

            // ── Movimiento real entre fixes (para GpsCopilot) ──
            var m = (_fixLat !== 0 || _fixLng !== 0) ? dist2D(_fixLat, _fixLng, lat, lng) : 999;
            window._gpsPrevPos = { lat: _fixLat, lng: _fixLng, ts: _fixTs };
            window._gpsMovedMeters = m;

            // ── Actualizar tracker de fix (independiente del throttle) ──
            _fixLat = lat; _fixLng = lng; _fixTs = now;

            // ── Throttle de envío: 5s mínimo O más de 20m movidos ──
            if (m < 20 && (now - _lastSentTs) < 5000) return;

            // ── Enviar al servidor ──
            lastLat = lat; lastLng = lng; lastTs = now;
            _lastSentTs = now;

            try { sessionStorage.setItem('gps_state', JSON.stringify({lat: lat, lng: lng, ts: now})); } catch (e) {}
            _sendGps(lat, lng, acc);
        }, function (err) {
            // PERMISSION_DENIED (code 1): el usuario dijo que no → PARAR
            if (err && err.code === 1) {
                gpsStopped = true;
                if (watchId !== null) { navigator.geolocation.clearWatch(watchId); watchId = null; }
                return;
            }
            // Android 8.1 overlay bug
            if (err && err.message && /bubble|overlay|ask for your permissions|permission/i.test(err.message)) {
                gpsStopped = true;
                if (watchId !== null) { navigator.geolocation.clearWatch(watchId); watchId = null; }
                return;
            }
            // code 2 (POSITION_UNAVAILABLE) o 3 (TIMEOUT): watchPosition auto-reintenta
        }, {
            enableHighAccuracy: true,
            maximumAge: 0,
            timeout: 30000
        });
    }

    // ── Arranque diferido ─────────────────────────────────────────
    // Coche real (isCarDevice): watchPosition en tiempo real.
    // Preview / móvil: polling con getCurrentPosition.
    if (iLite === '90') {
        // Modo coche: GPS bajo demanda del usuario (toque/click/scroll)
        var _gpsDeferred = function () {
            isCarDevice ? startWatchGps() : startGps();
            document.removeEventListener('click', _gpsDeferred, true);
            document.removeEventListener('touchstart', _gpsDeferred, true);
            document.removeEventListener('scroll', _gpsDeferred, true);
        };
        document.addEventListener('click', _gpsDeferred, true);
        document.addEventListener('touchstart', _gpsDeferred, true);
        document.addEventListener('scroll', _gpsDeferred, true);
        // Fallback: si el usuario no interactúa en 60s, arrancar igual
        setTimeout(function () {
            if (!gpsStarted) isCarDevice ? startWatchGps() : startGps();
        }, 60000);
    } else {
        // Modo normal: diferir 5s para no bloquear el arranque
        setTimeout(startGps, 5000);
    }
})();

// ═══ Rutas Map (Leaflet lazy load) ════════════════════════════════════════════
// Se activa cuando la pestaña Rutas de Josue inyecta window._rutasMapData

window._loadRutasMap = function () {
    var data = window._rutasMapData;
    if (!data || !data.points || !data.points.length) return;

    // Evitar doble inicialización
    if (window._rutasMapLoaded) return;
    window._rutasMapLoaded = true;

    var container = document.getElementById('rutasMap');
    if (!container) return;

    // Guardar datos del día completo para restore
    window._rutasMapFullData = data;

    // Lazy load Leaflet CSS
    if (!document.getElementById('leaflet-css')) {
        var lc = document.createElement('link');
        lc.id = 'leaflet-css';
        lc.rel = 'stylesheet';
        lc.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
        lc.integrity = 'sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=';
        lc.crossOrigin = '';
        document.head.appendChild(lc);
    }

    function initMap() {
        var points = data.points;

        // Centro: media de todas las posiciones o el centro proporcionado
        var clat = data.center ? data.center.lat : points[0].lat;
        var clng = data.center ? data.center.lng : points[0].lng;

        var map = L.map('rutasMap', {
            attributionControl: false,
            zoomControl: true
        }).setView([clat, clng], 14);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a>',
            maxZoom: 19
        }).addTo(map);

        // Guardar referencia global al mapa
        window._rutasMap = map;
        // Grupo para los marcadores/trayectos (para limpiar fácilmente en modo trip)
        window._rutasMapDayLayer = L.featureGroup().addTo(map);

        // Marcadores
        var markers = [];
        var latlngs = [];

        points.forEach(function (p, i) {
            var latlng = [p.lat, p.lng];
            latlngs.push(latlng);

            var popupHtml = '<b>' + p.time + '</b><br>' +
                'Precision: ' + (p.acc || '?') + 'm<br>' +
                '<a href="https://www.google.com/maps?q=' + p.lat + ',' + p.lng + '" target="_blank" rel="noopener">📍 Ver en Google Maps</a>';

            var marker = L.circleMarker(latlng, {
                radius: (i === 0) ? 7 : (i === points.length - 1) ? 7 : 5,
                fillColor: (i === 0) ? '#22c55e' : (i === points.length - 1) ? '#e83e8c' : '#f59e0b',
                color: '#fff',
                weight: 2,
                opacity: 1,
                fillOpacity: 0.9
            }).bindPopup(popupHtml);

            marker.addTo(window._rutasMapDayLayer);
            markers.push(marker);
        });

        // Punto del día anterior (si existe, para continuidad visual)
        if (data.prevPoint) {
            L.circleMarker([data.prevPoint.lat, data.prevPoint.lng], {
                radius: 6,
                fillColor: '#64748b',
                color: '#fff',
                weight: 1.5,
                opacity: 0.7,
                fillOpacity: 0.7
            }).bindPopup('Última posición del día anterior').addTo(window._rutasMapDayLayer);
        }

        // Línea de ruta (polyline)
        if (latlngs.length > 1) {
            L.polyline(latlngs, {
                color: '#e83e8c',
                weight: 3,
                opacity: 0.7,
                smoothFactor: 1
            }).addTo(window._rutasMapDayLayer);
        }

        // Si hay un solo punto, círculo de precisión
        if (points.length === 1 && points[0].acc > 0) {
            L.circle([points[0].lat, points[0].lng], {
                radius: points[0].acc,
                color: '#3b82f6',
                fillColor: '#3b82f6',
                fillOpacity: 0.1,
                weight: 1
            }).addTo(window._rutasMapDayLayer);
        }

        // Ajustar vista para mostrar todos los puntos
        if (latlngs.length > 1) {
            try {
                map.fitBounds(L.latLngBounds(latlngs).pad(0.15));
            } catch (e) { /* ignorar si falla fitBounds */ }
        }

        // Forzar redibujado después de que el mapa sea visible (soluciona tiles grises)
        setTimeout(function () { map.invalidateSize(); }, 200);
    }

    // Lazy load Leaflet JS
    if (window.L) {
        initMap();
    } else {
        var ls = document.createElement('script');
        ls.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
        ls.integrity = 'sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=';
        ls.crossOrigin = '';
        ls.onload = initMap;
        document.head.appendChild(ls);
    }
};

// ── Navegación del mapa: centrar en un punto ──
window._rutasMapGoTo = function (lat, lng, zoom) {
    var map = window._rutasMap;
    if (!map) {
        // Scroll al mapa por si no está visible
        var el = document.getElementById('rutasMap');
        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }
    _rutasMapRestoreDay();
    map.setView([lat, lng], zoom || 16, { animate: true });
    // Mostrar un marcador temporal
    if (window._rutasMapPopupMarker) {
        window._rutasMapDayLayer.removeLayer(window._rutasMapPopupMarker);
    }
    window._rutasMapPopupMarker = L.circleMarker([lat, lng], {
        radius: 9,
        fillColor: '#f59e0b',
        color: '#fff',
        weight: 3,
        opacity: 1,
        fillOpacity: 0.9
    }).addTo(window._rutasMapDayLayer).bindPopup(lat + ', ' + lng).openPopup();
    // Scroll al mapa
    var mapEl = document.getElementById('rutasMap');
    if (mapEl) mapEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
};

// ── Mostrar trayecto en el mapa ──
window._rutasMapShowTrip = function (tripData) {
    var map = window._rutasMap;
    if (!map) return;

    // Limpiar capa del día y mostrar solo el trayecto
    window._rutasMapDayLayer.clearLayers();

    var startLatlng = [tripData.startLat, tripData.startLng];
    var endLatlng   = [tripData.endLat, tripData.endLng];

    // Marcador inicio (verde)
    L.circleMarker(startLatlng, {
        radius: 9,
        fillColor: '#22c55e',
        color: '#fff',
        weight: 3,
        opacity: 1,
        fillOpacity: 0.9
    }).bindPopup('Inicio: ' + tripData.label).addTo(window._rutasMapDayLayer);

    // Marcador fin (rosa)
    L.circleMarker(endLatlng, {
        radius: 9,
        fillColor: '#e83e8c',
        color: '#fff',
        weight: 3,
        opacity: 1,
        fillOpacity: 0.9
    }).bindPopup('Fin: ' + tripData.label).addTo(window._rutasMapDayLayer);

    // Polilínea de la ruta
    var latlngs = [];
    if (tripData.points && tripData.points.length > 0) {
        tripData.points.forEach(function (p) { latlngs.push([p.lat, p.lng]); });
    } else {
        latlngs = [startLatlng, endLatlng];
    }

    if (latlngs.length > 1) {
        L.polyline(latlngs, {
            color: '#e83e8c',
            weight: 4,
            opacity: 0.85,
            smoothFactor: 1
        }).addTo(window._rutasMapDayLayer);
    }

    // Ajustar vista al trayecto
    try {
        map.fitBounds(L.latLngBounds(latlngs).pad(0.2));
    } catch (e) { map.setView(startLatlng, 15); }

    // Mostrar barra de navegación
    var nav = document.getElementById('rutasMapNav');
    var navLabel = document.getElementById('rutasMapNavLabel');
    if (nav) nav.style.display = 'flex';
    if (navLabel) navLabel.textContent = tripData.label || 'Trayecto';

    // Scroll al mapa
    var mapEl = document.getElementById('rutasMap');
    if (mapEl) mapEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
};

// ── Volver a la vista completa del día ──
window._rutasMapReset = function () {
    _rutasMapRestoreDay();
    var nav = document.getElementById('rutasMapNav');
    if (nav) nav.style.display = 'none';
};

// ── Restaurar capa del día completo ──
function _rutasMapRestoreDay() {
    var map = window._rutasMap;
    var data = window._rutasMapFullData;
    if (!map || !data || !data.points || !data.points.length) return;

    window._rutasMapDayLayer.clearLayers();

    var points = data.points;
    var latlngs = [];

    points.forEach(function (p, i) {
        var latlng = [p.lat, p.lng];
        latlngs.push(latlng);

        var popupHtml = '<b>' + p.time + '</b><br>' +
            'Precision: ' + (p.acc || '?') + 'm<br>' +
            '<a href="https://www.google.com/maps?q=' + p.lat + ',' + p.lng + '" target="_blank" rel="noopener">📍 Ver en Google Maps</a>';

        L.circleMarker(latlng, {
            radius: (i === 0) ? 7 : (i === points.length - 1) ? 7 : 5,
            fillColor: (i === 0) ? '#22c55e' : (i === points.length - 1) ? '#e83e8c' : '#f59e0b',
            color: '#fff',
            weight: 2,
            opacity: 1,
            fillOpacity: 0.9
        }).bindPopup(popupHtml).addTo(window._rutasMapDayLayer);
    });

    if (data.prevPoint) {
        L.circleMarker([data.prevPoint.lat, data.prevPoint.lng], {
            radius: 6,
            fillColor: '#64748b',
            color: '#fff',
            weight: 1.5,
            opacity: 0.7,
            fillOpacity: 0.7
        }).bindPopup('Última posición del día anterior').addTo(window._rutasMapDayLayer);
    }

    if (latlngs.length > 1) {
        L.polyline(latlngs, {
            color: '#e83e8c',
            weight: 3,
            opacity: 0.7,
            smoothFactor: 1
        }).addTo(window._rutasMapDayLayer);
    }

    if (points.length === 1 && points[0].acc > 0) {
        L.circle([points[0].lat, points[0].lng], {
            radius: points[0].acc,
            color: '#3b82f6',
            fillColor: '#3b82f6',
            fillOpacity: 0.1,
            weight: 1
        }).addTo(window._rutasMapDayLayer);
    }

    if (latlngs.length > 1) {
        try { map.fitBounds(L.latLngBounds(latlngs).pad(0.15)); } catch (e) {}
    }

    window._rutasMapPopupMarker = null;
}

// Si los datos ya se inyectaron (script inline después del HTML del mapa), iniciar ya
if (window._rutasMapData && window._rutasMapData.points && window._rutasMapData.points.length) {
    window._loadRutasMap();
}

// =============================================================================
// JOSUE REPRODUCTOR: Fullscreen toggle + Lite sidebar panel
// =============================================================================
(function () {
    function toggleJosueFS(mode) {
        var isLite = document.body.classList.contains('is-lite');
        var cls = isLite ? 'yt-fs-video' : 'josue-yt-fs';
        if (typeof mode !== 'boolean') {
            mode = !document.body.classList.contains(cls);
        }
        if (mode) {
            document.body.classList.add(cls);
        } else {
            document.body.classList.remove(cls);
        }
        // Sync iframe pointer-events based on video-expanded state
        _syncVideoOverlay();
    }

    // In non-expanded mode: set iframe pointer-events:none so transport buttons control playback.
    // In video-expanded mode: restore pointer-events so user can tap the big video to pause/resume.
    function _syncVideoOverlay() {
        var isLite = document.body.classList.contains('is-lite');
        if (!isLite) return;
        var isVideoFS = document.body.classList.contains('yt-fs-video');
        var container = document.getElementById('youtubePlayerContainer');
        if (!container) return;
        var iframe = container.querySelector('iframe');
        if (!iframe) return;
        iframe.style.pointerEvents = isVideoFS ? 'auto' : 'none';
    }

    // Ensure iframe pointer-events synced on lite page load.
    // The iframe may not exist yet, so retry on first user interaction.
    if (document.body.classList.contains('is-lite')) {
        _syncVideoOverlay();
    }

    // ☀️ Restaurar sunlight mode desde localStorage (solo Lite)
    if (document.body.classList.contains('is-lite') && localStorage.getItem('yt_sunlight_mode') === '1') {
        var reproductor = document.getElementById('youtubeReproductor');
        if (reproductor) reproductor.classList.add('yt-sunlight');
    }

    // Lite sidebar toggle / close helpers
    function liteToggleSidebar() {
        var sidebar = document.getElementById('ytRadioSidebar');
        var overlay = document.getElementById('ytRadioSidebarOverlay');
        if (!sidebar) return;
        var isOpen = sidebar.classList.contains('open');
        // Close GPS if sidebar is opening
        if (!isOpen) liteCloseGps();
        if (isOpen) {
            sidebar.classList.remove('open');
            if (overlay) overlay.classList.remove('visible');
        } else {
            sidebar.classList.add('open');
            if (overlay) overlay.classList.add('visible');
        }
    }

    function liteCloseSidebar() {
        var sidebar = document.getElementById('ytRadioSidebar');
        var overlay = document.getElementById('ytRadioSidebarOverlay');
        if (sidebar) sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('visible');
    }

    function liteTogglePanel(panelId) {
        var panel = document.getElementById(panelId);
        var overlay = document.getElementById('ytRadioSidebarOverlay');
        if (!panel) return;
        var isOpen = panel.classList.contains('open');
        // Close GPS if any panel is opening
        if (!isOpen) liteCloseGps();
        // Cerrar otros paneles y sidebar
        liteCloseSidebar();
        liteClosePanel('presintoniasPanel');
        liteClosePanel('radiosPanel');
        if (isOpen) {
            panel.classList.remove('open');
            if (overlay) overlay.classList.remove('visible');
        } else {
            panel.classList.add('open');
            if (overlay) overlay.classList.add('visible');
        }
    }

    function liteClosePanel(panelId) {
        var panel = document.getElementById(panelId);
        var overlay = document.getElementById('ytRadioSidebarOverlay');
        if (panel) panel.classList.remove('open');
        // Solo ocultar overlay si ningún panel/sidebar está abierto
        var sidebar = document.getElementById('ytRadioSidebar');
        var pres = document.getElementById('presintoniasPanel');
        var rad = document.getElementById('radiosPanel');
        var anyOpen = (sidebar && sidebar.classList.contains('open'))
            || (pres && pres.classList.contains('open'))
            || (rad && rad.classList.contains('open'));
        if (!anyOpen && overlay) overlay.classList.remove('visible');
    }

    // ── GPS Navigation overlay ──
    var _gpsRadarInited = false;

    function liteOpenGps() {
        // Close other panels/sidebar first to avoid z-index conflicts
        liteCloseSidebar();
        liteClosePanel('presintoniasPanel');
        liteClosePanel('radiosPanel');
        var overlay = document.getElementById('gpsOverlay');
        if (overlay) {
            overlay.classList.add('open');
            // Init radar on first open
            if (!_gpsRadarInited && window.GpsRadar) {
                window.GpsRadar.init('gpsRadarCanvas');
                _gpsRadarInited = true;
            }
            // Start radar animation
            if (window.GpsRadar) {
                window.GpsRadar.start();
            }
            // Feed current GPS position if available
            if (window._gpsLastPos && window.GpsRadar) {
                window.GpsRadar.updatePosition(
                    window._gpsLastPos.lat,
                    window._gpsLastPos.lng,
                    window._gpsLastPos.accuracy,
                    window._gpsLastPos.ts
                );
            }

            // Fallback: one-shot geolocation on first open if no position yet
            // (e.g. preview via ?lite=1 on desktop/mobile; tracking starts deferred
            //  after first touch so position may not have arrived yet)
            if (window.GpsRadar && !window._gpsLastPos && navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function (pos) {
                    window.GpsRadar.updatePosition(
                        pos.coords.latitude,
                        pos.coords.longitude,
                        pos.coords.accuracy,
                        Date.now()
                    );
                }, function () {}, { enableHighAccuracy: true, maximumAge: 0, timeout: 8000 });
            }
            // Update coordinates display if we have a recent GPS position
            _gpsUpdateCoordsDisplay();
        }
    }

    function liteCloseGps() {
        var overlay = document.getElementById('gpsOverlay');
        if (overlay) overlay.classList.remove('open');
        // Stop radar animation
        if (window.GpsRadar) window.GpsRadar.stop();
    }

    function liteToggleGps() {
        var overlay = document.getElementById('gpsOverlay');
        if (!overlay) return;
        if (overlay.classList.contains('open')) {
            liteCloseGps();
        } else {
            liteOpenGps();
        }
    }

    function _gpsUpdateCoordsDisplay() {
        var el = document.getElementById('gpsCoordsDisplay');
        if (!el) return;
        var pos = window._gpsLastPos;
        if (pos && pos.lat && pos.lng) {
            el.textContent = pos.lat.toFixed(5) + ', ' + pos.lng.toFixed(5);
        } else {
            el.textContent = '--';
        }
    }

    // Crear overlay si no existe (solo en lite, lazy)
    var _sidebarsMoved = false;
    function liteEnsureOverlay() {
        // Mover sidebars fuera del overflow container del reproductor para
        // evitar que el overflow-y:auto del #youtubeReproductor interfiera
        // con el hit-testing de position:fixed en Chrome 95 WebView.
        if (!_sidebarsMoved && document.body.classList.contains('is-lite')) {
            _sidebarsMoved = true;
            ['ytRadioSidebar', 'presintoniasPanel', 'radiosPanel'].forEach(function(id) {
                var el = document.getElementById(id);
                if (el && el.parentNode !== document.body) {
                    document.body.appendChild(el);
                }
            });
        }
        if (document.getElementById('ytRadioSidebarOverlay')) return;
        if (!document.body.classList.contains('is-lite')) return;
        var ov = document.createElement('div');
        ov.id = 'ytRadioSidebarOverlay';
        ov.className = 'yt-radio-sidebar-overlay';
        ov.addEventListener('click', function () {
            liteCloseSidebar();
            liteClosePanel('presintoniasPanel');
            liteClosePanel('radiosPanel');
        });
        // Siempre appendear a document.body para evitar problemas de
        // containing block / stacking context con el overflow del reproductor
        document.body.appendChild(ov);
    }

    // ── KITT Car Button: split-tap zones (deferred to after DOM fully settled) ──
    var _kittBtn = document.getElementById('ytJefryChatStart');
    if (_kittBtn) {
        _kittBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            e.preventDefault();
            var rect = this.getBoundingClientRect();
            var clickX = e.clientX - rect.left;
            var halfW = rect.width / 2;
            var isLeft = clickX < halfW;
            console.log('[KITT] btn click x=' + Math.round(clickX) + ' half=' + Math.round(halfW) + ' side=' + (isLeft ? 'LEFT' : 'RIGHT'));
            // Visual flash feedback
            this.classList.add('kitt-btn-flash');
            var self = this;
            setTimeout(function () { self.classList.remove('kitt-btn-flash'); }, 250);
            if (isLeft) {
                // Left half → Jefry voice activation + stop melody if playing
                console.log('[KITT] left tap → Jefry voice');
                if (typeof window.KittPlayer !== 'undefined') window.KittPlayer.stop();
                var vb = document.getElementById('voiceStartButton');
                if (vb) { vb.click(); console.log('[KITT] voiceStartButton clicked'); }
                else { console.warn('[KITT] voiceStartButton not found'); }
            } else {
                // Right half → toggle Knight Rider melody
                console.log('[KITT] right tap → toggle melody');
                if (typeof window.KittPlayer !== 'undefined') {
                    if (window.KittPlayer.isPlaying()) {
                        console.log('[KITT] stopping melody');
                        window.KittPlayer.stop();
                    } else {
                        console.log('[KITT] starting melody');
                        window.KittPlayer.play();
                    }
                } else {
                    console.warn('[KITT] KittPlayer not defined');
                }
            }
        });
        console.log('[KITT] button listener attached to #ytJefryChatStart');
    } else {
        console.warn('[KITT] #ytJefryChatStart NOT FOUND in DOM');
    }

    // ── Fix: handlers directos en cada botón del menu-bank (bypass del document listener) ──
    //    Así evitamos cualquier interceptación de eventos por overlays/iframes

    // Mini debug click indicator (definido antes que _bindMenuBtn para ver binds)
    window._liteBindCount = 0;
    window._liteBindTotal = 4; // PRES, GPS, RAD, BIB (CAR tiene su propio handler)
    window._liteLastClick = function (id, keep) {
        var el = document.getElementById('_liteClickDbg');
        if (!el && document.body) {
            el = document.createElement('div');
            el.id = '_liteClickDbg';
            el.style.cssText = 'position:fixed;bottom:40px;right:4px;z-index:9999999;background:rgba(0,0,0,.85);color:#0f0;font:9px monospace;padding:2px 6px;border-radius:3px;pointer-events:none;opacity:0;transition:opacity .15s';
            document.body.appendChild(el);
        }
        if (el) {
            var diagExtra = (typeof window._getDiagInfo === 'function') ? ' | ' + window._getDiagInfo() : '';
            el.textContent = '✅ ' + id + ' [' + window._liteBindCount + '/' + window._liteBindTotal + '] ' + new Date().toISOString().slice(11, 19) + diagExtra;
            el.style.opacity = '1';
            clearTimeout(el._t);
            el._t = setTimeout(function () { if (!keep) el.style.opacity = '0'; }, keep ? 5000 : 1800);
        }
    };

    function _bindMenuBtn(btnId, fn) {
        var btn = document.getElementById(btnId);
        if (btn) {
            console.log('[BIND] Handler attached to #' + btnId);
            window._liteBindCount++;
            if (typeof window._liteLastClick === 'function') {
                window._liteLastClick('BIND:' + btnId.replace('yt','').replace('Toggle','').slice(0,12), window._liteBindCount >= window._liteBindTotal);
            }
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                e.preventDefault();
                if (typeof window._liteLastClick === 'function') window._liteLastClick(btnId);
                fn();
            });
        } else {
            console.warn('[BIND] ⚠️ Button NOT FOUND: #' + btnId);
            if (typeof window._liteLastClick === 'function') {
                window._liteLastClick('MISS:' + btnId.replace('yt','').replace('Toggle','').slice(0,10));
            }
        }
    }

    _bindMenuBtn('ytRadioPresintoniasToggle', function () {
        SfxPlayer.menuClick();
        liteEnsureOverlay();
        liteTogglePanel('presintoniasPanel');
        var pp = document.getElementById('presintoniasPanel');
        if (pp && pp.classList.contains('open')) SfxPlayer.sidebarOpen();
        else SfxPlayer.sidebarClose();
    });

    _bindMenuBtn('ytGpsBtn', function () {
        SfxPlayer.menuClick();
        liteToggleGps();
        var go = document.getElementById('gpsOverlay');
        if (go && go.classList.contains('open')) SfxPlayer.sidebarOpen();
        else SfxPlayer.sidebarClose();
    });

    _bindMenuBtn('ytRadioRadiosToggle', function () {
        SfxPlayer.menuClick();
        liteEnsureOverlay();
        liteTogglePanel('radiosPanel');
        var rp = document.getElementById('radiosPanel');
        if (rp && rp.classList.contains('open')) SfxPlayer.sidebarOpen();
        else SfxPlayer.sidebarClose();
    });

    _bindMenuBtn('ytRadioSidebarToggle', function () {
        SfxPlayer.menuClick();
        liteEnsureOverlay();
        liteToggleSidebar();
        var s = document.getElementById('ytRadioSidebar');
        if (s && s.classList.contains('open')) SfxPlayer.sidebarOpen();
        else SfxPlayer.sidebarClose();
    });

    // El document listener sigue existiendo para otros botones (close, backdrop, etc.)
    // pero los botones del menu-bank ya tienen su propio handler directo arriba

    // ── DIAG: elementFromPoint en cada click para detectar qué tapa los botones ──
    document.addEventListener('click', function (e) {
        var el = document.elementFromPoint(e.clientX, e.clientY);
        if (el) {
            var id = el.id || '';
            var cls = (el.className && typeof el.className === 'string') ? el.className.split(' ').slice(0, 2).join('.') : '';
            var tag = el.tagName.toLowerCase();
            var info = tag + (id ? '#' + id : '') + (cls ? '.' + cls : '');
            // Solo mostrar si NO es un botón del menu-bank (si es botón, el handler de _bindMenuBtn ya muestra)
            if (!el.closest('#ytJefryChatStart, #ytRadioPresintoniasToggle, #ytGpsBtn, #ytRadioRadiosToggle, #ytRadioSidebarToggle')) {
                var nearMenu = el.closest('.yt-menu-bank');
                if (nearMenu || el.closest('#ytRadioSidebar')) {
                    if (typeof window._liteLastClick === 'function') {
                        window._liteLastClick('TAP→' + info.slice(0, 35) + (nearMenu ? ' (menu)' : ''));
                    }
                }
            }
        }
    }, true); // capture phase
    document.addEventListener('click', function (e) {
        // ── KITT global dismiss: any tap stops melody + effects ──
        if (typeof window.KittPlayer !== 'undefined' && window.KittPlayer.isPlaying()) {
            window.KittPlayer.stop();
        }

        var enterBtn = e.target.closest('#josueYtFsBtn');
        var exitBtn = e.target.closest('#josueYtFsBtnLite');
        var sidebarToggleBtn = e.target.closest('#ytRadioSidebarToggle');
        var sidebarCloseBtn = e.target.closest('#ytRadioSidebarClose');
        var presintoniasToggleBtn = e.target.closest('#ytRadioPresintoniasToggle');
        var presintoniasCloseBtn = e.target.closest('#presintoniasPanelClose');
        var radiosToggleBtn = e.target.closest('#ytRadioRadiosToggle');
        var radiosCloseBtn = e.target.closest('#radiosPanelClose');
        var gpsBtn = e.target.closest('#ytGpsBtn');
        var gpsCloseBtn = e.target.closest('#gpsCloseBtn');
        var gpsBackdrop = e.target.closest('#gpsBackdrop');
        var gpsSetHomeBtn = e.target.closest('#gpsSetHomeBtn');
        var gpsFireTorpedoBtn = e.target.closest('#gpsFireTorpedoBtn');
        var gpsSosBtn = e.target.closest('#gpsSosBtn');
        var gpsBitacoraBtn = e.target.closest('#gpsBitacoraBtn');

        if (enterBtn) {
            toggleJosueFS(true);
            if (document.body.classList.contains('is-lite')) {
                liteEnsureOverlay();
            }
        } else if (exitBtn) {
            // Botón ⟲ Menús — toggle fullscreen (entra/sale)
            if (document.body.classList.contains('is-lite')) {
                // Lite: toggle directly josue-yt-fs (sidebar hidden ↔ visible)
                document.body.classList.toggle('josue-yt-fs');
                // Clean up inner video-expanded state when exiting fullscreen
                if (!document.body.classList.contains('josue-yt-fs')) {
                    document.body.classList.remove('yt-fs-video');
                }
                _syncVideoOverlay();
            } else {
                toggleJosueFS(!document.body.classList.contains('josue-yt-fs'));
            }
        } else if (sidebarToggleBtn) {
            SfxPlayer.menuClick();
            liteEnsureOverlay();
            liteToggleSidebar();
            var s = document.getElementById('ytRadioSidebar');
            if (s && s.classList.contains('open')) SfxPlayer.sidebarOpen();
            else SfxPlayer.sidebarClose();
        } else if (presintoniasToggleBtn) {
            SfxPlayer.menuClick();
            liteEnsureOverlay();
            liteTogglePanel('presintoniasPanel');
            var pp = document.getElementById('presintoniasPanel');
            if (pp && pp.classList.contains('open')) SfxPlayer.sidebarOpen();
            else SfxPlayer.sidebarClose();
        } else if (radiosToggleBtn) {
            SfxPlayer.menuClick();
            liteEnsureOverlay();
            liteTogglePanel('radiosPanel');
            var rp = document.getElementById('radiosPanel');
            if (rp && rp.classList.contains('open')) SfxPlayer.sidebarOpen();
            else SfxPlayer.sidebarClose();
        } else if (sidebarCloseBtn) {
            SfxPlayer.menuClick();
            liteCloseSidebar();
            SfxPlayer.sidebarClose();
        } else if (presintoniasCloseBtn) {
            SfxPlayer.menuClick();
            liteClosePanel('presintoniasPanel');
            SfxPlayer.sidebarClose();
        } else if (radiosCloseBtn) {
            SfxPlayer.menuClick();
            liteClosePanel('radiosPanel');
            SfxPlayer.sidebarClose();
        } else if (gpsBtn) {
            SfxPlayer.menuClick();
            liteToggleGps();
            var go = document.getElementById('gpsOverlay');
            if (go && go.classList.contains('open')) SfxPlayer.sidebarOpen();
            else SfxPlayer.sidebarClose();
        } else if (gpsCloseBtn || gpsBackdrop) {
            SfxPlayer.menuClick();
            liteCloseGps();
            SfxPlayer.sidebarClose();
        } else if (gpsSetHomeBtn) {
            SfxPlayer.menuClick();
            if (window.GpsRadar) {
                var pos = window.GpsRadar.getCurrentPosition();
                if (pos) {
                    window.GpsRadar.setHome(pos.lat, pos.lng, 'Base');
                }
            }
        } else if (gpsFireTorpedoBtn) {
            SfxPlayer.menuClick();
            if (window.GpsRadar) {
                var pos = window.GpsRadar.getCurrentPosition();
                if (pos) {
                    window.GpsRadar.fireTorpedo(pos.lat, pos.lng);
                }
            }
        } else if (gpsSosBtn) {
            SfxPlayer.menuClick();
            if (window.GpsRadar) {
                var pos = window.GpsRadar.getCurrentPosition();
                if (pos) {
                    var coords = pos.lat.toFixed(6) + ', ' + pos.lng.toFixed(6);
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(coords).catch(function () {});
                    }
                    // Flash SOS visual
                    var sosBtn = document.getElementById('gpsSosBtn');
                    if (sosBtn) {
                        sosBtn.textContent = '🆘 COPIADO';
                        sosBtn.style.animation = 'sosPulse 0.3s ease';
                        sosBtn.style.backgroundColor = 'rgba(255,50,0,.3)';
                        setTimeout(function () {
                            sosBtn.textContent = '🆘 SOS';
                            sosBtn.style.animation = '';
                            sosBtn.style.backgroundColor = '';
                        }, 2000);
                    }
                }
            }
        } else if (gpsBitacoraBtn) {
            SfxPlayer.menuClick();
            // Bitácora placeholder — will be implemented in Phase 2
            if (window.GpsRadar) {
                var pos = window.GpsRadar.getCurrentPosition();
                if (pos) {
                    var coords = pos.lat.toFixed(5) + ', ' + pos.lng.toFixed(5);
                    alert('Bitácora de inmersiones\n\nPróximamente.\n\nÚltima posición:\n' + coords);
                } else {
                    alert('Bitácora de inmersiones\n\nPróximamente.');
                }
            }
        }

        // ☀️ Sunlight mode toggle (Lite only)
        var sunlightBtn = e.target.closest('#ytSunlightToggle');
        if (sunlightBtn) {
            var reproductor = document.getElementById('youtubeReproductor');
            if (reproductor) {
                var wasSunlight = reproductor.classList.contains('yt-sunlight');
                if (wasSunlight) {
                    reproductor.classList.remove('yt-sunlight');
                    localStorage.setItem('yt_sunlight_mode', '0');
                } else {
                    reproductor.classList.add('yt-sunlight');
                    localStorage.setItem('yt_sunlight_mode', '1');
                }
            }
            return;
        }

        // Lite: click on video player area → enter video-expanded mode
        var videoPlayerTap = e.target.closest('#youtubeMiniPlayer');
        if (videoPlayerTap && document.body.classList.contains('is-lite') && !document.body.classList.contains('yt-fs-video')) {
            toggleJosueFS(true);
            liteEnsureOverlay();
            return;
        }

        // Lite: click close button → exit fullscreen
        var fsCloseBtn = e.target.closest('#ytLiteFsClose');
        if (fsCloseBtn && document.body.classList.contains('is-lite')) {
            toggleJosueFS(false);
            return;
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            var isLite = document.body.classList.contains('is-lite');
            var isDesktopFS = document.body.classList.contains('josue-yt-fs');
            var isVideoFS = document.body.classList.contains('yt-fs-video');
            var presPanel = document.getElementById('presintoniasPanel');
            var radPanel = document.getElementById('radiosPanel');
            var sidebar = document.getElementById('ytRadioSidebar');
            var radOpen = radPanel && radPanel.classList.contains('open');
            var presOpen = presPanel && presPanel.classList.contains('open');
            var sidebarOpen = sidebar && sidebar.classList.contains('open');
            var gpsOverlay = document.getElementById('gpsOverlay');
            var gpsOpen = gpsOverlay && gpsOverlay.classList.contains('open');
            if (radOpen) {
                liteClosePanel('radiosPanel');
            } else if (presOpen) {
                liteClosePanel('presintoniasPanel');
            } else if (sidebarOpen) {
                // Cerrar sidebar primero
                liteCloseSidebar();
            } else if (gpsOpen) {
                // Cerrar GPS
                liteCloseGps();
            } else if (isLite && isVideoFS) {
                // En lite, Escape sale del modo video expandido
                toggleJosueFS(false);
            } else if (!isLite && isDesktopFS) {
                toggleJosueFS(false);
            }
        }
    });

    // ═══════════════════════════════════════════════════════════════════
    // VOICE COPILOT: Music pause/resume helpers (hoisted, accessible anywhere in IIFE)
    // ═══════════════════════════════════════════════════════════════════
    window._voicePausedMusic = false;
    window._voiceMusicStateBeforePause = null;

    function _voicePauseMusic() {
        if (window._voicePausedMusic) return;
        var state = { youtube: false, radio: false };

        if (typeof YTPlayer !== 'undefined' && YTPlayer.player
            && typeof YTPlayer.player.getPlayerState === 'function') {
            var ytState = YTPlayer.player.getPlayerState();
            if (ytState === 1 || ytState === 3) { // PLAYING=1, BUFFERING=3
                state.youtube = true;
                YTPlayer.player.pauseVideo();
            }
        }

        if (typeof YTPlayer !== 'undefined' && YTPlayer._radioAudioEl
            && !YTPlayer._radioAudioEl.paused) {
            state.radio = true;
            YTPlayer._radioAudioEl.pause();
        }

        if (state.youtube || state.radio) {
            window._voicePausedMusic = true;
            window._voiceMusicStateBeforePause = state;
        }
    }
    window._voicePauseMusic = _voicePauseMusic;

    function _voiceResumeMusic() {
        if (!window._voicePausedMusic) return;
        window._voicePausedMusic = false;
        var state = window._voiceMusicStateBeforePause;
        window._voiceMusicStateBeforePause = null;
        if (!state) return;

        setTimeout(function () {
            if (state.youtube && typeof YTPlayer !== 'undefined' && YTPlayer.player) {
                YTPlayer.player.playVideo();
            } else if (state.radio && typeof YTPlayer !== 'undefined' && YTPlayer._radioAudioEl) {
                YTPlayer._radioAudioEl.play().catch(function () {});
            }
        }, 600);
    }
    window._voiceResumeMusic = _voiceResumeMusic;

    // ── Voice debug telemetry helper ──
    window._voiceDebug = function (step, detail) {
        var fd = new FormData();
        fd.append('action', 'debug_voice');
        fd.append('step', step);
        fd.append('detail', (detail || '') + ' | ua:' + navigator.userAgent.substring(0, 80));
        var url = window.location.pathname;
        if (window.location.pathname.indexOf('/control/') !== -1 || window.location.pathname.indexOf('/index.php') !== -1) {
            url = window.location.pathname;
        } else {
            url = 'index.php';
        }
        try {
            fetch(url, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function () {}).catch(function () {});
        } catch (e) {}
        console.log('[VOZ DEBUG]', step, detail);
    };
    // Fire initial capability check on page load
    window.addEventListener('load', function () {
        var sr = !!(window.SpeechRecognition || window.webkitSpeechRecognition);
        window._voiceDebug('page_loaded', 'SpeechRecognition=' + sr + ' protocol=' + window.location.protocol + ' lite=' + document.body.classList.contains('is-lite'));
    });

    window._voiceInteractionActive = false;

    function _voiceOnTtsEnded() {
        window._voiceInteractionActive = false;
        if (window._voicePausedMusic) {
            window._voiceResumeMusic();
        }
    }
    window._voiceOnTtsEnded = _voiceOnTtsEnded;

    // ═══════════════════════════════════════════════════════════════════
    // VOICE PROACTIVE ENGINE — Ducking + speak + trigger checks (Fase 1)
    // ═══════════════════════════════════════════════════════════════════
    window._voiceProactive = {
        _origVol: null,
        _speaking: false,

        // Speak with ducking: lower music to 20%, speak, restore
        speak: function (text, opts) {
            opts = opts || {};
            var self = this;
            if (!text) return;

            // Duck music (lower, don't pause)
            if (opts.duckMusic !== false) {
                self._duckMusic(20);
            }

            // Use existing TTS
            if (opts.importance === 'high' && text.length > 80) {
                // Server-side TTS via OpenAI
                var fd = new FormData();
                fd.append('action', 'tts');
                fd.append('text', text);
                var fetchUrl = self._buildFetchUrl();
                fetch(fetchUrl, {
                    method: 'POST', body: fd, credentials: 'same-origin',
                    headers: { 'Accept': 'audio/mpeg', 'X-Requested-With': 'XMLHttpRequest' }
                }).then(function (res) { return res.ok ? res.blob() : null; })
                  .then(function (blob) {
                      if (!blob) { self._unduckMusic(); return; }
                      var url = URL.createObjectURL(blob);
                      var a = new Audio(url);
                      a.onended = function () { URL.revokeObjectURL(url); self._unduckMusic(); self._speaking = false; };
                      self._speaking = true;
                      a.play().catch(function () { self._unduckMusic(); });
                  }).catch(function () { self._unduckMusic(); });
            } else {
                // Browser TTS
                speechSynthesis.cancel();
                var utter = new SpeechSynthesisUtterance(text);
                // Select best voice (reuse logic from ttsSpeak if available)
                var voices = speechSynthesis.getVoices();
                var bestVoice = null;
                var pref = ['Monica', 'Helena', 'Sara', 'Paulina', 'Marisol'];
                for (var p = 0; p < pref.length && !bestVoice; p++) {
                    for (var v = 0; v < voices.length; v++) {
                        if (voices[v].lang.indexOf('es') === 0 && voices[v].name.indexOf(pref[p]) !== -1) {
                            bestVoice = voices[v]; break;
                        }
                    }
                }
                if (!bestVoice) {
                    for (var i = 0; i < voices.length; i++) {
                        if (voices[i].lang.indexOf('es') === 0) { bestVoice = voices[i]; break; }
                    }
                }
                if (bestVoice) utter.voice = bestVoice;
                utter.rate = 0.93;
                utter.pitch = 1.0;
                utter.volume = 1.0;
                var selfRef = self;
                utter.onstart = function () { selfRef._speaking = true; };
                utter.onend = function () { selfRef._speaking = false; selfRef._unduckMusic(); };
                utter.onerror = function () { selfRef._speaking = false; selfRef._unduckMusic(); };
                speechSynthesis.speak(utter);
            }
        },

        _duckMusic: function (percent) {
            var self = this;
            if (typeof YTPlayer !== 'undefined' && YTPlayer.player
                && typeof YTPlayer.player.getVolume === 'function') {
                if (self._origVol === null) {
                    self._origVol = YTPlayer.player.getVolume();
                }
                YTPlayer.player.setVolume(Math.max(1, Math.round(self._origVol * percent / 100)));
            }
        },

        _unduckMusic: function () {
            var self = this;
            if (self._origVol !== null && typeof YTPlayer !== 'undefined' && YTPlayer.player
                && typeof YTPlayer.player.setVolume === 'function') {
                YTPlayer.player.setVolume(self._origVol);
                self._origVol = null;
            }
        },

        _buildFetchUrl: function () {
            var sp = new URLSearchParams(window.location.search);
            sp.delete('action');
            return 'index.php' + (sp.toString() ? '?' + sp.toString() : '');
        }
    };

    // ── Proactive trigger checks (run on page load + periodically) ──
    window._voiceProactiveChecks = {
        _lastMorning: null,
        _lastEvening: null,
        _lastCelebration: null,
        _lastPhrase: 0,
        _lastCampaignCheck: 0,
        _lastDiaryMorning: null,
        _lastDiaryWorryNag: 0,
        _lastDiaryIdeaCheck: 0,
        _lastDiaryDecisionCheck: 0,
        _lastDiaryMoodCheck: 0,
        _lastDiaryCompile: null,
        _spokenToday: {},    // phraseId -> true, reset daily
        _dailyInteractionCount: 0,

        init: function () {
            var self = this;
            self._lastMorning = localStorage.getItem('jefry_last_morning') || '';
            self._lastEvening = localStorage.getItem('jefry_last_evening') || '';
            self._lastCelebration = localStorage.getItem('jefry_last_celebration') || '';
            self._lastPhrase = parseInt(localStorage.getItem('jefry_last_phrase_ts') || '0', 10);
            self._lastDiaryMorning = localStorage.getItem('jefry_last_diary_morning') || '';
            self._lastDiaryWorryNag = parseInt(localStorage.getItem('jefry_last_diary_worry_ts') || '0', 10);
            self._lastDiaryIdeaCheck = parseInt(localStorage.getItem('jefry_last_diary_idea_ts') || '0', 10);
            self._lastDiaryDecisionCheck = parseInt(localStorage.getItem('jefry_last_diary_decision_ts') || '0', 10);
            self._lastDiaryMoodCheck = parseInt(localStorage.getItem('jefry_last_diary_mood_ts') || '0', 10);
            self._lastDiaryCompile = localStorage.getItem('jefry_last_diary_compile') || '';
            self._dailyInteractionCount = parseInt(localStorage.getItem('jefry_daily_interactions') || '0', 10);
        },

        runAll: function () {
            var now = new Date();
            var today = now.toISOString().slice(0, 10);
            var hour = now.getHours();

            // Reset daily tracking if new day
            if (localStorage.getItem('jefry_today_date') !== today) {
                localStorage.setItem('jefry_today_date', today);
                localStorage.setItem('jefry_daily_interactions', '0');
                localStorage.setItem('jefry_spoken_phrases', '{}');
                this._dailyInteractionCount = 0;
                this._spokenToday = {};
            }

            // Morning greeting (6-12h, first load of the day) — respect silent mode
            if (hour >= 6 && hour <= 12 && this._lastMorning !== today && this.shouldBeProactive()) {
                this._lastMorning = today;
                localStorage.setItem('jefry_last_morning', today);
                this._fetchAndSpeak('morning_greeting', { duckMusic: true, importance: 'high' });
                // Diary morning — si hay entrada de ayer, Jefry la menciona
                if (this._lastDiaryMorning !== today) {
                    this._lastDiaryMorning = today;
                    localStorage.setItem('jefry_last_diary_morning', today);
                    setTimeout(function () { self._fetchAndSpeak('diary_morning', { duckMusic: true, importance: 'medium' }); }, 5000);
                }
                return;
            }

            // Evening wrapup (>20h, only if has been active today)
            if (hour >= 20 && this._lastEvening !== today) {
                this._lastEvening = today;
                localStorage.setItem('jefry_last_evening', today);
                this._fetchAndSpeak('evening_wrapup', { duckMusic: true, importance: 'high' });
                // Diary compile — compilar entrada del día
                if (this._lastDiaryCompile !== today) {
                    this._lastDiaryCompile = today;
                    localStorage.setItem('jefry_last_diary_compile', today);
                    setTimeout(function () { self._fetchAndSpeak('diary_compile_today', { duckMusic: true, importance: 'medium' }); }, 8000);
                }
                return;
            }

            // ── Diary periodic checks (spread throughout the day) ──
            this._runDiaryChecks(now);

            // Celebration check
            if (this._lastCelebration !== today) {
                this._checkCelebration(today);
            }

            // Proactive phrase (every 15 min)
            this._tryPhrase();
        },

        _runDiaryChecks: function (now) {
            var self = this;
            if (!self.shouldBeProactive()) return;

            // Worry nag: every 6 hours if there's a persistent worry
            if (now - self._lastDiaryWorryNag > 21600000) { // 6 hours
                self._lastDiaryWorryNag = now;
                localStorage.setItem('jefry_last_diary_worry_ts', now.toString());
                self._fetchAndSpeak('diary_worry_nag', { duckMusic: true, importance: 'medium' });
                return;
            }

            // Idea remind: once per day (at any hour after 10h)
            if (now - self._lastDiaryIdeaCheck > 86400000 && new Date().getHours() >= 10) { // 24h
                self._lastDiaryIdeaCheck = now;
                localStorage.setItem('jefry_last_diary_idea_ts', now.toString());
                self._fetchAndSpeak('diary_idea_remind', { duckMusic: true, importance: 'low' });
                return;
            }

            // Decision nudge: once every 3 days
            if (now - self._lastDiaryDecisionCheck > 259200000) { // 72h
                self._lastDiaryDecisionCheck = now;
                localStorage.setItem('jefry_last_diary_decision_ts', now.toString());
                self._fetchAndSpeak('diary_decision_nudge', { duckMusic: true, importance: 'low' });
                return;
            }

            // Mood check: once every 12 hours
            if (now - self._lastDiaryMoodCheck > 43200000) { // 12h
                self._lastDiaryMoodCheck = now;
                localStorage.setItem('jefry_last_diary_mood_ts', now.toString());
                self._fetchAndSpeak('diary_mood_check', { duckMusic: true, importance: 'medium' });
                return;
            }
        },

        _fetchAndSpeak: function (trigger, opts) {
            var self = this;
            var fd = new FormData();
            fd.append('action', 'voice_proactive');
            fd.append('proactive_trigger', trigger);
            fd.append('proactive_context_json', JSON.stringify({}));

            var fetchUrl = window._voiceProactive._buildFetchUrl();
            fetch(fetchUrl, {
                method: 'POST', body: fd, credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (r) { return r.json(); })
              .then(function (data) {
                  if (data && data.tts_text) {
                      window._voiceProactive.speak(data.tts_text, opts);
                  }
                  // Celebration confetti
                  if (data && data.celebration) {
                      self._showConfetti();
                  }
                  // Whiteboard
                  if (data && data.whiteboard) {
                      var wbDelay = data.whiteboard.mode === 'flash' ? 200 : 800;
                      setTimeout(function () {
                          if (window._jefryWhiteboard) window._jefryWhiteboard.show(data.whiteboard);
                      }, wbDelay);
                  }
              }).catch(function () {});
        },

        _fetchPhrase: function (phraseId, context) {
            var fd = new FormData();
            fd.append('action', 'voice_proactive');
            fd.append('proactive_trigger', 'proactive_phrase');
            fd.append('proactive_context_json', JSON.stringify({
                phrase_id: phraseId,
                data: context || {}
            }));

            var fetchUrl = window._voiceProactive._buildFetchUrl();
            fetch(fetchUrl, {
                method: 'POST', body: fd, credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (r) { return r.json(); })
              .then(function (data) {
                  if (data && data.tts_text) {
                      window._voiceProactive.speak(data.tts_text, { duckMusic: true });
                  }
                  if (data && data.whiteboard) {
                      setTimeout(function () {
                          if (window._jefryWhiteboard) window._jefryWhiteboard.show(data.whiteboard);
                      }, 200);
                  }
              }).catch(function () {});
        },

        _checkCelebration: function (today) {
            var self = this;
            var fd = new FormData();
            fd.append('action', 'voice_proactive');
            fd.append('proactive_trigger', 'celebration_check');
            fd.append('proactive_context_json', JSON.stringify({}));

            var fetchUrl = window._voiceProactive._buildFetchUrl();
            fetch(fetchUrl, {
                method: 'POST', body: fd, credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (r) { return r.json(); })
              .then(function (data) {
                  if (data && data.celebration) {
                      self._lastCelebration = today;
                      localStorage.setItem('jefry_last_celebration', today);
                      if (data.tts_text) {
                          window._voiceProactive.speak(data.tts_text, { duckMusic: true, importance: 'high' });
                      }
                      self._showConfetti();
                  }
                  if (data && data.whiteboard) {
                      setTimeout(function () {
                          if (window._jefryWhiteboard) window._jefryWhiteboard.show(data.whiteboard);
                      }, 200);
                  }
              }).catch(function () {});
        },

        _tryPhrase: function () {
            var self = this;
            var now = Date.now();

            // ── Respect mode flags ──
            if (!self.shouldBeProactive()) return;

            // ── Campaign health check every ~1 hour (4 cycles of 15 min) ──
            if (now - self._lastCampaignCheck > 3600000) { // 1 hour
                self._lastCampaignCheck = now;
                self._checkCampaignHealth();
            }

            // Minimum 15 min (900000 ms) between phrases
            if (now - self._lastPhrase < 900000) return;

            // Load spoken phrases for today
            var raw = localStorage.getItem('jefry_spoken_phrases') || '{}';
            try { self._spokenToday = JSON.parse(raw); } catch (e) { self._spokenToday = {}; }

            // Try phrases in priority order
            var phraseId = self._pickNextPhrase();
            if (!phraseId) return;

            self._lastPhrase = now;
            localStorage.setItem('jefry_last_phrase_ts', now.toString());
            self._spokenToday[phraseId] = true;
            localStorage.setItem('jefry_spoken_phrases', JSON.stringify(self._spokenToday));

            self._fetchPhrase(phraseId, {});
        },

        _pickNextPhrase: function () {
            // Simple random pick from available phrases that haven't been spoken today
            // In real implementation, this uses GPS context, time, CRM data
            // For now, pick a random high-priority phrase that hasn't been spoken
            var pool = [
                'gps_near_home', 'gps_near_work', 'mood_how_feeling',
                'music_no_music_yet', 'fun_motivation'
            ];
            var available = pool.filter(function (id) {
                return !window._voiceProactiveChecks._spokenToday[id];
            });
            if (available.length === 0) return null;
            return available[Math.floor(Math.random() * available.length)];
        },

        _checkCampaignHealth: function () {
            var self = this;
            if (!self.shouldBeProactive()) return;
            var fd = new FormData();
            fd.append('action', 'voice_proactive');
            fd.append('proactive_trigger', 'proactive_phrase');
            fd.append('proactive_context_json', JSON.stringify({
                phrase_id: 'biz_branch_neglected',
                data: {}
            }));

            var fetchUrl = window._voiceProactive._buildFetchUrl();
            fetch(fetchUrl, {
                method: 'POST', body: fd, credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (r) { return r.json(); })
              .then(function (data) {
                  if (data && data.tts_text) {
                      // Only speak if there's an actual alert (non-empty tts_text)
                      window._voiceProactive.speak(data.tts_text, { duckMusic: true });
                  }
              }).catch(function () {});
        },

        _showConfetti: function () {
            // Simple CSS confetti overlay on the cassette deck
            var body = document.body;
            if (!body.classList.contains('is-lite')) return;

            var container = document.createElement('div');
            container.className = 'jefry-confetti';
            container.setAttribute('aria-hidden', 'true');

            // Create 30 particles
            var colors = ['#f59e0b', '#ef4444', '#10b981', '#3b82f6', '#8b5cf6', '#ec4899'];
            for (var i = 0; i < 30; i++) {
                var particle = document.createElement('span');
                particle.className = 'jefry-confetti-particle';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.animationDelay = Math.random() * 0.8 + 's';
                particle.style.animationDuration = (2 + Math.random() * 2) + 's';
                particle.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                particle.style.opacity = 0.7 + Math.random() * 0.3;
                container.appendChild(particle);
            }

            document.body.appendChild(container);

            // Clean up after animation
            setTimeout(function () {
                if (container.parentNode) container.parentNode.removeChild(container);
            }, 4000);
        },

        // Called when user speaks a command
        onUserInteraction: function () {
            this._dailyInteractionCount++;
            localStorage.setItem('jefry_daily_interactions', this._dailyInteractionCount.toString());
        },

        // ── Read mode flags from localStorage before being proactive ──
        shouldBeProactive: function () {
            if (localStorage.getItem('jefry_mode_silent') === 'true') return false;
            if (localStorage.getItem('jefry_mode_accompanied') === 'true') return false;
            return true;
        }
    };

    // ── Global: apply system_actions from LLM response ──
    function _applySystemActions(actions) {
        for (var i = 0; i < actions.length; i++) {
            var a = actions[i];
            var intent = a.intent || a.action || '';

            switch (intent) {
                case 'set_mode':
                    if (a.mode === 'silent') {
                        localStorage.setItem('jefry_mode_silent', a.value ? 'true' : 'false');
                    } else if (a.mode === 'accompanied') {
                        localStorage.setItem('jefry_mode_accompanied', a.value ? 'true' : 'false');
                    } else if (a.mode === 'proactive') {
                        // Reset both flags
                        localStorage.removeItem('jefry_mode_silent');
                        localStorage.removeItem('jefry_mode_accompanied');
                    }
                    break;

                case 'send_whatsapp':
                    if (a.whatsapp_url) {
                        window.open(a.whatsapp_url, '_blank');
                    }
                    break;

                case 'play_music':
                    if (window._DjJefry) {
                        window._DjJefry._playMusic({ action: 'play_music', mood: a.mood });
                    }
                    break;

                case 'parking':
                    if (window._GpsCopilot) {
                        if (a.action === 'save') {
                            window._GpsCopilot.saveParking();
                        } else if (a.action === 'recall') {
                            var recall = window._GpsCopilot.recallParking();
                            if (recall && recall.indexOf('¿Quieres que te guíe?') !== -1) {
                                setTimeout(function () {
                                    if (confirm('¿Abrir Google Maps con la ruta a pie?')) {
                                        window._GpsCopilot.openParkingRoute();
                                    }
                                }, 1000);
                            }
                        }
                    }
                    break;

                case 'voice_control':
                    if (typeof YTPlayer !== 'undefined' && YTPlayer.player) {
                        if (a.action === 'pause_music') {
                            YTPlayer.player.pauseVideo();
                        } else if (a.action === 'resume_music') {
                            YTPlayer.player.playVideo();
                        } else if (a.action === 'skip_song') {
                            if (YTPlayer.playNext) YTPlayer.playNext();
                        } else if (a.action === 'volume' && a.value) {
                            var vol = parseInt(a.value, 10);
                            if (!isNaN(vol) && YTPlayer.player.setVolume) {
                                YTPlayer.player.setVolume(Math.max(0, Math.min(100, vol)));
                            }
                        } else if (a.action === 'mute' && YTPlayer.player.mute) {
                            YTPlayer.player.mute();
                        } else if (a.action === 'unmute' && YTPlayer.player.unMute) {
                            YTPlayer.player.unMute();
                        }
                    }
                    break;
            }
        }
    };
    // ═══════════════════════════════════════════════════════════════════
    // WAKE WORD COPILOT — "Oye Jefry" (Lite/Car Mode only)
    // ═══════════════════════════════════════════════════════════════════
    window._WakeWordCopilot = {
        phase: 'idle',
        audioCtx: null, analyser: null, micStream: null,
        checkInterval: null, scanTimer: null, cooldownTimer: null,
        scanRecognition: null, micBtn: null, destroyed: false,

        threshold: 0.12, scanDuration: 7000, cooldownDuration: 12000, checkIntervalMs: 200,
        wakeWords: [], // built dynamically in init() from settings

        init: function () {
            var self = this;
            self.micBtn = document.querySelector('[data-voice-lite-toggle]');
            if (!self.micBtn) return;

            // ── Read wake word config from body data attributes ──
            var wakeEnabled = document.body.getAttribute('data-voice-wake-enabled');
            if (wakeEnabled !== '1') {
                self.phase = 'unavailable';
                console.log('[WakeWord] Wake word disabled via settings — push-to-talk only');
                return;
            }

            var wakeWordRaw = document.body.getAttribute('data-voice-wake-word') || 'Jefry';
            self._buildWakeWords(wakeWordRaw);

            var AC = window.AudioContext || window.webkitAudioContext;
            if (!AC) { self.phase = 'unavailable'; console.log('[WakeWord] AudioContext N/D — fallback push-to-talk'); return; }

            var done = false;
            function go() {
                if (done || self.phase !== 'idle') return;
                done = true;
                document.removeEventListener('click', go);
                document.removeEventListener('touchend', go);
                self._initAudio(AC);
            }
            document.addEventListener('click', go, { once: false });
            document.addEventListener('touchend', go, { once: false });
        },

        // ── Generate wake word variants from a base word ──
        _buildWakeWords: function (baseWord) {
            var w = String(baseWord || '').toLowerCase().trim();
            if (!w) w = 'jefry';

            // Strip accents for matching
            var wNorm = w.normalize('NFD').replace(/[\u0300-\u036f]/g, '');

            var variants = [];
            // Base word + common prefixes
            variants.push(wNorm);
            variants.push('oye ' + wNorm);
            variants.push('hey ' + wNorm);

            // Common phonetic/spelling variants
            if (wNorm.length <= 1) return;

            // r/rr confusion (e.g., "jefry" vs "jefrry")
            if (wNorm.indexOf('r') !== -1 && wNorm.indexOf('rr') === -1) {
                var withRR = wNorm.replace(/r/g, 'rr');
                variants.push(withRR);
                variants.push('oye ' + withRR);
                variants.push('hey ' + withRR);
            }
            if (wNorm.indexOf('rr') !== -1) {
                var withR = wNorm.replace(/rr/g, 'r');
                variants.push(withR);
                variants.push('oye ' + withR);
                variants.push('hey ' + withR);
            }

            // y/i confusion at end of word (e.g., "jefry" vs "jefri")
            if (wNorm.charAt(wNorm.length - 1) === 'y') {
                var withI = wNorm.slice(0, -1) + 'i';
                variants.push(withI);
                variants.push('oye ' + withI);
                variants.push('hey ' + withI);
            } else if (wNorm.charAt(wNorm.length - 1) === 'i') {
                var withY = wNorm.slice(0, -1) + 'y';
                variants.push(withY);
                variants.push('oye ' + withY);
                variants.push('hey ' + withY);
            }

            // Single/double consonant common errors (ll/l, cc/c for short words)
            if (wNorm.length <= 6) {
                if (wNorm.indexOf('ll') !== -1) {
                    variants.push(wNorm.replace(/ll/g, 'l'));
                    variants.push(wNorm.replace(/ll/g, 'y'));
                }
                if (wNorm.indexOf('cc') !== -1) {
                    variants.push(wNorm.replace(/cc/g, 'c'));
                }
            }

            // Deduplicate
            var seen = {};
            var unique = [];
            for (var i = 0; i < variants.length; i++) {
                var v = variants[i];
                if (!seen[v]) { seen[v] = true; unique.push(v); }
            }
            self.wakeWords = unique;
            console.log('[WakeWord] Configured wake words:', unique);
        },

        _initAudio: function (AC) {
            var self = this;
            try {
                self.audioCtx = new AC();
                navigator.mediaDevices.getUserMedia({ audio: true, video: false }).then(function (stream) {
                    self.micStream = stream;
                    var src = self.audioCtx.createMediaStreamSource(stream);
                    self.analyser = self.audioCtx.createAnalyser();
                    self.analyser.fftSize = 256;
                    self.analyser.smoothingTimeConstant = 0.3;
                    src.connect(self.analyser);
                    self._startStandby();
                }).catch(function (err) {
                    console.log('[WakeWord] Mic denied:', err.message);
                    self.phase = 'unavailable';
                    self._destroyAudio();
                });
            } catch (e) {
                console.log('[WakeWord] Init failed:', e.message);
                self.phase = 'unavailable';
            }
        },

        _startStandby: function () {
            var self = this;
            self._clearAllTimers();
            self._setPhase('standby');
            if (!self.analyser) return;
            var len = self.analyser.frequencyBinCount;
            var buf = new Uint8Array(len);

            self.checkInterval = setInterval(function () {
                if (self.phase !== 'standby' || !self.analyser) return;
                self.analyser.getByteTimeDomainData(buf);
                var sum = 0;
                for (var i = 0; i < len; i++) { var s = (buf[i] - 128) / 128; sum += s * s; }
                if (Math.sqrt(sum / len) > self.threshold) self._startWakeScan();
            }, self.checkIntervalMs);
        },

        _startWakeScan: function () {
            var self = this;
            self._setPhase('scanning');
            if (self.checkInterval) { clearInterval(self.checkInterval); self.checkInterval = null; }

            var RC = window.SpeechRecognition || window.webkitSpeechRecognition;
            if (!RC || window._voiceInteractionActive) { self._enterCooldown(); return; }

            var detected = false;
            try {
                self.scanRecognition = new RC();
                self.scanRecognition.lang = 'es-ES';
                self.scanRecognition.continuous = false;
                self.scanRecognition.interimResults = true;
                self.scanRecognition.maxAlternatives = 3;

                self.scanRecognition.onresult = function (e) {
                    if (detected) return;
                    for (var i = e.resultIndex; i < e.results.length; i++) {
                        for (var j = 0; j < e.results[i].length; j++) {
                            var t = (e.results[i][j].transcript || '').toLowerCase().trim();
                            t = t.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
                            for (var w = 0; w < self.wakeWords.length; w++) {
                                var ww = self.wakeWords[w].normalize('NFD').replace(/[\u0300-\u036f]/g, '');
                                if (t.indexOf(ww) !== -1) { detected = true; self._onWakeDetected(); return; }
                            }
                        }
                    }
                };
                self.scanRecognition.onerror = function () { self._cancelScan(); self._enterCooldown(); };
                self.scanRecognition.onend   = function () { if (!detected) { self._cancelScan(); self._enterCooldown(); } };
                self.scanRecognition.start();
                self.scanTimer = setTimeout(function () { if (!detected) { self._cancelScan(); self._enterCooldown(); } }, self.scanDuration);
            } catch (e) { self._cancelScan(); self._enterCooldown(); }
        },

        _onWakeDetected: function () {
            var self = this;
            self._cancelScan();
            self._setPhase('active');
            console.log('[WakeWord] ¡Oye Jefry detectado!');
            self._playBeep();
            window._voicePauseMusic();
            window._voiceInteractionActive = true;

            var startBtn = document.getElementById('voiceStartButton');
            if (startBtn && !startBtn.disabled) {
                setTimeout(function () { startBtn.click(); }, 350);
            } else {
                window._voiceInteractionActive = false;
                self._enterCooldown();
            }
        },

        _cancelScan: function () {
            if (this.scanRecognition) { try { this.scanRecognition.abort(); } catch (e) {} this.scanRecognition = null; }
            if (this.scanTimer) { clearTimeout(this.scanTimer); this.scanTimer = null; }
        },

        _enterCooldown: function () {
            var self = this;
            self._cancelScan();
            self._setPhase('cooldown');
            self.cooldownTimer = setTimeout(function () { if (!self.destroyed) self._startStandby(); }, self.cooldownDuration);
        },

        _setPhase: function (p) {
            this.phase = p;
            if (this.micBtn) {
                this.micBtn.classList.remove('wake-standby','wake-scanning','wake-active','wake-cooldown');
                if (p !== 'idle' && p !== 'unavailable') this.micBtn.classList.add('wake-' + p);
            }
        },

        _playBeep: function () {
            var ctx = this.audioCtx;
            if (!ctx || ctx.state === 'closed') return;
            try {
                if (ctx.state === 'suspended') ctx.resume();
                var o = ctx.createOscillator(), g = ctx.createGain();
                o.connect(g); g.connect(ctx.destination);
                o.type = 'sine';
                o.frequency.setValueAtTime(880, ctx.currentTime);
                o.frequency.setValueAtTime(1100, ctx.currentTime + 0.08);
                g.gain.setValueAtTime(0.12, ctx.currentTime);
                g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.3);
                o.start(ctx.currentTime); o.stop(ctx.currentTime + 0.3);
            } catch (e) {}
        },

        _clearAllTimers: function () {
            if (this.checkInterval) { clearInterval(this.checkInterval); this.checkInterval = null; }
            if (this.scanTimer) { clearTimeout(this.scanTimer); this.scanTimer = null; }
            if (this.cooldownTimer) { clearTimeout(this.cooldownTimer); this.cooldownTimer = null; }
        },

        _destroyAudio: function () {
            this._clearAllTimers();
            if (this.micStream) { this.micStream.getTracks().forEach(function (t) { t.stop(); }); this.micStream = null; }
            if (this.audioCtx && this.audioCtx.state !== 'closed') { this.audioCtx.close().catch(function () {}); this.audioCtx = null; this.analyser = null; }
        },

        destroy: function () {
            this.destroyed = true;
            this._destroyAudio();
            this._cancelScan();
            this.phase = 'idle';
            if (this.micBtn) this.micBtn.classList.remove('wake-standby','wake-scanning','wake-active','wake-cooldown');
        }
    };

    // ═══════════════════════════════════════════════════════════════
    // JEFRY WHITEBOARD — Pizarra visual overlay
    // ═══════════════════════════════════════════════════════════════
    window._jefryWhiteboard = {
        _timer: null,
        _progressTimer: null,

        show: function (data) {
            if (!data) return;
            var self = this;
            var overlay = document.getElementById('jefryWhiteboardOverlay');
            var content = document.getElementById('jefryWhiteboardContent');
            var toolbar = document.getElementById('jefryWhiteboardToolbar');
            var title = document.getElementById('jefryWhiteboardTitle');
            var progress = document.getElementById('jefryWhiteboardProgress');
            var progressBar = document.getElementById('jefryWhiteboardProgressBar');
            if (!overlay || !content) return;

            // Build content
            this._buildContent(data, content);

            // Toolbar visibility
            var isModal = data.mode === 'modal';
            toolbar.style.display = isModal ? 'flex' : 'none';

            // Title
            if (data.type === 'chart' && data.chart && data.chart.title) {
                title.textContent = data.chart.title;
            } else if (data.type === 'image' && data.alt) {
                title.textContent = data.alt;
            } else {
                title.textContent = '';
            }

            // Progress bar (only for flash)
            progress.hidden = !(data.mode === 'flash');
            if (data.mode === 'flash') {
                progressBar.style.width = '100%';
            }

            // Show
            overlay.hidden = false;
            requestAnimationFrame(function () {
                overlay.classList.add('active');
            });

            // Flash timer
            if (data.mode === 'flash') {
                var duration = (data.duration || 5) * 1000;
                var startTime = Date.now();
                var totalMs = duration;

                // Clear existing timers
                if (self._progressTimer) clearInterval(self._progressTimer);
                if (self._timer) clearTimeout(self._timer);

                self._progressTimer = setInterval(function () {
                    var elapsed = Date.now() - startTime;
                    var remaining = Math.max(0, 100 - (elapsed / totalMs * 100));
                    progressBar.style.width = remaining + '%';
                    if (remaining <= 0 && self._progressTimer) {
                        clearInterval(self._progressTimer);
                        self._progressTimer = null;
                    }
                }, 100);

                self._timer = setTimeout(function () {
                    if (self._progressTimer) { clearInterval(self._progressTimer); self._progressTimer = null; }
                    self.hide();
                }, duration);
            }
        },

        hide: function () {
            var self = this;
            var overlay = document.getElementById('jefryWhiteboardOverlay');
            if (!overlay) return;

            if (self._timer) { clearTimeout(self._timer); self._timer = null; }
            if (self._progressTimer) { clearInterval(self._progressTimer); self._progressTimer = null; }

            overlay.classList.remove('active');
            setTimeout(function () {
                overlay.hidden = true;
                var content = document.getElementById('jefryWhiteboardContent');
                if (content) content.innerHTML = '';
            }, 250);
        },

        _buildContent: function (data, container) {
            container.className = 'jefry-whiteboard-content';
            container.innerHTML = '';

            switch (data.type || '') {
                case 'chart':
                    this._buildChart(data, container);
                    break;
                case 'image':
                    this._buildImage(data, container);
                    break;
                case 'html':
                    container.innerHTML = data.html || '';
                    break;
                case 'text':
                default:
                    container.innerHTML = '<div class="jefry-whiteboard-text">' + this._escapeHtml(data.text || '') + '</div>';
                    break;
            }
        },

        _buildImage: function (data, container) {
            var img = document.createElement('img');
            img.src = data.src || '';
            img.alt = data.alt || '';
            img.className = 'jefry-whiteboard-image';
            img.loading = 'lazy';
            img.onerror = function () {
                this.style.display = 'none';
                var fallback = document.createElement('div');
                fallback.className = 'jefry-whiteboard-text';
                fallback.textContent = 'No se pudo cargar la imagen.';
                container.appendChild(fallback);
            };
            container.appendChild(img);
        },

        _buildChart: function (data, container) {
            if (!data.chart) return;
            var chart = data.chart;

            // Lite mode: use CSS bars (Chart.js not loaded to save RAM)
            if (document.body.classList.contains('is-lite')) {
                this._buildLiteChart(chart, container);
                return;
            }

            // Desktop: use Chart.js
            var chartDiv = document.createElement('div');
            chartDiv.className = 'jefry-whiteboard-chart';
            container.appendChild(chartDiv);

            var canvas = document.createElement('canvas');
            chartDiv.appendChild(canvas);

            // Delay to ensure canvas is visible and sized
            var self = this;
            setTimeout(function () {
                self._renderChartJs(canvas, chart);
            }, 150);
        },

        _buildLiteChart: function (chart, container) {
            var labels = chart.labels || [];
            var datasets = chart.datasets || [];
            if (!datasets.length || !labels.length) return;

            var dataset = datasets[0];
            var values = dataset.data || [];
            var maxVal = 1;
            for (var i = 0; i < values.length; i++) {
                if (values[i] > maxVal) maxVal = values[i];
            }

            var titleEl = document.createElement('div');
            titleEl.style.cssText = 'font-size:16px;font-weight:600;color:#d9e2ef;margin-bottom:14px;text-align:center';
            titleEl.textContent = chart.title || '';
            container.appendChild(titleEl);

            for (var i = 0; i < labels.length; i++) {
                var pct = maxVal > 0 ? (values[i] / maxVal * 100) : 0;

                var row = document.createElement('div');
                row.className = 'jefry-whiteboard-bar-row';

                var label = document.createElement('div');
                label.className = 'jefry-whiteboard-bar-label';
                label.textContent = labels[i];

                var track = document.createElement('div');
                track.className = 'jefry-whiteboard-bar-track';

                var fill = document.createElement('div');
                fill.className = 'jefry-whiteboard-bar-fill';
                fill.style.width = pct + '%';

                var value = document.createElement('div');
                value.className = 'jefry-whiteboard-bar-value';
                value.textContent = values[i].toLocaleString('es-ES') + '€';

                track.appendChild(fill);
                row.appendChild(label);
                row.appendChild(track);
                row.appendChild(value);
                container.appendChild(row);
            }
        },

        _renderChartJs: function (canvas, chart) {
            if (typeof Chart === 'undefined') {
                // Fallback to bars if Chart.js not available
                var fallback = document.createElement('div');
                fallback.style.cssText = 'text-align:center;color:#8099b3;padding:20px';
                fallback.textContent = chart.title || '';
                canvas.parentNode.replaceChild(fallback, canvas);
                return;
            }

            var ctx = canvas.getContext('2d');
            var type = chart.type === 'doughnut' ? 'doughnut' : 'bar';

            new Chart(ctx, {
                type: type,
                data: {
                    labels: chart.labels || [],
                    datasets: chart.datasets || []
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: type === 'doughnut', labels: { color: '#d9e2ef' } }
                    },
                    scales: type === 'bar' ? {
                        x: { ticks: { color: '#8099b3' }, grid: { color: '#1a2a40' } },
                        y: { ticks: { color: '#8099b3' }, grid: { color: '#1a2a40' } }
                    } : undefined
                }
            });
        },

        _escapeHtml: function (str) {
            if (!str) return '';
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        },

        init: function () {
            var self = this;
            var overlay = document.getElementById('jefryWhiteboardOverlay');
            if (!overlay) return;

            // Close button
            var closeBtn = overlay.querySelector('.jefry-whiteboard-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', function () { self.hide(); });
            }

            // Click outside card (modal only — but safe to always attach)
            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) {
                    self.hide();
                }
            });

            // Escape key
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && !overlay.hidden) {
                    self.hide();
                }
            });
        }
    };

    // Init whiteboard events
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { window._jefryWhiteboard.init(); });
    } else {
        window._jefryWhiteboard.init();
    }

    // ═══════════════════════════════════════════════════════════════
    // DIARY TAB — UI en Josue
    // ═══════════════════════════════════════════════════════════════
    window._diaryTab = {
        offset: 0,
        limit: 10,
        loading: false,
        hasMore: true,
        searchTimer: null,

        init: function () {
            var self = this;
            var timeline = document.getElementById('diaryTimeline');
            if (!timeline) return;

            self.loadEntries();

            // Búsqueda con debounce
            var searchInput = document.getElementById('diarySearch');
            if (searchInput) {
                searchInput.addEventListener('input', function () {
                    clearTimeout(self.searchTimer);
                    self.searchTimer = setTimeout(function () {
                        self.offset = 0;
                        self.hasMore = true;
                        self.loadEntries(searchInput.value);
                    }, 400);
                });
            }

            // Botón cargar más
            var loadMoreBtn = document.getElementById('diaryLoadMoreBtn');
            if (loadMoreBtn) {
                loadMoreBtn.addEventListener('click', function () {
                    self.loadMore();
                });
            }

            // Scroll infinito
            window.addEventListener('scroll', function () {
                if (self.loading || !self.hasMore) return;
                var loadMore = document.getElementById('diaryLoadMore');
                if (loadMore && loadMore.style.display !== 'none') {
                    var rect = loadMore.getBoundingClientRect();
                    if (rect.top < window.innerHeight + 200) {
                        self.loadMore();
                    }
                }
            });
        },

        loadEntries: function (search) {
            var self = this;
            self.loading = true;
            self.offset = 0;

            var fd = new FormData();
            fd.append('action', 'get_diario_entries');
            fd.append('offset', '0');
            fd.append('limit', String(self.limit));
            if (search) fd.append('search', search);

            // Usar URL simple (POST a sí mismo)
            fetch(window.location.href, {
                method: 'POST', body: fd, credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (r) { return r.json(); })
              .then(function (data) {
                  self.loading = false;
                  if (!data || !data.ok) return;

                  var timeline = document.getElementById('diaryTimeline');
                  if (!timeline) return;
                  timeline.innerHTML = '';

                  if (data.entries.length === 0) {
                      timeline.innerHTML = '<div class="diary-empty">No hay entradas todavía. Las entradas del diario se generan automáticamente a partir de tus conversaciones con Jefry.</div>';
                      return;
                  }

                  data.entries.forEach(function (entry) {
                      timeline.innerHTML += self._buildEntryHTML(entry);
                  });

                  self.hasMore = data.has_more;
                  self.offset = data.entries.length;

                  var loadMore = document.getElementById('diaryLoadMore');
                  if (loadMore) {
                      loadMore.style.display = data.has_more ? 'block' : 'none';
                  }
              }).catch(function () {
                  self.loading = false;
              });
        },

        loadMore: function () {
            var self = this;
            if (self.loading || !self.hasMore) return;
            self.loading = true;

            var searchInput = document.getElementById('diarySearch');
            var search = searchInput ? searchInput.value : '';

            var fd = new FormData();
            fd.append('action', 'get_diario_entries');
            fd.append('offset', String(self.offset));
            fd.append('limit', String(self.limit));
            if (search) fd.append('search', search);

            fetch(window.location.href, {
                method: 'POST', body: fd, credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (r) { return r.json(); })
              .then(function (data) {
                  self.loading = false;
                  if (!data || !data.ok) return;

                  var timeline = document.getElementById('diaryTimeline');
                  if (!timeline) return;

                  data.entries.forEach(function (entry) {
                      timeline.innerHTML += self._buildEntryHTML(entry);
                  });

                  self.hasMore = data.has_more;
                  self.offset += data.entries.length;

                  var loadMore = document.getElementById('diaryLoadMore');
                  if (loadMore) {
                      loadMore.style.display = data.has_more ? 'block' : 'none';
                  }
              }).catch(function () {
                  self.loading = false;
              });
        },

        _buildEntryHTML: function (entry) {
            var moodEmojis = {
                'motivado': '😊', 'feliz': '😄', 'ilusionado': '🤩',
                'neutro': '😐', 'cansado': '😴', 'preocupado': '😟',
                'frustrado': '😤', 'estresado': '😰'
            };
            var mood = entry.mood || 'neutro';
            var moodEmoji = moodEmojis[mood] || '😐';

            var fecha = entry.fecha || '';
            var ts = new Date(fecha + 'T00:00:00');
            var dias = ['Dom', 'Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab'];
            var fechaStr = !isNaN(ts) ? dias[ts.getDay()] + ' ' + String(ts.getDate()).padStart(2, '0') : fecha;

            var cleanText = (entry.clean_text || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            var rawText = (entry.raw_text || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

            var highlightsHTML = '';
            if (entry.highlights && entry.highlights.length) {
                highlightsHTML = '<div class="diary-entry-highlights">' +
                    entry.highlights.map(function (h) { return '<span class="diary-highlight-tag">' + h.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</span>'; }).join('') +
                    '</div>';
            }

            var tagsHTML = '';
            if (entry.tags && entry.tags.length) {
                tagsHTML = '<div class="diary-entry-tags">' +
                    entry.tags.map(function (t) { return '<span class="diary-tag">' + t.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</span>'; }).join('') +
                    '</div>';
            }

            var entryId = 'diary-raw-' + (entry.fecha || '').replace(/[^a-z0-9]/g, '');

            return '<div class="diary-entry">' +
                '<div class="diary-entry-header">' +
                '<span class="diary-entry-date">' + fechaStr + '</span>' +
                '<span class="diary-entry-mood" title="' + mood + '">' + moodEmoji + '</span>' +
                '<span class="diary-entry-frags">' + (entry.fragmentos || 0) + ' fragmentos</span>' +
                '</div>' +
                '<div class="diary-entry-body"><p class="diary-entry-text">' + cleanText.replace(/\n/g, '<br>') + '</p></div>' +
                highlightsHTML + tagsHTML +
                '<button class="diary-raw-toggle" onclick="var el=document.getElementById(\'' + entryId + '\');el.style.display=el.style.display===\'none\'?\'block\':\'none\';this.textContent=el.style.display===\'none\'?\'📝 Ver transcripción literal\':\'📝 Ocultar transcripción literal\';">📝 Ver transcripción literal</button>' +
                '<div class="diary-raw-text" id="' + entryId + '" style="display:none"><pre>' + rawText + '</pre></div>' +
                '</div>';
        }
    };

    // Auto-init diary tab when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { window._diaryTab.init(); });
    } else {
        window._diaryTab.init();
    }

    // ═══════════════════════════════════════════════════════════════════
    // 🔧 FIX OVERLAYS LITE — Monitor de consistencia + auto-reparación
    // ═══════════════════════════════════════════════════════════════════
    (function () {
        if (!document.body.classList.contains('is-lite')) return;
        var _lastAutoFix = 0;
        var _diagInfo = ''; // para el debug indicator

        // Forzar estilos críticos via JS (belt-and-suspenders — no depender solo de CSS)
        function _forceCassetteStyles() {
            var empty = document.querySelector('.yt-cassette-empty');
            var door = document.querySelector('.yt-cassette-door');
            var tape = document.querySelector('.yt-cassette-tape:not(.loaded)');
            var iframe = document.querySelector('#youtubePlayerContainer iframe');

            if (empty && empty.style.pointerEvents !== 'none') {
                empty.style.setProperty('pointer-events', 'none', 'important');
            }
            if (door && door.style.overflow !== 'clip') {
                door.style.setProperty('overflow', 'clip', 'important');
            }
            if (tape && tape.style.pointerEvents !== 'none') {
                tape.style.setProperty('pointer-events', 'none', 'important');
            }
            if (iframe && !document.body.classList.contains('yt-fs-video') && iframe.style.pointerEvents !== 'none') {
                iframe.style.setProperty('pointer-events', 'none', 'important');
            }
        }

        // Monitoreo periódico: overlays stuck + forzar estilos cassette
        setInterval(function () {
            _forceCassetteStyles();

            var overlay = document.getElementById('ytRadioSidebarOverlay');
            var sidebar = document.getElementById('ytRadioSidebar');
            var pres = document.getElementById('presintoniasPanel');
            var rad = document.getElementById('radiosPanel');

            var overlayVis = overlay && overlay.classList.contains('visible');
            var anyPanel = (sidebar && sidebar.classList.contains('open'))
                || (pres && pres.classList.contains('open'))
                || (rad && rad.classList.contains('open'));

            // Diagnosticar cassette empty
            var empty = document.querySelector('.yt-cassette-empty');
            if (empty) {
                var cs = window.getComputedStyle(empty);
                _diagInfo = 'empty:pe=' + cs.pointerEvents + ' h=' + empty.offsetHeight;
            }

            if (overlayVis && !anyPanel) {
                var now = Date.now();
                if (now - _lastAutoFix > 2000) {
                    _lastAutoFix = now;
                    setTimeout(function () {
                        var ov2 = document.getElementById('ytRadioSidebarOverlay');
                        if (!ov2 || !ov2.classList.contains('visible')) return;
                        var any2 = (document.getElementById('ytRadioSidebar') && document.getElementById('ytRadioSidebar').classList.contains('open'))
                            || (document.getElementById('presintoniasPanel') && document.getElementById('presintoniasPanel').classList.contains('open'))
                            || (document.getElementById('radiosPanel') && document.getElementById('radiosPanel').classList.contains('open'));
                        if (!any2) {
                            ov2.classList.remove('visible');
                            console.warn('[LITE-FIX] Overlay stuck corregido automáticamente');
                        }
                    }, 500);
                }
            }
        }, 1500);

        // Exponer info de diagnóstico para el indicator
        window._getDiagInfo = function () { return _diagInfo; };
    })();

})();

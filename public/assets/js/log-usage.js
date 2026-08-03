// log-usage.js
// Phase 6 -- progressive-enhancement helpers for the teknisi Log Usage
// screen. No framework; plain DOM events, matching materials.js.

document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('material-form');
    if (!form) return; // no assigned open WOs -- form isn't rendered

    var searchInput = document.getElementById('material-search');
    var chipRow = document.getElementById('material-category-chips');
    var pickerCards = document.querySelectorAll('.material-picker-card');
    var materialRadios = document.querySelectorAll('.material-picker-radio');

    var usageSection = document.getElementById('usage-input-section');
    var usageLabel = document.getElementById('usage-input-label');
    var qtyGroup = document.getElementById('qty-input-group');
    var qtyInput = document.getElementById('qty_used');
    var qtyStockHint = document.getElementById('qty-stock-hint');
    var snSelects = document.querySelectorAll('.sn-select');
    var snEmptyHint = document.getElementById('sn-empty-hint');
    var submitBtn = document.getElementById('log-usage-submit');

    // Phase 7 -- mini "log terbaru" panel per WO, shown once a WO is picked.
    var woSelect = document.getElementById('wo_id');
    var woLogsPanels = document.querySelectorAll('.wo-logs-panel');

    function showLogsPanelForSelectedWO() {
        var selectedWoId = woSelect.value;
        woLogsPanels.forEach(function (panel) {
            panel.style.display = panel.getAttribute('data-wo-id') === selectedWoId ? 'block' : 'none';
        });
    }

    if (woSelect && woLogsPanels.length) {
        woSelect.addEventListener('change', showLogsPanelForSelectedWO);
        showLogsPanelForSelectedWO();
    }

    var activeCategory = '';

    function applyFilters() {
        var search = (searchInput.value || '').toLowerCase().trim();

        pickerCards.forEach(function (card) {
            var matchesCategory = activeCategory === '' || card.getAttribute('data-category') === activeCategory;
            var matchesSearch = search === '' || card.getAttribute('data-search').indexOf(search) !== -1;
            card.classList.toggle('hidden-by-filter', !(matchesCategory && matchesSearch));
        });
    }

    if (chipRow) {
        chipRow.querySelectorAll('.chip').forEach(function (chip) {
            chip.addEventListener('click', function () {
                chipRow.querySelectorAll('.chip').forEach(function (c) { c.classList.remove('active'); });
                chip.classList.add('active');
                activeCategory = chip.getAttribute('data-category') || '';
                applyFilters();
            });
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', applyFilters);
    }

    function resetUsageInputs() {
        qtyGroup.style.display = 'none';
        qtyInput.disabled = true;
        qtyInput.required = false;
        qtyInput.value = '';

        snSelects.forEach(function (sel) {
            sel.style.display = 'none';
            sel.disabled = true;
            sel.required = false;
        });

        snEmptyHint.style.display = 'none';
    }

    function onMaterialSelected(radio) {
        pickerCards.forEach(function (card) { card.classList.remove('selected'); });
        radio.closest('.material-picker-card').classList.add('selected');

        resetUsageInputs();
        usageSection.style.display = 'block';

        var tracking = radio.getAttribute('data-tracking');
        var description = radio.getAttribute('data-description');

        if (tracking === 'quantity') {
            usageLabel.textContent = 'Jumlah Digunakan — ' + description;
            qtyGroup.style.display = 'block';
            qtyInput.disabled = false;
            qtyInput.required = true;
            var stock = parseFloat(radio.getAttribute('data-stock')) || 0;
            var unit = radio.getAttribute('data-unit');
            qtyInput.max = stock;
            qtyStockHint.textContent = 'Sisa stok: ' + stock + ' ' + unit;
            qtyStockHint.classList.toggle('stock-hint-warning', stock <= 0);
        } else {
            usageLabel.textContent = 'Pilih SN — ' + description;
            var matchingSelect = null;
            snSelects.forEach(function (sel) {
                if (sel.getAttribute('data-material-id') === radio.value) {
                    matchingSelect = sel;
                }
            });

            if (matchingSelect && matchingSelect.options.length > 1) {
                matchingSelect.style.display = 'block';
                matchingSelect.disabled = false;
                matchingSelect.required = true;
            } else {
                snEmptyHint.style.display = 'block';
            }
        }

        submitBtn.disabled = false;
    }

    materialRadios.forEach(function (radio) {
        radio.addEventListener('change', function () { onMaterialSelected(radio); });
    });
});

// log-usage.js
// Phase 6 -- progressive-enhancement helpers for the teknisi Log Usage
// screen. No framework; plain DOM events, matching materials.js.
//
// Multi-log UX pass: material picking is now checkboxes instead of a
// radio group, and each card's qty/SN input lives inline right below
// that card (toggled per-item) instead of one shared block at the
// bottom of the list. Submit is enabled once at least one material is
// checked; each checked item's own input carries `required` so the
// browser blocks submit on incomplete rows without extra JS.

document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('material-form');
    if (!form) return; // no assigned open WOs -- form isn't rendered

    var searchInput = document.getElementById('material-search');
    var chipRow = document.getElementById('material-category-chips');
    var pickerItems = document.querySelectorAll('.material-picker-item');
    var materialCheckboxes = document.querySelectorAll('.material-picker-checkbox');

    var summaryText = document.getElementById('log-usage-summary-text');
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

        pickerItems.forEach(function (item) {
            var matchesCategory = activeCategory === '' || item.getAttribute('data-category') === activeCategory;
            var matchesSearch = search === '' || item.getAttribute('data-search').indexOf(search) !== -1;
            item.classList.toggle('hidden-by-filter', !(matchesCategory && matchesSearch));
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

    function updateSummary() {
        var checked = document.querySelectorAll('.material-picker-checkbox:checked');
        var count = checked.length;

        submitBtn.disabled = count === 0;
        summaryText.textContent = count === 0
            ? 'Belum ada material dipilih'
            : count + ' material dipilih';
    }

    function onMaterialToggled(checkbox) {
        var materialId = checkbox.getAttribute('data-material-id');
        var card = checkbox.closest('.material-picker-card');
        var inlineSection = document.getElementById('usage-input-' + materialId);

        card.classList.toggle('selected', checkbox.checked);

        if (!inlineSection) {
            updateSummary();
            return;
        }

        var qtyInput = inlineSection.querySelector('.qty-input-inline');
        var snSelect = inlineSection.querySelector('.sn-select-inline');

        if (checkbox.checked) {
            inlineSection.style.display = 'block';
            if (qtyInput) {
                qtyInput.disabled = false;
                qtyInput.required = true;
            }
            if (snSelect) {
                snSelect.disabled = false;
                snSelect.required = true;
            }
        } else {
            inlineSection.style.display = 'none';
            if (qtyInput) {
                qtyInput.disabled = true;
                qtyInput.required = false;
                qtyInput.value = '';
            }
            if (snSelect) {
                snSelect.disabled = true;
                snSelect.required = false;
                snSelect.value = '';
            }
        }

        updateSummary();
    }

    materialCheckboxes.forEach(function (checkbox) {
        checkbox.addEventListener('change', function () { onMaterialToggled(checkbox); });
    });

    updateSummary();
});

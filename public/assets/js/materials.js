// materials.js
// Phase 4 -- small progressive-enhancement helpers for the material form.
// No framework; plain DOM events, matching the rest of this mockup.

document.addEventListener('DOMContentLoaded', function () {
    var categorySelect = document.getElementById('category_id');
    var newCategoryFields = document.getElementById('new-category-fields');

    function toggleNewCategoryFields() {
        if (!categorySelect || !newCategoryFields) return;
        newCategoryFields.style.display = categorySelect.value === '__new__' ? 'block' : 'none';
    }

    if (categorySelect) {
        categorySelect.addEventListener('change', toggleNewCategoryFields);
        toggleNewCategoryFields();
    }

    // Only present on the "create" form (edit form doesn't render tracking_type radios).
    var trackingRadios = document.querySelectorAll('input[name="tracking_type"]');
    var initialStockField = document.getElementById('initial-stock-field');
    var serialStockHint = document.getElementById('serial-stock-hint');

    function toggleStockFields() {
        if (!trackingRadios.length) return;
        var selected = document.querySelector('input[name="tracking_type"]:checked');
        var isQuantity = selected && selected.value === 'quantity';

        if (initialStockField) initialStockField.style.display = isQuantity ? 'block' : 'none';
        if (serialStockHint) serialStockHint.style.display = isQuantity ? 'none' : (selected ? 'block' : 'none');
    }

    trackingRadios.forEach(function (radio) {
        radio.addEventListener('change', toggleStockFields);
    });
    toggleStockFields();
});

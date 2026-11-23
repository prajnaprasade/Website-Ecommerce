// File ini disiapkan jika nanti kamu butuh animasi tambahan
$(document).ready(function(){
    console.log("Hogwarts Shop siap dijalankan!");

    function updateCartSelection() {
        var checkboxes = Array.prototype.slice.call(document.querySelectorAll('.cart-item-checkbox'));
        var selectAll = document.getElementById('cartSelectAll');
        var total = document.getElementById('cartSelectedTotal');
        var count = document.getElementById('cartSelectedCount');
        var checkoutButton = document.getElementById('cartCheckoutButton');
        if (!checkboxes.length || !total || !count || !checkoutButton) {
            return;
        }

        var selectedTotal = 0;
        var selectedCount = 0;
        checkboxes.forEach(function (checkbox) {
            if (checkbox.checked) {
                selectedTotal += Number(checkbox.dataset.subtotal) || 0;
                selectedCount += 1;
            }
        });

        total.textContent = 'Rp ' + selectedTotal.toLocaleString('id-ID');
        count.textContent = String(selectedCount);
        checkoutButton.disabled = selectedCount === 0;
        if (selectAll) {
            selectAll.checked = selectedCount === checkboxes.length;
            selectAll.indeterminate = false;
        }
    }

    document.addEventListener('change', function (event) {
        if (event.target.id === 'cartSelectAll') {
            document.querySelectorAll('.cart-item-checkbox').forEach(function (checkbox) {
                checkbox.checked = event.target.checked;
            });
            updateCartSelection();
        } else if (event.target.classList.contains('cart-item-checkbox')) {
            updateCartSelection();
        }
    });

    updateCartSelection();

});
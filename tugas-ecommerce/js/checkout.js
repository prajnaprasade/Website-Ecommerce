(function () {
    'use strict';

    var form = document.querySelector('form[action="checkout.php"]');
    var cityInput = document.getElementById('checkoutCity');
    var stateInput = document.getElementById('checkoutState');
    var zipInput = document.getElementById('checkoutZip');
    var addressInput = document.getElementById('checkoutAddress');
    var nameInput = document.getElementById('checkoutName');
    var phoneInput = document.getElementById('checkoutPhone');
    var savedAddressSelect = document.getElementById('checkoutSavedAddress');
    var savedAddressIdInput = document.getElementById('savedAddressId');
    var areaIdInput = document.getElementById('destinationAreaId');
    var rateIdInput = document.getElementById('shippingRateId');
    var areaResults = document.getElementById('shippingAreaResults');
    var selectedArea = document.getElementById('selectedShippingArea');
    var rateOptions = document.getElementById('shippingRateOptions');
    var rateMessage = document.getElementById('shippingRateMessage');
    var searchAreasButton = document.getElementById('searchShippingAreas');
    var submitButton = document.getElementById('processCheckout');
    var shippingCostDisplay = document.getElementById('shippingCostDisplay');
    var grandTotalDisplay = document.getElementById('grandTotalDisplay');
    var subtotalElement = document.getElementById('subtotalAmount');
    var csrfInput = form ? form.querySelector('input[name="csrf_token"]') : null;
    var checkoutReady = submitButton && submitButton.dataset.checkoutReady === '1';

    if (!form || !cityInput || !stateInput || !zipInput || !addressInput || !areaIdInput ||
        !nameInput || !phoneInput || !savedAddressIdInput || !rateIdInput || !areaResults || !selectedArea || !rateOptions || !rateMessage ||
        !searchAreasButton || !submitButton || !csrfInput) {
        return;
    }

    function formatCurrency(amount) {
        return 'Rp ' + Number(amount).toLocaleString('id-ID');
    }

    function request(data) {
        var body = new URLSearchParams();
        Object.keys(data).forEach(function (key) {
            body.append(key, data[key]);
        });
        return fetch('shipping-options.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            credentials: 'same-origin',
            body: body.toString()
        }).then(function (response) {
            return response.json().then(function (payload) {
                if (!response.ok) {
                    throw new Error(payload.error || 'Permintaan ongkir gagal.');
                }
                return payload;
            });
        });
    }

    function clearRates() {
        rateIdInput.value = '';
        rateOptions.textContent = '';
        rateMessage.textContent = '';
        shippingCostDisplay.textContent = 'Pilih layanan kurir';
        grandTotalDisplay.textContent = formatCurrency(subtotalElement.dataset.amount);
        submitButton.disabled = true;
        submitButton.textContent = 'Pilih Kurir untuk Melanjutkan';
    }

    function clearAreaSelection() {
        savedAddressIdInput.value = '0';
        areaIdInput.value = '';
        selectedArea.textContent = '';
        areaResults.textContent = '';
        clearRates();
    }

    function selectSavedAddress() {
        var option = savedAddressSelect && savedAddressSelect.selectedOptions[0];
        if (!option || option.value === '0') {
            [nameInput, phoneInput, addressInput, cityInput, stateInput, zipInput].forEach(function (input) {
                input.value = '';
            });
            clearAreaSelection();
            return;
        }

        nameInput.value = option.dataset.name || '';
        phoneInput.value = option.dataset.phone || '';
        addressInput.value = option.dataset.address || '';
        cityInput.value = option.dataset.city || '';
        stateInput.value = option.dataset.state || '';
        zipInput.value = option.dataset.zip || '';
        savedAddressIdInput.value = option.value;
        areaIdInput.value = option.dataset.areaId || '';
        areaResults.textContent = '';
        selectedArea.textContent = 'Lokasi tujuan dimuat dari alamat tersimpan.';
        loadShippingRates();
    }

    function locationQuery() {
        return [cityInput.value.trim(), stateInput.value.trim(), zipInput.value.trim()]
            .filter(Boolean)
            .join(', ');
    }

    function loadShippingRates() {
        clearRates();
        rateMessage.className = 'small mt-2 text-muted';
        rateMessage.textContent = 'Mengambil tarif kurir aktual...';
        request({
            action: 'get_rates',
            destination_area_id: areaIdInput.value,
            address: addressInput.value.trim(),
            city: cityInput.value.trim(),
            state: stateInput.value.trim(),
            zip: zipInput.value.trim(),
            saved_address_id: savedAddressIdInput.value,
            csrf_token: csrfInput.value
        }).then(function (payload) {
            rateMessage.textContent = 'Pilih kurir dan layanan pengiriman:';
            payload.options.forEach(function (option) {
                var label = document.createElement('label');
                label.className = 'shipping-method-option d-flex align-items-center justify-content-between border rounded p-3 mb-2';
                var choice = document.createElement('input');
                choice.type = 'radio';
                choice.name = 'shipping_rate_choice';
                choice.value = option.rate_id;
                choice.addEventListener('change', function () {
                    rateIdInput.value = option.rate_id;
                    shippingCostDisplay.textContent = formatCurrency(option.price);
                    grandTotalDisplay.textContent = formatCurrency(
                        Number(subtotalElement.dataset.amount) + Number(option.price)
                    );
                    submitButton.disabled = !checkoutReady;
                    submitButton.textContent = 'Lanjut ke Pembayaran';
                });

                var details = document.createElement('span');
                details.textContent = option.company + ' · ' + option.service +
                    (option.duration ? ' · ' + option.duration : '');
                var price = document.createElement('strong');
                price.className = 'ml-2 text-nowrap';
                price.textContent = formatCurrency(option.price);
                label.appendChild(choice);
                label.appendChild(details);
                label.appendChild(price);
                rateOptions.appendChild(label);
            });
        }).catch(function (error) {
            rateMessage.textContent = error.message;
            rateMessage.className = 'small mt-2 text-danger';
        });
    }

    searchAreasButton.addEventListener('click', function () {
        var query = locationQuery();
        areaResults.textContent = '';
        rateMessage.textContent = 'Mencari area pengiriman...';
        clearRates();
        if (query.length < 3) {
            rateMessage.textContent = 'Isi kecamatan/kota dan kode pos sebelum mencari.';
            return;
        }

        searchAreasButton.disabled = true;
        request({ action: 'search_areas', query: query, csrf_token: csrfInput.value })
            .then(function (payload) {
                rateMessage.textContent = '';
                payload.areas.forEach(function (area) {
                    var option = document.createElement('button');
                    option.type = 'button';
                    option.className = 'btn btn-light border btn-block text-left mb-1';
                    option.setAttribute('role', 'option');
                    option.textContent = area.name + (area.postal_code ? ' · ' + area.postal_code : '');
                    option.addEventListener('click', function () {
                        if (area.postal_code) {
                            zipInput.value = area.postal_code;
                        }
                        areaIdInput.value = area.id;
                        selectedArea.textContent = 'Lokasi kurir dipilih: ' + option.textContent;
                        areaResults.textContent = '';
                        loadShippingRates();
                    });
                    areaResults.appendChild(option);
                });
            })
            .catch(function (error) {
                rateMessage.textContent = error.message;
                rateMessage.className = 'small mt-2 text-danger';
            })
            .finally(function () {
                searchAreasButton.disabled = false;
            });
    });

    [nameInput, phoneInput, cityInput, stateInput, zipInput, addressInput].forEach(function (input) {
        input.addEventListener('input', clearAreaSelection);
    });

    if (savedAddressSelect) {
        savedAddressSelect.addEventListener('change', selectSavedAddress);
        if (savedAddressSelect.value !== '0') {
            selectSavedAddress();
        }
    }

    form.addEventListener('submit', function (event) {
        if (!rateIdInput.value) {
            event.preventDefault();
            rateMessage.textContent = 'Pilih layanan kurir dan ongkir terlebih dahulu.';
            rateMessage.className = 'small mt-2 text-danger';
        }
    });
})();

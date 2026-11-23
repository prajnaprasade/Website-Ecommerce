document.addEventListener('DOMContentLoaded', function () {
    var tabs = Array.prototype.slice.call(document.querySelectorAll('[data-user-tab]'));
    var panels = Array.prototype.slice.call(document.querySelectorAll('[data-user-panel]'));
    var sidebar = document.getElementById('userDashboardSidebar');
    var sidebarToggle = document.getElementById('userSidebarToggle');
    var addressForm = document.getElementById('userAddressForm');
    var addressModal = document.getElementById('userAddressModal');
    var profileAreaSearch = document.getElementById('searchProfileShippingArea');
    var profileAreaResults = document.getElementById('profileShippingAreaResults');
    var profileSelectedArea = document.getElementById('profileSelectedShippingArea');
    var profileAreaId = document.getElementById('profileAddressAreaId');
    var profileShippingCsrf = document.getElementById('addressShippingCsrf');
    var addressCity = document.getElementById('addressCity');
    var addressState = document.getElementById('addressState');
    var addressPostal = document.getElementById('addressPostal');

    function showFeedback(element, message, isError) {
        if (!element) {
            return;
        }

        element.textContent = message;
        element.classList.toggle('is-error', Boolean(isError));
        element.hidden = false;
    }

    function activateTab(tabName, updateHash) {
        var targetTab = tabs.find(function (tab) {
            return tab.getAttribute('data-user-tab') === tabName;
        });

        if (!targetTab) {
            return;
        }

        tabs.forEach(function (tab) {
            var isActive = tab === targetTab;
            tab.classList.toggle('active', isActive);
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
            tab.setAttribute('tabindex', isActive ? '0' : '-1');
        });

        panels.forEach(function (panel) {
            var isActive = panel.getAttribute('data-user-panel') === tabName;
            panel.classList.toggle('active', isActive);
            panel.hidden = !isActive;
        });

        if (updateHash) {
            window.history.replaceState(null, '', '#akun-' + tabName);
        }

        if (sidebar) {
            sidebar.classList.remove('is-open');
        }
        if (sidebarToggle) {
            sidebarToggle.setAttribute('aria-expanded', 'false');
        }
    }

    tabs.forEach(function (tab, index) {
        tab.setAttribute('role', 'tab');
        tab.setAttribute('tabindex', index === 0 ? '0' : '-1');
        tab.addEventListener('click', function () {
            activateTab(tab.getAttribute('data-user-tab'), true);
        });
        tab.addEventListener('keydown', function (event) {
            var nextIndex = index;
            if (event.key === 'ArrowDown' || event.key === 'ArrowRight') {
                nextIndex = (index + 1) % tabs.length;
            } else if (event.key === 'ArrowUp' || event.key === 'ArrowLeft') {
                nextIndex = (index - 1 + tabs.length) % tabs.length;
            } else {
                return;
            }
            event.preventDefault();
            tabs[nextIndex].focus();
            activateTab(tabs[nextIndex].getAttribute('data-user-tab'), true);
        });
    });

    var initialTab = window.location.hash.indexOf('#akun-') === 0
        ? window.location.hash.substring(6)
        : 'personal';
    activateTab(initialTab, false);

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function () {
            var isOpen = sidebar.classList.toggle('is-open');
            sidebarToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    }

    var filterButtons = Array.prototype.slice.call(document.querySelectorAll('[data-order-filter]'));
    var orderCards = Array.prototype.slice.call(document.querySelectorAll('.user-order-card'));
    var filterEmpty = document.getElementById('orderFilterEmpty');
    var orderEmptyState = document.querySelector('[data-user-panel="orders"] .profile-empty-state');
    filterButtons.forEach(function (button) {
        button.setAttribute('aria-pressed', button.classList.contains('active') ? 'true' : 'false');
        button.addEventListener('click', function () {
            var filter = button.getAttribute('data-order-filter');
            var visibleCount = 0;
            filterButtons.forEach(function (filterButton) {
                filterButton.classList.toggle('active', filterButton === button);
                filterButton.setAttribute('aria-pressed', filterButton === button ? 'true' : 'false');
            });
            orderCards.forEach(function (card) {
                var visible = filter === 'all' || card.getAttribute('data-order-status') === filter;
                card.hidden = !visible;
                if (visible) {
                    visibleCount += 1;
                }
            });
            if (filterEmpty) {
                filterEmpty.hidden = filter === 'all' || visibleCount > 0;
            }
            if (orderEmptyState) {
                orderEmptyState.hidden = filter !== 'all';
            }
        });
    });

    function resetAddressForm() {
        if (addressForm) {
            addressForm.reset();
        }
        if (document.getElementById('addressId')) {
            document.getElementById('addressId').value = '0';
        }
        if (profileAreaId) {
            profileAreaId.value = '';
        }
        if (profileAreaResults) {
            profileAreaResults.textContent = '';
        }
        if (profileSelectedArea) {
            profileSelectedArea.textContent = '';
        }
        var modalTitle = document.getElementById('userAddressModalTitle');
        var submitButton = addressForm ? addressForm.querySelector('[type="submit"]') : null;
        if (modalTitle) {
            modalTitle.textContent = 'Tambah alamat baru';
        }
        if (submitButton) {
            submitButton.textContent = 'Simpan Alamat';
        }
    }

    function searchProfileAreas() {
        if (!profileAreaSearch || !profileAreaResults || !profileSelectedArea ||
            !profileShippingCsrf || !addressCity || !addressState || !addressPostal) {
            return;
        }
        var query = [addressCity.value.trim(), addressState.value.trim(), addressPostal.value.trim()]
            .filter(Boolean)
            .join(', ');
        profileAreaResults.textContent = '';
        profileSelectedArea.textContent = 'Mencari lokasi pengiriman...';
        if (query.length < 3) {
            profileSelectedArea.textContent = 'Isi kota, provinsi, dan kode pos sebelum mencari.';
            return;
        }

        profileAreaSearch.disabled = true;
        var body = new URLSearchParams();
        body.append('action', 'search_areas');
        body.append('query', query);
        body.append('csrf_token', profileShippingCsrf.value);
        fetch('shipping-options.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            credentials: 'same-origin',
            body: body.toString()
        }).then(function (response) {
            return response.json().then(function (payload) {
                if (!response.ok) {
                    throw new Error(payload.error || 'Lokasi tidak dapat dicari.');
                }
                return payload;
            });
        }).then(function (payload) {
            profileSelectedArea.textContent = '';
            payload.areas.forEach(function (area) {
                var option = document.createElement('button');
                option.type = 'button';
                option.className = 'btn btn-light border btn-block text-left mb-1';
                option.setAttribute('role', 'option');
                option.textContent = area.name + (area.postal_code ? ' · ' + area.postal_code : '');
                option.addEventListener('click', function () {
                    if (area.postal_code) {
                        addressPostal.value = area.postal_code;
                    }
                    profileAreaId.value = area.id;
                    profileSelectedArea.textContent = 'Lokasi dipilih: ' + option.textContent;
                    profileAreaResults.textContent = '';
                });
                profileAreaResults.appendChild(option);
            });
            if (!payload.areas.length) {
                profileSelectedArea.textContent = 'Lokasi tidak ditemukan. Periksa kota dan kode pos.';
            }
        }).catch(function (error) {
            profileSelectedArea.textContent = error.message;
        }).finally(function () {
            profileAreaSearch.disabled = false;
        });
    }

    if (profileAreaSearch) {
        profileAreaSearch.addEventListener('click', searchProfileAreas);
    }
    [addressCity, addressState, addressPostal].forEach(function (input) {
        if (input) {
            input.addEventListener('input', function () {
                if (profileAreaId) {
                    profileAreaId.value = '';
                }
                if (profileAreaResults) {
                    profileAreaResults.textContent = '';
                }
                if (profileSelectedArea) {
                    profileSelectedArea.textContent = '';
                }
            });
        }
    });

    document.addEventListener('click', function (event) {
        var target = event.target;
        var editButton = target.closest('[data-address-edit]');
        var addButton = target.closest('[data-toggle="modal"][data-target="#userAddressModal"]');
        var trackButton = target.closest('[data-order-track]');
        var demoButton = target.closest('[data-demo-action]');
        if (addButton && !editButton) {
            resetAddressForm();
        }

        if (editButton) {
            var addressCard = editButton.closest('.user-address-card');
            if (addressCard && addressForm) {
                document.getElementById('addressId').value = addressCard.dataset.addressId || '0';
                document.getElementById('addressRecipient').value = addressCard.dataset.addressName || '';
                document.getElementById('addressPhone').value = addressCard.dataset.addressPhone || '';
                document.getElementById('addressDetail').value = addressCard.dataset.addressStreet || '';
                addressCity.value = addressCard.dataset.addressCity || '';
                addressState.value = addressCard.dataset.addressState || '';
                addressPostal.value = addressCard.dataset.addressPostal || '';
                profileAreaId.value = addressCard.dataset.addressAreaId || '';
                document.getElementById('addressIsDefault').checked = Boolean(addressCard.querySelector('.user-main-address'));
                profileSelectedArea.textContent = 'Lokasi tersimpan: ' + addressCity.value + ', ' + addressState.value + ' ' + addressPostal.value;
                document.getElementById('userAddressModalTitle').textContent = 'Edit alamat';
                addressForm.querySelector('[type="submit"]').textContent = 'Simpan Perubahan';
                if (window.jQuery) {
                    window.jQuery(addressModal).modal('show');
                }
            }
        }

        if (trackButton) {
            window.alert('Informasi pelacakan belum tersedia. Silakan hubungi CS toko untuk status pengiriman.');
        }

        if (demoButton) {
            window.alert(demoButton.getAttribute('data-demo-action'));
        }

    });

    if (addressModal) {
        addressModal.addEventListener('hidden.bs.modal', resetAddressForm);
    }

    if (addressForm && addressForm.dataset.openOnLoad === '1' && addressModal && window.jQuery) {
        var savedAddressId = document.getElementById('addressId');
        if (savedAddressId && Number(savedAddressId.value) > 0) {
            document.getElementById('userAddressModalTitle').textContent = 'Edit alamat';
            addressForm.querySelector('[type="submit"]').textContent = 'Simpan Perubahan';
        }
        if (profileAreaId && profileAreaId.value && profileSelectedArea) {
            profileSelectedArea.textContent = 'Lokasi yang dipilih sebelumnya.';
        }
        window.jQuery(addressModal).modal('show');
    }

    if (addressForm) {
        addressForm.addEventListener('submit', function (event) {
            if (!profileAreaId || !profileAreaId.value) {
                event.preventDefault();
                showFeedback(document.getElementById('profileSelectedShippingArea'), 'Pilih lokasi dari hasil pencarian terlebih dahulu.', true);
                return;
            }
        });
    }

    document.addEventListener('submit', function (event) {
        if (event.target.matches('.user-address-delete-form') &&
            !window.confirm('Hapus alamat ini dari akun?')) {
            event.preventDefault();
        }
    });

    var passwordForm = document.getElementById('userPasswordForm');
    if (passwordForm) {
        passwordForm.addEventListener('submit', function (event) {
            event.preventDefault();
            var newPassword = document.getElementById('newPassword');
            var confirmation = document.getElementById('confirmPassword');
            if (newPassword.value !== confirmation.value) {
                showFeedback(document.getElementById('passwordFeedback'), 'Konfirmasi kata sandi belum sama.', true);
                confirmation.focus();
                return;
            }
            showFeedback(document.getElementById('passwordFeedback'), 'Form valid. Perubahan kata sandi tidak disimpan karena bagian ini masih berupa demo.', false);
            passwordForm.reset();
        });
    }

    var twoFactorToggle = document.getElementById('twoFactorToggle');
    if (twoFactorToggle) {
        twoFactorToggle.addEventListener('change', function () {
            var feedback = document.getElementById('twoFactorFeedback');
            showFeedback(
                feedback,
                twoFactorToggle.checked
                    ? 'Status 2FA berubah untuk demo ini saja; fitur belum terhubung ke autentikasi.'
                    : 'Autentikasi dua langkah belum diaktifkan.',
                false
            );
        });
    }
});

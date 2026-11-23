(function () {
    'use strict';

    var panel      = document.getElementById('supportBotPanel');
    var launcher   = document.getElementById('supportBotLauncher');
    var closeBtn   = document.getElementById('supportBotClose');
    var messages   = document.getElementById('supportBotMessages');
    var form       = document.getElementById('supportBotForm');
    var input      = document.getElementById('supportBotInput');
    var basePath   = window.location.pathname.replace(/[^/]*$/, '');
    var chatbotUrl = basePath + 'chatbot.php';

    if (!panel || !launcher || !closeBtn || !messages || !form || !input) {
        return;
    }

    // Riwayat percakapan (maks 10 pesan terakhir dikirim ke server)
    var history = [];

    // --- Utility ---
    function escapeHtml(text) {
        return text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    // Render supported markdown without allowing untrusted text to become markup.
    function renderMarkdown(text) {
        var escaped = escapeHtml(text);
        // Bold
        escaped = escaped.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        // Italic
        escaped = escaped.replace(/\*(.+?)\*/g, '<em>$1</em>');
        // Keep list text in one grid cell so inline formatting cannot split into flex items.
        escaped = escaped.replace(/^(\d+)\.\s+(.+)$/gm, '<span class="bot-list-item"><span class="bot-list-num">$1.</span><span class="bot-list-content">$2</span></span>');
        escaped = escaped.replace(/^[-*]\s+(.+)$/gm, '<span class="bot-list-item bot-list-bullet"><span class="bot-list-num" aria-hidden="true"></span><span class="bot-list-content">$1</span></span>');
        // Baris baru
        escaped = escaped.replace(/\n/g, '<br>');
        return escaped;
    }

    // Tambahkan gelembung pesan ke panel
    function appendMessage(text, sender, isTyping) {
        var bubble = document.createElement('div');
        bubble.className = 'support-bot-message support-bot-message-' + sender;

        if (isTyping) {
            bubble.className += ' support-bot-typing';
            bubble.innerHTML =
                '<span class="typing-dot"></span>' +
                '<span class="typing-dot"></span>' +
                '<span class="typing-dot"></span>';
        } else if (sender === 'assistant') {
            bubble.innerHTML = renderMarkdown(text);
        } else {
            bubble.textContent = text;
        }

        messages.appendChild(bubble);
        messages.scrollTop = messages.scrollHeight;
        return bubble;
    }

    // Kirim pesan ke chatbot.php dan tampilkan balasan
    function submitQuestion(question) {
        var trimmed = question.trim();
        if (!trimmed) return;

        // Nonaktifkan form saat menunggu
        input.disabled = true;
        form.querySelector('button[type="submit"]').disabled = true;

        appendMessage(trimmed, 'user', false);
        input.value = '';

        // Tambahkan ke history lokal
        history.push({ role: 'user', text: trimmed });

        // Tampilkan animasi typing
        var typingBubble = appendMessage('', 'assistant', true);

        // Kirim ke endpoint PHP
        fetch(chatbotUrl, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({
                message: trimmed,
                history: history.slice(0, -1).slice(-10)   // kirim maks 10 pesan sebelumnya
            })
        })
        .then(function (res) {
                return res.json().then(function (data) {
                    if (!res.ok) {
                        var requestError = new Error(data.error || 'Gemini gagal menjawab. Coba lagi sebentar.');
                        requestError.retryAfter = data.retry_after;
                        throw requestError;
                    }
                    return data;
                });
        })
        .then(function (data) {
                typingBubble.remove();

                var reply = data.reply || 'Maaf, Lumos belum menerima jawaban. Coba lagi sebentar.';
                appendMessage(reply, 'assistant', false);

            // Simpan balasan AI ke history
            history.push({ role: 'assistant', text: reply });

            // Jaga history tetap ringkas
            if (history.length > 20) {
                history = history.slice(-20);
            }
        })
        .catch(function (error) {
            typingBubble.remove();
            var errorMessage = error && error.message
                ? error.message
                : 'Koneksi terputus. Pastikan server berjalan dan coba lagi.';
            if (error && error.retryAfter) {
                errorMessage += ' Coba lagi dalam sekitar ' + error.retryAfter + ' detik; tidak perlu memuat ulang halaman.';
            }
            appendMessage(errorMessage, 'assistant', false);
        })
        .finally(function () {
            input.disabled = false;
            form.querySelector('button[type="submit"]').disabled = false;
            input.focus();
        });
    }

    // --- Panel buka/tutup ---
    function setPanelOpen(isOpen) {
        panel.hidden = !isOpen;
        launcher.setAttribute('aria-expanded', String(isOpen));
        if (isOpen) {
            input.focus();
        } else {
            launcher.focus();
        }
    }

    launcher.addEventListener('click', function () {
        setPanelOpen(panel.hidden);
    });

    closeBtn.addEventListener('click', function () {
        setPanelOpen(false);
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        submitQuestion(input.value);
    });

    // Tombol-tombol pertanyaan cepat
    document.querySelectorAll('[data-support-question]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            submitQuestion(btn.getAttribute('data-support-question'));
        });
    });

    // Tutup dengan Escape
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !panel.hidden) {
            setPanelOpen(false);
        }
    });
})();

(function () {
    const config = window.__CHAT_CONFIG__;
    if (!config || !config.apiUrl || !config.myId || !config.storageKey) {
        return;
    }

    const state = {
        contacts: [],
        active: null,
        lastSeen: {},
        pollTimer: null
    };

    const elements = {
        contacts: document.getElementById('chatContacts'),
        target: document.getElementById('chatTarget'),
        messages: document.getElementById('chatMessages'),
        input: document.getElementById('chatInput'),
        send: document.getElementById('chatSend')
    };

    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[char]));

    function hasChatUi() {
        return Boolean(elements.contacts && elements.target && elements.messages);
    }

    function loadSeenState() {
        try {
            state.lastSeen = JSON.parse(localStorage.getItem(config.storageKey) || '{}') || {};
        } catch (error) {
            state.lastSeen = {};
        }
    }

    function saveSeenState() {
        localStorage.setItem(config.storageKey, JSON.stringify(state.lastSeen));
    }

    function buildApiUrl(query) {
        const base = new URL(config.apiUrl, window.location.href);
        if (query) {
            base.search = query;
        }
        return base.toString();
    }

    function requestJson(url, options = {}) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open(options.method || 'GET', url, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

            if (options.headers) {
                Object.entries(options.headers).forEach(([name, value]) => {
                    xhr.setRequestHeader(name, value);
                });
            }

            xhr.onreadystatechange = function () {
                if (xhr.readyState !== 4) {
                    return;
                }

                const raw = xhr.responseText || '';
                const cleanRaw = raw.replace(/^\uFEFF/, '').trim();
                let data = {};

                try {
                    data = cleanRaw ? JSON.parse(cleanRaw) : {};
                } catch (error) {
                    reject(new Error(cleanRaw || 'RÃ©ponse JSON invalide'));
                    return;
                }

                if (xhr.status < 200 || xhr.status >= 300) {
                    reject(new Error(data.error || `Erreur HTTP ${xhr.status}`));
                    return;
                }

                resolve(data);
            };

            xhr.onerror = function () {
                reject(new Error('RequÃªte AJAX impossible'));
            };

            xhr.send(options.body || null);
        });
    }

    function renderChatBadge(count) {
        document.querySelectorAll('[data-chat-badge]').forEach((badge) => {
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : String(count);
                badge.classList.remove('hidden');
                badge.classList.add('inline-flex');
            } else {
                badge.textContent = '';
                badge.classList.add('hidden');
                badge.classList.remove('inline-flex');
            }
        });
    }

    function renderContacts() {
        if (!elements.contacts) {
            return;
        }

        if (!state.contacts.length) {
            elements.contacts.innerHTML = '<p class="p-3 text-xs text-gray-400">Aucun contact.</p>';
            return;
        }

        elements.contacts.innerHTML = state.contacts.map((contact) => {
            const id = String(contact.matricule);
            const seen = parseInt(state.lastSeen[id] || 0, 10);
            const lastMessageId = parseInt(contact.last_message_id || 0, 10);
            const unseen = seen < lastMessageId && String(contact.last_sender_id || '') !== String(config.myId);
            const isActive = state.active === id;

            return `
                <button data-id="${esc(id)}"
                    class="w-full text-left px-3 py-3 border-b border-fuchsia-100 dark:border-gray-700 transition flex justify-between items-center gap-2 ${isActive ? 'bg-fuchsia-100 dark:bg-fuchsia-900/30' : 'hover:bg-fuchsia-100 dark:hover:bg-fuchsia-900/20'}">
                    <span>
                        <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">${esc(contact.label)}</p>
                        <p class="text-xs text-gray-400">${esc(contact.role)}</p>
                    </span>
                    <span class="w-2 h-2 rounded-full bg-red-500 flex-shrink-0 ${unseen ? 'pulse-dot opacity-100' : 'opacity-0'}"></span>
                </button>
            `;
        }).join('');

        elements.contacts.querySelectorAll('button[data-id]').forEach((button) => {
            button.addEventListener('click', () => selectContact(button.dataset.id));
        });
    }

    function updateUnreadCount() {
        let unread = 0;

        state.contacts.forEach((contact) => {
            const id = String(contact.matricule);
            const seen = parseInt(state.lastSeen[id] || 0, 10);
            const lastMessageId = parseInt(contact.last_message_id || 0, 10);
            const isMine = String(contact.last_sender_id || '') === String(config.myId);

            if (seen < lastMessageId && !isMine) {
                unread += 1;
            }
        });

        renderChatBadge(unread);
    }

    function renderMessages(messages) {
        if (!elements.messages) {
            return;
        }

        const box = elements.messages;
        const wasNearBottom = box.scrollHeight - box.scrollTop <= box.clientHeight + 60;

        box.innerHTML = messages.map((message) => {
            const isMine = String(message.sender_id) === String(config.myId);

            if (!isMine) {
                const currentSeen = parseInt(state.lastSeen[state.active] || 0, 10);
                state.lastSeen[state.active] = Math.max(currentSeen, parseInt(message.id || 0, 10));
            }

            const time = message.created_at
                ? `<span class="text-[10px] opacity-60 ml-1">${esc(String(message.created_at).slice(11, 16))}</span>`
                : '';

            return isMine
                ? `<div class="flex justify-end"><div class="bg-primary text-white px-3 py-2 rounded-xl rounded-br-none text-sm max-w-[80%]">${esc(message.message || '')}${time}</div></div>`
                : `<div class="flex justify-start"><div class="bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 px-3 py-2 rounded-xl rounded-bl-none text-sm max-w-[80%]"><span class="font-semibold text-primary text-xs">${esc(`${message.sender_prenom || ''} ${message.sender_nom || ''}`.trim())}</span><br>${esc(message.message || '')}${time}</div></div>`;
        }).join('');

        saveSeenState();
        updateUnreadCount();

        if (wasNearBottom) {
            box.scrollTop = box.scrollHeight;
        }
    }

    async function loadContacts() {
        try {
            const data = await requestJson(buildApiUrl('action=contacts'));
            state.contacts = Array.isArray(data.contacts) ? data.contacts : [];

            if (state.active && !state.contacts.some((contact) => String(contact.matricule) === state.active)) {
                state.active = null;
                localStorage.removeItem(config.activeContactKey);
            }

            renderContacts();
            updateUnreadCount();
        } catch (error) {
            renderChatBadge(0);
            if (elements.contacts) {
                elements.contacts.innerHTML = `<p class="p-3 text-xs text-red-500">${esc(error.message || 'Chat indisponible.')}</p>`;
            }
        }
    }

    async function loadMessages() {
        if (!state.active || !elements.messages) {
            return;
        }

        try {
            const data = await requestJson(buildApiUrl(`action=messages&contact_id=${encodeURIComponent(state.active)}`));
            renderMessages(Array.isArray(data.messages) ? data.messages : []);
        } catch (error) {
            elements.messages.innerHTML = `<p class="text-sm text-red-500">${esc(error.message || 'Impossible de charger les messages.')}</p>`;
        }
    }

    async function selectContact(id) {
        state.active = String(id);
        localStorage.setItem(config.activeContactKey, state.active);

        const contact = state.contacts.find((item) => String(item.matricule) === state.active);
        if (elements.target) {
            elements.target.textContent = contact ? `Conversation avec ${contact.label}` : 'Conversation';
        }

        if (contact) {
            state.lastSeen[state.active] = parseInt(contact.last_message_id || 0, 10);
            saveSeenState();
        }

        renderContacts();
        await loadMessages();
    }

    async function sendMessage() {
        if (!elements.input || !state.active) {
            return;
        }

        const text = elements.input.value.trim();
        if (!text) {
            return;
        }

        elements.input.value = '';

        try {
            await requestJson(buildApiUrl('action=send'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: new URLSearchParams({
                    receiver_id: state.active,
                    message: text
                }).toString()
            });

            await loadMessages();
            await loadContacts();
        } catch (error) {
            elements.input.value = text;
        }
    }

    async function poll() {
        await loadContacts();
        if (state.active && hasChatUi()) {
            await loadMessages();
        }
    }

    function bindEvents() {
        if (elements.send) {
            elements.send.addEventListener('click', sendMessage);
        }

        if (elements.input) {
            elements.input.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    sendMessage();
                }
            });
        }
    }

    async function init() {
        loadSeenState();
        bindEvents();
        await loadContacts();

        if (hasChatUi()) {
            const savedContact = localStorage.getItem(config.activeContactKey);
            if (savedContact && state.contacts.some((contact) => String(contact.matricule) === String(savedContact))) {
                await selectContact(savedContact);
            }
        }

        if (state.pollTimer) {
            clearInterval(state.pollTimer);
        }

        state.pollTimer = window.setInterval(poll, 4000);
    }

    init();
})();

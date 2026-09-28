<?php
session_start();
include 'db.php';

if (!isset($_SESSION['matricule'])) {
    header("Location: connexion.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat | EltaRH</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com" onerror="this.onerror=null;this.src='../js/cdn.js';"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: { fuchsia700: '#C026D3' },
                    fontFamily: { poppins: ['Poppins', 'sans-serif'] }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-100 font-poppins min-h-screen">
<div class="h-screen p-4 md:p-6">

    <div id="usersOverlay" class="fixed inset-0 bg-black/50 hidden z-30 md:hidden"></div>

    <aside id="mobileUsersSidebar" class="fixed left-0 top-0 h-screen w-80 max-w-[85vw] bg-white dark:bg-gray-800 border-r border-gray-100 dark:border-gray-700 shadow-xl transform -translate-x-full transition-transform z-40 md:hidden overflow-hidden">
        <div class="p-5 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <h2 class="text-lg font-bold">Utilisateurs</h2>
            <button id="closeUsersMenu" class="p-2 rounded-xl bg-gray-100 dark:bg-gray-700" aria-label="Fermer">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <ul id="mobile-user-list" class="overflow-y-auto h-[calc(100%-72px)] p-2 space-y-1">
            <li class="text-sm text-gray-400 px-3 py-2">Chargement...</li>
        </ul>
    </aside>

    <div class="h-full max-w-7xl mx-auto grid md:grid-cols-[320px_1fr] gap-4">

        <aside class="hidden md:block bg-white dark:bg-gray-800 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-xl overflow-hidden">
            <div class="p-5 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h2 class="text-lg font-bold">Utilisateurs</h2>
                <button id="darkToggle" class="p-2 rounded-xl bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition" aria-label="Thème">
                    <i class="fa-solid fa-moon"></i>
                </button>
            </div>
            <ul id="user-list" class="overflow-y-auto h-[calc(100%-72px)] p-2 space-y-1">
                <li class="text-sm text-gray-400 px-3 py-2">Chargement...</li>
            </ul>
        </aside>

        <section class="bg-white dark:bg-gray-800 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-xl overflow-hidden flex flex-col">
            <header class="bg-fuchsia-700 text-white p-5 font-bold flex items-center justify-between gap-2">
                <span id="chat-header-title">Sélectionnez un utilisateur</span>
                <div class="flex items-center gap-2 md:hidden">
                    <button id="openUsersMenu" class="p-2 rounded-xl bg-white/20 hover:bg-white/30 transition" aria-label="Contacts">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                    <button id="darkToggleMobile" class="p-2 rounded-xl bg-white/20 hover:bg-white/30 transition" aria-label="Thème">
                        <i class="fa-solid fa-moon"></i>
                    </button>
                </div>
            </header>
            <div id="chat-box" class="flex-1 p-5 overflow-y-auto bg-gray-50 dark:bg-gray-900/30 space-y-2"></div>
            <div class="p-4 border-t border-gray-200 dark:border-gray-700 flex gap-2 bg-white dark:bg-gray-800">
                <input type="text" id="message-input" placeholder="Écrire un message"
                    class="flex-1 p-3 border rounded-xl bg-white dark:bg-gray-700 dark:border-gray-600 focus:outline-none focus:ring-2 focus:ring-fuchsia-600/40">
                <button onclick="sendMessage()" class="bg-fuchsia-700 text-white px-4 rounded-xl hover:bg-fuchsia-800 transition">
                    Envoyer
                </button>
            </div>
        </section>
    </div>
</div>

<script>
// Matricule de l'utilisateur connecté, injecté une seule fois depuis PHP
const MY_ID = '<?= htmlspecialchars((string) $_SESSION['matricule'], ENT_QUOTES, 'UTF-8') ?>';

let currentContactId = null;
let pollingTimer     = null;

// Échappe le HTML pour éviter les injections XSS côté JS
function esc(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// ── Contacts : GET chat_api.php?action=contacts ───────────────
async function loadContacts() {
    try {
        const res  = await fetch('chat_api.php?action=contacts');
        const data = await res.json();
        if (!Array.isArray(data.contacts)) return;

        const buildItem = (u, mobile = false) => `
            <li>
                <button class="w-full text-left p-3 rounded-xl hover:bg-fuchsia-50 dark:hover:bg-fuchsia-900/20 transition"
                    onclick="openConversation('${esc(u.matricule)}','${esc(u.label)}')${mobile ? '; toggleUsersMenu(false)' : ''}">
                    <p class="font-semibold text-sm">${esc(u.label)}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">${esc(u.role)}</p>
                </button>
            </li>`;

        const empty = '<li class="text-sm text-gray-400 px-3 py-2">Aucun contact disponible.</li>';
        document.getElementById('user-list').innerHTML        = data.contacts.length ? data.contacts.map(u => buildItem(u)).join('')       : empty;
        document.getElementById('mobile-user-list').innerHTML = data.contacts.length ? data.contacts.map(u => buildItem(u, true)).join('') : empty;
    } catch {
        document.getElementById('user-list').innerHTML = '<li class="text-sm text-red-500 px-3 py-2">Erreur de chargement.</li>';
    }
}

// ── Ouvrir une conversation ───────────────────────────────────
function openConversation(contactId, username) {
    currentContactId = contactId;
    document.getElementById('chat-header-title').textContent = 'Chat avec ' + username;
    document.getElementById('chat-box').innerHTML = '';
    clearInterval(pollingTimer);
    loadMessages();
    pollingTimer = setInterval(loadMessages, 2000);
}

// ── Messages : GET chat_api.php?action=messages ───────────────
async function loadMessages() {
    if (!currentContactId) return;
    try {
        const res  = await fetch(`chat_api.php?action=messages&contact_id=${encodeURIComponent(currentContactId)}`);
        const data = await res.json();
        if (Array.isArray(data.messages)) renderMessages(data.messages);
    } catch { /* silencieux */ }
}

// ── Rendu des bulles de messages ──────────────────────────────
function renderMessages(messages) {
    const chatBox     = document.getElementById('chat-box');
    const wasAtBottom = chatBox.scrollHeight - chatBox.scrollTop <= chatBox.clientHeight + 40;

    chatBox.innerHTML = messages.map(m => {
        const isMine  = String(m.sender_id) === MY_ID;
        const author  = esc(((m.sender_prenom ?? '') + ' ' + (m.sender_nom ?? '')).trim());
        const content = esc(m.message ?? '');
        const time    = m.created_at
            ? `<span class="text-[10px] opacity-60 ml-1">${esc(String(m.created_at).slice(11, 16))}</span>`
            : '';

        return isMine
            ? `<div class="flex justify-end">
                   <div class="bg-fuchsia-600 text-white p-3 rounded-xl rounded-br-none text-sm max-w-[75%]">
                       ${content}${time}
                   </div>
               </div>`
            : `<div class="flex justify-start">
                   <div class="bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 p-3 rounded-xl rounded-bl-none text-sm max-w-[75%]">
                       <span class="font-semibold text-fuchsia-700 dark:text-fuchsia-400">${author}</span><br>
                       ${content}${time}
                   </div>
               </div>`;
    }).join('');

    if (wasAtBottom) chatBox.scrollTop = chatBox.scrollHeight;
}

// ── Envoi : POST chat_api.php?action=send ────────────────────
async function sendMessage() {
    const input = document.getElementById('message-input');
    const msg   = input.value.trim();
    if (!msg || !currentContactId) return;
    input.value = '';
    try {
        await fetch('chat_api.php?action=send', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ receiver_id: currentContactId, message: msg })
        });
        loadMessages();
    } catch { /* silencieux */ }
}

document.getElementById('message-input').addEventListener('keydown', e => {
    if (e.key === 'Enter') sendMessage();
});

// ── Dark mode ─────────────────────────────────────────────────
const root = document.documentElement;
const darkButtons = [document.getElementById('darkToggle'), document.getElementById('darkToggleMobile')].filter(Boolean);

function syncThemeIcon() {
    const icon = root.classList.contains('dark') ? '<i class="fa-solid fa-sun"></i>' : '<i class="fa-solid fa-moon"></i>';
    darkButtons.forEach(btn => btn.innerHTML = icon);
}

if (localStorage.getItem('theme') === 'dark') root.classList.add('dark');
syncThemeIcon();
darkButtons.forEach(btn => btn.addEventListener('click', () => {
    root.classList.toggle('dark');
    localStorage.setItem('theme', root.classList.contains('dark') ? 'dark' : 'light');
    syncThemeIcon();
}));

// ── Menu mobile ───────────────────────────────────────────────
const mobileUsersSidebar = document.getElementById('mobileUsersSidebar');
const usersOverlay       = document.getElementById('usersOverlay');

function toggleUsersMenu(show) {
    if (!mobileUsersSidebar || !usersOverlay) return;
    mobileUsersSidebar.classList.toggle('-translate-x-full', !show);
    usersOverlay.classList.toggle('hidden', !show);
}

document.getElementById('openUsersMenu')?.addEventListener('click',  () => toggleUsersMenu(true));
document.getElementById('closeUsersMenu')?.addEventListener('click', () => toggleUsersMenu(false));
usersOverlay?.addEventListener('click', () => toggleUsersMenu(false));

loadContacts();
</script>
</body>
</html>
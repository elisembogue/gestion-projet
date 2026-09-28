<?php
session_start();
require_once "db.php";

if (!isset($_SESSION['matricule']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: connexion.php');
    exit;
}

$messages = [
    'DeleteSuccess' => ['type' => 'success', 'text' => 'Utilisateur supprime avec succes.'],
    'DeleteFail' => ['type' => 'error', 'text' => 'La suppression a echoue.'],
    'InvalidRequest' => ['type' => 'error', 'text' => 'Requete invalide.'],
    'UpdateSuccess' => ['type' => 'success', 'text' => 'Profil mis a jour avec succes.'],
    'UpdateFail' => ['type' => 'error', 'text' => 'La mise a jour a echoue.'],
];
$currentMessage = $messages[$_GET['message'] ?? ''] ?? null;

function getAvatar(array $row): string {
    $photo = trim((string) ($row['photo'] ?? ''));

    if ($photo !== '') {
        if (preg_match('/^https?:\/\//i', $photo) === 1) {
            return '<img src="' . htmlspecialchars($photo, ENT_QUOTES, 'UTF-8') . '" class="w-10 h-10 rounded-full object-cover border-2 border-white shadow-sm">';
        }

        $relativePath = str_replace('\\', '/', $photo);
        $publicPath = $relativePath;

        if (strpos($relativePath, '../') === 0) {
            $relativePath = substr($relativePath, 3);
        }

        if (strpos($publicPath, 'uploads/') === 0) {
            $publicPath = '../' . $publicPath;
        }

        $absolutePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        if (is_file($absolutePath)) {
            return '<img src="' . htmlspecialchars($publicPath, ENT_QUOTES, 'UTF-8') . '" class="w-10 h-10 rounded-full object-cover border-2 border-white shadow-sm">';
        }
    }

    $initials = urlencode(($row['prenom'] ?? '') . '+' . ($row['nom'] ?? ''));
    return '<img src="https://ui-avatars.com/api/?name=' . $initials . '&background=C026D3&color=fff&rounded=true" class="w-10 h-10 shadow-sm">';
}

$sqlEmp = "SELECT matricule, prenom, nom, date_naissance, sexe, email, telephone, photo FROM users WHERE role='employe' ORDER BY nom";
$resultEmp = mysqli_query($conn, $sqlEmp);

$sqlCli = "SELECT matricule, prenom, nom, date_naissance, sexe, email, telephone, photo FROM users WHERE role='client' ORDER BY nom";
$resultCli = mysqli_query($conn, $sqlCli);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des utilisateurs</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com" onerror="this.onerror=null;this.src='../js/cdn.js';"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: { primary: '#C026D3' },
                    fontFamily: { poppins: ['Poppins', 'sans-serif'] }
                }
            }
        }
    </script>
    <style>
        #modalModifier { display: none; }
        #modalModifier.active { display: flex; }
    </style>
</head>
<body class="font-poppins bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-100 transition-colors duration-300">
<main class="max-w-7xl mx-auto py-8 px-4 sm:px-6">
    <header class="mb-6">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-primary">Gestion des utilisateurs</h1>
                <p class="text-sm text-gray-600 dark:text-gray-300">Administration des employes et des clients.</p>
            </div>
            <div class="hidden md:flex items-center gap-3">
                <a href="adminDashboard.php" class="px-4 py-2 rounded-xl bg-white dark:bg-gray-800 border dark:border-gray-600">Dashboard</a>
                <a href="inscription.php" class="px-4 py-2 rounded-xl bg-primary text-white font-semibold hover:bg-fuchsia-800 transition">Ajouter un employe</a>
                <button onclick="toggleDarkMode()" type="button" class="theme-toggle p-3 rounded-xl bg-white dark:bg-gray-800 border dark:border-gray-600" aria-label="Changer de theme">
                    <span>Moon</span>
                </button>
            </div>
            <div class="md:hidden flex items-center gap-2">
                <button onclick="toggleDarkMode()" type="button" class="theme-toggle p-3 rounded-xl bg-white dark:bg-gray-800 border dark:border-gray-600" aria-label="Changer de theme">
                    <span>Moon</span>
                </button>
                <button id="mobileTopMenuToggle" type="button" class="p-3 rounded-xl bg-white dark:bg-gray-800 border dark:border-gray-600" aria-label="Ouvrir le menu">
                    Menu
                </button>
            </div>
        </div>
        <div id="mobileTopMenu" class="hidden md:hidden mt-3 rounded-2xl border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 p-3 space-y-2">
            <a href="adminDashboard.php" class="block px-3 py-2 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-700">Dashboard</a>
            <a href="inscription.php" class="block px-3 py-2 rounded-xl bg-primary text-white font-semibold hover:bg-fuchsia-800 transition">Ajouter un employe</a>
        </div>

        <div class="mt-4">
            <label for="searchUsers" class="block text-xs text-gray-500 dark:text-gray-300 mb-1">Rechercher un utilisateur (employe ou client)</label>
            <input id="searchUsers" type="text" placeholder="Ecrire un nom, prenom ou email..."
                class="w-full sm:w-96 rounded-xl border px-3 py-2 bg-white dark:bg-gray-800 dark:border-gray-600">
        </div>
    </header>

    <?php if ($currentMessage): ?>
        <div class="mb-5 rounded-xl px-4 py-3 border <?= $currentMessage['type'] === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
            <?= htmlspecialchars($currentMessage['text'], ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <section class="mb-10">
        <div class="flex flex-wrap items-end justify-between gap-3 mb-3">
            <h2 class="text-xl font-bold">Employes</h2>
        </div>
        <?php if ($resultEmp && mysqli_num_rows($resultEmp) > 0): ?>
            <div class="overflow-x-auto rounded-2xl shadow-lg border border-gray-200 dark:border-gray-600">
                <table class="min-w-full bg-white dark:bg-gray-800 text-sm">
                    <thead class="bg-primary text-white">
                        <tr>
                            <th class="py-3 px-3 text-left">Profil</th>
                            <th class="py-3 px-3 text-left">Prenom</th>
                            <th class="py-3 px-3 text-left">Nom</th>
                            <th class="py-3 px-3 text-left">Naissance</th>
                            <th class="py-3 px-3 text-left">Sexe</th>
                            <th class="py-3 px-3 text-left">Email</th>
                            <th class="py-3 px-3 text-left">Telephone</th>
                            <th class="py-3 px-3 text-center">Modifier</th>
                            <th class="py-3 px-3 text-center">Supprimer</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($row = mysqli_fetch_assoc($resultEmp)): ?>
                        <tr class="user-row border-t border-gray-100 dark:border-gray-700 hover:bg-fuchsia-50 dark:hover:bg-fuchsia-900/20"
                            data-search="<?= htmlspecialchars(strtolower(($row['prenom'] ?? '') . ' ' . ($row['nom'] ?? '') . ' ' . ($row['email'] ?? '')), ENT_QUOTES, 'UTF-8') ?>">
                            <td class="py-2 px-3"><?= getAvatar($row) ?></td>
                            <td class="py-2 px-3 font-medium"><?= htmlspecialchars($row['prenom'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="py-2 px-3"><?= htmlspecialchars($row['nom'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="py-2 px-3"><?= htmlspecialchars($row['date_naissance'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="py-2 px-3"><?= htmlspecialchars($row['sexe'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="py-2 px-3"><?= htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="py-2 px-3"><?= htmlspecialchars($row['telephone'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="py-2 px-3 text-center">
                                <button onclick='ouvrirModale(<?= json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="text-primary hover:scale-110 transition">
                                    <img src="../image/write.png" alt="Modifier" class="inline w-5 h-5">
                                </button>
                            </td>
                            <td class="py-2 px-3 text-center">
                                <form method="POST" action="deleteUser.php" onsubmit="return confirm('Supprimer cet utilisateur ?');" class="inline">
                                    <input type="hidden" name="id" value="<?= htmlspecialchars((string) $row['matricule'], ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="text-red-500 hover:scale-110 transition">
                                        <img src="../image/remove.png" alt="Supprimer" class="inline w-5 h-5">
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-sm text-gray-600 dark:text-gray-300">Aucun employe present.</p>
        <?php endif; ?>
    </section>

    <section>
        <h2 class="text-xl font-bold mb-3">Clients</h2>
        <?php if ($resultCli && mysqli_num_rows($resultCli) > 0): ?>
            <div class="overflow-x-auto rounded-2xl shadow-lg border border-gray-200 dark:border-gray-600">
                <table class="min-w-full bg-white dark:bg-gray-800 text-sm">
                    <thead class="bg-blue-700 text-white">
                        <tr>
                            <th class="py-3 px-3 text-left">Profil</th>
                            <th class="py-3 px-3 text-left">Prenom</th>
                            <th class="py-3 px-3 text-left">Nom</th>
                            <th class="py-3 px-3 text-left">Naissance</th>
                            <th class="py-3 px-3 text-left">Sexe</th>
                            <th class="py-3 px-3 text-left">Email</th>
                            <th class="py-3 px-3 text-left">Telephone</th>
                            <th class="py-3 px-3 text-center">Modifier</th>
                            <th class="py-3 px-3 text-center">Supprimer</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($row = mysqli_fetch_assoc($resultCli)): ?>
                        <tr class="user-row border-t border-gray-100 dark:border-gray-700 hover:bg-blue-50 dark:hover:bg-blue-900/20"
                            data-search="<?= htmlspecialchars(strtolower(($row['prenom'] ?? '') . ' ' . ($row['nom'] ?? '') . ' ' . ($row['email'] ?? '')), ENT_QUOTES, 'UTF-8') ?>">
                            <td class="py-2 px-3"><?= getAvatar($row) ?></td>
                            <td class="py-2 px-3 font-medium"><?= htmlspecialchars($row['prenom'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="py-2 px-3"><?= htmlspecialchars($row['nom'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="py-2 px-3"><?= htmlspecialchars($row['date_naissance'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="py-2 px-3"><?= htmlspecialchars($row['sexe'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="py-2 px-3"><?= htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="py-2 px-3"><?= htmlspecialchars($row['telephone'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="py-2 px-3 text-center">
                                <button onclick='ouvrirModale(<?= json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="text-blue-700 hover:scale-110 transition">
                                    <img src="../image/write.png" alt="Modifier" class="inline w-5 h-5">
                                </button>
                            </td>
                            <td class="py-2 px-3 text-center">
                                <form method="POST" action="deleteUser.php" onsubmit="return confirm('Supprimer cet utilisateur ?');" class="inline">
                                    <input type="hidden" name="id" value="<?= htmlspecialchars((string) $row['matricule'], ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="text-red-500 hover:scale-110 transition">
                                        <img src="../image/remove.png" alt="Supprimer" class="inline w-5 h-5">
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-sm text-gray-600 dark:text-gray-300">Aucun client present.</p>
        <?php endif; ?>
    </section>
</main>

<div id="modalModifier" class="fixed inset-0 bg-black/60 z-[100] items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md p-6 max-h-[95vh] overflow-y-auto border dark:border-gray-700">
        <h2 class="text-2xl font-bold text-primary mb-6 text-center">Modifier un profil</h2>
        <form action="updateUser.php" method="POST" class="space-y-4">
            <input type="hidden" name="matricule" id="edit_id">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm mb-1">Prenom</label>
                    <input type="text" name="prenom" id="edit_prenom" required class="w-full p-2.5 border rounded-xl dark:bg-gray-700 dark:border-gray-600">
                </div>
                <div>
                    <label class="block text-sm mb-1">Nom</label>
                    <input type="text" name="nom" id="edit_nom" required class="w-full p-2.5 border rounded-xl dark:bg-gray-700 dark:border-gray-600">
                </div>
            </div>
            <div>
                <label class="block text-sm mb-1">Email</label>
                <input type="email" name="email" id="edit_email" required class="w-full p-2.5 border rounded-xl dark:bg-gray-700 dark:border-gray-600">
            </div>
            <div>
                <label class="block text-sm mb-1">Telephone</label>
                <input type="text" name="telephone" id="edit_telephone" class="w-full p-2.5 border rounded-xl dark:bg-gray-700 dark:border-gray-600">
            </div>
            <div>
                <label class="block text-sm mb-1">Date de naissance</label>
                <input type="date" name="date_naissance" id="edit_date" class="w-full p-2.5 border rounded-xl dark:bg-gray-700 dark:border-gray-600">
            </div>
            <div>
                <label class="block text-sm mb-1">Sexe</label>
                <div class="flex gap-5 p-2 rounded-xl bg-gray-50 dark:bg-gray-700/60">
                    <label class="inline-flex items-center gap-2"><input type="radio" name="sexe" value="masculin" id="sex_m"> Masculin</label>
                    <label class="inline-flex items-center gap-2"><input type="radio" name="sexe" value="feminin" id="sex_f"> Feminin</label>
                </div>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="flex-1 bg-primary hover:bg-fuchsia-800 text-white font-bold py-2.5 rounded-xl">Modifier</button>
                <button type="button" onclick="fermerModale()" class="flex-1 bg-gray-200 dark:bg-gray-700 py-2.5 rounded-xl">Annuler</button>
            </div>
        </form>
    </div>
</div>

<script>
    function ouvrirModale(user) {
        const modal = document.getElementById('modalModifier');
        document.getElementById('edit_id').value = user.matricule ?? '';
        document.getElementById('edit_prenom').value = user.prenom ?? '';
        document.getElementById('edit_nom').value = user.nom ?? '';
        document.getElementById('edit_email').value = user.email ?? '';
        document.getElementById('edit_telephone').value = user.telephone ?? '';
        document.getElementById('edit_date').value = user.date_naissance ?? '';

        const sexe = (user.sexe || '').toLowerCase();
        document.getElementById('sex_m').checked = sexe.includes('mas');
        document.getElementById('sex_f').checked = !sexe.includes('mas');

        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function fermerModale() {
        document.getElementById('modalModifier').classList.remove('active');
        document.body.style.overflow = 'auto';
    }

    window.addEventListener('click', (e) => {
        if (e.target.id === 'modalModifier') fermerModale();
    });

    function toggleDarkMode() {
        document.documentElement.classList.toggle('dark');
        const isDark = document.documentElement.classList.contains('dark');
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
        document.querySelectorAll('.theme-toggle span').forEach((icon) => {
            icon.textContent = isDark ? 'Sun' : 'Moon';
        });
    }

    window.addEventListener('DOMContentLoaded', () => {
        const mobileTopMenu = document.getElementById('mobileTopMenu');
        const mobileTopMenuToggle = document.getElementById('mobileTopMenuToggle');
        if (mobileTopMenu && mobileTopMenuToggle) {
            mobileTopMenuToggle.addEventListener('click', () => mobileTopMenu.classList.toggle('hidden'));
        }

        if (localStorage.getItem('theme') === 'dark') {
            document.documentElement.classList.add('dark');
        }
        document.querySelectorAll('.theme-toggle span').forEach((icon) => {
            icon.textContent = document.documentElement.classList.contains('dark') ? 'Sun' : 'Moon';
        });

        const searchInput = document.getElementById('searchUsers');
        const rows = Array.from(document.querySelectorAll('.user-row'));
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                const q = (searchInput.value || '').toLowerCase().trim();
                rows.forEach((row) => {
                    const hay = (row.getAttribute('data-search') || '').toLowerCase();
                    row.style.display = q === '' || hay.includes(q) ? '' : 'none';
                });
            });
        }
    });
</script>
</body>
</html>

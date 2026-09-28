<?php
session_start();
require_once "db.php";

if (!isset($_SESSION['matricule']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: connexion.php');
    exit;
}

$matricule = trim((string) ($_GET['id'] ?? ''));
$error   = '';
$success = '';
$row     = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send'])) {

    $matricule      = trim((string) ($_POST['matricule']      ?? $matricule));
    $prenom         = trim((string) ($_POST['prenom']         ?? ''));
    $nom            = trim((string) ($_POST['nom']            ?? ''));
    $email          = trim((string) ($_POST['email']          ?? ''));
    $telephone      = trim((string) ($_POST['telephone']      ?? ''));
    $date_naissance = trim((string) ($_POST['date_naissance'] ?? ''));
    $sexe           = strtolower(trim((string) ($_POST['sexe'] ?? '')));

    if ($prenom === '' || $nom === '' || $email === '' || $telephone === '' || $date_naissance === '' || $sexe === '' || $matricule === '') {
        $error = "Veuillez remplir tous les champs.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Adresse email invalide.";
    } elseif (!in_array($sexe, ['masculin', 'feminin'], true)) {
        $error = "Valeur de sexe invalide.";
    } else {
        $stmt = mysqli_prepare($conn,
            "UPDATE users SET prenom=?, nom=?, email=?, telephone=?, date_naissance=?, sexe=? WHERE matricule=?"
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'sssssss', $prenom, $nom, $email, $telephone, $date_naissance, $sexe, $matricule);
            if (mysqli_stmt_execute($stmt)) {
                $success = "Modification réussie !";
            } else {
                $error = "Erreur lors de la modification.";
            }
            mysqli_stmt_close($stmt);
        } else {
            $error = "Erreur technique.";
        }
    }
}

// Chargement des données actuelles de l'utilisateur
if ($matricule !== '') {
    $stmt = mysqli_prepare($conn,
        "SELECT matricule, prenom, nom, email, telephone, date_naissance, sexe FROM users WHERE matricule=? LIMIT 1"
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 's', $matricule);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($result && mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
        }
        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier un employé</title>
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
</head>
<body class="font-poppins bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-100 min-h-screen transition-colors duration-300">
    <div class="max-w-2xl mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl sm:text-3xl font-extrabold text-primary">Modifier un employé</h1>
            <button onclick="toggleDarkMode()" class="p-3 rounded-xl bg-white dark:bg-gray-800 border dark:border-gray-600" aria-label="Changer de thème">
                <span id="modeIcon">🌙</span>
            </button>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border dark:border-gray-700 p-6 sm:p-8">

            <?php if ($error): ?>
                <div class="mb-4 rounded-xl border border-red-300 bg-red-100 px-4 py-3 text-red-700">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="mb-4 rounded-xl border border-green-300 bg-green-100 px-4 py-3 text-green-700">
                    <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
                    <a href="showUser.php" class="underline font-semibold">Retour</a>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="matricule" value="<?= htmlspecialchars($row['matricule'] ?? $matricule, ENT_QUOTES, 'UTF-8') ?>">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block mb-1 text-sm font-semibold">Prénom</label>
                        <input type="text" name="prenom" value="<?= htmlspecialchars($row['prenom'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required
                            class="w-full border rounded-xl px-3 py-2 dark:bg-gray-700 dark:border-gray-600">
                    </div>
                    <div>
                        <label class="block mb-1 text-sm font-semibold">Nom</label>
                        <input type="text" name="nom" value="<?= htmlspecialchars($row['nom'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required
                            class="w-full border rounded-xl px-3 py-2 dark:bg-gray-700 dark:border-gray-600">
                    </div>
                </div>

                <div>
                    <label class="block mb-1 text-sm font-semibold">Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($row['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required
                        class="w-full border rounded-xl px-3 py-2 dark:bg-gray-700 dark:border-gray-600">
                </div>

                <div>
                    <label class="block mb-1 text-sm font-semibold">Téléphone</label>
                    <input type="tel" name="telephone" value="<?= htmlspecialchars($row['telephone'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required
                        class="w-full border rounded-xl px-3 py-2 dark:bg-gray-700 dark:border-gray-600">
                </div>

                <div>
                    <label class="block mb-1 text-sm font-semibold">Date de naissance</label>
                    <input type="date" name="date_naissance" value="<?= htmlspecialchars($row['date_naissance'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required
                        class="w-full border rounded-xl px-3 py-2 dark:bg-gray-700 dark:border-gray-600">
                </div>

                <div>
                    <label class="block mb-2 text-sm font-semibold">Sexe</label>
                    <?php $sexeVal = strtolower((string) ($row['sexe'] ?? '')); ?>
                    <div class="flex gap-5 p-3 rounded-xl bg-gray-50 dark:bg-gray-700/60">
                        <label class="inline-flex items-center gap-2">
                            <input type="radio" name="sexe" value="masculin" <?= $sexeVal === 'masculin' ? 'checked' : '' ?> required>
                            Masculin
                        </label>
                        <label class="inline-flex items-center gap-2">
                            <input type="radio" name="sexe" value="feminin" <?= $sexeVal === 'feminin' ? 'checked' : '' ?> required>
                            Féminin
                        </label>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-3 pt-2">
                    <input type="submit" name="send" value="Modifier"
                        class="bg-primary hover:bg-fuchsia-800 text-white font-semibold py-2.5 px-6 rounded-xl cursor-pointer text-center">
                    <a href="showUser.php"
                        class="bg-gray-500 hover:bg-gray-600 text-white font-semibold py-2.5 px-6 rounded-xl text-center">Annuler</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleDarkMode() {
            document.documentElement.classList.toggle('dark');
            const isDark = document.documentElement.classList.contains('dark');
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
            document.getElementById('modeIcon').textContent = isDark ? '☀️' : '🌙';
        }

        window.addEventListener('DOMContentLoaded', () => {
            if (localStorage.getItem('theme') === 'dark') {
                document.documentElement.classList.add('dark');
                document.getElementById('modeIcon').textContent = '☀️';
            }
        });
    </script>
</body>
</html>
<?php
session_start();
include_once "db.php";

$error = '';
$success = '';

if (isset($_POST['register'])) {
    $prenom = trim($_POST['prenom'] ?? '');
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $date_naissance = $_POST['date_naissance'] ?? '';
    $sexe = $_POST['sexe'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($prenom === '' || $nom === '' || $email === '' || $telephone === '' || $date_naissance === '' || $sexe === '' || $password === '') {
        $error = "Veuillez remplir tous les champs obligatoires.";
    } elseif ($password !== $confirm_password) {
        $error = "Les mots de passe ne correspondent pas.";
    } else {
        $stmt = $conn->prepare("SELECT email FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $resultCheck = $stmt->get_result();

        if ($resultCheck->num_rows > 0) {
            $error = "Cet email est déjà utilisé.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $role = 'employe';

            $stmt = $conn->prepare("INSERT INTO users (prenom, nom, email, telephone, role, date_naissance, sexe, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssss", $prenom, $nom, $email, $telephone, $role, $date_naissance, $sexe, $hashed_password);

            if ($stmt->execute()) {
                $success = "Inscription réussie !";
            } else {
                $error = "Erreur SQL : " . $stmt->error;
            }
        }
        $stmt->close();
    }
}

function old(string $key): string {
    return htmlspecialchars($_POST[$key] ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription Employé</title>
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
                    colors: { primary: '#C026D3' },
                    fontFamily: { poppins: ['Poppins', 'sans-serif'] }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-100 font-poppins transition-colors duration-300">
    <header class="fixed inset-x-0 top-0 z-50 border-b border-white/20 bg-fuchsia-700 backdrop-blur dark:border-slate-600 dark:bg-slate-800">
        <div class="mx-auto flex h-20 max-w-7xl items-center justify-between gap-3 px-4 sm:px-6">
            <a href="Accueil.php" class="flex items-center gap-3">
                <div class="">
                   <img src="../image/logo EltaRH.png" alt="EltaRH" class="h-16 sm:h-20 w-auto">
                </div>
                <div>
                    <p class="text-[11px] uppercase  text-fuchsia-100">By Didacsoft</p>
                </div>
            </a>

            <nav class="hidden items-center gap-6 text-sm font-medium text-white md:flex">
                <a href="Accueil.php" class="transition hover:text-fuchsia-200">Accueil</a>
                <a href="Commande.php#commande" class="transition hover:text-fuchsia-200">Commandes</a>
                <a href="Commande.php#a-propos" class="transition hover:text-fuchsia-200">A-propos</a>
                <a href="connexion.php" class="rounded-full bg-white px-4 py-2 font-bold text-fuchsia-700">Connexion</a>
                <button id="darkToggle" class="h-10 w-10 rounded-xl bg-white/20" aria-label="Basculer le thème"><i class="fa-solid fa-moon"></i></button>
            </nav>

            <div class="flex items-center gap-2 md:hidden">
                <button id="darkToggleMobile" class="h-10 w-10 rounded-xl bg-white/20 text-white" aria-label="Basculer le thème mobile"><i class="fa-solid fa-moon"></i></button>
                <button id="mobileMenuToggle" class="h-10 w-10 rounded-xl bg-white/20 text-white" aria-label="Menu mobile"><i class="fas fa-bars"></i></button>
            </div>
        </div>

        <div id="mobileMenu" class="hidden border-t border-white/10 bg-fuchsia-700 px-6 py-4 text-white dark:border-slate-600 dark:bg-slate-800 md:hidden">
            <div class="space-y-3 text-sm">
                <a href="Accueil.php" class="block">Accueil</a>
                <a href="Commande.php#commande" class="block">Commandes</a>
                <a href="Commande.php#a-propos" class="block">A-propos</a>
                <a href="connexion.php" class="mt-2 inline-flex rounded-full bg-white px-4 py-2 font-bold text-fuchsia-700">Connexion</a>
            </div>
        </div>
    </header>

    <main class="flex justify-center items-center min-h-screen p-4 pt-28 sm:pt-32">
        <div class="w-full max-w-2xl bg-white/90 dark:bg-gray-800/95 shadow-2xl rounded-3xl p-5 sm:p-8 border border-white/30 dark:border-gray-700 backdrop-blur">
            <div class="mb-8 text-center">
                <h1 class="text-3xl font-extrabold text-primary">Inscription Employé</h1>
                <p class="text-sm text-gray-600 dark:text-gray-300 mt-2">Créez un compte employé pour accéder à la plateforme.</p>
            </div>

            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-4"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-xl mb-4">
                    <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
                    <a href="connexion.php" class="text-primary underline font-bold">Se connecter</a>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block mb-1 text-sm font-semibold">Prénom</label>
                        <input type="text" name="prenom" value="<?= old('prenom') ?>" class="w-full border rounded-xl px-3 py-2 dark:bg-gray-700 dark:text-white outline-none focus:ring-2 focus:ring-primary" required>
                    </div>
                    <div>
                        <label class="block mb-1 text-sm font-semibold">Nom</label>
                        <input type="text" name="nom" value="<?= old('nom') ?>" class="w-full border rounded-xl px-3 py-2 dark:bg-gray-700 dark:text-white outline-none focus:ring-2 focus:ring-primary" required>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block mb-1 text-sm font-semibold">Email</label>
                        <input type="email" name="email" value="<?= old('email') ?>" class="w-full border rounded-xl px-3 py-2 dark:bg-gray-700 dark:text-white outline-none focus:ring-2 focus:ring-primary" required>
                    </div>
                    <div>
                        <label class="block mb-1 text-sm font-semibold">Téléphone</label>
                        <input type="text" name="telephone" value="<?= old('telephone') ?>" class="w-full border rounded-xl px-3 py-2 dark:bg-gray-700 dark:text-white outline-none focus:ring-2 focus:ring-primary" required>
                    </div>
                </div>

                <div>
                    <label class="block mb-1 text-sm font-semibold">Date de naissance</label>
                    <input type="date" name="date_naissance" value="<?= old('date_naissance') ?>" class="w-full border rounded-xl px-3 py-2 dark:bg-gray-700 dark:text-white outline-none focus:ring-2 focus:ring-primary" required>
                </div>

                <div>
                    <label class="block mb-2 text-sm font-semibold">Sexe</label>
                    <div class="flex gap-6 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-xl border dark:border-gray-600">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="sexe" value="Masculin" <?= old('sexe') === 'Masculin' ? 'checked' : '' ?> class="w-4 h-4 text-primary focus:ring-primary" required>
                            <span>Masculin</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="sexe" value="Féminin" <?= old('sexe') === 'Féminin' ? 'checked' : '' ?> class="w-4 h-4 text-primary focus:ring-primary" required>
                            <span>Féminin</span>
                        </label>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block mb-1 text-sm font-semibold">Mot de passe</label>
                        <div class="relative">
                            <input id="registerPassword" type="password" name="password" class="w-full border rounded-xl px-3 py-2 pr-12 dark:bg-gray-700 dark:text-white outline-none focus:ring-2 focus:ring-primary" required>
                            <button type="button" data-password-toggle="registerPassword" class="absolute inset-y-0 right-2 my-auto h-9 w-9 rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-600" aria-label="Afficher ou masquer le mot de passe"><i class="fa-solid fa-eye"></i></button>
                        </div>
                    </div>
                    <div>
                        <label class="block mb-1 text-sm font-semibold">Confirmer mot de passe</label>
                        <div class="relative">
                            <input id="registerConfirmPassword" type="password" name="confirm_password" class="w-full border rounded-xl px-3 py-2 pr-12 dark:bg-gray-700 dark:text-white outline-none focus:ring-2 focus:ring-primary" required>
                            <button type="button" data-password-toggle="registerConfirmPassword" class="absolute inset-y-0 right-2 my-auto h-9 w-9 rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-600" aria-label="Afficher ou masquer le mot de passe"><i class="fa-solid fa-eye"></i></button>
                        </div>
                    </div>
                </div>

                <button type="submit" name="register" class="w-full bg-primary hover:bg-fuchsia-800 text-white font-bold py-3 rounded-xl shadow-lg transition-all active:scale-95">
                    S'inscrire
                </button>

                <p class="text-center text-sm">
                    Déjà un compte ? <a href="connexion.php" class="text-primary hover:underline font-semibold">Se connecter</a>
                </p>
            </form>
        </div>
    </main>

    <script>
        const darkButtons = [document.getElementById('darkToggle'), document.getElementById('darkToggleMobile')].filter(Boolean);

        function renderThemeIcons() {
            const isDark = document.documentElement.classList.contains('dark');
            const icon = isDark ? '<i class="fa-solid fa-sun"></i>' : '<i class="fa-solid fa-moon"></i>';
            darkButtons.forEach((btn) => {
                btn.innerHTML = icon;
            });
        }

        function toggleDarkMode() {
            document.documentElement.classList.toggle('dark');
            const isDark = document.documentElement.classList.contains('dark');
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
            renderThemeIcons();
        }

        window.addEventListener('DOMContentLoaded', function () {
            if (localStorage.getItem('theme') === 'dark') {
                document.documentElement.classList.add('dark');
            }
            renderThemeIcons();
            darkButtons.forEach((btn) => btn.addEventListener('click', toggleDarkMode));

            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const mobileMenu = document.getElementById('mobileMenu');
            if (mobileMenuToggle && mobileMenu) {
                mobileMenuToggle.addEventListener('click', () => mobileMenu.classList.toggle('hidden'));
            }
        });

        document.querySelectorAll('[data-password-toggle]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const input = document.getElementById(btn.getAttribute('data-password-toggle'));
                if (!input) return;
                const isHidden = input.type === 'password';
                input.type = isHidden ? 'text' : 'password';
                btn.innerHTML = isHidden ? '<i class="fa-solid fa-eye-slash"></i>' : '<i class="fa-solid fa-eye"></i>';
            });
        });
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
</body>
</html>


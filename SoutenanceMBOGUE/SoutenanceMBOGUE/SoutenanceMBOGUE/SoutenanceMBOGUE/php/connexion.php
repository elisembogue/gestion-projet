<?php
session_start();
require_once "db.php";

$error = '';
$welcomeMessage = '';
$redirectUrl = '';

function passwordMatches(string $plainPassword, string $storedPassword): bool
{
    if ($storedPassword === '') {
        return false;
    }

    if (password_verify($plainPassword, $storedPassword)) {
        return true;
    }

    if (hash_equals($storedPassword, $plainPassword)) {
        return true;
    }

    if (hash_equals($storedPassword, md5($plainPassword))) {
        return true;
    }

    if (hash_equals($storedPassword, sha1($plainPassword))) {
        return true;
    }

    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = trim($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email ou mot de passe incorrect.';
    } else {
        $sql = "SELECT matricule, nom, prenom, email, role, password FROM users WHERE email = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $user = mysqli_fetch_assoc($result);

            if ($user && passwordMatches($password, (string) $user['password'])) {
                session_regenerate_id(true);

                $storedPassword = (string) ($user['password'] ?? '');
                $isHash = password_get_info($storedPassword)['algo'] !== null;
                if (!$isHash) {
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $updateStmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE matricule = ? LIMIT 1");
                    if ($updateStmt) {
                        mysqli_stmt_bind_param($updateStmt, "si", $newHash, $user['matricule']);
                        mysqli_stmt_execute($updateStmt);
                        mysqli_stmt_close($updateStmt);
                    }
                }

                $_SESSION['matricule'] = $user['matricule'];
                $_SESSION['nom'] = $user['nom'];
                $_SESSION['prenom'] = $user['prenom'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];

                $role = strtolower((string) $user['role']);
                if ($role === 'admin') {
                    $redirectUrl = 'adminDashboard.php';
                } elseif ($role === 'client') {
                    $redirectUrl = 'clientDashboard.php';
                } elseif ($role === 'employe' || $role === 'employee') {
                    $redirectUrl = 'employeDashboard.php';
                } else {
                    session_destroy();
                    $error = 'Rôle utilisateur non reconnu. Contactez un administrateur.';
                }

                if ($redirectUrl !== '') {
                    $displayName = trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''));
                    $welcomeMessage = 'Bon retour ' . $displayName;
                }
            } else {
                $error = 'Email ou mot de passe incorrect.';
            }
            mysqli_stmt_close($stmt);
        } else {
            $error = 'Erreur technique de base de données.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion | EltaRH</title>
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
                    fontFamily: { poppins: ['Poppins', 'sans-serif'] },
                    colors: { fuchsia700: '#C026D3' }
                }
            }
        }
    </script>
</head>
<body class="min-h-full font-poppins bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-100">
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
                <a href="inscription.php" class="rounded-full bg-white px-4 py-2 font-bold text-fuchsia-700">Inscription</a>
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
                <a href="inscription.php" class="mt-2 inline-flex rounded-full bg-white px-4 py-2 font-bold text-fuchsia-700">Inscription</a>
            </div>
        </div>
    </header>

    <div class="relative flex min-h-screen items-center justify-center overflow-hidden px-4 py-8 sm:py-10 pt-28 sm:pt-32">
        <div class="absolute inset-0">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(192,38,211,0.2),transparent_40%),radial-gradient(circle_at_80%_0%,rgba(255,255,255,0.2),transparent_35%)]"></div>
        </div>

        <div class="relative z-10 grid w-full max-w-5xl overflow-hidden rounded-3xl border border-white/20 bg-white/80 shadow-2xl backdrop-blur-lg dark:bg-gray-800/80 dark:border-gray-600 lg:grid-cols-2">
            <section class="hidden h-full flex-col justify-between bg-fuchsia-700 p-10 text-white lg:flex">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-fuchsia-100">Espace sécurisé</p>
                    <?php if ($welcomeMessage !== ''): ?>
                        <h1 class="mt-4 text-4xl font-extrabold leading-tight"><?= htmlspecialchars($welcomeMessage, ENT_QUOTES, 'UTF-8') ?></h1>
                        <p class="mt-4 text-fuchsia-100">Redirection en cours vers votre tableau de bord...</p>
                    <?php else: ?>
                        <h1 class="mt-4 text-4xl font-extrabold leading-tight">Bienvenue sur EltaRH</h1>
                        <p class="mt-4 text-fuchsia-100">Centralisez vos opérations RH et vos projets dans un espace unique.</p>
                    <?php endif; ?>
                </div>
                <?php if ($welcomeMessage !== ''): ?>
                    <div class="rounded-2xl border border-white/30 bg-white/15 p-4 text-sm text-fuchsia-50 animate-pulse">
                        Connexion validée. Chargement de votre espace...
                    </div>
                <?php else: ?>
                    <div class="rounded-2xl border border-white/30 bg-white/10 p-4 text-sm text-fuchsia-50">
                        Accès réservé aux comptes vérifiés. Vos sessions sont protégées.
                    </div>
                <?php endif; ?>
            </section>

            <section class="p-5 sm:p-8 lg:p-10">
                <div class="mb-8">
                    <h2 class="text-3xl font-extrabold text-gray-900 dark:text-white">Connexion</h2>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Entrez vos identifiants pour accéder à votre tableau de bord.</p>
                </div>

                <?php if ($error !== ''): ?>
                    <div class="mb-6 rounded-xl border border-red-300/70 bg-red-100/80 p-4 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-300 dark:border-red-500/30">
                        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>
                <?php if ($welcomeMessage !== ''): ?>
                    <div class="mb-6 rounded-xl border border-green-300/70 bg-green-100/80 p-4 text-sm text-green-700 dark:bg-green-900/30 dark:text-green-300 dark:border-green-500/30 lg:hidden">
                        <?= htmlspecialchars($welcomeMessage, ENT_QUOTES, 'UTF-8') ?>. Redirection...
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="space-y-5">
                    <label class="block">
                        <span class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-200">Adresse email</span>
                        <input type="email" name="email" required autocomplete="email" value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-fuchsia-500 focus:ring-2 focus:ring-fuchsia-500/30 dark:bg-gray-900 dark:border-gray-600" placeholder="nom@entreprise.com">
                    </label>

                    <label class="block">
                        <span class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-200">Mot de passe</span>
                        <div class="relative">
                            <input id="loginPassword" type="password" name="password" required autocomplete="current-password" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 pr-12 text-sm outline-none transition focus:border-fuchsia-500 focus:ring-2 focus:ring-fuchsia-500/30 dark:bg-gray-900 dark:border-gray-600" placeholder="Votre mot de passe">
                            <button type="button" data-password-toggle="loginPassword" class="absolute inset-y-0 right-2 my-auto h-9 w-9 rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700" aria-label="Afficher ou masquer le mot de passe"><i class="fa-solid fa-eye"></i></button>
                        </div>
                    </label>

                    <button type="submit" name="login" class="w-full rounded-xl bg-fuchsia-700 px-4 py-3 text-sm font-bold text-white transition hover:bg-fuchsia-800">
                        Se connecter
                    </button>
                </form>

                <p class="mt-6 text-center text-sm text-gray-600 dark:text-gray-300">
                    Nouveau compte ?
                    <a href="inscription.php" class="font-bold text-fuchsia-700 hover:text-fuchsia-600 dark:text-fuchsia-300">Créer un compte</a>
                </p>
            </section>
        </div>
    </div>

    <script>
        const root = document.documentElement;
        const darkButtons = [document.getElementById('darkToggle'), document.getElementById('darkToggleMobile')].filter(Boolean);

        function syncThemeIcon() {
            const icon = root.classList.contains('dark')
                ? '<i class="fa-solid fa-sun"></i>'
                : '<i class="fa-solid fa-moon"></i>';
            darkButtons.forEach((btn) => {
                btn.innerHTML = icon;
            });
        }

        if (localStorage.getItem('theme') === 'dark') {
            root.classList.add('dark');
        }
        syncThemeIcon();

        darkButtons.forEach((btn) => {
            btn.addEventListener('click', () => {
                root.classList.toggle('dark');
                localStorage.setItem('theme', root.classList.contains('dark') ? 'dark' : 'light');
                syncThemeIcon();
            });
        });

        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const mobileMenu = document.getElementById('mobileMenu');
        if (mobileMenuToggle && mobileMenu) {
            mobileMenuToggle.addEventListener('click', () => mobileMenu.classList.toggle('hidden'));
        }

        document.querySelectorAll('[data-password-toggle]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const input = document.getElementById(btn.getAttribute('data-password-toggle'));
                if (!input) return;
                const isHidden = input.type === 'password';
                input.type = isHidden ? 'text' : 'password';
                btn.innerHTML = isHidden ? '<i class="fa-solid fa-eye-slash"></i>' : '<i class="fa-solid fa-eye"></i>';
            });
        });

        <?php if ($redirectUrl !== ''): ?>
        window.setTimeout(() => {
            window.location.href = '<?= htmlspecialchars($redirectUrl, ENT_QUOTES, 'UTF-8') ?>';
        }, 1500);
        <?php endif; ?>
    </script>
</body>
</html>


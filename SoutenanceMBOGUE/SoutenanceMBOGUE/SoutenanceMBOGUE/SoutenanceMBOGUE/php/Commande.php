<?php
$aboutOnly = (($_GET['view'] ?? '') === 'about');
session_start();
require_once "db.php";

$success = "";
$error = "";

function old(string $key): string
{
    return htmlspecialchars(trim($_POST[$key] ?? ""), ENT_QUOTES, "UTF-8");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $prenom = trim($_POST["prenom"] ?? "");
    $nom = trim($_POST["clientNom"] ?? "");
    $sexe = trim($_POST["sexe"] ?? "");
    $password = trim($_POST["password"] ?? "");
    $dateNaissance = trim($_POST["date_naissance"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $tel = trim($_POST["clientTel"] ?? "");
    $typeProjet = trim($_POST["type_projet"] ?? "");

    if ($prenom !== "" && $nom !== "" && $sexe !== "" && $password !== "" && $dateNaissance !== "" && $email !== "" && $tel !== "" && $typeProjet !== "") {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $stmtUser = $conn->prepare("INSERT INTO users (prenom, nom, sexe, password, date_naissance, email, telephone, role) VALUES (?, ?, ?, ?, ?, ?, ?, 'client')");
        if ($stmtUser === false) {
            die("Erreur de préparation users : " . $conn->error);
        }
        $stmtUser->bind_param("sssssss", $prenom, $nom, $sexe, $passwordHash, $dateNaissance, $email, $tel);
        $userOk = $stmtUser->execute();
        if (!$userOk) {
            $error = "Erreur Utilisateurs : " . $stmtUser->error;
        }
        $stmtUser->close();

        if ($userOk) {
            $clientId = $conn->insert_id;
            $stmtProjet = $conn->prepare("INSERT INTO projet (id_client, type_projet) VALUES (?, ?)");
            if ($stmtProjet === false) {
                die("Erreur de préparation projet : " . $conn->error);
            }
            $stmtProjet->bind_param("is", $clientId, $typeProjet);
            $projetOk = $stmtProjet->execute();
            if (!$projetOk) {
                $error = "Erreur Projet : " . $stmtProjet->error;
            }
            $stmtProjet->close();
            if ($projetOk) {
                $success = "Commande enregistrée avec succès !";
            }
        }
    } else {
        $error = "Veuillez remplir tous les champs.";
    }
}

$prestations = [
    ["title" => "Création de site web", "desc" => "Solutions modernes pour valoriser votre image de marque.", "image" => "../image/image9.jpg"],
    ["title" => "Marketing digital", "desc" => "Boostez votre visibilité grâce aux leviers du web.", "image" => "../image/image10.jpg"],
    ["title" => "Publicité en ligne", "desc" => "Campagnes ciblées pour maximiser vos ventes.", "image" => "../image/image11.jpg"],
    ["title" => "Réseaux sociaux", "desc" => "Gestion de communauté pour accroître votre notoriété.", "image" => "../image/image12.jpg"]
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commande | EltaRH</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com" onerror="this.onerror=null;this.src='../js/cdn.js';"></script>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        primary: "#C026D3",
                        primaryDark: "#86198F"
                    },
                    fontFamily: {
                        poppins: ["Poppins", "sans-serif"]
                    }
                }
            }
        };
    </script>
    <style>
        .slider-track {
            transition: transform 0.8s ease-in-out;
        }
    </style>
</head>
<body class="min-h-screen bg-slate-50 font-poppins text-slate-900 dark:bg-slate-900 dark:text-slate-100">
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
                <a href="Commande.php?view=about" class="transition hover:text-fuchsia-200">À propos</a>
                <a href="#contact" class="transition hover:text-fuchsia-200">Contact</a>
                <a href="connexion.php" class="rounded-full bg-white px-4 py-2 font-bold text-primary">Connexion</a>
                <button id="darkToggle" class="h-10 w-10 rounded-xl bg-white/20" aria-label="Changer le thème">
                    <i class="fa-solid fa-moon"></i>
                </button>
            </nav>

            <div class="flex items-center gap-2 md:hidden">
                <button id="darkToggleMobile" class="h-10 w-10 rounded-xl bg-white/20 text-white" aria-label="Changer le thème mobile">
                    <i class="fa-solid fa-moon"></i>
                </button>
                <button id="mobileMenuToggle" class="h-10 w-10 rounded-xl bg-white/20 text-white" aria-label="Menu mobile">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>

        <div id="mobileMenu" class="hidden border-t border-white/10 bg-fuchsia-700 px-6 py-4 text-white dark:border-slate-600 dark:bg-slate-800 md:hidden">
            <div class="space-y-3 text-sm">
                <a href="Accueil.php" class="block">Accueil</a>
                <a href="Commande.php#commande" class="block">Commandes</a>
                <a href="Commande.php?view=about" class="block">À propos</a>
                <a href="#contact" class="block">Contact</a>
                <a href="connexion.php" class="mt-2 inline-flex rounded-full bg-white px-4 py-2 font-bold text-primary">Connexion</a>
            </div>
        </div>
    </header>

    <main id="page-commande" class="mx-auto max-w-7xl px-4 pb-24 pt-32 sm:px-6">
        <?php if ($aboutOnly): ?>
        <section id="a-propos" class="mx-auto max-w-7xl px-0 py-4">
            <div class="overflow-hidden rounded-3xl border border-fuchsia-200/60 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-800">
                <div class="bg-gradient-to-r from-fuchsia-700 to-fuchsia-900 px-7 py-8 text-white">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-fuchsia-100">À propos</p>
                    <h1 class="mt-2 text-3xl font-black">Didacsoft</h1>
                    <p class="mt-3 max-w-3xl text-fuchsia-100">Partenaire de transformation digitale pour les entreprises recherchant des solutions logicielles robustes et évolutives.</p>
                </div>
                <div class="grid gap-5 p-7 md:grid-cols-3">
                    <article class="rounded-2xl border border-slate-200 bg-slate-50 p-5 dark:border-slate-700 dark:bg-slate-900">
                        <h2 class="text-lg font-bold text-slate-900 dark:text-white">Mission</h2>
                        <p class="mt-2 text-slate-600 dark:text-slate-300">Concevoir des logiciels utiles et durables adaptés aux besoins métiers locaux.</p>
                    </article>
                    <article class="rounded-2xl border border-slate-200 bg-slate-50 p-5 dark:border-slate-700 dark:bg-slate-900">
                        <h2 class="text-lg font-bold text-slate-900 dark:text-white">Vision</h2>
                        <p class="mt-2 text-slate-600 dark:text-slate-300">Accélérer l'adoption numérique des PME avec des outils simples et performants.</p>
                    </article>
                    <article class="rounded-2xl border border-slate-200 bg-slate-50 p-5 dark:border-slate-700 dark:bg-slate-900">
                        <h2 class="text-lg font-bold text-slate-900 dark:text-white">Valeurs</h2>
                        <p class="mt-2 text-slate-600 dark:text-slate-300">Innovation, qualité, proximité client et sécurité des données.</p>
                    </article>
                </div>
            </div>
        </section>
        <?php else: ?>
        <section class="rounded-3xl bg-gradient-to-r from-primary to-primaryDark p-8 text-white md:p-10">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-fuchsia-100">Commande rapide</p>
            <h1 class="mt-2 text-3xl font-black md:text-5xl">Lancez votre projet avec l'équipe Didacsoft</h1>
            <p class="mt-3 max-w-3xl text-fuchsia-100">Choisissez une prestation, créez votre demande et suivez l'avancement depuis votre espace client.</p>
        </section>

        <div class="mt-8 text-center">
            <?php if ($success): ?>
                <p class="inline-block rounded-xl border border-green-300/50 bg-green-100 px-4 py-2 font-semibold text-green-700 dark:border-green-500/40 dark:bg-green-900/40 dark:text-green-300"><?= htmlspecialchars($success, ENT_QUOTES, "UTF-8") ?></p>
            <?php endif; ?>
            <?php if ($error): ?>
                <p class="inline-block rounded-xl border border-red-300/50 bg-red-100 px-4 py-2 font-semibold text-red-700 dark:border-red-500/40 dark:bg-red-900/40 dark:text-red-300"><?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?></p>
            <?php endif; ?>
        </div>

        <section class="mt-12 overflow-hidden rounded-3xl border border-fuchsia-100 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-800">
            <div id="commandeSliderTrack" class="slider-track flex">
                <div class="cmd-slide grid w-full shrink-0 grid-cols-1 gap-5 p-5 md:grid-cols-2">
                    <article class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-900">
                        <img src="../image/image2.jpg" alt="Brief client" class="h-72 w-full object-cover object-top md:h-80">
                        <div class="p-4">
                            <h3 class="font-bold text-slate-900 dark:text-white">Brief et qualification</h3>
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Chaque commande est cadrée selon vos objectifs métier et vos délais.</p>
                        </div>
                    </article>
                    <article class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-900">
                        <img src="../image/image3.jpg" alt="Exécution projet" class="h-72 w-full object-cover object-top md:h-80">
                        <div class="p-4">
                            <h3 class="font-bold text-slate-900 dark:text-white">Exécution structurée</h3>
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">L'équipe suit un plan d'action clair avec reporting et validations intermédiaires.</p>
                        </div>
                    </article>
                </div>
                <div class="cmd-slide grid w-full shrink-0 grid-cols-1 gap-5 p-5 md:grid-cols-2">
                    <article class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-900">
                        <img src="../image/image4.jpg" alt="Communication client" class="h-72 w-full object-cover object-top md:h-80">
                        <div class="p-4">
                            <h3 class="font-bold text-slate-900 dark:text-white">Communication continue</h3>
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Vous restez informé de chaque étape via un tableau de bord et des échanges directs.</p>
                        </div>
                    </article>
                    <article class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-900">
                        <img src="../image/image5.jpg" alt="Livraison et suivi" class="h-72 w-full object-cover object-top md:h-80">
                        <div class="p-4">
                            <h3 class="font-bold text-slate-900 dark:text-white">Livraison maîtrisée</h3>
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Contrôle qualité, finalisation et accompagnement après la mise en production.</p>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <section id="a-propos-features" class="mt-12 grid gap-6 md:grid-cols-3">
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <p class="text-sm text-slate-500 dark:text-slate-300">Approche</p>
                <h3 class="mt-1 text-xl font-bold text-slate-900 dark:text-white">Orientation résultats</h3>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Nous alignons chaque commande sur des objectifs concrets et mesurables.</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <p class="text-sm text-slate-500 dark:text-slate-300">Expertise</p>
                <h3 class="mt-1 text-xl font-bold text-slate-900 dark:text-white">Équipe multidisciplinaire</h3>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Design, développement et communication travaillent en coordination continue.</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <p class="text-sm text-slate-500 dark:text-slate-300">Engagement</p>
                <h3 class="mt-1 text-xl font-bold text-slate-900 dark:text-white">Qualité et suivi</h3>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Processus de validation rigoureux pour garantir la fiabilité de vos outils.</p>
            </article>
        </section>

        <section id="commande" class="mt-12">
            <div class="mb-5 flex items-end justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-fuchsia-600 dark:text-fuchsia-300">Nos prestations</p>
                    <h2 class="text-2xl font-black text-slate-900 dark:text-white">Choisissez une offre</h2>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <?php foreach ($prestations as $item): ?>
                    <article class="flex flex-col justify-between rounded-3xl border border-slate-100 bg-white p-6 shadow-lg transition hover:-translate-y-1 hover:border-fuchsia-500/30 dark:border-slate-700 dark:bg-slate-800">
                        <div>
                            <img src="<?= htmlspecialchars($item["image"], ENT_QUOTES, "UTF-8") ?>" alt="<?= htmlspecialchars($item["title"], ENT_QUOTES, "UTF-8") ?>" class="mb-4 h-40 w-full rounded-2xl object-cover">
                            <h3 class="mb-2 text-xl font-semibold text-slate-900 dark:text-white"><?= htmlspecialchars($item["title"], ENT_QUOTES, "UTF-8") ?></h3>
                            <p class="text-slate-600 dark:text-slate-300"><?= htmlspecialchars($item["desc"], ENT_QUOTES, "UTF-8") ?></p>
                        </div>
                        <button class="mt-6 rounded-xl bg-primary px-4 py-2.5 text-white transition hover:bg-primaryDark" onclick="ouvrirFormulaire('<?= htmlspecialchars($item["title"], ENT_QUOTES, "UTF-8") ?>')">Commander</button>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
    </main>

    <?php if (!$aboutOnly): ?>
    <div id="formulaireModal" class="fixed inset-0 z-[60] hidden items-center justify-center bg-black/50 p-4">
        <div class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-2xl border bg-white p-5 shadow-2xl dark:border-slate-700 dark:bg-slate-800 sm:p-6">
            <div class="mb-4 flex items-start justify-between gap-4">
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Passer commande</h2>
                <button type="button" onclick="fermerFormulaire()" class="h-9 w-9 rounded-lg bg-slate-100 dark:bg-slate-700" aria-label="Fermer">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form method="POST" id="commandeForm" class="space-y-4">
                <input type="hidden" id="prestationNom" name="type_projet" value="<?= old("type_projet") ?>">
                <div>
                    <label for="prenom" class="mb-1 block font-medium">Prénom</label>
                    <input id="prenom" type="text" name="prenom" value="<?= old("prenom") ?>" required class="w-full rounded-xl border px-3 py-2 text-black">
                </div>
                <div>
                    <label for="clientNom" class="mb-1 block font-medium">Nom</label>
                    <input id="clientNom" type="text" name="clientNom" value="<?= old("clientNom") ?>" required class="w-full rounded-xl border px-3 py-2 text-black">
                </div>
                <div>
                    <label class="mb-1 block font-medium">Sexe</label>
                    <div class="flex gap-5">
                        <label class="inline-flex items-center gap-2"><input type="radio" name="sexe" value="masculin" <?= (($_POST["sexe"] ?? "") === "masculin") ? "checked" : "" ?> required> Masculin</label>
                        <label class="inline-flex items-center gap-2"><input type="radio" name="sexe" value="feminin" <?= (($_POST["sexe"] ?? "") === "feminin") ? "checked" : "" ?> required> Féminin</label>
                    </div>
                </div>
                <div>
                    <label for="password" class="mb-1 block font-medium">Mot de passe</label>
                    <div class="relative">
                        <input id="password" type="password" name="password" required class="w-full rounded-xl border px-3 py-2 pr-12 text-black">
                        <button type="button" data-password-toggle="password" class="absolute inset-y-0 right-2 my-auto h-9 w-9 rounded-lg text-gray-600 hover:bg-gray-100" aria-label="Afficher/Masquer"><i class="fa-solid fa-eye"></i></button>
                    </div>
                </div>
                <div>
                    <label for="date_naissance" class="mb-1 block font-medium">Date de naissance</label>
                    <input id="date_naissance" type="date" name="date_naissance" value="<?= old("date_naissance") ?>" required class="w-full rounded-xl border px-3 py-2 text-black">
                </div>
                <div>
                    <label for="email" class="mb-1 block font-medium">Email</label>
                    <input id="email" type="email" name="email" value="<?= old("email") ?>" required class="w-full rounded-xl border px-3 py-2 text-black">
                </div>
                <div>
                    <label for="clientTel" class="mb-1 block font-medium">Téléphone</label>
                    <input id="clientTel" type="tel" name="clientTel" value="<?= old("clientTel") ?>" required class="w-full rounded-xl border px-3 py-2 text-black">
                </div>
                <div class="flex flex-col justify-end gap-2 pt-2 sm:flex-row">
                    <button type="button" onclick="fermerFormulaire()" class="rounded-xl bg-slate-200 px-4 py-2 text-slate-900">Annuler</button>
                    <button type="submit" class="rounded-xl bg-primary px-4 py-2 text-white">Envoyer la demande</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <footer id="contact" class="bg-fuchsia-700 text-gray-100 dark:bg-slate-800">
        <div class="mx-auto grid max-w-7xl gap-10 px-6 py-12 md:grid-cols-3">
            <div>
            <img src="../image/logo EltaRH.png" alt="EltaRH" class="h-20 sm:h-24 w-auto">
                <p class="mb-8 max-w-sm text-lg text-gray-200">Innover pour simplifier la gestion de vos projets informatiques au Cameroun et au-delà.</p>
                <div class="flex flex-wrap gap-4">
                    <a href="#" class="flex h-11 w-11 items-center justify-center rounded-full bg-white/20 transition hover:bg-fuchsia-600"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="flex h-11 w-11 items-center justify-center rounded-full bg-white/20 transition hover:bg-fuchsia-600"><i class="fab fa-linkedin-in"></i></a>
                    <a href="#" class="flex h-11 w-11 items-center justify-center rounded-full bg-white/20 transition hover:bg-fuchsia-600"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="flex h-11 w-11 items-center justify-center rounded-full bg-white/20 transition hover:bg-fuchsia-600"><i class="fab fa-youtube"></i></a>
                </div>
            </div>
            <div>
                <h3 class="mb-3 font-semibold text-white">Navigation</h3>
                <ul class="space-y-2 text-gray-200">
                    <li><a href="Accueil.php" class="hover:text-white">Accueil</a></li>
                    <li><a href="Commande.php#commande" class="hover:text-white">Commandes</a></li>
                    <li><a href="Commande.php?view=about" class="hover:text-white">À propos</a></li>
                    <li><a href="connexion.php" class="hover:text-white">Connexion</a></li>
                </ul>
            </div>
            <div>
                <h3 class="mb-6 text-sm font-bold uppercase tracking-widest text-white">Contact</h3>
                <ul class="space-y-4 text-gray-200">
                    <li class="flex items-center gap-3"><i class="fas fa-map-marker-alt text-fuchsia-200"></i> Douala, Cameroun</li>
                    <li class="flex items-center gap-3"><i class="fas fa-phone-alt text-fuchsia-200"></i> +237 650 80 75 35</li>
                    <li class="flex items-center gap-3"><i class="fas fa-envelope text-fuchsia-200"></i> infos@didacsoft.com</li>
                </ul>
            </div>
        </div>
        <div class="border-t border-white/20 py-4 text-center text-sm text-white">© 2026 Développé par MBELTA LOVE</div>
    </footer>

    <script>
        const html = document.documentElement;
        const darkButtons = [document.getElementById("darkToggle"), document.getElementById("darkToggleMobile")].filter(Boolean);

        function updateThemeIcons() {
            darkButtons.forEach((btn) => {
                btn.innerHTML = html.classList.contains("dark") ? '<i class="fa-solid fa-sun"></i>' : '<i class="fa-solid fa-moon"></i>';
            });
        }

        if (localStorage.getItem("theme") === "dark") {
            html.classList.add("dark");
        }
        updateThemeIcons();

        darkButtons.forEach((btn) => btn.addEventListener("click", () => {
            html.classList.toggle("dark");
            localStorage.setItem("theme", html.classList.contains("dark") ? "dark" : "light");
            updateThemeIcons();
        }));

        const mobileMenuToggle = document.getElementById("mobileMenuToggle");
        const mobileMenu = document.getElementById("mobileMenu");
        if (mobileMenuToggle && mobileMenu) {
            mobileMenuToggle.addEventListener("click", () => mobileMenu.classList.toggle("hidden"));
        }

        const commandeSliderTrack = document.getElementById("commandeSliderTrack");
        if (commandeSliderTrack) {
            const slides = commandeSliderTrack.querySelectorAll(".cmd-slide");
            let currentIndex = 0;
            const total = slides.length;
            window.setInterval(() => {
                currentIndex = (currentIndex + 1) % total;
                commandeSliderTrack.style.transform = `translateX(-${currentIndex * 100}%)`;
            }, 5200);
        }

        const modal = document.getElementById("formulaireModal");
        const prestationNom = document.getElementById("prestationNom");

        function ouvrirFormulaire(typeProjet) {
            if (!modal || !prestationNom) return;
            prestationNom.value = typeProjet;
            modal.classList.remove("hidden");
            modal.classList.add("flex");
            document.body.classList.add("overflow-hidden");
        }

        function fermerFormulaire() {
            if (!modal) return;
            modal.classList.add("hidden");
            modal.classList.remove("flex");
            document.body.classList.remove("overflow-hidden");
        }

        if (modal) {
            modal.addEventListener("click", (e) => {
                if (e.target === modal) fermerFormulaire();
            });
        }

        document.addEventListener("keydown", (e) => {
            if (e.key === "Escape") fermerFormulaire();
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

        <?php if ($_SERVER["REQUEST_METHOD"] === "POST" && $error): ?>
        ouvrirFormulaire('<?= old("type_projet") ?>');
        <?php endif; ?>
    </script>
</body>
</html>

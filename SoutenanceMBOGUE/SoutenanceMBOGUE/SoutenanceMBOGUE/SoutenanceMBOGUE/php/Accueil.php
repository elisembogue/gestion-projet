<?php
$aboutOnly = (($_GET['view'] ?? '') === 'about');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accueil | EltaRH</title>
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
<body class="font-poppins bg-slate-50 text-slate-900 dark:bg-slate-900 dark:text-slate-100">
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
                <a href="Accueil.php?view=about" class="transition hover:text-fuchsia-200">A-propos</a>
                <a href="#contact" class="transition hover:text-fuchsia-200">Contact</a>
                <a href="connexion.php" class="rounded-full bg-white px-4 py-2 font-bold text-primary">Connexion</a>
                <button id="darkToggle" class="h-10 w-10 rounded-xl bg-white/20" aria-label="Basculer le thème">
                    <i class="fa-solid fa-moon"></i>
                </button>
            </nav>

            <div class="flex items-center gap-2 md:hidden">
                <button id="darkToggleMobile" class="h-10 w-10 rounded-xl bg-white/20 text-white" aria-label="Basculer le thème mobile">
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
                <a href="Accueil.php?view=about" class="block">A-propos</a>
                <a href="#contact" class="block">Contact</a>
                <a href="connexion.php" class="mt-2 inline-flex rounded-full bg-white px-4 py-2 font-bold text-primary">Connexion</a>
            </div>
        </div>
    </header>

    <main id="accueil" class="pt-24">
        <?php if ($aboutOnly): ?>
        <section id="a-propos" class="mx-auto max-w-7xl px-4 py-16 sm:px-6">
            <div class="overflow-hidden rounded-3xl border border-fuchsia-200/60 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-800">
                <div class="bg-gradient-to-r from-fuchsia-700 to-fuchsia-900 px-7 py-8 text-white">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-fuchsia-100">A-propos</p>
                    <h1 class="mt-2 text-3xl font-black">Didacsoft</h1>
                    <p class="mt-3 max-w-3xl text-fuchsia-100">Entreprise de solutions logicielles dédiée à la digitalisation des organisations, avec une approche orientée qualité, robustesse et performance.</p>
                </div>
                <div class="grid gap-5 p-7 md:grid-cols-2">
                    <article class="rounded-2xl border border-slate-200 bg-slate-50 p-5 dark:border-slate-700 dark:bg-slate-900">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white">Notre expertise</h2>
                        <p class="mt-2 text-slate-600 dark:text-slate-300">Nos équipes regroupent des experts en génie logiciel, systèmes d'information et gestion d'entreprise pour concevoir des solutions adaptées aux besoins réels.</p>
                    </article>
                    <article class="rounded-2xl border border-slate-200 bg-slate-50 p-5 dark:border-slate-700 dark:bg-slate-900">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white">Nos missions</h2>
                        <ul class="mt-2 space-y-2 text-slate-600 dark:text-slate-300">
                            <li>Créer des logiciels puissants pour les besoins locaux.</li>
                            <li>Accélérer l'informatisation des secteurs de gestion.</li>
                            <li>Garantir la pérennité des données des PME.</li>
                        </ul>
                    </article>
                    <article class="rounded-2xl border border-slate-200 bg-slate-50 p-5 dark:border-slate-700 dark:bg-slate-900">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white">Notre vision</h2>
                        <p class="mt-2 text-slate-600 dark:text-slate-300">Démocratiser la transformation digitale des entreprises au Cameroun grâce à des solutions accessibles, personnalisables et faciles à utiliser.</p>
                    </article>
                    <article class="rounded-2xl border border-slate-200 bg-slate-50 p-5 dark:border-slate-700 dark:bg-slate-900">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white">Nos valeurs</h2>
                        <p class="mt-2 text-slate-600 dark:text-slate-300">Innovation, qualité, robustesse, proximité client et sécurité des données constituent la base de tous nos projets.</p>
                    </article>
                </div>
            </div>
        </section>
        <?php else: ?>
        <section class="relative overflow-hidden">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(192,38,211,0.35),transparent_45%),linear-gradient(to_bottom_right,#701a75,#1e1b4b)]"></div>
            <div class="relative mx-auto grid max-w-7xl gap-10 px-4 py-20 sm:px-6 lg:grid-cols-2 lg:items-center">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-fuchsia-200">Plateforme métier</p>
                    <h1 class="mt-3 text-4xl font-black leading-tight text-white md:text-6xl">Un pilotage clair de vos services digitaux.</h1>
                    <p class="mt-5 max-w-2xl text-lg text-fuchsia-100">Inspirée de l'identité Didacsoft, la plateforme EltaRH rassemble commande, production et communication dans une interface professionnelle et rapide.</p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <a href="Commande.php" class="rounded-full bg-white px-6 py-3 font-bold text-primary">Passer une commande</a>
                        <a href="connexion.php" class="rounded-full border border-white/70 px-6 py-3 text-white">Accéder au dashboard</a>
                    </div>
                    <div class="mt-8 grid max-w-xl grid-cols-3 gap-3">
                        <div class="rounded-2xl border border-white/20 bg-white/15 p-3 text-white">
                            <p class="text-2xl font-black">24/7</p>
                            <p class="text-xs text-fuchsia-100">Suivi projet</p>
                        </div>
                        <div class="rounded-2xl border border-white/20 bg-white/15 p-3 text-white">
                            <p class="text-2xl font-black">100%</p>
                            <p class="text-xs text-fuchsia-100">Transparence</p>
                        </div>
                        <div class="rounded-2xl border border-white/20 bg-white/15 p-3 text-white">
                            <p class="text-2xl font-black">3 rôles</p>
                            <p class="text-xs text-fuchsia-100">Client · Employé · Admin</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl border border-white/20 bg-white/10 p-6 backdrop-blur md:p-8">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-fuchsia-200">Vision opérationnelle</p>
                    <h2 class="mt-3 text-2xl font-bold text-white">Un espace de travail simple et structuré</h2>
                    <p class="mt-4 text-fuchsia-100">Votre équipe suit les commandes, échange avec les clients et mesure la progression dans un seul environnement.</p>
                    <div class="mt-6 space-y-3 text-sm text-fuchsia-100">
                        <p class="flex items-center gap-3"><i class="fa-solid fa-check text-fuchsia-200"></i> Commandes centralisées par priorité</p>
                        <p class="flex items-center gap-3"><i class="fa-solid fa-check text-fuchsia-200"></i> Communication instantanée entre rôles</p>
                        <p class="flex items-center gap-3"><i class="fa-solid fa-check text-fuchsia-200"></i> Suivi de production en temps réel</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-7xl px-4 pt-12 sm:px-6">
            <div class="overflow-hidden rounded-3xl border border-fuchsia-100 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-800">
                <div id="homeSliderTrack" class="slider-track flex">
                    <div class="home-slide grid w-full shrink-0 grid-cols-1 gap-5 p-5 md:grid-cols-2">
                        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-900">
                            <img src="../image/image1.jpg" alt="Analyse projet" class="h-72 w-full object-cover object-top md:h-80">
                            <div class="p-4">
                                <h3 class="font-bold text-slate-900 dark:text-white">Cadrage stratégique</h3>
                                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Analyse du besoin, objectifs client et feuille de route validée.</p>
                            </div>
                        </article>
                        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-900">
                            <img src="../image/image2.jpg" alt="Conception solution" class="h-72 w-full object-cover object-top md:h-80">
                            <div class="p-4">
                                <h3 class="font-bold text-slate-900 dark:text-white">Conception fonctionnelle</h3>
                                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Architecture, maquettes et planning de réalisation professionnel.</p>
                            </div>
                        </article>
                    </div>
                    <div class="home-slide grid w-full shrink-0 grid-cols-1 gap-5 p-5 md:grid-cols-2">
                        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-900">
                            <img src="../image/image3.jpg" alt="Production digitale" class="h-72 w-full object-cover object-top md:h-80">
                            <div class="p-4">
                                <h3 class="font-bold text-slate-900 dark:text-white">Production continue</h3>
                                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Développement itératif avec suivi des tâches et points de contrôle.</p>
                            </div>
                        </article>
                        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-900">
                            <img src="../image/image4.jpg" alt="Collaboration équipe" class="h-72 w-full object-cover object-top md:h-80">
                            <div class="p-4">
                                <h3 class="font-bold text-slate-900 dark:text-white">Collaboration client-équipe</h3>
                                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Échanges fluides et validation rapide à chaque étape métier.</p>
                            </div>
                        </article>
                    </div>
                    <div class="home-slide grid w-full shrink-0 grid-cols-1 gap-5 p-5 md:grid-cols-2">
                        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-900">
                            <img src="../image/image5.jpg" alt="Qualité et tests" class="h-72 w-full object-cover object-top md:h-80">
                            <div class="p-4">
                                <h3 class="font-bold text-slate-900 dark:text-white">Contrôle qualité</h3>
                                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Tests, corrections et conformité avant livraison finale.</p>
                            </div>
                        </article>
                        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-900">
                            <img src="../image/image8.jpg" alt="Suivi des performances" class="h-72 w-full object-cover object-top md:h-80">
                            <div class="p-4">
                                <h3 class="font-bold text-slate-900 dark:text-white">Reporting de progression</h3>
                                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Indicateurs visuels pour piloter avancement, charge et performance.</p>
                            </div>
                        </article>
                    </div>
                    <div class="home-slide grid w-full shrink-0 grid-cols-1 gap-5 p-5 md:grid-cols-2">
                        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-900">
                            <img src="../image/image7.jpg" alt="Nouvelle activité 1" class="h-72 w-full object-cover object-top md:h-80">
                            <div class="p-4">
                                <h3 class="font-bold text-slate-900 dark:text-white">Pilotage visuel avancé</h3>
                                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Suivi opérationnel enrichi avec de nouveaux supports visuels.</p>
                            </div>
                        </article>
                        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-900">
                            <img src="../image/iage6.jpg" alt="Nouvelle activité 2" class="h-72 w-full object-cover object-top md:h-80">
                            <div class="p-4">
                                <h3 class="font-bold text-slate-900 dark:text-white">Communication projet renforcée</h3>
                                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Coordination d'équipe optimisée pour accélérer l’exécution.</p>
                            </div>
                        </article>
                    </div>
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-7xl px-4 py-10 sm:px-6">
            <div class="grid gap-6 md:grid-cols-3">
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <p class="text-sm text-slate-500 dark:text-slate-300">Engagement</p>
                    <p class="mt-1 text-3xl font-black text-primary">SLA 24h</p>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Réponse opérationnelle sous 24 heures pour chaque nouvelle demande.</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <p class="text-sm text-slate-500 dark:text-slate-300">Méthode</p>
                    <p class="mt-1 text-3xl font-black text-primary">Agile</p>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Cycles courts, points hebdomadaires et ajustements rapides.</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <p class="text-sm text-slate-500 dark:text-slate-300">Traçabilité</p>
                    <p class="mt-1 text-3xl font-black text-primary">100%</p>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Toutes les actions sont historisées sur vos dashboards.</p>
                </article>
            </div>
        </section>

        <?php endif; ?>
    </main>

    <footer id="contact" class="bg-fuchsia-700 text-gray-100 dark:bg-slate-800">
        <div class="mx-auto grid max-w-7xl gap-10 px-6 py-12 md:grid-cols-3">
            <div>
                <img src="../image/logo EltaRH.png" alt="EltaRH" class="h-20 sm:h-24 w-auto">
                <p class="mb-8 max-w-sm text-lg text-gray-200">Innover pour simplifier la gestion de vos projets informatiques au Cameroun et au-delà.</p>
                <div class="flex flex-wrap gap-4">
                    <a href="https://facebook.com" class="flex h-11 w-11 items-center justify-center rounded-full bg-white/20 transition hover:bg-fuchsia-600"><i class="fab fa-facebook-f"></i></a>
                    <a href="https://linkedin.com" class="flex h-11 w-11 items-center justify-center rounded-full bg-white/20 transition hover:bg-fuchsia-600"><i class="fab fa-linkedin-in"></i></a>
                    <a href="https://instagram.com" class="flex h-11 w-11 items-center justify-center rounded-full bg-white/20 transition hover:bg-fuchsia-600"><i class="fab fa-instagram"></i></a>
                    <a href="https://youtube.com" class="flex h-11 w-11 items-center justify-center rounded-full bg-white/20 transition hover:bg-fuchsia-600"><i class="fab fa-youtube"></i></a>
                </div>
            </div>
            <div>
                <h3 class="mb-3 font-semibold text-white">Navigation</h3>
                <ul class="space-y-2 text-gray-200">
                    <li><a href="Accueil.php" class="hover:text-white">Accueil</a></li>
                    <li><a href="Commande.php#commande" class="hover:text-white">Commandes</a></li>
                    <li><a href="Accueil.php?view=about" class="hover:text-white">A-propos</a></li>
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
        const root = document.documentElement;
        const darkButtons = [document.getElementById("darkToggle"), document.getElementById("darkToggleMobile")].filter(Boolean);

        function syncThemeIcon() {
            darkButtons.forEach((btn) => {
                btn.innerHTML = root.classList.contains("dark") ? '<i class="fa-solid fa-sun"></i>' : '<i class="fa-solid fa-moon"></i>';
            });
        }

        if (localStorage.getItem("theme") === "dark") {
            root.classList.add("dark");
        }
        syncThemeIcon();

        darkButtons.forEach((btn) => btn.addEventListener("click", () => {
            root.classList.toggle("dark");
            localStorage.setItem("theme", root.classList.contains("dark") ? "dark" : "light");
            syncThemeIcon();
        }));

        const mobileToggle = document.getElementById("mobileMenuToggle");
        const mobileMenu = document.getElementById("mobileMenu");
        if (mobileToggle && mobileMenu) {
            mobileToggle.addEventListener("click", () => mobileMenu.classList.toggle("hidden"));
        }

        const sliderTrack = document.getElementById("homeSliderTrack");
        if (sliderTrack) {
            const slides = sliderTrack.querySelectorAll(".home-slide");
            let currentIndex = 0;
            const total = slides.length;
            window.setInterval(() => {
                currentIndex = (currentIndex + 1) % total;
                sliderTrack.style.transform = `translateX(-${currentIndex * 100}%)`;
            }, 5000);
        }
    </script>
</body>
</html>


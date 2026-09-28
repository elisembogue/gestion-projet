-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : mar. 03 mars 2026 à 19:50
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `gestionprojet`
--

-- --------------------------------------------------------

--
-- Structure de la table `employe_presence`
--

CREATE TABLE `employe_presence` (
  `id` int(11) NOT NULL,
  `employe_id` int(11) NOT NULL,
  `presence_date` date NOT NULL,
  `first_seen_at` datetime NOT NULL,
  `last_seen_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `employe_presence`
--

INSERT INTO `employe_presence` (`id`, `employe_id`, `presence_date`, `first_seen_at`, `last_seen_at`) VALUES
(1, 16, '2026-03-03', '2026-03-03 16:58:29', '2026-03-03 19:17:01');

-- --------------------------------------------------------

--
-- Structure de la table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `messages`
--

INSERT INTO `messages` (`id`, `sender_id`, `receiver_id`, `message`, `created_at`) VALUES
(1, 19, 16, 'Bonjour Mr', '2026-03-03 13:18:39'),
(2, 15, 18, 'Bonjour Madame', '2026-03-03 13:32:49'),
(3, 18, 15, 'dfgh', '2026-03-03 14:26:04'),
(4, 15, 18, 'bjr', '2026-03-03 14:28:02'),
(5, 15, 18, 'le projet serabientot disponible', '2026-03-03 14:33:13'),
(6, 18, 15, 'parfait', '2026-03-03 14:50:51'),
(7, 15, 18, 'OK', '2026-03-03 14:51:20');

-- --------------------------------------------------------

--
-- Structure de la table `project_plans`
--

CREATE TABLE `project_plans` (
  `id` int(11) NOT NULL,
  `projet_id` int(11) NOT NULL,
  `employe_id` int(11) NOT NULL,
  `planned_start_date` date NOT NULL,
  `planned_end_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `project_plans`
--

INSERT INTO `project_plans` (`id`, `projet_id`, `employe_id`, `planned_start_date`, `planned_end_date`, `created_at`, `updated_at`) VALUES
(1, 2, 16, '2026-03-03', '2026-04-10', '2026-03-03 18:09:33', '2026-03-03 18:09:33');

-- --------------------------------------------------------

--
-- Structure de la table `project_progress`
--

CREATE TABLE `project_progress` (
  `id` int(11) NOT NULL,
  `projet_id` int(11) NOT NULL,
  `employe_id` int(11) NOT NULL,
  `note` text NOT NULL,
  `progress_value` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `project_progress`
--

INSERT INTO `project_progress` (`id`, `projet_id`, `employe_id`, `note`, `progress_value`, `created_at`) VALUES
(1, 2, 16, 'Conception du diagramme des cas d\'utilisation, diagramme des classe et de séquence', 1, '2026-03-03 15:37:02');

-- --------------------------------------------------------

--
-- Structure de la table `project_subtasks`
--

CREATE TABLE `project_subtasks` (
  `id` int(11) NOT NULL,
  `projet_id` int(11) NOT NULL,
  `task_title_id` int(11) NOT NULL,
  `employe_id` int(11) NOT NULL,
  `label` varchar(255) NOT NULL,
  `is_done` tinyint(1) NOT NULL DEFAULT 0,
  `done_at` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `project_subtasks`
--

INSERT INTO `project_subtasks` (`id`, `projet_id`, `task_title_id`, `employe_id`, `label`, `is_done`, `done_at`, `created_at`) VALUES
(1, 2, 1, 16, '-collecte des données', 1, '2026-03-03', '2026-03-03 18:09:33'),
(2, 2, 1, 16, '-Trie en catégorie', 0, NULL, '2026-03-03 18:09:33'),
(3, 2, 1, 16, '-Descente sur le terrain', 0, NULL, '2026-03-03 18:09:33'),
(4, 2, 1, 16, '-Etablissement de la liste des besoin', 0, NULL, '2026-03-03 18:09:33');

-- --------------------------------------------------------

--
-- Structure de la table `project_task_titles`
--

CREATE TABLE `project_task_titles` (
  `id` int(11) NOT NULL,
  `projet_id` int(11) NOT NULL,
  `employe_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `project_task_titles`
--

INSERT INTO `project_task_titles` (`id`, `projet_id`, `employe_id`, `title`, `created_at`) VALUES
(1, 2, 16, 'Etude du besoin', '2026-03-03 18:09:33');

-- --------------------------------------------------------

--
-- Structure de la table `projet`
--

CREATE TABLE `projet` (
  `id` int(11) NOT NULL,
  `id_client` int(11) NOT NULL,
  `type_projet` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `statut` enum('en_attente','en_cours','termine') DEFAULT 'en_attente',
  `date_debut` date DEFAULT NULL,
  `date_fin` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `projet`
--

INSERT INTO `projet` (`id`, `id_client`, `type_projet`, `description`, `statut`, `date_debut`, `date_fin`, `created_at`) VALUES
(1, 3, 'Application mobile', 'App de gestion commerciale', 'en_cours', NULL, NULL, '2026-02-23 14:54:55'),
(2, 19, 'Marketing Web', NULL, 'en_cours', '2026-03-03', NULL, '2026-02-23 14:54:55');

-- --------------------------------------------------------

--
-- Structure de la table `projets_employes`
--

CREATE TABLE `projets_employes` (
  `id` int(11) NOT NULL,
  `projet_id` int(11) NOT NULL,
  `employe_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `projets_employes`
--

INSERT INTO `projets_employes` (`id`, `projet_id`, `employe_id`) VALUES
(1, 2, 16);

-- --------------------------------------------------------

--
-- Structure de la table `taches`
--

CREATE TABLE `taches` (
  `id` int(11) NOT NULL,
  `projet_id` int(11) NOT NULL,
  `titre` varchar(100) NOT NULL,
  `statut` enum('a_faire','en_cours','terminee') DEFAULT 'a_faire',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `taches`
--

INSERT INTO `taches` (`id`, `projet_id`, `titre`, `statut`, `created_at`) VALUES
(1, 2, 'Analyse UML du projet', 'a_faire', '2026-03-03 15:35:46');

-- --------------------------------------------------------

--
-- Structure de la table `task_daily_logs`
--

CREATE TABLE `task_daily_logs` (
  `id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `projet_id` int(11) NOT NULL,
  `employe_id` int(11) NOT NULL,
  `work_date` date NOT NULL,
  `details` varchar(255) NOT NULL,
  `done_units` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `task_targets`
--

CREATE TABLE `task_targets` (
  `task_id` int(11) NOT NULL,
  `target_units` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `matricule` int(11) NOT NULL,
  `nom` varchar(50) NOT NULL,
  `prenom` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(100) NOT NULL,
  `sexe` enum('masculin','feminin') NOT NULL,
  `telephone` varchar(15) NOT NULL,
  `date_naissance` date NOT NULL,
  `role` enum('admin','employe','client') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `photo` varchar(255) DEFAULT 'default.png'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`matricule`, `nom`, `prenom`, `email`, `password`, `sexe`, `telephone`, `date_naissance`, `role`, `created_at`, `photo`) VALUES
(2, 'Dupont', 'Jean', 'client@mail.com', '$2y$10$abcdefghijklmnopqrstuv', 'masculin', '690123456', '1995-05-12', 'client', '2026-02-23 14:54:55', 'default.png'),
(3, 'Martin', 'Alice', 'employe@mail.com', '$2y$10$abcdefghijklmnopqrstuv', 'feminin', '697888888', '1998-08-20', 'employe', '2026-02-23 14:54:55', 'default.png'),
(12, 'SANCHEZ', 'Louis', 'louis@gmail.com', '$2y$10$ni/ahwrJ9tUaSf4QuwQBLOMA2HZmzXLf4Uy8/7KlOnSGvCw4pHXUe', 'masculin', '7918918', '1990-08-24', 'client', '2026-02-23 14:54:55', 'default.png'),
(15, 'SUAREZ', 'Patricia', 'suarez@gmail.com', '$2y$10$MQ3dhziYnJKw3m4kt0Vtde1X7oBUU8UxFJb8dq.sTOYrzoh4WPuU6', 'feminin', '698321671', '1989-03-19', 'employe', '2026-02-23 14:54:55', 'uploads/profile_15_1771255396.jpg'),
(16, 'SEULEU', 'William', 'william@gmail.com', '$2y$10$3gUaJol1W7atDflvXj2UteKcbK7MLPl1NXvEZRtbc79Mv0gQAxbvW', 'masculin', '693213981', '2000-11-12', 'employe', '2026-02-23 14:54:55', 'uploads/profiles/emp_16_1771861308.png'),
(18, 'MBELTA', 'Love', 'mbeltalove@gmail.com', '$2y$10$kNOZ4z1LQ7mrlQqooWLVAutMfRxXARKiLWDaiOwIwQbJyELfi2xgu', 'masculin', '', '0000-00-00', 'admin', '2026-02-23 14:54:55', 'uploads/profiles/admin_18_1771261759.jpg'),
(19, 'NYA', 'Junior', 'junior@gmail.com', '$2y$10$k1TBJcHeHayizWxcGpz1G.V4VgqXd1J5OvFdE2FHvUbPL2R.nGDRy', 'masculin', '69329891', '1987-09-23', 'client', '2026-02-23 14:54:55', 'uploads/profile_19.jpg');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `employe_presence`
--
ALTER TABLE `employe_presence`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_presence` (`employe_id`,`presence_date`),
  ADD KEY `idx_presence_date` (`presence_date`);

--
-- Index pour la table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- Index pour la table `project_plans`
--
ALTER TABLE `project_plans`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `projet_id` (`projet_id`),
  ADD KEY `idx_plan_employe` (`employe_id`);

--
-- Index pour la table `project_progress`
--
ALTER TABLE `project_progress`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_project` (`projet_id`),
  ADD KEY `idx_employe` (`employe_id`);

--
-- Index pour la table `project_subtasks`
--
ALTER TABLE `project_subtasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sub_project` (`projet_id`),
  ADD KEY `idx_sub_title` (`task_title_id`),
  ADD KEY `idx_sub_done` (`is_done`,`done_at`);

--
-- Index pour la table `project_task_titles`
--
ALTER TABLE `project_task_titles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_title_project` (`projet_id`),
  ADD KEY `idx_title_employe` (`employe_id`);

--
-- Index pour la table `projet`
--
ALTER TABLE `projet`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_client` (`id_client`);

--
-- Index pour la table `projets_employes`
--
ALTER TABLE `projets_employes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `projet_id` (`projet_id`),
  ADD KEY `employe_id` (`employe_id`);

--
-- Index pour la table `taches`
--
ALTER TABLE `taches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `projet_id` (`projet_id`);

--
-- Index pour la table `task_daily_logs`
--
ALTER TABLE `task_daily_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_task_date` (`task_id`,`work_date`),
  ADD KEY `idx_project` (`projet_id`),
  ADD KEY `idx_employe` (`employe_id`);

--
-- Index pour la table `task_targets`
--
ALTER TABLE `task_targets`
  ADD PRIMARY KEY (`task_id`),
  ADD KEY `idx_target_units` (`target_units`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`matricule`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `employe_presence`
--
ALTER TABLE `employe_presence`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=99;

--
-- AUTO_INCREMENT pour la table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pour la table `project_plans`
--
ALTER TABLE `project_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `project_progress`
--
ALTER TABLE `project_progress`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `project_subtasks`
--
ALTER TABLE `project_subtasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `project_task_titles`
--
ALTER TABLE `project_task_titles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `projet`
--
ALTER TABLE `projet`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `projets_employes`
--
ALTER TABLE `projets_employes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `taches`
--
ALTER TABLE `taches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `task_daily_logs`
--
ALTER TABLE `task_daily_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `matricule` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`matricule`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`matricule`) ON DELETE CASCADE;

--
-- Contraintes pour la table `projet`
--
ALTER TABLE `projet`
  ADD CONSTRAINT `projet_ibfk_1` FOREIGN KEY (`id_client`) REFERENCES `users` (`matricule`) ON DELETE CASCADE;

--
-- Contraintes pour la table `projets_employes`
--
ALTER TABLE `projets_employes`
  ADD CONSTRAINT `projets_employes_ibfk_1` FOREIGN KEY (`projet_id`) REFERENCES `projet` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `projets_employes_ibfk_2` FOREIGN KEY (`employe_id`) REFERENCES `users` (`matricule`) ON DELETE CASCADE;

--
-- Contraintes pour la table `taches`
--
ALTER TABLE `taches`
  ADD CONSTRAINT `taches_ibfk_1` FOREIGN KEY (`projet_id`) REFERENCES `projet` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

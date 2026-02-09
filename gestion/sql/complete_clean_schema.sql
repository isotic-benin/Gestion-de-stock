-- Script de création de base de données complet et propre
-- Généré le : 22 Janvier 2026
-- Ce script crée toutes les tables nécessaires, les tables de permissions,
-- et un utilisateur Administrateur par défaut avec tous les accès.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Structure de la table `categories`
--

CREATE TABLE IF NOT EXISTS `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nom` (`nom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Structure de la table `clients`
--

CREATE TABLE IF NOT EXISTS `clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `adresse` varchar(255) DEFAULT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `telephone` (`telephone`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Structure de la table `entreprise_infos`
--

CREATE TABLE IF NOT EXISTS `entreprise_infos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(150) NOT NULL,
  `adresse` varchar(255) DEFAULT NULL,
  `telephone` varchar(30) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `ifu` varchar(50) DEFAULT NULL,
  `rccm` varchar(100) DEFAULT NULL,
  `devise` varchar(25) DEFAULT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `date_modification` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Structure de la table `fournisseurs`
--

CREATE TABLE IF NOT EXISTS `fournisseurs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(150) NOT NULL,
  `contact_personne` varchar(150) DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `adresse` varchar(255) DEFAULT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Structure de la table `magasins`
--

CREATE TABLE IF NOT EXISTS `magasins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(150) NOT NULL,
  `adresse` varchar(255) DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Structure de la table `personnel`
--

CREATE TABLE IF NOT EXISTS `personnel` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `nom_utilisateur` varchar(50) NOT NULL,
  `mot_de_passe` varchar(255) NOT NULL, -- Note: Stockage en clair observé dans le code existant
  `role` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `magasin_id` int(11) DEFAULT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nom_utilisateur` (`nom_utilisateur`),
  UNIQUE KEY `email` (`email`),
  KEY `fk_personnel_magasin` (`magasin_id`),
  CONSTRAINT `fk_personnel_magasin` FOREIGN KEY (`magasin_id`) REFERENCES `magasins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Structure de la table `produits`
--

CREATE TABLE IF NOT EXISTS `produits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `prix_achat` decimal(10,2) NOT NULL DEFAULT 0.00,
  `prix_vente` decimal(10,2) NOT NULL DEFAULT 0.00,
  `quantite_stock` decimal(10,2) NOT NULL DEFAULT 0.00,
  `seuil_alerte_stock` int(11) DEFAULT 5,
  `fournisseur_id` int(11) DEFAULT NULL,
  `categorie_id` int(11) DEFAULT NULL,
  `magasin_id` int(11) DEFAULT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  `date_modification` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_produits_fournisseur` (`fournisseur_id`),
  KEY `fk_produits_categorie` (`categorie_id`),
  KEY `fk_produits_magasin` (`magasin_id`),
  CONSTRAINT `fk_produits_fournisseur` FOREIGN KEY (`fournisseur_id`) REFERENCES `fournisseurs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_produits_categorie` FOREIGN KEY (`categorie_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_produits_magasin` FOREIGN KEY (`magasin_id`) REFERENCES `magasins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Structure de la table `ventes`
--

CREATE TABLE IF NOT EXISTS `ventes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) DEFAULT NULL,
  `magasin_id` int(11) NOT NULL,
  `personnel_id` int(11) NOT NULL,
  `date_vente` datetime DEFAULT current_timestamp(),
  `montant_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `montant_recu_espece` decimal(10,2) DEFAULT 0.00,
  `montant_recu_momo` decimal(10,2) DEFAULT 0.00,
  `montant_change` decimal(10,2) DEFAULT 0.00,
  `montant_du` decimal(10,2) DEFAULT 0.00,
  `type_paiement` varchar(50) DEFAULT 'espece',
  `statut_paiement` varchar(50) DEFAULT 'paye',
  `reduction_globale_pourcentage` decimal(5,2) DEFAULT 0.00,
  `reduction_globale_montant` decimal(10,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `fk_ventes_client` (`client_id`),
  KEY `fk_ventes_magasin` (`magasin_id`),
  KEY `fk_ventes_personnel` (`personnel_id`),
  CONSTRAINT `fk_ventes_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ventes_magasin` FOREIGN KEY (`magasin_id`) REFERENCES `magasins` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ventes_personnel` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Structure de la table `details_vente`
--

CREATE TABLE IF NOT EXISTS `details_vente` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vente_id` int(11) NOT NULL,
  `produit_id` int(11) NOT NULL,
  `quantite` decimal(10,2) NOT NULL,
  `prix_vente_unitaire` decimal(10,2) NOT NULL,
  `prix_achat_unitaire` decimal(10,2) NOT NULL,
  `reduction_ligne_pourcentage` decimal(5,2) DEFAULT 0.00,
  `reduction_ligne_montant` decimal(10,2) DEFAULT 0.00,
  `total_ligne` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_details_vente_vente` (`vente_id`),
  KEY `fk_details_vente_produit` (`produit_id`),
  CONSTRAINT `fk_details_vente_vente` FOREIGN KEY (`vente_id`) REFERENCES `ventes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_details_vente_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Structure de la table `retours_vente`
--

CREATE TABLE IF NOT EXISTS `retours_vente` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vente_id` int(11) NOT NULL,
  `produit_id` int(11) NOT NULL,
  `quantite_retournee` decimal(10,2) NOT NULL,
  `montant_rembourse` decimal(10,2) NOT NULL,
  `raison_retour` text DEFAULT NULL,
  `personnel_id` int(11) NOT NULL,
  `magasin_id` int(11) NOT NULL,
  `date_retour` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_retours_vente` (`vente_id`),
  KEY `fk_retours_produit` (`produit_id`),
  KEY `fk_retours_personnel` (`personnel_id`),
  KEY `fk_retours_magasin` (`magasin_id`),
  CONSTRAINT `fk_retours_vente` FOREIGN KEY (`vente_id`) REFERENCES `ventes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_retours_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`),
  CONSTRAINT `fk_retours_personnel` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`id`),
  CONSTRAINT `fk_retours_magasin` FOREIGN KEY (`magasin_id`) REFERENCES `magasins` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Structure de la table `depenses`
--

CREATE TABLE IF NOT EXISTS `depenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `description` varchar(255) NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `date_depense` date NOT NULL,
  `categorie_depense` varchar(100) DEFAULT NULL,
  `personnel_id` int(11) NOT NULL,
  `magasin_id` int(11) DEFAULT NULL,
  `date_enregistrement` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_depenses_personnel` (`personnel_id`),
  KEY `fk_depenses_magasin` (`magasin_id`),
  CONSTRAINT `fk_depenses_personnel` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`id`),
  CONSTRAINT `fk_depenses_magasin` FOREIGN KEY (`magasin_id`) REFERENCES `magasins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Structure de la table `transferts_stock`
--

CREATE TABLE IF NOT EXISTS `transferts_stock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `produit_id` int(11) NOT NULL,
  `magasin_source_id` int(11) NOT NULL,
  `magasin_destination_id` int(11) NOT NULL,
  `quantite` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `personnel_id_demande` int(11) DEFAULT NULL,
  `statut` varchar(50) DEFAULT 'en_attente',
  `date_demande` datetime DEFAULT current_timestamp(),
  `personnel_id_action` int(11) DEFAULT NULL,
  `date_action` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_transferts_produit` (`produit_id`),
  KEY `fk_transferts_source` (`magasin_source_id`),
  KEY `fk_transferts_dest` (`magasin_destination_id`),
  KEY `fk_transferts_pers_demande` (`personnel_id_demande`),
  KEY `fk_transferts_pers_action` (`personnel_id_action`),
  CONSTRAINT `fk_transferts_produit` FOREIGN KEY (`produit_id`) REFERENCES `produits` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_transferts_source` FOREIGN KEY (`magasin_source_id`) REFERENCES `magasins` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_transferts_dest` FOREIGN KEY (`magasin_destination_id`) REFERENCES `magasins` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_transferts_pers_demande` FOREIGN KEY (`personnel_id_demande`) REFERENCES `personnel` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_transferts_pers_action` FOREIGN KEY (`personnel_id_action`) REFERENCES `personnel` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Structure de la table `dettes_clients`
--

CREATE TABLE IF NOT EXISTS `dettes_clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `montant_initial` decimal(10,2) NOT NULL DEFAULT 0.00,
  `montant_restant` decimal(10,2) NOT NULL DEFAULT 0.00,
  `date_limite_paiement` date DEFAULT NULL,
  `description` text DEFAULT NULL,
  `statut` varchar(50) DEFAULT 'en_cours',
  `date_creation` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_dettes_client` (`client_id`),
  CONSTRAINT `fk_dettes_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Structure de la table `comptes_epargne`
--

CREATE TABLE IF NOT EXISTS `comptes_epargne` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `numero_compte` varchar(50) NOT NULL,
  `solde` decimal(10,2) DEFAULT 0.00,
  `date_creation` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero_compte` (`numero_compte`),
  KEY `fk_comptes_epargne_client` (`client_id`),
  CONSTRAINT `fk_comptes_epargne_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Structure de la table `transactions_epargne`
--

CREATE TABLE IF NOT EXISTS `transactions_epargne` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `compte_epargne_id` int(11) NOT NULL,
  `type_transaction` varchar(50) NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `date_transaction` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_trans_epargne_compte` (`compte_epargne_id`),
  CONSTRAINT `fk_trans_epargne_compte` FOREIGN KEY (`compte_epargne_id`) REFERENCES `comptes_epargne` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Structure de la table `dettes_magasins`
--

CREATE TABLE IF NOT EXISTS `dettes_magasins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `magasin_id` int(11) DEFAULT NULL,
  `fournisseur_id` int(11) DEFAULT NULL,
  `montant_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `montant_paye` decimal(10,2) NOT NULL DEFAULT 0.00,
  `date_dette` date NOT NULL,
  `description` text DEFAULT NULL,
  `personnel_id_enregistrement` int(11) DEFAULT NULL,
  `date_enregistrement` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_dettes_magasin_mag` (`magasin_id`),
  KEY `fk_dettes_magasin_fourn` (`fournisseur_id`),
  KEY `fk_dettes_magasin_pers` (`personnel_id_enregistrement`),
  CONSTRAINT `fk_dettes_magasin_mag` FOREIGN KEY (`magasin_id`) REFERENCES `magasins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_dettes_magasin_fourn` FOREIGN KEY (`fournisseur_id`) REFERENCES `fournisseurs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_dettes_magasin_pers` FOREIGN KEY (`personnel_id_enregistrement`) REFERENCES `personnel` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Structure de la table `paiements_dettes_magasins`
--

CREATE TABLE IF NOT EXISTS `paiements_dettes_magasins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dette_magasin_id` int(11) NOT NULL,
  `montant_paiement` decimal(10,2) NOT NULL,
  `date_paiement` datetime DEFAULT current_timestamp(),
  `personnel_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_paiement_dette_mag_dette` (`dette_magasin_id`),
  KEY `fk_paiement_dette_mag_pers` (`personnel_id`),
  CONSTRAINT `fk_paiement_dette_mag_dette` FOREIGN KEY (`dette_magasin_id`) REFERENCES `dettes_magasins` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_paiement_dette_mag_pers` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Structure des tables de Permissions
--

CREATE TABLE IF NOT EXISTS `modules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL COMMENT 'Nom affiché du module',
  `code` varchar(50) NOT NULL COMMENT 'Code unique pour identification dans le code',
  `description` text DEFAULT NULL,
  `icone` varchar(50) DEFAULT 'fas fa-cogs' COMMENT 'Classe FontAwesome',
  `ordre` int(11) DEFAULT 0 COMMENT 'Ordre d''affichage',
  `actif` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `personnel_id` int(11) NOT NULL,
  `module_id` int(11) NOT NULL,
  `can_create` tinyint(1) DEFAULT 0 COMMENT 'Permission de création',
  `can_read` tinyint(1) DEFAULT 0 COMMENT 'Permission de lecture/consultation',
  `can_update` tinyint(1) DEFAULT 0 COMMENT 'Permission de modification',
  `can_delete` tinyint(1) DEFAULT 0 COMMENT 'Permission de suppression',
  `date_creation` datetime DEFAULT current_timestamp(),
  `date_modification` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_personnel_module` (`personnel_id`, `module_id`),
  KEY `fk_permissions_personnel` (`personnel_id`),
  KEY `fk_permissions_module` (`module_id`),
  CONSTRAINT `fk_permissions_personnel` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_permissions_module` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Insertion des Modules par défaut
--

INSERT INTO `modules` (`nom`, `code`, `description`, `actif`) VALUES
('Tableau de Bord', 'dashboard', 'Accès au tableau de bord et aux statistiques globales', 1),
('Gestion des Produits', 'produits', 'Gestion du catalogue, prix et stock', 1),
('Gestion des Ventes', 'ventes', 'Point de vente et historique', 1),
('Gestion des Clients', 'clients', 'Base de données clients', 1),
('Gestion du Personnel', 'personnel', 'Comptes utilisateurs et rôles', 1),
('Gestion des Magasins', 'magasins', 'Configuration des points de vente', 1),
('Gestion des Fournisseurs', 'fournisseurs', 'Base fournisseurs', 1),
('Gestion des Dépenses', 'depenses', 'Suivi des dépenses', 1),
('Transferts de Stock', 'transferts_stock', 'Gestion des mouvements entre magasins', 1),
('Retours Ventes', 'retours_ventes', 'Gestion des retours clients', 1),
('Dettes Clients', 'dettes_clients', 'Suivi des créances clients', 1),
('Comptes Épargne', 'comptes_epargne', 'Gestion des comptes épargne clients', 1),
('Paramètres', 'parametres', 'Configuration générale de l\'entreprise et permissions', 1),
('Rapports', 'rapports', 'Statistiques et rapports d\'activité', 1),
('Dettes Entreprise', 'dettes_entreprise', 'Gestion des dettes envers les fournisseurs et magasins', 1)
ON DUPLICATE KEY UPDATE description = VALUES(description);

--
-- Création de l'Administrateur par défaut
--

-- ATTENTION : Le mot de passe est ici en CLAIR comme demandé par la structure actuelle du projet.
-- Dans un environnement sécurisé, utilisez password_hash().
INSERT INTO `personnel` (`id`, `nom`, `prenom`, `nom_utilisateur`, `mot_de_passe`, `role`, `email`, `telephone`, `magasin_id`) VALUES
(1, 'Administrateur', 'Principal', 'admin', 'admin123', 'Administrateur', 'admin@example.com', '00000000', NULL)
ON DUPLICATE KEY UPDATE role = 'Administrateur';

--
-- Attribution de toutes les permissions à l'Administrateur
--

INSERT INTO `permissions` (`personnel_id`, `module_id`, `can_create`, `can_read`, `can_update`, `can_delete`)
SELECT p.id, m.id, 1, 1, 1, 1
FROM personnel p
CROSS JOIN modules m
WHERE p.role = 'Administrateur'
ON DUPLICATE KEY UPDATE 
    can_create = 1, 
    can_read = 1, 
    can_update = 1, 
    can_delete = 1;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- ================================================================
-- SCHEMA POUR LE SYSTÈME DE PERMISSIONS DYNAMIQUE
-- Fichier: permissions_schema.sql
-- Créé le: 2026-01-29
-- ================================================================

-- Table des modules du système
CREATE TABLE IF NOT EXISTS `modules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL UNIQUE COMMENT 'Code unique du module (ex: ventes, produits)',
  `nom` varchar(100) NOT NULL COMMENT 'Nom affiché du module',
  `description` varchar(255) DEFAULT NULL COMMENT 'Description du module',
  `icone` varchar(50) DEFAULT NULL COMMENT 'Classe FontAwesome de l icone',
  `ordre` int(11) DEFAULT 0 COMMENT 'Ordre d affichage',
  `actif` tinyint(1) DEFAULT 1 COMMENT 'Module actif ou non',
  `date_creation` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table des permissions (relation personnel - module avec CRUD)
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

-- ================================================================
-- INSERTION DES MODULES PAR DÉFAUT
-- ================================================================

INSERT INTO `modules` (`code`, `nom`, `description`, `icone`, `ordre`, `actif`) VALUES
-- Tableau de bord
('dashboard', 'Tableau de Bord', 'Accès au tableau de bord principal', 'fas fa-tachometer-alt', 1, 1),

-- Module Clients
('clients', 'Gestion des Clients', 'Gestion des informations clients', 'fas fa-user-friends', 2, 1),
('comptes_epargne', 'Comptes Épargne', 'Gestion des comptes épargne clients', 'fas fa-piggy-bank', 3, 1),
('dettes_clients', 'Dettes Clients', 'Gestion des dettes des clients', 'fas fa-money-bill-wave', 4, 1),

-- Module Produits & Stock
('produits_stock', 'Produits & Stock', 'Gestion des produits et du stock', 'fas fa-box-open', 5, 1),
('transferts_stock', 'Transferts de Stock', 'Gestion des transferts entre magasins', 'fas fa-truck-ramp-box', 6, 1),

-- Module Ventes
('point_de_vente', 'Point de Vente', 'Réalisation des ventes', 'fas fa-calculator', 7, 1),
('historique_ventes', 'Historique des Ventes', 'Consultation de l historique des ventes', 'fas fa-history', 8, 1),
('retours_ventes', 'Retours de Vente', 'Gestion des retours produits', 'fas fa-undo', 9, 1),

-- Module Finances
('depenses', 'Dépenses', 'Gestion des dépenses', 'fas fa-money-bill-wave', 10, 1),
('dettes_entreprise', 'Dettes Magasins', 'Gestion des dettes de l entreprise', 'fas fa-file-invoice-dollar', 11, 1),

-- Module Fournisseurs
('fournisseurs', 'Fournisseurs', 'Gestion des fournisseurs', 'fas fa-truck', 12, 1),

-- Module Paramètres
('configuration', 'Configuration Entreprise', 'Configuration des paramètres entreprise', 'fas fa-building', 13, 1),
('magasins', 'Magasins', 'Gestion des magasins', 'fas fa-store-alt', 14, 1),
('personnel', 'Personnel', 'Gestion du personnel', 'fas fa-users', 15, 1),
('permissions', 'Permissions', 'Gestion des permissions utilisateurs', 'fas fa-user-shield', 16, 1),

-- Module Statistiques
('statistiques', 'Statistiques & Rapports', 'Accès aux statistiques et rapports', 'fas fa-chart-pie', 17, 1);

-- ================================================================
-- PERMISSIONS PAR DÉFAUT POUR LES ADMINISTRATEURS EXISTANTS
-- (Donne toutes les permissions à tous les administrateurs)
-- ================================================================

-- Cette requête crée les permissions complètes pour tous les utilisateurs
-- ayant le rôle "Administrateur"
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

-- ================================================================
-- FIN DU SCRIPT
-- ================================================================

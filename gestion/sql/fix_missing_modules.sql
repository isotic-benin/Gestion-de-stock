-- Patch pour corriger les erreurs de structure et ajouter les modules manquants
-- À exécuter si la base de données existe déjà

-- 1. Ajouter les colonnes manquantes à la table modules
ALTER TABLE `modules` 
ADD COLUMN IF NOT EXISTS `icone` varchar(50) DEFAULT 'fas fa-cogs' COMMENT 'Classe FontAwesome' AFTER `description`,
ADD COLUMN IF NOT EXISTS `ordre` int(11) DEFAULT 0 COMMENT 'Ordre d''affichage' AFTER `icone`;

-- 2. Insérer les modules manquants avec icônes et ordre
INSERT INTO `modules` (`nom`, `code`, `description`, `icone`, `ordre`, `actif`) VALUES
('Tableau de Bord', 'dashboard', 'Accès au tableau de bord et aux statistiques globales', 'fas fa-tachometer-alt', 1, 1),
('Gestion des Clients', 'clients', 'Base de données clients', 'fas fa-handshake', 2, 1),
('Gestion des Produits', 'produits', 'Gestion du catalogue, prix et stock', 'fas fa-boxes', 3, 1),
('Gestion des Ventes', 'ventes', 'Point de vente et historique', 'fas fa-cash-register', 4, 1),
('Gestion des Dépenses', 'depenses', 'Suivi des dépenses', 'fas fa-wallet', 5, 1),
('Gestion des Fournisseurs', 'fournisseurs', 'Base fournisseurs', 'fas fa-truck', 6, 1),
('Paramètres', 'parametres', 'Configuration générale de l''entreprise et permissions', 'fas fa-cog', 7, 1),
('Rapports', 'rapports', 'Statistiques et rapports d''activité', 'fas fa-chart-pie', 8, 1),
('Dettes Entreprise', 'dettes_entreprise', 'Gestion des dettes envers les fournisseurs et magasins', 'fas fa-file-invoice-dollar', 5, 1),
('Transferts de Stock', 'transferts_stock', 'Gestion des mouvements entre magasins', 'fas fa-truck-ramp-box', 3, 1),
('Retours Ventes', 'retours_ventes', 'Gestion des retours clients', 'fas fa-undo', 4, 1),
('Dettes Clients', 'dettes_clients', 'Suivi des créances clients', 'fas fa-money-bill-wave', 2, 1),
('Comptes Épargne', 'comptes_epargne', 'Gestion des comptes épargne clients', 'fas fa-piggy-bank', 2, 1)
ON DUPLICATE KEY UPDATE 
    icone = VALUES(icone),
    ordre = VALUES(ordre),
    description = VALUES(description);

-- 3. Accorder les permissions à l'Administrateur pour ces nouveaux modules
INSERT INTO `permissions` (`personnel_id`, `module_id`, `can_create`, `can_read`, `can_update`, `can_delete`)
SELECT p.id, m.id, 1, 1, 1, 1
FROM personnel p
CROSS JOIN modules m
WHERE p.role = 'Administrateur' 
AND m.code IN ('parametres', 'rapports', 'dettes_entreprise')
ON DUPLICATE KEY UPDATE 
    can_create = 1, 
    can_read = 1, 
    can_update = 1, 
    can_delete = 1;

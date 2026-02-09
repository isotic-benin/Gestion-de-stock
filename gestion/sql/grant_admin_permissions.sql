-- ================================================================
-- SCRIPT SQL: Donner toutes les permissions à l'Administrateur
-- Fichier: grant_admin_permissions.sql
-- ================================================================

-- OPTION 1: Donner toutes les permissions à TOUS les Administrateurs
-- Cette requête attribue toutes les permissions CRUD à tous les utilisateurs 
-- ayant le rôle "Administrateur" pour tous les modules actifs.

INSERT INTO permissions (personnel_id, module_id, can_create, can_read, can_update, can_delete)
SELECT p.id, m.id, 1, 1, 1, 1
FROM personnel p
CROSS JOIN modules m
WHERE p.role = 'Administrateur' AND m.actif = 1
ON DUPLICATE KEY UPDATE 
    can_create = 1, 
    can_read = 1, 
    can_update = 1, 
    can_delete = 1;

-- ================================================================
-- OPTION 2: Donner toutes les permissions à un Administrateur spécifique
-- Remplacez [ID_ADMINISTRATEUR] par l'ID de l'utilisateur concerné
-- ================================================================

-- INSERT INTO permissions (personnel_id, module_id, can_create, can_read, can_update, can_delete)
-- SELECT [ID_ADMINISTRATEUR], m.id, 1, 1, 1, 1
-- FROM modules m
-- WHERE m.actif = 1
-- ON DUPLICATE KEY UPDATE 
--     can_create = 1, 
--     can_read = 1, 
--     can_update = 1, 
--     can_delete = 1;

-- ================================================================
-- VÉRIFICATION: Afficher les permissions de l'administrateur
-- ================================================================

SELECT 
    pers.nom, 
    pers.prenom, 
    pers.role,
    m.nom AS module_nom,
    p.can_create,
    p.can_read,
    p.can_update,
    p.can_delete
FROM permissions p
INNER JOIN personnel pers ON p.personnel_id = pers.id
INNER JOIN modules m ON p.module_id = m.id
WHERE pers.role = 'Administrateur'
ORDER BY pers.id, m.ordre;

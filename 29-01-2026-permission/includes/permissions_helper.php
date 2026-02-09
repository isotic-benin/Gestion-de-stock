<?php
/**
 * Fichier: includes/permissions_helper.php
 * 
 * Fonctions helper pour la gestion des permissions dynamiques.
 * Ce fichier fournit des fonctions pour vérifier si un utilisateur
 * a une permission spécifique sur un module donné.
 */

if (!function_exists('hasPermission')) {

    /**
     * Vérifie si l'utilisateur connecté a une permission spécifique sur un module.
     *
     * @param mysqli $conn Connexion à la base de données
     * @param string $module_code Code du module (ex: 'ventes', 'produits_stock')
     * @param string $permission_type Type de permission: 'create', 'read', 'update', 'delete'
     * @param int|null $personnel_id ID du personnel (optionnel, utilise $_SESSION['user_id'] par défaut)
     * @return bool True si l'utilisateur a la permission, False sinon
     */
    function hasPermission($conn, $module_code, $permission_type = 'read', $personnel_id = null)
    {
        // Si pas de connexion ou pas d'utilisateur connecté
        if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
            return false;
        }

        // Utiliser l'ID de session si non fourni
        if ($personnel_id === null) {
            $personnel_id = $_SESSION['id'] ?? null;
        }

        if (!$personnel_id) {
            return false;
        }

        // Les Administrateurs avec l'ancien système ont accès à tout (rétrocompatibilité)
        // On vérifie d'abord si l'utilisateur a des permissions définies dans la nouvelle table
        $check_sql = "SELECT COUNT(*) as count FROM permissions WHERE personnel_id = ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "i", $personnel_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        $permissions_count = mysqli_fetch_assoc($check_result)['count'];
        mysqli_stmt_close($check_stmt);

        // Si aucune permission définie et que l'utilisateur est Administrateur, accès total
        if ($permissions_count == 0 && isset($_SESSION['role']) && $_SESSION['role'] === 'Administrateur') {
            return true;
        }

        // Mapper le type de permission au nom de colonne
        $permission_column = '';
        switch (strtolower($permission_type)) {
            case 'create':
            case 'c':
                $permission_column = 'can_create';
                break;
            case 'read':
            case 'r':
                $permission_column = 'can_read';
                break;
            case 'update':
            case 'u':
                $permission_column = 'can_update';
                break;
            case 'delete':
            case 'd':
                $permission_column = 'can_delete';
                break;
            default:
                return false;
        }

        // Requête pour vérifier la permission
        $sql = "SELECT p.{$permission_column} 
                FROM permissions p
                INNER JOIN modules m ON p.module_id = m.id
                WHERE p.personnel_id = ? AND m.code = ? AND m.actif = 1";

        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return false;
        }

        mysqli_stmt_bind_param($stmt, "is", $personnel_id, $module_code);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($row = mysqli_fetch_assoc($result)) {
            mysqli_stmt_close($stmt);
            return (bool) $row[$permission_column];
        }

        mysqli_stmt_close($stmt);
        return false;
    }

    /**
     * Vérifie si l'utilisateur a au moins la permission de lecture sur un module.
     * Fonction raccourcie pour les vérifications d'accès aux pages.
     *
     * @param mysqli $conn Connexion à la base de données
     * @param string $module_code Code du module
     * @return bool
     */
    function canAccessModule($conn, $module_code)
    {
        return hasPermission($conn, $module_code, 'read');
    }

    /**
     * Récupère toutes les permissions d'un utilisateur.
     *
     * @param mysqli $conn Connexion à la base de données
     * @param int $personnel_id ID du personnel
     * @return array Tableau associatif [module_code => ['create' => bool, 'read' => bool, ...]]
     */
    function getAllPermissions($conn, $personnel_id)
    {
        $permissions = [];

        $sql = "SELECT m.code, p.can_create, p.can_read, p.can_update, p.can_delete
                FROM permissions p
                INNER JOIN modules m ON p.module_id = m.id
                WHERE p.personnel_id = ? AND m.actif = 1";

        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return $permissions;
        }

        mysqli_stmt_bind_param($stmt, "i", $personnel_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        while ($row = mysqli_fetch_assoc($result)) {
            $permissions[$row['code']] = [
                'create' => (bool) $row['can_create'],
                'read' => (bool) $row['can_read'],
                'update' => (bool) $row['can_update'],
                'delete' => (bool) $row['can_delete']
            ];
        }

        mysqli_stmt_close($stmt);
        return $permissions;
    }

    /**
     * Récupère la liste de tous les modules actifs.
     *
     * @param mysqli $conn Connexion à la base de données
     * @return array Liste des modules
     */
    function getAllModules($conn)
    {
        $modules = [];

        $sql = "SELECT id, code, nom, description, icone, ordre 
                FROM modules 
                WHERE actif = 1 
                ORDER BY ordre ASC";

        $result = mysqli_query($conn, $sql);

        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $modules[] = $row;
            }
            mysqli_free_result($result);
        }

        return $modules;
    }

    /**
     * Met à jour les permissions d'un utilisateur pour un module.
     *
     * @param mysqli $conn Connexion à la base de données
     * @param int $personnel_id ID du personnel
     * @param int $module_id ID du module
     * @param bool $can_create Permission de création
     * @param bool $can_read Permission de lecture
     * @param bool $can_update Permission de modification
     * @param bool $can_delete Permission de suppression
     * @return bool Succès ou échec
     */
    function updatePermission($conn, $personnel_id, $module_id, $can_create, $can_read, $can_update, $can_delete)
    {
        $sql = "INSERT INTO permissions (personnel_id, module_id, can_create, can_read, can_update, can_delete)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    can_create = VALUES(can_create),
                    can_read = VALUES(can_read),
                    can_update = VALUES(can_update),
                    can_delete = VALUES(can_delete)";

        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return false;
        }

        $c = $can_create ? 1 : 0;
        $r = $can_read ? 1 : 0;
        $u = $can_update ? 1 : 0;
        $d = $can_delete ? 1 : 0;

        mysqli_stmt_bind_param($stmt, "iiiiii", $personnel_id, $module_id, $c, $r, $u, $d);
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return $success;
    }

    /**
     * Récupère les permissions d'un utilisateur pour l'affichage dans l'interface.
     *
     * @param mysqli $conn Connexion à la base de données
     * @param int $personnel_id ID du personnel
     * @return array Tableau avec modules et leurs permissions
     */
    function getPermissionsForDisplay($conn, $personnel_id)
    {
        $data = [];

        // Récupérer tous les modules
        $modules = getAllModules($conn);

        // Récupérer les permissions existantes de l'utilisateur
        $sql = "SELECT module_id, can_create, can_read, can_update, can_delete 
                FROM permissions 
                WHERE personnel_id = ?";

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $personnel_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $user_permissions = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $user_permissions[$row['module_id']] = [
                'can_create' => $row['can_create'],
                'can_read' => $row['can_read'],
                'can_update' => $row['can_update'],
                'can_delete' => $row['can_delete']
            ];
        }
        mysqli_stmt_close($stmt);

        // Combiner les modules avec les permissions
        foreach ($modules as $module) {
            $module_id = $module['id'];
            $data[] = [
                'module_id' => $module_id,
                'module_code' => $module['code'],
                'module_nom' => $module['nom'],
                'module_icone' => $module['icone'],
                'can_create' => isset($user_permissions[$module_id]) ? $user_permissions[$module_id]['can_create'] : 0,
                'can_read' => isset($user_permissions[$module_id]) ? $user_permissions[$module_id]['can_read'] : 0,
                'can_update' => isset($user_permissions[$module_id]) ? $user_permissions[$module_id]['can_update'] : 0,
                'can_delete' => isset($user_permissions[$module_id]) ? $user_permissions[$module_id]['can_delete'] : 0
            ];
        }

        return $data;
    }

    /**
     * Vérifie l'accès à une page et redirige si non autorisé.
     * Cette fonction doit être appelée au début de chaque page protégée.
     *
     * @param mysqli $conn Connexion à la base de données
     * @param string $module_code Code du module
     * @param string $permission_type Type de permission requise (par défaut: 'read')
     * @param string $redirect_url URL de redirection si non autorisé (par défaut: dashboard)
     * @return void
     */
    function requirePermission($conn, $module_code, $permission_type = 'read', $redirect_url = null)
    {
        // Vérifier si l'utilisateur est connecté
        if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
            header("location: " . BASE_URL . "index.php");
            exit;
        }

        // Vérifier la permission
        if (!hasPermission($conn, $module_code, $permission_type)) {
            // URL de redirection par défaut
            if ($redirect_url === null) {
                $redirect_url = BASE_URL . "dashboard.php";
            }

            // Stocker un message d'erreur dans la session
            $_SESSION['permission_error'] = "Vous n'avez pas les permissions nécessaires pour accéder à cette fonctionnalité.";

            header("location: " . $redirect_url);
            exit;
        }
    }

    /**
     * Récupère la liste du personnel avec leurs informations de base.
     *
     * @param mysqli $conn Connexion à la base de données
     * @return array Liste du personnel
     */
    function getAllPersonnel($conn)
    {
        $personnel = [];

        $sql = "SELECT p.id, p.nom, p.prenom, p.nom_utilisateur, p.role, m.nom as nom_magasin
                FROM personnel p
                LEFT JOIN magasins m ON p.magasin_id = m.id
                ORDER BY p.nom, p.prenom";

        $result = mysqli_query($conn, $sql);

        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $personnel[] = $row;
            }
            mysqli_free_result($result);
        }

        return $personnel;
    }

    /**
     * Vérifie si les tables de permissions existent dans la base de données.
     *
     * @param mysqli $conn Connexion à la base de données
     * @return bool True si les tables existent
     */
    function permissionTablesExist($conn)
    {
        $tables_exist = true;

        // Vérifier la table modules
        $result = mysqli_query($conn, "SHOW TABLES LIKE 'modules'");
        if (mysqli_num_rows($result) == 0) {
            $tables_exist = false;
        }
        mysqli_free_result($result);

        // Vérifier la table permissions
        $result = mysqli_query($conn, "SHOW TABLES LIKE 'permissions'");
        if (mysqli_num_rows($result) == 0) {
            $tables_exist = false;
        }
        mysqli_free_result($result);

        return $tables_exist;
    }

} // End if function_exists
?>
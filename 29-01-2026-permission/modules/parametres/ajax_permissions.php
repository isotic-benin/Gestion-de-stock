<?php
/**
 * Fichier: modules/parametres/ajax_permissions.php
 * 
 * Gestionnaire AJAX pour les opérations sur les permissions.
 * Gère la récupération et la mise à jour des permissions utilisateur.
 */

session_start();
header('Content-Type: application/json; charset=UTF-8');

include '../../db_connect.php';
include '../../includes/permissions_helper.php';

// Vérifier si l'utilisateur est connecté et est administrateur
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['role'] ?? '') !== 'Administrateur') {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit;
}

// Déterminer l'action à effectuer
$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'get_permissions':
        getPermissions($conn);
        break;

    case 'save_permissions':
        savePermissions($conn);
        break;

    case 'get_personnel_list':
        getPersonnelList($conn);
        break;

    case 'init_tables':
        initPermissionTables($conn);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
        break;
}

/**
 * Récupère les permissions d'un utilisateur spécifique
 */
function getPermissions($conn)
{
    $personnel_id = isset($_GET['personnel_id']) ? (int) $_GET['personnel_id'] : 0;

    if ($personnel_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID personnel invalide']);
        return;
    }

    // Vérifier si les tables existent
    if (!permissionTablesExist($conn)) {
        echo json_encode([
            'success' => false,
            'message' => 'Les tables de permissions ne sont pas encore créées. Veuillez les initialiser.',
            'tables_missing' => true
        ]);
        return;
    }

    // Récupérer les permissions pour l'affichage
    $permissions = getPermissionsForDisplay($conn, $personnel_id);

    // Récupérer les infos du personnel
    $sql = "SELECT nom, prenom, role FROM personnel WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $personnel_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $personnel_info = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    echo json_encode([
        'success' => true,
        'personnel' => $personnel_info,
        'permissions' => $permissions
    ]);
}

/**
 * Sauvegarde les permissions d'un utilisateur
 */
function savePermissions($conn)
{
    // Vérifier la méthode HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
        return;
    }

    // Récupérer les données JSON
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Données invalides']);
        return;
    }

    $personnel_id = isset($input['personnel_id']) ? (int) $input['personnel_id'] : 0;
    $permissions = isset($input['permissions']) ? $input['permissions'] : [];

    if ($personnel_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID personnel invalide']);
        return;
    }

    // Vérifier si les tables existent
    if (!permissionTablesExist($conn)) {
        echo json_encode([
            'success' => false,
            'message' => 'Les tables de permissions ne sont pas encore créées.',
            'tables_missing' => true
        ]);
        return;
    }

    // Démarrer une transaction
    mysqli_begin_transaction($conn);

    try {
        // Supprimer les anciennes permissions de cet utilisateur
        $delete_sql = "DELETE FROM permissions WHERE personnel_id = ?";
        $delete_stmt = mysqli_prepare($conn, $delete_sql);
        mysqli_stmt_bind_param($delete_stmt, "i", $personnel_id);
        mysqli_stmt_execute($delete_stmt);
        mysqli_stmt_close($delete_stmt);

        // Insérer les nouvelles permissions
        $insert_sql = "INSERT INTO permissions (personnel_id, module_id, can_create, can_read, can_update, can_delete) VALUES (?, ?, ?, ?, ?, ?)";
        $insert_stmt = mysqli_prepare($conn, $insert_sql);

        foreach ($permissions as $perm) {
            $module_id = (int) $perm['module_id'];
            $can_create = isset($perm['can_create']) && $perm['can_create'] ? 1 : 0;
            $can_read = isset($perm['can_read']) && $perm['can_read'] ? 1 : 0;
            $can_update = isset($perm['can_update']) && $perm['can_update'] ? 1 : 0;
            $can_delete = isset($perm['can_delete']) && $perm['can_delete'] ? 1 : 0;

            // N'insérer que si au moins une permission est activée
            if ($can_create || $can_read || $can_update || $can_delete) {
                mysqli_stmt_bind_param($insert_stmt, "iiiiii", $personnel_id, $module_id, $can_create, $can_read, $can_update, $can_delete);
                mysqli_stmt_execute($insert_stmt);
            }
        }

        mysqli_stmt_close($insert_stmt);

        // Valider la transaction
        mysqli_commit($conn);

        echo json_encode([
            'success' => true,
            'message' => 'Permissions enregistrées avec succès'
        ]);

    } catch (Exception $e) {
        // Annuler la transaction en cas d'erreur
        mysqli_rollback($conn);

        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors de l\'enregistrement: ' . $e->getMessage()
        ]);
    }
}

/**
 * Récupère la liste du personnel
 */
function getPersonnelList($conn)
{
    $personnel = getAllPersonnel($conn);

    echo json_encode([
        'success' => true,
        'personnel' => $personnel
    ]);
}

/**
 * Initialise les tables de permissions si elles n'existent pas
 */
function initPermissionTables($conn)
{
    // Vérifier si les tables existent déjà
    if (permissionTablesExist($conn)) {
        echo json_encode([
            'success' => true,
            'message' => 'Les tables de permissions existent déjà'
        ]);
        return;
    }

    // Lire le fichier SQL
    $sql_file = __DIR__ . '/../../sql/permissions_schema.sql';

    if (!file_exists($sql_file)) {
        echo json_encode([
            'success' => false,
            'message' => 'Fichier SQL de schéma introuvable'
        ]);
        return;
    }

    $sql_content = file_get_contents($sql_file);

    // Exécuter le SQL
    mysqli_multi_query($conn, $sql_content);

    // Attendre que toutes les requêtes soient terminées
    while (mysqli_next_result($conn)) {
        if (!mysqli_more_results($conn))
            break;
    }

    // Vérifier si les tables ont été créées
    if (permissionTablesExist($conn)) {
        echo json_encode([
            'success' => true,
            'message' => 'Tables de permissions créées avec succès'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors de la création des tables: ' . mysqli_error($conn)
        ]);
    }
}

mysqli_close($conn);
?>
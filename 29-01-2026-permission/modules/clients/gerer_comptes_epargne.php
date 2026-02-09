<?php
// modules/clients/gerer_comptes_epargne.php
session_start();

// Inclure les fichiers nécessaires
include '../../db_connect.php'; // Ceci inclura config.php
include '../../includes/permissions_helper.php'; // Helper de permissions
include '../../includes/header.php'; // Inclut le header avec le début du HTML


// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("location: " . BASE_URL . "public/login.php");
    exit;
}

// Vérifier les permissions d'accès au module (lecture)
if (!hasPermission($conn, 'comptes_epargne', 'read')) {
    header("location: " . BASE_URL . "dashboard.php?error=access_denied");
    exit;
}

// Définir les permissions pour l'affichage conditionnel des boutons
$can_create = hasPermission($conn, 'comptes_epargne', 'create');
$can_update = hasPermission($conn, 'comptes_epargne', 'update');
$can_delete = hasPermission($conn, 'comptes_epargne', 'delete');

$page_title = "Gestion des Comptes Épargne Client";

$message = ''; // Pour afficher les messages de succès ou d'erreur

// --- Logique de traitement des formulaires (CRUD Comptes Épargne) ---

// Gérer l'ajout ou la modification d'un compte épargne
if ($_SERVER["REQUEST_METHOD"] == "POST" && (isset($_POST['add_compte']) || isset($_POST['edit_compte']))) {
    // Vérifier les permissions selon l'action
    if (isset($_POST['add_compte']) && !$can_create) {
        $message = '<div class="alert alert-error">Vous n\'avez pas la permission d\'ajouter des comptes épargne.</div>';
        goto end_form_processing_comptes;
    }
    if (isset($_POST['edit_compte']) && !$can_update) {
        $message = '<div class="alert alert-error">Vous n\'avez pas la permission de modifier des comptes épargne.</div>';
        goto end_form_processing_comptes;
    }
    $client_id = sanitize_input($_POST['client_id']);
    $numero_compte = sanitize_input($_POST['numero_compte']);
    $solde_initial = isset($_POST['solde_initial']) ? (float) sanitize_input($_POST['solde_initial']) : 0.00;

    if (isset($_POST['add_compte'])) {
        // Ajout
        $sql = "INSERT INTO comptes_epargne (client_id, numero_compte, solde) VALUES (?, ?, ?)";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "isd", $client_id, $numero_compte, $solde_initial);
            if (mysqli_stmt_execute($stmt)) {
                $message = '<div class="alert alert-success">Compte épargne ajouté avec succès !</div>';
            } else {
                $message = '<div class="alert alert-error">Erreur lors de l\'ajout du compte épargne : ' . mysqli_error($conn) . '</div>';
            }
            mysqli_stmt_close($stmt);
        }
    } elseif (isset($_POST['edit_compte'])) {
        // Modification (le solde n'est pas modifiable directement ici, seulement via transactions)
        $compte_id = sanitize_input($_POST['compte_id']);
        $sql = "UPDATE comptes_epargne SET client_id = ?, numero_compte = ? WHERE id = ?";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "isi", $client_id, $numero_compte, $compte_id);
            if (mysqli_stmt_execute($stmt)) {
                $message = '<div class="alert alert-success">Compte épargne modifié avec succès !</div>';
            } else {
                $message = '<div class="alert alert-error">Erreur lors de la modification du compte épargne : ' . mysqli_error($conn) . '</div>';
            }
            mysqli_stmt_close($stmt);
        }
    }
}
end_form_processing_comptes:
;

// Gérer la suppression d'un compte épargne
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_compte'])) {
    // Vérifier la permission de suppression
    if (!$can_delete) {
        $message = '<div class="alert alert-error">Vous n\'avez pas la permission de supprimer des comptes épargne.</div>';
    } else {
        $compte_id = sanitize_input($_POST['compte_id']);
        $sql = "DELETE FROM comptes_epargne WHERE id = ?";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "i", $compte_id);
            if (mysqli_stmt_execute($stmt)) {
                $message = '<div class="alert alert-success">Compte épargne supprimé avec succès !</div>';
            } else {
                $message = '<div class="alert alert-error">Erreur lors de la suppression du compte épargne : ' . mysqli_error($conn) . '</div>';
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// --- Logique de traitement des transactions (Dépôt/Retrait) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && (isset($_POST['effectuer_depot']) || isset($_POST['effectuer_retrait']))) {
    $compte_id = sanitize_input($_POST['transaction_compte_id']);
    $montant = (float) sanitize_input($_POST['montant_transaction']);
    $description = sanitize_input($_POST['description_transaction']);
    $type_transaction = isset($_POST['effectuer_depot']) ? 'depot' : 'retrait';

    // Récupérer le solde actuel
    $sql_solde = "SELECT solde FROM comptes_epargne WHERE id = ?";
    if ($stmt_solde = mysqli_prepare($conn, $sql_solde)) {
        mysqli_stmt_bind_param($stmt_solde, "i", $compte_id);
        mysqli_stmt_execute($stmt_solde);
        mysqli_stmt_bind_result($stmt_solde, $current_solde);
        mysqli_stmt_fetch($stmt_solde);
        mysqli_stmt_close($stmt_solde);
    } else {
        $message = '<div class="alert alert-error">Erreur de préparation de la requête de solde : ' . mysqli_error($conn) . '</div>';
        goto end_transaction; // Utilisation de goto pour sortir rapidement en cas d'erreur
    }

    $new_solde = $current_solde;
    if ($type_transaction == 'depot') {
        $new_solde += $montant;
    } else { // retrait
        if ($montant > $current_solde) {
            $message = '<div class="alert alert-error">Solde insuffisant pour effectuer ce retrait.</div>';
            goto end_transaction;
        }
        $new_solde -= $montant;
    }

    // Début de la transaction SQL pour assurer l'atomicité
    mysqli_begin_transaction($conn);

    try {
        // 1. Mettre à jour le solde du compte
        $sql_update = "UPDATE comptes_epargne SET solde = ? WHERE id = ?";
        if ($stmt_update = mysqli_prepare($conn, $sql_update)) {
            mysqli_stmt_bind_param($stmt_update, "di", $new_solde, $compte_id);
            if (!mysqli_stmt_execute($stmt_update)) {
                throw new Exception("Erreur lors de la mise à jour du solde.");
            }
            mysqli_stmt_close($stmt_update);
        } else {
            throw new Exception("Erreur de préparation de la requête de mise à jour du solde.");
        }

        // 2. Enregistrer la transaction
        $sql_insert_trans = "INSERT INTO transactions_epargne (compte_epargne_id, type_transaction, montant, description) VALUES (?, ?, ?, ?)";
        if ($stmt_insert_trans = mysqli_prepare($conn, $sql_insert_trans)) {
            mysqli_stmt_bind_param($stmt_insert_trans, "isds", $compte_id, $type_transaction, $montant, $description);
            if (!mysqli_stmt_execute($stmt_insert_trans)) {
                throw new Exception("Erreur lors de l'enregistrement de la transaction.");
            }
            mysqli_stmt_close($stmt_insert_trans);
        } else {
            throw new Exception("Erreur de préparation de la requête d'insertion de transaction.");
        }

        mysqli_commit($conn);
        $message = '<div class="alert alert-success">Transaction de ' . $type_transaction . ' effectuée avec succès !</div>';

    } catch (Exception $e) {
        mysqli_rollback($conn);
        $message = '<div class="alert alert-error">Erreur lors de la transaction : ' . $e->getMessage() . '</div>';
    }

    end_transaction:
    ; // Étiquette pour le goto
}


// --- Logique de listage (Pagination, Recherche, Tri) ---

$limit = 10; // Nombre d'éléments par page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$search_query = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$sort_by = isset($_GET['sort_by']) ? sanitize_input($_GET['sort_by']) : 'ce.date_creation';
$sort_order = isset($_GET['sort_order']) && in_array(strtoupper($_GET['sort_order']), ['ASC', 'DESC']) ? $_GET['sort_order'] : 'DESC';

// Construction de la clause WHERE pour la recherche
$where_clause = '';
if (!empty($search_query)) {
    $where_clause = " WHERE c.nom LIKE '%" . $search_query . "%' OR c.prenom LIKE '%" . $search_query . "%' OR ce.numero_compte LIKE '%" . $search_query . "%'";
}

// Requête pour le nombre total de comptes épargne (pour la pagination)
$count_sql = "SELECT COUNT(ce.id) AS total FROM comptes_epargne ce JOIN clients c ON ce.client_id = c.id" . $where_clause;
$count_result = mysqli_query($conn, $count_sql);
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $limit);

// Requête pour récupérer les comptes épargne avec pagination, recherche et tri
$sql = "SELECT ce.id, ce.client_id, ce.numero_compte, ce.solde, ce.date_creation, c.nom AS client_nom, c.prenom AS client_prenom
        FROM comptes_epargne ce
        JOIN clients c ON ce.client_id = c.id"
    . $where_clause . " ORDER BY " . $sort_by . " " . $sort_order . " LIMIT ? OFFSET ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $limit, $offset);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$comptes_epargne = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Récupérer la liste des clients pour le sélecteur
$clients_disponibles = [];
$sql_clients = "SELECT id, nom, prenom FROM clients ORDER BY nom ASC, prenom ASC";
$result_clients = mysqli_query($conn, $sql_clients);
while ($row = mysqli_fetch_assoc($result_clients)) {
    $clients_disponibles[] = $row;
}
mysqli_free_result($result_clients);

?>

<div class="flex h-screen bg-gray-100">
    <!-- Sidebar -->
    <?php include '../../includes/sidebar_dashboard.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden md:ml-64">
        <!-- Top Bar -->
        <?php include '../../includes/topbar.php'; ?>


        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6 pb-20">
            <div class="container mx-auto">
                <h1 class="text-3xl font-bold text-gray-800 mb-6"><?php echo $page_title; ?></h1>

                <?php if (!empty($message)): ?>
                    <div id="message-container" class="mb-4">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <div class="bg-white p-6 rounded-lg shadow-md mb-6">
                    <div class="flex flex-col md:flex-row justify-between items-center mb-4">
                        <?php if ($can_create): ?>
                            <button id="addCompteBtn"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md shadow-md transition duration-300 mb-4 md:mb-0">
                                <i class="fas fa-plus-circle mr-2"></i> Ajouter un Compte Épargne
                            </button>
                        <?php endif; ?>
                        <form method="GET" action="" class="flex flex-col md:flex-row gap-4 w-full md:w-auto">
                            <input type="text" name="search" placeholder="Rechercher..."
                                value="<?php echo htmlspecialchars($search_query); ?>"
                                class="flex-grow shadow appearance-none border rounded-md py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            <select name="sort_by"
                                class="shadow appearance-none border rounded-md py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                <option value="ce.date_creation" <?php echo $sort_by == 'ce.date_creation' ? 'selected' : ''; ?>>Trier par Date Création</option>
                                <option value="c.nom" <?php echo $sort_by == 'c.nom' ? 'selected' : ''; ?>>Trier par Nom
                                    Client</option>
                                <option value="ce.solde" <?php echo $sort_by == 'ce.solde' ? 'selected' : ''; ?>>Trier par
                                    Solde</option>
                            </select>
                            <select name="sort_order"
                                class="shadow appearance-none border rounded-md py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                <option value="ASC" <?php echo $sort_order == 'ASC' ? 'selected' : ''; ?>>Ascendant
                                </option>
                                <option value="DESC" <?php echo $sort_order == 'DESC' ? 'selected' : ''; ?>>Descendant
                                </option>
                            </select>
                            <button type="submit"
                                class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-md shadow-md transition duration-300">
                                <i class="fas fa-filter mr-2"></i> Filtrer
                            </button>
                        </form>
                    </div>

                    <div class="table-container overflow-x-auto">
                        <table class="data-table min-w-full">
                            <thead>
                                <tr>
                                    <th class="px-4 py-2">ID Compte</th>
                                    <th class="px-4 py-2">Client</th>
                                    <th class="px-4 py-2">Numéro Compte</th>
                                    <th class="px-4 py-2">Solde</th>
                                    <th class="px-4 py-2">Date Création</th>
                                    <th class="px-4 py-2">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($comptes_epargne)): ?>
                                    <?php foreach ($comptes_epargne as $compte): ?>
                                        <tr>
                                            <td class="border px-4 py-2"><?php echo htmlspecialchars($compte['id']); ?></td>
                                            <td class="border px-4 py-2">
                                                <?php echo htmlspecialchars($compte['client_nom'] . ' ' . $compte['client_prenom']); ?>
                                            </td>
                                            <td class="border px-4 py-2">
                                                <?php echo htmlspecialchars($compte['numero_compte']); ?>
                                            </td>
                                            <td class="border px-4 py-2 font-semibold text-green-700">
                                                <?php echo number_format($compte['solde'], 2, ',', ' ') . ' ' . htmlspecialchars(get_currency()); ?>
                                            </td>
                                            <td class="border px-4 py-2">
                                                <?php echo date('d/m/Y H:i', strtotime($compte['date_creation'])); ?>
                                            </td>
                                            <td class="border px-4 py-2 action-buttons flex flex-wrap gap-2">
                                                <?php if ($can_update): ?>
                                                    <button
                                                        class="btn btn-edit edit-compte-btn bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-1 px-3 rounded-md text-sm"
                                                        data-id="<?php echo htmlspecialchars($compte['id']); ?>"
                                                        data-client_id="<?php echo htmlspecialchars($compte['client_id']); ?>"
                                                        data-numero_compte="<?php echo htmlspecialchars($compte['numero_compte']); ?>">
                                                        <i class="fas fa-edit"></i> Modifier
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($can_update): ?>
                                                    <button
                                                        class="btn btn-primary deposit-btn bg-green-500 hover:bg-green-600 text-white font-bold py-1 px-3 rounded-md text-sm"
                                                        data-id="<?php echo htmlspecialchars($compte['id']); ?>"
                                                        data-client_nom="<?php echo htmlspecialchars($compte['client_nom'] . ' ' . $compte['client_prenom']); ?>"
                                                        data-numero_compte="<?php echo htmlspecialchars($compte['numero_compte']); ?>">
                                                        <i class="fas fa-money-bill-wave"></i> Dépôt
                                                    </button>
                                                    <button
                                                        class="btn btn-warning withdraw-btn bg-orange-500 hover:bg-orange-600 text-white font-bold py-1 px-3 rounded-md text-sm"
                                                        data-id="<?php echo htmlspecialchars($compte['id']); ?>"
                                                        data-client_nom="<?php echo htmlspecialchars($compte['client_nom'] . ' ' . $compte['client_prenom']); ?>"
                                                        data-numero_compte="<?php echo htmlspecialchars($compte['numero_compte']); ?>">
                                                        <i class="fas fa-money-bill-transfer"></i> Retrait
                                                    </button>
                                                <?php endif; ?>
                                                <button
                                                    class="btn btn-info view-history-btn bg-blue-500 hover:bg-blue-600 text-white font-bold py-1 px-3 rounded-md text-sm"
                                                    data-id="<?php echo htmlspecialchars($compte['id']); ?>"
                                                    data-client_nom="<?php echo htmlspecialchars($compte['client_nom'] . ' ' . $compte['client_prenom']); ?>"
                                                    data-numero_compte="<?php echo htmlspecialchars($compte['numero_compte']); ?>">
                                                    <i class="fas fa-history"></i> Historique
                                                </button>
                                                <?php if ($can_delete): ?>
                                                    <button
                                                        class="btn btn-delete delete-compte-btn bg-red-500 hover:bg-red-600 text-white font-bold py-1 px-3 rounded-md text-sm"
                                                        data-id="<?php echo htmlspecialchars($compte['id']); ?>">
                                                        <i class="fas fa-trash-alt"></i> Supprimer
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 border">Aucun compte épargne trouvé.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="pagination flex flex-wrap justify-center items-center gap-2 mt-6">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search_query); ?>&sort_by=<?php echo urlencode($sort_by); ?>&sort_order=<?php echo urlencode($sort_order); ?>"
                                class="btn-pagination bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md">Précédent</a>
                        <?php else: ?>
                            <span
                                class="btn-pagination disabled bg-gray-300 text-gray-700 font-bold py-2 px-4 rounded-md cursor-not-allowed">Précédent</span>
                        <?php endif; ?>

                        <?php
                        // Logic to display a limited number of page buttons around the current page
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);

                        if ($start_page > 1) {
                            echo '<a href="?page=1&search=' . urlencode($search_query) . '&sort_by=' . urlencode($sort_by) . '&sort_order=' . urlencode($sort_order) . '" class="btn-pagination bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md">1</a>';
                            if ($start_page > 2) {
                                echo '<span class="px-2 py-2">...</span>';
                            }
                        }

                        for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search_query); ?>&sort_by=<?php echo urlencode($sort_by); ?>&sort_order=<?php echo urlencode($sort_order); ?>"
                                class="btn-pagination <?php echo ($i == $page) ? 'current-page bg-blue-700 text-white' : 'bg-blue-500 hover:bg-blue-700 text-white'; ?> font-bold py-2 px-4 rounded-md"><?php echo $i; ?></a>
                        <?php endfor;

                        if ($end_page < $total_pages) {
                            if ($end_page < $total_pages - 1) {
                                echo '<span class="px-2 py-2">...</span>';
                            }
                            echo '<a href="?page=' . $total_pages . '&search=' . urlencode($search_query) . '&sort_by=' . urlencode($sort_by) . '&sort_order=' . urlencode($sort_order) . '" class="btn-pagination bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md">' . $total_pages . '</a>';
                        }
                        ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search_query); ?>&sort_by=<?php echo urlencode($sort_by); ?>&sort_order=<?php echo urlencode($sort_order); ?>"
                                class="btn-pagination bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md">Suivant</a>
                        <?php else: ?>
                            <span
                                class="btn-pagination disabled bg-gray-300 text-gray-700 font-bold py-2 px-4 rounded-md cursor-not-allowed">Suivant</span>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        </main>
        <?php include '../../includes/footer.php'; ?>
    </div>
</div>

<!-- Modal d'ajout/modification de compte épargne -->
<div id="compteEpargneModal"
    class="modal hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full flex items-center justify-center">
    <div class="modal-content bg-white p-6 rounded-lg shadow-xl max-w-lg w-11/12 mx-auto">
        <span
            class="modal-close-button absolute top-3 right-3 text-gray-600 hover:text-gray-900 text-2xl cursor-pointer">&times;</span>
        <h2 id="modalTitleCompteEpargne" class="text-2xl font-bold text-gray-800 mb-4">Ajouter un Compte Épargne</h2>
        <form id="compteEpargneForm" method="POST" action="">
            <input type="hidden" id="compteId" name="compte_id">
            <div class="form-group mb-4">
                <label for="client_id" class="block text-gray-700 text-sm font-bold mb-2">Client:</label>
                <select id="client_id" name="client_id" required
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">-- Sélectionner un client --</option>
                    <?php foreach ($clients_disponibles as $client_option): ?>
                        <option value="<?php echo htmlspecialchars($client_option['id']); ?>">
                            <?php echo htmlspecialchars($client_option['nom'] . ' ' . $client_option['prenom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group mb-4">
                <label for="numero_compte" class="block text-gray-700 text-sm font-bold mb-2">Numéro de Compte:</label>
                <input type="text" id="numero_compte" name="numero_compte" required
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div class="form-group mb-6" id="soldeInitialGroup">
                <label for="solde_initial" class="block text-gray-700 text-sm font-bold mb-2">Solde Initial:</label>
                <input type="number" step="0.01" id="solde_initial" name="solde_initial" value="0.00" required
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <button type="submit" id="submitCompteBtn" name="add_compte"
                class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full">
                <i class="fas fa-save mr-2"></i> Enregistrer
            </button>
        </form>
    </div>
</div>

<!-- Modal de confirmation de suppression -->
<div id="deleteConfirmCompteModal"
    class="modal hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full flex items-center justify-center">
    <div class="modal-content bg-white p-6 rounded-lg shadow-xl max-w-sm w-11/12 mx-auto text-center">
        <span
            class="modal-close-button absolute top-3 right-3 text-gray-600 hover:text-gray-900 text-2xl cursor-pointer">&times;</span>
        <h2 class="text-2xl font-bold text-gray-800 mb-4">Confirmer la Suppression</h2>
        <p class="text-gray-700 mb-6">Êtes-vous sûr de vouloir supprimer ce compte épargne ? Cette action est
            irréversible et supprimera toutes les transactions associées.</p>
        <form id="deleteCompteForm" method="POST" action="">
            <input type="hidden" id="deleteCompteId" name="compte_id">
            <div class="flex justify-center space-x-4">
                <button type="button" id="cancelDeleteCompteBtn"
                    class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded-md transition duration-300">
                    Annuler
                </button>
                <button type="submit" name="delete_compte"
                    class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-md transition duration-300">
                    Supprimer <i class="fas fa-trash-alt ml-2"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal de transaction (Dépôt/Retrait) -->
<div id="transactionModal"
    class="modal hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full flex items-center justify-center">
    <div class="modal-content bg-white p-6 rounded-lg shadow-xl max-w-md w-11/12 mx-auto">
        <span
            class="modal-close-button absolute top-3 right-3 text-gray-600 hover:text-gray-900 text-2xl cursor-pointer">&times;</span>
        <h2 id="modalTitleTransaction" class="text-2xl font-bold text-gray-800 mb-4">Effectuer une Transaction</h2>
        <p class="text-gray-700 mb-4">Client: <span id="transactionClientNom" class="font-semibold"></span> | Compte:
            <span id="transactionNumeroCompte" class="font-semibold"></span>
        </p>
        <form id="transactionForm" method="POST" action="">
            <input type="hidden" id="transactionCompteId" name="transaction_compte_id">
            <div class="form-group mb-4">
                <label for="montant_transaction" class="block text-gray-700 text-sm font-bold mb-2">Montant:</label>
                <input type="number" step="0.01" id="montant_transaction" name="montant_transaction" required min="0.01"
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div class="form-group mb-6">
                <label for="description_transaction"
                    class="block text-gray-700 text-sm font-bold mb-2">Description:</label>
                <textarea id="description_transaction" name="description_transaction" rows="3"
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"></textarea>
            </div>
            <div class="flex justify-end space-x-4 mt-4">
                <button type="submit" id="submitDepositBtn" name="effectuer_depot"
                    class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-md transition duration-300">
                    <i class="fas fa-plus-circle mr-2"></i> Déposer
                </button>
                <button type="submit" id="submitWithdrawBtn" name="effectuer_retrait"
                    class="bg-orange-600 hover:bg-orange-700 text-white font-bold py-2 px-4 rounded-md transition duration-300">
                    <i class="fas fa-minus-circle mr-2"></i> Retirer
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal d'historique des transactions -->
<div id="historyModal"
    class="modal hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full flex items-center justify-center">
    <div class="modal-content bg-white p-6 rounded-lg shadow-xl max-w-2xl w-11/12 mx-auto">
        <span
            class="modal-close-button absolute top-3 right-3 text-gray-600 hover:text-gray-900 text-2xl cursor-pointer">&times;</span>
        <h2 class="text-2xl font-bold text-gray-800 mb-4">Historique des Transactions</h2>
        <p class="text-gray-700 mb-4">Client: <span id="historyClientNom" class="font-semibold"></span> | Compte: <span
                id="historyNumeroCompte" class="font-semibold"></span></p>
        <div class="table-container mb-4 overflow-x-auto">
            <table class="data-table min-w-full">
                <thead>
                    <tr>
                        <th class="px-4 py-2">Date</th>
                        <th class="px-4 py-2">Type</th>
                        <th class="px-4 py-2">Montant</th>
                        <th class="px-4 py-2">Description</th>
                        <th class="px-4 py-2">Reçu</th>
                    </tr>
                </thead>
                <tbody id="historyTableBody">
                    <!-- Les transactions seront chargées ici via JavaScript -->
                </tbody>
            </table>
        </div>
        <div class="flex justify-end mt-4">
            <button id="printHistoryBtn"
                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md transition duration-300">
                <i class="fas fa-print mr-2"></i> Imprimer l'historique
            </button>
        </div>
    </div>
</div>



<script>
    // Devise configurée depuis la page de configuration entreprise
    const currency = <?php echo json_encode(get_currency()); ?>;

    document.addEventListener('DOMContentLoaded', function () {
        // Refs pour le modal d'ajout/modification de compte
        const compteEpargneModal = document.getElementById('compteEpargneModal');
        const addCompteBtn = document.getElementById('addCompteBtn');
        const modalCloseButtons = document.querySelectorAll('.modal-close-button');
        const compteEpargneForm = document.getElementById('compteEpargneForm');
        const modalTitleCompteEpargne = document.getElementById('modalTitleCompteEpargne');
        const submitCompteBtn = document.getElementById('submitCompteBtn');
        const compteIdInput = document.getElementById('compteId');
        const clientIdSelect = document.getElementById('client_id');
        const numeroCompteInput = document.getElementById('numero_compte');
        const soldeInitialGroup = document.getElementById('soldeInitialGroup');
        const soldeInitialInput = document.getElementById('solde_initial');

        // Refs pour le modal de suppression
        const deleteConfirmCompteModal = document.getElementById('deleteConfirmCompteModal');
        const deleteCompteIdInput = document.getElementById('deleteCompteId');
        const cancelDeleteCompteBtn = document.getElementById('cancelDeleteCompteBtn');

        // Refs pour le modal de transaction
        const transactionModal = document.getElementById('transactionModal');
        const transactionClientNomSpan = document.getElementById('transactionClientNom');
        const transactionNumeroCompteSpan = document.getElementById('transactionNumeroCompte');
        const transactionCompteIdInput = document.getElementById('transactionCompteId');
        const montantTransactionInput = document.getElementById('montant_transaction');
        const descriptionTransactionInput = document.getElementById('description_transaction');
        const submitDepositBtn = document.getElementById('submitDepositBtn');
        const submitWithdrawBtn = document.getElementById('submitWithdrawBtn');

        // Refs pour le modal d'historique
        const historyModal = document.getElementById('historyModal');
        const historyClientNomSpan = document.getElementById('historyClientNom');
        const historyNumeroCompteSpan = document.getElementById('historyNumeroCompte');
        const historyTableBody = document.getElementById('historyTableBody');
        const printHistoryBtn = document.getElementById('printHistoryBtn');

        // Fonction pour ouvrir un modal
        function openModal(modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        // Fonction pour fermer un modal
        function closeModal(modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        // --- Gestion des Modals de Compte Épargne ---
        if (addCompteBtn) {
            addCompteBtn.addEventListener('click', function () {
                modalTitleCompteEpargne.textContent = 'Ajouter un Compte Épargne';
                submitCompteBtn.name = 'add_compte';
                submitCompteBtn.innerHTML = '<i class="fas fa-save mr-2"></i> Enregistrer';
                compteEpargneForm.reset();
                compteIdInput.value = '';
                soldeInitialGroup.classList.remove('hidden'); // Afficher le solde initial pour l'ajout
                soldeInitialInput.setAttribute('required', 'required');
                openModal(compteEpargneModal);
            });
        }

        document.querySelectorAll('.edit-compte-btn').forEach(button => {
            button.addEventListener('click', function () {
                modalTitleCompteEpargne.textContent = 'Modifier un Compte Épargne';
                submitCompteBtn.name = 'edit_compte';
                submitCompteBtn.innerHTML = '<i class="fas fa-edit mr-2"></i> Mettre à jour';

                compteIdInput.value = this.dataset.id;
                clientIdSelect.value = this.dataset.client_id;
                numeroCompteInput.value = this.dataset.numero_compte;
                soldeInitialGroup.classList.add('hidden'); // Cacher le solde initial pour la modification
                soldeInitialInput.removeAttribute('required'); // Rendre non requis
                openModal(compteEpargneModal);
            });
        });

        document.querySelectorAll('.delete-compte-btn').forEach(button => {
            button.addEventListener('click', function () {
                deleteCompteIdInput.value = this.dataset.id;
                openModal(deleteConfirmCompteModal);
            });
        });

        // --- Gestion des Modals de Transaction ---
        document.querySelectorAll('.deposit-btn').forEach(button => {
            button.addEventListener('click', function () {
                modalTitleTransaction.textContent = 'Effectuer un Dépôt';
                transactionClientNomSpan.textContent = this.dataset.client_nom;
                transactionNumeroCompteSpan.textContent = this.dataset.numero_compte;
                transactionCompteIdInput.value = this.dataset.id;
                montantTransactionInput.value = '';
                descriptionTransactionInput.value = '';
                submitDepositBtn.classList.remove('hidden');
                submitWithdrawBtn.classList.add('hidden');
                openModal(transactionModal);
            });
        });

        document.querySelectorAll('.withdraw-btn').forEach(button => {
            button.addEventListener('click', function () {
                modalTitleTransaction.textContent = 'Effectuer un Retrait';
                transactionClientNomSpan.textContent = this.dataset.client_nom;
                transactionNumeroCompteSpan.textContent = this.dataset.numero_compte;
                transactionCompteIdInput.value = this.dataset.id;
                montantTransactionInput.value = '';
                descriptionTransactionInput.value = '';
                submitDepositBtn.classList.add('hidden');
                submitWithdrawBtn.classList.remove('hidden');
                openModal(transactionModal);
            });
        });

        // --- Gestion du Modal d'Historique ---
        document.querySelectorAll('.view-history-btn').forEach(button => {
            button.addEventListener('click', function () {
                const compteId = this.dataset.id;
                historyClientNomSpan.textContent = this.dataset.client_nom;
                historyNumeroCompteSpan.textContent = this.dataset.numero_compte;
                loadTransactionHistory(compteId); // Charger l'historique
                openModal(historyModal);
            });
        });

        // Fonction pour charger l'historique des transactions via AJAX
        function loadTransactionHistory(compteId) {
            historyTableBody.innerHTML = '<tr><td colspan="5" class="text-center py-4"><i class="fas fa-spinner fa-spin mr-2"></i> Chargement de l\'historique...</td></tr>';

            fetch(`<?php echo BASE_URL; ?>modules/clients/ajax_get_transactions.php?compte_id=${compteId}`) // Utilisation de BASE_URL
                .then(response => response.json())
                .then(data => {
                    historyTableBody.innerHTML = ''; // Vider le contenu précédent
                    if (data.length > 0) {
                        data.forEach(transaction => {
                            const row = `
                            <tr>
                                <td class="border px-4 py-2">${new Date(transaction.date_transaction).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' })}</td>
                                <td class="border px-4 py-2"><span class="px-2 py-1 rounded-full text-xs font-semibold ${transaction.type_transaction === 'depot' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">${transaction.type_transaction.charAt(0).toUpperCase() + transaction.type_transaction.slice(1)}</span></td>
                                <td class="border px-4 py-2 ${transaction.type_transaction === 'depot' ? 'text-green-600' : 'text-red-600'} font-semibold">${parseFloat(transaction.montant).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}</td>
                                <td class="border px-4 py-2">${htmlspecialchars(transaction.description || 'N/A')}</td>
                                <td class="border px-4 py-2">
                                    <button class="btn btn-info print-receipt-btn bg-blue-500 hover:bg-blue-600 text-white font-bold py-1 px-3 rounded-md text-sm"
                                            data-transaction_id="${transaction.id}"
                                            data-compte_id="${compteId}"
                                            data-client_nom="${historyClientNomSpan.textContent}"
                                            data-numero_compte="${historyNumeroCompteSpan.textContent}"
                                            data-type_transaction="${transaction.type_transaction}"
                                            data-montant="${transaction.montant}"
                                            data-description="${transaction.description}"
                                            data-date_transaction="${transaction.date_transaction}">
                                        <i class="fas fa-print"></i> Reçu
                                    </button>
                                </td>
                            </tr>
                        `;
                            historyTableBody.innerHTML += row;
                        });
                        // Attacher les écouteurs d'événements aux nouveaux boutons de reçu
                        document.querySelectorAll('.print-receipt-btn').forEach(button => {
                            button.addEventListener('click', printReceipt);
                        });
                    } else {
                        historyTableBody.innerHTML = '<tr><td colspan="5" class="text-center py-4 border">Aucune transaction trouvée pour ce compte.</td></tr>';
                    }
                })
                .catch(error => {
                    console.error('Erreur lors du chargement de l\'historique:', error);
                    historyTableBody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-red-500 border">Erreur lors du chargement.</td></tr>';
                });
        }

        // Fonction pour générer et imprimer le reçu
        function printReceipt(event) {
            const btn = event.currentTarget;
            const transaction = btn.dataset;

            const companyInfo = <?php echo json_encode($company_info ?? []); ?>;
            const companyName = companyInfo.nom || 'HGB Multi';
            const companyAddress = companyInfo.adresse || '123 Rue de la Quincaillerie';
            const companyPhone = companyInfo.telephone || '+123 456 7890';
            const companyEmail = companyInfo.email || 'info@quincailleriexyz.com';

            const receiptContent = `
            <div style="font-family: 'monospace', 'Courier New', monospace; font-size: 12px; width: 250px; margin: 0 auto; border: 1px dashed #333; padding: 10px;">
                <div style="text-align: center; margin-bottom: 10px;">
                    <h3 style="margin: 0; font-size: 14px;">${companyName}</h3>
                    <p style="margin: 2px 0;">${companyAddress}</p>
                    <p style="margin: 2px 0;">Tel: ${companyPhone}</p>
                    <p style="margin: 2px 0;">Email: ${companyEmail}</p>
                    <hr style="border-top: 1px dashed #ccc; margin: 8px 0;">
                </div>
                <p style="margin: 2px 0;"><strong>REÇU DE TRANSACTION</strong></p>
                <p style="margin: 2px 0;">Date: ${new Date(transaction.date_transaction).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' })}</p>
                <p style="margin: 2px 0;">Client: ${transaction.client_nom}</p>
                <p style="margin: 2px 0;">Compte: ${transaction.numero_compte}</p>
                <hr style="border-top: 1px dashed #ccc; margin: 8px 0;">
                <p style="margin: 2px 0;">Type: <strong style="text-transform: uppercase;">${transaction.type_transaction}</strong></p>
                <p style="margin: 2px 0;">Montant: <strong style="font-size: 14px;">${parseFloat(transaction.montant).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}</strong></p>
                <p style="margin: 2px 0;">Description: ${htmlspecialchars(transaction.description || 'N/A')}</p>
                <hr style="border-top: 1px dashed #ccc; margin: 8px 0;">
                <div style="text-align: center; margin-top: 10px;">
                    <p style="margin: 2px 0;">Merci de votre confiance !</p>
                </div>
            </div>
        `;

            const printWindow = window.open('', '', 'height=600,width=400');
            printWindow.document.write('<html><head><title>Reçu de Transaction</title>');
            printWindow.document.write('</head><body>');
            printWindow.document.write(receiptContent);
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.print();
        }

        // Fonction utilitaire pour échapper les caractères HTML (pour les données venant d'AJAX)
        function htmlspecialchars(str) {
            if (typeof str !== 'string') return str; // Retourne la valeur telle quelle si ce n'est pas une chaîne
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return str.replace(/[&<>"']/g, function (m) { return map[m]; });
        }

        // --- Gestion générale des fermetures de modals ---
        modalCloseButtons.forEach(button => {
            button.addEventListener('click', function () {
                closeModal(compteEpargneModal);
                closeModal(deleteConfirmCompteModal);
                closeModal(transactionModal);
                closeModal(historyModal);
            });
        });

        window.addEventListener('click', function (event) {
            if (event.target == compteEpargneModal) closeModal(compteEpargneModal);
            if (event.target == deleteConfirmCompteModal) closeModal(deleteConfirmCompteModal);
            if (event.target == transactionModal) closeModal(transactionModal);
            if (event.target == historyModal) closeModal(historyModal);
        });

        if (cancelDeleteCompteBtn) {
            cancelDeleteCompteBtn.addEventListener('click', function () {
                closeModal(deleteConfirmCompteModal);
            });
        }

        // --- Gestion du menu mobile (sidebar) ---
        const sidebar = document.getElementById('sidebar');
        const sidebarToggleOpen = document.getElementById('sidebar-toggle-open');
        const sidebarToggleClose = document.getElementById('sidebar-toggle-close');

        function openSidebar() {
            sidebar.classList.remove('-translate-x-full');
            sidebar.classList.add('translate-x-0');
        }

        function closeSidebar() {
            sidebar.classList.remove('translate-x-0');
            sidebar.classList.add('-translate-x-full');
        }

        if (sidebarToggleOpen) {
            sidebarToggleOpen.addEventListener('click', openSidebar);
        }
        if (sidebarToggleClose) {
            sidebarToggleClose.addEventListener('click', closeSidebar);
        }

        document.addEventListener('click', function (event) {
            if (window.innerWidth < 768 && !sidebar.contains(event.target) && sidebarToggleOpen && !sidebarToggleOpen.contains(event.target) && sidebar.classList.contains('translate-x-0')) {
                closeSidebar();
            }
        });

        const sidebarLinks = sidebar.querySelectorAll('a');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', function () {
                if (window.innerWidth < 768) {
                    closeSidebar();
                }
            });
        });
    });
</script>
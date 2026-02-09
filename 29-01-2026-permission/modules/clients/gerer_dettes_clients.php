<?php
// modules/clients/gerer_dettes_clients.php
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
if (!hasPermission($conn, 'dettes_clients', 'read')) {
    header("location: " . BASE_URL . "dashboard.php?error=access_denied");
    exit;
}

// Définir les permissions pour l'affichage conditionnel des boutons
$can_create = hasPermission($conn, 'dettes_clients', 'create');
$can_update = hasPermission($conn, 'dettes_clients', 'update');
$can_delete = hasPermission($conn, 'dettes_clients', 'delete');

$page_title = "Gestion des Dettes Clients";

$message = ''; // Pour afficher les messages de succès ou d'erreur

// --- Logique de traitement des formulaires (CRUD Dettes Clients) ---

// Gérer l'ajout ou la modification d'une dette client
if ($_SERVER["REQUEST_METHOD"] == "POST" && (isset($_POST['add_dette']) || isset($_POST['edit_dette']))) {
    // Vérifier les permissions selon l'action
    if (isset($_POST['add_dette']) && !$can_create) {
        $message = '<div class="alert alert-error">Vous n\'avez pas la permission d\'ajouter des dettes clients.</div>';
        goto end_form_processing_dettes;
    }
    if (isset($_POST['edit_dette']) && !$can_update) {
        $message = '<div class="alert alert-error">Vous n\'avez pas la permission de modifier des dettes clients.</div>';
        goto end_form_processing_dettes;
    }
    $client_id = sanitize_input($_POST['client_id']);
    $montant_initial = (float) sanitize_input($_POST['montant_initial']);
    $date_limite_paiement = sanitize_input($_POST['date_limite_paiement']);
    $description = sanitize_input($_POST['description']);
    $statut = sanitize_input($_POST['statut']);

    if (isset($_POST['add_dette'])) {
        // Ajout
        $sql = "INSERT INTO dettes_clients (client_id, montant_initial, montant_restant, date_limite_paiement, description, statut) VALUES (?, ?, ?, ?, ?, ?)";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "iddsss", $client_id, $montant_initial, $montant_initial, $date_limite_paiement, $description, $statut);
            if (mysqli_stmt_execute($stmt)) {
                $message = '<div class="alert alert-success">Dette client ajoutée avec succès !</div>';
            } else {
                $message = '<div class="alert alert-error">Erreur lors de l\'ajout de la dette client : ' . mysqli_error($conn) . '</div>';
            }
            mysqli_stmt_close($stmt);
        }
    } elseif (isset($_POST['edit_dette'])) {
        // Modification
        $dette_id = sanitize_input($_POST['dette_id']);
        // Récupérer le montant initial et le montant restant actuels pour ajuster
        $current_montant_initial = 0;
        $current_montant_restant = 0;
        $sql_current = "SELECT montant_initial, montant_restant FROM dettes_clients WHERE id = ?";
        if ($stmt_current = mysqli_prepare($conn, $sql_current)) {
            mysqli_stmt_bind_param($stmt_current, "i", $dette_id);
            mysqli_stmt_execute($stmt_current);
            mysqli_stmt_bind_result($stmt_current, $current_montant_initial, $current_montant_restant);
            mysqli_stmt_fetch($stmt_current);
            mysqli_stmt_close($stmt_current);
        }

        // Si le montant initial est modifié, ajuster le montant restant proportionnellement
        $new_montant_restant = $montant_initial - ($current_montant_initial - $current_montant_restant);
        if ($new_montant_restant < 0)
            $new_montant_restant = 0; // Ne pas descendre en dessous de zéro

        $sql = "UPDATE dettes_clients SET client_id = ?, montant_initial = ?, montant_restant = ?, date_limite_paiement = ?, description = ?, statut = ? WHERE id = ?";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "iddsssi", $client_id, $montant_initial, $new_montant_restant, $date_limite_paiement, $description, $statut, $dette_id);
            if (mysqli_stmt_execute($stmt)) {
                $message = '<div class="alert alert-success">Dette client modifiée avec succès !</div>';
            } else {
                $message = '<div class="alert alert-error">Erreur lors de la modification de la dette client : ' . mysqli_error($conn) . '</div>';
            }
            mysqli_stmt_close($stmt);
        }
    }
}
end_form_processing_dettes:
;

// Gérer la suppression d'une dette client
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_dette'])) {
    // Vérifier la permission de suppression
    if (!$can_delete) {
        $message = '<div class="alert alert-error">Vous n\'avez pas la permission de supprimer des dettes clients.</div>';
    } else {
        $dette_id = sanitize_input($_POST['dette_id']);
        $sql = "DELETE FROM dettes_clients WHERE id = ?";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "i", $dette_id);
            if (mysqli_stmt_execute($stmt)) {
                $message = '<div class="alert alert-success">Dette client supprimée avec succès !</div>';
            } else {
                $message = '<div class="alert alert-error">Erreur lors de la suppression de la dette client : ' . mysqli_error($conn) . '</div>';
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Gérer le paiement d'une dette client
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['payer_dette'])) {
    $dette_id = sanitize_input($_POST['dette_id_paiement']);
    $montant_paiement = (float) sanitize_input($_POST['montant_paiement']);

    // Récupérer la dette actuelle
    $sql_dette = "SELECT montant_restant FROM dettes_clients WHERE id = ?";
    if ($stmt_dette = mysqli_prepare($conn, $sql_dette)) {
        mysqli_stmt_bind_param($stmt_dette, "i", $dette_id);
        mysqli_stmt_execute($stmt_dette);
        mysqli_stmt_bind_result($stmt_dette, $montant_restant);
        mysqli_stmt_fetch($stmt_dette);
        mysqli_stmt_close($stmt_dette);

        if ($montant_paiement <= 0) {
            $message = '<div class="alert alert-error">Le montant du paiement doit être positif.</div>';
        } elseif ($montant_paiement > $montant_restant) {
            $message = '<div class="alert alert-error">Le montant du paiement dépasse le montant restant dû.</div>';
        } else {
            $new_montant_restant = $montant_restant - $montant_paiement;
            $new_statut = ($new_montant_restant <= 0) ? 'payee' : 'en_cours';

            $sql_update = "UPDATE dettes_clients SET montant_restant = ?, statut = ? WHERE id = ?";
            if ($stmt_update = mysqli_prepare($conn, $sql_update)) {
                mysqli_stmt_bind_param($stmt_update, "dsi", $new_montant_restant, $new_statut, $dette_id);
                if (mysqli_stmt_execute($stmt_update)) {
                    $message = '<div class="alert alert-success">Paiement enregistré avec succès ! Statut : ' . $new_statut . '</div>';
                } else {
                    $message = '<div class="alert alert-error">Erreur lors de l\'enregistrement du paiement : ' . mysqli_error($conn) . '</div>';
                }
                mysqli_stmt_close($stmt_update);
            }
        }
    } else {
        $message = '<div class="alert alert-error">Dette introuvable ou erreur de base de données.</div>';
    }
}


// --- Logique de listage (Pagination, Recherche, Tri) ---

$limit = 10; // Nombre d'éléments par page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$search_query = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$sort_by = isset($_GET['sort_by']) ? sanitize_input($_GET['sort_by']) : 'dc.date_creation';
$sort_order = isset($_GET['sort_order']) && in_array(strtoupper($_GET['sort_order']), ['ASC', 'DESC']) ? $_GET['sort_order'] : 'DESC';
$filter_statut = isset($_GET['filter_statut']) ? sanitize_input($_GET['filter_statut']) : '';

// Construction de la clause WHERE pour la recherche et le filtre de statut
$where_clause = '';
$params = [];
$param_types = '';

if (!empty($search_query)) {
    $where_clause .= " WHERE (c.nom LIKE ? OR c.prenom LIKE ? OR dc.description LIKE ?)";
    $search_param = '%' . $search_query . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'sss';
}

if (!empty($filter_statut) && $filter_statut != 'tous') {
    if (empty($where_clause)) {
        $where_clause .= " WHERE";
    } else {
        $where_clause .= " AND";
    }
    $where_clause .= " dc.statut = ?";
    $params[] = $filter_statut;
    $param_types .= 's';
}

// Requête pour le nombre total de dettes (pour la pagination)
$count_sql = "SELECT COUNT(dc.id) AS total FROM dettes_clients dc JOIN clients c ON dc.client_id = c.id" . $where_clause;
$stmt_count = mysqli_prepare($conn, $count_sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt_count, $param_types, ...$params);
}
mysqli_stmt_execute($stmt_count);
$count_result = mysqli_stmt_get_result($stmt_count);
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $limit);
mysqli_stmt_close($stmt_count);

// Requête pour récupérer les dettes avec pagination, recherche, tri et filtre
$sql = "SELECT dc.id, dc.client_id, dc.montant_initial, dc.montant_restant, dc.date_creation, dc.date_limite_paiement, dc.description, dc.statut, c.nom AS client_nom, c.prenom AS client_prenom
        FROM dettes_clients dc
        JOIN clients c ON dc.client_id = c.id"
    . $where_clause . " ORDER BY " . $sort_by . " " . $sort_order . " LIMIT ? OFFSET ?";

$stmt = mysqli_prepare($conn, $sql);
// Ajouter les paramètres de LIMIT et OFFSET aux paramètres existants
$params[] = $limit;
$params[] = $offset;
$param_types .= 'ii';

mysqli_stmt_bind_param($stmt, $param_types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$dettes_clients = mysqli_fetch_all($result, MYSQLI_ASSOC);
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
                            <button id="addDetteBtn"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md shadow-md transition duration-300 mb-4 md:mb-0">
                                <i class="fas fa-plus-circle mr-2"></i> Ajouter une Dette
                            </button>
                        <?php endif; ?>
                        <form method="GET" action="" class="flex flex-col md:flex-row gap-4 w-full md:w-auto">
                            <input type="text" name="search" placeholder="Rechercher..."
                                value="<?php echo htmlspecialchars($search_query); ?>"
                                class="flex-grow shadow appearance-none border rounded-md py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            <select name="filter_statut"
                                class="shadow appearance-none border rounded-md py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                <option value="tous" <?php echo $filter_statut == 'tous' ? 'selected' : ''; ?>>Tous les
                                    statuts</option>
                                <option value="en_cours" <?php echo $filter_statut == 'en_cours' ? 'selected' : ''; ?>>En
                                    cours</option>
                                <option value="payee" <?php echo $filter_statut == 'payee' ? 'selected' : ''; ?>>Payée
                                </option>
                                <option value="annulee" <?php echo $filter_statut == 'annulee' ? 'selected' : ''; ?>>
                                    Annulée</option>
                            </select>
                            <select name="sort_by"
                                class="shadow appearance-none border rounded-md py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                <option value="dc.date_creation" <?php echo $sort_by == 'dc.date_creation' ? 'selected' : ''; ?>>Trier par Date Création</option>
                                <option value="c.nom" <?php echo $sort_by == 'c.nom' ? 'selected' : ''; ?>>Trier par Nom
                                    Client</option>
                                <option value="dc.montant_restant" <?php echo $sort_by == 'dc.montant_restant' ? 'selected' : ''; ?>>Trier par Montant Restant</option>
                                <option value="dc.date_limite_paiement" <?php echo $sort_by == 'dc.date_limite_paiement' ? 'selected' : ''; ?>>Trier par Date Limite</option>
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
                                    <th class="px-4 py-2">ID Dette</th>
                                    <th class="px-4 py-2">Client</th>
                                    <th class="px-4 py-2">Montant Initial</th>
                                    <th class="px-4 py-2">Montant Restant</th>
                                    <th class="px-4 py-2">Date Création</th>
                                    <th class="px-4 py-2">Date Limite</th>
                                    <th class="px-4 py-2">Description</th>
                                    <th class="px-4 py-2">Statut</th>
                                    <th class="px-4 py-2">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($dettes_clients)): ?>
                                    <?php foreach ($dettes_clients as $dette): ?>
                                        <tr>
                                            <td class="border px-4 py-2"><?php echo htmlspecialchars($dette['id']); ?></td>
                                            <td class="border px-4 py-2">
                                                <?php echo htmlspecialchars($dette['client_nom'] . ' ' . $dette['client_prenom']); ?>
                                            </td>
                                            <td class="border px-4 py-2">
                                                <?php echo number_format($dette['montant_initial'], 2, ',', ' '); ?> XOF
                                            </td>
                                            <td
                                                class="border px-4 py-2 font-semibold <?php echo ($dette['montant_restant'] > 0 && $dette['statut'] == 'en_cours') ? 'text-red-700' : 'text-green-700'; ?>">
                                                <?php echo number_format($dette['montant_restant'], 2, ',', ' '); ?> XOF
                                            </td>
                                            <td class="border px-4 py-2">
                                                <?php echo date('d/m/Y', strtotime($dette['date_creation'])); ?>
                                            </td>
                                            <td class="border px-4 py-2">
                                                <?php echo date('d/m/Y', strtotime($dette['date_limite_paiement'])); ?>
                                            </td>
                                            <td class="border px-4 py-2"><?php echo htmlspecialchars($dette['description']); ?>
                                            </td>
                                            <td class="border px-4 py-2">
                                                <span class="px-2 py-1 rounded-full text-xs font-semibold
                                                    <?php
                                                    if ($dette['statut'] == 'en_cours')
                                                        echo 'bg-yellow-100 text-yellow-800';
                                                    else if ($dette['statut'] == 'payee')
                                                        echo 'bg-green-100 text-green-800';
                                                    else if ($dette['statut'] == 'annulee')
                                                        echo 'bg-gray-100 text-gray-800';
                                                    ?>">
                                                    <?php echo htmlspecialchars(str_replace('_', ' ', ucfirst($dette['statut']))); ?>
                                                </span>
                                            </td>
                                            <td class="border px-4 py-2 action-buttons flex flex-wrap gap-2">
                                                <?php if ($can_update): ?>
                                                    <button
                                                        class="btn btn-edit edit-dette-btn bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-1 px-3 rounded-md text-sm"
                                                        data-id="<?php echo htmlspecialchars($dette['id']); ?>"
                                                        data-client_id="<?php echo htmlspecialchars($dette['client_id']); ?>"
                                                        data-montant_initial="<?php echo htmlspecialchars($dette['montant_initial']); ?>"
                                                        data-date_limite_paiement="<?php echo htmlspecialchars($dette['date_limite_paiement']); ?>"
                                                        data-description="<?php echo htmlspecialchars($dette['description']); ?>"
                                                        data-statut="<?php echo htmlspecialchars($dette['statut']); ?>">
                                                        <i class="fas fa-edit"></i> Modifier
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($dette['statut'] == 'en_cours' && $dette['montant_restant'] > 0 && $can_update): ?>
                                                    <button
                                                        class="btn btn-success payer-dette-btn bg-green-500 hover:bg-green-600 text-white font-bold py-1 px-3 rounded-md text-sm"
                                                        data-id="<?php echo htmlspecialchars($dette['id']); ?>"
                                                        data-client_nom="<?php echo htmlspecialchars($dette['client_nom'] . ' ' . $dette['client_prenom']); ?>"
                                                        data-montant_restant="<?php echo htmlspecialchars($dette['montant_restant']); ?>">
                                                        <i class="fas fa-money-check-alt"></i> Payer
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($can_delete): ?>
                                                    <button
                                                        class="btn btn-delete delete-dette-btn bg-red-500 hover:bg-red-600 text-white font-bold py-1 px-3 rounded-md text-sm"
                                                        data-id="<?php echo htmlspecialchars($dette['id']); ?>">
                                                        <i class="fas fa-trash-alt"></i> Supprimer
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-4 border">Aucune dette client trouvée.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="pagination flex flex-wrap justify-center items-center gap-2 mt-6">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search_query); ?>&sort_by=<?php echo urlencode($sort_by); ?>&sort_order=<?php echo urlencode($sort_order); ?>&filter_statut=<?php echo urlencode($filter_statut); ?>"
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
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search_query); ?>&sort_by=<?php echo urlencode($sort_by); ?>&sort_order=<?php echo urlencode($sort_order); ?>&filter_statut=<?php echo urlencode($filter_statut); ?>"
                                class="btn-pagination <?php echo ($i == $page) ? 'current-page bg-blue-700 text-white' : 'bg-blue-500 hover:bg-blue-700 text-white'; ?> font-bold py-2 px-4 rounded-md"><?php echo $i; ?></a>
                        <?php endfor;

                        if ($end_page < $total_pages) {
                            if ($end_page < $total_pages - 1) {
                                echo '<span class="px-2 py-2">...</span>';
                            }
                            echo '<a href="?page=' . $total_pages . '&search=' . urlencode($search_query) . '&sort_by=' . urlencode($sort_by) . '&sort_order=' . urlencode($sort_order) . '&filter_statut=' . urlencode($filter_statut) . '" class="btn-pagination bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md">' . $total_pages . '</a>';
                        }
                        ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search_query); ?>&sort_by=<?php echo urlencode($sort_by); ?>&sort_order=<?php echo urlencode($sort_order); ?>&filter_statut=<?php echo urlencode($filter_statut); ?>"
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

<!-- Modal d'ajout/modification de dette client -->
<div id="detteClientModal"
    class="modal hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full flex items-center justify-center">
    <div class="modal-content bg-white p-6 rounded-lg shadow-xl max-w-lg w-11/12 mx-auto">
        <span
            class="modal-close-button absolute top-3 right-3 text-gray-600 hover:text-gray-900 text-2xl cursor-pointer">&times;</span>
        <h2 id="modalTitleDetteClient" class="text-2xl font-bold text-gray-800 mb-4">Ajouter une Dette Client</h2>
        <form id="detteClientForm" method="POST" action="">
            <input type="hidden" id="detteId" name="dette_id">
            <div class="form-group mb-4">
                <label for="client_id_dette" class="block text-gray-700 text-sm font-bold mb-2">Client:</label>
                <select id="client_id_dette" name="client_id" required
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
                <label for="montant_initial" class="block text-gray-700 text-sm font-bold mb-2">Montant Initial:</label>
                <input type="number" step="0.01" id="montant_initial" name="montant_initial" required min="0.01"
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div class="form-group mb-4">
                <label for="date_limite_paiement" class="block text-gray-700 text-sm font-bold mb-2">Date Limite de
                    Paiement:</label>
                <input type="date" id="date_limite_paiement" name="date_limite_paiement"
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div class="form-group mb-4">
                <label for="description_dette" class="block text-gray-700 text-sm font-bold mb-2">Description:</label>
                <textarea id="description_dette" name="description" rows="3"
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"></textarea>
            </div>
            <div class="form-group mb-6">
                <label for="statut" class="block text-gray-700 text-sm font-bold mb-2">Statut:</label>
                <select id="statut" name="statut" required
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="en_cours">En cours</option>
                    <option value="payee">Payée</option>
                    <option value="annulee">Annulée</option>
                </select>
            </div>
            <button type="submit" id="submitDetteBtn" name="add_dette"
                class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full">
                <i class="fas fa-save mr-2"></i> Enregistrer
            </button>
        </form>
    </div>
</div>

<!-- Modal de confirmation de suppression -->
<div id="deleteConfirmDetteModal"
    class="modal hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full flex items-center justify-center">
    <div class="modal-content bg-white p-6 rounded-lg shadow-xl max-w-sm w-11/12 mx-auto text-center">
        <span
            class="modal-close-button absolute top-3 right-3 text-gray-600 hover:text-gray-900 text-2xl cursor-pointer">&times;</span>
        <h2 class="text-2xl font-bold text-gray-800 mb-4">Confirmer la Suppression</h2>
        <p class="text-gray-700 mb-6">Êtes-vous sûr de vouloir supprimer cette dette client ? Cette action est
            irréversible.</p>
        <form id="deleteDetteForm" method="POST" action="">
            <input type="hidden" id="deleteDetteId" name="dette_id">
            <div class="flex justify-center space-x-4">
                <button type="button" id="cancelDeleteDetteBtn"
                    class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded-md transition duration-300">
                    Annuler
                </button>
                <button type="submit" name="delete_dette"
                    class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-md transition duration-300">
                    Supprimer <i class="fas fa-trash-alt ml-2"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal de paiement de dette -->
<div id="payerDetteModal"
    class="modal hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full flex items-center justify-center">
    <div class="modal-content bg-white p-6 rounded-lg shadow-xl max-w-md w-11/12 mx-auto">
        <span
            class="modal-close-button absolute top-3 right-3 text-gray-600 hover:text-gray-900 text-2xl cursor-pointer">&times;</span>
        <h2 class="text-2xl font-bold text-gray-800 mb-4">Enregistrer un Paiement</h2>
        <p class="text-gray-700 mb-4">Client: <span id="paiementClientNom" class="font-semibold"></span></p>
        <p class="text-gray-700 mb-6">Montant Restant Dû: <span id="paiementMontantRestant"
                class="font-semibold text-red-700"></span> XOF</p>
        <form id="payerDetteForm" method="POST" action="">
            <input type="hidden" id="detteIdPaiement" name="dette_id_paiement">
            <div class="form-group mb-6">
                <label for="montant_paiement" class="block text-gray-700 text-sm font-bold mb-2">Montant du
                    Paiement:</label>
                <input type="number" step="0.01" id="montant_paiement" name="montant_paiement" required min="0.01"
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <button type="submit" name="payer_dette"
                class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full">
                <i class="fas fa-check-circle mr-2"></i> Enregistrer le Paiement
            </button>
        </form>
    </div>
</div>



<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Refs pour le modal d'ajout/modification de dette client
        const detteClientModal = document.getElementById('detteClientModal');
        const addDetteBtn = document.getElementById('addDetteBtn');
        const modalCloseButtons = document.querySelectorAll('.modal-close-button');
        const detteClientForm = document.getElementById('detteClientForm');
        const modalTitleDetteClient = document.getElementById('modalTitleDetteClient');
        const submitDetteBtn = document.getElementById('submitDetteBtn');
        const detteIdInput = document.getElementById('detteId');
        const clientIdDetteSelect = document.getElementById('client_id_dette');
        const montantInitialInput = document.getElementById('montant_initial');
        const dateLimitePaiementInput = document.getElementById('date_limite_paiement');
        const descriptionDetteInput = document.getElementById('description_dette');
        const statutSelect = document.getElementById('statut');

        // Refs pour le modal de suppression
        const deleteConfirmDetteModal = document.getElementById('deleteConfirmDetteModal');
        const deleteDetteIdInput = document.getElementById('deleteDetteId');
        const cancelDeleteDetteBtn = document.getElementById('cancelDeleteDetteBtn');

        // Refs pour le modal de paiement
        const payerDetteModal = document.getElementById('payerDetteModal');
        const paiementClientNomSpan = document.getElementById('paiementClientNom');
        const paiementMontantRestantSpan = document.getElementById('paiementMontantRestant');
        const detteIdPaiementInput = document.getElementById('detteIdPaiement');
        const montantPaiementInput = document.getElementById('montant_paiement');

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

        // --- Gestion des Modals de Dette Client ---
        if (addDetteBtn) {
            addDetteBtn.addEventListener('click', function () {
                modalTitleDetteClient.textContent = 'Ajouter une Dette Client';
                submitDetteBtn.name = 'add_dette';
                submitDetteBtn.innerHTML = '<i class="fas fa-save mr-2"></i> Enregistrer';
                detteClientForm.reset();
                detteIdInput.value = '';
                statutSelect.value = 'en_cours'; // Statut par défaut
                openModal(detteClientModal);
            });
        }

        document.querySelectorAll('.edit-dette-btn').forEach(button => {
            button.addEventListener('click', function () {
                modalTitleDetteClient.textContent = 'Modifier la Dette Client';
                submitDetteBtn.name = 'edit_dette';
                submitDetteBtn.innerHTML = '<i class="fas fa-edit mr-2"></i> Mettre à jour';

                detteIdInput.value = this.dataset.id;
                clientIdDetteSelect.value = this.dataset.client_id;
                montantInitialInput.value = this.dataset.montant_initial;
                dateLimitePaiementInput.value = this.dataset.date_limite_paiement;
                descriptionDetteInput.value = this.dataset.description;
                statutSelect.value = this.dataset.statut;

                openModal(detteClientModal);
            });
        });

        document.querySelectorAll('.delete-dette-btn').forEach(button => {
            button.addEventListener('click', function () {
                deleteDetteIdInput.value = this.dataset.id;
                openModal(deleteConfirmDetteModal);
            });
        });

        // --- Gestion du Modal de Paiement ---
        document.querySelectorAll('.payer-dette-btn').forEach(button => {
            button.addEventListener('click', function () {
                paiementClientNomSpan.textContent = this.dataset.client_nom;
                paiementMontantRestantSpan.textContent = parseFloat(this.dataset.montant_restant).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                detteIdPaiementInput.value = this.dataset.id;
                montantPaiementInput.value = parseFloat(this.dataset.montant_restant).toFixed(2); // Pré-remplir avec le montant restant
                montantPaiementInput.max = parseFloat(this.dataset.montant_restant).toFixed(2); // Définir le max
                openModal(payerDetteModal);
            });
        });

        // --- Gestion générale des fermetures de modals ---
        modalCloseButtons.forEach(button => {
            button.addEventListener('click', function () {
                closeModal(detteClientModal);
                closeModal(deleteConfirmDetteModal);
                closeModal(payerDetteModal);
            });
        });

        window.addEventListener('click', function (event) {
            if (event.target == detteClientModal) closeModal(detteClientModal);
            if (event.target == deleteConfirmDetteModal) closeModal(deleteConfirmDetteModal);
            if (event.target == payerDetteModal) closeModal(payerDetteModal);
        });

        if (cancelDeleteDetteBtn) {
            cancelDeleteDetteBtn.addEventListener('click', function () {
                closeModal(deleteConfirmDetteModal);
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
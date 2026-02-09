<?php
// modules/finance/gerer_depenses.php
session_start();

// Inclure les fichiers nécessaires
include '../../db_connect.php';
include '../../includes/permissions_helper.php'; // Helper de permissions
include '../../includes/header.php'; // Inclut le header avec le début du HTML

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("location: " . BASE_URL . "public/login.php");
    exit;
}

// Vérifier les permissions d'accès au module dépenses (lecture)
if (!hasPermission($conn, 'depenses', 'read')) {
    header("location: " . BASE_URL . "dashboard.php?error=access_denied");
    exit;
}

// Définir les permissions pour l'affichage conditionnel des boutons
$can_create = hasPermission($conn, 'depenses', 'create');
$can_update = hasPermission($conn, 'depenses', 'update');
$can_delete = hasPermission($conn, 'depenses', 'delete');

$page_title = "Gestion des Dépenses";

$message = ''; // Pour afficher les messages de succès ou d'erreur

// --- Logique de traitement des formulaires (CRUD Dépenses) ---

// Gérer l'ajout ou la modification d'une dépense
if ($_SERVER["REQUEST_METHOD"] == "POST" && (isset($_POST['add_depense']) || isset($_POST['edit_depense']))) {
    // Vérifier les permissions selon l'action
    if (isset($_POST['add_depense']) && !$can_create) {
        $message = '<div class="alert alert-error">Vous n\'avez pas la permission d\'ajouter des dépenses.</div>';
        goto end_form_processing_depenses;
    }
    if (isset($_POST['edit_depense']) && !$can_update) {
        $message = '<div class="alert alert-error">Vous n\'avez pas la permission de modifier des dépenses.</div>';
        goto end_form_processing_depenses;
    }

    $description = sanitize_input($_POST['description']);
    $montant = (float) sanitize_input($_POST['montant']);
    $date_depense = sanitize_input($_POST['date_depense']);
    $categorie_depense = sanitize_input($_POST['categorie_depense']);
    $magasin_id = !empty($_POST['magasin_id']) ? sanitize_input($_POST['magasin_id']) : NULL;
    $personnel_id = $_SESSION['id']; // L'utilisateur connecté est celui qui enregistre la dépense

    if (isset($_POST['add_depense'])) {
        // Ajout
        $sql = "INSERT INTO depenses (description, montant, date_depense, categorie_depense, personnel_id, magasin_id) VALUES (?, ?, ?, ?, ?, ?)";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "sdssii", $description, $montant, $date_depense, $categorie_depense, $personnel_id, $magasin_id);
            if (mysqli_stmt_execute($stmt)) {
                $message = '<div class="alert alert-success">Dépense ajoutée avec succès !</div>';
            } else {
                $message = '<div class="alert alert-error">Erreur lors de l\'ajout de la dépense : ' . mysqli_error($conn) . '</div>';
            }
            mysqli_stmt_close($stmt);
        }
    } elseif (isset($_POST['edit_depense'])) {
        // Modification
        $depense_id = sanitize_input($_POST['depense_id']);
        $sql = "UPDATE depenses SET description = ?, montant = ?, date_depense = ?, categorie_depense = ?, magasin_id = ? WHERE id = ?";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "sdssii", $description, $montant, $date_depense, $categorie_depense, $magasin_id, $depense_id);
            if (mysqli_stmt_execute($stmt)) {
                $message = '<div class="alert alert-success">Dépense modifiée avec succès !</div>';
            } else {
                $message = '<div class="alert alert-error">Erreur lors de la modification de la dépense : ' . mysqli_error($conn) . '</div>';
            }
            mysqli_stmt_close($stmt);
        }
    }
}
end_form_processing_depenses:
;

// Gérer la suppression d'une dépense
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_depense'])) {
    if (!$can_delete) {
        $message = '<div class="alert alert-error">Vous n\'avez pas la permission de supprimer des dépenses.</div>';
    } else {
        $depense_id = sanitize_input($_POST['depense_id']);
        $sql = "DELETE FROM depenses WHERE id = ?";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "i", $depense_id);
            if (mysqli_stmt_execute($stmt)) {
                $message = '<div class="alert alert-success">Dépense supprimée avec succès !</div>';
            } else {
                $message = '<div class="alert alert-error">Erreur lors de la suppression de la dépense : ' . mysqli_error($conn) . '</div>';
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// --- Logique de listage (Pagination, Recherche, Tri) ---

$limit = 10; // Nombre d'éléments par page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$search_query = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$sort_by = isset($_GET['sort_by']) ? sanitize_input($_GET['sort_by']) : 'd.date_depense';
$sort_order = isset($_GET['sort_order']) && in_array(strtoupper($_GET['sort_order']), ['ASC', 'DESC']) ? $_GET['sort_order'] : 'DESC';
$filter_categorie = isset($_GET['filter_categorie']) ? sanitize_input($_GET['filter_categorie']) : '';
$filter_magasin = isset($_GET['filter_magasin']) ? sanitize_input($_GET['filter_magasin']) : '';


// Construction de la clause WHERE pour la recherche et les filtres
$where_clause = '';
$params = [];
$param_types = '';

if (!empty($search_query)) {
    $where_clause .= " WHERE (d.description LIKE ? OR d.categorie_depense LIKE ? OR p.nom LIKE ? OR p.prenom LIKE ? OR m.nom LIKE ?)";
    $search_param = '%' . $search_query . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'sssss';
}

if (!empty($filter_categorie)) {
    if (empty($where_clause))
        $where_clause .= " WHERE";
    else
        $where_clause .= " AND";
    $where_clause .= " d.categorie_depense = ?";
    $params[] = $filter_categorie;
    $param_types .= 's';
}

if (!empty($filter_magasin)) {
    if (empty($where_clause))
        $where_clause .= " WHERE";
    else
        $where_clause .= " AND";
    $where_clause .= " d.magasin_id = ?";
    $params[] = $filter_magasin;
    $param_types .= 'i';
}


// Requête pour le nombre total de dépenses (pour la pagination)
$count_sql = "SELECT COUNT(d.id) AS total FROM depenses d
              JOIN personnel p ON d.personnel_id = p.id
              LEFT JOIN magasins m ON d.magasin_id = m.id"
    . $where_clause;
$stmt_count = mysqli_prepare($conn, $count_sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt_count, $param_types, ...$params);
}
mysqli_stmt_execute($stmt_count);
$count_result = mysqli_stmt_get_result($stmt_count);
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $limit);
mysqli_stmt_close($stmt_count);


// Requête pour récupérer les dépenses avec pagination, recherche, tri et filtres
$sql = "SELECT d.id, d.description, d.montant, d.date_depense, d.categorie_depense, d.date_enregistrement,
               p.nom AS personnel_nom, p.prenom AS personnel_prenom,
               m.nom AS nom_magasin, d.magasin_id
        FROM depenses d
        JOIN personnel p ON d.personnel_id = p.id
        LEFT JOIN magasins m ON d.magasin_id = m.id"
    . $where_clause . " ORDER BY " . $sort_by . " " . $sort_order . " LIMIT ? OFFSET ?";

$stmt = mysqli_prepare($conn, $sql);
// Ajouter les paramètres de LIMIT et OFFSET aux paramètres existants
$params[] = $limit;
$params[] = $offset;
$param_types .= 'ii';

mysqli_stmt_bind_param($stmt, $param_types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$depenses = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);


// Récupérer la liste des catégories de dépenses uniques pour le filtre
$categories_disponibles = [];
$sql_categories = "SELECT DISTINCT categorie_depense FROM depenses WHERE categorie_depense IS NOT NULL AND categorie_depense != '' ORDER BY categorie_depense ASC";
$result_categories = mysqli_query($conn, $sql_categories);
while ($row = mysqli_fetch_assoc($result_categories)) {
    $categories_disponibles[] = $row['categorie_depense'];
}
mysqli_free_result($result_categories);

// Récupérer la liste des magasins pour le filtre
$magasins_disponibles = [];
$sql_magasins = "SELECT id, nom FROM magasins ORDER BY nom ASC";
$result_magasins = mysqli_query($conn, $sql_magasins);
while ($row = mysqli_fetch_assoc($result_magasins)) {
    $magasins_disponibles[] = $row;
}
mysqli_free_result($result_magasins);

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
                            <button id="addDepenseBtn"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md shadow-md transition duration-300 mb-4 md:mb-0">
                                <i class="fas fa-plus-circle mr-2"></i> Ajouter une Dépense
                            </button>
                        <?php endif; ?>
                        <form method="GET" action=""
                            class="flex flex-col md:flex-row gap-4 w-full md:w-auto items-center">
                            <input type="text" name="search" placeholder="Rechercher..."
                                value="<?php echo htmlspecialchars($search_query); ?>"
                                class="flex-grow shadow appearance-none border rounded-md py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            <select name="filter_categorie"
                                class="shadow appearance-none border rounded-md py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                <option value="">Toutes Catégories</option>
                                <?php foreach ($categories_disponibles as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($filter_categorie == $cat) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select name="filter_magasin"
                                class="shadow appearance-none border rounded-md py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                <option value="">Tous Magasins</option>
                                <?php foreach ($magasins_disponibles as $mag): ?>
                                    <option value="<?php echo htmlspecialchars($mag['id']); ?>" <?php echo ($filter_magasin == $mag['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($mag['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select name="sort_by"
                                class="shadow appearance-none border rounded-md py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                <option value="d.date_depense" <?php echo $sort_by == 'd.date_depense' ? 'selected' : ''; ?>>Trier par Date Dépense</option>
                                <option value="d.montant" <?php echo $sort_by == 'd.montant' ? 'selected' : ''; ?>>Trier
                                    par Montant</option>
                                <option value="d.categorie_depense" <?php echo $sort_by == 'd.categorie_depense' ? 'selected' : ''; ?>>Trier par Catégorie</option>
                            </select>
                            <select name="sort_order"
                                class="shadow appearance-none border rounded-md py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                <option value="DESC" <?php echo $sort_order == 'DESC' ? 'selected' : ''; ?>>Descendant
                                </option>
                                <option value="ASC" <?php echo $sort_order == 'ASC' ? 'selected' : ''; ?>>Ascendant
                                </option>
                            </select>
                            <button type="submit"
                                class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-md shadow-md transition duration-300">
                                <i class="fas fa-filter mr-2"></i> Filtrer
                            </button>
                        </form>
                    </div>

                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Description</th>
                                    <th>Montant</th>
                                    <th>Date Dépense</th>
                                    <th>Catégorie</th>
                                    <th>Enregistré par</th>
                                    <th>Magasin</th>
                                    <th>Date Enregistrement</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($depenses)): ?>
                                    <?php foreach ($depenses as $depense): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($depense['id']); ?></td>
                                            <td><?php echo htmlspecialchars($depense['description']); ?></td>
                                            <td class="font-semibold text-red-700">
                                                <?php echo number_format($depense['montant'], 2, ',', ' '); ?> XOF
                                            </td>
                                            <td><?php echo date('d/m/Y', strtotime($depense['date_depense'])); ?></td>
                                            <td><?php echo htmlspecialchars($depense['categorie_depense']); ?></td>
                                            <td><?php echo htmlspecialchars($depense['personnel_nom'] . ' ' . $depense['personnel_prenom']); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($depense['nom_magasin'] ?? 'N/A'); ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($depense['date_enregistrement'])); ?>
                                            </td>
                                            <td class="action-buttons flex-wrap">
                                                <?php if ($can_update): ?>
                                                    <button class="btn btn-edit edit-depense-btn"
                                                        data-id="<?php echo htmlspecialchars($depense['id']); ?>"
                                                        data-description="<?php echo htmlspecialchars($depense['description']); ?>"
                                                        data-montant="<?php echo htmlspecialchars($depense['montant']); ?>"
                                                        data-date_depense="<?php echo htmlspecialchars($depense['date_depense']); ?>"
                                                        data-categorie_depense="<?php echo htmlspecialchars($depense['categorie_depense']); ?>"
                                                        data-magasin_id="<?php echo htmlspecialchars($depense['magasin_id']); ?>">
                                                        <i class="fas fa-edit"></i> Modifier
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($can_delete): ?>
                                                    <button class="btn btn-delete delete-depense-btn"
                                                        data-id="<?php echo htmlspecialchars($depense['id']); ?>">
                                                        <i class="fas fa-trash-alt"></i> Supprimer
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-4">Aucune dépense trouvée.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="flex flex-col sm:flex-row justify-between items-center mt-4">
                        <p class="text-gray-600 mb-2 sm:mb-0">Affichage de
                            <?php echo min($limit, $total_records - $offset); ?> sur <?php echo $total_records; ?>
                            dépenses
                        </p>
                        <div class="flex flex-wrap justify-center sm:justify-start gap-2">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search_query); ?>&sort_by=<?php echo urlencode($sort_by); ?>&sort_order=<?php echo urlencode($sort_order); ?>&filter_categorie=<?php echo urlencode($filter_categorie); ?>&filter_magasin=<?php echo urlencode($filter_magasin); ?>"
                                    class="px-3 py-1 text-sm bg-gray-200 rounded-md hover:bg-gray-300">Précédent</a>
                            <?php endif; ?>

                            <?php
                            // Logique pour afficher un nombre limité de pages autour de la page actuelle
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);

                            if ($start_page > 1) {
                                echo '<a href="?page=1&search=' . urlencode($search_query) . '&sort_by=' . urlencode($sort_by) . '&sort_order=' . urlencode($sort_order) . '&filter_categorie=' . urlencode($filter_categorie) . '&filter_magasin=' . urlencode($filter_magasin) . '" class="px-3 py-1 text-sm rounded-md bg-gray-200 hover:bg-gray-300">1</a>';
                                if ($start_page > 2) {
                                    echo '<span class="px-3 py-1 text-sm">...</span>';
                                }
                            }

                            for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search_query); ?>&sort_by=<?php echo urlencode($sort_by); ?>&sort_order=<?php echo urlencode($sort_order); ?>&filter_categorie=<?php echo urlencode($filter_categorie); ?>&filter_magasin=<?php echo urlencode($filter_magasin); ?>"
                                    class="px-3 py-1 text-sm rounded-md <?php echo ($i == $page) ? 'bg-blue-600 text-white' : 'bg-gray-200 hover:bg-gray-300'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor;

                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<span class="px-3 py-1 text-sm">...</span>';
                                }
                                echo '<a href="?page=' . $total_pages . '&search=' . urlencode($search_query) . '&sort_by=' . urlencode($sort_by) . '&sort_order=' . urlencode($sort_order) . '&filter_categorie=' . urlencode($filter_categorie) . '&filter_magasin=' . urlencode($filter_magasin) . '" class="px-3 py-1 text-sm rounded-md bg-gray-200 hover:bg-gray-300">' . $total_pages . '</a>';
                            }
                            ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search_query); ?>&sort_by=<?php echo urlencode($sort_by); ?>&sort_order=<?php echo urlencode($sort_order); ?>&filter_categorie=<?php echo urlencode($filter_categorie); ?>&filter_magasin=<?php echo urlencode($filter_magasin); ?>"
                                    class="px-3 py-1 text-sm bg-gray-200 rounded-md hover:bg-gray-300">Suivant</a>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </div>
        </main>
        <?php include '../../includes/footer.php'; ?>
    </div>
</div>

<!-- Modal d'ajout/modification de dépense -->
<div id="depenseModal" class="modal hidden">
    <div class="modal-content max-w-lg">
        <span class="modal-close-button">&times;</span>
        <h2 id="modalTitleDepense" class="text-2xl font-bold text-gray-800 mb-4">Ajouter une Dépense</h2>
        <form id="depenseForm" method="POST" action="">
            <input type="hidden" id="depenseId" name="depense_id">
            <div class="form-group">
                <label for="description_depense">Description:</label>
                <textarea id="description_depense" name="description" rows="3" required
                    class="w-full p-2 border border-gray-300 rounded-md"></textarea>
            </div>
            <div class="form-group">
                <label for="montant">Montant:</label>
                <input type="number" step="0.01" id="montant" name="montant" required min="0.01"
                    class="w-full p-2 border border-gray-300 rounded-md">
            </div>
            <div class="form-group">
                <label for="date_depense">Date de la Dépense:</label>
                <input type="date" id="date_depense" name="date_depense" required
                    class="w-full p-2 border border-gray-300 rounded-md">
            </div>
            <div class="form-group">
                <label for="categorie_depense">Catégorie:</label>
                <input type="text" id="categorie_depense" name="categorie_depense" list="categoriesList"
                    class="w-full p-2 border border-gray-300 rounded-md"
                    placeholder="Ex: Loyer, Salaires, Fournitures...">
                <datalist id="categoriesList">
                    <?php foreach ($categories_disponibles as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>">
                        <?php endforeach; ?>
                </datalist>
            </div>
            <div class="form-group">
                <label for="magasin_id_depense">Magasin Associé (Optionnel):</label>
                <select id="magasin_id_depense" name="magasin_id" class="w-full p-2 border border-gray-300 rounded-md">
                    <option value="">-- Dépense Générale --</option>
                    <?php foreach ($magasins_disponibles as $mag): ?>
                        <option value="<?php echo htmlspecialchars($mag['id']); ?>">
                            <?php echo htmlspecialchars($mag['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" id="submitDepenseBtn" name="add_depense" class="btn-primary mt-4 w-full">
                <i class="fas fa-save mr-2"></i> Enregistrer
            </button>
        </form>
    </div>
</div>

<!-- Modal de confirmation de suppression -->
<div id="deleteConfirmDepenseModal" class="modal hidden">
    <div class="modal-content max-w-sm text-center">
        <span class="modal-close-button">&times;</span>
        <h2 class="text-2xl font-bold text-gray-800 mb-4">Confirmer la Suppression</h2>
        <p class="text-gray-700 mb-6">Êtes-vous sûr de vouloir supprimer cette dépense ? Cette action est irréversible.
        </p>
        <form id="deleteDepenseForm" method="POST" action="">
            <input type="hidden" id="deleteDepenseId" name="depense_id">
            <div class="flex justify-center space-x-4">
                <button type="button" id="cancelDeleteDepenseBtn"
                    class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded-md transition duration-300">
                    Annuler
                </button>
                <button type="submit" name="delete_depense"
                    class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-md transition duration-300">
                    Supprimer <i class="fas fa-trash-alt ml-2"></i>
                </button>
            </div>
        </form>
    </div>
</div>



<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Refs pour le modal d'ajout/modification de dépense
        const depenseModal = document.getElementById('depenseModal');
        const addDepenseBtn = document.getElementById('addDepenseBtn');
        const modalCloseButtons = document.querySelectorAll('.modal-close-button');
        const depenseForm = document.getElementById('depenseForm');
        const modalTitleDepense = document.getElementById('modalTitleDepense');
        const submitDepenseBtn = document.getElementById('submitDepenseBtn');

        const depenseIdInput = document.getElementById('depenseId');
        const descriptionDepenseInput = document.getElementById('description_depense');
        const montantInput = document.getElementById('montant');
        const dateDepenseInput = document.getElementById('date_depense');
        const categorieDepenseInput = document.getElementById('categorie_depense');
        const magasinIdDepenseSelect = document.getElementById('magasin_id_depense');

        // Refs pour le modal de suppression
        const deleteConfirmDepenseModal = document.getElementById('deleteConfirmDepenseModal');
        const deleteDepenseIdInput = document.getElementById('deleteDepenseId');
        const cancelDeleteDepenseBtn = document.getElementById('cancelDeleteDepenseBtn');

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

        // --- Gestion des Modals de Dépense ---
        if (addDepenseBtn) {
            addDepenseBtn.addEventListener('click', function () {
                modalTitleDepense.textContent = 'Ajouter une Dépense';
                submitDepenseBtn.name = 'add_depense';
                submitDepenseBtn.innerHTML = '<i class="fas fa-save mr-2"></i> Enregistrer';
                depenseForm.reset();
                depenseIdInput.value = '';
                // Pré-remplir la date avec la date actuelle
                dateDepenseInput.valueAsDate = new Date();
                openModal(depenseModal);
            });
        }

        document.querySelectorAll('.edit-depense-btn').forEach(button => {
            button.addEventListener('click', function () {
                modalTitleDepense.textContent = 'Modifier la Dépense';
                submitDepenseBtn.name = 'edit_depense';
                submitDepenseBtn.innerHTML = '<i class="fas fa-edit mr-2"></i> Mettre à jour';

                depenseIdInput.value = this.dataset.id;
                descriptionDepenseInput.value = this.dataset.description;
                montantInput.value = this.dataset.montant;
                dateDepenseInput.value = this.dataset.date_depense;
                categorieDepenseInput.value = this.dataset.categorie_depense;
                magasinIdDepenseSelect.value = this.dataset.magasin_id;

                openModal(depenseModal);
            });
        });

        document.querySelectorAll('.delete-depense-btn').forEach(button => {
            button.addEventListener('click', function () {
                deleteDepenseIdInput.value = this.dataset.id;
                openModal(deleteConfirmDepenseModal);
            });
        });

        // --- Gestion générale des fermetures de modals ---
        modalCloseButtons.forEach(button => {
            button.addEventListener('click', function () {
                closeModal(depenseModal);
                closeModal(deleteConfirmDepenseModal);
            });
        });

        window.addEventListener('click', function (event) {
            if (event.target == depenseModal) closeModal(depenseModal);
            if (event.target == deleteConfirmDepenseModal) closeModal(deleteConfirmDepenseModal);
        });

        if (cancelDeleteDepenseBtn) {
            cancelDeleteDepenseBtn.addEventListener('click', function () {
                closeModal(deleteConfirmDepenseModal);
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
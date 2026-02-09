<?php
// modules/magasins/gerer_magasins.php
session_start();

// Inclure les fichiers nécessaires
include '../../db_connect.php';
include '../../includes/permissions_helper.php'; // Helper de permissions
include '../../includes/header.php'; // Inclut le header avec le début du HTML
?>

<style>
    /* Styles spécifiques pour cette page si nécessaire, en complément de style.css */
    /* Par exemple, ajustements pour les modals ou les tables */
</style>

<?php
// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("location: " . BASE_URL . "public/login.php");
    exit;
}

// Vérifier les permissions d'accès au module magasins (lecture)
if (!hasPermission($conn, 'magasins', 'read')) {
    header("location: " . BASE_URL . "dashboard.php?error=access_denied");
    exit;
}

// Définir les permissions pour l'affichage conditionnel des boutons
$can_create = hasPermission($conn, 'magasins', 'create');
$can_update = hasPermission($conn, 'magasins', 'update');
$can_delete = hasPermission($conn, 'magasins', 'delete');

$page_title = "Gestion des Magasins";

$message = ''; // Pour afficher les messages de succès ou d'erreur

// --- Logique de traitement des formulaires (Ajout, Modification, Suppression) ---

// Gérer l'ajout ou la modification d'un magasin
if ($_SERVER["REQUEST_METHOD"] == "POST" && (isset($_POST['add_magasin']) || isset($_POST['edit_magasin']))) {
    // Vérifier les permissions avant traitement
    if (isset($_POST['add_magasin']) && !$can_create) {
        $message = '<div class="alert alert-error">Vous n\'avez pas la permission d\'ajouter un magasin.</div>';
        goto skip_magasin_action;
    }
    if (isset($_POST['edit_magasin']) && !$can_update) {
        $message = '<div class="alert alert-error">Vous n\'avez pas la permission de modifier un magasin.</div>';
        goto skip_magasin_action;
    }

    $nom = sanitize_input($_POST['nom']);
    $adresse = sanitize_input($_POST['adresse']);
    $telephone = sanitize_input($_POST['telephone']);
    $email = sanitize_input($_POST['email']);

    if (isset($_POST['add_magasin'])) {
        // Ajout
        $sql = "INSERT INTO magasins (nom, adresse, telephone, email) VALUES (?, ?, ?, ?)";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "ssss", $nom, $adresse, $telephone, $email);
            if (mysqli_stmt_execute($stmt)) {
                $message = '<div class="alert alert-success">Magasin ajouté avec succès !</div>';
            } else {
                $message = '<div class="alert alert-error">Erreur lors de l\'ajout du magasin : ' . mysqli_error($conn) . '</div>';
            }
            mysqli_stmt_close($stmt);
        }
    } elseif (isset($_POST['edit_magasin'])) {
        // Modification
        $magasin_id = sanitize_input($_POST['magasin_id']);
        $sql = "UPDATE magasins SET nom = ?, adresse = ?, telephone = ?, email = ? WHERE id = ?";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "ssssi", $nom, $adresse, $telephone, $email, $magasin_id);
            if (mysqli_stmt_execute($stmt)) {
                $message = '<div class="alert alert-success">Magasin modifié avec succès !</div>';
            } else {
                $message = '<div class="alert alert-error">Erreur lors de la modification du magasin : ' . mysqli_error($conn) . '</div>';
            }
            mysqli_stmt_close($stmt);
        }
    }
}

skip_magasin_action:

// Gérer la suppression d'un magasin
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_magasin'])) {
    // Vérifier la permission de suppression
    if (!$can_delete) {
        $message = '<div class="alert alert-error">Vous n\'avez pas la permission de supprimer un magasin.</div>';
        goto skip_delete_magasin;
    }

    $magasin_id = sanitize_input($_POST['magasin_id']);
    $sql = "DELETE FROM magasins WHERE id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $magasin_id);
        if (mysqli_stmt_execute($stmt)) {
            $message = '<div class="alert alert-success">Magasin supprimé avec succès !</div>';
        } else {
            $message = '<div class="alert alert-error">Erreur lors de la suppression du magasin : ' . mysqli_error($conn) . '</div>';
        }
        mysqli_stmt_close($stmt);
    }
}

skip_delete_magasin:

// --- Logique de listage (Pagination, Recherche, Tri) ---

$limit = 10; // Nombre d'éléments par page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$search_query = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$sort_by = isset($_GET['sort_by']) ? sanitize_input($_GET['sort_by']) : 'nom';
$sort_order = isset($_GET['sort_order']) && in_array(strtoupper($_GET['sort_order']), ['ASC', 'DESC']) ? $_GET['sort_order'] : 'ASC';

// Construction de la clause WHERE pour la recherche
$where_clause = '';
if (!empty($search_query)) {
    $where_clause = " WHERE nom LIKE '%" . $search_query . "%' OR adresse LIKE '%" . $search_query . "%' OR telephone LIKE '%" . $search_query . "%' OR email LIKE '%" . $search_query . "%'";
}

// Requête pour le nombre total de magasins (pour la pagination)
$count_sql = "SELECT COUNT(id) AS total FROM magasins" . $where_clause;
$count_result = mysqli_query($conn, $count_sql);
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $limit);

// Requête pour récupérer les magasins avec pagination, recherche et tri
$sql = "SELECT id, nom, adresse, telephone, email FROM magasins" . $where_clause . " ORDER BY " . $sort_by . " " . $sort_order . " LIMIT ? OFFSET ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $limit, $offset);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$magasins = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

?>

<div class="flex h-screen bg-gray-100">
    <!-- Sidebar (incluse via header.php, mais nous devons la rendre visible ici pour le contexte du dashboard) -->
    <?php include '../../includes/sidebar_dashboard.php'; // Un fichier à créer pour la sidebar du dashboard ?>

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
                            <button id="addMagasinBtn"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md shadow-md transition duration-300 mb-4 md:mb-0">
                                <i class="fas fa-plus-circle mr-2"></i> Ajouter un Magasin
                            </button>
                        <?php endif; ?>
                        <form method="GET" action="" class="flex flex-col md:flex-row gap-4 w-full md:w-auto">
                            <input type="text" name="search" placeholder="Rechercher..."
                                value="<?php echo htmlspecialchars($search_query); ?>"
                                class="flex-grow shadow appearance-none border rounded-md py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            <select name="sort_by"
                                class="shadow appearance-none border rounded-md py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                <option value="nom" <?php echo $sort_by == 'nom' ? 'selected' : ''; ?>>Trier par Nom
                                </option>
                                <option value="adresse" <?php echo $sort_by == 'adresse' ? 'selected' : ''; ?>>Trier par
                                    Adresse</option>
                                <option value="date_creation" <?php echo $sort_by == 'date_creation' ? 'selected' : ''; ?>>Trier par Date</option>
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

                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nom</th>
                                    <th>Adresse</th>
                                    <th>Téléphone</th>
                                    <th>Email</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($magasins)): ?>
                                    <?php foreach ($magasins as $magasin): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($magasin['id']); ?></td>
                                            <td><?php echo htmlspecialchars($magasin['nom']); ?></td>
                                            <td><?php echo htmlspecialchars($magasin['adresse']); ?></td>
                                            <td><?php echo htmlspecialchars($magasin['telephone']); ?></td>
                                            <td><?php echo htmlspecialchars($magasin['email']); ?></td>
                                            <td class="action-buttons">
                                                <?php if ($can_update): ?>
                                                    <button class="btn btn-edit edit-magasin-btn"
                                                        data-id="<?php echo htmlspecialchars($magasin['id']); ?>"
                                                        data-nom="<?php echo htmlspecialchars($magasin['nom']); ?>"
                                                        data-adresse="<?php echo htmlspecialchars($magasin['adresse']); ?>"
                                                        data-telephone="<?php echo htmlspecialchars($magasin['telephone']); ?>"
                                                        data-email="<?php echo htmlspecialchars($magasin['email']); ?>">
                                                        <i class="fas fa-edit"></i> Modifier
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($can_delete): ?>
                                                    <button class="btn btn-delete delete-magasin-btn"
                                                        data-id="<?php echo htmlspecialchars($magasin['id']); ?>">
                                                        <i class="fas fa-trash-alt"></i> Supprimer
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">Aucun magasin trouvé.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="flex flex-col sm:flex-row justify-between items-center mt-4">
                        <p class="text-gray-600 mb-2 sm:mb-0">Affichage de
                            <?php echo min($limit, $total_records - $offset); ?> sur <?php echo $total_records; ?>
                            magasins
                        </p>
                        <div class="flex flex-wrap justify-center sm:justify-start gap-2">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search_query); ?>&sort_by=<?php echo urlencode($sort_by); ?>&sort_order=<?php echo urlencode($sort_order); ?>"
                                    class="px-3 py-1 text-sm bg-gray-200 rounded-md hover:bg-gray-300">Précédent</a>
                            <?php endif; ?>

                            <?php
                            // Logique pour afficher un nombre limité de pages autour de la page actuelle
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);

                            if ($start_page > 1) {
                                echo '<a href="?page=1&search=' . urlencode($search_query) . '&sort_by=' . urlencode($sort_by) . '&sort_order=' . urlencode($sort_order) . '" class="px-3 py-1 text-sm rounded-md bg-gray-200 hover:bg-gray-300">1</a>';
                                if ($start_page > 2) {
                                    echo '<span class="px-3 py-1 text-sm">...</span>';
                                }
                            }

                            for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search_query); ?>&sort_by=<?php echo urlencode($sort_by); ?>&sort_order=<?php echo urlencode($sort_order); ?>"
                                    class="px-3 py-1 text-sm rounded-md <?php echo ($i == $page) ? 'bg-blue-600 text-white' : 'bg-gray-200 hover:bg-gray-300'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor;

                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<span class="px-3 py-1 text-sm">...</span>';
                                }
                                echo '<a href="?page=' . $total_pages . '&search=' . urlencode($search_query) . '&sort_by=' . urlencode($sort_by) . '&sort_order=' . urlencode($sort_order) . '" class="px-3 py-1 text-sm rounded-md bg-gray-200 hover:bg-gray-300">' . $total_pages . '</a>';
                            }
                            ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search_query); ?>&sort_by=<?php echo urlencode($sort_by); ?>&sort_order=<?php echo urlencode($sort_order); ?>"
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

<!-- Modal d'ajout/modification de magasin -->
<div id="magasinModal" class="modal hidden">
    <div class="modal-content">
        <span class="modal-close-button">&times;</span>
        <h2 id="modalTitle" class="text-2xl font-bold text-gray-800 mb-4">Ajouter un Magasin</h2>
        <form id="magasinForm" method="POST" action="">
            <input type="hidden" id="magasinId" name="magasin_id">
            <div class="form-group">
                <label for="nom">Nom du Magasin:</label>
                <input type="text" id="nom" name="nom" required class="w-full p-2 border border-gray-300 rounded-md">
            </div>
            <div class="form-group">
                <label for="adresse">Adresse:</label>
                <input type="text" id="adresse" name="adresse" class="w-full p-2 border border-gray-300 rounded-md">
            </div>
            <div class="form-group">
                <label for="telephone">Téléphone:</label>
                <input type="text" id="telephone" name="telephone" class="w-full p-2 border border-gray-300 rounded-md">
            </div>
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" class="w-full p-2 border border-gray-300 rounded-md">
            </div>
            <button type="submit" id="submitMagasinBtn" name="add_magasin" class="btn-primary mt-4 w-full">
                <i class="fas fa-save mr-2"></i> Enregistrer
            </button>
        </form>
    </div>
</div>

<!-- Modal de confirmation de suppression -->
<div id="deleteConfirmModal" class="modal hidden">
    <div class="modal-content max-w-sm text-center">
        <span class="modal-close-button">&times;</span>
        <h2 class="text-2xl font-bold text-gray-800 mb-4">Confirmer la Suppression</h2>
        <p class="text-gray-700 mb-6">Êtes-vous sûr de vouloir supprimer ce magasin ? Cette action est irréversible.</p>
        <form id="deleteMagasinForm" method="POST" action="">
            <input type="hidden" id="deleteMagasinId" name="magasin_id">
            <div class="flex justify-center space-x-4">
                <button type="button" id="cancelDeleteBtn"
                    class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded-md transition duration-300">
                    Annuler
                </button>
                <button type="submit" name="delete_magasin"
                    class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-md transition duration-300">
                    Supprimer <i class="fas fa-trash-alt ml-2"></i>
                </button>
            </div>
        </form>
    </div>
</div>



<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Initialiser les modals
        const magasinModal = document.getElementById('magasinModal');
        const addMagasinBtn = document.getElementById('addMagasinBtn');
        const modalCloseButtons = document.querySelectorAll('.modal-close-button');
        const magasinForm = document.getElementById('magasinForm');
        const modalTitle = document.getElementById('modalTitle');
        const submitMagasinBtn = document.getElementById('submitMagasinBtn');
        const magasinIdInput = document.getElementById('magasinId');
        const nomInput = document.getElementById('nom');
        const adresseInput = document.getElementById('adresse');
        const telephoneInput = document.getElementById('telephone');
        const emailInput = document.getElementById('email');

        const deleteConfirmModal = document.getElementById('deleteConfirmModal');
        const deleteMagasinIdInput = document.getElementById('deleteMagasinId');
        const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');

        // Fonction pour ouvrir un modal
        function openModal(modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex'); // Pour centrer avec flexbox
        }

        // Fonction pour fermer un modal
        function closeModal(modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        // Ouvrir le modal d'ajout
        if (addMagasinBtn) {
            addMagasinBtn.addEventListener('click', function () {
                modalTitle.textContent = 'Ajouter un Magasin';
                submitMagasinBtn.name = 'add_magasin';
                submitMagasinBtn.innerHTML = '<i class="fas fa-save mr-2"></i> Enregistrer';
                magasinForm.reset(); // Réinitialiser le formulaire
                magasinIdInput.value = ''; // S'assurer que l'ID est vide pour l'ajout
                openModal(magasinModal);
            });
        }

        // Ouvrir le modal de modification
        document.querySelectorAll('.edit-magasin-btn').forEach(button => {
            button.addEventListener('click', function () {
                modalTitle.textContent = 'Modifier un Magasin';
                submitMagasinBtn.name = 'edit_magasin';
                submitMagasinBtn.innerHTML = '<i class="fas fa-edit mr-2"></i> Mettre à jour';

                // Remplir le formulaire avec les données existantes
                magasinIdInput.value = this.dataset.id;
                nomInput.value = this.dataset.nom;
                adresseInput.value = this.dataset.adresse;
                telephoneInput.value = this.dataset.telephone;
                emailInput.value = this.dataset.email;

                openModal(magasinModal);
            });
        });

        // Ouvrir le modal de suppression
        document.querySelectorAll('.delete-magasin-btn').forEach(button => {
            button.addEventListener('click', function () {
                deleteMagasinIdInput.value = this.dataset.id;
                openModal(deleteConfirmModal);
            });
        });

        // Fermer les modals via les boutons de fermeture
        modalCloseButtons.forEach(button => {
            button.addEventListener('click', function () {
                closeModal(magasinModal);
                closeModal(deleteConfirmModal);
            });
        });

        // Fermer les modals si l'utilisateur clique en dehors du contenu
        window.addEventListener('click', function (event) {
            if (event.target == magasinModal) {
                closeModal(magasinModal);
            }
            if (event.target == deleteConfirmModal) {
                closeModal(deleteConfirmModal);
            }
        });

        // Annuler la suppression
        if (cancelDeleteBtn) {
            cancelDeleteBtn.addEventListener('click', function () {
                closeModal(deleteConfirmModal);
            });
        }

        // Gestion du menu mobile (pour le dashboard, copié/adapté de script.js si nécessaire)
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

        // Fermer la sidebar si on clique en dehors sur les écrans mobiles
        document.addEventListener('click', function (event) {
            if (window.innerWidth < 768 && !sidebar.contains(event.target) && sidebarToggleOpen && !sidebarToggleOpen.contains(event.target) && sidebar.classList.contains('translate-x-0')) {
                closeSidebar();
            }
        });

        // Optionnel: Fermer la sidebar quand un lien est cliqué sur mobile
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
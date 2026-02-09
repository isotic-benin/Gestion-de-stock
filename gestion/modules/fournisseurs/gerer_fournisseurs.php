<?php
// modules/fournisseurs/gerer_fournisseurs.php
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

// Vérifier les permissions d'accès au module fournisseurs (lecture)
if (!hasPermission($conn, 'fournisseurs', 'read')) {
    header("location: " . BASE_URL . "dashboard.php?error=access_denied");
    exit;
}

// Définir les permissions pour l'affichage conditionnel des boutons
$can_create = hasPermission($conn, 'fournisseurs', 'create');
$can_update = hasPermission($conn, 'fournisseurs', 'update');
$can_delete = hasPermission($conn, 'fournisseurs', 'delete');

$page_title = "Gestion des Fournisseurs";

$message = ''; // Pour afficher les messages de succès ou d'erreur

// --- Logique de traitement des formulaires (CRUD Fournisseurs) ---

// Gérer l'ajout ou la modification d'un fournisseur
if ($_SERVER["REQUEST_METHOD"] == "POST" && (isset($_POST['add_fournisseur']) || isset($_POST['edit_fournisseur']))) {
    // Vérifier les permissions avant traitement
    if (isset($_POST['add_fournisseur']) && !$can_create) {
        $message = '<div class="alert alert-error">Vous n\'avez pas la permission d\'ajouter un fournisseur.</div>';
        goto skip_fournisseur_action;
    }
    if (isset($_POST['edit_fournisseur']) && !$can_update) {
        $message = '<div class="alert alert-error">Vous n\'avez pas la permission de modifier un fournisseur.</div>';
        goto skip_fournisseur_action;
    }

    $nom = sanitize_input($_POST['nom']);
    $contact_personne = sanitize_input($_POST['contact_personne']);
    $telephone = sanitize_input($_POST['telephone']);
    $email = sanitize_input($_POST['email']);
    $adresse = sanitize_input($_POST['adresse']);

    if (isset($_POST['add_fournisseur'])) {
        // Ajout
        $sql = "INSERT INTO fournisseurs (nom, contact_personne, telephone, email, adresse) VALUES (?, ?, ?, ?, ?)";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "sssss", $nom, $contact_personne, $telephone, $email, $adresse);
            if (mysqli_stmt_execute($stmt)) {
                $message = '<div class="alert alert-success">Fournisseur ajouté avec succès !</div>';
            } else {
                $message = '<div class="alert alert-error">Erreur lors de l\'ajout du fournisseur : ' . mysqli_error($conn) . '</div>';
            }
            mysqli_stmt_close($stmt);
        }
    } elseif (isset($_POST['edit_fournisseur'])) {
        // Modification
        $fournisseur_id = sanitize_input($_POST['fournisseur_id']);
        $sql = "UPDATE fournisseurs SET nom = ?, contact_personne = ?, telephone = ?, email = ?, adresse = ? WHERE id = ?";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "sssssi", $nom, $contact_personne, $telephone, $email, $adresse, $fournisseur_id);
            if (mysqli_stmt_execute($stmt)) {
                $message = '<div class="alert alert-success">Fournisseur modifié avec succès !</div>';
            } else {
                $message = '<div class="alert alert-error">Erreur lors de la modification du fournisseur : ' . mysqli_error($conn) . '</div>';
            }
            mysqli_stmt_close($stmt);
        }
    }
}

skip_fournisseur_action:

// Gérer la suppression d'un fournisseur
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_fournisseur'])) {
    // Vérifier la permission de suppression
    if (!$can_delete) {
        $message = '<div class="alert alert-error">Vous n\'avez pas la permission de supprimer un fournisseur.</div>';
        goto skip_delete_fournisseur;
    }

    $fournisseur_id = sanitize_input($_POST['fournisseur_id']);
    $sql = "DELETE FROM fournisseurs WHERE id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $fournisseur_id);
        if (mysqli_stmt_execute($stmt)) {
            $message = '<div class="alert alert-success">Fournisseur supprimé avec succès !</div>';
        } else {
            $message = '<div class="alert alert-error">Erreur lors de la suppression du fournisseur : ' . mysqli_error($conn) . '</div>';
        }
        mysqli_stmt_close($stmt);
    }
}

skip_delete_fournisseur:

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
    $where_clause = " WHERE nom LIKE '%" . $search_query . "%' OR contact_personne LIKE '%" . $search_query . "%' OR telephone LIKE '%" . $search_query . "%' OR email LIKE '%" . $search_query . "%' OR adresse LIKE '%" . $search_query . "%'";
}

// Requête pour le nombre total de fournisseurs (pour la pagination)
$count_sql = "SELECT COUNT(id) AS total FROM fournisseurs" . $where_clause;
$count_result = mysqli_query($conn, $count_sql);
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $limit);

// Requête pour récupérer les fournisseurs avec pagination, recherche et tri
$sql = "SELECT id, nom, contact_personne, telephone, email, adresse FROM fournisseurs" . $where_clause . " ORDER BY " . $sort_by . " " . $sort_order . " LIMIT ? OFFSET ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $limit, $offset);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$fournisseurs = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

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
                            <button id="addFournisseurBtn"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md shadow-md transition duration-300 mb-4 md:mb-0">
                                <i class="fas fa-truck-loading mr-2"></i> Ajouter un Fournisseur
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
                                <option value="contact_personne" <?php echo $sort_by == 'contact_personne' ? 'selected' : ''; ?>>Trier par Contact</option>
                                <option value="email" <?php echo $sort_by == 'email' ? 'selected' : ''; ?>>Trier par Email
                                </option>
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
                                    <th>Personne Contact</th>
                                    <th>Téléphone</th>
                                    <th>Email</th>
                                    <th>Adresse</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($fournisseurs)): ?>
                                    <?php foreach ($fournisseurs as $fournisseur): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($fournisseur['id']); ?></td>
                                            <td><?php echo htmlspecialchars($fournisseur['nom']); ?></td>
                                            <td><?php echo htmlspecialchars($fournisseur['contact_personne'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($fournisseur['telephone'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($fournisseur['email'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($fournisseur['adresse'] ?? ''); ?></td>
                                            <td class="action-buttons flex-wrap">
                                                <?php if ($can_update): ?>
                                                    <button class="btn btn-edit edit-fournisseur-btn"
                                                        data-id="<?php echo htmlspecialchars($fournisseur['id']); ?>"
                                                        data-nom="<?php echo htmlspecialchars($fournisseur['nom']); ?>"
                                                        data-contact_personne="<?php echo htmlspecialchars($fournisseur['contact_personne'] ?? ''); ?>"
                                                        data-telephone="<?php echo htmlspecialchars($fournisseur['telephone'] ?? ''); ?>"
                                                        data-email="<?php echo htmlspecialchars($fournisseur['email'] ?? ''); ?>"
                                                        data-adresse="<?php echo htmlspecialchars($fournisseur['adresse'] ?? ''); ?>">
                                                        <i class="fas fa-edit"></i> Modifier
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($can_delete): ?>
                                                    <button class="btn btn-delete delete-fournisseur-btn"
                                                        data-id="<?php echo htmlspecialchars($fournisseur['id']); ?>">
                                                        <i class="fas fa-trash-alt"></i> Supprimer
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4">Aucun fournisseur trouvé.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="flex flex-col sm:flex-row justify-between items-center mt-4">
                        <p class="text-gray-600 mb-2 sm:mb-0">Affichage de
                            <?php echo min($limit, $total_records - $offset); ?> sur <?php echo $total_records; ?>
                            fournisseurs
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

<!-- Modal d'ajout/modification de fournisseur -->
<div id="fournisseurModal" class="modal hidden">
    <div class="modal-content max-w-lg">
        <span class="modal-close-button">&times;</span>
        <h2 id="modalTitleFournisseur" class="text-2xl font-bold text-gray-800 mb-4">Ajouter un Fournisseur</h2>
        <form id="fournisseurForm" method="POST" action="">
            <input type="hidden" id="fournisseurId" name="fournisseur_id">
            <div class="form-group">
                <label for="nom_fournisseur">Nom du Fournisseur:</label>
                <input type="text" id="nom_fournisseur" name="nom" required
                    class="w-full p-2 border border-gray-300 rounded-md">
            </div>
            <div class="form-group">
                <label for="contact_personne">Personne Contact:</label>
                <input type="text" id="contact_personne" name="contact_personne"
                    class="w-full p-2 border border-gray-300 rounded-md">
            </div>
            <div class="form-group">
                <label for="telephone_fournisseur">Téléphone:</label>
                <input type="text" id="telephone_fournisseur" name="telephone"
                    class="w-full p-2 border border-gray-300 rounded-md">
            </div>
            <div class="form-group">
                <label for="email_fournisseur">Email:</label>
                <input type="email" id="email_fournisseur" name="email"
                    class="w-full p-2 border border-gray-300 rounded-md">
            </div>
            <div class="form-group">
                <label for="adresse_fournisseur">Adresse:</label>
                <textarea id="adresse_fournisseur" name="adresse" rows="3"
                    class="w-full p-2 border border-gray-300 rounded-md"></textarea>
            </div>
            <button type="submit" id="submitFournisseurBtn" name="add_fournisseur" class="btn-primary mt-4 w-full">
                <i class="fas fa-save mr-2"></i> Enregistrer
            </button>
        </form>
    </div>
</div>

<!-- Modal de confirmation de suppression -->
<div id="deleteConfirmFournisseurModal" class="modal hidden">
    <div class="modal-content max-w-sm text-center">
        <span class="modal-close-button">&times;</span>
        <h2 class="text-2xl font-bold text-gray-800 mb-4">Confirmer la Suppression</h2>
        <p class="text-gray-700 mb-6">Êtes-vous sûr de vouloir supprimer ce fournisseur ? Cette action est irréversible.
        </p>
        <form id="deleteFournisseurForm" method="POST" action="">
            <input type="hidden" id="deleteFournisseurId" name="fournisseur_id">
            <div class="flex justify-center space-x-4">
                <button type="button" id="cancelDeleteFournisseurBtn"
                    class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded-md transition duration-300">
                    Annuler
                </button>
                <button type="submit" name="delete_fournisseur"
                    class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-md transition duration-300">
                    Supprimer <i class="fas fa-trash-alt ml-2"></i>
                </button>
            </div>
        </form>
    </div>
</div>



<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Refs pour le modal d'ajout/modification de fournisseur
        const fournisseurModal = document.getElementById('fournisseurModal');
        const addFournisseurBtn = document.getElementById('addFournisseurBtn');
        const modalCloseButtons = document.querySelectorAll('.modal-close-button');
        const fournisseurForm = document.getElementById('fournisseurForm');
        const modalTitleFournisseur = document.getElementById('modalTitleFournisseur');
        const submitFournisseurBtn = document.getElementById('submitFournisseurBtn');

        const fournisseurIdInput = document.getElementById('fournisseurId');
        const nomFournisseurInput = document.getElementById('nom_fournisseur');
        const contactPersonneInput = document.getElementById('contact_personne');
        const telephoneFournisseurInput = document.getElementById('telephone_fournisseur');
        const emailFournisseurInput = document.getElementById('email_fournisseur');
        const adresseFournisseurInput = document.getElementById('adresse_fournisseur');

        // Refs pour le modal de suppression
        const deleteConfirmFournisseurModal = document.getElementById('deleteConfirmFournisseurModal');
        const deleteFournisseurIdInput = document.getElementById('deleteFournisseurId');
        const cancelDeleteFournisseurBtn = document.getElementById('cancelDeleteFournisseurBtn');

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

        // --- Gestion des Modals de Fournisseur ---
        if (addFournisseurBtn) {
            addFournisseurBtn.addEventListener('click', function () {
                modalTitleFournisseur.textContent = 'Ajouter un Fournisseur';
                submitFournisseurBtn.name = 'add_fournisseur';
                submitFournisseurBtn.innerHTML = '<i class="fas fa-save mr-2"></i> Enregistrer';
                fournisseurForm.reset();
                fournisseurIdInput.value = '';
                openModal(fournisseurModal);
            });
        }

        document.querySelectorAll('.edit-fournisseur-btn').forEach(button => {
            button.addEventListener('click', function () {
                modalTitleFournisseur.textContent = 'Modifier le Fournisseur';
                submitFournisseurBtn.name = 'edit_fournisseur';
                submitFournisseurBtn.innerHTML = '<i class="fas fa-edit mr-2"></i> Mettre à jour';

                fournisseurIdInput.value = this.dataset.id;
                nomFournisseurInput.value = this.dataset.nom;
                contactPersonneInput.value = this.dataset.contact_personne;
                telephoneFournisseurInput.value = this.dataset.telephone;
                emailFournisseurInput.value = this.dataset.email;
                adresseFournisseurInput.value = this.dataset.adresse;

                openModal(fournisseurModal);
            });
        });

        document.querySelectorAll('.delete-fournisseur-btn').forEach(button => {
            button.addEventListener('click', function () {
                deleteFournisseurIdInput.value = this.dataset.id;
                openModal(deleteConfirmFournisseurModal);
            });
        });

        // --- Gestion générale des fermetures de modals ---
        modalCloseButtons.forEach(button => {
            button.addEventListener('click', function () {
                closeModal(fournisseurModal);
                closeModal(deleteConfirmFournisseurModal);
            });
        });

        window.addEventListener('click', function (event) {
            if (event.target == fournisseurModal) closeModal(fournisseurModal);
            if (event.target == deleteConfirmFournisseurModal) closeModal(deleteConfirmFournisseurModal);
        });

        if (cancelDeleteFournisseurBtn) {
            cancelDeleteFournisseurBtn.addEventListener('click', function () {
                closeModal(deleteConfirmFournisseurModal);
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
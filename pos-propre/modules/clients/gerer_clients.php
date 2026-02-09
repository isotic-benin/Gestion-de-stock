<?php
// modules/clients/gerer_clients.php
session_start();

// Inclure les fichiers nécessaires
include '../../db_connect.php'; // Ceci inclura config.php
include '../../includes/header.php'; // Inclut le header avec le début du HTML

// Vérifier si l'utilisateur est connecté et a le rôle approprié
// Les rôles autorisés pour la gestion des clients sont Administrateur, Caissier, Vendeur
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !in_array($_SESSION['role'], ['Administrateur', 'Caissier', 'Vendeur'])) {
    header("location: " . BASE_URL . "public/login.php"); // Utilisation de BASE_URL
    exit;
}

$page_title = "Gestion des Clients";

$message = ''; // Pour afficher les messages de succès ou d'erreur

// --- Logique de traitement des formulaires (Ajout, Modification, Suppression) ---

// Gérer l'ajout ou la modification d'un client
if ($_SERVER["REQUEST_METHOD"] == "POST" && (isset($_POST['add_client']) || isset($_POST['edit_client']))) {
    $nom = sanitize_input($_POST['nom']);
    $prenom = sanitize_input($_POST['prenom']);
    $telephone = !empty($_POST['telephone']) ? sanitize_input($_POST['telephone']) : null; // Téléphone est facultatif
    $email = !empty($_POST['email']) ? sanitize_input($_POST['email']) : null; // Email est facultatif
    $adresse = sanitize_input($_POST['adresse']);

    $is_edit = isset($_POST['edit_client']);
    $client_id = $is_edit ? sanitize_input($_POST['client_id']) : null;

    // Validation de l'unicité du téléphone (si fourni) et de l'email (si fourni)
    $errors = [];

    // Vérifier l'unicité du téléphone si fourni
    if ($telephone !== null) {
        $sql_check_phone = "SELECT id FROM clients WHERE telephone = ?";
        if ($is_edit) {
            $sql_check_phone .= " AND id != ?";
        }
        if ($stmt_check_phone = mysqli_prepare($conn, $sql_check_phone)) {
            if ($is_edit) {
                mysqli_stmt_bind_param($stmt_check_phone, "si", $telephone, $client_id);
            } else {
                mysqli_stmt_bind_param($stmt_check_phone, "s", $telephone);
            }
            mysqli_stmt_execute($stmt_check_phone);
            mysqli_stmt_store_result($stmt_check_phone);
            if (mysqli_stmt_num_rows($stmt_check_phone) > 0) {
                $errors[] = "Le numéro de téléphone est déjà utilisé par un autre client.";
            }
            mysqli_stmt_close($stmt_check_phone);
        }
    }

    // Vérifier l'unicité de l'email si fourni
    if ($email !== null) {
        $sql_check_email = "SELECT id FROM clients WHERE email = ?";
        if ($is_edit) {
            $sql_check_email .= " AND id != ?";
        }
        if ($stmt_check_email = mysqli_prepare($conn, $sql_check_email)) {
            if ($is_edit) {
                mysqli_stmt_bind_param($stmt_check_email, "si", $email, $client_id);
            } else {
                mysqli_stmt_bind_param($stmt_check_email, "s", $email);
            }
            mysqli_stmt_execute($stmt_check_email);
            mysqli_stmt_store_result($stmt_check_email);
            if (mysqli_stmt_num_rows($stmt_check_email) > 0) {
                $errors[] = "L'adresse e-mail est déjà utilisée par un autre client.";
            }
            mysqli_stmt_close($stmt_check_email);
        }
    }

    if (empty($errors)) {
        if (isset($_POST['add_client'])) {
            // Ajout
            $sql = "INSERT INTO clients (nom, prenom, telephone, email, adresse) VALUES (?, ?, ?, ?, ?)";
            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "sssss", $nom, $prenom, $telephone, $email, $adresse);
                if (mysqli_stmt_execute($stmt)) {
                    $message = '<div class="alert alert-success">Client ajouté avec succès !</div>';
                } else {
                    $message = '<div class="alert alert-error">Erreur lors de l\'ajout du client : ' . mysqli_error($conn) . '</div>';
                }
                mysqli_stmt_close($stmt);
            }
        } elseif (isset($_POST['edit_client'])) {
            // Modification
            $sql = "UPDATE clients SET nom = ?, prenom = ?, telephone = ?, email = ?, adresse = ? WHERE id = ?";
            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "sssssi", $nom, $prenom, $telephone, $email, $adresse, $client_id);
                if (mysqli_stmt_execute($stmt)) {
                    $message = '<div class="alert alert-success">Client modifié avec succès !</div>';
                } else {
                    $message = '<div class="alert alert-error">Erreur lors de la modification du client : ' . mysqli_error($conn) . '</div>';
                }
                mysqli_stmt_close($stmt);
            }
        }
    } else {
        // Afficher les erreurs dans la modale
        $message = '<div class="alert alert-error">' . implode('<br>', $errors) . '</div>';
        // Pour que la modale reste ouverte avec les erreurs, nous devons passer les données et un flag
        // Cela nécessitera un peu de JavaScript pour réouvrir la modale et afficher le message
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                const clientModal = document.getElementById('clientModal');
                const modalTitleClient = document.getElementById('modalTitleClient');
                const submitClientBtn = document.getElementById('submitClientBtn');
                const clientIdInput = document.getElementById('clientId');
                const nomClientInput = document.getElementById('nom_client');
                const prenomClientInput = document.getElementById('prenom_client');
                const telephoneClientInput = document.getElementById('telephone_client');
                const emailClientInput = document.getElementById('email_client');
                const adresseClientInput = document.getElementById('adresse_client');
                const modalMessageContainer = document.getElementById('modal-message-container');

                modalMessageContainer.innerHTML = `" . $message . "`;
                modalMessageContainer.classList.remove('hidden');

                modalTitleClient.textContent = '" . ($is_edit ? "Modifier le Client" : "Ajouter un Client") . "';
                submitClientBtn.name = '" . ($is_edit ? "edit_client" : "add_client") . "';
                submitClientBtn.innerHTML = '" . ($is_edit ? "<i class=\"fas fa-edit mr-2\"></i> Mettre à jour" : "<i class=\"fas fa-save mr-2\"></i> Enregistrer") . "';

                clientIdInput.value = '" . ($client_id ?? '') . "';
                nomClientInput.value = '" . htmlspecialchars($nom) . "';
                prenomClientInput.value = '" . htmlspecialchars($prenom) . "';
                telephoneClientInput.value = '" . htmlspecialchars($telephone ?? '') . "';
                emailClientInput.value = '" . htmlspecialchars($email ?? '') . "';
                adresseClientInput.value = '" . htmlspecialchars($adresse) . "';

                clientModal.classList.remove('hidden');
                clientModal.classList.add('flex');
            });
        </script>";
        $message = ''; // Vider le message PHP car il sera géré par JS dans la modale
    }
}

// Gérer la suppression d'un client
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_client'])) {
    $client_id = sanitize_input($_POST['client_id']);
    $sql = "DELETE FROM clients WHERE id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $client_id);
        if (mysqli_stmt_execute($stmt)) {
            $message = '<div class="alert alert-success">Client supprimé avec succès !</div>';
        } else {
            $message = '<div class="alert alert-error">Erreur lors de la suppression du client : ' . mysqli_error($conn) . '</div>';
        }
        mysqli_stmt_close($stmt);
    }
}

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
    $where_clause = " WHERE nom LIKE '%" . $search_query . "%' OR prenom LIKE '%" . $search_query . "%' OR telephone LIKE '%" . $search_query . "%' OR email LIKE '%" . $search_query . "%' OR adresse LIKE '%" . $search_query . "%'";
}

// Requête pour le nombre total de clients (pour la pagination)
$count_sql = "SELECT COUNT(id) AS total FROM clients" . $where_clause;
$count_result = mysqli_query($conn, $count_sql);
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $limit);

// Requête pour récupérer les clients avec pagination, recherche et tri
$sql = "SELECT id, nom, prenom, telephone, email, adresse FROM clients" . $where_clause . " ORDER BY " . $sort_by . " " . $sort_order . " LIMIT ? OFFSET ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $limit, $offset);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$clients = mysqli_fetch_all($result, MYSQLI_ASSOC);
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
                        <button id="addClientBtn"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md shadow-md transition duration-300 mb-4 md:mb-0">
                            <i class="fas fa-user-plus mr-2"></i> Ajouter un Client
                        </button>
                        <form method="GET" action="" class="flex flex-col md:flex-row gap-4 w-full md:w-auto">
                            <input type="text" name="search" placeholder="Rechercher..."
                                value="<?php echo htmlspecialchars($search_query); ?>"
                                class="flex-grow shadow appearance-none border rounded-md py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            <select name="sort_by"
                                class="shadow appearance-none border rounded-md py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                <option value="nom" <?php echo $sort_by == 'nom' ? 'selected' : ''; ?>>Trier par Nom
                                </option>
                                <option value="telephone" <?php echo $sort_by == 'telephone' ? 'selected' : ''; ?>>Trier
                                    par Téléphone</option>
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

                    <div class="table-container overflow-x-auto">
                        <!-- Added overflow-x-auto for table responsiveness -->
                        <table class="data-table min-w-full"> <!-- Added min-w-full for table responsiveness -->
                            <thead>
                                <tr>
                                    <th class="px-4 py-2">ID</th>
                                    <th class="px-4 py-2">Nom</th>
                                    <th class="px-4 py-2">Prénom</th>
                                    <th class="px-4 py-2">Téléphone</th>
                                    <th class="px-4 py-2">Email</th>
                                    <th class="px-4 py-2">Adresse</th>
                                    <th class="px-4 py-2">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($clients)): ?>
                                    <?php foreach ($clients as $client): ?>
                                        <tr>
                                            <td class="border px-4 py-2"><?php echo htmlspecialchars($client['id']); ?></td>
                                            <td class="border px-4 py-2"><?php echo htmlspecialchars($client['nom']); ?></td>
                                            <td class="border px-4 py-2"><?php echo htmlspecialchars($client['prenom']); ?></td>
                                            <td class="border px-4 py-2">
                                                <?php echo htmlspecialchars($client['telephone'] ?? ''); ?></td>
                                            <td class="border px-4 py-2"><?php echo htmlspecialchars($client['email'] ?? ''); ?>
                                            </td>
                                            <td class="border px-4 py-2"><?php echo htmlspecialchars($client['adresse']); ?>
                                            </td>
                                            <td class="border px-4 py-2 action-buttons flex flex-col md:flex-row gap-2">
                                                <!-- Adjusted for mobile stacking -->
                                                <button
                                                    class="btn btn-edit edit-client-btn bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-1 px-3 rounded-md text-sm"
                                                    data-id="<?php echo htmlspecialchars($client['id']); ?>"
                                                    data-nom="<?php echo htmlspecialchars($client['nom']); ?>"
                                                    data-prenom="<?php echo htmlspecialchars($client['prenom']); ?>"
                                                    data-telephone="<?php echo htmlspecialchars($client['telephone'] ?? ''); ?>"
                                                    data-email="<?php echo htmlspecialchars($client['email'] ?? ''); ?>"
                                                    data-adresse="<?php echo htmlspecialchars($client['adresse']); ?>">
                                                    <i class="fas fa-edit"></i> Modifier
                                                </button>
                                                <button
                                                    class="btn btn-delete delete-client-btn bg-red-500 hover:bg-red-600 text-white font-bold py-1 px-3 rounded-md text-sm"
                                                    data-id="<?php echo htmlspecialchars($client['id']); ?>">
                                                    <i class="fas fa-trash-alt"></i> Supprimer
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 border">Aucun client trouvé.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="pagination flex flex-wrap justify-center items-center gap-2 mt-6">
                        <!-- Added flex-wrap and gap for responsiveness -->
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

<!-- Modal d'ajout/modification de client -->
<div id="clientModal"
    class="modal hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full flex items-center justify-center">
    <div class="modal-content bg-white p-6 rounded-lg shadow-xl max-w-lg w-11/12 mx-auto">
        <span
            class="modal-close-button absolute top-3 right-3 text-gray-600 hover:text-gray-900 text-2xl cursor-pointer">&times;</span>
        <h2 id="modalTitleClient" class="text-2xl font-bold text-gray-800 mb-4">Ajouter un Client</h2>
        <div id="modal-message-container" class="mb-4 hidden"></div>
        <!-- Conteneur pour les messages d'erreur de la modale -->
        <form id="clientForm" method="POST" action="">
            <input type="hidden" id="clientId" name="client_id">
            <div class="form-group mb-4">
                <label for="nom_client" class="block text-gray-700 text-sm font-bold mb-2">Nom:</label>
                <input type="text" id="nom_client" name="nom" required
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div class="form-group mb-4">
                <label for="prenom_client" class="block text-gray-700 text-sm font-bold mb-2">Prénom:</label>
                <input type="text" id="prenom_client" name="prenom" required
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div class="form-group mb-4">
                <label for="telephone_client" class="block text-gray-700 text-sm font-bold mb-2">Téléphone
                    (facultatif):</label>
                <input type="text" id="telephone_client" name="telephone"
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div class="form-group mb-4">
                <label for="email_client" class="block text-gray-700 text-sm font-bold mb-2">Email (facultatif):</label>
                <input type="email" id="email_client" name="email"
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div class="form-group mb-6">
                <label for="adresse_client" class="block text-gray-700 text-sm font-bold mb-2">Adresse:</label>
                <input type="text" id="adresse_client" name="adresse"
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <button type="submit" id="submitClientBtn" name="add_client"
                class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full">
                <i class="fas fa-save mr-2"></i> Enregistrer
            </button>
        </form>
    </div>
</div>

<!-- Modal de confirmation de suppression -->
<div id="deleteConfirmClientModal"
    class="modal hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full flex items-center justify-center">
    <div class="modal-content bg-white p-6 rounded-lg shadow-xl max-w-sm w-11/12 mx-auto text-center">
        <span
            class="modal-close-button absolute top-3 right-3 text-gray-600 hover:text-gray-900 text-2xl cursor-pointer">&times;</span>
        <h2 class="text-2xl font-bold text-gray-800 mb-4">Confirmer la Suppression</h2>
        <p class="text-gray-700 mb-6">Êtes-vous sûr de vouloir supprimer ce client ? Cette action est irréversible.</p>
        <form id="deleteClientForm" method="POST" action="">
            <input type="hidden" id="deleteClientId" name="client_id">
            <div class="flex justify-center space-x-4">
                <button type="button" id="cancelDeleteClientBtn"
                    class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded-md transition duration-300">
                    Annuler
                </button>
                <button type="submit" name="delete_client"
                    class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-md transition duration-300">
                    Supprimer <i class="fas fa-trash-alt ml-2"></i>
                </button>
            </div>
        </form>
    </div>
</div>



<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Initialiser les modals pour les clients
        const clientModal = document.getElementById('clientModal');
        const addClientBtn = document.getElementById('addClientBtn');
        const modalCloseButtons = document.querySelectorAll('.modal-close-button'); // Tous les boutons de fermeture
        const clientForm = document.getElementById('clientForm');
        const modalTitleClient = document.getElementById('modalTitleClient');
        const submitClientBtn = document.getElementById('submitClientBtn');
        const modalMessageContainer = document.getElementById('modal-message-container'); // Nouveau conteneur de messages

        const clientIdInput = document.getElementById('clientId');
        const nomClientInput = document.getElementById('nom_client');
        const prenomClientInput = document.getElementById('prenom_client');
        const telephoneClientInput = document.getElementById('telephone_client');
        const emailClientInput = document.getElementById('email_client');
        const adresseClientInput = document.getElementById('adresse_client');

        const deleteConfirmClientModal = document.getElementById('deleteConfirmClientModal');
        const deleteClientIdInput = document.getElementById('deleteClientId');
        const cancelDeleteClientBtn = document.getElementById('cancelDeleteClientBtn');

        // Fonction pour ouvrir un modal (réutilisée)
        function openModal(modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        // Fonction pour fermer un modal (réutilisée)
        function closeModal(modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            modalMessageContainer.classList.add('hidden'); // Cacher le message à la fermeture
            modalMessageContainer.innerHTML = ''; // Effacer le contenu du message
        }

        // Ouvrir le modal d'ajout de client
        if (addClientBtn) {
            addClientBtn.addEventListener('click', function () {
                modalTitleClient.textContent = 'Ajouter un Client';
                submitClientBtn.name = 'add_client';
                submitClientBtn.innerHTML = '<i class="fas fa-save mr-2"></i> Enregistrer';
                clientForm.reset(); // Réinitialiser le formulaire
                clientIdInput.value = '';
                openModal(clientModal);
            });
        }

        // Ouvrir le modal de modification de client
        document.querySelectorAll('.edit-client-btn').forEach(button => {
            button.addEventListener('click', function () {
                modalTitleClient.textContent = 'Modifier le Client';
                submitClientBtn.name = 'edit_client';
                submitClientBtn.innerHTML = '<i class="fas fa-edit mr-2"></i> Mettre à jour';

                // Remplir le formulaire avec les données existantes
                clientIdInput.value = this.dataset.id;
                nomClientInput.value = this.dataset.nom;
                prenomClientInput.value = this.dataset.prenom;
                telephoneClientInput.value = this.dataset.telephone;
                emailClientInput.value = this.dataset.email;
                adresseClientInput.value = this.dataset.adresse;

                openModal(clientModal);
            });
        });

        // Ouvrir le modal de suppression de client
        document.querySelectorAll('.delete-client-btn').forEach(button => {
            button.addEventListener('click', function () {
                deleteClientIdInput.value = this.dataset.id;
                openModal(deleteConfirmClientModal);
            });
        });

        // Fermer les modals via les boutons de fermeture (pour tous les modals)
        modalCloseButtons.forEach(button => {
            button.addEventListener('click', function () {
                closeModal(clientModal);
                closeModal(deleteConfirmClientModal);
            });
        });

        // Fermer les modals si l'utilisateur clique en dehors du contenu
        window.addEventListener('click', function (event) {
            if (event.target == clientModal) {
                closeModal(clientModal);
            }
            if (event.target == deleteConfirmClientModal) {
                closeModal(deleteConfirmClientModal);
            }
        });

        // Annuler la suppression
        if (cancelDeleteClientBtn) {
            cancelDeleteClientBtn.addEventListener('click', function () {
                closeModal(deleteConfirmClientModal);
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
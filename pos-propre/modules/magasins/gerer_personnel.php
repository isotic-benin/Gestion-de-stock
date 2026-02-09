<?php
// modules/magasins/gerer_personnel.php

// Inclure les fichiers nécessaires au tout début, avant toute sortie HTML.
// On suppose que db_connect.php inclut config.php, qui démarre la session
// et définit sanitize_input et BASE_URL.
include '../../db_connect.php';

// Vérifier si l'utilisateur est connecté et a le rôle d'Administrateur
// La session est déjà active (démarrée par config.php selon le message d'erreur)
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'Administrateur') {
    header("location: " . BASE_URL . "public/login.php"); // Utilisation de BASE_URL
    exit; // Terminer l'exécution du script après la redirection
}

$page_title = "Gestion du Personnel";

$message = ''; // Pour afficher les messages de succès ou d'erreur

// --- Logique de traitement des formulaires (Ajout, Modification, Suppression) ---

// Gérer l'ajout ou la modification d'un membre du personnel
if ($_SERVER["REQUEST_METHOD"] == "POST" && (isset($_POST['add_personnel']) || isset($_POST['edit_personnel']))) {
    $nom = sanitize_input($_POST['nom']);
    $prenom = sanitize_input($_POST['prenom']);
    $nom_utilisateur = sanitize_input($_POST['nom_utilisateur']);
    $role = sanitize_input($_POST['role']);
    $email = sanitize_input($_POST['email']);
    $telephone = sanitize_input($_POST['telephone']);
    $magasin_id = !empty($_POST['magasin_id']) ? (int)sanitize_input($_POST['magasin_id']) : NULL;

    if (isset($_POST['add_personnel'])) {
        // Enregistrement du mot de passe en texte clair (NON SÉCURISÉ)
        // Cette méthode est EXTRÊMEMENT INSECURISÉE et ne doit pas être utilisée en production.
        $mot_de_passe = sanitize_input($_POST['mot_de_passe']);

        // Ajout
        $sql = "INSERT INTO personnel (nom, prenom, nom_utilisateur, mot_de_passe, role, email, telephone, magasin_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "sssssssi", $nom, $prenom, $nom_utilisateur, $mot_de_passe, $role, $email, $telephone, $magasin_id);
            if (mysqli_stmt_execute($stmt)) {
                $message = '<div class="alert alert-success">Personnel ajouté avec succès !</div>';
            } else {
                $message = '<div class="alert alert-error">Erreur lors de l\'ajout du personnel : ' . mysqli_error($conn) . '</div>';
            }
            mysqli_stmt_close($stmt);
        }
    } elseif (isset($_POST['edit_personnel'])) {
        $personnel_id = sanitize_input($_POST['personnel_id']);
        // Mise à jour du mot de passe en texte clair (NON SÉCURISÉ)
        // Cette méthode est EXTRÊMEMENT INSECURISÉE et ne doit pas être utilisée en production.
        $mot_de_passe_new = !empty($_POST['mot_de_passe']) ? sanitize_input($_POST['mot_de_passe']) : null;

        // Modification
        if ($mot_de_passe_new) {
            $sql = "UPDATE personnel SET nom = ?, prenom = ?, nom_utilisateur = ?, mot_de_passe = ?, role = ?, email = ?, telephone = ?, magasin_id = ? WHERE id = ?";
            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "sssssssii", $nom, $prenom, $nom_utilisateur, $mot_de_passe_new, $role, $email, $telephone, $magasin_id, $personnel_id);
            }
        } else {
            // Modification sans changer le mot de passe
            // Gérer le cas où magasin_id peut être NULL
            if ($magasin_id === NULL || $magasin_id === '') {
                // Utiliser une requête SQL différente pour NULL
                $sql = "UPDATE personnel SET nom = ?, prenom = ?, nom_utilisateur = ?, role = ?, email = ?, telephone = ?, magasin_id = NULL WHERE id = ?";
                if ($stmt = mysqli_prepare($conn, $sql)) {
                    // Chaîne de type : ssssssi (7 caractères pour 7 variables, pas de magasin_id)
                    mysqli_stmt_bind_param($stmt, "ssssssi", $nom, $prenom, $nom_utilisateur, $role, $email, $telephone, $personnel_id);
                }
            } else {
                $sql = "UPDATE personnel SET nom = ?, prenom = ?, nom_utilisateur = ?, role = ?, email = ?, telephone = ?, magasin_id = ? WHERE id = ?";
                if ($stmt = mysqli_prepare($conn, $sql)) {
                    // Chaîne de type corrigée : ssssssii (8 caractères pour 8 variables)
                    mysqli_stmt_bind_param($stmt, "ssssssii", $nom, $prenom, $nom_utilisateur, $role, $email, $telephone, $magasin_id, $personnel_id);
                }
            }
        }

        if ($stmt && mysqli_stmt_execute($stmt)) {
            $message = '<div class="alert alert-success">Personnel modifié avec succès !</div>';
        } else {
            $message = '<div class="alert alert-error">Erreur lors de la modification du personnel : ' . mysqli_error($conn) . '</div>';
        }
        if ($stmt)
            mysqli_stmt_close($stmt);
    }
}

// Gérer la suppression d'un membre du personnel
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_personnel'])) {
    $personnel_id = sanitize_input($_POST['personnel_id']);
    $sql = "DELETE FROM personnel WHERE id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $personnel_id);
        if (mysqli_stmt_execute($stmt)) {
            $message = '<div class="alert alert-success">Personnel supprimé avec succès !</div>';
        } else {
            $message = '<div class="alert alert-error">Erreur lors de la suppression du personnel : ' . mysqli_error($conn) . '</div>';
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
    $where_clause = " WHERE p.nom LIKE '%" . $search_query . "%' OR p.prenom LIKE '%" . $search_query . "%' OR p.nom_utilisateur LIKE '%" . $search_query . "%' OR p.email LIKE '%" . $search_query . "%' OR p.role LIKE '%" . $search_query . "%'";
}

// Requête pour le nombre total de personnel (pour la pagination)
$count_sql = "SELECT COUNT(p.id) AS total FROM personnel p" . $where_clause;
$count_result = mysqli_query($conn, $count_sql);
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $limit);

// Requête pour récupérer le personnel avec pagination, recherche et tri
// Jointure avec la table magasins pour afficher le nom du magasin
$sql = "SELECT p.id, p.nom, p.prenom, p.nom_utilisateur, p.role, p.email, p.telephone, m.nom AS nom_magasin, p.magasin_id
        FROM personnel p
        LEFT JOIN magasins m ON p.magasin_id = m.id"
    . $where_clause . " ORDER BY p." . $sort_by . " " . $sort_order . " LIMIT ? OFFSET ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $limit, $offset);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$personnel = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Récupérer la liste des rôles disponibles (peut être statique ou depuis une table si nécessaire)
$roles_disponibles = ['Administrateur', 'Gérant', 'Caissier', 'Vendeur'];

// Récupérer la liste des magasins pour le sélecteur
$magasins_disponibles = [];
$sql_magasins = "SELECT id, nom FROM magasins ORDER BY nom ASC";
$result_magasins = mysqli_query($conn, $sql_magasins);
while ($row = mysqli_fetch_assoc($result_magasins)) {
    $magasins_disponibles[] = $row;
}
mysqli_free_result($result_magasins);

// Inclure le header HTML après toute logique de redirection
include '../../includes/header.php';
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
                        <button id="addPersonnelBtn"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md shadow-md transition duration-300 mb-4 md:mb-0">
                            <i class="fas fa-user-plus mr-2"></i> Ajouter du Personnel
                        </button>
                        <form method="GET" action="" class="flex flex-col md:flex-row gap-4 w-full md:w-auto">
                            <input type="text" name="search" placeholder="Rechercher..."
                                value="<?php echo htmlspecialchars($search_query); ?>"
                                class="flex-grow shadow appearance-none border rounded-md py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            <select name="sort_by"
                                class="shadow appearance-none border rounded-md py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                <option value="nom" <?php echo $sort_by == 'nom' ? 'selected' : ''; ?>>Trier par Nom
                                </option>
                                <option value="role" <?php echo $sort_by == 'role' ? 'selected' : ''; ?>>Trier par Rôle
                                </option>
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
                                    <th>Prénom</th>
                                    <th>Nom d'utilisateur</th>
                                    <th>Rôle</th>
                                    <th>Email</th>
                                    <th>Téléphone</th>
                                    <th>Magasin</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($personnel)): ?>
                                    <?php foreach ($personnel as $p): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($p['id']); ?></td>
                                            <td><?php echo htmlspecialchars($p['nom']); ?></td>
                                            <td><?php echo htmlspecialchars($p['prenom']); ?></td>
                                            <td><?php echo htmlspecialchars($p['nom_utilisateur']); ?></td>
                                            <td><?php echo htmlspecialchars($p['role']); ?></td>
                                            <td><?php echo htmlspecialchars($p['email']); ?></td>
                                            <td><?php echo htmlspecialchars($p['telephone']); ?></td>
                                            <td><?php echo htmlspecialchars($p['nom_magasin'] ?? 'N/A'); ?></td>
                                            <td class="action-buttons">
                                                <button class="btn btn-edit edit-personnel-btn"
                                                    data-id="<?php echo htmlspecialchars($p['id']); ?>"
                                                    data-nom="<?php echo htmlspecialchars($p['nom']); ?>"
                                                    data-prenom="<?php echo htmlspecialchars($p['prenom']); ?>"
                                                    data-nom_utilisateur="<?php echo htmlspecialchars($p['nom_utilisateur']); ?>"
                                                    data-role="<?php echo htmlspecialchars($p['role']); ?>"
                                                    data-email="<?php echo htmlspecialchars($p['email']); ?>"
                                                    data-telephone="<?php echo htmlspecialchars($p['telephone']); ?>"
                                                    data-magasin_id="<?php echo htmlspecialchars($p['magasin_id']); ?>">
                                                    <i class="fas fa-edit"></i> Modifier
                                                </button>
                                                <button class="btn btn-delete delete-personnel-btn"
                                                    data-id="<?php echo htmlspecialchars($p['id']); ?>">
                                                    <i class="fas fa-trash-alt"></i> Supprimer
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-4">Aucun membre du personnel trouvé.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="flex flex-col sm:flex-row justify-between items-center mt-4">
                        <p class="text-gray-600 mb-2 sm:mb-0">Affichage de
                            <?php echo min($limit, $total_records - $offset); ?> sur <?php echo $total_records; ?>
                            membres du personnel
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

<!-- Modal d'ajout/modification de personnel -->
<div id="personnelModal" class="modal hidden">
    <div class="modal-content">
        <span class="modal-close-button">&times;</span>
        <h2 id="modalTitlePersonnel" class="text-2xl font-bold text-gray-800 mb-4">Ajouter du Personnel</h2>
        <form id="personnelForm" method="POST" action="">
            <input type="hidden" id="personnelId" name="personnel_id">
            <div class="form-group">
                <label for="nom">Nom:</label>
                <input type="text" id="nom_personnel" name="nom" required
                    class="w-full p-2 border border-gray-300 rounded-md">
            </div>
            <div class="form-group">
                <label for="prenom">Prénom:</label>
                <input type="text" id="prenom_personnel" name="prenom" required
                    class="w-full p-2 border border-gray-300 rounded-md">
            </div>
            <div class="form-group">
                <label for="nom_utilisateur">Nom d'utilisateur:</label>
                <input type="text" id="nom_utilisateur" name="nom_utilisateur" required
                    class="w-full p-2 border border-gray-300 rounded-md">
            </div>
            <div class="form-group">
                <label for="mot_de_passe">Mot de passe (laisser vide pour ne pas changer):</label>
                <input type="password" id="mot_de_passe" name="mot_de_passe"
                    class="w-full p-2 border border-gray-300 rounded-md">
            </div>
            <div class="form-group">
                <label for="role">Rôle:</label>
                <select id="role" name="role" required class="w-full p-2 border border-gray-300 rounded-md">
                    <?php foreach ($roles_disponibles as $role_option): ?>
                        <option value="<?php echo htmlspecialchars($role_option); ?>">
                            <?php echo htmlspecialchars($role_option); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email_personnel" name="email"
                    class="w-full p-2 border border-gray-300 rounded-md">
            </div>
            <div class="form-group">
                <label for="telephone">Téléphone:</label>
                <input type="text" id="telephone_personnel" name="telephone"
                    class="w-full p-2 border border-gray-300 rounded-md">
            </div>
            <div class="form-group">
                <label for="magasin_id">Magasin Affecté:</label>
                <select id="magasin_id" name="magasin_id" class="w-full p-2 border border-gray-300 rounded-md">
                    <option value="">-- Aucun Magasin --</option>
                    <?php foreach ($magasins_disponibles as $magasin_option): ?>
                        <option value="<?php echo htmlspecialchars($magasin_option['id']); ?>">
                            <?php echo htmlspecialchars($magasin_option['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" id="submitPersonnelBtn" name="add_personnel" class="btn-primary mt-4 w-full">
                <i class="fas fa-save mr-2"></i> Enregistrer
            </button>
        </form>
    </div>
</div>

<!-- Modal de confirmation de suppression -->
<div id="deleteConfirmPersonnelModal" class="modal hidden">
    <div class="modal-content max-w-sm text-center">
        <span class="modal-close-button">&times;</span>
        <h2 class="text-2xl font-bold text-gray-800 mb-4">Confirmer la Suppression</h2>
        <p class="text-gray-700 mb-6">Êtes-vous sûr de vouloir supprimer ce membre du personnel ? Cette action est
            irréversible.</p>
        <form id="deletePersonnelForm" method="POST" action="">
            <input type="hidden" id="deletePersonnelId" name="personnel_id">
            <div class="flex justify-center space-x-4">
                <button type="button" id="cancelDeletePersonnelBtn"
                    class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded-md transition duration-300">
                    Annuler
                </button>
                <button type="submit" name="delete_personnel"
                    class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-md transition duration-300">
                    Supprimer <i class="fas fa-trash-alt ml-2"></i>
                </button>
            </div>
        </form>
    </div>
</div>



<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Initialiser les modals pour le personnel
        const personnelModal = document.getElementById('personnelModal');
        const addPersonnelBtn = document.getElementById('addPersonnelBtn');
        const modalCloseButtons = document.querySelectorAll('.modal-close-button'); // Tous les boutons de fermeture
        const personnelForm = document.getElementById('personnelForm');
        const modalTitlePersonnel = document.getElementById('modalTitlePersonnel');
        const submitPersonnelBtn = document.getElementById('submitPersonnelBtn');

        const personnelIdInput = document.getElementById('personnelId');
        const nomInput = document.getElementById('nom_personnel');
        const prenomInput = document.getElementById('prenom_personnel');
        const nomUtilisateurInput = document.getElementById('nom_utilisateur');
        const motDePasseInput = document.getElementById('mot_de_passe');
        const roleSelect = document.getElementById('role');
        const emailInput = document.getElementById('email_personnel');
        const telephoneInput = document.getElementById('telephone_personnel');
        const magasinIdSelect = document.getElementById('magasin_id');

        const deleteConfirmPersonnelModal = document.getElementById('deleteConfirmPersonnelModal');
        const deletePersonnelIdInput = document.getElementById('deletePersonnelId');
        const cancelDeletePersonnelBtn = document.getElementById('cancelDeletePersonnelBtn');

        // Fonction pour ouvrir un modal (réutilisée)
        function openModal(modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        // Fonction pour fermer un modal (réutilisée)
        function closeModal(modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        // Ouvrir le modal d'ajout de personnel
        if (addPersonnelBtn) {
            addPersonnelBtn.addEventListener('click', function () {
                modalTitlePersonnel.textContent = 'Ajouter du Personnel';
                submitPersonnelBtn.name = 'add_personnel';
                submitPersonnelBtn.innerHTML = '<i class="fas fa-save mr-2"></i> Enregistrer';
                personnelForm.reset(); // Réinitialiser le formulaire
                personnelIdInput.value = '';
                motDePasseInput.setAttribute('required', 'required'); // Mot de passe requis pour l'ajout
                openModal(personnelModal);
            });
        }

        // Ouvrir le modal de modification de personnel
        document.querySelectorAll('.edit-personnel-btn').forEach(button => {
            button.addEventListener('click', function () {
                modalTitlePersonnel.textContent = 'Modifier le Personnel';
                submitPersonnelBtn.name = 'edit_personnel';
                submitPersonnelBtn.innerHTML = '<i class="fas fa-edit mr-2"></i> Mettre à jour';

                // Remplir le formulaire avec les données existantes
                personnelIdInput.value = this.dataset.id;
                nomInput.value = this.dataset.nom;
                prenomInput.value = this.dataset.prenom;
                nomUtilisateurInput.value = this.dataset.nom_utilisateur;
                motDePasseInput.value = ''; // Ne pas pré-remplir le mot de passe pour des raisons de sécurité
                motDePasseInput.removeAttribute('required'); // Mot de passe non requis pour la modification
                roleSelect.value = this.dataset.role;
                emailInput.value = this.dataset.email;
                telephoneInput.value = this.dataset.telephone;
                magasinIdSelect.value = this.dataset.magasin_id;

                openModal(personnelModal);
            });
        });

        // Ouvrir le modal de suppression de personnel
        document.querySelectorAll('.delete-personnel-btn').forEach(button => {
            button.addEventListener('click', function () {
                deletePersonnelIdInput.value = this.dataset.id;
                openModal(deleteConfirmPersonnelModal);
            });
        });

        // Fermer les modals via les boutons de fermeture (pour tous les modals)
        modalCloseButtons.forEach(button => {
            button.addEventListener('click', function () {
                closeModal(personnelModal);
                closeModal(deleteConfirmPersonnelModal);
            });
        });

        // Fermer les modals si l'utilisateur clique en dehors du contenu
        window.addEventListener('click', function (event) {
            if (event.target == personnelModal) {
                closeModal(personnelModal);
            }
            if (event.target == deleteConfirmPersonnelModal) {
                closeModal(deleteConfirmPersonnelModal);
            }
        });

        // Annuler la suppression
        if (cancelDeletePersonnelBtn) {
            cancelDeletePersonnelBtn.addEventListener('click', function () {
                closeModal(deleteConfirmPersonnelModal);
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
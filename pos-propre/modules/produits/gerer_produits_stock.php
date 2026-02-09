<?php
// modules/produits/gerer_produits_stock.php
session_start();

// Inclure les fichiers nécessaires
include '../../db_connect.php';
include '../../includes/header.php'; // Inclut le header avec le début du HTML

// Définir le répertoire d'upload des images
define('UPLOAD_DIR', '../../uploads/produits/'); // Assurez-vous que ce dossier existe et est accessible en écriture
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}

// Fonction pour gérer l'upload de l'image
function handleImageUpload($file_input_name, $current_image_url = null)
{
    if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] === UPLOAD_ERR_OK) {
        $file_tmp_name = $_FILES[$file_input_name]['tmp_name'];
        $file_name = uniqid() . '_' . basename($_FILES[$file_input_name]['name']);
        $file_destination = UPLOAD_DIR . $file_name;

        // Vérifier le type de fichier (optionnel mais recommandé)
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($_FILES[$file_input_name]['type'], $allowed_types)) {
            return ['error' => 'Seuls les fichiers JPG, PNG et GIF sont autorisés.'];
        }

        // Déplacer le fichier téléchargé
        if (move_uploaded_file($file_tmp_name, $file_destination)) {
            // Supprimer l'ancienne image si une nouvelle est téléchargée lors d'une modification
            if ($current_image_url && file_exists($current_image_url)) {
                unlink($current_image_url);
            }
            return ['success' => str_replace('../../', '', $file_destination)]; // Retourne le chemin relatif pour la DB
        } else {
            return ['error' => 'Erreur lors de l\'upload de l\'image.'];
        }
    } elseif (isset($_POST['remove_image']) && $_POST['remove_image'] == 'true') {
        // Si l'utilisateur a explicitement demandé de supprimer l'image
        if ($current_image_url && file_exists($current_image_url)) {
            unlink($current_image_url);
        }
        return ['success' => NULL]; // Définit l'image_url à NULL
    }
    return ['success' => $current_image_url]; // Aucune nouvelle image, garde l'ancienne
}


// Vérifier si l'utilisateur est connecté et a le rôle approprié
// Les rôles autorisés pour la gestion des produits et du stock sont Administrateur, Gérant
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !in_array($_SESSION['role'], ['Administrateur', 'Gérant'])) {
    header("location: " . BASE_URL . "public/login.php"); // Utilisation de BASE_URL
    exit;
}

$page_title = "Gestion des Produits et du Stock";

$message = ''; // Pour afficher les messages de succès ou d'erreur

// --- Logique de traitement des formulaires (CRUD Produits) ---

// Gérer l'ajout ou la modification d'un produit
if ($_SERVER["REQUEST_METHOD"] == "POST" && (isset($_POST['add_produit']) || isset($_POST['edit_produit']))) {
    $nom = sanitize_input($_POST['nom']);
    $description = sanitize_input($_POST['description']);
    $prix_achat = (float) sanitize_input($_POST['prix_achat']);
    $prix_vente = (float) sanitize_input($_POST['prix_vente']);
    $quantite_stock = (float) sanitize_input($_POST['quantite_stock']);
    $seuil_alerte_stock = (int) sanitize_input($_POST['seuil_alerte_stock']);

    $fournisseur_id = !empty($_POST['fournisseur_id']) ? sanitize_input($_POST['fournisseur_id']) : NULL;
    $categorie_id = !empty($_POST['categorie_id']) ? sanitize_input($_POST['categorie_id']) : NULL;
    $magasin_id = !empty($_POST['magasin_id']) ? sanitize_input($_POST['magasin_id']) : NULL; // Nouveau champ magasin_id

    $image_url = NULL; // Initialiser à NULL

    // Pour la modification, récupérer l'URL de l'image actuelle
    if (isset($_POST['edit_produit'])) {
        $produit_id_for_image = sanitize_input($_POST['produit_id']);
        $sql_get_current_image = "SELECT image_url FROM produits WHERE id = ?";
        if ($stmt_get_image = mysqli_prepare($conn, $sql_get_current_image)) {
            mysqli_stmt_bind_param($stmt_get_image, "i", $produit_id_for_image);
            mysqli_stmt_execute($stmt_get_image);
            $result_get_image = mysqli_stmt_get_result($stmt_get_image);
            $row_image = mysqli_fetch_assoc($result_get_image);
            $current_image_url_db = $row_image['image_url'];
            mysqli_stmt_close($stmt_get_image);
            // Reconstruire le chemin complet si nécessaire pour la suppression
            $full_current_image_path = $current_image_url_db ? '../../' . $current_image_url_db : null;

            $image_upload_result = handleImageUpload('image_produit', $full_current_image_path);
            if (isset($image_upload_result['error'])) {
                $message = '<div class="alert alert-error">' . $image_upload_result['error'] . '</div>';
                // Ne pas poursuivre si l'upload a échoué
            } else {
                $image_url = $image_upload_result['success'];
            }
        }
    } else {
        // Pour l'ajout, simplement uploader la nouvelle image
        $image_upload_result = handleImageUpload('image_produit');
        if (isset($image_upload_result['error'])) {
            $message = '<div class="alert alert-error">' . $image_upload_result['error'] . '</div>';
            // Ne pas poursuivre si l'upload a échoué
        } else {
            $image_url = $image_upload_result['success'];
        }
    }

    // Si un message d'erreur d'upload existe, ne pas procéder à l'insertion/mise à jour du produit
    if (!empty($message)) {
        goto end_form_processing; // Saute à la fin du traitement du formulaire
    }

    // Gérer l'ajout de nouvelle catégorie si fournie
    if (!empty($_POST['new_categorie_nom'])) {
        $new_categorie_nom = sanitize_input($_POST['new_categorie_nom']);
        $sql_cat = "INSERT INTO categories (nom) VALUES (?)";
        if ($stmt_cat = mysqli_prepare($conn, $sql_cat)) {
            mysqli_stmt_bind_param($stmt_cat, "s", $new_categorie_nom);
            if (mysqli_stmt_execute($stmt_cat)) {
                $categorie_id = mysqli_insert_id($conn);
            } else {
                $message = '<div class="alert alert-error">Erreur lors de l\'ajout de la catégorie : ' . mysqli_error($conn) . '</div>';
            }
            mysqli_stmt_close($stmt_cat);
        }
    }

    // Gérer l'ajout de nouveau fournisseur si fourni
    if (!empty($_POST['new_fournisseur_nom'])) {
        $new_fournisseur_nom = sanitize_input($_POST['new_fournisseur_nom']);
        $sql_four = "INSERT INTO fournisseurs (nom) VALUES (?)";
        if ($stmt_four = mysqli_prepare($conn, $sql_four)) {
            mysqli_stmt_bind_param($stmt_four, "s", $new_fournisseur_nom);
            if (mysqli_stmt_execute($stmt_four)) {
                $fournisseur_id = mysqli_insert_id($conn);
            } else {
                $message = '<div class="alert alert-error">Erreur lors de l\'ajout du fournisseur : ' . mysqli_error($conn) . '</div>';
            }
            mysqli_stmt_close($stmt_four);
        }
    }


    if (isset($_POST['add_produit'])) {
        // Ajout
        // Assurez-vous que la colonne 'magasin_id' existe dans votre table 'produits'
        $sql = "INSERT INTO produits (nom, description, image_url, prix_achat, prix_vente, fournisseur_id, categorie_id, quantite_stock, seuil_alerte_stock, magasin_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "sssdiiidii", $nom, $description, $image_url, $prix_achat, $prix_vente, $fournisseur_id, $categorie_id, $quantite_stock, $seuil_alerte_stock, $magasin_id);
            if (mysqli_stmt_execute($stmt)) {
                $message = '<div class="alert alert-success">Produit ajouté avec succès !</div>';
            } else {
                $message = '<div class="alert alert-error">Erreur lors de l\'ajout du produit : ' . mysqli_error($conn) . '</div>';
            }
            mysqli_stmt_close($stmt);
        }
    } elseif (isset($_POST['edit_produit'])) {
        // Modification
        $produit_id = sanitize_input($_POST['produit_id']);
        // Assurez-vous que la colonne 'magasin_id' existe dans votre table 'produits'
        $sql = "UPDATE produits SET nom = ?, description = ?, image_url = ?, prix_achat = ?, prix_vente = ?, fournisseur_id = ?, categorie_id = ?, quantite_stock = ?, seuil_alerte_stock = ?, magasin_id = ? WHERE id = ?";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "sssdiiidiii", $nom, $description, $image_url, $prix_achat, $prix_vente, $fournisseur_id, $categorie_id, $quantite_stock, $seuil_alerte_stock, $magasin_id, $produit_id);
            if (mysqli_stmt_execute($stmt)) {
                $message = '<div class="alert alert-success">Produit modifié avec succès !</div>';
            } else {
                $message = '<div class="alert alert-error">Erreur lors de la modification du produit : ' . mysqli_error($conn) . '</div>';
            }
            mysqli_stmt_close($stmt);
        }
    }
    end_form_processing:
    ; // Étiquette pour le goto
}

// Gérer la suppression d'un produit
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_produit'])) {
    $produit_id = sanitize_input($_POST['produit_id']);

    // Récupérer le chemin de l'image avant de supprimer le produit
    $sql_get_image = "SELECT image_url FROM produits WHERE id = ?";
    if ($stmt_get_image = mysqli_prepare($conn, $sql_get_image)) {
        mysqli_stmt_bind_param($stmt_get_image, "i", $produit_id);
        mysqli_stmt_execute($stmt_get_image);
        $result_get_image = mysqli_stmt_get_result($stmt_get_image);
        $row = mysqli_fetch_assoc($result_get_image);
        $image_to_delete = $row['image_url'] ? '../../' . $row['image_url'] : NULL;
        mysqli_stmt_close($stmt_get_image);
    }

    $sql = "DELETE FROM produits WHERE id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $produit_id);
        if (mysqli_stmt_execute($stmt)) {
            // Supprimer l'image du serveur après la suppression réussie de la base de données
            if ($image_to_delete && file_exists($image_to_delete)) {
                unlink($image_to_delete);
            }
            $message = '<div class="alert alert-success">Produit supprimé avec succès !</div>';
        } else {
            $message = '<div class="alert alert-error">Erreur lors de la suppression du produit : ' . mysqli_error($conn) . '</div>';
        }
        mysqli_stmt_close($stmt);
    }
}

// Gérer la complétion du stock
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['complete_stock'])) {
    $produit_id = sanitize_input($_POST['produit_id_stock']);
    $quantite_ajoutee = (float) sanitize_input($_POST['quantite_ajoutee']);

    if ($quantite_ajoutee <= 0) {
        $message = '<div class="alert alert-error">La quantité à ajouter doit être positive.</div>';
    } else {
        $sql = "UPDATE produits SET quantite_stock = quantite_stock + ? WHERE id = ?";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "di", $quantite_ajoutee, $produit_id);
            if (mysqli_stmt_execute($stmt)) {
                $message = '<div class="alert alert-success">Stock mis à jour avec succès !</div>';
            } else {
                $message = '<div class="alert alert-error">Erreur lors de la mise à jour du stock : ' . mysqli_error($conn) . '</div>';
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
$sort_by = isset($_GET['sort_by']) ? sanitize_input($_GET['sort_by']) : 'p.nom';
$sort_order = isset($_GET['sort_order']) && in_array(strtoupper($_GET['sort_order']), ['ASC', 'DESC']) ? $_GET['sort_order'] : 'ASC';
$filter_category = isset($_GET['filter_category']) ? sanitize_input($_GET['filter_category']) : '';
$filter_fournisseur = isset($_GET['filter_fournisseur']) ? sanitize_input($_GET['filter_fournisseur']) : '';
$filter_magasin = isset($_GET['filter_magasin']) ? sanitize_input($_GET['filter_magasin']) : ''; // Nouveau filtre magasin
$filter_alerte_stock = isset($_GET['filter_alerte_stock']) ? true : false;


// Construction de la clause WHERE pour la recherche et les filtres
$where_clause = '';
$params = [];
$param_types = '';

if (!empty($search_query)) {
    $where_clause .= " WHERE (p.nom LIKE ? OR p.description LIKE ?)";
    $search_param = '%' . $search_query . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'ss';
}

if (!empty($filter_category)) {
    if (empty($where_clause))
        $where_clause .= " WHERE";
    else
        $where_clause .= " AND";
    $where_clause .= " c.id = ?";
    $params[] = $filter_category;
    $param_types .= 'i';
}

if (!empty($filter_fournisseur)) {
    if (empty($where_clause))
        $where_clause .= " WHERE";
    else
        $where_clause .= " AND";
    $where_clause .= " f.id = ?";
    $params[] = $filter_fournisseur;
    $param_types .= 'i';
}

if (!empty($filter_magasin)) { // Nouveau filtre magasin
    if (empty($where_clause))
        $where_clause .= " WHERE";
    else
        $where_clause .= " AND";
    $where_clause .= " m.id = ?";
    $params[] = $filter_magasin;
    $param_types .= 'i';
}

if ($filter_alerte_stock) {
    if (empty($where_clause))
        $where_clause .= " WHERE";
    else
        $where_clause .= " AND";
    $where_clause .= " p.quantite_stock <= p.seuil_alerte_stock";
}


// Requête pour le nombre total de produits (pour la pagination)
$count_sql = "SELECT COUNT(p.id) AS total FROM produits p
              LEFT JOIN categories c ON p.categorie_id = c.id
              LEFT JOIN fournisseurs f ON p.fournisseur_id = f.id
              LEFT JOIN magasins m ON p.magasin_id = m.id" // Nouvelle jointure
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

// Requête pour récupérer les produits avec pagination, recherche, tri et filtres
$sql = "SELECT p.id, p.nom, p.description, p.image_url, p.prix_achat, p.prix_vente, p.quantite_stock, p.seuil_alerte_stock, p.date_creation,
               c.nom AS nom_categorie, f.nom AS nom_fournisseur, m.nom AS nom_magasin, p.categorie_id, p.fournisseur_id, p.magasin_id
        FROM produits p
        LEFT JOIN categories c ON p.categorie_id = c.id
        LEFT JOIN fournisseurs f ON p.fournisseur_id = f.id
        LEFT JOIN magasins m ON p.magasin_id = m.id" // Nouvelle jointure
    . $where_clause . " ORDER BY " . $sort_by . " " . $sort_order . " LIMIT ? OFFSET ?";

$stmt = mysqli_prepare($conn, $sql);
// Ajouter les paramètres de LIMIT et OFFSET aux paramètres existants
$params[] = $limit;
$params[] = $offset;
$param_types .= 'ii';

mysqli_stmt_bind_param($stmt, $param_types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$produits = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Récupérer la liste des catégories pour les sélecteurs
$categories_disponibles = [];
$sql_categories = "SELECT id, nom FROM categories ORDER BY nom ASC";
$result_categories = mysqli_query($conn, $sql_categories);
while ($row = mysqli_fetch_assoc($result_categories)) {
    $categories_disponibles[] = $row;
}
mysqli_free_result($result_categories);

// Récupérer la liste des fournisseurs pour les sélecteurs
$fournisseurs_disponibles = [];
$sql_fournisseurs = "SELECT id, nom FROM fournisseurs ORDER BY nom ASC";
$result_fournisseurs = mysqli_query($conn, $sql_fournisseurs);
while ($row = mysqli_fetch_assoc($result_fournisseurs)) {
    $fournisseurs_disponibles[] = $row;
}
mysqli_free_result($result_fournisseurs);

// Récupérer la liste des magasins pour les sélecteurs
$magasins_disponibles = [];
$sql_magasins = "SELECT id, nom FROM magasins ORDER BY nom ASC";
$result_magasins = mysqli_query($conn, $sql_magasins);
while ($row = mysqli_fetch_assoc($result_magasins)) {
    $magasins_disponibles[] = $row;
}
mysqli_free_result($result_magasins);

?>

<div class="flex h-screen bg-gray-100">
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
                        <button id="addProduitBtn"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md shadow-md transition duration-300 mb-4 md:mb-0">
                            <i class="fas fa-box-open mr-2"></i> Ajouter un Produit
                        </button>

                        <form method="GET" action=""
                            class="flex flex-col md:flex-row gap-4 w-full md:w-auto items-center">
                            <input type="text" name="search" placeholder="Rechercher..."
                                value="<?php echo htmlspecialchars($search_query); ?>"
                                class="flex-grow shadow appearance-none border rounded-md py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            <select name="filter_category"
                                class="shadow appearance-none border rounded-md py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                <option value="">Toutes catégories</option>
                                <?php foreach ($categories_disponibles as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select name="filter_fournisseur"
                                class="shadow appearance-none border rounded-md py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                <option value="">Tous fournisseurs</option>
                                <?php foreach ($fournisseurs_disponibles as $four): ?>
                                    <option value="<?php echo $four['id']; ?>"><?php echo htmlspecialchars($four['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select name="filter_magasin"
                                class="shadow appearance-none border rounded-md py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                <option value="">Tous magasins</option>
                                <?php foreach ($magasins_disponibles as $mag): ?>
                                    <option value="<?php echo $mag['id']; ?>"><?php echo htmlspecialchars($mag['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="flex items-center">
                                <input type="checkbox" id="filter_alerte_stock" name="filter_alerte_stock"
                                    class="form-checkbox h-4 w-4 text-blue-600" <?php echo $filter_alerte_stock ? 'checked' : ''; ?>>
                                <label for="filter_alerte_stock" class="ml-2 text-gray-700">Alerte Stock</label>
                            </div>
                            <button type="submit"
                                class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-md shadow-md transition duration-300">
                                Filtrer
                            </button>
                            <a href="gerer_produits_stock.php"
                                class="bg-gray-400 hover:bg-gray-500 text-white font-bold py-2 px-4 rounded-md shadow-md transition duration-300">
                                Réinitialiser
                            </a>
                        </form>
                    </div>

                    <div class="overflow-x-auto bg-white rounded-lg shadow-md">
                        <table class="min-w-full leading-normal">
                            <thead>
                                <tr>
                                    <th
                                        class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Image
                                    </th>
                                    <th
                                        class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        <a href="?page=<?php echo $page; ?>&search=<?php echo urlencode($search_query); ?>&sort_by=p.nom&sort_order=<?php echo ($sort_by == 'p.nom' && $sort_order == 'ASC') ? 'DESC' : 'ASC'; ?>&filter_category=<?php echo urlencode($filter_category); ?>&filter_fournisseur=<?php echo urlencode($filter_fournisseur); ?>&filter_magasin=<?php echo urlencode($filter_magasin); ?><?php echo $filter_alerte_stock ? '&filter_alerte_stock=true' : ''; ?>"
                                            class="flex items-center">
                                            Nom
                                            <?php if ($sort_by == 'p.nom'): ?>
                                                <i class="ml-1 fas fa-sort-<?php echo strtolower($sort_order); ?>"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th
                                        class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Description
                                    </th>
                                    <th
                                        class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Catégorie
                                    </th>
                                    <th
                                        class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Fournisseur
                                    </th>
                                    <th
                                        class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Magasin
                                    </th>
                                    <th
                                        class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        <a href="?page=<?php echo $page; ?>&search=<?php echo urlencode($search_query); ?>&sort_by=p.prix_achat&sort_order=<?php echo ($sort_by == 'p.prix_achat' && $sort_order == 'ASC') ? 'DESC' : 'ASC'; ?>&filter_category=<?php echo urlencode($filter_category); ?>&filter_fournisseur=<?php echo urlencode($filter_fournisseur); ?>&filter_magasin=<?php echo urlencode($filter_magasin); ?><?php echo $filter_alerte_stock ? '&filter_alerte_stock=true' : ''; ?>"
                                            class="flex items-center">
                                            Prix Achat
                                            <?php if ($sort_by == 'p.prix_achat'): ?>
                                                <i class="ml-1 fas fa-sort-<?php echo strtolower($sort_order); ?>"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th
                                        class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        <a href="?page=<?php echo $page; ?>&search=<?php echo urlencode($search_query); ?>&sort_by=p.prix_vente&sort_order=<?php echo ($sort_by == 'p.prix_vente' && $sort_order == 'ASC') ? 'DESC' : 'ASC'; ?>&filter_category=<?php echo urlencode($filter_category); ?>&filter_fournisseur=<?php echo urlencode($filter_fournisseur); ?>&filter_magasin=<?php echo urlencode($filter_magasin); ?><?php echo $filter_alerte_stock ? '&filter_alerte_stock=true' : ''; ?>"
                                            class="flex items-center">
                                            Prix Vente
                                            <?php if ($sort_by == 'p.prix_vente'): ?>
                                                <i class="ml-1 fas fa-sort-<?php echo strtolower($sort_order); ?>"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th
                                        class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        <a href="?page=<?php echo $page; ?>&search=<?php echo urlencode($search_query); ?>&sort_by=p.quantite_stock&sort_order=<?php echo ($sort_by == 'p.quantite_stock' && $sort_order == 'ASC') ? 'DESC' : 'ASC'; ?>&filter_category=<?php echo urlencode($filter_category); ?>&filter_fournisseur=<?php echo urlencode($filter_fournisseur); ?>&filter_magasin=<?php echo urlencode($filter_magasin); ?><?php echo $filter_alerte_stock ? '&filter_alerte_stock=true' : ''; ?>"
                                            class="flex items-center">
                                            Qté Stock
                                            <?php if ($sort_by == 'p.quantite_stock'): ?>
                                                <i class="ml-1 fas fa-sort-<?php echo strtolower($sort_order); ?>"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th
                                        class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Seuil Alerte
                                    </th>
                                    <th
                                        class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($produits)): ?>
                                    <?php foreach ($produits as $produit): ?>
                                        <tr
                                            class="<?php echo ($produit['quantite_stock'] <= $produit['seuil_alerte_stock']) ? 'bg-red-100' : 'hover:bg-gray-50'; ?>">
                                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                                <?php if ($produit['image_url']): ?>
                                                    <img src="<?php echo BASE_URL . htmlspecialchars($produit['image_url']); ?>"
                                                        alt="Image Produit" class="w-16 h-16 object-cover rounded-md">
                                                <?php else: ?>
                                                    <div
                                                        class="w-16 h-16 bg-gray-200 flex items-center justify-center rounded-md text-gray-500 text-xs">
                                                        No Image
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                                <p class="text-gray-900 whitespace-no-wrap">
                                                    <?php echo htmlspecialchars($produit['nom']); ?>
                                                </p>
                                            </td>
                                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                                <p class="text-gray-900 whitespace-no-wrap">
                                                    <?php echo htmlspecialchars($produit['description']); ?>
                                                </p>
                                            </td>
                                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                                <p class="text-gray-900 whitespace-no-wrap">
                                                    <?php echo htmlspecialchars($produit['nom_categorie'] ?? 'N/A'); ?>
                                                </p>
                                            </td>
                                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                                <p class="text-gray-900 whitespace-no-wrap">
                                                    <?php echo htmlspecialchars($produit['nom_fournisseur'] ?? 'N/A'); ?>
                                                </p>
                                            </td>
                                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                                <p class="text-gray-900 whitespace-no-wrap">
                                                    <?php echo htmlspecialchars($produit['nom_magasin'] ?? 'N/A'); ?>
                                                </p>
                                            </td>
                                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                                <p class="text-gray-900 whitespace-no-wrap">
                                                    <?php echo number_format($produit['prix_achat'], 2, ',', ' '); ?> XOF
                                                </p>
                                            </td>
                                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                                <p class="text-gray-900 whitespace-no-wrap">
                                                    <?php echo number_format($produit['prix_vente'], 2, ',', ' '); ?> XOF
                                                </p>
                                            </td>
                                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                                <p
                                                    class="text-gray-900 whitespace-no-wrap <?php echo ($produit['quantite_stock'] <= $produit['seuil_alerte_stock']) ? 'text-red-600 font-bold' : ''; ?>">
                                                    <?php echo number_format($produit['quantite_stock'], 2, ',', ' '); ?>
                                                </p>
                                            </td>
                                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                                <p class="text-gray-900 whitespace-no-wrap">
                                                    <?php echo htmlspecialchars($produit['seuil_alerte_stock']); ?>
                                                </p>
                                            </td>
                                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                                <div class="flex items-center space-x-3">
                                                    <button class="edit-produit-btn text-blue-600 hover:text-blue-900"
                                                        data-id="<?php echo $produit['id']; ?>"
                                                        data-nom="<?php echo htmlspecialchars($produit['nom']); ?>"
                                                        data-description="<?php echo htmlspecialchars($produit['description']); ?>"
                                                        data-prix_achat="<?php echo $produit['prix_achat']; ?>"
                                                        data-prix_vente="<?php echo $produit['prix_vente']; ?>"
                                                        data-quantite_stock="<?php echo $produit['quantite_stock']; ?>"
                                                        data-seuil_alerte_stock="<?php echo $produit['seuil_alerte_stock']; ?>"
                                                        data-categorie_id="<?php echo $produit['categorie_id']; ?>"
                                                        data-fournisseur_id="<?php echo $produit['fournisseur_id']; ?>"
                                                        data-magasin_id="<?php echo $produit['magasin_id']; ?>"
                                                        data-image_url="<?php echo htmlspecialchars($produit['image_url'] ? BASE_URL . $produit['image_url'] : ''); ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="delete-produit-btn text-red-600 hover:text-red-900"
                                                        data-id="<?php echo $produit['id']; ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                    <button class="complete-stock-btn text-green-600 hover:text-green-900"
                                                        data-id="<?php echo $produit['id']; ?>"
                                                        data-nom="<?php echo htmlspecialchars($produit['nom']); ?>">
                                                        <i class="fas fa-plus-square"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="11"
                                            class="px-5 py-5 border-b border-gray-200 bg-white text-sm text-center text-gray-600">
                                            Aucun produit trouvé.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex flex-col sm:flex-row justify-between items-center mt-4">
                        <p class="text-gray-600 mb-2 sm:mb-0">Affichage de
                            <?php echo min($limit, $total_records - $offset); ?> sur <?php echo $total_records; ?>
                            produits
                        </p>
                        <div class="flex flex-wrap justify-center sm:justify-start gap-2">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search_query); ?>&sort_by=<?php echo urlencode($sort_by); ?>&sort_order=<?php echo urlencode($sort_order); ?>&filter_category=<?php echo urlencode($filter_category); ?>&filter_fournisseur=<?php echo urlencode($filter_fournisseur); ?>&filter_magasin=<?php echo urlencode($filter_magasin); ?><?php echo $filter_alerte_stock ? '&filter_alerte_stock=true' : ''; ?>"
                                    class="px-3 py-1 text-sm bg-gray-200 rounded-md hover:bg-gray-300">Précédent</a>
                            <?php endif; ?>

                            <?php
                            // Logique pour afficher un nombre limité de pages autour de la page actuelle
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);

                            if ($start_page > 1) {
                                echo '<a href="?page=1&search=' . urlencode($search_query) . '&sort_by=' . urlencode($sort_by) . '&sort_order=' . urlencode($sort_order) . '&filter_category=' . urlencode($filter_category) . '&filter_fournisseur=' . urlencode($filter_fournisseur) . '&filter_magasin=' . urlencode($filter_magasin) . ($filter_alerte_stock ? '&filter_alerte_stock=true' : '') . '" class="px-3 py-1 text-sm rounded-md bg-gray-200 hover:bg-gray-300">1</a>';
                                if ($start_page > 2) {
                                    echo '<span class="px-3 py-1 text-sm">...</span>';
                                }
                            }

                            for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search_query); ?>&sort_by=<?php echo urlencode($sort_by); ?>&sort_order=<?php echo urlencode($sort_order); ?>&filter_category=<?php echo urlencode($filter_category); ?>&filter_fournisseur=<?php echo urlencode($filter_fournisseur); ?>&filter_magasin=<?php echo urlencode($filter_magasin); ?><?php echo $filter_alerte_stock ? '&filter_alerte_stock=true' : ''; ?>"
                                    class="px-3 py-1 text-sm rounded-md <?php echo ($i == $page) ? 'bg-blue-600 text-white' : 'bg-gray-200 hover:bg-gray-300'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor;

                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<span class="px-3 py-1 text-sm">...</span>';
                                }
                                echo '<a href="?page=' . $total_pages . '&search=' . urlencode($search_query) . '&sort_by=' . urlencode($sort_by) . '&sort_order=' . urlencode($sort_order) . '&filter_category=' . urlencode($filter_category) . '&filter_fournisseur=' . urlencode($filter_fournisseur) . '&filter_magasin=' . urlencode($filter_magasin) . ($filter_alerte_stock ? '&filter_alerte_stock=true' : '') . '" class="px-3 py-1 text-sm rounded-md bg-gray-200 hover:bg-gray-300">' . $total_pages . '</a>';
                            }
                            ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search_query); ?>&sort_by=<?php echo urlencode($sort_by); ?>&sort_order=<?php echo urlencode($sort_order); ?>&filter_category=<?php echo urlencode($filter_category); ?>&filter_fournisseur=<?php echo urlencode($filter_fournisseur); ?>&filter_magasin=<?php echo urlencode($filter_magasin); ?><?php echo $filter_alerte_stock ? '&filter_alerte_stock=true' : ''; ?>"
                                    class="px-3 py-1 text-sm bg-gray-200 rounded-md hover:bg-gray-300">Suivant</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div id="produitModal"
                    class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full flex items-center justify-center hidden">
                    <div
                        class="bg-white p-8 rounded-lg shadow-xl max-w-lg w-full mx-auto relative max-h-[90vh] overflow-y-auto">
                        <button id="closeProduitModal"
                            class="absolute top-3 right-3 text-gray-600 hover:text-gray-900 text-2xl font-bold">&times;</button>
                        <h2 id="produitModalTitle" class="text-2xl font-bold mb-6 text-gray-800">Ajouter un Produit</h2>
                        <form id="produitForm" method="POST" action="" enctype="multipart/form-data">
                            <input type="hidden" name="produit_id" id="produit_id">
                            <div class="grid grid-cols-1 gap-4 mb-4 md:grid-cols-2">
                                <div>
                                    <label for="nom" class="block text-gray-700 text-sm font-bold mb-2">Nom du
                                        Produit:</label>
                                    <input type="text" name="nom" id="nom"
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                        required>
                                </div>
                                <div>
                                    <label for="categorie_id"
                                        class="block text-gray-700 text-sm font-bold mb-2">Catégorie:</label>
                                    <select name="categorie_id" id="categorie_id"
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                        <option value="">Sélectionner une catégorie</option>
                                        <?php foreach ($categories_disponibles as $cat): ?>
                                            <option value="<?php echo $cat['id']; ?>">
                                                <?php echo htmlspecialchars($cat['nom']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 gap-4 mb-4 md:grid-cols-2">
                                <div>
                                    <label for="fournisseur_id"
                                        class="block text-gray-700 text-sm font-bold mb-2">Fournisseur:</label>
                                    <select name="fournisseur_id" id="fournisseur_id"
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                        <option value="">Sélectionner un fournisseur</option>
                                        <?php foreach ($fournisseurs_disponibles as $four): ?>
                                            <option value="<?php echo $four['id']; ?>">
                                                <?php echo htmlspecialchars($four['nom']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="magasin_id"
                                        class="block text-gray-700 text-sm font-bold mb-2">Magasin:</label>
                                    <select name="magasin_id" id="magasin_id"
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                        <option value="">Sélectionner un magasin</option>
                                        <?php foreach ($magasins_disponibles as $mag): ?>
                                            <option value="<?php echo $mag['id']; ?>">
                                                <?php echo htmlspecialchars($mag['nom']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label for="description"
                                    class="block text-gray-700 text-sm font-bold mb-2">Description:</label>
                                <textarea name="description" id="description" rows="3"
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"></textarea>
                            </div>
                            <div class="mb-4">
                                <label for="image_produit" class="block text-gray-700 text-sm font-bold mb-2">Image du
                                    Produit (facultatif):</label>
                                <input type="file" name="image_produit" id="image_produit" accept="image/*"
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                <div id="current_image_preview" class="mt-2 hidden">
                                    <img src="" alt="Image Actuelle" class="w-32 h-32 object-cover rounded-md">
                                    <label class="inline-flex items-center mt-2">
                                        <input type="checkbox" name="remove_image" id="remove_image" value="true"
                                            class="form-checkbox">
                                        <span class="ml-2 text-red-600 text-sm">Supprimer l'image actuelle</span>
                                    </label>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 gap-4 mb-4 md:grid-cols-2">
                                <div>
                                    <label for="prix_achat" class="block text-gray-700 text-sm font-bold mb-2">Prix
                                        d'Achat (XOF):</label>
                                    <input type="number" step="0.01" name="prix_achat" id="prix_achat"
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                        required min="0">
                                </div>
                                <div>
                                    <label for="prix_vente" class="block text-gray-700 text-sm font-bold mb-2">Prix de
                                        Vente (XOF):</label>
                                    <input type="number" step="0.01" name="prix_vente" id="prix_vente"
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                        required min="0">
                                </div>
                            </div>
                            <div class="grid grid-cols-1 gap-4 mb-4 md:grid-cols-2">
                                <div>
                                    <label for="quantite_stock"
                                        class="block text-gray-700 text-sm font-bold mb-2">Quantité en Stock:</label>
                                    <input type="number" step="0.01" name="quantite_stock" id="quantite_stock"
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                        required min="0">
                                </div>
                                <div>
                                    <label for="seuil_alerte_stock"
                                        class="block text-gray-700 text-sm font-bold mb-2">Seuil d'Alerte Stock:</label>
                                    <input type="number" step="1" name="seuil_alerte_stock" id="seuil_alerte_stock"
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                        required min="0">
                                </div>
                            </div>
                            <div class="grid grid-cols-1 gap-4 mb-4 md:grid-cols-2">
                                <div>
                                    <label for="new_categorie_nom"
                                        class="block text-gray-700 text-sm font-bold mb-2 mt-2">Ou Nouvelle
                                        Catégorie:</label>
                                    <input type="text" name="new_categorie_nom" id="new_categorie_nom"
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                        placeholder="Nom nouvelle catégorie">
                                </div>
                                <div>
                                    <label for="new_fournisseur_nom"
                                        class="block text-gray-700 text-sm font-bold mb-2 mt-2">Ou Nouveau
                                        Fournisseur:</label>
                                    <input type="text" name="new_fournisseur_nom" id="new_fournisseur_nom"
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                        placeholder="Nom nouveau fournisseur">
                                </div>
                            </div>
                            <div class="flex justify-end">
                                <button type="submit" name="add_produit" id="submitProduitBtn"
                                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md shadow-md transition duration-300">
                                    Ajouter Produit
                                </button>
                                <button type="button" id="cancelProduitBtn"
                                    class="bg-gray-400 hover:bg-gray-500 text-white font-bold py-2 px-4 rounded-md shadow-md transition duration-300 ml-2">
                                    Annuler
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div id="deleteConfirmProduitModal"
                    class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full flex items-center justify-center hidden">
                    <div
                        class="bg-white p-8 rounded-lg shadow-xl max-w-sm w-full mx-auto relative max-h-[90vh] overflow-y-auto">
                        <button id="closeDeleteProduitModal"
                            class="absolute top-3 right-3 text-gray-600 hover:text-gray-900 text-2xl font-bold">&times;</button>
                        <h2 class="text-2xl font-bold mb-6 text-gray-800">Confirmer la Suppression</h2>
                        <p class="text-gray-700 mb-6">Êtes-vous sûr de vouloir supprimer ce produit ? Cette action est
                            irréversible.</p>
                        <form method="POST" action="">
                            <input type="hidden" name="produit_id" id="delete_produit_id">
                            <div class="flex justify-end">
                                <button type="submit" name="delete_produit"
                                    class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-md shadow-md transition duration-300">
                                    Supprimer
                                </button>
                                <button type="button" id="cancelDeleteProduitBtn"
                                    class="bg-gray-400 hover:bg-gray-500 text-white font-bold py-2 px-4 rounded-md shadow-md transition duration-300 ml-2">
                                    Annuler
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div id="completeStockModal"
                    class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full flex items-center justify-center hidden">
                    <div
                        class="bg-white p-8 rounded-lg shadow-xl max-w-sm w-full mx-auto relative max-h-[90vh] overflow-y-auto">
                        <button id="closeCompleteStockModal"
                            class="absolute top-3 right-3 text-gray-600 hover:text-gray-900 text-2xl font-bold">&times;</button>
                        <h2 class="text-2xl font-bold mb-6 text-gray-800">Compléter le Stock</h2>
                        <p class="text-gray-700 mb-4">Produit: <span id="produitNomStock" class="font-semibold"></span>
                        </p>
                        <form method="POST" action="">
                            <input type="hidden" name="produit_id_stock" id="produit_id_stock">
                            <div class="mb-4">
                                <label for="quantite_ajoutee"
                                    class="block text-gray-700 text-sm font-bold mb-2">Quantité à Ajouter:</label>
                                <input type="number" step="0.01" name="quantite_ajoutee" id="quantite_ajoutee"
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                    required min="0.01">
                            </div>
                            <div class="flex justify-end">
                                <button type="submit" name="complete_stock"
                                    class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-md shadow-md transition duration-300">
                                    Ajouter au Stock
                                </button>
                                <button type="button" id="cancelCompleteStockBtn"
                                    class="bg-gray-400 hover:bg-gray-500 text-white font-bold py-2 px-4 rounded-md shadow-md transition duration-300 ml-2">
                                    Annuler
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </main>

        <?php include '../../includes/footer.php'; ?>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // --- Gestion des Modals (Ajout/Modification) ---
        const produitModal = document.getElementById('produitModal');
        const addProduitBtn = document.getElementById('addProduitBtn');
        const closeProduitModal = document.getElementById('closeProduitModal');
        const cancelProduitBtn = document.getElementById('cancelProduitBtn');
        const produitModalTitle = document.getElementById('produitModalTitle');
        const submitProduitBtn = document.getElementById('submitProduitBtn');
        const produitForm = document.getElementById('produitForm');
        const produitIdInput = document.getElementById('produit_id');
        const nomInput = document.getElementById('nom');
        const descriptionInput = document.getElementById('description');
        const prixAchatInput = document.getElementById('prix_achat');
        const prixVenteInput = document.getElementById('prix_vente');
        const quantiteStockInput = document.getElementById('quantite_stock');
        const seuilAlerteStockInput = document.getElementById('seuil_alerte_stock');
        const categorieIdInput = document.getElementById('categorie_id');
        const fournisseurIdInput = document.getElementById('fournisseur_id');
        const magasinIdInput = document.getElementById('magasin_id'); // Nouveau champ magasin_id
        const imageProduitInput = document.getElementById('image_produit');
        const currentImagePreview = document.getElementById('current_image_preview');
        const currentImageTag = currentImagePreview.querySelector('img');
        const removeImageCheckbox = document.getElementById('remove_image');

        function openModal(modal) {
            modal.classList.remove('hidden');
            setTimeout(() => modal.classList.add('opacity-100'), 10);
        }

        function closeModal(modal) {
            modal.classList.remove('opacity-100');
            setTimeout(() => modal.classList.add('hidden'), 300); // Durée de la transition
        }

        addProduitBtn.addEventListener('click', function () {
            produitModalTitle.textContent = 'Ajouter un Produit';
            submitProduitBtn.textContent = 'Ajouter Produit';
            submitProduitBtn.name = 'add_produit';
            produitForm.reset();
            produitIdInput.value = '';
            currentImagePreview.classList.add('hidden');
            currentImageTag.src = '';
            removeImageCheckbox.checked = false;
            // MODIFICATION ICI : Rendre l'image facultative pour l'ajout
            imageProduitInput.required = false;
            openModal(produitModal);
        });

        document.querySelectorAll('.edit-produit-btn').forEach(button => {
            button.addEventListener('click', function () {
                produitModalTitle.textContent = 'Modifier le Produit';
                submitProduitBtn.textContent = 'Modifier Produit';
                submitProduitBtn.name = 'edit_produit';
                // L'image est déjà non requise pour la modification, pas de changement nécessaire ici
                imageProduitInput.required = false;

                produitIdInput.value = this.dataset.id;
                nomInput.value = this.dataset.nom;
                descriptionInput.value = this.dataset.description;
                prixAchatInput.value = this.dataset.prix_achat;
                prixVenteInput.value = this.dataset.prix_vente;
                quantiteStockInput.value = this.dataset.quantite_stock;
                seuilAlerteStockInput.value = this.dataset.seuil_alerte_stock;
                categorieIdInput.value = this.dataset.categorie_id;
                fournisseurIdInput.value = this.dataset.fournisseur_id;
                magasinIdInput.value = this.dataset.magasin_id; // Remplir le champ magasin_id

                if (this.dataset.image_url) {
                    currentImagePreview.classList.remove('hidden');
                    currentImageTag.src = this.dataset.image_url;
                } else {
                    currentImagePreview.classList.add('hidden');
                    currentImageTag.src = '';
                }
                removeImageCheckbox.checked = false;

                openModal(produitModal);
            });
        });

        closeProduitModal.addEventListener('click', function () {
            closeModal(produitModal);
        });

        cancelProduitBtn.addEventListener('click', function () {
            closeModal(produitModal);
        });

        // Fermer la modal si on clique en dehors
        window.addEventListener('click', function (event) {
            if (event.target == produitModal) {
                closeModal(produitModal);
            }
            if (event.target == deleteConfirmProduitModal) {
                closeModal(deleteConfirmProduitModal);
            }
            if (event.target == completeStockModal) {
                closeModal(completeStockModal);
            }
        });

        // --- Gestion de la suppression ---
        const deleteConfirmProduitModal = document.getElementById('deleteConfirmProduitModal');
        const closeDeleteProduitModal = document.getElementById('closeDeleteProduitModal');
        const cancelDeleteProduitBtn = document.getElementById('cancelDeleteProduitBtn');
        const deleteProduitIdInput = document.getElementById('delete_produit_id');

        document.querySelectorAll('.delete-produit-btn').forEach(button => {
            button.addEventListener('click', function () {
                deleteProduitIdInput.value = this.dataset.id;
                openModal(deleteConfirmProduitModal);
            });
        });

        if (closeDeleteProduitModal) {
            closeDeleteProduitModal.addEventListener('click', function () {
                closeModal(deleteConfirmProduitModal);
            });
        }

        if (cancelDeleteProduitBtn) {
            cancelDeleteProduitBtn.addEventListener('click', function () {
                closeModal(deleteConfirmProduitModal);
            });
        }

        // --- Gestion de la complétion de stock ---
        const completeStockModal = document.getElementById('completeStockModal');
        const closeCompleteStockModal = document.getElementById('closeCompleteStockModal');
        const cancelCompleteStockBtn = document.getElementById('cancelCompleteStockBtn');
        const produitIdStockInput = document.getElementById('produit_id_stock');
        const produitNomStockSpan = document.getElementById('produitNomStock');
        const quantiteAjouteeInput = document.getElementById('quantite_ajoutee');

        document.querySelectorAll('.complete-stock-btn').forEach(button => {
            button.addEventListener('click', function () {
                produitIdStockInput.value = this.dataset.id;
                produitNomStockSpan.textContent = this.dataset.nom;
                quantiteAjouteeInput.value = ''; // Réinitialiser la quantité ajoutée
                openModal(completeStockModal);
            });
        });

        if (closeCompleteStockModal) {
            closeCompleteStockModal.addEventListener('click', function () {
                closeModal(completeStockModal);
            });
        }
        if (cancelCompleteStockBtn) {
            cancelCompleteStockBtn.addEventListener('click', function () {
                closeModal(completeStockModal);
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

        // Masquer le message après 5 secondes s'il existe
        const messageContainer = document.getElementById('message-container');
        if (messageContainer) {
            setTimeout(() => {
                messageContainer.style.transition = 'opacity 1s ease-out';
                messageContainer.style.opacity = '0';
                setTimeout(() => messageContainer.remove(), 1000);
            }, 5000);
        }
    });
</script>
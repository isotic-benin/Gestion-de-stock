<?php
// modules/produits/gerer_transferts_stock.php
session_start();

// Inclure les fichiers nécessaires
include '../../db_connect.php';
include '../../includes/header.php'; // Inclut le header avec le début du HTML

// Vérifier si l'utilisateur est connecté et a le rôle approprié
// Les rôles autorisés pour la gestion des transferts sont Administrateur, Gérant
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !in_array($_SESSION['role'], ['Administrateur', 'Gérant'])) {
    header("location: " . BASE_URL . "public/login.php"); // Utilisation de BASE_URL
    exit;
}

$page_title = "Gestion des Transferts de Stock";

$message = ''; // Pour afficher les messages de succès ou d'erreur

// --- Logique de traitement des formulaires (CRUD Transferts) ---

// Gérer l'ajout ou la modification d'un transfert
if ($_SERVER["REQUEST_METHOD"] == "POST" && (isset($_POST['add_transfert']) || isset($_POST['edit_transfert']))) {
    $produit_id = sanitize_input($_POST['produit_id']);
    $magasin_source_id = sanitize_input($_POST['magasin_source_id']);
    $magasin_destination_id = sanitize_input($_POST['magasin_destination_id']);
    $quantite = (float) sanitize_input($_POST['quantite']);
    $description = sanitize_input($_POST['description']);
    $personnel_id_demande = $_SESSION['id']; // L'utilisateur connecté est celui qui demande

    if ($magasin_source_id == $magasin_destination_id) {
        $message = '<div class="alert alert-error">Le magasin source et le magasin de destination ne peuvent pas être les mêmes.</div>';
    } elseif ($quantite <= 0) {
        $message = '<div class="alert alert-error">La quantité à transférer doit être positive.</div>';
    } else {
        // Vérifier la quantité disponible dans le magasin source pour le produit sélectionné
        $sql_check_stock = "SELECT quantite_stock FROM produits WHERE id = ?";
        if ($stmt_check_stock = mysqli_prepare($conn, $sql_check_stock)) {
            mysqli_stmt_bind_param($stmt_check_stock, "i", $produit_id);
            mysqli_stmt_execute($stmt_check_stock);
            mysqli_stmt_bind_result($stmt_check_stock, $stock_disponible);
            mysqli_stmt_fetch($stmt_check_stock);
            mysqli_stmt_close($stmt_check_stock);

            // Pour la modification, considérer la quantité originale du transfert si elle était en attente
            $original_quantite = 0;
            if (isset($_POST['edit_transfert'])) {
                $transfert_id_edit = sanitize_input($_POST['transfert_id']);
                $sql_get_original_qty = "SELECT quantite FROM transferts_stock WHERE id = ? AND statut = 'en_attente'";
                if ($stmt_get_original_qty = mysqli_prepare($conn, $sql_get_original_qty)) {
                    mysqli_stmt_bind_param($stmt_get_original_qty, "i", $transfert_id_edit);
                    mysqli_stmt_execute($stmt_get_original_qty);
                    mysqli_stmt_bind_result($stmt_get_original_qty, $original_quantite);
                    mysqli_stmt_fetch($stmt_get_original_qty);
                    mysqli_stmt_close($stmt_get_original_qty);
                }
            }

            // Ajuster le stock disponible pour le scénario de modification
            $adjusted_stock_disponible = $stock_disponible + $original_quantite;

            if ($quantite > $adjusted_stock_disponible) {
                $message = '<div class="alert alert-error">Quantité insuffisante dans le stock du magasin source. Stock disponible: ' . $stock_disponible . '</div>';
            } else {
                if (isset($_POST['add_transfert'])) {
                    // Ajout
                    $sql = "INSERT INTO transferts_stock (produit_id, magasin_source_id, magasin_destination_id, quantite, description, personnel_id_demande) VALUES (?, ?, ?, ?, ?, ?)";
                    if ($stmt = mysqli_prepare($conn, $sql)) {
                        mysqli_stmt_bind_param($stmt, "iiidss", $produit_id, $magasin_source_id, $magasin_destination_id, $quantite, $description, $personnel_id_demande);
                        if (mysqli_stmt_execute($stmt)) {
                            $message = '<div class="alert alert-success">Demande de transfert ajoutée avec succès !</div>';
                        } else {
                            $message = '<div class="alert alert-error">Erreur lors de l\'ajout du transfert : ' . mysqli_error($conn) . '</div>';
                        }
                        mysqli_stmt_close($stmt);
                    }
                } elseif (isset($_POST['edit_transfert'])) {
                    // Modification
                    $transfert_id = sanitize_input($_POST['transfert_id']);
                    $statut = sanitize_input($_POST['statut']);

                    // Récupérer la quantité et le statut actuels du transfert pour ajustement de stock
                    $sql_current_transfert = "SELECT quantite, statut FROM transferts_stock WHERE id = ?";
                    $stmt_current_transfert = mysqli_prepare($conn, $sql_current_transfert);
                    mysqli_stmt_bind_param($stmt_current_transfert, "i", $transfert_id);
                    mysqli_stmt_execute($stmt_current_transfert);
                    mysqli_stmt_bind_result($stmt_current_transfert, $old_quantite, $old_statut);
                    mysqli_stmt_fetch($stmt_current_transfert);
                    mysqli_stmt_close($stmt_current_transfert);

                    mysqli_begin_transaction($conn);
                    try {
                        $sql = "UPDATE transferts_stock SET produit_id = ?, magasin_source_id = ?, magasin_destination_id = ?, quantite = ?, description = ?, statut = ? WHERE id = ?";
                        if ($stmt = mysqli_prepare($conn, $sql)) {
                            mysqli_stmt_bind_param($stmt, "iiidssi", $produit_id, $magasin_source_id, $magasin_destination_id, $quantite, $description, $statut, $transfert_id);
                            if (!mysqli_stmt_execute($stmt)) {
                                throw new Exception("Erreur lors de la modification du transfert : " . mysqli_error($conn));
                            }
                            mysqli_stmt_close($stmt);

                            // Si la quantité a changé alors que le statut est 'en_attente', ajuster le stock
                            if ($old_statut == 'en_attente' && $old_quantite != $quantite) {
                                // Remettre l'ancienne quantité au stock source
                                $sql_add_back = "UPDATE produits SET quantite_stock = quantite_stock + ? WHERE id = ?";
                                $stmt_add_back = mysqli_prepare($conn, $sql_add_back);
                                mysqli_stmt_bind_param($stmt_add_back, "di", $old_quantite, $produit_id);
                                if (!mysqli_stmt_execute($stmt_add_back)) {
                                    throw new Exception("Erreur lors de l'ajustement du stock (ajout ancienne quantité).");
                                }
                                mysqli_stmt_close($stmt_add_back);

                                // Déduire la nouvelle quantité du stock source
                                $sql_deduct_new = "UPDATE produits SET quantite_stock = quantite_stock - ? WHERE id = ?";
                                $stmt_deduct_new = mysqli_prepare($conn, $sql_deduct_new);
                                mysqli_stmt_bind_param($stmt_deduct_new, "di", $quantite, $produit_id);
                                if (!mysqli_stmt_execute($stmt_deduct_new)) {
                                    throw new Exception("Erreur lors de l'ajustement du stock (déduction nouvelle quantité).");
                                }
                                mysqli_stmt_close($stmt_deduct_new);
                            }

                            $message = '<div class="alert alert-success">Transfert modifié avec succès !</div>';
                            mysqli_commit($conn);
                        } else {
                            throw new Exception("Erreur de préparation de la requête de modification : " . mysqli_error($conn));
                        }
                    } catch (Exception $e) {
                        mysqli_rollback($conn);
                        $message = '<div class="alert alert-error">' . $e->getMessage() . '</div>';
                    }
                }
            }
        } else {
            $message = '<div class="alert alert-error">Erreur de préparation de la requête de vérification de stock : ' . mysqli_error($conn) . '</div>';
        }
    }
}

// Gérer la suppression d'un transfert
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_transfert'])) {
    $transfert_id = sanitize_input($_POST['transfert_id']);

    // Avant de supprimer, si le statut est 'en_attente', retourner la quantité au stock source
    $sql_get_transfert_details = "SELECT produit_id, quantite, statut FROM transferts_stock WHERE id = ?";
    if ($stmt_get_details = mysqli_prepare($conn, $sql_get_transfert_details)) {
        mysqli_stmt_bind_param($stmt_get_details, "i", $transfert_id);
        mysqli_stmt_execute($stmt_get_details);
        mysqli_stmt_bind_result($stmt_get_details, $prod_id_to_return, $qty_to_return, $status_to_check);
        mysqli_stmt_fetch($stmt_get_details);
        mysqli_stmt_close($stmt_get_details);

        mysqli_begin_transaction($conn);
        try {
            $sql = "DELETE FROM transferts_stock WHERE id = ?";
            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "i", $transfert_id);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Erreur lors de la suppression du transfert : " . mysqli_error($conn));
                }
                mysqli_stmt_close($stmt);

                if ($status_to_check == 'en_attente') {
                    $sql_return_stock = "UPDATE produits SET quantite_stock = quantite_stock + ? WHERE id = ?";
                    $stmt_return_stock = mysqli_prepare($conn, $sql_return_stock);
                    mysqli_stmt_bind_param($stmt_return_stock, "di", $qty_to_return, $prod_id_to_return);
                    if (!mysqli_stmt_execute($stmt_return_stock)) {
                        throw new Exception("Erreur lors du retour du stock au magasin source.");
                    }
                    mysqli_stmt_close($stmt_return_stock);
                }

                $message = '<div class="alert alert-success">Transfert supprimé avec succès !</div>';
                mysqli_commit($conn);
            } else {
                throw new Exception("Erreur de préparation de la requête de suppression : " . mysqli_error($conn));
            }
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $message = '<div class="alert alert-error">' . $e->getMessage() . '</div>';
        }
    } else {
        $message = '<div class="alert alert-error">Erreur de préparation de la requête de récupération des détails du transfert : ' . mysqli_error($conn) . '</div>';
    }
}

// Gérer les actions de confirmation, rejet, annulation
if ($_SERVER["REQUEST_METHOD"] == "POST" && (isset($_POST['confirm_transfert']) || isset($_POST['reject_transfert']) || isset($_POST['cancel_transfert']))) {
    $transfert_id = sanitize_input($_POST['transfert_id_action']);
    $personnel_id_action = $_SESSION['id'];
    $date_action = date('Y-m-d H:i:s');

    // Récupérer les détails du transfert
    $sql_get_transfert = "SELECT produit_id, magasin_source_id, magasin_destination_id, quantite, statut FROM transferts_stock WHERE id = ?";
    if ($stmt_get_transfert = mysqli_prepare($conn, $sql_get_transfert)) {
        mysqli_stmt_bind_param($stmt_get_transfert, "i", $transfert_id);
        mysqli_stmt_execute($stmt_get_transfert);
        mysqli_stmt_bind_result($stmt_get_transfert, $produit_id, $magasin_source_id, $magasin_destination_id, $quantite, $current_statut);
        mysqli_stmt_fetch($stmt_get_transfert);
        mysqli_stmt_close($stmt_get_transfert);

        if (!$produit_id) { // Si le transfert n'existe pas
            $message = '<div class="alert alert-error">Transfert introuvable.</div>';
            goto end_action;
        }

        // Empêcher l'action si le statut n'est pas 'en_attente'
        if ($current_statut != 'en_attente' && (isset($_POST['confirm_transfert']) || isset($_POST['reject_transfert']))) {
            $message = '<div class="alert alert-error">Impossible d\'effectuer cette action. Le transfert n\'est pas en attente.</div>';
            goto end_action;
        }
        // Empêcher l'annulation si déjà confirmé/rejeté
        if ($current_statut != 'en_attente' && isset($_POST['cancel_transfert'])) {
            $message = '<div class="alert alert-error">Impossible d\'annuler un transfert qui n\'est pas en attente.</div>';
            goto end_action;
        }


        mysqli_begin_transaction($conn);
        try {
            if (isset($_POST['confirm_transfert'])) {
                // Récupérer les détails du produit source pour la création éventuelle dans le magasin de destination
                $sql_get_product_details = "SELECT nom, description, image_url, prix_achat, prix_vente, fournisseur_id, categorie_id, seuil_alerte_stock FROM produits WHERE id = ?";
                $stmt_get_product_details = mysqli_prepare($conn, $sql_get_product_details);
                mysqli_stmt_bind_param($stmt_get_product_details, "i", $produit_id); // $produit_id ici est l'ID de l'instance du produit source
                mysqli_stmt_execute($stmt_get_product_details);
                $result_product_details = mysqli_stmt_get_result($stmt_get_product_details);
                $product_details = mysqli_fetch_assoc($result_product_details);
                mysqli_stmt_close($stmt_get_product_details);

                if (!$product_details) {
                    throw new Exception("Détails du produit source introuvables.");
                }

                // Vérifier la quantité disponible dans le magasin source avant de confirmer
                // Cette vérification est déjà faite lors de l'ajout/modification, mais une double vérification ne nuit pas
                $sql_check_stock_confirm = "SELECT quantite_stock FROM produits WHERE id = ?";
                if ($stmt_check_stock_confirm = mysqli_prepare($conn, $sql_check_stock_confirm)) {
                    mysqli_stmt_bind_param($stmt_check_stock_confirm, "i", $produit_id);
                    mysqli_stmt_execute($stmt_check_stock_confirm);
                    mysqli_stmt_bind_result($stmt_check_stock_confirm, $stock_disponible_confirm);
                    mysqli_stmt_fetch($stmt_check_stock_confirm);
                    mysqli_stmt_close($stmt_check_stock_confirm);

                    if ($quantite > $stock_disponible_confirm) {
                        throw new Exception("Quantité insuffisante dans le stock du magasin source pour confirmer le transfert. Stock disponible: " . $stock_disponible_confirm);
                    }
                } else {
                    throw new Exception("Erreur de préparation de la requête de vérification de stock.");
                }

                // 1. Décrémenter le stock du produit dans le magasin source (l'instance de produit d'origine)
                $sql_decr_stock = "UPDATE produits SET quantite_stock = quantite_stock - ? WHERE id = ?";
                if ($stmt_decr = mysqli_prepare($conn, $sql_decr_stock)) {
                    mysqli_stmt_bind_param($stmt_decr, "di", $quantite, $produit_id);
                    if (!mysqli_stmt_execute($stmt_decr)) {
                        throw new Exception("Erreur lors de la décrémentation du stock source.");
                    }
                    mysqli_stmt_close($stmt_decr);
                } else {
                    throw new Exception("Erreur de préparation de la requête de décrémentation de stock.");
                }

                // 2. Incrémenter le stock dans le magasin de destination
                // D'abord, vérifier si une entrée pour ce produit (par son nom) existe déjà dans le magasin de destination.
                $sql_check_dest_product = "SELECT id FROM produits WHERE nom = ? AND magasin_id = ?";
                $stmt_check_dest_product = mysqli_prepare($conn, $sql_check_dest_product);
                mysqli_stmt_bind_param($stmt_check_dest_product, "si", $product_details['nom'], $magasin_destination_id);
                mysqli_stmt_execute($stmt_check_dest_product);
                mysqli_stmt_store_result($stmt_check_dest_product);

                if (mysqli_stmt_num_rows($stmt_check_dest_product) > 0) {
                    // Le produit existe dans le magasin de destination, mettre à jour son stock
                    mysqli_stmt_bind_result($stmt_check_dest_product, $dest_produit_id);
                    mysqli_stmt_fetch($stmt_check_dest_product);
                    mysqli_stmt_close($stmt_check_dest_product);

                    $sql_incr_stock = "UPDATE produits SET quantite_stock = quantite_stock + ? WHERE id = ?";
                    if ($stmt_incr = mysqli_prepare($conn, $sql_incr_stock)) {
                        mysqli_stmt_bind_param($stmt_incr, "di", $quantite, $dest_produit_id);
                        if (!mysqli_stmt_execute($stmt_incr)) {
                            throw new Exception("Erreur lors de l'incrémentation du stock de destination.");
                        }
                        mysqli_stmt_close($stmt_incr);
                    } else {
                        throw new Exception("Erreur de préparation de la requête d'incrémentation de stock.");
                    }
                } else {
                    // Le produit n'existe pas dans le magasin de destination, créer une nouvelle entrée
                    mysqli_stmt_close($stmt_check_dest_product);

                    $sql_insert_dest_product = "INSERT INTO produits (nom, description, image_url, prix_achat, prix_vente, fournisseur_id, categorie_id, quantite_stock, seuil_alerte_stock, magasin_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    if ($stmt_insert = mysqli_prepare($conn, $sql_insert_dest_product)) {
                        mysqli_stmt_bind_param(
                            $stmt_insert,
                            "sssdiiidii",
                            $product_details['nom'],
                            $product_details['description'],
                            $product_details['image_url'],
                            $product_details['prix_achat'],
                            $product_details['prix_vente'],
                            $product_details['fournisseur_id'],
                            $product_details['categorie_id'],
                            $quantite, // Quantité initiale pour la nouvelle entrée
                            $product_details['seuil_alerte_stock'],
                            $magasin_destination_id
                        );
                        if (!mysqli_stmt_execute($stmt_insert)) {
                            throw new Exception("Erreur lors de la création de l'entrée du produit dans le magasin de destination.");
                        }
                        mysqli_stmt_close($stmt_insert);
                    } else {
                        throw new Exception("Erreur de préparation de la requête d'insertion du produit de destination.");
                    }
                }

                $new_statut = 'confirme';
                $message = '<div class="alert alert-success">Transfert confirmé avec succès ! Stocks mis à jour.</div>';

            } elseif (isset($_POST['reject_transfert'])) {
                $new_statut = 'rejete';
                $message = '<div class="alert alert-success">Transfert rejeté.</div>';
            } elseif (isset($_POST['cancel_transfert'])) {
                $new_statut = 'annule';
                $message = '<div class="alert alert-success">Transfert annulé.</div>';
            }

            // Mettre à jour le statut et la date d'action du transfert
            $sql_update_transfert = "UPDATE transferts_stock SET statut = ?, date_action = ?, personnel_id_action = ? WHERE id = ?";
            if ($stmt_update_transfert = mysqli_prepare($conn, $sql_update_transfert)) {
                mysqli_stmt_bind_param($stmt_update_transfert, "ssii", $new_statut, $date_action, $personnel_id_action, $transfert_id);
                if (!mysqli_stmt_execute($stmt_update_transfert)) {
                    throw new Exception("Erreur lors de la mise à jour du statut du transfert.");
                }
                mysqli_stmt_close($stmt_update_transfert);
            } else {
                throw new Exception("Erreur de préparation de la requête de mise à jour du statut.");
            }

            mysqli_commit($conn);
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $message = '<div class="alert alert-error">Erreur lors de l\'action sur le transfert : ' . $e->getMessage() . '</div>';
        }
    } else {
        $message = '<div class="alert alert-error">Erreur de préparation de la requête de récupération du transfert : ' . mysqli_error($conn) . '</div>';
    }
    end_action:
    ; // Étiquette pour le goto
}


// --- Logique de listage (Pagination, Recherche, Tri) ---

$limit = 10; // Nombre d'éléments par page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$search_query = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$sort_by = isset($_GET['sort_by']) ? sanitize_input($_GET['sort_by']) : 'ts.date_demande';
$sort_order = isset($_GET['sort_order']) && in_array(strtoupper($_GET['sort_order']), ['ASC', 'DESC']) ? $_GET['sort_order'] : 'DESC';
$filter_statut = isset($_GET['filter_statut']) ? sanitize_input($_GET['filter_statut']) : 'tous';
$filter_magasin_source = isset($_GET['filter_magasin_source']) ? sanitize_input($_GET['filter_magasin_source']) : '';
$filter_magasin_destination = isset($_GET['filter_magasin_destination']) ? sanitize_input($_GET['filter_magasin_destination']) : '';


// Construction de la clause WHERE pour la recherche et les filtres
$where_clause = '';
$params = [];
$param_types = '';

if (!empty($search_query)) {
    $where_clause .= " WHERE (p.nom LIKE ? OR ms.nom LIKE ? OR md.nom LIKE ? OR ts.description LIKE ?)";
    $search_param = '%' . $search_query . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'ssss';
}

if (!empty($filter_statut) && $filter_statut != 'tous') {
    if (empty($where_clause))
        $where_clause .= " WHERE";
    else
        $where_clause .= " AND";
    $where_clause .= " ts.statut = ?";
    $params[] = $filter_statut;
    $param_types .= 's';
}

if (!empty($filter_magasin_source)) {
    if (empty($where_clause))
        $where_clause .= " WHERE";
    else
        $where_clause .= " AND";
    $where_clause .= " ts.magasin_source_id = ?";
    $params[] = $filter_magasin_source;
    $param_types .= 'i';
}

if (!empty($filter_magasin_destination)) {
    if (empty($where_clause))
        $where_clause .= " WHERE";
    else
        $where_clause .= " AND";
    $where_clause .= " ts.magasin_destination_id = ?";
    $params[] = $filter_magasin_destination;
    $param_types .= 'i';
}


// Requête pour le nombre total de transferts (pour la pagination)
$count_sql = "SELECT COUNT(ts.id) AS total FROM transferts_stock ts
              JOIN produits p ON ts.produit_id = p.id
              JOIN magasins ms ON ts.magasin_source_id = ms.id
              JOIN magasins md ON ts.magasin_destination_id = md.id"
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


// Requête pour récupérer les transferts avec pagination, recherche, tri et filtres
$sql = "SELECT ts.id, ts.produit_id, ts.magasin_source_id, ts.magasin_destination_id, ts.quantite, ts.statut, ts.date_demande, ts.date_action, ts.description,
               p.nom AS nom_produit, ms.nom AS nom_magasin_source, md.nom AS nom_magasin_destination,
               p_demande.nom AS nom_personnel_demande, p_demande.prenom AS prenom_personnel_demande,
               p_action.nom AS nom_personnel_action, p_action.prenom AS prenom_personnel_action
        FROM transferts_stock ts
        JOIN produits p ON ts.produit_id = p.id
        JOIN magasins ms ON ts.magasin_source_id = ms.id
        JOIN magasins md ON ts.magasin_destination_id = md.id
        LEFT JOIN personnel p_demande ON ts.personnel_id_demande = p_demande.id
        LEFT JOIN personnel p_action ON ts.personnel_id_action = p_action.id"
    . $where_clause . " ORDER BY " . $sort_by . " " . $sort_order . " LIMIT ? OFFSET ?";

$stmt = mysqli_prepare($conn, $sql);
// Ajouter les paramètres de LIMIT et OFFSET aux paramètres existants
$params[] = $limit;
$params[] = $offset;
$param_types .= 'ii';

mysqli_stmt_bind_param($stmt, $param_types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$transferts = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);


// Récupérer la liste des produits pour les sélecteurs
$produits_disponibles = [];
// Modified SQL query to include magasin_id
$sql_produits = "SELECT id, nom, quantite_stock, magasin_id FROM produits ORDER BY nom ASC";
$result_produits = mysqli_query($conn, $sql_produits);
while ($row = mysqli_fetch_assoc($result_produits)) {
    $produits_disponibles[] = $row;
}
mysqli_free_result($result_produits);

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
                        <button id="addTransfertBtn"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md shadow-md transition duration-300 mb-4 md:mb-0">
                            <i class="fas fa-plus-circle mr-2"></i> Initier un Transfert
                        </button>
                        <form method="GET" action=""
                            class="flex flex-col md:flex-row gap-4 w-full md:w-auto items-center">
                            <input type="text" name="search" placeholder="Rechercher..."
                                value="<?php echo htmlspecialchars($search_query); ?>"
                                class="flex-grow shadow appearance-none border rounded-md py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            <select name="filter_statut"
                                class="shadow appearance-none border rounded-md py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                <option value="tous" <?php echo $filter_statut == 'tous' ? 'selected' : ''; ?>>Tous les
                                    statuts</option>
                                <option value="en_attente" <?php echo $filter_statut == 'en_attente' ? 'selected' : ''; ?>>En attente</option>
                                <option value="confirme" <?php echo $filter_statut == 'confirme' ? 'selected' : ''; ?>>
                                    Confirmé</option>
                                <option value="rejete" <?php echo $filter_statut == 'rejete' ? 'selected' : ''; ?>>Rejeté
                                </option>
                                <option value="annule" <?php echo $filter_statut == 'annule' ? 'selected' : ''; ?>>Annulé
                                </option>
                            </select>
                            <select name="filter_magasin_source"
                                class="shadow appearance-none border rounded-md py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                <option value="">Magasin Source</option>
                                <?php foreach ($magasins_disponibles as $mag): ?>
                                    <option value="<?php echo htmlspecialchars($mag['id']); ?>" <?php echo ($filter_magasin_source == $mag['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($mag['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select name="filter_magasin_destination"
                                class="shadow appearance-none border rounded-md py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                <option value="">Magasin Destination</option>
                                <?php foreach ($magasins_disponibles as $mag): ?>
                                    <option value="<?php echo htmlspecialchars($mag['id']); ?>" <?php echo ($filter_magasin_destination == $mag['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($mag['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select name="sort_by"
                                class="shadow appearance-none border rounded-md py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                <option value="ts.date_demande" <?php echo $sort_by == 'ts.date_demande' ? 'selected' : ''; ?>>Trier par Date Demande</option>
                                <option value="p.nom" <?php echo $sort_by == 'p.nom' ? 'selected' : ''; ?>>Trier par
                                    Produit</option>
                                <option value="ts.statut" <?php echo $sort_by == 'ts.statut' ? 'selected' : ''; ?>>Trier
                                    par Statut</option>
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
                                    <th class="px-4 py-2">ID</th>
                                    <th class="px-4 py-2">Produit</th>
                                    <th class="px-4 py-2">Magasin Source</th>
                                    <th class="px-4 py-2">Magasin Destination</th>
                                    <th class="px-4 py-2">Quantité</th>
                                    <th class="px-4 py-2">Statut</th>
                                    <th class="px-4 py-2">Date Demande</th>
                                    <th class="px-4 py-2">Date Action</th>
                                    <th class="px-4 py-2">Demandeur</th>
                                    <th class="px-4 py-2">Acteur</th>
                                    <th class="px-4 py-2">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($transferts)): ?>
                                    <?php foreach ($transferts as $transfert): ?>
                                        <tr>
                                            <td class="border px-4 py-2"><?php echo htmlspecialchars($transfert['id']); ?></td>
                                            <td class="border px-4 py-2">
                                                <?php echo htmlspecialchars($transfert['nom_produit']); ?>
                                            </td>
                                            <td class="border px-4 py-2">
                                                <?php echo htmlspecialchars($transfert['nom_magasin_source']); ?>
                                            </td>
                                            <td class="border px-4 py-2">
                                                <?php echo htmlspecialchars($transfert['nom_magasin_destination']); ?>
                                            </td>
                                            <td class="border px-4 py-2"><?php echo htmlspecialchars($transfert['quantite']); ?>
                                            </td>
                                            <td class="border px-4 py-2">
                                                <span class="px-2 py-1 rounded-full text-xs font-semibold
                                                    <?php
                                                    if ($transfert['statut'] == 'en_attente')
                                                        echo 'bg-blue-100 text-blue-800';
                                                    else if ($transfert['statut'] == 'confirme')
                                                        echo 'bg-green-100 text-green-800';
                                                    else if ($transfert['statut'] == 'rejete')
                                                        echo 'bg-red-100 text-red-800';
                                                    else if ($transfert['statut'] == 'annule')
                                                        echo 'bg-gray-100 text-gray-800';
                                                    ?>">
                                                    <?php echo htmlspecialchars(str_replace('_', ' ', ucfirst($transfert['statut']))); ?>
                                                </span>
                                            </td>
                                            <td class="border px-4 py-2">
                                                <?php echo date('d/m/Y H:i', strtotime($transfert['date_demande'])); ?>
                                            </td>
                                            <td class="border px-4 py-2">
                                                <?php echo $transfert['date_action'] ? date('d/m/Y H:i', strtotime($transfert['date_action'])) : 'N/A'; ?>
                                            </td>
                                            <td class="border px-4 py-2">
                                                <?php echo htmlspecialchars($transfert['nom_personnel_demande'] . ' ' . $transfert['prenom_personnel_demande'] ?? 'N/A'); ?>
                                            </td>
                                            <td class="border px-4 py-2">
                                                <?php echo htmlspecialchars($transfert['nom_personnel_action'] . ' ' . $transfert['prenom_personnel_action'] ?? 'N/A'); ?>
                                            </td>
                                            <td class="border px-4 py-2 action-buttons flex flex-col md:flex-row gap-2">
                                                <button
                                                    class="btn btn-edit edit-transfert-btn bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-1 px-3 rounded-md text-sm"
                                                    data-id="<?php echo htmlspecialchars($transfert['id']); ?>"
                                                    data-produit_id="<?php echo htmlspecialchars($transfert['produit_id']); ?>"
                                                    data-magasin_source_id="<?php echo htmlspecialchars($transfert['magasin_source_id']); ?>"
                                                    data-magasin_destination_id="<?php echo htmlspecialchars($transfert['magasin_destination_id']); ?>"
                                                    data-quantite="<?php echo htmlspecialchars($transfert['quantite']); ?>"
                                                    data-description="<?php echo htmlspecialchars($transfert['description']); ?>"
                                                    data-statut="<?php echo htmlspecialchars($transfert['statut']); ?>">
                                                    <i class="fas fa-edit"></i> Modifier
                                                </button>
                                                <?php if ($transfert['statut'] == 'en_attente'): ?>
                                                    <button
                                                        class="btn btn-success confirm-transfert-btn bg-green-500 hover:bg-green-600 text-white font-bold py-1 px-3 rounded-md text-sm"
                                                        data-id="<?php echo htmlspecialchars($transfert['id']); ?>">
                                                        <i class="fas fa-check-circle"></i> Confirmer
                                                    </button>
                                                    <button
                                                        class="btn btn-warning reject-transfert-btn bg-orange-500 hover:bg-orange-600 text-white font-bold py-1 px-3 rounded-md text-sm"
                                                        data-id="<?php echo htmlspecialchars($transfert['id']); ?>">
                                                        <i class="fas fa-times-circle"></i> Rejeter
                                                    </button>
                                                    <button
                                                        class="btn btn-info cancel-transfert-btn bg-blue-500 hover:bg-blue-600 text-white font-bold py-1 px-3 rounded-md text-sm"
                                                        data-id="<?php echo htmlspecialchars($transfert['id']); ?>">
                                                        <i class="fas fa-ban"></i> Annuler
                                                    </button>
                                                <?php endif; ?>
                                                <button
                                                    class="btn btn-delete delete-transfert-btn bg-red-500 hover:bg-red-600 text-white font-bold py-1 px-3 rounded-md text-sm"
                                                    data-id="<?php echo htmlspecialchars($transfert['id']); ?>">
                                                    <i class="fas fa-trash-alt"></i> Supprimer
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="11" class="text-center py-4 border">Aucun transfert de stock trouvé.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex flex-col sm:flex-row justify-between items-center mt-4">
                        <p class="text-gray-600 mb-2 sm:mb-0">Affichage de
                            <?php echo min($limit, $total_records - $offset); ?> sur <?php echo $total_records; ?>
                            transferts
                        </p>
                        <div class="flex flex-wrap justify-center sm:justify-start gap-2">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search_query); ?>&sort_by=<?php echo urlencode($sort_by); ?>&sort_order=<?php echo urlencode($sort_order); ?>&filter_statut=<?php echo urlencode($filter_statut); ?>&filter_magasin_source=<?php echo urlencode($filter_magasin_source); ?>&filter_magasin_destination=<?php echo urlencode($filter_magasin_destination); ?>"
                                    class="px-3 py-1 text-sm bg-gray-200 rounded-md hover:bg-gray-300">Précédent</a>
                            <?php endif; ?>

                            <?php
                            // Logique pour afficher un nombre limité de pages autour de la page actuelle
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);

                            if ($start_page > 1) {
                                echo '<a href="?page=1&search=' . urlencode($search_query) . '&sort_by=' . urlencode($sort_by) . '&sort_order=' . urlencode($sort_order) . '&filter_statut=' . urlencode($filter_statut) . '&filter_magasin_source=' . urlencode($filter_magasin_source) . '&filter_magasin_destination=' . urlencode($filter_magasin_destination) . '" class="px-3 py-1 text-sm rounded-md bg-gray-200 hover:bg-gray-300">1</a>';
                                if ($start_page > 2) {
                                    echo '<span class="px-3 py-1 text-sm">...</span>';
                                }
                            }

                            for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search_query); ?>&sort_by=<?php echo urlencode($sort_by); ?>&sort_order=<?php echo urlencode($sort_order); ?>&filter_statut=<?php echo urlencode($filter_statut); ?>&filter_magasin_source=<?php echo urlencode($filter_magasin_source); ?>&filter_magasin_destination=<?php echo urlencode($filter_magasin_destination); ?>"
                                    class="px-3 py-1 text-sm rounded-md <?php echo ($i == $page) ? 'bg-blue-600 text-white' : 'bg-gray-200 hover:bg-gray-300'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor;

                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<span class="px-3 py-1 text-sm">...</span>';
                                }
                                echo '<a href="?page=' . $total_pages . '&search=' . urlencode($search_query) . '&sort_by=' . urlencode($sort_by) . '&sort_order=' . urlencode($sort_order) . '&filter_statut=' . urlencode($filter_statut) . '&filter_magasin_source=' . urlencode($filter_magasin_source) . '&filter_magasin_destination=' . urlencode($filter_magasin_destination) . '" class="px-3 py-1 text-sm rounded-md bg-gray-200 hover:bg-gray-300">' . $total_pages . '</a>';
                            }
                            ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search_query); ?>&sort_by=<?php echo urlencode($sort_by); ?>&sort_order=<?php echo urlencode($sort_order); ?>&filter_statut=<?php echo urlencode($filter_statut); ?>&filter_magasin_source=<?php echo urlencode($filter_magasin_source); ?>&filter_magasin_destination=<?php echo urlencode($filter_magasin_destination); ?>"
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

<div id="transfertModal"
    class="modal hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full flex items-center justify-center">
    <div
        class="modal-content bg-white p-6 rounded-lg shadow-xl max-w-lg w-11/12 mx-auto relative max-h-[90vh] overflow-y-auto">
        <span
            class="modal-close-button absolute top-3 right-3 text-gray-600 hover:text-gray-900 text-2xl cursor-pointer">&times;</span>
        <h2 id="modalTitleTransfert" class="text-2xl font-bold text-gray-800 mb-4">Initier un Transfert de Stock</h2>
        <form id="transfertForm" method="POST" action="">
            <input type="hidden" id="transfertId" name="transfert_id">
            <div class="form-group mb-4">
                <label for="produit_id" class="block text-gray-700 text-sm font-bold mb-2">Produit:</label>
                <select id="produit_id" name="produit_id" required
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">-- Sélectionner un produit --</option>
                    <?php foreach ($produits_disponibles as $prod): ?>
                        <option value="<?php echo htmlspecialchars($prod['id']); ?>"
                            data-stock="<?php echo htmlspecialchars($prod['quantite_stock']); ?>"
                            data-magasin_id="<?php echo htmlspecialchars($prod['magasin_id']); ?>">
                            <?php echo htmlspecialchars($prod['nom']); ?> (Stock:
                            <?php echo htmlspecialchars($prod['quantite_stock']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <p id="produitStockInfo" class="text-sm text-gray-600 mt-1 hidden">Stock disponible: <span
                        id="availableStock"></span></p>
            </div>
            <div class="form-group mb-4">
                <label for="magasin_source_id" class="block text-gray-700 text-sm font-bold mb-2">Magasin
                    Source:</label>
                <select id="magasin_source_id" name="magasin_source_id" required
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">-- Sélectionner un magasin source --</option>
                    <?php foreach ($magasins_disponibles as $mag): ?>
                        <option value="<?php echo htmlspecialchars($mag['id']); ?>">
                            <?php echo htmlspecialchars($mag['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group mb-4">
                <label for="magasin_destination_id" class="block text-gray-700 text-sm font-bold mb-2">Magasin
                    Destination:</label>
                <select id="magasin_destination_id" name="magasin_destination_id" required
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="">-- Sélectionner un magasin de destination --</option>
                    <?php foreach ($magasins_disponibles as $mag): ?>
                        <option value="<?php echo htmlspecialchars($mag['id']); ?>">
                            <?php echo htmlspecialchars($mag['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group mb-4">
                <label for="quantite" class="block text-gray-700 text-sm font-bold mb-2">Quantité:</label>
                <input type="number" step="0.01" id="quantite" name="quantite" required min="1"
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div class="form-group mb-4">
                <label for="description_transfert"
                    class="block text-gray-700 text-sm font-bold mb-2">Description:</label>
                <textarea id="description_transfert" name="description" rows="3"
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"></textarea>
            </div>
            <div class="form-group mb-6" id="statutGroup" style="display: none;">
                <label for="statut" class="block text-gray-700 text-sm font-bold mb-2">Statut:</label>
                <select id="statut" name="statut"
                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <option value="en_attente">En attente</option>
                    <option value="confirme">Confirmé</option>
                    <option value="rejete">Rejeté</option>
                    <option value="annule">Annulé</option>
                </select>
            </div>
            <button type="submit" id="submitTransfertBtn" name="add_transfert"
                class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full">
                <i class="fas fa-save mr-2"></i> Enregistrer le Transfert
            </button>
        </form>
    </div>
</div>

<div id="deleteConfirmTransfertModal"
    class="modal hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full flex items-center justify-center">
    <div
        class="modal-content bg-white p-6 rounded-lg shadow-xl max-w-sm w-11/12 mx-auto text-center relative max-h-[90vh] overflow-y-auto">
        <span
            class="modal-close-button absolute top-3 right-3 text-gray-600 hover:text-gray-900 text-2xl cursor-pointer">&times;</span>
        <h2 class="text-2xl font-bold text-gray-800 mb-4">Confirmer la Suppression</h2>
        <p class="text-gray-700 mb-6">Êtes-vous sûr de vouloir supprimer ce transfert ? Cette action est irréversible.
        </p>
        <form id="deleteTransfertForm" method="POST" action="">
            <input type="hidden" id="deleteTransfertId" name="transfert_id">
            <div class="flex justify-center space-x-4">
                <button type="button" id="cancelDeleteTransfertBtn"
                    class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded-md transition duration-300">
                    Annuler
                </button>
                <button type="submit" name="delete_transfert"
                    class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-md transition duration-300">
                    Supprimer <i class="fas fa-trash-alt ml-2"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<div id="actionConfirmModal"
    class="modal hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full flex items-center justify-center">
    <div
        class="modal-content bg-white p-6 rounded-lg shadow-xl max-w-sm w-11/12 mx-auto text-center relative max-h-[90vh] overflow-y-auto">
        <span
            class="modal-close-button absolute top-3 right-3 text-gray-600 hover:text-gray-900 text-2xl cursor-pointer">&times;</span>
        <h2 id="actionModalTitle" class="text-2xl font-bold text-gray-800 mb-4">Confirmer l'Action</h2>
        <p id="actionModalMessage" class="text-gray-700 mb-6">Êtes-vous sûr de vouloir effectuer cette action sur le
            transfert ?</p>
        <form id="actionTransfertForm" method="POST" action="">
            <input type="hidden" id="actionTransfertId" name="transfert_id_action">
            <div class="flex justify-center space-x-4">
                <button type="button" id="cancelActionBtn"
                    class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded-md transition duration-300">
                    Annuler
                </button>
                <button type="submit" id="confirmActionButton"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md transition duration-300">
                    Confirmer
                </button>
            </div>
        </form>
    </div>
</div>



<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Refs pour le modal d'ajout/modification de transfert
        const transfertModal = document.getElementById('transfertModal');
        const addTransfertBtn = document.getElementById('addTransfertBtn');
        const modalCloseButtons = document.querySelectorAll('.modal-close-button');
        const transfertForm = document.getElementById('transfertForm');
        const modalTitleTransfert = document.getElementById('modalTitleTransfert');
        const submitTransfertBtn = document.getElementById('submitTransfertBtn');

        const transfertIdInput = document.getElementById('transfertId');
        const produitIdSelect = document.getElementById('produit_id');
        const magasinSourceIdSelect = document.getElementById('magasin_source_id');
        const magasinDestinationIdSelect = document.getElementById('magasin_destination_id');
        const quantiteInput = document.getElementById('quantite');
        const descriptionTransfertInput = document.getElementById('description_transfert');
        const statutGroup = document.getElementById('statutGroup');
        const statutSelect = document.getElementById('statut');
        const produitStockInfo = document.getElementById('produitStockInfo');
        const availableStockSpan = document.getElementById('availableStock');

        // Refs pour le modal de suppression
        const deleteConfirmTransfertModal = document.getElementById('deleteConfirmTransfertModal');
        const deleteTransfertIdInput = document.getElementById('deleteTransfertId');
        const cancelDeleteTransfertBtn = document.getElementById('cancelDeleteTransfertBtn');

        // Refs pour le modal de confirmation d'action
        const actionConfirmModal = document.getElementById('actionConfirmModal');
        const actionModalTitle = document.getElementById('actionModalTitle');
        const actionModalMessage = document.getElementById('actionModalMessage');
        const actionTransfertForm = document.getElementById('actionTransfertForm');
        const actionTransfertIdInput = document.getElementById('actionTransfertId');
        const confirmActionButton = document.getElementById('confirmActionButton');
        const cancelActionBtn = document.getElementById('cancelActionBtn');

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

        // --- Gestion des Modals de Transfert ---
        if (addTransfertBtn) {
            addTransfertBtn.addEventListener('click', function () {
                modalTitleTransfert.textContent = 'Initier un Transfert de Stock';
                submitTransfertBtn.name = 'add_transfert';
                submitTransfertBtn.innerHTML = '<i class="fas fa-save mr-2"></i> Enregistrer le Transfert';
                transfertForm.reset();
                transfertIdInput.value = '';
                statutGroup.style.display = 'none'; // Cacher le statut pour l'ajout
                produitStockInfo.classList.add('hidden'); // Cacher l'info stock
                magasinSourceIdSelect.disabled = false; // Rendre le champ modifiable pour l'ajout initial
                openModal(transfertModal);
            });
        }

        document.querySelectorAll('.edit-transfert-btn').forEach(button => {
            button.addEventListener('click', function () {
                modalTitleTransfert.textContent = 'Modifier le Transfert de Stock';
                submitTransfertBtn.name = 'edit_transfert';
                submitTransfertBtn.innerHTML = '<i class="fas fa-edit mr-2"></i> Mettre à jour';

                transfertIdInput.value = this.dataset.id;
                produitIdSelect.value = this.dataset.produit_id;
                magasinSourceIdSelect.value = this.dataset.magasin_source_id;
                magasinDestinationIdSelect.value = this.dataset.magasin_destination_id;
                quantiteInput.value = this.dataset.quantite;
                descriptionTransfertInput.value = this.dataset.description;
                statutSelect.value = this.dataset.statut;
                statutGroup.style.display = 'block'; // Afficher le statut pour la modification
                produitStockInfo.classList.add('hidden'); // Cacher l'info stock

                // Désactiver le champ magasin source en mode modification pour éviter les incohérences
                magasinSourceIdSelect.disabled = true;

                openModal(transfertModal);
            });
        });

        document.querySelectorAll('.delete-transfert-btn').forEach(button => {
            button.addEventListener('click', function () {
                deleteTransfertIdInput.value = this.dataset.id;
                openModal(deleteConfirmTransfertModal);
            });
        });

        // Afficher le stock disponible du produit sélectionné et pré-remplir le magasin source
        produitIdSelect.addEventListener('change', function () {
            const selectedOption = this.options[this.selectedIndex];
            const stock = selectedOption.dataset.stock;
            const magasinId = selectedOption.dataset.magasinId; // Get the associated magasin_id

            if (stock !== undefined) {
                availableStockSpan.textContent = stock;
                produitStockInfo.classList.remove('hidden');
            } else {
                produitStockInfo.classList.add('hidden');
            }

            if (magasinId !== undefined && magasinId !== '') { // Check if magasinId is not empty
                magasinSourceIdSelect.value = magasinId;
                magasinSourceIdSelect.disabled = true; // Make it read-only
            } else {
                magasinSourceIdSelect.value = ''; // Optionally clear if no magasin is associated
                magasinSourceIdSelect.disabled = false; // Make it editable if no magasin is associated
            }
        });


        // --- Gestion des Modals d'Action (Confirmer/Rejeter/Annuler) ---
        document.querySelectorAll('.confirm-transfert-btn').forEach(button => {
            button.addEventListener('click', function () {
                actionModalTitle.textContent = 'Confirmer le Transfert';
                actionModalMessage.textContent = 'Êtes-vous sûr de vouloir confirmer ce transfert ? Les stocks seront mis à jour.';
                actionTransfertIdInput.value = this.dataset.id;
                confirmActionButton.name = 'confirm_transfert';
                confirmActionButton.textContent = 'Confirmer';
                confirmActionButton.className = 'bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-md transition duration-300';
                openModal(actionConfirmModal);
            });
        });

        document.querySelectorAll('.reject-transfert-btn').forEach(button => {
            button.addEventListener('click', function () {
                actionModalTitle.textContent = 'Rejeter le Transfert';
                actionModalMessage.textContent = 'Êtes-vous sûr de vouloir rejeter ce transfert ? Le stock ne sera pas affecté.';
                actionTransfertIdInput.value = this.dataset.id;
                confirmActionButton.name = 'reject_transfert';
                confirmActionButton.textContent = 'Rejeter';
                confirmActionButton.className = 'bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 px-4 rounded-md transition duration-300';
                openModal(actionConfirmModal);
            });
        });

        document.querySelectorAll('.cancel-transfert-btn').forEach(button => {
            button.addEventListener('click', function () {
                actionModalTitle.textContent = 'Annuler le Transfert';
                actionModalMessage.textContent = 'Êtes-vous sûr de vouloir annuler ce transfert ? Le stock ne sera pas affecté.';
                actionTransfertIdInput.value = this.dataset.id;
                confirmActionButton.name = 'cancel_transfert';
                confirmActionButton.textContent = 'Annuler';
                confirmActionButton.className = 'bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-md transition duration-300';
                openModal(actionConfirmModal);
            });
        });

        // --- Gestion générale des fermetures de modals ---
        modalCloseButtons.forEach(button => {
            button.addEventListener('click', function () {
                closeModal(transfertModal);
                closeModal(deleteConfirmTransfertModal);
                closeModal(actionConfirmModal);
            });
        });

        window.addEventListener('click', function (event) {
            if (event.target == transfertModal) closeModal(transfertModal);
            if (event.target == deleteConfirmTransfertModal) closeModal(deleteConfirmTransfertModal);
            if (event.target == actionConfirmModal) closeModal(actionConfirmModal);
        });

        if (cancelDeleteTransfertBtn) {
            cancelDeleteTransfertBtn.addEventListener('click', function () {
                closeModal(deleteConfirmTransfertModal);
            });
        }

        if (cancelActionBtn) {
            cancelActionBtn.addEventListener('click', function () {
                closeModal(actionConfirmModal);
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
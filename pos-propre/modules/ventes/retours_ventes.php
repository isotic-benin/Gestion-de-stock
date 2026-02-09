<?php
// modules/ventes/retours_ventes.php
session_start();

// Inclure les fichiers nécessaires
include '../../db_connect.php';
include '../../includes/header.php'; // Inclut le header avec le début du HTML

// Vérifier si l'utilisateur est connecté et a le rôle approprié
// Les rôles autorisés pour les retours de vente sont Administrateur, Caissier, Vendeur
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !in_array($_SESSION['role'], ['Administrateur', 'Vendeur'])) {
    header("location: " . BASE_URL . "public/login.php"); // Utilisation de BASE_URL
    exit;
}

$page_title = "Gestion des Retours de Vente";

$message = ''; // Pour afficher les messages de succès ou d'erreur

// --- SECTION À REMPLACER dans retours_ventes.php (lignes de traitement des retours) ---
// Remplacer la section "Logique de traitement des retours de vente" par ce code amélioré :

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['process_return'])) {
    $vente_id = sanitize_input($_POST['return_vente_id']);
    $produit_id = sanitize_input($_POST['return_produit_id']);
    $quantite_retournee = (float) sanitize_input($_POST['quantite_retournee']);
    $raison_retour = sanitize_input($_POST['raison_retour']);
    $personnel_id = $_SESSION['id'];
    $magasin_id = sanitize_input($_POST['return_magasin_id']);

    if ($quantite_retournee <= 0) {
        $message = '<div class="alert alert-error">La quantité retournée doit être supérieure à zéro.</div>';
        goto end_return_process;
    }

    // Récupérer les détails de l'article dans la vente originale
    $sql_item_details = "SELECT dv.quantite, dv.prix_vente_unitaire, dv.reduction_ligne_montant, dv.reduction_ligne_pourcentage,
                                p.prix_achat AS produit_prix_achat, p.quantite_stock AS stock_actuel
                         FROM details_vente dv
                         JOIN produits p ON dv.produit_id = p.id
                         WHERE dv.vente_id = ? AND dv.produit_id = ? AND p.magasin_id = ?";
    if ($stmt_item_details = mysqli_prepare($conn, $sql_item_details)) {
        mysqli_stmt_bind_param($stmt_item_details, "iii", $vente_id, $produit_id, $magasin_id);
        mysqli_stmt_execute($stmt_item_details);
        $result_item_details = mysqli_stmt_get_result($stmt_item_details);
        $item_details = mysqli_fetch_assoc($result_item_details);
        mysqli_stmt_close($stmt_item_details);

        if (!$item_details) {
            $message = '<div class="alert alert-error">Produit non trouvé dans cette vente ou dans ce magasin.</div>';
            goto end_return_process;
        }

        // Vérifier la quantité déjà retournée
        $sql_returned_qty = "SELECT SUM(quantite_retournee) AS total_returned FROM retours_vente WHERE vente_id = ? AND produit_id = ?";
        $total_returned_qty = 0;
        if ($stmt_returned_qty = mysqli_prepare($conn, $sql_returned_qty)) {
            mysqli_stmt_bind_param($stmt_returned_qty, "ii", $vente_id, $produit_id);
            mysqli_stmt_execute($stmt_returned_qty);
            mysqli_stmt_bind_result($stmt_returned_qty, $total_returned_qty);
            mysqli_stmt_fetch($stmt_returned_qty);
            mysqli_stmt_close($stmt_returned_qty);
        }

        $total_returned_qty = $total_returned_qty ? $total_returned_qty : 0;
        $remaining_purchasable_qty = $item_details['quantite'] - $total_returned_qty;

        if ($quantite_retournee > $remaining_purchasable_qty) {
            $message = '<div class="alert alert-error">Quantité retournée dépasse la quantité vendue ou déjà retournée. Quantité restante à retourner: ' . number_format($remaining_purchasable_qty, 2) . '</div>';
            goto end_return_process;
        }

        // Calcul du montant remboursé
        $prix_unitaire_net = $item_details['prix_vente_unitaire'];
        if ($item_details['reduction_ligne_montant'] > 0 && $item_details['quantite'] > 0) {
            $reduction_par_unite = $item_details['reduction_ligne_montant'] / $item_details['quantite'];
            $prix_unitaire_net -= $reduction_par_unite;
        } elseif ($item_details['reduction_ligne_pourcentage'] > 0) {
            $prix_unitaire_net = $prix_unitaire_net * (1 - ($item_details['reduction_ligne_pourcentage'] / 100));
        }

        $montant_rembourse = $quantite_retournee * $prix_unitaire_net;

        mysqli_begin_transaction($conn);
        try {
            // 1. Enregistrer le retour de vente
            $sql_insert_return = "INSERT INTO retours_vente (vente_id, produit_id, quantite_retournee, montant_rembourse, raison_retour, personnel_id, magasin_id) VALUES (?, ?, ?, ?, ?, ?, ?)";
            if ($stmt_insert_return = mysqli_prepare($conn, $sql_insert_return)) {
                mysqli_stmt_bind_param($stmt_insert_return, "iiddsii", $vente_id, $produit_id, $quantite_retournee, $montant_rembourse, $raison_retour, $personnel_id, $magasin_id);
                if (!mysqli_stmt_execute($stmt_insert_return)) {
                    throw new Exception("Erreur lors de l'enregistrement du retour: " . mysqli_error($conn));
                }
                $return_id = mysqli_insert_id($conn);
                mysqli_stmt_close($stmt_insert_return);
            } else {
                throw new Exception("Erreur de préparation de la requête de retour: " . mysqli_error($conn));
            }

            // 2. CRITIQUE : Mettre à jour le stock du produit (incrémenter)
            // Cette requête DOIT s'exécuter pour ajouter la quantité retournée au stock
            $sql_update_stock = "UPDATE produits SET quantite_stock = quantite_stock + ? WHERE id = ? AND magasin_id = ?";
            if ($stmt_update_stock = mysqli_prepare($conn, $sql_update_stock)) {
                mysqli_stmt_bind_param($stmt_update_stock, "dii", $quantite_retournee, $produit_id, $magasin_id);
                if (!mysqli_stmt_execute($stmt_update_stock)) {
                    throw new Exception("Erreur lors de la mise à jour du stock: " . mysqli_error($conn));
                }

                // Vérifier que la mise à jour a bien affecté une ligne
                $rows_affected = mysqli_stmt_affected_rows($stmt_update_stock);
                mysqli_stmt_close($stmt_update_stock);

                if ($rows_affected === 0) {
                    throw new Exception("Aucun produit n'a été mis à jour. Vérifiez que le produit existe dans ce magasin.");
                }

                // DOUBLE VÉRIFICATION : Vérifier que le stock a bien été incrémenté
                $sql_verify_stock = "SELECT quantite_stock FROM produits WHERE id = ? AND magasin_id = ?";
                if ($stmt_verify = mysqli_prepare($conn, $sql_verify_stock)) {
                    mysqli_stmt_bind_param($stmt_verify, "ii", $produit_id, $magasin_id);
                    mysqli_stmt_execute($stmt_verify);
                    mysqli_stmt_bind_result($stmt_verify, $nouveau_stock);
                    mysqli_stmt_fetch($stmt_verify);
                    mysqli_stmt_close($stmt_verify);

                    $stock_attendu = $item_details['stock_actuel'] + $quantite_retournee;
                    if (abs($nouveau_stock - $stock_attendu) > 0.01) { // Tolérance de 0.01 pour les flottants
                        throw new Exception("Erreur de vérification : Le stock n'a pas été correctement mis à jour.");
                    }
                }

            } else {
                throw new Exception("Erreur de préparation de la requête de mise à jour de stock: " . mysqli_error($conn));
            }

            mysqli_commit($conn);
            $devise = get_currency();
            $message = '<div class="alert alert-success">Retour enregistré avec succès ! Montant remboursé: ' . number_format($montant_rembourse, 2, ',', ' ') . ' ' . $devise . '. Le stock a été mis à jour.</div>';
            $_SESSION['last_return_id'] = $return_id;
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $message = '<div class="alert alert-error">Erreur lors du traitement du retour : ' . $e->getMessage() . '</div>';
        }
    } else {
        $message = '<div class="alert alert-error">Erreur de préparation de la requête de détails de l\'article: ' . mysqli_error($conn) . '</div>';
    }

    end_return_process:
    ;
}

// --- Logique de listage (Pagination, Recherche, Tri) ---

$limit = 10; // Nombre d'éléments par page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$search_query = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$sort_by = isset($_GET['sort_by']) ? sanitize_input($_GET['sort_by']) : 'rv.date_retour';
$sort_order = isset($_GET['sort_order']) && in_array(strtoupper($_GET['sort_order']), ['ASC', 'DESC']) ? $_GET['sort_order'] : 'DESC';
$filter_magasin = isset($_GET['filter_magasin']) ? sanitize_input($_GET['filter_magasin']) : '';


// Construction de la clause WHERE pour la recherche et les filtres
$where_clause = '';
$params = [];
$param_types = '';

if (!empty($search_query)) {
    $where_clause .= " WHERE (p.nom LIKE ? OR v.id LIKE ? OR m.nom LIKE ?)";
    $search_param = '%' . $search_query . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'sss';
}

if (!empty($filter_magasin)) {
    if (empty($where_clause))
        $where_clause .= " WHERE";
    else
        $where_clause .= " AND";
    $where_clause .= " rv.magasin_id = ?";
    $params[] = $filter_magasin;
    $param_types .= 'i';
}


// Requête pour le nombre total de retours (pour la pagination)
$count_sql = "SELECT COUNT(rv.id) AS total FROM retours_vente rv
              JOIN produits p ON rv.produit_id = p.id
              JOIN ventes v ON rv.vente_id = v.id
              JOIN magasins m ON rv.magasin_id = m.id"
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


// Requête pour récupérer les retours avec pagination, recherche, tri et filtres
$sql = "SELECT rv.id, rv.vente_id, rv.produit_id, rv.quantite_retournee, rv.montant_rembourse, rv.date_retour, rv.raison_retour,
               p.nom AS produit_nom,
               m.nom AS nom_magasin,
               pers.nom AS personnel_nom, pers.prenom AS personnel_prenom,
               c.nom AS client_nom, c.prenom AS client_prenom, c.telephone AS client_telephone
        FROM retours_vente rv
        JOIN produits p ON rv.produit_id = p.id
        JOIN ventes v ON rv.vente_id = v.id
        JOIN magasins m ON rv.magasin_id = m.id
        JOIN personnel pers ON rv.personnel_id = pers.id
        LEFT JOIN clients c ON v.client_id = c.id"
    . $where_clause . " ORDER BY " . $sort_by . " " . $sort_order . " LIMIT ? OFFSET ?";

$stmt = mysqli_prepare($conn, $sql);
// Ajouter les paramètres de LIMIT et OFFSET aux paramètres existants
$params[] = $limit;
$params[] = $offset;
$param_types .= 'ii';

mysqli_stmt_bind_param($stmt, $param_types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$retours = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);


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
                        <button id="initiateReturnBtn"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md shadow-md transition duration-300 mb-4 md:mb-0">
                            <i class="fas fa-arrow-rotate-left mr-2"></i> Initier un Retour
                        </button>
                        <form method="GET" action=""
                            class="flex flex-col md:flex-row gap-4 w-full md:w-auto items-center">
                            <input type="text" name="search" placeholder="Rechercher..."
                                value="<?php echo htmlspecialchars($search_query); ?>"
                                class="flex-grow shadow appearance-none border rounded-md py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            <select name="filter_magasin"
                                class="shadow appearance-none border rounded-md py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                <option value="">Tous les Magasins</option>
                                <?php foreach ($magasins_disponibles as $mag): ?>
                                    <option value="<?php echo htmlspecialchars($mag['id']); ?>" <?php echo ($filter_magasin == $mag['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($mag['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select name="sort_by"
                                class="shadow appearance-none border rounded-md py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                <option value="rv.date_retour" <?php echo $sort_by == 'rv.date_retour' ? 'selected' : ''; ?>>Trier par Date Retour</option>
                                <option value="rv.montant_rembourse" <?php echo $sort_by == 'rv.montant_rembourse' ? 'selected' : ''; ?>>Trier par Montant Remboursé</option>
                                <option value="p.nom" <?php echo $sort_by == 'p.nom' ? 'selected' : ''; ?>>Trier par
                                    Produit</option>
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
                                    <th>ID Retour</th>
                                    <th>ID Vente</th>
                                    <th>Produit</th>
                                    <th>Quantité Retournée</th>
                                    <th>Montant Remboursé</th>
                                    <th>Date Retour</th>
                                    <th>Raison</th>
                                    <th>Magasin</th>
                                    <th>Personnel</th>
                                    <th>Client (Vente Origine)</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($retours)): ?>
                                    <?php foreach ($retours as $retour): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($retour['id']); ?></td>
                                            <td><?php echo htmlspecialchars($retour['vente_id']); ?></td>
                                            <td><?php echo htmlspecialchars($retour['produit_nom']); ?></td>
                                            <td><?php echo number_format($retour['quantite_retournee'], 2, ',', ' '); ?></td>
                                            <td class="font-semibold text-green-700">
                                                <?php echo number_format($retour['montant_rembourse'], 2, ',', ' ') . ' ' . htmlspecialchars(get_currency()); ?>
                                            </td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($retour['date_retour'])); ?></td>
                                            <td><?php echo htmlspecialchars($retour['raison_retour']); ?></td>
                                            <td><?php echo htmlspecialchars($retour['nom_magasin']); ?></td>
                                            <td><?php echo htmlspecialchars($retour['personnel_nom'] . ' ' . $retour['personnel_prenom']); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($retour['client_nom'] ? $retour['client_nom'] . ' ' . $retour['client_prenom'] : 'Anonyme'); ?>
                                            </td>
                                            <td class="action-buttons">
                                                <button
                                                    class="btn bg-purple-600 hover:bg-purple-700 text-white print-return-receipt-btn"
                                                    data-return_id="<?php echo htmlspecialchars($retour['id']); ?>">
                                                    <i class="fas fa-receipt"></i> Imprimer Reçu
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="11" class="text-center py-4">Aucun retour de vente trouvé.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="flex flex-col sm:flex-row justify-between items-center mt-4">
                        <p class="text-gray-600 mb-2 sm:mb-0">Affichage de
                            <?php echo min($limit, $total_records - $offset); ?> sur <?php echo $total_records; ?>
                            retours
                        </p>
                        <div class="flex flex-wrap justify-center sm:justify-start gap-2">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search_query); ?>&sort_by=<?php echo urlencode($sort_by); ?>&sort_order=<?php echo urlencode($sort_order); ?>&filter_magasin=<?php echo urlencode($filter_magasin); ?>"
                                    class="px-3 py-1 text-sm bg-gray-200 rounded-md hover:bg-gray-300">Précédent</a>
                            <?php endif; ?>

                            <?php
                            // Logique pour afficher un nombre limité de pages autour de la page actuelle
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);

                            if ($start_page > 1) {
                                echo '<a href="?page=1&search=' . urlencode($search_query) . '&sort_by=' . urlencode($sort_by) . '&sort_order=' . urlencode($sort_order) . '&filter_magasin=' . urlencode($filter_magasin) . '" class="px-3 py-1 text-sm rounded-md bg-gray-200 hover:bg-gray-300">1</a>';
                                if ($start_page > 2) {
                                    echo '<span class="px-3 py-1 text-sm">...</span>';
                                }
                            }

                            for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search_query); ?>&sort_by=<?php echo urlencode($sort_by); ?>&sort_order=<?php echo urlencode($sort_order); ?>&filter_magasin=<?php echo urlencode($filter_magasin); ?>"
                                    class="px-3 py-1 text-sm rounded-md <?php echo ($i == $page) ? 'bg-blue-600 text-white' : 'bg-gray-200 hover:bg-gray-300'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor;

                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<span class="px-3 py-1 text-sm">...</span>';
                                }
                                echo '<a href="?page=' . $total_pages . '&search=' . urlencode($search_query) . '&sort_by=' . urlencode($sort_by) . '&sort_order=' . urlencode($sort_order) . '&filter_magasin=' . urlencode($filter_magasin) . '" class="px-3 py-1 text-sm rounded-md bg-gray-200 hover:bg-gray-300">' . $total_pages . '</a>';
                            }
                            ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search_query); ?>&sort_by=<?php echo urlencode($sort_by); ?>&sort_order=<?php echo urlencode($sort_order); ?>&filter_magasin=<?php echo urlencode($filter_magasin); ?>"
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

<!-- Modal d'initiation de retour -->
<div id="initiateReturnModal" class="modal hidden">
    <div class="modal-content max-w-2xl">
        <span class="modal-close-button">&times;</span>
        <h2 class="text-2xl font-bold text-gray-800 mb-4">Initier un Retour de Vente</h2>
        <form id="returnForm" method="POST" action="">
            <input type="hidden" id="returnMagasinId" name="return_magasin_id">
            <div class="form-group">
                <label for="select_sale_id">Sélectionner une Vente:</label>
                <select id="select_sale_id" class="w-full p-2 border border-gray-300 rounded-md">
                    <option value="">-- Sélectionner une vente --</option>
                </select>
                <button type="button" id="loadSaleDetailsBtn" class="btn-primary mt-2 bg-blue-500 hover:bg-blue-600">
                    <i class="fas fa-search mr-2"></i> Charger Détails Vente
                </button>
            </div>

            <div id="saleDetailsContainer" class="mt-4 p-4 border border-gray-200 rounded-md bg-gray-50 hidden">
                <h3 class="font-bold text-lg mb-2">Détails de la Vente Originale:</h3>
                <p><strong>ID Vente:</strong> <span id="displayVenteId"></span></p>
                <p><strong>Date Vente:</strong> <span id="displayDateVente"></span></p>
                <p><strong>Client:</strong> <span id="displayClientNom"></span></p>
                <p><strong>Magasin:</strong> <span id="displayMagasinNom"></span></p>
                <p><strong>Montant Total:</strong> <span id="displayMontantTotal"></span>
                    <?php echo htmlspecialchars(get_currency()); ?></p>
                <hr class="my-3">
                <h3 class="font-bold text-lg mb-2">Articles de la Vente:</h3>
                <div class="table-container">
                    <table class="data-table w-full">
                        <thead>
                            <tr>
                                <th>Produit</th>
                                <th>Qté Vendue</th>
                                <th>Qté Déjà Retournée</th>
                                <th>Qté Max Retournable</th>
                                <th>Prix Unitaire</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="saleItemsBody">
                            <!-- Les articles de la vente seront chargés ici -->
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="returnProductForm" class="mt-4 p-4 border border-gray-200 rounded-md bg-gray-50 hidden">
                <h3 class="font-bold text-lg mb-2">Détails du Retour:</h3>
                <input type="hidden" id="returnVenteId" name="return_vente_id">
                <input type="hidden" id="returnProduitId" name="return_produit_id">
                <input type="hidden" id="returnProduitPrixVenteUnitaire" name="return_produit_prix_vente_unitaire">
                <input type="hidden" id="returnProduitReductionLigneMontant"
                    name="return_produit_reduction_ligne_montant">
                <input type="hidden" id="returnProduitReductionLignePourcentage"
                    name="return_produit_reduction_ligne_pourcentage">

                <p class="mb-2">Produit sélectionné: <strong id="selectedProduitNom"></strong></p>
                <p class="mb-2">Quantité max retournable: <strong id="selectedProduitMaxReturnQty"></strong></p>

                <div class="form-group">
                    <label for="quantite_retournee">Quantité à Retourner:</label>
                    <input type="number" step="0.01" id="quantite_retournee" name="quantite_retournee" required
                        min="0.01" class="w-full p-2 border border-gray-300 rounded-md">
                </div>
                <div class="form-group">
                    <label for="raison_retour">Raison du Retour:</label>
                    <textarea id="raison_retour" name="raison_retour" rows="3"
                        class="w-full p-2 border border-gray-300 rounded-md"></textarea>
                </div>
                <button type="submit" name="process_return"
                    class="btn-primary mt-4 w-full bg-green-600 hover:bg-green-700">
                    <i class="fas fa-undo-alt mr-2"></i> Enregistrer le Retour
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal de reçu de retour -->
<div id="returnReceiptModal" class="modal hidden">
    <div class="modal-content max-w-sm">
        <span class="modal-close-button">&times;</span>
        <h2 class="text-2xl font-bold text-gray-800 mb-4 text-center">Reçu de Retour</h2>
        <div id="returnReceiptContent"
            class="text-sm font-mono p-2 border border-gray-300 rounded-md overflow-auto max-h-96">
            <!-- Le contenu du reçu de retour sera généré ici par JavaScript -->
        </div>
        <div class="flex justify-center space-x-4 mt-4">
            <button id="printReturnReceiptBtn"
                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md transition duration-300">
                <i class="fas fa-print mr-2"></i> Imprimer le Reçu
            </button>
            <button id="newReturnBtn"
                class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-md transition duration-300">
                <i class="fas fa-plus mr-2"></i> Nouveau Retour
            </button>
        </div>
    </div>
</div>



<script>
    const currency = <?php echo json_encode(get_currency()); ?>;
    document.addEventListener('DOMContentLoaded', function () {
        // Refs pour le modal d'initiation de retour
        const initiateReturnModal = document.getElementById('initiateReturnModal');
        const initiateReturnBtn = document.getElementById('initiateReturnBtn');
        const modalCloseButtons = document.querySelectorAll('.modal-close-button');
        const selectSaleId = document.getElementById('select_sale_id');
        const loadSaleDetailsBtn = document.getElementById('loadSaleDetailsBtn');
        const saleDetailsContainer = document.getElementById('saleDetailsContainer');
        const displayVenteId = document.getElementById('displayVenteId');
        const displayDateVente = document.getElementById('displayDateVente');
        const displayClientNom = document.getElementById('displayClientNom');
        const displayMagasinNom = document.getElementById('displayMagasinNom');
        const displayMontantTotal = document.getElementById('displayMontantTotal');
        const saleItemsBody = document.getElementById('saleItemsBody');
        const returnProductForm = document.getElementById('returnProductForm');
        const selectedProduitNom = document.getElementById('selectedProduitNom');
        const selectedProduitMaxReturnQty = document.getElementById('selectedProduitMaxReturnQty');
        const quantiteRetourneeInput = document.getElementById('quantite_retournee');
        const returnVenteIdInput = document.getElementById('returnVenteId');
        const returnProduitIdInput = document.getElementById('returnProduitId');
        const returnProduitPrixVenteUnitaireInput = document.getElementById('returnProduitPrixVenteUnitaire');
        const returnProduitReductionLigneMontantInput = document.getElementById('returnProduitReductionLigneMontant');
        const returnProduitReductionLignePourcentageInput = document.getElementById('returnProduitReductionLignePourcentage');
        const returnMagasinIdInput = document.getElementById('returnMagasinId');

        // Refs pour le modal de reçu de retour
        const returnReceiptModal = document.getElementById('returnReceiptModal');
        const returnReceiptContent = document.getElementById('returnReceiptContent');
        const printReturnReceiptBtn = document.getElementById('printReturnReceiptBtn');
        const newReturnBtn = document.getElementById('newReturnBtn');

        let currentSaleDataForReturn = null; // Stocke les détails de la vente chargée pour le retour

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

        modalCloseButtons.forEach(button => {
            button.addEventListener('click', function () {
                closeModal(initiateReturnModal);
                closeModal(returnReceiptModal);
                // Réinitialiser les formulaires et affichages
                resetReturnForm();
            });
        });

        window.addEventListener('click', function (event) {
            if (event.target == initiateReturnModal) closeModal(initiateReturnModal);
            if (event.target == returnReceiptModal) closeModal(returnReceiptModal);
        });

        // --- Gestion de l'initiation d'un retour ---
        initiateReturnBtn.addEventListener('click', function () {
            openModal(initiateReturnModal);
            // Charger la liste des ventes dans le select
            loadSalesList();
        });

        // Fonction pour charger la liste des ventes dans le select
        function loadSalesList() {
            selectSaleId.innerHTML = '<option value="">-- Chargement des ventes... --</option>';
            fetch(`<?php echo BASE_URL; ?>modules/ventes/ajax_get_sales_list.php`)
                .then(response => response.json())
                .then(data => {
                    selectSaleId.innerHTML = '<option value="">-- Sélectionner une vente --</option>';
                    if (Array.isArray(data) && data.length > 0) {
                        data.forEach(vente => {
                            const clientInfo = vente.client_nom ? `${vente.client_nom} ${vente.client_prenom}` : 'Anonyme';
                            const dateVente = new Date(vente.date_vente).toLocaleDateString('fr-FR', { dateStyle: 'short' });
                            const option = document.createElement('option');
                            option.value = vente.id;
                            option.textContent = `#${vente.id} - ${dateVente} - ${clientInfo} - ${parseFloat(vente.montant_total).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency} - ${vente.nom_magasin}`;
                            selectSaleId.appendChild(option);
                        });
                    } else {
                        selectSaleId.innerHTML = '<option value="">-- Aucune vente trouvée --</option>';
                    }
                })
                .catch(error => {
                    console.error('Erreur lors du chargement de la liste des ventes:', error);
                    selectSaleId.innerHTML = '<option value="">-- Erreur de chargement --</option>';
                });
        }

        loadSaleDetailsBtn.addEventListener('click', function () {
            const saleId = selectSaleId.value;
            if (!saleId) {
                showMessage('error', 'Veuillez sélectionner une vente.');
                return;
            }

            saleDetailsContainer.classList.add('hidden');
            returnProductForm.classList.add('hidden');
            saleItemsBody.innerHTML = '<tr><td colspan="6" class="text-center py-4"><i class="fas fa-spinner fa-spin mr-2"></i> Chargement des articles...</td></tr>';

            fetch(`<?php echo BASE_URL; ?>modules/ventes/ajax_get_sale_items_for_return.php?sale_id=${saleId}`) // Utilisation de BASE_URL
                .then(response => response.json())
                .then(data => {
                    if (data.sale && data.items) {
                        currentSaleDataForReturn = data; // Stocker les données
                        displayVenteId.textContent = data.sale.id;
                        displayDateVente.textContent = new Date(data.sale.date_vente).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' });
                        displayClientNom.textContent = data.sale.client_nom ? `${htmlspecialchars(data.sale.client_nom)} ${htmlspecialchars(data.sale.client_prenom)}` : 'Anonyme';
                        displayMagasinNom.textContent = htmlspecialchars(data.sale.nom_magasin);
                        returnMagasinIdInput.value = data.sale.magasin_id; // Définir le magasin pour le retour
                        displayMontantTotal.textContent = parseFloat(data.sale.montant_total).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                        saleItemsBody.innerHTML = '';
                        if (data.items.length > 0) {
                            data.items.forEach(item => {
                                const remainingQty = item.quantite - item.total_returned_qty;
                                const row = document.createElement('tr');
                                row.innerHTML = `
                                <td>${htmlspecialchars(item.produit_nom)}</td>
                                <td>${parseFloat(item.quantite).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                <td>${parseFloat(item.total_returned_qty).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                <td class="font-semibold ${remainingQty <= 0 ? 'text-red-500' : 'text-green-600'}">${parseFloat(remainingQty).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                <td>${parseFloat(item.prix_vente_unitaire).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}</td>
                                <td>
                                    <button type="button" class="btn btn-sm bg-orange-500 hover:bg-orange-600 text-white select-product-for-return-btn"
                                            data-produit_id="${item.produit_id}"
                                            data-produit_nom="${htmlspecialchars(item.produit_nom)}"
                                            data-max_return_qty="${remainingQty}"
                                            data-prix_vente_unitaire="${item.prix_vente_unitaire}"
                                            data-reduction_ligne_montant="${item.reduction_ligne_montant}"
                                            data-reduction_ligne_pourcentage="${item.reduction_ligne_pourcentage}"
                                            ${remainingQty <= 0 ? 'disabled' : ''}>
                                        <i class="fas fa-hand-point-right"></i> Sélectionner
                                    </button>
                                </td>
                            `;
                                saleItemsBody.appendChild(row);
                            });
                            attachSelectProductForReturnListeners();
                        } else {
                            saleItemsBody.innerHTML = '<tr><td colspan="6" class="text-center py-4">Aucun article trouvé pour cette vente.</td></tr>';
                        }
                        saleDetailsContainer.classList.remove('hidden');
                    } else {
                        saleItemsBody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-red-500">Vente introuvable ou erreur.</td></tr>';
                    }
                })
                .catch(error => {
                    console.error('Erreur lors du chargement des détails de vente pour retour:', error);
                    saleItemsBody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-red-500">Erreur lors du chargement des détails.</td></tr>';
                });
        });

        function attachSelectProductForReturnListeners() {
            document.querySelectorAll('.select-product-for-return-btn').forEach(button => {
                button.addEventListener('click', function () {
                    const data = this.dataset;
                    selectedProduitNom.textContent = data.produit_nom;
                    selectedProduitMaxReturnQty.textContent = parseFloat(data.max_return_qty).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                    returnVenteIdInput.value = displayVenteId.textContent;
                    returnProduitIdInput.value = data.produit_id;
                    quantiteRetourneeInput.value = parseFloat(data.max_return_qty).toFixed(2); // Pré-remplir avec le max
                    quantiteRetourneeInput.max = parseFloat(data.max_return_qty).toFixed(2); // Définir le max
                    quantiteRetourneeInput.min = "0.01"; // Minimum 0.01

                    returnProduitPrixVenteUnitaireInput.value = data.prix_vente_unitaire;
                    returnProduitReductionLigneMontantInput.value = data.reduction_ligne_montant;
                    returnProduitReductionLignePourcentageInput.value = data.reduction_ligne_pourcentage;

                    returnProductForm.classList.remove('hidden');
                });
            });
        }

        function resetReturnForm() {
            selectSaleId.value = '';
            saleDetailsContainer.classList.add('hidden');
            returnProductForm.classList.add('hidden');
            saleItemsBody.innerHTML = '';
            currentSaleDataForReturn = null;
        }

        // --- Afficher le reçu de retour après un retour réussi ---
        <?php if (isset($_SESSION['last_return_id'])): ?>
            const lastReturnId = <?php echo json_encode($_SESSION['last_return_id']); ?>;
            fetch(`<?php echo BASE_URL; ?>modules/ventes/ajax_get_return_details_for_receipt.php?return_id=${lastReturnId}`) // Utilisation de BASE_URL
                .then(response => response.json())
                .then(data => {
                    if (data.return && data.sale && data.product && data.personnel && data.magasin) {
                        generateReturnReceiptContent(data);
                        openModal(returnReceiptModal);
                        // Nettoyer la session après affichage
                        <?php unset($_SESSION['last_return_id']); ?>
                    } else {
                        showMessage('error', 'Impossible de charger les détails du reçu de retour.');
                    }
                })
                .catch(error => {
                    console.error('Erreur lors du chargement du reçu de retour:', error);
                    showMessage('error', 'Erreur lors du chargement des détails du reçu de retour.');
                });
        <?php endif; ?>

        // --- Générer le contenu du reçu de retour ---
        function generateReturnReceiptContent(data) {
            const retour = data.return;
            const sale = data.sale;
            const product = data.product;
            const personnel = data.personnel;
            const magasin = data.magasin;
            const client = data.client; // Peut être null

            const companyInfo = <?php echo json_encode($company_info ?? []); ?>;
            const companyName = companyInfo.nom || "HGB Multi";
            const companyAddress = companyInfo.adresse || "123 Rue de la Quincaillerie, Ville, Pays";
            const companyPhone = companyInfo.telephone || "+229 01 20202020";

            let clientInfo = client ? `Client: ${htmlspecialchars(client.nom)} ${htmlspecialchars(client.prenom)}<br>` : 'Client: Anonyme<br>';
            if (client && client.telephone) clientInfo += `Tel: ${htmlspecialchars(client.telephone)}<br>`;

            const receiptHtml = `
            <div style="font-family: 'monospace', 'Courier New', monospace; font-size: 12px; width: 100%; margin: 0 auto; padding: 5px;">
                <div style="text-align: center; margin-bottom: 10px;">
                    <h3 style="margin: 0; font-size: 14px;">${companyName}</h3>
                    <p style="margin: 2px 0;">${companyAddress}</p>
                    <p style="margin: 2px 0;">Tel: ${companyPhone}</p>
                    <hr style="border-top: 1px dashed #ccc; margin: 8px 0;">
                    <p style="margin: 2px 0;"><strong>REÇU DE RETOUR</strong></p>
                    <p style="margin: 2px 0;">Date Retour: ${new Date(retour.date_retour).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' })}</p>
                    <p style="margin: 2px 0;">Retour #: ${retour.id}</p>
                    <p style="margin: 2px 0;">Vente Origine #: ${retour.vente_id}</p>
                    <p style="margin: 2px 0;">Magasin: ${htmlspecialchars(magasin.nom)}</p>
                    <hr style="border-top: 1px dashed #ccc; margin: 8px 0;">
                </div>
                <p style="margin: 2px 0;">${clientInfo}</p>
                <hr style="border-top: 1px dashed #ccc; margin: 8px 0;">
                <p style="margin: 2px 0;">Produit Retourné: <strong>${htmlspecialchars(product.nom)}</strong></p>
                <p style="margin: 2px 0;">Quantité Retournée: <strong>${parseFloat(retour.quantite_retournee).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</strong></p>
                <p style="margin: 2px 0;">Raison du Retour: ${htmlspecialchars(retour.raison_retour || 'Non spécifiée')}</p>
                <hr style="border-top: 1px dashed #ccc; margin: 8px 0;">
                <div style="text-align: right;">
                    <p style="margin: 2px 0; font-size: 14px; font-weight: bold;">MONTANT REMBOURSÉ: ${parseFloat(retour.montant_rembourse).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}</p>
                </div>
                <hr style="border-top: 1px dashed #ccc; margin: 8px 0;">
                <div style="text-align: center; margin-top: 10px;">
                    <p style="margin: 2px 0;">Retour traité par: ${htmlspecialchars(personnel.nom)} ${htmlspecialchars(personnel.prenom)}</p>
                    <p style="margin: 2px 0;">Merci de votre compréhension.</p>
                </div>
            </div>
        `;
            returnReceiptContent.innerHTML = receiptHtml;
        }

        // --- Impression du reçu de retour ---
        printReturnReceiptBtn.addEventListener('click', function () {
            const printContent = returnReceiptContent.innerHTML;
            const printWindow = window.open('', '', 'height=600,width=400');
            printWindow.document.write('<html><head><title>Reçu de Retour</title>');
            printWindow.document.write('<style>body { font-family: monospace; font-size: 12px; margin: 0; padding: 5px; }</style>');
            printWindow.document.write('</head><body>');
            printWindow.document.write(printContent);
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.print();
        });

        // --- Nouveau Retour (réinitialiser le modal) ---
        newReturnBtn.addEventListener('click', function () {
            closeModal(returnReceiptModal);
            resetReturnForm();
            showMessage('success', 'Prêt pour un nouveau retour !');
        });

        // --- Gestion du bouton "Imprimer Reçu" dans le tableau ---
        document.addEventListener('click', function (event) {
            if (event.target.closest('.print-return-receipt-btn')) {
                const button = event.target.closest('.print-return-receipt-btn');
                const returnId = button.getAttribute('data-return_id');
                if (returnId) {
                    fetch(`<?php echo BASE_URL; ?>modules/ventes/ajax_get_return_details_for_receipt.php?return_id=${returnId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.return && data.sale && data.product && data.personnel && data.magasin) {
                                generateReturnReceiptContent(data);
                                openModal(returnReceiptModal);
                            } else {
                                showMessage('error', 'Impossible de charger les détails du reçu de retour.');
                            }
                        })
                        .catch(error => {
                            console.error('Erreur lors du chargement du reçu de retour:', error);
                            showMessage('error', 'Erreur lors du chargement des détails du reçu de retour.');
                        });
                }
            }
        });

        // Fonction utilitaire pour échapper les caractères HTML
        function htmlspecialchars(str) {
            if (typeof str !== 'string' && typeof str !== 'number') return str;
            str = String(str);
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return str.replace(/[&<>"']/g, function (m) { return map[m]; });
        }

        // Fonction pour afficher les messages
        function showMessage(type, message) {
            const messageContainer = document.getElementById('message-container');
            if (!messageContainer) {
                alert(message);
                return;
            }
            const alertClass = type === 'error' ? 'alert-error' : 'alert-success';
            messageContainer.innerHTML = `<div class="alert ${alertClass}">${htmlspecialchars(message)}</div>`;
            messageContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            // Auto-hide après 5 secondes
            setTimeout(() => {
                messageContainer.innerHTML = '';
            }, 5000);
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
<?php
// modules/finance/gerer_dettes_magasins.php
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

// Vérifier les permissions d'accès au module dettes_entreprise (lecture)
if (!hasPermission($conn, 'dettes_entreprise', 'read')) {
    header("location: " . BASE_URL . "dashboard.php?error=access_denied");
    exit;
}

// Définir les permissions pour l'affichage conditionnel des boutons
$can_create = hasPermission($conn, 'dettes_entreprise', 'create');
$can_update = hasPermission($conn, 'dettes_entreprise', 'update');
$can_delete = hasPermission($conn, 'dettes_entreprise', 'delete');

$page_title = "Gestion des Dettes des Magasins";

$message = ''; // Pour afficher les messages de succès ou d'erreur

// --- Logique de traitement des formulaires (CRUD Dettes Magasins) ---

// Gérer l'ajout ou la modification d'une dette
if ($_SERVER["REQUEST_METHOD"] == "POST" && (isset($_POST['add_dette_magasin']) || isset($_POST['edit_dette_magasin']))) {
    // Vérifier les permissions avant traitement
    if (isset($_POST['add_dette_magasin']) && !$can_create) {
        $message = '<div class="alert alert-error">Vous n\'avez pas la permission d\'ajouter une dette.</div>';
        goto skip_dette_action;
    }
    if (isset($_POST['edit_dette_magasin']) && !$can_update) {
        $message = '<div class="alert alert-error">Vous n\'avez pas la permission de modifier une dette.</div>';
        goto skip_dette_action;
    }

    $description = sanitize_input($_POST['description']);
    $montant_initial = (float) sanitize_input($_POST['montant_initial']);
    $date_emission = sanitize_input($_POST['date_emission']);
    $date_echeance = !empty($_POST['date_echeance']) ? sanitize_input($_POST['date_echeance']) : NULL;
    $fournisseur_id = !empty($_POST['fournisseur_id']) ? sanitize_input($_POST['fournisseur_id']) : NULL;
    $magasin_id = !empty($_POST['magasin_id']) ? sanitize_input($_POST['magasin_id']) : NULL;
    $statut = sanitize_input($_POST['statut']);
    $personnel_id_enregistrement = $_SESSION['id']; // L'utilisateur connecté est celui qui enregistre

    if (isset($_POST['add_dette_magasin'])) {
        // Ajout
        $sql = "INSERT INTO dettes_magasins (description, montant_initial, montant_restant, date_emission, date_echeance, fournisseur_id, magasin_id, statut, personnel_id_enregistrement) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "sddsssiis", $description, $montant_initial, $montant_initial, $date_emission, $date_echeance, $fournisseur_id, $magasin_id, $statut, $personnel_id_enregistrement);
            if (mysqli_stmt_execute($stmt)) {
                $message = '<div class="alert alert-success">Dette de magasin ajoutée avec succès !</div>';
            } else {
                $message = '<div class="alert alert-error">Erreur lors de l\'ajout de la dette de magasin : ' . mysqli_error($conn) . '</div>';
            }
            mysqli_stmt_close($stmt);
        }
    } elseif (isset($_POST['edit_dette_magasin'])) {
        // Modification
        $dette_id = sanitize_input($_POST['dette_id']);
        // Récupérer le montant restant actuel pour ne pas le modifier directement
        $current_montant_restant = 0;
        $sql_current_montant = "SELECT montant_restant FROM dettes_magasins WHERE id = ?";
        if ($stmt_current = mysqli_prepare($conn, $sql_current_montant)) {
            mysqli_stmt_bind_param($stmt_current, "i", $dette_id);
            mysqli_stmt_execute($stmt_current);
            mysqli_stmt_bind_result($stmt_current, $current_montant_restant);
            mysqli_stmt_fetch($stmt_current);
            mysqli_stmt_close($stmt_current);
        }

        $sql = "UPDATE dettes_magasins SET description = ?, montant_initial = ?, date_emission = ?, date_echeance = ?, fournisseur_id = ?, magasin_id = ?, statut = ? WHERE id = ?";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "sdsssiis", $description, $montant_initial, $date_emission, $date_echeance, $fournisseur_id, $magasin_id, $statut, $dette_id);
            if (mysqli_stmt_execute($stmt)) {
                $message = '<div class="alert alert-success">Dette de magasin modifiée avec succès !</div>';
            } else {
                $message = '<div class="alert alert-error">Erreur lors de la modification de la dette de magasin : ' . mysqli_error($conn) . '</div>';
            }
            mysqli_stmt_close($stmt);
        }
    }
}

skip_dette_action:

// Gérer la suppression d'une dette
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_dette_magasin'])) {
    // Vérifier la permission de suppression
    if (!$can_delete) {
        $message = '<div class="alert alert-error">Vous n\'avez pas la permission de supprimer une dette.</div>';
        goto skip_delete_dette;
    }

    $dette_id = sanitize_input($_POST['dette_id']);
    $sql = "DELETE FROM dettes_magasins WHERE id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $dette_id);
        if (mysqli_stmt_execute($stmt)) {
            $message = '<div class="alert alert-success">Dette de magasin supprimée avec succès !</div>';
        } else {
            $message = '<div class="alert alert-error">Erreur lors de la suppression de la dette de magasin : ' . mysqli_error($conn) . '</div>';
        }
        mysqli_stmt_close($stmt);
    }
}

skip_delete_dette:

// Gérer l'enregistrement d'un paiement
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['enregistrer_paiement'])) {
    $dette_id = sanitize_input($_POST['paiement_dette_id']);
    $montant_paye = (float) sanitize_input($_POST['montant_paiement']);
    $description_paiement = sanitize_input($_POST['description_paiement']);
    $personnel_id = $_SESSION['id'];

    if ($montant_paye <= 0) {
        $message = '<div class="alert alert-error">Le montant du paiement doit être positif.</div>';
        goto end_paiement_process;
    }

    // Récupérer le montant restant actuel de la dette
    $current_montant_restant = 0;
    $sql_current_restant = "SELECT montant_restant FROM dettes_magasins WHERE id = ?";
    if ($stmt_current_restant = mysqli_prepare($conn, $sql_current_restant)) {
        mysqli_stmt_bind_param($stmt_current_restant, "i", $dette_id);
        mysqli_stmt_execute($stmt_current_restant);
        mysqli_stmt_bind_result($stmt_current_restant, $current_montant_restant);
        mysqli_stmt_fetch($stmt_current_restant);
        mysqli_stmt_close($stmt_current_restant);
    } else {
        $message = '<div class="alert alert-error">Erreur de préparation de la requête de solde restant : ' . mysqli_error($conn) . '</div>';
        goto end_paiement_process;
    }

    if ($montant_paye > $current_montant_restant) {
        $message = '<div class="alert alert-error">Le montant du paiement dépasse le montant restant dû. Montant restant: ' . number_format($current_montant_restant, 2, ',', ' ') . ' XOF</div>';
        goto end_paiement_process;
    }

    $new_montant_restant = $current_montant_restant - $montant_paye;
    $new_statut = 'partiellement_payee';
    if ($new_montant_restant <= 0) {
        $new_statut = 'payee';
    }

    mysqli_begin_transaction($conn);
    try {
        // 1. Mettre à jour la dette (Rien à faire sur la table dettes_magasins car le montant restant et le statut sont calculés)
        // On vérifie juste que le paiement ne dépasse pas le reste à payer (optionnel mais recommandé)
        // Mais pour l'instant on se contente d'enregistrer le paiement.

        // Mettre à jour le montant payé dans dettes_magasins
        $sql_update_dette = "UPDATE dettes_magasins SET montant_paye = montant_paye + ? WHERE id = ?";
        if ($stmt_update_dette = mysqli_prepare($conn, $sql_update_dette)) {
            mysqli_stmt_bind_param($stmt_update_dette, "di", $montant_paye, $dette_id);
            if (!mysqli_stmt_execute($stmt_update_dette)) {
                throw new Exception("Erreur lors de la mise à jour du montant payé.");
            }
            mysqli_stmt_close($stmt_update_dette);
        } else {
            throw new Exception("Erreur de préparation de la requête de mise à jour de dette.");
        }

        // 2. Enregistrer le paiement
        $sql_insert_paiement = "INSERT INTO paiements_dettes_magasins (dette_magasin_id, montant_paye, personnel_id, description) VALUES (?, ?, ?, ?)";
        if ($stmt_insert_paiement = mysqli_prepare($conn, $sql_insert_paiement)) {
            mysqli_stmt_bind_param($stmt_insert_paiement, "idss", $dette_id, $montant_paye, $personnel_id, $description_paiement);
            if (!mysqli_stmt_execute($stmt_insert_paiement)) {
                throw new Exception("Erreur lors de l'enregistrement du paiement.");
            }
            mysqli_stmt_close($stmt_insert_paiement);
        } else {
            throw new Exception("Erreur de préparation de la requête d'insertion de paiement.");
        }

        mysqli_commit($conn);
        $message = '<div class="alert alert-success">Paiement enregistré avec succès ! Statut de la dette: ' . $new_statut . '</div>';

    } catch (Exception $e) {
        mysqli_rollback($conn);
        $message = '<div class="alert alert-error">Erreur lors du traitement du paiement : ' . $e->getMessage() . '</div>';
    }

    end_paiement_process:
    ;
}


// --- Logique de listage (Pagination, Recherche, Tri) ---

$limit = 10; // Nombre d'éléments par page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$search_query = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$sort_by = isset($_GET['sort_by']) ? sanitize_input($_GET['sort_by']) : 'dm.date_dette'; // Corrigé: date_emission -> date_dette
// Validation de la colonne de tri pour éviter les erreurs SQL
$allowed_sort_columns = ['dm.date_dette', 'f.nom', 'm.nom', 'dm.description', 'dm.montant_total'];
if (!in_array($sort_by, $allowed_sort_columns)) {
    $sort_by = 'dm.date_dette';
}

$sort_order = isset($_GET['sort_order']) && in_array(strtoupper($_GET['sort_order']), ['ASC', 'DESC']) ? $_GET['sort_order'] : 'DESC';
$filter_statut = isset($_GET['filter_statut']) ? sanitize_input($_GET['filter_statut']) : 'tous';
$filter_fournisseur = isset($_GET['filter_fournisseur']) ? sanitize_input($_GET['filter_fournisseur']) : '';
$filter_magasin = isset($_GET['filter_magasin']) ? sanitize_input($_GET['filter_magasin']) : '';


// Construction de la clause WHERE pour la recherche et les filtres
$where_clause = '';
$params = [];
$param_types = '';

if (!empty($search_query)) {
    $where_clause .= " WHERE (dm.description LIKE ? OR f.nom LIKE ? OR m.nom LIKE ?)";
    $search_param = '%' . $search_query . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'sss';
}

if (!empty($filter_statut) && $filter_statut != 'tous') {
    // Filtrage sur colonne calculée : nécessite une logique différente ou HAVING
    // Pour simplifier, on filtre sur les montants directement dans le WHERE
    if (empty($where_clause))
        $where_clause .= " WHERE";
    else
        $where_clause .= " AND";

    if ($filter_statut == 'payee') {
        $where_clause .= " (dm.montant_paye >= dm.montant_total)"; // Payée
    } elseif ($filter_statut == 'partiellement_payee') {
        $where_clause .= " (dm.montant_paye > 0 AND dm.montant_paye < dm.montant_total)"; // Partiellement payée
    } elseif ($filter_statut == 'non_payee') {
        $where_clause .= " (dm.montant_paye = 0)"; // Non payée
    }
    // Pas de paramètre à binder ici car on compare des colonnes
}

if (!empty($filter_fournisseur)) {
    if (empty($where_clause))
        $where_clause .= " WHERE";
    else
        $where_clause .= " AND";
    $where_clause .= " dm.fournisseur_id = ?";
    $params[] = $filter_fournisseur;
    $param_types .= 'i';
}

if (!empty($filter_magasin)) {
    if (empty($where_clause))
        $where_clause .= " WHERE";
    else
        $where_clause .= " AND";
    $where_clause .= " dm.magasin_id = ?";
    $params[] = $filter_magasin;
    $param_types .= 'i';
}


// Requête pour le nombre total de dettes (pour la pagination)
$count_sql = "SELECT COUNT(dm.id) AS total FROM dettes_magasins dm
              LEFT JOIN fournisseurs f ON dm.fournisseur_id = f.id
              LEFT JOIN magasins m ON dm.magasin_id = m.id"
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


// Requête pour récupérer les dettes avec pagination, recherche, tri et filtres
$sql = "SELECT dm.id, dm.description, dm.montant_total, (dm.montant_total - dm.montant_paye) AS montant_restant, dm.date_dette AS date_emission, NULL AS date_echeance, 
                CASE 
                    WHEN dm.montant_paye >= dm.montant_total THEN 'payee'
                    WHEN dm.montant_paye > 0 THEN 'partiellement_payee'
                    ELSE 'non_payee'
                END AS statut, 
                dm.date_enregistrement,
                f.nom AS nom_fournisseur, m.nom AS nom_magasin,
                dm.fournisseur_id, dm.magasin_id
         FROM dettes_magasins dm
         LEFT JOIN fournisseurs f ON dm.fournisseur_id = f.id
         LEFT JOIN magasins m ON dm.magasin_id = m.id"
    . $where_clause . " ORDER BY " . $sort_by . " " . $sort_order . " LIMIT ? OFFSET ?";

$stmt = mysqli_prepare($conn, $sql);
// Ajouter les paramètres de LIMIT et OFFSET aux paramètres existants
$params[] = $limit;
$params[] = $offset;
$param_types .= 'ii';

mysqli_stmt_bind_param($stmt, $param_types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$dettes_magasins = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);


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
                        <button id="addDetteMagasinBtn"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md shadow-md transition duration-300 mb-4 md:mb-0">
                            <i class="fas fa-plus-circle mr-2"></i> Ajouter une Dette
                        </button>
                        <form method="GET" action=""
                            class="flex flex-col md:flex-row gap-4 w-full md:w-auto items-center">
                            <input type="text" name="search" placeholder="Rechercher..."
                                value="<?php echo htmlspecialchars($search_query); ?>"
                                class="flex-grow shadow appearance-none border rounded-md py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            <select name="filter_statut"
                                class="shadow appearance-none border rounded-md py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                <option value="tous" <?php echo $filter_statut == 'tous' ? 'selected' : ''; ?>>Tous les
                                    Statuts</option>
                                <option value="en_cours" <?php echo $filter_statut == 'en_cours' ? 'selected' : ''; ?>>En
                                    cours</option>
                                <option value="payee" <?php echo $filter_statut == 'payee' ? 'selected' : ''; ?>>Payée
                                </option>
                                <option value="partiellement_payee" <?php echo $filter_statut == 'partiellement_payee' ? 'selected' : ''; ?>>Partiellement Payée</option>
                                <option value="annulee" <?php echo $filter_statut == 'annulee' ? 'selected' : ''; ?>>
                                    Annulée</option>
                            </select>
                            <select name="filter_fournisseur"
                                class="shadow appearance-none border rounded-md py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                <option value="">Tous Fournisseurs</option>
                                <?php foreach ($fournisseurs_disponibles as $four): ?>
                                    <option value="<?php echo htmlspecialchars($four['id']); ?>" <?php echo ($filter_fournisseur == $four['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($four['nom']); ?>
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
                                <option value="dm.date_emission" <?php echo $sort_by == 'dm.date_emission' ? 'selected' : ''; ?>>Trier par Date Émission</option>
                                <option value="dm.montant_restant" <?php echo $sort_by == 'dm.montant_restant' ? 'selected' : ''; ?>>Trier par Montant Restant</option>
                                <option value="dm.date_echeance" <?php echo $sort_by == 'dm.date_echeance' ? 'selected' : ''; ?>>Trier par Date Échéance</option>
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
                                    <th>ID Dette</th>
                                    <th>Description</th>
                                    <th>Montant Initial</th>
                                    <th>Montant Restant</th>
                                    <th>Date Émission</th>
                                    <th>Date Échéance</th>
                                    <th>Fournisseur</th>
                                    <th>Magasin</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($dettes_magasins)): ?>
                                    <?php foreach ($dettes_magasins as $dette): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($dette['id']); ?></td>
                                            <td><?php echo htmlspecialchars($dette['description']); ?></td>
                                            <td><?php echo number_format($dette['montant_initial'], 2, ',', ' '); ?> XOF</td>
                                            <td
                                                class="font-semibold <?php echo ($dette['montant_restant'] > 0 && $dette['statut'] != 'payee' && $dette['statut'] != 'annulee') ? 'text-red-700' : 'text-green-700'; ?>">
                                                <?php echo number_format($dette['montant_restant'], 2, ',', ' '); ?> XOF
                                            </td>
                                            <td><?php echo date('d/m/Y', strtotime($dette['date_emission'])); ?></td>
                                            <td><?php echo $dette['date_echeance'] ? date('d/m/Y', strtotime($dette['date_echeance'])) : 'N/A'; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($dette['nom_fournisseur'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($dette['nom_magasin'] ?? 'N/A'); ?></td>
                                            <td>
                                                <span class="px-2 py-1 rounded-full text-xs font-semibold
                                                    <?php
                                                    if ($dette['statut'] == 'en_cours')
                                                        echo 'bg-yellow-100 text-yellow-800';
                                                    else if ($dette['statut'] == 'payee')
                                                        echo 'bg-green-100 text-green-800';
                                                    else if ($dette['statut'] == 'partiellement_payee')
                                                        echo 'bg-blue-100 text-blue-800';
                                                    else if ($dette['statut'] == 'annulee')
                                                        echo 'bg-gray-100 text-gray-800';
                                                    ?>">
                                                    <?php echo htmlspecialchars(str_replace('_', ' ', ucfirst($dette['statut']))); ?>
                                                </span>
                                            </td>
                                            <td class="action-buttons flex-wrap">
                                                <button class="btn btn-info view-payments-btn"
                                                    data-dette_id="<?php echo htmlspecialchars($dette['id']); ?>"
                                                    data-description="<?php echo htmlspecialchars($dette['description']); ?>">
                                                    <i class="fas fa-money-check-alt"></i> Paiements
                                                </button>
                                                <button class="btn btn-edit edit-dette-magasin-btn"
                                                    data-id="<?php echo htmlspecialchars($dette['id']); ?>"
                                                    data-description="<?php echo htmlspecialchars($dette['description']); ?>"
                                                    data-montant_initial="<?php echo htmlspecialchars($dette['montant_initial']); ?>"
                                                    data-date_emission="<?php echo htmlspecialchars($dette['date_emission']); ?>"
                                                    data-date_echeance="<?php echo htmlspecialchars($dette['date_echeance']); ?>"
                                                    data-fournisseur_id="<?php echo htmlspecialchars($dette['fournisseur_id']); ?>"
                                                    data-magasin_id="<?php echo htmlspecialchars($dette['magasin_id']); ?>"
                                                    data-statut="<?php echo htmlspecialchars($dette['statut']); ?>">
                                                    <i class="fas fa-edit"></i> Modifier
                                                </button>
                                                <?php if ($dette['statut'] != 'payee' && $dette['statut'] != 'annulee'): ?>
                                                    <button class="btn btn-success record-payment-btn"
                                                        data-dette_id="<?php echo htmlspecialchars($dette['id']); ?>"
                                                        data-description="<?php echo htmlspecialchars($dette['description']); ?>"
                                                        data-montant_restant="<?php echo htmlspecialchars($dette['montant_restant']); ?>">
                                                        <i class="fas fa-dollar-sign"></i> Enregistrer Paiement
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-delete delete-dette-magasin-btn"
                                                    data-id="<?php echo htmlspecialchars($dette['id']); ?>">
                                                    <i class="fas fa-trash-alt"></i> Supprimer
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="10" class="text-center py-4">Aucune dette de magasin trouvée.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="flex flex-col sm:flex-row justify-between items-center mt-4">
                        <p class="text-gray-600 mb-2 sm:mb-0">Affichage de
                            <?php echo min($limit, $total_records - $offset); ?> sur <?php echo $total_records; ?>
                            dettes
                        </p>
                        <div class="flex flex-wrap justify-center sm:justify-start gap-2">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search_query); ?>&sort_by=<?php echo urlencode($sort_by); ?>&sort_order=<?php echo urlencode($sort_order); ?>&filter_statut=<?php echo urlencode($filter_statut); ?>&filter_fournisseur=<?php echo urlencode($filter_fournisseur); ?>&filter_magasin=<?php echo urlencode($filter_magasin); ?>"
                                    class="px-3 py-1 text-sm bg-gray-200 rounded-md hover:bg-gray-300">Précédent</a>
                            <?php endif; ?>

                            <?php
                            // Logique pour afficher un nombre limité de pages autour de la page actuelle
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);

                            if ($start_page > 1) {
                                echo '<a href="?page=1&search=' . urlencode($search_query) . '&sort_by=' . urlencode($sort_by) . '&sort_order=' . urlencode($sort_order) . '&filter_statut=' . urlencode($filter_statut) . '&filter_fournisseur=' . urlencode($filter_fournisseur) . '&filter_magasin=' . urlencode($filter_magasin) . '" class="px-3 py-1 text-sm rounded-md bg-gray-200 hover:bg-gray-300">1</a>';
                                if ($start_page > 2) {
                                    echo '<span class="px-3 py-1 text-sm">...</span>';
                                }
                            }

                            for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search_query); ?>&sort_by=<?php echo urlencode($sort_by); ?>&sort_order=<?php echo urlencode($sort_order); ?>&filter_statut=<?php echo urlencode($filter_statut); ?>&filter_fournisseur=<?php echo urlencode($filter_fournisseur); ?>&filter_magasin=<?php echo urlencode($filter_magasin); ?>"
                                    class="px-3 py-1 text-sm rounded-md <?php echo ($i == $page) ? 'bg-blue-600 text-white' : 'bg-gray-200 hover:bg-gray-300'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor;

                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<span class="px-3 py-1 text-sm">...</span>';
                                }
                                echo '<a href="?page=' . $total_pages . '&search=' . urlencode($search_query) . '&sort_by=' . urlencode($sort_by) . '&sort_order=' . urlencode($sort_order) . '&filter_statut=' . urlencode($filter_statut) . '&filter_fournisseur=' . urlencode($filter_fournisseur) . '&filter_magasin=' . urlencode($filter_magasin) . '" class="px-3 py-1 text-sm rounded-md bg-gray-200 hover:bg-gray-300">' . $total_pages . '</a>';
                            }
                            ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search_query); ?>&sort_by=<?php echo urlencode($sort_by); ?>&sort_order=<?php echo urlencode($sort_order); ?>&filter_statut=<?php echo urlencode($filter_statut); ?>&filter_fournisseur=<?php echo urlencode($filter_fournisseur); ?>&filter_magasin=<?php echo urlencode($filter_magasin); ?>"
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

<!-- Modal d'ajout/modification de dette magasin -->
<div id="detteMagasinModal" class="modal hidden">
    <div class="modal-content max-w-lg">
        <span class="modal-close-button">&times;</span>
        <h2 id="modalTitleDetteMagasin" class="text-2xl font-bold text-gray-800 mb-4">Ajouter une Dette Magasin</h2>
        <form id="detteMagasinForm" method="POST" action="">
            <input type="hidden" id="detteId" name="dette_id">
            <div class="form-group">
                <label for="description_dette_magasin">Description:</label>
                <textarea id="description_dette_magasin" name="description" rows="3" required
                    class="w-full p-2 border border-gray-300 rounded-md"></textarea>
            </div>
            <div class="form-group">
                <label for="montant_initial">Montant Initial:</label>
                <input type="number" step="0.01" id="montant_initial" name="montant_initial" required min="0.01"
                    class="w-full p-2 border border-gray-300 rounded-md">
            </div>
            <div class="form-group">
                <label for="date_emission">Date d'Émission:</label>
                <input type="date" id="date_emission" name="date_emission" required
                    class="w-full p-2 border border-gray-300 rounded-md">
            </div>
            <div class="form-group">
                <label for="date_echeance">Date d'Échéance (Optionnel):</label>
                <input type="date" id="date_echeance" name="date_echeance"
                    class="w-full p-2 border border-gray-300 rounded-md">
            </div>
            <div class="form-group">
                <label for="fournisseur_id">Fournisseur (Optionnel):</label>
                <select id="fournisseur_id" name="fournisseur_id" class="w-full p-2 border border-gray-300 rounded-md">
                    <option value="">-- Aucun Fournisseur --</option>
                    <?php foreach ($fournisseurs_disponibles as $four): ?>
                        <option value="<?php echo htmlspecialchars($four['id']); ?>">
                            <?php echo htmlspecialchars($four['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="magasin_id_dette">Magasin Associé (Optionnel):</label>
                <select id="magasin_id_dette" name="magasin_id" class="w-full p-2 border border-gray-300 rounded-md">
                    <option value="">-- Dépense Générale --</option>
                    <?php foreach ($magasins_disponibles as $mag): ?>
                        <option value="<?php echo htmlspecialchars($mag['id']); ?>">
                            <?php echo htmlspecialchars($mag['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="statut">Statut:</label>
                <select id="statut" name="statut" required class="w-full p-2 border border-gray-300 rounded-md">
                    <option value="en_cours">En cours</option>
                    <option value="payee">Payée</option>
                    <option value="partiellement_payee">Partiellement Payée</option>
                    <option value="annulee">Annulée</option>
                </select>
            </div>
            <button type="submit" id="submitDetteMagasinBtn" name="add_dette_magasin" class="btn-primary mt-4 w-full">
                <i class="fas fa-save mr-2"></i> Enregistrer
            </button>
        </form>
    </div>
</div>

<!-- Modal de confirmation de suppression -->
<div id="deleteConfirmDetteMagasinModal" class="modal hidden">
    <div class="modal-content max-w-sm text-center">
        <span class="modal-close-button">&times;</span>
        <h2 class="text-2xl font-bold text-gray-800 mb-4">Confirmer la Suppression</h2>
        <p class="text-gray-700 mb-6">Êtes-vous sûr de vouloir supprimer cette dette de magasin ? Cette action est
            irréversible et supprimera tous les paiements associés.</p>
        <form id="deleteDetteMagasinForm" method="POST" action="">
            <input type="hidden" id="deleteDetteMagasinId" name="dette_id">
            <div class="flex justify-center space-x-4">
                <button type="button" id="cancelDeleteDetteMagasinBtn"
                    class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded-md transition duration-300">
                    Annuler
                </button>
                <button type="submit" name="delete_dette_magasin"
                    class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-md transition duration-300">
                    Supprimer <i class="fas fa-trash-alt ml-2"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal d'enregistrement de paiement -->
<div id="recordPaymentModal" class="modal hidden">
    <div class="modal-content max-w-md">
        <span class="modal-close-button">&times;</span>
        <h2 class="text-2xl font-bold text-gray-800 mb-4">Enregistrer un Paiement</h2>
        <p class="text-gray-700 mb-2">Dette: <span id="paymentDetteDescription" class="font-semibold"></span></p>
        <p class="text-gray-700 mb-6">Montant Restant Dû: <span id="paymentMontantRestant"
                class="font-semibold text-red-700"></span> XOF</p>
        <form id="recordPaymentForm" method="POST" action="">
            <input type="hidden" id="paiementDetteId" name="paiement_dette_id">
            <div class="form-group">
                <label for="montant_paiement">Montant du Paiement:</label>
                <input type="number" step="0.01" id="montant_paiement" name="montant_paiement" required min="0.01"
                    class="w-full p-2 border border-gray-300 rounded-md">
            </div>
            <div class="form-group">
                <label for="description_paiement">Description du Paiement (Optionnel):</label>
                <textarea id="description_paiement" name="description_paiement" rows="2"
                    class="w-full p-2 border border-gray-300 rounded-md"></textarea>
            </div>
            <button type="submit" name="enregistrer_paiement"
                class="btn-primary mt-4 w-full bg-green-600 hover:bg-green-700">
                <i class="fas fa-check-circle mr-2"></i> Enregistrer le Paiement
            </button>
        </form>
    </div>
</div>

<!-- Modal de l'historique des paiements -->
<div id="paymentsHistoryModal" class="modal hidden">
    <div class="modal-content max-w-2xl">
        <span class="modal-close-button">&times;</span>
        <h2 class="text-2xl font-bold text-gray-800 mb-4">Historique des Paiements pour: <span
                id="historyDetteDescription"></span></h2>
        <div class="table-container mb-4">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID Paiement</th>
                        <th>Montant Payé</th>
                        <th>Date Paiement</th>
                        <th>Enregistré par</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody id="paymentsHistoryTableBody">
                    <!-- Les paiements seront chargés ici via JavaScript -->
                </tbody>
            </table>
        </div>
    </div>
</div>



<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Refs pour le modal d'ajout/modification de dette magasin
        const detteMagasinModal = document.getElementById('detteMagasinModal');
        const addDetteMagasinBtn = document.getElementById('addDetteMagasinBtn');
        const modalCloseButtons = document.querySelectorAll('.modal-close-button');
        const detteMagasinForm = document.getElementById('detteMagasinForm');
        const modalTitleDetteMagasin = document.getElementById('modalTitleDetteMagasin');
        const submitDetteMagasinBtn = document.getElementById('submitDetteMagasinBtn');

        const detteIdInput = document.getElementById('detteId');
        const descriptionDetteMagasinInput = document.getElementById('description_dette_magasin');
        const montantInitialInput = document.getElementById('montant_initial');
        const dateEmissionInput = document.getElementById('date_emission');
        const dateEcheanceInput = document.getElementById('date_echeance');
        const fournisseurIdSelect = document.getElementById('fournisseur_id');
        const magasinIdDetteSelect = document.getElementById('magasin_id_dette');
        const statutSelect = document.getElementById('statut');

        // Refs pour le modal de suppression
        const deleteConfirmDetteMagasinModal = document.getElementById('deleteConfirmDetteMagasinModal');
        const deleteDetteMagasinIdInput = document.getElementById('deleteDetteMagasinId');
        const cancelDeleteDetteMagasinBtn = document.getElementById('cancelDeleteDetteMagasinBtn');

        // Refs pour le modal d'enregistrement de paiement
        const recordPaymentModal = document.getElementById('recordPaymentModal');
        const paymentDetteDescriptionSpan = document.getElementById('paymentDetteDescription');
        const paymentMontantRestantSpan = document.getElementById('paymentMontantRestant');
        const paiementDetteIdInput = document.getElementById('paiementDetteId');
        const montantPaiementInput = document.getElementById('montant_paiement');
        const descriptionPaiementInput = document.getElementById('description_paiement');

        // Refs pour le modal de l'historique des paiements
        const paymentsHistoryModal = document.getElementById('paymentsHistoryModal');
        const historyDetteDescriptionSpan = document.getElementById('historyDetteDescription');
        const paymentsHistoryTableBody = document.getElementById('paymentsHistoryTableBody');


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

        // --- Gestion des Modals de Dette Magasin ---
        if (addDetteMagasinBtn) {
            addDetteMagasinBtn.addEventListener('click', function () {
                modalTitleDetteMagasin.textContent = 'Ajouter une Dette Magasin';
                submitDetteMagasinBtn.name = 'add_dette_magasin';
                submitDetteMagasinBtn.innerHTML = '<i class="fas fa-save mr-2"></i> Enregistrer';
                detteMagasinForm.reset();
                detteIdInput.value = '';
                // Pré-remplir la date d'émission avec la date actuelle
                dateEmissionInput.valueAsDate = new Date();
                statutSelect.value = 'en_cours'; // Statut par défaut
                openModal(detteMagasinModal);
            });
        }

        document.querySelectorAll('.edit-dette-magasin-btn').forEach(button => {
            button.addEventListener('click', function () {
                modalTitleDetteMagasin.textContent = 'Modifier la Dette Magasin';
                submitDetteMagasinBtn.name = 'edit_dette_magasin';
                submitDetteMagasinBtn.innerHTML = '<i class="fas fa-edit mr-2"></i> Mettre à jour';

                detteIdInput.value = this.dataset.id;
                descriptionDetteMagasinInput.value = this.dataset.description;
                montantInitialInput.value = this.dataset.montant_initial;
                dateEmissionInput.value = this.dataset.date_emission;
                dateEcheanceInput.value = this.dataset.date_echeance;
                fournisseurIdSelect.value = this.dataset.fournisseur_id;
                magasinIdDetteSelect.value = this.dataset.magasin_id;
                statutSelect.value = this.dataset.statut;

                openModal(detteMagasinModal);
            });
        });

        document.querySelectorAll('.delete-dette-magasin-btn').forEach(button => {
            button.addEventListener('click', function () {
                deleteDetteMagasinIdInput.value = this.dataset.id;
                openModal(deleteConfirmDetteMagasinModal);
            });
        });

        // --- Gestion du Modal d'enregistrement de paiement ---
        document.querySelectorAll('.record-payment-btn').forEach(button => {
            button.addEventListener('click', function () {
                paymentDetteDescriptionSpan.textContent = this.dataset.description;
                paymentMontantRestantSpan.textContent = parseFloat(this.dataset.montant_restant).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                paiementDetteIdInput.value = this.dataset.dette_id;
                montantPaiementInput.value = parseFloat(this.dataset.montant_restant).toFixed(2); // Pré-remplir avec le montant restant
                montantPaiementInput.max = parseFloat(this.dataset.montant_restant).toFixed(2); // Définir le max
                montantPaiementInput.min = "0.01"; // Minimum 0.01
                descriptionPaiementInput.value = ''; // Réinitialiser
                openModal(recordPaymentModal);
            });
        });

        // --- Gestion du Modal de l'historique des paiements ---
        document.querySelectorAll('.view-payments-btn').forEach(button => {
            button.addEventListener('click', function () {
                const detteId = this.dataset.dette_id;
                const description = this.dataset.description;
                historyDetteDescriptionSpan.textContent = description;
                loadPaymentsHistory(detteId); // Charger l'historique
                openModal(paymentsHistoryModal);
            });
        });

        // Fonction pour charger l'historique des paiements via AJAX
        function loadPaymentsHistory(detteId) {
            paymentsHistoryTableBody.innerHTML = '<tr><td colspan="5" class="text-center py-4"><i class="fas fa-spinner fa-spin mr-2"></i> Chargement de l\'historique...</td></tr>';

            fetch(`<?php echo BASE_URL; ?>modules/finance/ajax_get_dette_payments.php?dette_id=${detteId}`) // Utilisation de BASE_URL
                .then(response => response.json())
                .then(data => {
                    paymentsHistoryTableBody.innerHTML = ''; // Vider le contenu précédent
                    if (data.length > 0) {
                        data.forEach(paiement => {
                            const row = `
                            <tr>
                                <td>${htmlspecialchars(paiement.id)}</td>
                                <td class="font-semibold text-green-600">${parseFloat(paiement.montant_paye).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} XOF</td>
                                <td>${new Date(paiement.date_paiement).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' })}</td>
                                <td>${htmlspecialchars(paiement.personnel_nom)} ${htmlspecialchars(paiement.personnel_prenom)}</td>
                                <td>${htmlspecialchars(paiement.description || 'N/A')}</td>
                            </tr>
                        `;
                            paymentsHistoryTableBody.innerHTML += row;
                        });
                    } else {
                        paymentsHistoryTableBody.innerHTML = '<tr><td colspan="5" class="text-center py-4">Aucun paiement enregistré pour cette dette.</td></tr>';
                    }
                })
                .catch(error => {
                    console.error('Erreur lors du chargement de l\'historique des paiements:', error);
                    paymentsHistoryTableBody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-red-500">Erreur lors du chargement.</td></tr>';
                });
        }


        // --- Gestion générale des fermetures de modals ---
        modalCloseButtons.forEach(button => {
            button.addEventListener('click', function () {
                closeModal(detteMagasinModal);
                closeModal(deleteConfirmDetteMagasinModal);
                closeModal(recordPaymentModal);
                closeModal(paymentsHistoryModal);
            });
        });

        window.addEventListener('click', function (event) {
            if (event.target == detteMagasinModal) closeModal(detteMagasinModal);
            if (event.target == deleteConfirmDetteMagasinModal) closeModal(deleteConfirmDetteMagasinModal);
            if (event.target == recordPaymentModal) closeModal(recordPaymentModal);
            if (event.target == paymentsHistoryModal) closeModal(paymentsHistoryModal);
        });

        if (cancelDeleteDetteMagasinBtn) {
            cancelDeleteDetteMagasinBtn.addEventListener('click', function () {
                closeModal(deleteConfirmDetteMagasinModal);
            });
        }

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
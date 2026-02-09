<?php
// modules/ventes/point_de_vente.php
ob_start();
session_start();

// Inclure les fichiers nécessaires
include '../../db_connect.php';
include '../../includes/header.php';

// Vérifier si l'utilisateur est connecté et a le rôle approprié
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !in_array($_SESSION['role'], ['Administrateur', 'Vendeur'])) {
    header("location: " . BASE_URL . "public/login.php");
    exit;
}

$page_title = "Point de Vente";
$message = '';

// Récupérer la liste des magasins
$magasins_disponibles = [];
$sql_magasins = "SELECT id, nom FROM magasins ORDER BY nom ASC";
$result_magasins = mysqli_query($conn, $sql_magasins);
while ($row = mysqli_fetch_assoc($result_magasins)) {
    $magasins_disponibles[] = $row;
}
mysqli_free_result($result_magasins);

// Déterminer le magasin par défaut
$default_magasin_id = null;
if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['Caissier', 'Vendeur'])) {
    $personnel_id = null;
    $sql_check_personnel = "SELECT id FROM personnel WHERE id = ?";
    if ($stmt = mysqli_prepare($conn, $sql_check_personnel)) {
        mysqli_stmt_bind_param($stmt, "i", $_SESSION['id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $valid_personnel_id);
        if (mysqli_stmt_fetch($stmt)) {
            $personnel_id = $valid_personnel_id;
        } else {
            $message = "Erreur : L'utilisateur connecté n'existe pas dans la table personnel.";
        }
        mysqli_stmt_close($stmt);
    }

    $sql_personnel_magasin = "SELECT magasin_id FROM personnel WHERE id = ?";
    if ($stmt_pm = mysqli_prepare($conn, $sql_personnel_magasin)) {
        mysqli_stmt_bind_param($stmt_pm, "i", $personnel_id);
        mysqli_stmt_execute($stmt_pm);
        mysqli_stmt_bind_result($stmt_pm, $assigned_magasin_id);
        mysqli_stmt_fetch($stmt_pm);
        mysqli_stmt_close($stmt_pm);
        $default_magasin_id = $assigned_magasin_id;
    }
} else if (isset($magasins_disponibles[0])) {
    $default_magasin_id = $magasins_disponibles[0]['id'];
}

// --- MODIFICATION : Génération d'un token pour éviter les doublons ---
if (!isset($_SESSION['sale_token'])) {
    $_SESSION['sale_token'] = bin2hex(random_bytes(32));
}

// --- Logique de traitement de la vente (POST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['finalize_sale'])) {
    // Vérifier le token pour éviter les doublons
    if (!isset($_POST['sale_token']) || $_POST['sale_token'] !== $_SESSION['sale_token']) {
        $message = '<div class="alert alert-error">Cette vente a déjà été enregistrée ou le formulaire est invalide.</div>';
        goto end_sale_process;
    }

    $client_id = !empty($_POST['client_id']) ? sanitize_input($_POST['client_id']) : NULL;
    $magasin_id = sanitize_input($_POST['magasin_id_vente']);

    $personnel_id = null;
    $sql_check_personnel = "SELECT id FROM personnel WHERE id = ?";
    if ($stmt = mysqli_prepare($conn, $sql_check_personnel)) {
        mysqli_stmt_bind_param($stmt, "i", $_SESSION['id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $valid_personnel_id);
        if (mysqli_stmt_fetch($stmt)) {
            $personnel_id = $valid_personnel_id;
        } else {
            $message = "Erreur : L'utilisateur connecté n'existe pas dans la table personnel.";
        }
        mysqli_stmt_close($stmt);
    }

    $montant_total = (float) sanitize_input($_POST['montant_total_final']);
    $montant_recu_espece = (float) sanitize_input($_POST['montant_recu_espece']);
    $montant_recu_momo = (float) sanitize_input($_POST['montant_recu_momo']);
    $montant_epargne_utilise = isset($_POST['montant_epargne_utilise']) ? (float) sanitize_input($_POST['montant_epargne_utilise']) : 0.00;
    $compte_epargne_id = !empty($_POST['compte_epargne_id']) ? (int) sanitize_input($_POST['compte_epargne_id']) : NULL;
    $reduction_globale_pourcentage = (float) sanitize_input($_POST['reduction_globale_pourcentage']);
    $reduction_globale_montant = (float) sanitize_input($_POST['reduction_globale_montant']);
    $cart_items_json = $_POST['cart_items_json'];
    $cart_items = json_decode($cart_items_json, true);

    $montant_paye = $montant_recu_espece + $montant_recu_momo + $montant_epargne_utilise;
    $montant_change = 0;
    $montant_du = 0;
    $type_paiement = '';
    $statut_paiement = '';

    if ($montant_paye >= $montant_total) {
        $montant_change = $montant_paye - $montant_total;
        $statut_paiement = 'paye';
    } else {
        $montant_du = $montant_total - $montant_paye;
        $statut_paiement = 'partiellement_paye';
        if ($montant_paye == 0)
            $statut_paiement = 'impaye';
    }

    mysqli_begin_transaction($conn);
    try {
        // 1. Insérer la vente principale
        $sql_vente = "INSERT INTO ventes (client_id, magasin_id, personnel_id, montant_total, montant_recu_espece, montant_recu_momo, montant_change, montant_du, type_paiement, statut_paiement, reduction_globale_pourcentage, reduction_globale_montant) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        if ($stmt_vente = mysqli_prepare($conn, $sql_vente)) {
            mysqli_stmt_bind_param($stmt_vente, "iiidddddssdd", $client_id, $magasin_id, $personnel_id, $montant_total, $montant_recu_espece, $montant_recu_momo, $montant_change, $montant_du, $type_paiement, $statut_paiement, $reduction_globale_pourcentage, $reduction_globale_montant);
            if (!mysqli_stmt_execute($stmt_vente)) {
                throw new Exception("Erreur lors de l'insertion de la vente: " . mysqli_error($conn));
            }
            $vente_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt_vente);
        } else {
            throw new Exception("Erreur de préparation de la requête de vente: " . mysqli_error($conn));
        }

        // 2. Insérer les détails de vente et déduire le stock
        $sql_detail = "INSERT INTO details_vente (vente_id, produit_id, quantite, prix_vente_unitaire, prix_achat_unitaire, reduction_ligne_pourcentage, reduction_ligne_montant, total_ligne) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $sql_update_stock = "UPDATE produits SET quantite_stock = quantite_stock - ? WHERE id = ? AND magasin_id = ?";

        if (!empty($cart_items)) {
            foreach ($cart_items as $item) {
                $produit_id = $item['id'];
                $quantite = $item['quantity'];
                $prix_vente_unitaire = $item['unitPrice'];
                $prix_achat_unitaire = $item['purchasePrice'];
                $reduction_ligne_pourcentage = $item['lineDiscountPercentage'];
                $reduction_ligne_montant = $item['lineDiscountAmount'];
                $total_ligne = $item['lineTotal'];

                // Insérer le détail de vente
                if ($stmt_detail = mysqli_prepare($conn, $sql_detail)) {
                    mysqli_stmt_bind_param($stmt_detail, "iidddddd", $vente_id, $produit_id, $quantite, $prix_vente_unitaire, $prix_achat_unitaire, $reduction_ligne_pourcentage, $reduction_ligne_montant, $total_ligne);
                    if (!mysqli_stmt_execute($stmt_detail)) {
                        throw new Exception("Erreur lors de l'insertion du détail de vente pour le produit ID " . $produit_id . ": " . mysqli_error($conn));
                    }
                    mysqli_stmt_close($stmt_detail);
                } else {
                    throw new Exception("Erreur de préparation de la requête de détail de vente: " . mysqli_error($conn));
                }

                // Déduire le stock
                if ($stmt_stock = mysqli_prepare($conn, $sql_update_stock)) {
                    mysqli_stmt_bind_param($stmt_stock, "dii", $quantite, $produit_id, $magasin_id);
                    if (!mysqli_stmt_execute($stmt_stock)) {
                        throw new Exception("Erreur lors de la déduction du stock pour le produit ID " . $produit_id . " dans le magasin " . $magasin_id . ": " . mysqli_error($conn));
                    }
                    mysqli_stmt_close($stmt_stock);
                } else {
                    throw new Exception("Erreur de préparation de la requête de mise à jour de stock: " . mysqli_error($conn));
                }
            }
        }

        // 3. Débiter l'épargne si utilisée
        if ($montant_epargne_utilise > 0 && $compte_epargne_id !== NULL) {
            // Vérifier que le compte existe et que le solde est suffisant
            $sql_check_epargne = "SELECT solde FROM comptes_epargne WHERE id = ? AND client_id = ?";
            if ($stmt_check_epargne = mysqli_prepare($conn, $sql_check_epargne)) {
                mysqli_stmt_bind_param($stmt_check_epargne, "ii", $compte_epargne_id, $client_id);
                mysqli_stmt_execute($stmt_check_epargne);
                mysqli_stmt_bind_result($stmt_check_epargne, $solde_actuel);
                if (!mysqli_stmt_fetch($stmt_check_epargne)) {
                    mysqli_stmt_close($stmt_check_epargne);
                    throw new Exception("Compte épargne introuvable ou ne correspond pas au client.");
                }
                mysqli_stmt_close($stmt_check_epargne);

                if ($montant_epargne_utilise > $solde_actuel) {
                    $devise = get_currency();
                    throw new Exception("Le montant d'épargne à utiliser (" . number_format($montant_epargne_utilise, 2, ',', ' ') . " " . $devise . ") dépasse le solde disponible (" . number_format($solde_actuel, 2, ',', ' ') . " " . $devise . ").");
                }

                // Débiter l'épargne
                $nouveau_solde = $solde_actuel - $montant_epargne_utilise;
                $sql_update_epargne = "UPDATE comptes_epargne SET solde = ? WHERE id = ?";
                if ($stmt_update_epargne = mysqli_prepare($conn, $sql_update_epargne)) {
                    mysqli_stmt_bind_param($stmt_update_epargne, "di", $nouveau_solde, $compte_epargne_id);
                    if (!mysqli_stmt_execute($stmt_update_epargne)) {
                        throw new Exception("Erreur lors du débit de l'épargne: " . mysqli_error($conn));
                    }
                    mysqli_stmt_close($stmt_update_epargne);
                } else {
                    throw new Exception("Erreur de préparation de la requête de débit d'épargne: " . mysqli_error($conn));
                }

                // Enregistrer la transaction d'épargne (retrait)
                $description_retrait = "Retrait pour paiement de la vente ID: " . $vente_id;
                $sql_insert_trans_epargne = "INSERT INTO transactions_epargne (compte_epargne_id, type_transaction, montant, description) VALUES (?, 'retrait', ?, ?)";
                if ($stmt_trans_epargne = mysqli_prepare($conn, $sql_insert_trans_epargne)) {
                    mysqli_stmt_bind_param($stmt_trans_epargne, "ids", $compte_epargne_id, $montant_epargne_utilise, $description_retrait);
                    if (!mysqli_stmt_execute($stmt_trans_epargne)) {
                        throw new Exception("Erreur lors de l'enregistrement de la transaction d'épargne: " . mysqli_error($conn));
                    }
                    mysqli_stmt_close($stmt_trans_epargne);
                } else {
                    throw new Exception("Erreur de préparation de la requête d'insertion de transaction d'épargne: " . mysqli_error($conn));
                }
            } else {
                throw new Exception("Erreur de préparation de la requête de vérification d'épargne: " . mysqli_error($conn));
            }
        }

        // 4. Gérer la dette client si applicable
        if ($montant_du > 0 && $client_id !== NULL) {
            $description_dette = "Dette issue de la vente ID: " . $vente_id;
            $date_limite_paiement = date('Y-m-d', strtotime('+30 days'));
            $statut_dette = 'en_cours';

            $sql_dette = "INSERT INTO dettes_clients (client_id, montant_initial, montant_restant, date_limite_paiement, description, statut) VALUES (?, ?, ?, ?, ?, ?)";
            if ($stmt_dette = mysqli_prepare($conn, $sql_dette)) {
                mysqli_stmt_bind_param($stmt_dette, "iddsss", $client_id, $montant_du, $montant_du, $date_limite_paiement, $description_dette, $statut_dette);
                if (!mysqli_stmt_execute($stmt_dette)) {
                    throw new Exception("Erreur lors de l'enregistrement de la dette client: " . mysqli_error($conn));
                }
                mysqli_stmt_close($stmt_dette);
            } else {
                throw new Exception("Erreur de préparation de la requête de dette client: " . mysqli_error($conn));
            }
        }

        mysqli_commit($conn);

        // Régénérer un nouveau token après succès
        $_SESSION['sale_token'] = bin2hex(random_bytes(32));

        // Stocker l'ID de la vente pour l'affichage du reçu
        $_SESSION['last_sale_id'] = $vente_id;

        // Redirection pour éviter les doublons sur refresh (PRG pattern)
        header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
        exit;

    } catch (Exception $e) {
        mysqli_rollback($conn);
        $message = '<div class="alert alert-error">Erreur lors de l\'enregistrement de la vente : ' . $e->getMessage() . '</div>';
        // Régénérer le token en cas d'erreur aussi
        $_SESSION['sale_token'] = bin2hex(random_bytes(32));
    }

    end_sale_process:
    ;
}

// Vérifier si on revient après une vente réussie
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $message = '<div class="alert alert-success">Vente enregistrée avec succès !</div>';
}

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
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div class="form-group">
                            <label for="magasin_id_vente" class="block text-gray-700 text-sm font-bold mb-2">Magasin de
                                Vente:</label>
                            <select id="magasin_id_vente"
                                class="shadow appearance-none border rounded-md w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                <?php foreach ($magasins_disponibles as $mag): ?>
                                    <option value="<?php echo htmlspecialchars($mag['id']); ?>" <?php echo ($default_magasin_id == $mag['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($mag['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="searchProduct" class="block text-gray-700 text-sm font-bold mb-2">Rechercher
                                Produit:</label>
                            <input type="text" id="searchProduct"
                                class="shadow appearance-none border rounded-md w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                placeholder="Entrez le nom du produit...">
                        </div>

                        <div id="productResults"
                            class="hidden bg-white border border-gray-300 rounded-md shadow-md max-h-48 overflow-y-auto">
                        </div>
                    </div>

                    <div class="mb-6">
                        <h2 class="text-2xl font-bold text-gray-800 mb-4">Panier</h2>

                        <div class="overflow-x-auto">
                            <table class="data-table mb-4 min-w-full">
                                <thead>
                                    <tr>
                                        <th>Produit</th>
                                        <th>Quantité</th>
                                        <th>Prix Unitaire</th>
                                        <th>Réduction (%)</th>
                                        <th>Réduction (Montant)</th>
                                        <th>Total Ligne</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="cartBody"></tbody>
                            </table>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div><strong>Sous-total:</strong> <span id="subtotalDisplay">0.00
                                    <?php echo htmlspecialchars(get_currency()); ?></span></div>
                            <div><strong>Réduction Globale (%):</strong> <input type="number" step="0.01" min="0"
                                    max="100" id="globalDiscountPercentage" value="0.00"
                                    class="shadow appearance-none border rounded-md py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline w-24">
                            </div>
                            <div><strong>Réduction Globale Montant:</strong> <span id="globalDiscountAmountDisplay">0.00
                                    <?php echo htmlspecialchars(get_currency()); ?></span></div>
                            <div><strong>Total à Payer:</strong> <span id="totalPayableDisplay">0.00
                                    <?php echo htmlspecialchars(get_currency()); ?></span></div>
                        </div>

                        <!-- Section Épargne Client -->
                        <div id="epargneSection" class="hidden mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                            <div class="flex items-center justify-between mb-2">
                                <div>
                                    <strong class="text-blue-800">Solde Épargne Disponible:</strong>
                                    <span id="epargneSoldeDisplay" class="text-lg font-bold text-green-700 ml-2">0.00
                                        <?php echo htmlspecialchars(get_currency()); ?></span>
                                </div>
                                <span id="epargneCompteInfo" class="text-sm text-gray-600"></span>
                            </div>
                            <div>
                                <label for="montant_epargne_utilise"
                                    class="block text-gray-700 text-sm font-bold mb-2">Montant à Utiliser depuis
                                    l'Épargne:</label>
                                <input type="number" step="0.01" min="0" id="montant_epargne_utilise" value="0.00"
                                    class="shadow appearance-none border rounded-md w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                <button type="button" id="useAllEpargneBtn"
                                    class="mt-2 bg-blue-500 hover:bg-blue-600 text-white font-bold py-1 px-3 rounded-md shadow-md transition duration-300 text-sm">
                                    Utiliser tout le solde disponible
                                </button>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <div>
                                <label for="montant_recu_espece"
                                    class="block text-gray-700 text-sm font-bold mb-2">Montant Reçu Espèces:</label>
                                <input type="number" step="0.01" min="0" id="montant_recu_espece" value="0.00"
                                    class="shadow appearance-none border rounded-md w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            </div>
                            <div>
                                <label for="montant_recu_momo"
                                    class="block text-gray-700 text-sm font-bold mb-2">Montant Reçu MoMo:</label>
                                <input type="number" step="0.01" min="0" id="montant_recu_momo" value="0.00"
                                    class="shadow appearance-none border rounded-md w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            </div>
                            <div>
                                <strong>Change:</strong> <span id="montantChangeDisplay">0.00
                                    <?php echo htmlspecialchars(get_currency()); ?></span><br>
                                <strong>Dû:</strong> <span id="montantDuDisplay">0.00
                                    <?php echo htmlspecialchars(get_currency()); ?></span>
                            </div>
                        </div>

                        <div class="form-group mb-4">
                            <label for="searchClient" class="block text-gray-700 text-sm font-bold mb-2">Rechercher
                                Client:</label>
                            <input type="text" id="searchClient"
                                class="shadow appearance-none border rounded-md w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                placeholder="Entrez le nom ou prénom du client...">
                            <div id="clientResults"
                                class="hidden bg-white border border-gray-300 rounded-md shadow-md max-h-48 overflow-y-auto mt-2">
                            </div>
                            <div id="selectedClientInfo" class="hidden mt-2">
                                <strong>Client Sélectionné:</strong> <span id="clientNameDisplay"></span>
                                <button id="removeClientBtn"
                                    class="bg-red-500 hover:bg-red-600 text-white font-bold py-1 px-2 rounded-md shadow-md transition duration-300 ml-2"><i
                                        class="fas fa-times"></i></button>
                            </div>
                            <input type="hidden" id="clientIdSelected" name="client_id">
                        </div>

                        <button id="finalizeSaleBtn"
                            class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-md shadow-md transition duration-300"><i
                                class="fas fa-check mr-2"></i> Finaliser Vente</button>
                    </div>
                </div>
            </div>
        </main>

        <?php include '../../includes/footer.php'; ?>
    </div>
</div>

<div id="saleConfirmModal"
    class="modal fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center hidden z-[100]">
    <div class="modal-content bg-white p-6 rounded-lg shadow-lg max-w-md w-full">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-2xl font-bold text-gray-800">Confirmer la Vente</h2>
            <button id="closeSaleConfirmModalBtn" class="text-gray-500 hover:text-gray-700"><i
                    class="fas fa-times text-xl"></i></button>
        </div>

        <p id="saleConfirmMessage" class="mb-6 text-gray-700"></p>

        <form id="finalizeSaleForm" method="POST" action="">
            <input type="hidden" name="finalize_sale" value="1">
            <input type="hidden" name="sale_token" value="<?php echo $_SESSION['sale_token']; ?>">
            <input type="hidden" id="final_montant_total" name="montant_total_final">
            <input type="hidden" id="final_montant_recu_espece" name="montant_recu_espece">
            <input type="hidden" id="final_montant_recu_momo" name="montant_recu_momo">
            <input type="hidden" id="final_montant_epargne_utilise" name="montant_epargne_utilise">
            <input type="hidden" id="final_compte_epargne_id" name="compte_epargne_id">
            <input type="hidden" id="final_reduction_globale_pourcentage" name="reduction_globale_pourcentage">
            <input type="hidden" id="final_reduction_globale_montant" name="reduction_globale_montant">
            <input type="hidden" id="final_cart_items_json" name="cart_items_json">
            <input type="hidden" id="final_client_id" name="client_id">
            <input type="hidden" id="final_magasin_id_vente" name="magasin_id_vente">

            <div class="flex justify-end gap-4">
                <button type="button" id="cancelSaleBtn"
                    class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-md shadow-md transition duration-300">Annuler</button>
                <button type="submit"
                    class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-md shadow-md transition duration-300">Confirmer</button>
            </div>
        </form>
    </div>
    <div id="receiptModal"
        class="modal fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center hidden z-[100]">
        <div class="modal-content bg-white p-6 rounded-lg shadow-lg max-w-md w-full">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-2xl font-bold text-gray-800">Facture Pro Format</h2>
                <button id="closeReceiptModalBtn" class="text-gray-500 hover:text-gray-700"><i
                        class="fas fa-times text-xl"></i></button>
            </div>

            <div id="receiptContent" class="mb-6"></div>

            <div class="flex justify-end gap-4">
                <button id="finalizeReceiptBtn"
                    class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-md shadow-md transition duration-300"><i
                        class="fas fa-print mr-2"></i> Imprimer</button>
                <button id="newSaleBtn"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md shadow-md transition duration-300"><i
                        class="fas fa-plus mr-2"></i> Nouvelle Vente</button>
            </div>
        </div>
    </div>
    <script>
        // --- JavaScript pour le Point de Vente ---
        const currency = <?php echo json_encode(get_currency()); ?>;
        let cart = [];

        function openModal(modal) {
            modal.classList.remove('hidden');
        }

        function closeModal(modal) {
            modal.classList.add('hidden');
        }

        // Recherche de Produit
        const searchProductInput = document.getElementById('searchProduct');
        const productResults = document.getElementById('productResults');
        const magasinIdVenteSelect = document.getElementById('magasin_id_vente');

        searchProductInput.addEventListener('input', () => {
            const query = searchProductInput.value.trim();
            const magasinId = magasinIdVenteSelect.value;

            if (query.length < 2) {
                productResults.classList.add('hidden');
                return;
            }

            fetch(`<?php echo BASE_URL; ?>modules/ventes/ajax_search_products.php?query=${encodeURIComponent(query)}&magasin_id=${magasinId}`)
                .then(response => response.json())
                .then(data => {
                    productResults.innerHTML = '';
                    if (data.length > 0) {
                        data.forEach(product => {
                            const div = document.createElement('div');
                            div.classList.add('p-2', 'hover:bg-gray-100', 'cursor-pointer');
                            div.innerHTML = `
                        <strong>${htmlspecialchars(product.nom)}</strong><br>
                        Prix: ${parseFloat(product.prix_vente).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}<br>
                        Stock: ${parseFloat(product.quantite_stock).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                    `;
                            div.addEventListener('click', () => addToCart(product));
                            productResults.appendChild(div);
                        });
                        productResults.classList.remove('hidden');
                    } else {
                        productResults.innerHTML = '<div class="p-2 text-red-500">Aucun produit trouvé.</div>';
                        productResults.classList.remove('hidden');
                    }
                })
                .catch(error => {
                    console.error('Erreur lors de la recherche de produit:', error);
                    productResults.innerHTML = '<div class="p-2 text-red-500">Erreur lors de la recherche.</div>';
                    productResults.classList.remove('hidden');
                });
        });

        function addToCart(product) {
            const existingItem = cart.find(item => item.id === product.id);
            if (existingItem) {
                // NOTE: La vérification de stock doit se faire sur le stock réel du produit, qui n'est pas inclus ici
                // Pour l'instant, on se base sur la quantité stock récupérée lors de la recherche initiale, ce qui est une approximation.
                // Pour une gestion de stock critique, un contrôle côté serveur est indispensable avant la finalisation.
                // Ici, on va juste vérifier la quantité ajoutée par rapport à celle reçue du serveur lors de la recherche.
                // Si le produit n'est plus dans le DOM, on doit re-fetch son stock réel si on veut être précis.
                // Dans ce code, on utilise la `quantite_stock` reçue initialement.
                if (existingItem.quantity + 1 > product.quantite_stock) {
                    showMessage('error', 'Stock insuffisant pour ce produit.');
                    return;
                }
                existingItem.quantity += 1;
                // INTERDÉPENDANCE : Recalculer le prix unitaire en fonction de la nouvelle quantité
                existingItem.unitPrice = existingItem.originalUnitPrice * existingItem.quantity;
                // Recalculer la réduction et le total
                existingItem.lineDiscountAmount = (existingItem.unitPrice * existingItem.lineDiscountPercentage) / 100;
                existingItem.lineTotal = existingItem.unitPrice - existingItem.lineDiscountAmount;
            } else {
                if (1 > product.quantite_stock) {
                    showMessage('error', 'Stock insuffisant pour ce produit.');
                    return;
                }
                cart.push({
                    id: product.id,
                    name: product.nom,
                    unitPrice: parseFloat(product.prix_vente),
                    originalUnitPrice: parseFloat(product.prix_vente), // Prix original pour l'interdépendance quantité/prix
                    purchasePrice: parseFloat(product.prix_achat),
                    quantity: 1,
                    // ATTENTION : J'ajoute le stock initial pour les futures vérifications dans updateCartItem si nécessaire.
                    // Si la logique ne veut pas ce stock, l'enlever. Pour l'instant, je le mets pour simuler la vérification.
                    stock: parseFloat(product.quantite_stock),
                    lineDiscountPercentage: 0,
                    lineDiscountAmount: 0,
                    lineTotal: parseFloat(product.prix_vente)
                });
            }
            renderCart();
            calculateTotals();
            productResults.classList.add('hidden');
            searchProductInput.value = '';
        }

        function renderCart() {
            const cartBody = document.getElementById('cartBody');
            cartBody.innerHTML = '';
            if (cart.length > 0) {
                cart.forEach((item, index) => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                <td>${htmlspecialchars(item.name)}</td>
                <td><input type="number" step="0.01" min="0.01" value="${item.quantity.toFixed(2)}" class="quantity-input shadow appearance-none border rounded-md py-1 px-2 text-gray-700 leading-tight focus:outline-none focus:shadow-outline w-20" data-index="${index}"></td>
                <td><input type="number" step="0.01" min="0" value="${item.unitPrice.toFixed(2)}" class="unit-price-input shadow appearance-none border rounded-md py-1 px-2 text-gray-700 leading-tight focus:outline-none focus:shadow-outline w-24" data-index="${index}"></td>
                <td><input type="number" step="0.01" min="0" max="100" value="${item.lineDiscountPercentage.toFixed(2)}" class="line-discount-percentage-input shadow appearance-none border rounded-md py-1 px-2 text-gray-700 leading-tight focus:outline-none focus:shadow-outline w-20" data-index="${index}"></td>
                <td><input type="number" step="0.01" min="0" value="${item.lineDiscountAmount.toFixed(2)}" class="line-discount-amount-input shadow appearance-none border rounded-md py-1 px-2 text-gray-700 leading-tight focus:outline-none focus:shadow-outline w-24" data-index="${index}"></td>
                <td>${item.lineTotal.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}</td>
                <td>
                    <button class="remove-from-cart-btn bg-red-500 hover:bg-red-600 text-white font-bold py-1 px-2 rounded-md shadow-md transition duration-300" data-index="${index}"><i class="fas fa-trash"></i></button>
                </td>
            `;
                    cartBody.appendChild(row);
                });
                attachCartListeners();
            } else {
                cartBody.innerHTML = '<tr><td colspan="7" class="text-center py-4">Panier vide.</td></tr>';
            }
        }

        function attachCartListeners() {
            document.querySelectorAll('.quantity-input').forEach(input => {
                input.addEventListener('change', updateCartItem);
                // Empêcher l'ajout de quantité supérieure au stock.
                input.addEventListener('input', (event) => {
                    const index = parseInt(event.target.dataset.index);
                    const item = cart[index];
                    const newValue = parseFloat(event.target.value);
                    // Vérification simple du stock
                    if (newValue > item.stock) {
                        showMessage('warning', `La quantité maximale est limitée par le stock : ${item.stock}`);
                        event.target.value = item.stock.toFixed(2); // Ramener à la valeur max
                    }
                });
            });
            document.querySelectorAll('.unit-price-input').forEach(input => {
                input.addEventListener('change', updateCartItem);
            });
            document.querySelectorAll('.line-discount-percentage-input').forEach(input => {
                input.addEventListener('change', updateCartItem);
            });
            document.querySelectorAll('.line-discount-amount-input').forEach(input => {
                input.addEventListener('change', updateCartItem);
            });
            document.querySelectorAll('.remove-from-cart-btn').forEach(button => {
                button.addEventListener('click', removeFromCart);
            });
        }

        function updateCartItem(event) {
            const index = parseInt(event.target.dataset.index);
            const item = cart[index];
            let newValue = parseFloat(event.target.value);

            // Pour l'input de quantité : Interdépendance avec le prix unitaire
            if (event.target.classList.contains('quantity-input')) {
                // Vérification du stock
                if (item.stock && newValue > item.stock) {
                    newValue = item.stock;
                    event.target.value = newValue.toFixed(2);
                }

                // S'assurer que la quantité est positive
                if (newValue <= 0 || isNaN(newValue)) {
                    newValue = 0.01;
                    event.target.value = newValue.toFixed(2);
                }

                item.quantity = newValue;

                // INTERDÉPENDANCE : Recalculer le prix unitaire en fonction de la quantité
                // prixUnitaire = prixOriginal × quantité
                // Exemple : quantité 0.5 avec prix original 2000 → prixUnitaire = 1000
                item.unitPrice = item.originalUnitPrice * item.quantity;

                // Recalculer la réduction en montant
                item.lineDiscountAmount = (item.unitPrice * item.lineDiscountPercentage) / 100;

            } else if (event.target.classList.contains('unit-price-input')) {
                // INTERDÉPENDANCE : Recalculer la quantité en fonction du prix unitaire
                // quantité = prixUnitaire / prixOriginal
                // Exemple : prixUnitaire 1000 avec prix original 2000 → quantité = 0.5

                if (item.originalUnitPrice > 0) {
                    item.unitPrice = newValue;
                    item.quantity = newValue / item.originalUnitPrice;

                    // Vérifier que la quantité ne dépasse pas le stock
                    if (item.stock && item.quantity > item.stock) {
                        item.quantity = item.stock;
                        item.unitPrice = item.originalUnitPrice * item.quantity;
                    }

                    // S'assurer que la quantité est positive
                    if (item.quantity <= 0 || isNaN(item.quantity)) {
                        item.quantity = 0.01;
                        item.unitPrice = item.originalUnitPrice * item.quantity;
                    }
                }

                // Recalculer la réduction en montant
                item.lineDiscountAmount = (item.unitPrice * item.lineDiscountPercentage) / 100;

            } else if (event.target.classList.contains('line-discount-percentage-input')) {
                item.lineDiscountPercentage = newValue;
                // Recalculer la réduction en montant
                item.lineDiscountAmount = (item.unitPrice * newValue) / 100;

            } else if (event.target.classList.contains('line-discount-amount-input')) {
                item.lineDiscountAmount = newValue;
                // Recalculer la réduction en pourcentage
                item.lineDiscountPercentage = (item.unitPrice > 0) ? (newValue / item.unitPrice) * 100 : 0;
            }

            // Recalculer le total de la ligne
            // Total = prixUnitaire - réduction (car prixUnitaire représente le montant pour cette quantité)
            item.lineTotal = item.unitPrice - item.lineDiscountAmount;

            // S'assurer que les valeurs ne sont pas NaN
            if (isNaN(item.quantity)) item.quantity = 1;
            if (isNaN(item.unitPrice)) item.unitPrice = item.originalUnitPrice || 0;
            if (isNaN(item.lineDiscountPercentage)) item.lineDiscountPercentage = 0;
            if (isNaN(item.lineDiscountAmount)) item.lineDiscountAmount = 0;
            if (isNaN(item.lineTotal)) item.lineTotal = item.unitPrice;


            renderCart(); // Re-render pour mettre à jour les affichages des inputs et du total
            calculateTotals();
        }

        function removeFromCart(event) {
            const index = parseInt(event.currentTarget.dataset.index);
            cart.splice(index, 1);
            renderCart();
            calculateTotals();
        }

        // Recherche de Client
        const searchClientInput = document.getElementById('searchClient');
        const clientResults = document.getElementById('clientResults');
        const selectedClientInfo = document.getElementById('selectedClientInfo');
        const clientNameDisplay = document.getElementById('clientNameDisplay');
        const removeClientBtn = document.getElementById('removeClientBtn');
        const clientIdSelectedInput = document.getElementById('clientIdSelected');

        searchClientInput.addEventListener('input', () => {
            const query = searchClientInput.value.trim();

            if (query.length < 2) {
                clientResults.classList.add('hidden');
                return;
            }

            fetch(`<?php echo BASE_URL; ?>modules/ventes/ajax_search_clients.php?query=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    clientResults.innerHTML = '';
                    if (data.length > 0) {
                        data.forEach(client => {
                            const div = document.createElement('div');
                            div.classList.add('p-2', 'hover:bg-gray-100', 'cursor-pointer');
                            div.innerHTML = `
                        <strong>${htmlspecialchars(client.nom)} ${htmlspecialchars(client.prenom)}</strong><br>
                        Tel: ${htmlspecialchars(client.telephone)}
                    `;
                            div.addEventListener('click', () => selectClient(client));
                            clientResults.appendChild(div);
                        });
                        clientResults.classList.remove('hidden');
                    } else {
                        clientResults.innerHTML = '<div class="p-2 text-red-500">Aucun client trouvé.</div>';
                        clientResults.classList.remove('hidden');
                    }
                })
                .catch(error => {
                    console.error('Erreur lors de la recherche de client:', error);
                    clientResults.innerHTML = '<div class="p-2 text-red-500">Erreur lors de la recherche.</div>';
                    clientResults.classList.remove('hidden');
                });
        });

        // Variables pour l'épargne
        let clientEpargneData = {
            has_account: false,
            solde: 0,
            compte_id: null,
            numero_compte: null
        };
        const epargneSection = document.getElementById('epargneSection');
        const epargneSoldeDisplay = document.getElementById('epargneSoldeDisplay');
        const epargneCompteInfo = document.getElementById('epargneCompteInfo');
        const montantEpargneUtiliseInput = document.getElementById('montant_epargne_utilise');
        const useAllEpargneBtn = document.getElementById('useAllEpargneBtn');

        function selectClient(client) {
            clientIdSelectedInput.value = client.id;
            clientNameDisplay.textContent = `${htmlspecialchars(client.nom)} ${htmlspecialchars(client.prenom)}`;
            selectedClientInfo.classList.remove('hidden');
            clientResults.classList.add('hidden');
            searchClientInput.value = '';

            // Récupérer le solde d'épargne du client
            fetch(`<?php echo BASE_URL; ?>modules/ventes/ajax_get_client_epargne.php?client_id=${client.id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.has_account && data.solde > 0) {
                        clientEpargneData = data;
                        epargneSoldeDisplay.textContent = parseFloat(data.solde).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + currency;
                        epargneCompteInfo.textContent = `Compte: ${data.numero_compte}`;
                        epargneSection.classList.remove('hidden');
                        montantEpargneUtiliseInput.max = data.solde;
                        montantEpargneUtiliseInput.value = '0.00';
                    } else {
                        clientEpargneData = { has_account: false, solde: 0, compte_id: null, numero_compte: null };
                        epargneSection.classList.add('hidden');
                        montantEpargneUtiliseInput.value = '0.00';
                    }
                    calculateTotals();
                })
                .catch(error => {
                    console.error('Erreur lors de la récupération du solde d\'épargne:', error);
                    clientEpargneData = { has_account: false, solde: 0, compte_id: null, numero_compte: null };
                    epargneSection.classList.add('hidden');
                });
        }

        removeClientBtn.addEventListener('click', () => {
            clientIdSelectedInput.value = '';
            clientNameDisplay.textContent = '';
            selectedClientInfo.classList.add('hidden');
            clientEpargneData = { has_account: false, solde: 0, compte_id: null, numero_compte: null };
            epargneSection.classList.add('hidden');
            montantEpargneUtiliseInput.value = '0.00';
            calculateTotals();
        });

        // Bouton pour utiliser tout le solde d'épargne
        useAllEpargneBtn.addEventListener('click', () => {
            if (clientEpargneData.has_account && clientEpargneData.solde > 0) {
                montantEpargneUtiliseInput.value = parseFloat(clientEpargneData.solde).toFixed(2);
                calculateTotals();
            }
        });

        // Calculer les Totaux
        const subtotalDisplay = document.getElementById('subtotalDisplay');
        const globalDiscountAmountDisplay = document.getElementById('globalDiscountAmountDisplay');
        const totalPayableDisplay = document.getElementById('totalPayableDisplay');
        const montantChangeDisplay = document.getElementById('montantChangeDisplay');
        const montantDuDisplay = document.getElementById('montantDuDisplay');
        const globalDiscountPercentageInput = document.getElementById('globalDiscountPercentage');
        const montantRecuEspeceInput = document.getElementById('montant_recu_espece');
        const montantRecuMomoInput = document.getElementById('montant_recu_momo');

        function calculateTotals() {
            let subtotal = cart.reduce((sum, item) => sum + item.lineTotal, 0);
            subtotalDisplay.textContent = subtotal.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + currency;

            const globalDiscountPercentage = parseFloat(globalDiscountPercentageInput.value) || 0;
            const globalDiscountAmount = (subtotal * globalDiscountPercentage) / 100;
            globalDiscountAmountDisplay.textContent = globalDiscountAmount.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + currency;

            const totalPayable = subtotal - globalDiscountAmount;
            totalPayableDisplay.textContent = totalPayable.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + currency;

            const montantRecuEspece = parseFloat(montantRecuEspeceInput.value) || 0;
            const montantRecuMomo = parseFloat(montantRecuMomoInput.value) || 0;
            const montantEpargneUtilise = parseFloat(montantEpargneUtiliseInput.value) || 0;

            // Vérifier que le montant d'épargne utilisé ne dépasse pas le solde disponible
            const montantEpargneUtiliseFinal = Math.min(montantEpargneUtilise, clientEpargneData.solde || 0);
            if (montantEpargneUtilise !== montantEpargneUtiliseFinal) {
                montantEpargneUtiliseInput.value = montantEpargneUtiliseFinal.toFixed(2);
            }

            const totalRecu = montantRecuEspece + montantRecuMomo + montantEpargneUtiliseFinal;

            let montantChange = 0;
            let montantDu = 0;
            if (totalRecu >= totalPayable) {
                montantChange = totalRecu - totalPayable;
            } else {
                montantDu = totalPayable - totalRecu;
            }

            montantChangeDisplay.textContent = montantChange.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + currency;
            montantDuDisplay.textContent = montantDu.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + currency;

            document.getElementById('final_montant_total').value = totalPayable.toFixed(2);
            document.getElementById('final_montant_recu_espece').value = montantRecuEspece.toFixed(2);
            document.getElementById('final_montant_recu_momo').value = montantRecuMomo.toFixed(2);
            document.getElementById('final_montant_epargne_utilise').value = montantEpargneUtiliseFinal.toFixed(2);
            document.getElementById('final_compte_epargne_id').value = clientEpargneData.compte_id || '';
            document.getElementById('final_reduction_globale_pourcentage').value = globalDiscountPercentage.toFixed(2);
            document.getElementById('final_reduction_globale_montant').value = globalDiscountAmount.toFixed(2);
            document.getElementById('final_cart_items_json').value = JSON.stringify(cart);
            document.getElementById('final_client_id').value = clientIdSelectedInput.value;
            document.getElementById('final_magasin_id_vente').value = magasinIdVenteSelect.value;
        }

        globalDiscountPercentageInput.addEventListener('input', calculateTotals);
        montantRecuEspeceInput.addEventListener('input', calculateTotals);
        montantRecuMomoInput.addEventListener('input', calculateTotals);
        montantEpargneUtiliseInput.addEventListener('input', calculateTotals);
        magasinIdVenteSelect.addEventListener('change', calculateTotals);
        clientIdSelectedInput.addEventListener('change', calculateTotals);

        // --- Finaliser la vente ---
        const finalizeSaleBtn = document.getElementById('finalizeSaleBtn');
        const saleConfirmModal = document.getElementById('saleConfirmModal');
        const closeSaleConfirmModalBtn = document.getElementById('closeSaleConfirmModalBtn');
        const cancelSaleBtn = document.getElementById('cancelSaleBtn');
        const saleConfirmMessage = document.getElementById('saleConfirmMessage');

        finalizeSaleBtn.addEventListener('click', function () {
            if (cart.length === 0) {
                showMessage('error', 'Le panier est vide. Veuillez ajouter des produits.');
                return;
            }

            // Ajout d'une vérification de stock de dernière minute (bien que le contrôle soit déjà dans updateCartItem)
            const stockError = cart.some(item => {
                // Cette vérification est basée sur le stock initial "stock" que j'ai ajouté dans addToCart pour la démo.
                // En production, il faudrait refetch le stock réel ici.
                return item.quantity > item.stock;
            });

            if (stockError) {
                showMessage('error', 'Une quantité demandée dépasse le stock disponible. Veuillez ajuster le panier.');
                return;
            }

            // Déclencher le calcul une dernière fois pour s'assurer que les champs cachés sont à jour
            calculateTotals();

            const total = parseFloat(document.getElementById('final_montant_total').value).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            // Récupérer la valeur du montant dû après le dernier calculateTotals
            const montantDuText = montantDuDisplay.textContent.replace(' ' + currency, '').replace(/\s/g, '').replace(',', '.');
            const montantDu = parseFloat(montantDuText);
            const montantEpargneUtilise = parseFloat(document.getElementById('final_montant_epargne_utilise').value) || 0;

            let confirmMsg = `Voulez-vous finaliser cette vente pour un total de <span class="font-bold text-blue-700">${total} ${currency}</span> ?`;

            // Afficher les détails de paiement
            const paymentDetails = [];
            const montantRecuEspece = parseFloat(document.getElementById('final_montant_recu_espece').value) || 0;
            const montantRecuMomo = parseFloat(document.getElementById('final_montant_recu_momo').value) || 0;

            if (montantEpargneUtilise > 0) {
                paymentDetails.push(`<span class="font-bold text-green-700">${montantEpargneUtilise.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}</span> depuis l'épargne`);
            }
            if (montantRecuEspece > 0) {
                paymentDetails.push(`<span class="font-bold text-green-700">${montantRecuEspece.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}</span> en espèces`);
            }
            if (montantRecuMomo > 0) {
                paymentDetails.push(`<span class="font-bold text-green-700">${montantRecuMomo.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}</span> en MoMo`);
            }

            if (paymentDetails.length > 0) {
                confirmMsg += `<br><br>Moyens de paiement : ${paymentDetails.join(', ')}.`;
            }

            if (montantDu > 0) {
                // Remonter l'information que si l'on a un montant dû, un client DOIT être sélectionné.
                if (!clientIdSelectedInput.value) {
                    showMessage('error', 'Pour enregistrer une dette, un client doit être sélectionné !');
                    return;
                }
                confirmMsg += `<br><br>Un montant de <span class="font-bold text-red-700">${montantDu.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}</span> restera dû par le client.`;
            }
            saleConfirmMessage.innerHTML = confirmMsg;
            openModal(saleConfirmModal);
        });

        closeSaleConfirmModalBtn.addEventListener('click', () => closeModal(saleConfirmModal));
        cancelSaleBtn.addEventListener('click', () => closeModal(saleConfirmModal));

        // --- Afficher le reçu après une vente réussie - MODIFIÉ : Facture Pro Format uniquement ---
        const receiptModal = document.getElementById('receiptModal');
        const closeReceiptModalBtn = document.getElementById('closeReceiptModalBtn');
        const finalizeReceiptBtn = document.getElementById('finalizeReceiptBtn');
        const newSaleBtn = document.getElementById('newSaleBtn');
        const receiptContent = document.getElementById('receiptContent');

        // Auto-affichage et impression automatique de la facture après une vente validée (si session last_sale_id existe)
        document.addEventListener('DOMContentLoaded', function () {
            <?php if (isset($_SESSION['last_sale_id'])): ?>
                const lastSaleId = <?php echo json_encode($_SESSION['last_sale_id']); ?>;
                <?php unset($_SESSION['last_sale_id']); // On efface la session ici, avant le JavaScript ?>
                fetch(`<?php echo BASE_URL; ?>modules/ventes/ajax_get_sale_details_for_receipt.php?sale_id=${lastSaleId}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Erreur réseau: ' + response.status);
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('Données reçues:', data); // Debug
                        if (data.sale && data.sale.id) {
                            // Générer le contenu du reçu pour le modal
                            generateReceiptContent(data.sale, data.items || []);

                            // IMPRESSION AUTOMATIQUE DE LA FACTURE
                            printSaleReceiptAuto(data.sale, data.items || []);

                            // Afficher le modal pour réimpression ou nouvelle vente
                            openModal(receiptModal);
                        } else if (data.error) {
                            console.error('Erreur serveur:', data.error);
                            showMessage('error', 'Erreur: ' + data.error);
                        } else {
                            console.error('Données invalides:', data);
                            showMessage('error', 'Impossible de charger les détails du reçu. Vente ID: ' + lastSaleId);
                        }
                    })
                    .catch(error => {
                        console.error('Erreur lors du chargement du reçu:', error);
                        showMessage('error', 'Erreur lors du chargement des détails du reçu: ' + error.message);
                    });
            <?php endif; ?>
        });

        // --- MODIFIÉ : Génération uniquement de la Facture Pro Format ---
        function generateReceiptContent(sale, items) {
            const companyInfo = <?php echo json_encode($company_info ?? []); ?>;
            let companyName = companyInfo.nom || "HGB Multi";
            let companyAddress = companyInfo.adresse || "123 Rue de la Quincaillerie, Ville, Pays";
            let companyPhone = companyInfo.telephone || "+123 456 7890";
            let companyEmail = companyInfo.email || "info@hgb.com";
            let companyIfu = companyInfo.ifu || "";
            let companyRccm = companyInfo.rccm || "";

            let clientInfo = sale.client_nom ? `Client: ${htmlspecialchars(sale.client_nom)} ${htmlspecialchars(sale.client_prenom)}` : 'Client: Anonyme';
            if (sale.client_telephone) clientInfo += `<br>Tel: ${htmlspecialchars(sale.client_telephone)}`;

            let headerHtml = `
        <div style="text-align: center; margin-bottom: 10px;">
            <h3 style="margin: 0; font-size: 16px; font-weight: bold;">${companyName}</h3>
            <p style="margin: 2px 0;">${companyAddress}</p>
            <p style="margin: 2px 0;">Tel: ${companyPhone}${companyEmail ? ` | Email: ${companyEmail}` : ''}</p>
            ${(companyIfu || companyRccm) ? `<p style="margin: 2px 0;">${companyIfu ? `IFU : ${companyIfu}` : ''}${(companyIfu && companyRccm) ? ' | ' : ''}${companyRccm ? `RCCM : ${companyRccm}` : ''}</p>` : ''}
            <hr style="border-top: 2px solid #333; margin: 10px 0;">
            <p style="margin: 2px 0; font-size: 16px; font-weight: bold;">FACTURE PRO FORMAT</p>
            <p style="margin: 2px 0;">Date: ${new Date(sale.date_vente).toLocaleString('fr-FR', { dateStyle: 'full', timeStyle: 'short' })}</p>
            <p style="margin: 2px 0;">Numéro de Vente: ${sale.id}</p>
            <p style="margin: 2px 0;">Magasin: ${htmlspecialchars(sale.nom_magasin)}</p>
            <hr style="border-top: 1px dashed #ccc; margin: 8px 0;">
        </div>
        <p style="margin: 2px 0;">${clientInfo}</p>
        <hr style="border-top: 1px dashed #ccc; margin: 8px 0;">
        <div style="display: flex; justify-content: space-between; font-weight: bold;">
            <span style="width: 40%;">PRODUIT</span>
            <span style="width: 20%; text-align: center;">QTÉ</span>
            <span style="width: 20%; text-align: right;">P.U.</span>
            <span style="width: 20%; text-align: right;">TOTAL</span>
        </div>
        <hr style="border-top: 1px dashed #ccc; margin: 4px 0;">
    `;

            let itemsHtml = items.map(item => `
        <div style="display: flex; justify-content: space-between;">
            <span style="width: 40%;">${htmlspecialchars(item.produit_nom)}</span>
            <span style="width: 20%; text-align: center;">${parseFloat(item.quantite).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
            <span style="width: 20%; text-align: right;">${parseFloat(item.prix_vente_unitaire).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
            <span style="width: 20%; text-align: right; font-weight: bold;">${parseFloat(item.total_ligne).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
        </div>
        ${item.reduction_ligne_montant > 0 ? `<div style="text-align: right; font-size: 10px; padding-right: 20%;">Réduction Ligne: ${parseFloat(item.reduction_ligne_montant).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}</div>` : ''}
    `).join('<hr style="border-top: 1px dashed #eee; margin: 4px 0;">');

            let footerHtml = `
        <hr style="border-top: 1px dashed #ccc; margin: 8px 0;">
        <div style="text-align: right;">
            <p style="margin: 2px 0;">Sous-total: ${parseFloat(sale.montant_total + sale.reduction_globale_montant).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}</p>
            ${sale.reduction_globale_montant > 0 ? `<p style="margin: 2px 0;">Réduction Globale: ${parseFloat(sale.reduction_globale_montant).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}</p>` : ''}
            <p style="margin: 2px 0; font-size: 16px; font-weight: bold;">NET À PAYER: ${parseFloat(sale.montant_total).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}</p>
            <p style="margin: 2px 0;">Montant Reçu (Espèces): ${parseFloat(sale.montant_recu_espece).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}</p>
            <p style="margin: 2px 0;">Montant Reçu (Mobile Money): ${parseFloat(sale.montant_recu_momo).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}</p>
            ${sale.montant_change > 0 ? `<p style="margin: 2px 0; color: green;">Change Rendu: ${parseFloat(sale.montant_change).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}</p>` : ''}
            ${sale.montant_du > 0 ? `<p style="margin: 2px 0; color: red;">Montant Dû: ${parseFloat(sale.montant_du).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}</p>` : ''}
            <p style="margin: 2px 0;">Type de Paiement: ${htmlspecialchars(sale.type_paiement)}</p>
            <p style="margin: 2px 0;">Statut de Paiement: ${htmlspecialchars(sale.statut_paiement)}</p>
        </div>
        <hr style="border-top: 2px solid #333; margin: 10px 0;">
        <div style="text-align: center; margin-top: 10px;">
            <p style="margin: 2px 0;">Merci de votre confiance !</p>
            <p style="margin: 2px 0;">Caissier: ${htmlspecialchars(sale.personnel_nom)} ${htmlspecialchars(sale.personnel_prenom)}</p>
            <p style="margin: 2px 0; font-size: 10px;">Logiciel de Gestion de Quincaillerie v1.0</p>
        </div>
    `;

            const printContentHtml = `
        <div style="font-family: 'monospace', 'Courier New', monospace; font-size: 12px; width: 280px; margin: 0 auto; padding: 5px;">
            ${headerHtml}
            ${itemsHtml}
            ${footerHtml}
        </div>
    `;

            receiptContent.innerHTML = printContentHtml;
        }

        // Fonction pour imprimer automatiquement la facture (même format que historique_ventes.php)
        function printSaleReceiptAuto(sale, items) {
            const companyInfo = <?php echo json_encode($company_info ?? []); ?>;
            let companyName = companyInfo.nom || "HGB Multi";
            let companyAddress = companyInfo.adresse || "123 Rue de la Quincaillerie, Ville, Pays";
            let companyPhone = companyInfo.telephone || "+229 01 20202020";
            let companyEmail = companyInfo.email || "info@hgb.com";
            let companyIfu = companyInfo.ifu || "";
            let companyRccm = companyInfo.rccm || "";

            let clientInfo = sale.client_nom ? `Client: ${htmlspecialchars(sale.client_nom)} ${htmlspecialchars(sale.client_prenom)}` : 'Client: Anonyme';
            if (sale.client_telephone) clientInfo += `<br>Tel: ${htmlspecialchars(sale.client_telephone)}`;

            let headerHtml = `
            <div style="text-align: center; margin-bottom: 10px;">
                <h3 style="margin: 0; font-size: 16px; font-weight: bold;">${companyName}</h3>
                <p style="margin: 2px 0;">${companyAddress}</p>
                <p style="margin: 2px 0;">Tel: ${companyPhone}${companyEmail ? ` | Email: ${companyEmail}` : ''}</p>
                ${(companyIfu || companyRccm) ? `<p style="margin: 2px 0;">${companyIfu ? `IFU : ${companyIfu}` : ''}${(companyIfu && companyRccm) ? ' | ' : ''}${companyRccm ? `RCCM : ${companyRccm}` : ''}</p>` : ''}
                <hr style="border-top: 2px solid #333; margin: 10px 0;">
                <p style="margin: 2px 0; font-size: 16px; font-weight: bold;">FACTURE PRO FORMAT</p>
                <p style="margin: 2px 0;">Date: ${new Date(sale.date_vente).toLocaleString('fr-FR', { dateStyle: 'full', timeStyle: 'short' })}</p>
                <p style="margin: 2px 0;">Numéro de Vente: ${sale.id}</p>
                <p style="margin: 2px 0;">Magasin: ${htmlspecialchars(sale.nom_magasin)}</p>
                <hr style="border-top: 1px dashed #ccc; margin: 8px 0;">
            </div>
            <p style="margin: 2px 0;">${clientInfo}</p>
            <hr style="border-top: 1px dashed #ccc; margin: 8px 0;">
            <div style="display: flex; justify-content: space-between; font-weight: bold;">
                <span style="width: 40%;">PRODUIT</span>
                <span style="width: 20%; text-align: center;">QTÉ</span>
                <span style="width: 20%; text-align: right;">P.U.</span>
                <span style="width: 20%; text-align: right;">TOTAL</span>
            </div>
            <hr style="border-top: 1px dashed #ccc; margin: 4px 0;">
        `;

            let itemsHtml = items.map(item => `
            <div style="display: flex; justify-content: space-between;">
                <span style="width: 40%;">${htmlspecialchars(item.produit_nom)}</span>
                <span style="width: 20%; text-align: center;">${parseFloat(item.quantite).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                <span style="width: 20%; text-align: right;">${parseFloat(item.prix_vente_unitaire).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                <span style="width: 20%; text-align: right; font-weight: bold;">${parseFloat(item.total_ligne).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
            </div>
            ${item.reduction_ligne_montant > 0 ? `<div style="text-align: right; font-size: 10px; padding-right: 20%;">Réduction Ligne: ${parseFloat(item.reduction_ligne_montant).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}</div>` : ''}
        `).join('<hr style="border-top: 1px dashed #eee; margin: 4px 0;">');

            let footerHtml = `
            <hr style="border-top: 1px dashed #ccc; margin: 8px 0;">
            <div style="text-align: right;">
                <p style="margin: 2px 0;">Sous-total: ${parseFloat(sale.montant_total + sale.reduction_globale_montant).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}</p>
                ${sale.reduction_globale_montant > 0 ? `<p style="margin: 2px 0;">Réduction Globale: ${parseFloat(sale.reduction_globale_montant).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}</p>` : ''}
                <p style="margin: 2px 0; font-size: 16px; font-weight: bold;">NET À PAYER: ${parseFloat(sale.montant_total).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}</p>
                <p style="margin: 2px 0;">Montant Reçu (Espèces): ${parseFloat(sale.montant_recu_espece).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}</p>
                <p style="margin: 2px 0;">Montant Reçu (Mobile Money): ${parseFloat(sale.montant_recu_momo).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}</p>
                ${sale.montant_change > 0 ? `<p style="margin: 2px 0; color: green;">Change Rendu: ${parseFloat(sale.montant_change).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}</p>` : ''}
                ${sale.montant_du > 0 ? `<p style="margin: 2px 0; color: red;">Montant Dû: ${parseFloat(sale.montant_du).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currency}</p>` : ''}
                <p style="margin: 2px 0;">Type de Paiement: ${htmlspecialchars(sale.type_paiement)}</p>
                <p style="margin: 2px 0;">Statut de Paiement: ${htmlspecialchars(sale.statut_paiement)}</p>
            </div>
            <hr style="border-top: 2px solid #333; margin: 10px 0;">
            <div style="text-align: center; margin-top: 10px;">
                <p style="margin: 2px 0;">Merci de votre confiance !</p>
                <p style="margin: 2px 0;">Caissier: ${htmlspecialchars(sale.personnel_nom)} ${htmlspecialchars(sale.personnel_prenom)}</p>
                <p style="margin: 2px 0; font-size: 10px;">Logiciel de Gestion de Quincaillerie v1.0</p>
            </div>
        `;

            const printContent = `
            <div style="font-family: 'monospace', 'Courier New', monospace; font-size: 12px; width: 280px; margin: 0 auto; padding: 5px;">
                ${headerHtml}
                ${itemsHtml}
                ${footerHtml}
            </div>
        `;

            // Utiliser une iframe cachée pour éviter le blocage du popup
            let printFrame = document.getElementById('printFrame');
            if (!printFrame) {
                printFrame = document.createElement('iframe');
                printFrame.id = 'printFrame';
                printFrame.style.position = 'absolute';
                printFrame.style.top = '-10000px';
                printFrame.style.left = '-10000px';
                printFrame.style.width = '0';
                printFrame.style.height = '0';
                document.body.appendChild(printFrame);
            }

            const printDoc = printFrame.contentWindow || printFrame.contentDocument;
            const doc = printDoc.document || printDoc;

            doc.open();
            doc.write('<html><head><title>FACTURE PRO FORMAT</title>');
            doc.write('<style>body { font-family: monospace; font-size: 12px; margin: 0; padding: 5px; }</style>');
            doc.write('</head><body>');
            doc.write(printContent);
            doc.write('</body></html>');
            doc.close();

            // Attendre que le contenu soit chargé puis imprimer
            printFrame.onload = function () {
                try {
                    printFrame.contentWindow.focus();
                    printFrame.contentWindow.print();
                } catch (e) {
                    console.error('Erreur lors de l\'impression:', e);
                }
            };

            // Déclencher l'impression après un court délai si onload ne fonctionne pas
            setTimeout(function () {
                try {
                    printFrame.contentWindow.focus();
                    printFrame.contentWindow.print();
                } catch (e) {
                    console.error('Erreur lors de l\'impression (délai):', e);
                }
            }, 500);
        }

        finalizeReceiptBtn.addEventListener('click', function () {
            const printContent = receiptContent.innerHTML;
            const printWindow = window.open('', '', 'height=600,width=400');
            printWindow.document.write('<html><head><title>FACTURE PRO FORMAT</title>');
            printWindow.document.write('<style>body { font-family: monospace; font-size: 12px; margin: 0; padding: 5px; }</style>');
            printWindow.document.write('</head><body>');
            printWindow.document.write(printContent);
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.print();
        });

        newSaleBtn.addEventListener('click', function () {
            closeModal(receiptModal);
            cart = [];
            renderCart();
            montantRecuEspeceInput.value = '0.00';
            montantRecuMomoInput.value = '0.00';
            globalDiscountPercentageInput.value = '0.00';
            clientIdSelectedInput.value = '';
            clientNameDisplay.textContent = '';
            selectedClientInfo.classList.add('hidden');
            calculateTotals();
            showMessage('success', 'Prêt pour une nouvelle vente !');
        });

        closeReceiptModalBtn.addEventListener('click', () => closeModal(receiptModal));

        renderCart();

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

        function showMessage(type, message) {
            alert(message);
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
    </script>
    <?php ob_end_flush(); ?>
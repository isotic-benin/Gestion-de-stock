<?php
// modules/rapports/statistiques_rapports.php
// session_start();

// Inclure les fichiers nécessaires
include '../../db_connect.php';
include '../../includes/permissions_helper.php'; // Helper de permissions
include '../../includes/header.php'; // Inclut le header avec le début du HTML

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("location: " . BASE_URL . "public/login.php");
    exit;
}

// Vérifier les permissions d'accès au module rapports (lecture)
if (!hasPermission($conn, 'rapports', 'read')) {
    header("location: " . BASE_URL . "dashboard.php?error=access_denied");
    exit;
}

$page_title = "Statistiques et Rapports";

$message = ''; // Pour afficher les messages de succès ou d'erreur

// --- Gestion des filtres ---
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$magasin_id_filter = isset($_GET['magasin_id']) ? $_GET['magasin_id'] : '';

$sql_filters = '';
if (!empty($start_date)) {
    $sql_filters .= " AND v.date_vente >= '" . mysqli_real_escape_string($conn, $start_date) . " 00:00:00'";
}
if (!empty($end_date)) {
    $sql_filters .= " AND v.date_vente <= '" . mysqli_real_escape_string($conn, $end_date) . " 23:59:59'";
}
if (!empty($magasin_id_filter)) {
    $sql_filters .= " AND v.magasin_id = " . mysqli_real_escape_string($conn, $magasin_id_filter);
}

// Pour les statistiques qui ne concernent pas les ventes, ajuster les filtres
$sql_produits_filters = ''; // Pas de filtre de date directement sur les produits, mais peut être lié au magasin
if (!empty($magasin_id_filter)) {
    // Si la table produits avait une colonne magasin_id, on l'utiliserait ici.
    // Pour l'instant, on suppose les produits globaux ou on ajuste si nécessaire.
    // Par exemple, si les produits sont spécifiques à un magasin:
    $sql_produits_filters .= " AND p.magasin_id = " . mysqli_real_escape_string($conn, $magasin_id_filter);
}

$sql_dettes_clients_filters = '';
if (!empty($start_date)) {
    $sql_dettes_clients_filters .= " AND date_creation >= '" . mysqli_real_escape_string($conn, $start_date) . " 00:00:00'";
}
if (!empty($end_date)) {
    $sql_dettes_clients_filters .= " AND date_creation <= '" . mysqli_real_escape_string($conn, $end_date) . " 23:59:59'";
}
// Les dettes clients ne sont pas directement liées à un magasin dans la table,
// mais on pourrait les lier via les ventes si la dette est issue d'une vente.
// Pour l'instant, pas de filtre magasin_id sur dettes_clients directement.

$sql_dettes_magasins_filters = '';
if (!empty($start_date)) {
    $sql_dettes_magasins_filters .= " AND date_dette >= '" . mysqli_real_escape_string($conn, $start_date) . " 00:00:00'";
}
if (!empty($end_date)) {
    $sql_dettes_magasins_filters .= " AND date_dette <= '" . mysqli_real_escape_string($conn, $end_date) . " 23:59:59'";
}
if (!empty($magasin_id_filter)) {
    $sql_dettes_magasins_filters .= " AND magasin_id = " . mysqli_real_escape_string($conn, $magasin_id_filter);
}

$sql_epargne_clients_filters = '';
// L'épargne client n'est pas directement filtrable par date de transaction ou magasin dans la table `comptes_epargne`
// Il faudrait joindre `transactions_epargne` pour filtrer par date de transaction, et `clients` puis `ventes` pour lier à un magasin.
// Pour la simplicité, on garde le total global de l'épargne.


// --- Récupération des Statistiques ---

// 1. Bénéfices total des ventes
$total_benefices = 0;
$sql_benefices = "SELECT SUM(dv.total_ligne - (dv.quantite * dv.prix_achat_unitaire)) AS total_profit
                  FROM details_vente dv
                  JOIN ventes v ON dv.vente_id = v.id
                  WHERE v.statut_paiement = 'paye' " . $sql_filters;
$result_benefices = mysqli_query($conn, $sql_benefices);
if ($result_benefices && mysqli_num_rows($result_benefices) > 0) {
    $row = mysqli_fetch_assoc($result_benefices);
    $total_benefices = $row['total_profit'] ?? 0;
}

// 2. Total des produits
$total_produits = 0;
// Note: Assuming 'produits' table has a 'magasin_id' column for filtering
$sql_total_produits = "SELECT COUNT(p.id) AS total_count FROM produits p WHERE 1 " . $sql_produits_filters;
$result_total_produits = mysqli_query($conn, $sql_total_produits);
if ($result_total_produits && mysqli_num_rows($result_total_produits) > 0) {
    $row = mysqli_fetch_assoc($result_total_produits);
    $total_produits = $row['total_count'] ?? 0;
}

// 3. Nombre de produits en alerte de stock
$produits_alerte_stock = 0;
// Note: Assuming 'produits' table has a 'magasin_id' column for filtering
$sql_alerte_stock = "SELECT COUNT(p.id) AS alert_count FROM produits p WHERE p.quantite_stock <= p.seuil_alerte_stock " . $sql_produits_filters;
$result_alerte_stock = mysqli_query($conn, $sql_alerte_stock);
if ($result_alerte_stock && mysqli_num_rows($result_alerte_stock) > 0) {
    $row = mysqli_fetch_assoc($result_alerte_stock);
    $produits_alerte_stock = $row['alert_count'] ?? 0;
}

// 4. Total des dettes clients (montant restant dû)
$total_dettes_clients = 0;
$sql_dettes_clients = "SELECT SUM(montant_restant) AS total_debt FROM dettes_clients WHERE (statut = 'en_cours' OR statut = 'partiellement_paye')" . $sql_dettes_clients_filters;
$result_dettes_clients = mysqli_query($conn, $sql_dettes_clients);
if ($result_dettes_clients && mysqli_num_rows($result_dettes_clients) > 0) {
    $row = mysqli_fetch_assoc($result_dettes_clients);
    $total_dettes_clients = $row['total_debt'] ?? 0;
}

// 5. Total des dettes des magasins (dettes de l'entreprise)
$total_dettes_magasins = 0;
$sql_dettes_magasins = "SELECT SUM(montant_total - montant_paye) AS total_debt FROM dettes_magasins WHERE (montant_total - montant_paye > 0)" . $sql_dettes_magasins_filters;
$result_dettes_magasins = mysqli_query($conn, $sql_dettes_magasins);
if ($result_dettes_magasins && mysqli_num_rows($result_dettes_magasins) > 0) {
    $row = mysqli_fetch_assoc($result_dettes_magasins);
    $total_dettes_magasins = $row['total_debt'] ?? 0;
}

// 6. Total de l'épargne des clients
$total_epargne_clients = 0;
$sql_epargne_clients = "SELECT SUM(solde) AS total_savings FROM comptes_epargne" . $sql_epargne_clients_filters;
$result_epargne_clients = mysqli_query($conn, $sql_epargne_clients);
if ($result_epargne_clients && mysqli_num_rows($result_epargne_clients) > 0) {
    $row = mysqli_fetch_assoc($result_epargne_clients);
    $total_epargne_clients = $row['total_savings'] ?? 0;
}

// 7. Total des ventes (montant total)
$total_ventes = 0;
$sql_total_ventes = "SELECT SUM(v.montant_total) AS total_sales FROM ventes v WHERE v.statut_paiement = 'paye' " . $sql_filters;
$result_total_ventes = mysqli_query($conn, $sql_total_ventes);
if ($result_total_ventes && mysqli_num_rows($result_total_ventes) > 0) {
    $row = mysqli_fetch_assoc($result_total_ventes);
    $total_ventes = $row['total_sales'] ?? 0;
}

// 8. Chiffre d'affaires total de tous les produits en stock
// (quantité d'un produit * prix unitaire de vente) pour tous les produits en stock
$chiffre_affaires_stock = 0;
// Note: Assuming 'produits' table has a 'magasin_id' column for filtering
$sql_chiffre_affaires_stock = "SELECT SUM(p.quantite_stock * p.prix_achat) AS total_ca_stock FROM produits p WHERE 1 " . $sql_produits_filters;
$result_chiffre_affaires_stock = mysqli_query($conn, $sql_chiffre_affaires_stock);
if ($result_chiffre_affaires_stock && mysqli_num_rows($result_chiffre_affaires_stock) > 0) {
    $row = mysqli_fetch_assoc($result_chiffre_affaires_stock);
    $chiffre_affaires_stock = $row['total_ca_stock'] ?? 0;
}


// Récupérer la liste des magasins pour le filtre
$magasins = [];
$sql_magasins = "SELECT id, nom FROM magasins ORDER BY nom ASC";
$result_magasins = mysqli_query($conn, $sql_magasins);
if ($result_magasins) {
    while ($row = mysqli_fetch_assoc($result_magasins)) {
        $magasins[] = $row;
    }
}

// Fermer la connexion à la base de données après avoir récupéré toutes les données
// La connexion sera fermée automatiquement à la fin du script ou après l'inclusion de la sidebar
// mysqli_close($conn);

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
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">Filtres de Statistiques</h2>
                    <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <div>
                            <label for="start_date" class="block text-gray-700 text-sm font-bold mb-2">Date de
                                début:</label>
                            <input type="date" id="start_date" name="start_date"
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                value="<?php echo htmlspecialchars($start_date); ?>">
                        </div>
                        <div>
                            <label for="end_date" class="block text-gray-700 text-sm font-bold mb-2">Date de
                                fin:</label>
                            <input type="date" id="end_date" name="end_date"
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                value="<?php echo htmlspecialchars($end_date); ?>">
                        </div>
                        <div>
                            <label for="magasin_id" class="block text-gray-700 text-sm font-bold mb-2">Magasin:</label>
                            <select id="magasin_id" name="magasin_id"
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                <option value="">Tous les magasins</option>
                                <?php foreach ($magasins as $magasin): ?>
                                    <option value="<?php echo htmlspecialchars($magasin['id']); ?>" <?php echo ($magasin_id_filter == $magasin['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($magasin['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="md:col-span-3 flex justify-end">
                            <button type="submit"
                                class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                                Appliquer les filtres
                            </button>
                            <a href="<?php echo BASE_URL; ?>modules/rapports/statistiques_rapports.php"
                                class="ml-2 bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                                Réinitialiser
                            </a>
                        </div>
                    </form>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-md mb-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">Statistiques Clés</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">

                        <div
                            class="bg-gradient-to-br from-green-400 to-green-600 text-white p-6 rounded-lg shadow-lg flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold opacity-90">Bénéfices Total des Ventes</h3>
                                <p class="text-4xl font-bold mt-2">
                                    <?php echo number_format($total_benefices, 2, ',', ' '); ?> XOF
                                </p>
                            </div>
                            <i class="fas fa-chart-line text-5xl opacity-30"></i>
                        </div>

                        <div
                            class="bg-gradient-to-br from-indigo-400 to-indigo-600 text-white p-6 rounded-lg shadow-lg flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold opacity-90">Total des Ventes</h3>
                                <p class="text-4xl font-bold mt-2">
                                    <?php echo number_format($total_ventes, 2, ',', ' '); ?> XOF
                                </p>
                            </div>
                            <i class="fas fa-money-bill-wave text-5xl opacity-30"></i>
                        </div>

                        <div
                            class="bg-gradient-to-br from-blue-400 to-blue-600 text-white p-6 rounded-lg shadow-lg flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold opacity-90">Total des Produits</h3>
                                <p class="text-4xl font-bold mt-2">
                                    <?php echo number_format($total_produits, 0, ',', ' '); ?>
                                </p>
                            </div>
                            <i class="fas fa-boxes text-5xl opacity-30"></i>
                        </div>

                        <div
                            class="bg-gradient-to-br from-yellow-400 to-yellow-600 text-white p-6 rounded-lg shadow-lg flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold opacity-90">Produits en Alerte de Stock</h3>
                                <p class="text-4xl font-bold mt-2">
                                    <?php echo number_format($produits_alerte_stock, 0, ',', ' '); ?>
                                </p>
                            </div>
                            <i class="fas fa-exclamation-triangle text-5xl opacity-30"></i>
                        </div>

                        <div
                            class="bg-gradient-to-br from-red-400 to-red-600 text-white p-6 rounded-lg shadow-lg flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold opacity-90">Total Dettes Clients</h3>
                                <p class="text-4xl font-bold mt-2">
                                    <?php echo number_format($total_dettes_clients, 2, ',', ' '); ?> XOF
                                </p>
                            </div>
                            <i class="fas fa-hand-holding-dollar text-5xl opacity-30"></i>
                        </div>

                        <div
                            class="bg-gradient-to-br from-purple-400 to-purple-600 text-white p-6 rounded-lg shadow-lg flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold opacity-90">Total Dettes Magasins</h3>
                                <p class="text-4xl font-bold mt-2">
                                    <?php echo number_format($total_dettes_magasins, 2, ',', ' '); ?> XOF
                                </p>
                            </div>
                            <i class="fas fa-store text-5xl opacity-30"></i>
                        </div>

                        <div
                            class="bg-gradient-to-br from-teal-400 to-teal-600 text-white p-6 rounded-lg shadow-lg flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold opacity-90">Total Épargne Clients</h3>
                                <p class="text-4xl font-bold mt-2">
                                    <?php echo number_format($total_epargne_clients, 2, ',', ' '); ?> XOF
                                </p>
                            </div>
                            <i class="fas fa-piggy-bank text-5xl opacity-30"></i>
                        </div>

                        <!-- Nouvelle rubrique pour le chiffre d'affaires des produits en stock -->
                        <div
                            class="bg-gradient-to-br from-orange-400 to-orange-600 text-white p-6 rounded-lg shadow-lg flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold opacity-90">Chiffre d'Affaires Stock</h3>
                                <p class="text-4xl font-bold mt-2">
                                    <?php echo number_format($chiffre_affaires_stock, 2, ',', ' '); ?> XOF
                                </p>
                            </div>
                            <i class="fas fa-chart-pie text-5xl opacity-30"></i>
                        </div>

                    </div>

                    <h2 class="text-2xl font-bold text-gray-800 mb-4">Options d'Exportation</h2>
                    <div class="flex flex-col md:flex-row gap-4">
                        <button id="exportPdfBtn"
                            class="bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-6 rounded-md shadow-md transition duration-300 flex-1">
                            <i class="fas fa-file-pdf mr-2"></i> Exporter en PDF
                        </button>
                        <button id="exportExcelBtn"
                            class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-6 rounded-md shadow-md transition duration-300 flex-1">
                            <i class="fas fa-file-excel mr-2"></i> Exporter en Excel
                        </button>
                    </div>
                </div>
            </div>
        </main>
        <?php include '../../includes/footer.php'; ?>
    </div>
</div>



<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Refs pour les boutons d'exportation
        const exportPdfBtn = document.getElementById('exportPdfBtn');
        const exportExcelBtn = document.getElementById('exportExcelBtn');

        // --- Gestion des exportations ---
        exportPdfBtn.addEventListener('click', function () {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            const magasinId = document.getElementById('magasin_id').value;
            let exportUrl = '<?php echo BASE_URL; ?>modules/rapports/ajax_export_pdf.php?';
            if (startDate) exportUrl += 'start_date=' + startDate + '&';
            if (endDate) exportUrl += 'end_date=' + endDate + '&';
            if (magasinId) exportUrl += 'magasin_id=' + magasinId + '&';
            window.location.href = exportUrl.slice(0, -1); // Supprime le dernier '&'
        });

        exportExcelBtn.addEventListener('click', function () {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            const magasinId = document.getElementById('magasin_id').value;
            let exportUrl = '<?php echo BASE_URL; ?>modules/rapports/ajax_export_excel.php?';
            if (startDate) exportUrl += 'start_date=' + startDate + '&';
            if (endDate) exportUrl += 'end_date=' + endDate + '&';
            if (magasinId) exportUrl += 'magasin_id=' + magasinId + '&';
            window.location.href = exportUrl.slice(0, -1); // Supprime le dernier '&'
        });


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
<?php
// dashboard.php
session_start();

// Inclure les fichiers nécessaires
include 'db_connect.php'; // Ceci inclura config.php en premier
include 'includes/header.php'; // Inclut le header avec le début du HTML

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("location: " . BASE_URL . "public/login.php"); // Utilisation de BASE_URL
    exit;
}

$page_title = "Tableau de Bord";

// Récupérer la devise depuis les infos entreprise
$devise = $company_info['devise'] ?? 'XOF';

// 1. Produits en alerte de stock
$produits_alerte = [];
$sql_alerte = "SELECT p.id, p.nom, p.quantite_stock, p.seuil_alerte_stock, m.nom AS nom_magasin
               FROM produits p
               LEFT JOIN magasins m ON p.magasin_id = m.id
               WHERE p.quantite_stock <= p.seuil_alerte_stock
               ORDER BY (p.quantite_stock / NULLIF(p.seuil_alerte_stock, 0)) ASC
               LIMIT 10";
$result_alerte = mysqli_query($conn, $sql_alerte);
if ($result_alerte) {
    while ($row = mysqli_fetch_assoc($result_alerte)) {
        $produits_alerte[] = $row;
    }
}

// 2. Transferts de stock non confirmés
$transferts_non_confirmes = [];
$sql_transferts = "SELECT t.id, t.date_demande, p.nom AS produit_nom, 
                          ms.nom AS magasin_source, md.nom AS magasin_destination,
                          t.quantite, t.statut
                   FROM transferts_stock t
                   JOIN produits p ON t.produit_id = p.id
                   JOIN magasins ms ON t.magasin_source_id = ms.id
                   JOIN magasins md ON t.magasin_destination_id = md.id
                   WHERE t.statut = 'en_attente'
                   ORDER BY t.date_demande DESC
                   LIMIT 10";
$result_transferts = mysqli_query($conn, $sql_transferts);
if ($result_transferts) {
    while ($row = mysqli_fetch_assoc($result_transferts)) {
        $transferts_non_confirmes[] = $row;
    }
}

// 3. Produits les plus vendus (30 derniers jours)
$produits_plus_vendus = [];
$sql_produits_vendus = "SELECT p.nom, SUM(dv.quantite) AS total_quantite_vendue,
                               SUM(dv.total_ligne) AS total_vente
                        FROM details_vente dv
                        JOIN produits p ON dv.produit_id = p.id
                        JOIN ventes v ON dv.vente_id = v.id
                        WHERE v.date_vente >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                        GROUP BY p.id, p.nom
                        ORDER BY total_quantite_vendue DESC
                        LIMIT 10";
$result_produits_vendus = mysqli_query($conn, $sql_produits_vendus);
if ($result_produits_vendus) {
    while ($row = mysqli_fetch_assoc($result_produits_vendus)) {
        $produits_plus_vendus[] = $row;
    }
}

// 4. Meilleurs clients (30 derniers jours)
$meilleurs_clients = [];
$sql_clients = "SELECT c.id, c.nom, c.prenom, c.telephone,
                       COUNT(v.id) AS nombre_achats,
                       SUM(v.montant_total) AS total_achats
                FROM clients c
                JOIN ventes v ON c.id = v.client_id
                WHERE v.date_vente >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY c.id, c.nom, c.prenom, c.telephone
                ORDER BY total_achats DESC
                LIMIT 10";
$result_clients = mysqli_query($conn, $sql_clients);
if ($result_clients) {
    while ($row = mysqli_fetch_assoc($result_clients)) {
        $meilleurs_clients[] = $row;
    }
}

// 5. Magasins avec le plus de chiffre d'affaires (30 derniers jours)
$magasins_ca = [];
$sql_magasins_ca = "SELECT m.id, m.nom, 
                           COUNT(v.id) AS nombre_ventes,
                           SUM(v.montant_total) AS chiffre_affaires
                    FROM magasins m
                    LEFT JOIN ventes v ON m.id = v.magasin_id
                    WHERE v.date_vente >= DATE_SUB(NOW(), INTERVAL 30 DAY) OR v.date_vente IS NULL
                    GROUP BY m.id, m.nom
                    ORDER BY chiffre_affaires DESC
                    LIMIT 10";
$result_magasins_ca = mysqli_query($conn, $sql_magasins_ca);
if ($result_magasins_ca) {
    while ($row = mysqli_fetch_assoc($result_magasins_ca)) {
        $magasins_ca[] = $row;
    }
}

// 6. Fournisseurs dont les produits sont les plus vendus (30 derniers jours)
$fournisseurs_top = [];
$sql_fournisseurs_top = "SELECT f.id, f.nom, f.contact_personne, f.telephone,
                                COUNT(DISTINCT p.id) AS nombre_produits_vendus,
                                SUM(dv.quantite) AS total_quantite_vendue,
                                SUM(dv.total_ligne) AS total_ventes
                         FROM fournisseurs f
                         JOIN produits p ON p.fournisseur_id = f.id
                         JOIN details_vente dv ON dv.produit_id = p.id
                         JOIN ventes v ON dv.vente_id = v.id
                         WHERE v.date_vente >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                         GROUP BY f.id, f.nom, f.contact_personne, f.telephone
                         ORDER BY total_ventes DESC
                         LIMIT 10";
$result_fournisseurs_top = mysqli_query($conn, $sql_fournisseurs_top);
if ($result_fournisseurs_top) {
    while ($row = mysqli_fetch_assoc($result_fournisseurs_top)) {
        $fournisseurs_top[] = $row;
    }
}

?>

<div class="flex h-screen bg-gray-100">
    <!-- Sidebar -->
    <?php include 'includes/sidebar_dashboard.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden md:ml-64">
        <!-- Top Bar -->
        <?php include 'includes/topbar.php'; ?>

        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6 pb-20">
            <div class="container mx-auto">
                <h1 class="text-3xl font-bold text-gray-800 mb-6"><?php echo $page_title; ?></h1>

                <!-- Widgets de statistiques -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                    <!-- Produits en alerte -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-600 text-sm">Produits en Alerte</p>
                                <p class="text-2xl font-bold text-red-600"><?php echo count($produits_alerte); ?></p>
                            </div>
                            <div class="bg-red-100 rounded-full p-3">
                                <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Transferts non confirmés -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-600 text-sm">Transferts en Attente</p>
                                <p class="text-2xl font-bold text-orange-600">
                                    <?php echo count($transferts_non_confirmes); ?></p>
                            </div>
                            <div class="bg-orange-100 rounded-full p-3">
                                <i class="fas fa-exchange-alt text-orange-600 text-2xl"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Total produits vendus -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-600 text-sm">Produits Vendus (30j)</p>
                                <p class="text-2xl font-bold text-blue-600">
                                    <?php
                                    $total_produits_vendus = array_sum(array_column($produits_plus_vendus, 'total_quantite_vendue'));
                                    echo number_format($total_produits_vendus, 0, ',', ' ');
                                    ?>
                                </p>
                            </div>
                            <div class="bg-blue-100 rounded-full p-3">
                                <i class="fas fa-shopping-cart text-blue-600 text-2xl"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Chiffre d'affaires total -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-600 text-sm">CA Total (30j)</p>
                                <p class="text-2xl font-bold text-green-600">
                                    <?php
                                    $total_ca = array_sum(array_column($magasins_ca, 'chiffre_affaires'));
                                    echo number_format($total_ca, 0, ',', ' ') . ' ' . htmlspecialchars($devise);
                                    ?>
                                </p>
                            </div>
                            <div class="bg-green-100 rounded-full p-3">
                                <i class="fas fa-chart-line text-green-600 text-2xl"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sections détaillées -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Produits en alerte de stock -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-exclamation-triangle text-red-600 mr-2"></i>
                            Produits en Alerte de Stock
                        </h2>
                        <?php if (!empty($produits_alerte)): ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full">
                                    <thead>
                                        <tr class="bg-gray-50">
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                                Produit</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                                Magasin</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                                Stock</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                                Seuil</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <?php foreach ($produits_alerte as $produit): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-2 text-sm"><?php echo htmlspecialchars($produit['nom']); ?>
                                                </td>
                                                <td class="px-4 py-2 text-sm">
                                                    <?php echo htmlspecialchars($produit['nom_magasin'] ?? 'N/A'); ?></td>
                                                <td class="px-4 py-2 text-sm text-red-600 font-bold">
                                                    <?php echo number_format($produit['quantite_stock'], 2, ',', ' '); ?></td>
                                                <td class="px-4 py-2 text-sm">
                                                    <?php echo number_format($produit['seuil_alerte_stock'], 2, ',', ' '); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-500 text-center py-4">Aucun produit en alerte de stock.</p>
                        <?php endif; ?>
                    </div>

                    <!-- Transferts de stock non confirmés -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-exchange-alt text-orange-600 mr-2"></i>
                            Transferts de Stock en Attente
                        </h2>
                        <?php if (!empty($transferts_non_confirmes)): ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full">
                                    <thead>
                                        <tr class="bg-gray-50">
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                                Produit</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">De →
                                                Vers</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                                Quantité</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <?php foreach ($transferts_non_confirmes as $transfert): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-2 text-sm">
                                                    <?php echo htmlspecialchars($transfert['produit_nom']); ?></td>
                                                <td class="px-4 py-2 text-sm">
                                                    <?php echo htmlspecialchars($transfert['magasin_source']); ?> →
                                                    <?php echo htmlspecialchars($transfert['magasin_destination']); ?>
                                                </td>
                                                <td class="px-4 py-2 text-sm">
                                                    <?php echo number_format($transfert['quantite'], 2, ',', ' '); ?></td>
                                                <td class="px-4 py-2 text-sm">
                                                    <?php echo date('d/m/Y', strtotime($transfert['date_demande'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-500 text-center py-4">Aucun transfert en attente.</p>
                        <?php endif; ?>
                    </div>

                    <!-- Produits les plus vendus -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-star text-yellow-600 mr-2"></i>
                            Produits les Plus Vendus (30 jours)
                        </h2>
                        <?php if (!empty($produits_plus_vendus)): ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full">
                                    <thead>
                                        <tr class="bg-gray-50">
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                                Produit</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                                Quantité</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">CA
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <?php foreach ($produits_plus_vendus as $produit): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-2 text-sm"><?php echo htmlspecialchars($produit['nom']); ?>
                                                </td>
                                                <td class="px-4 py-2 text-sm font-semibold">
                                                    <?php echo number_format($produit['total_quantite_vendue'], 2, ',', ' '); ?>
                                                </td>
                                                <td class="px-4 py-2 text-sm text-green-600 font-bold">
                                                    <?php echo number_format($produit['total_vente'], 0, ',', ' ') . ' ' . htmlspecialchars($devise); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-500 text-center py-4">Aucune vente enregistrée.</p>
                        <?php endif; ?>
                    </div>

                    <!-- Meilleurs clients -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-users text-blue-600 mr-2"></i>
                            Meilleurs Clients (30 jours)
                        </h2>
                        <?php if (!empty($meilleurs_clients)): ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full">
                                    <thead>
                                        <tr class="bg-gray-50">
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                                Client</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                                Achats</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                                Total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <?php foreach ($meilleurs_clients as $client): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-2 text-sm">
                                                    <?php echo htmlspecialchars($client['nom'] . ' ' . $client['prenom']); ?>
                                                </td>
                                                <td class="px-4 py-2 text-sm"><?php echo $client['nombre_achats']; ?></td>
                                                <td class="px-4 py-2 text-sm text-green-600 font-bold">
                                                    <?php echo number_format($client['total_achats'], 0, ',', ' ') . ' ' . htmlspecialchars($devise); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-500 text-center py-4">Aucun client trouvé.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Magasins avec le plus de chiffre d'affaires -->
                <div class="mt-6 bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-store text-purple-600 mr-2"></i>
                        Magasins - Chiffre d'Affaires (30 jours)
                    </h2>
                    <?php if (!empty($magasins_ca)): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Magasin
                                        </th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nombre
                                            de Ventes</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Chiffre
                                            d'Affaires</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php foreach ($magasins_ca as $magasin): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2 text-sm font-semibold">
                                                <?php echo htmlspecialchars($magasin['nom']); ?></td>
                                            <td class="px-4 py-2 text-sm"><?php echo $magasin['nombre_ventes'] ?? 0; ?></td>
                                            <td class="px-4 py-2 text-sm text-green-600 font-bold">
                                                <?php echo number_format($magasin['chiffre_affaires'] ?? 0, 0, ',', ' ') . ' ' . htmlspecialchars($devise); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-500 text-center py-4">Aucun magasin trouvé.</p>
                    <?php endif; ?>
                </div>

                <!-- Fournisseurs dont les produits sont les plus vendus -->
                <div class="mt-6 bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-truck text-teal-600 mr-2"></i>
                        Fournisseurs - Produits les Plus Vendus (30 jours)
                    </h2>
                    <?php if (!empty($fournisseurs_top)): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fournisseur</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Contact</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Produits Vendus</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Qté Totale</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Chiffre d'Affaires</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php foreach ($fournisseurs_top as $fournisseur): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2 text-sm font-semibold">
                                                <?php echo htmlspecialchars($fournisseur['nom']); ?>
                                            </td>
                                            <td class="px-4 py-2 text-sm">
                                                <?php 
                                                    $contact_info = [];
                                                    if (!empty($fournisseur['contact_personne'])) {
                                                        $contact_info[] = htmlspecialchars($fournisseur['contact_personne']);
                                                    }
                                                    if (!empty($fournisseur['telephone'])) {
                                                        $contact_info[] = htmlspecialchars($fournisseur['telephone']);
                                                    }
                                                    echo !empty($contact_info) ? implode(' - ', $contact_info) : 'N/A';
                                                ?>
                                            </td>
                                            <td class="px-4 py-2 text-sm text-center">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                    <?php echo $fournisseur['nombre_produits_vendus']; ?> produit(s)
                                                </span>
                                            </td>
                                            <td class="px-4 py-2 text-sm font-semibold">
                                                <?php echo number_format($fournisseur['total_quantite_vendue'], 2, ',', ' '); ?>
                                            </td>
                                            <td class="px-4 py-2 text-sm text-green-600 font-bold">
                                                <?php echo number_format($fournisseur['total_ventes'] ?? 0, 0, ',', ' ') . ' ' . htmlspecialchars($devise); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-500 text-center py-4">Aucun fournisseur avec des ventes enregistrées.</p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
        <?php include 'includes/footer.php'; ?>
    </div>
</div>

<script src="/public/assets/js/script.js"></script>
</body>

</html>
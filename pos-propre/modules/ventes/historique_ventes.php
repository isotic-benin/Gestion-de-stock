<?php
// modules/ventes/historique_ventes.php
session_start();

include '../../db_connect.php';
include '../../includes/header.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("location: " . BASE_URL . "public/login.php");
    exit;
}

$page_title = "Historique des Ventes";
$message = '';

// --- Logique de listage (Pagination, Recherche, Tri) ---
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$search_query = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$sort_by = isset($_GET['sort_by']) ? sanitize_input($_GET['sort_by']) : 'v.date_vente';
$sort_order = isset($_GET['sort_order']) && in_array(strtoupper($_GET['sort_order']), ['ASC', 'DESC']) ? $_GET['sort_order'] : 'DESC';
$filter_magasin = isset($_GET['filter_magasin']) ? sanitize_input($_GET['filter_magasin']) : '';
$filter_statut_paiement = isset($_GET['filter_statut_paiement']) ? sanitize_input($_GET['filter_statut_paiement']) : 'tous';

// Construction de la clause WHERE
$where_clause = '';
$params = [];
$param_types = '';

if (!empty($search_query)) {
    $where_clause .= " WHERE (c.nom LIKE ? OR c.prenom LIKE ? OR p.nom LIKE ? OR p.prenom LIKE ? OR m.nom LIKE ?)";
    $search_param = '%' . $search_query . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'sssss';
}

if (!empty($filter_magasin)) {
    if (empty($where_clause))
        $where_clause .= " WHERE";
    else
        $where_clause .= " AND";
    $where_clause .= " v.magasin_id = ?";
    $params[] = $filter_magasin;
    $param_types .= 'i';
}

if (!empty($filter_statut_paiement) && $filter_statut_paiement != 'tous') {
    if (empty($where_clause))
        $where_clause .= " WHERE";
    else
        $where_clause .= " AND";
    $where_clause .= " v.statut_paiement = ?";
    $params[] = $filter_statut_paiement;
    $param_types .= 's';
}

// Requête pour le nombre total de ventes
$count_sql = "SELECT COUNT(v.id) AS total FROM ventes v
              LEFT JOIN clients c ON v.client_id = c.id
              LEFT JOIN magasins m ON v.magasin_id = m.id
              LEFT JOIN personnel p ON v.personnel_id = p.id"
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

// Requête pour récupérer les ventes
$sql = "SELECT v.id, v.client_id, v.magasin_id, v.personnel_id, v.date_vente, v.montant_total,
               v.montant_recu_espece, v.montant_recu_momo, v.montant_change, v.montant_du,
               v.type_paiement, v.statut_paiement, v.reduction_globale_pourcentage, v.reduction_globale_montant,
               c.nom AS client_nom, c.prenom AS client_prenom, c.telephone AS client_telephone,
               m.nom AS nom_magasin, p.nom AS personnel_nom, p.prenom AS personnel_prenom,
               GROUP_CONCAT(pr.nom SEPARATOR ', ') AS produits_vendus,
               COUNT(DISTINCT rv.id) AS nombre_retours
        FROM ventes v
        LEFT JOIN clients c ON v.client_id = c.id
        LEFT JOIN magasins m ON v.magasin_id = m.id
        LEFT JOIN personnel p ON v.personnel_id = p.id
        LEFT JOIN details_vente dv ON dv.vente_id = v.id
        LEFT JOIN produits pr ON pr.id = dv.produit_id
        LEFT JOIN retours_vente rv ON rv.vente_id = v.id
        $where_clause
        GROUP BY v.id
        ORDER BY $sort_by $sort_order
        LIMIT ? OFFSET ?";

$stmt = mysqli_prepare($conn, $sql);
$params[] = $limit;
$params[] = $offset;
$param_types .= 'ii';

mysqli_stmt_bind_param($stmt, $param_types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$ventes = mysqli_fetch_all($result, MYSQLI_ASSOC);
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
                        <h2 class="text-2xl font-bold text-gray-800 mb-4 md:mb-0">Liste des Ventes</h2>
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
                            <select name="filter_statut_paiement"
                                class="shadow appearance-none border rounded-md py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                <option value="tous" <?php echo $filter_statut_paiement == 'tous' ? 'selected' : ''; ?>>
                                    Tous les Statuts</option>
                                <option value="paye" <?php echo $filter_statut_paiement == 'paye' ? 'selected' : ''; ?>>
                                    Payée</option>
                                <option value="partiellement_paye" <?php echo $filter_statut_paiement == 'partiellement_paye' ? 'selected' : ''; ?>>Partiellement
                                    Payée</option>
                                <option value="impaye" <?php echo $filter_statut_paiement == 'impaye' ? 'selected' : ''; ?>>Impayée</option>
                            </select>
                            <select name="sort_by"
                                class="shadow appearance-none border rounded-md py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                <option value="v.date_vente" <?php echo $sort_by == 'v.date_vente' ? 'selected' : ''; ?>>
                                    Trier par Date</option>
                                <option value="v.montant_total" <?php echo $sort_by == 'v.montant_total' ? 'selected' : ''; ?>>Trier par Montant</option>
                                <option value="c.nom" <?php echo $sort_by == 'c.nom' ? 'selected' : ''; ?>>Trier par
                                    Client</option>
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
                                    <th>ID Vente</th>
                                    <th>Date</th>
                                    <th>Magasin</th>
                                    <th>Client</th>
                                    <th>Personnel</th>
                                    <th>Produits</th>
                                    <th>Montant Total</th>
                                    <th>Payé (Espèces)</th>
                                    <th>Payé (MoMo)</th>
                                    <th>Change</th>
                                    <th>Dû</th>
                                    <th>Statut Paiement</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($ventes)): ?>
                                    <?php foreach ($ventes as $vente): ?>
                                        <tr <?php echo ($vente['nombre_retours'] > 0) ? 'class="bg-yellow-50"' : ''; ?>>
                                            <td>
                                                <?php echo htmlspecialchars($vente['id']); ?>
                                                <?php if ($vente['nombre_retours'] > 0): ?>
                                                    <span class="ml-2 inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800" title="Cette vente a <?php echo $vente['nombre_retours']; ?> retour(s)">
                                                        <i class="fas fa-undo-alt mr-1"></i> <?php echo $vente['nombre_retours']; ?> retour(s)
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($vente['date_vente'])); ?></td>
                                            <td><?php echo htmlspecialchars($vente['nom_magasin']); ?></td>
                                            <td><?php echo htmlspecialchars($vente['client_nom'] . ' ' . $vente['client_prenom']); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($vente['personnel_nom'] . ' ' . $vente['personnel_prenom']); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($vente['produits_vendus'] ?: 'Aucun produit'); ?>
                                            </td>
                                            <td><?php echo number_format($vente['montant_total'], 2, ',', ' ') . ' ' . htmlspecialchars(get_currency()); ?></td>
                                            <td><?php echo number_format($vente['montant_recu_espece'], 2, ',', ' ') . ' ' . htmlspecialchars(get_currency()); ?>
                                            </td>
                                            <td><?php echo number_format($vente['montant_recu_momo'], 2, ',', ' ') . ' ' . htmlspecialchars(get_currency()); ?></td>
                                            <td><?php echo number_format($vente['montant_change'], 2, ',', ' ') . ' ' . htmlspecialchars(get_currency()); ?></td>
                                            <td><?php echo number_format($vente['montant_du'], 2, ',', ' ') . ' ' . htmlspecialchars(get_currency()); ?></td>
                                            <td>
                                                <?php
                                                if ($vente['statut_paiement'] == 'paye') {
                                                    echo '<span class="text-green-600 font-bold">Payée</span>';
                                                } elseif ($vente['statut_paiement'] == 'partiellement_paye') {
                                                    echo '<span class="text-orange-600 font-bold">Partiellement Payée</span>';
                                                } else {
                                                    echo '<span class="text-red-600 font-bold">Impayée</span>';
                                                }
                                                ?>
                                            </td>
                                            <td class="actions">
                                                <button onclick="printSaleReceipt(<?php echo $vente['id']; ?>)"
                                                    class="btn btn-sm bg-green-500 hover:bg-green-600 text-white"><i
                                                        class="fas fa-print mr-1"></i> Imprimer Facture</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="13" class="text-center py-4">Aucune vente trouvée.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Responsive -->
                    <div class="flex flex-col sm:flex-row justify-center items-center gap-2 mt-6">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo htmlspecialchars($search_query); ?>&sort_by=<?php echo htmlspecialchars($sort_by); ?>&sort_order=<?php echo htmlspecialchars($sort_order); ?>&filter_magasin=<?php echo htmlspecialchars($filter_magasin); ?>&filter_statut_paiement=<?php echo htmlspecialchars($filter_statut_paiement); ?>"
                                class="px-3 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition duration-300 text-sm sm:text-base">
                                <i class="fas fa-chevron-left mr-1"></i> Précédent
                            </a>
                        <?php endif; ?>

                        <div class="flex flex-wrap justify-center gap-1 sm:gap-2">
                            <?php
                            // Logique d'affichage intelligente des pages
                            $range = 2; // Nombre de pages à afficher de chaque côté
                            $start = max(1, $page - $range);
                            $end = min($total_pages, $page + $range);

                            // Afficher la première page si on est loin du début
                            if ($start > 1): ?>
                                <a href="?page=1&search=<?php echo htmlspecialchars($search_query); ?>&sort_by=<?php echo htmlspecialchars($sort_by); ?>&sort_order=<?php echo htmlspecialchars($sort_order); ?>&filter_magasin=<?php echo htmlspecialchars($filter_magasin); ?>&filter_statut_paiement=<?php echo htmlspecialchars($filter_statut_paiement); ?>"
                                    class="px-3 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition duration-300 text-sm sm:text-base">1</a>
                                <?php if ($start > 2): ?>
                                    <span class="px-2 py-2 text-gray-500 text-sm sm:text-base">...</span>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($i = $start; $i <= $end; $i++): ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo htmlspecialchars($search_query); ?>&sort_by=<?php echo htmlspecialchars($sort_by); ?>&sort_order=<?php echo htmlspecialchars($sort_order); ?>&filter_magasin=<?php echo htmlspecialchars($filter_magasin); ?>&filter_statut_paiement=<?php echo htmlspecialchars($filter_statut_paiement); ?>"
                                    class="px-3 py-2 <?php echo ($i == $page) ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?> rounded-md transition duration-300 text-sm sm:text-base">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <?php
                            // Afficher la dernière page si on est loin de la fin
                            if ($end < $total_pages): ?>
                                <?php if ($end < $total_pages - 1): ?>
                                    <span class="px-2 py-2 text-gray-500 text-sm sm:text-base">...</span>
                                <?php endif; ?>
                                <a href="?page=<?php echo $total_pages; ?>&search=<?php echo htmlspecialchars($search_query); ?>&sort_by=<?php echo htmlspecialchars($sort_by); ?>&sort_order=<?php echo htmlspecialchars($sort_order); ?>&filter_magasin=<?php echo htmlspecialchars($filter_magasin); ?>&filter_statut_paiement=<?php echo htmlspecialchars($filter_statut_paiement); ?>"
                                    class="px-3 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition duration-300 text-sm sm:text-base">
                                    <?php echo $total_pages; ?>
                                </a>
                            <?php endif; ?>
                        </div>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo htmlspecialchars($search_query); ?>&sort_by=<?php echo htmlspecialchars($sort_by); ?>&sort_order=<?php echo htmlspecialchars($sort_order); ?>&filter_magasin=<?php echo htmlspecialchars($filter_magasin); ?>&filter_statut_paiement=<?php echo htmlspecialchars($filter_statut_paiement); ?>"
                                class="px-3 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition duration-300 text-sm sm:text-base">
                                Suivant <i class="fas fa-chevron-right ml-1"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
        <?php include '../../includes/footer.php'; ?>
    </div>
</div>


<script>
    // --- MODIFIÉ : JavaScript pour l'impression uniquement de la Facture Pro Format ---
    const currency = <?php echo json_encode(get_currency()); ?>;
    function printSaleReceipt(saleId) {
        fetch(`<?php echo BASE_URL; ?>modules/ventes/ajax_get_sale_details_for_receipt.php?sale_id=${saleId}`)
            .then(response => response.json())
            .then(data => {
                if (data.sale && data.items) {
                    let sale = data.sale;
                    let items = data.items;
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

                    const printWindow = window.open('', '', 'height=600,width=400');
                    printWindow.document.write('<html><head><title>FACTURE PRO FORMAT</title>');
                    printWindow.document.write('<style>body { font-family: monospace; font-size: 12px; margin: 0; padding: 5px; }</style>');
                    printWindow.document.write('</head><body>');
                    printWindow.document.write(printContent);
                    printWindow.document.write('</body></html>');
                    printWindow.document.close();
                    printWindow.print();
                } else {
                    alert('Impossible de charger les détails de la vente.');
                }
            })
            .catch(error => {
                console.error('Erreur lors du chargement des détails de vente:', error);
                alert('Erreur lors du chargement des détails de la vente.');
            });
    }

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
</script>
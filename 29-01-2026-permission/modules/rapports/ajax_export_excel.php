<?php
// modules/rapports/ajax_export_excel.php
session_start();

// Inclure les fichiers nécessaires
include '../../db_connect.php';

// Vérifier si l'utilisateur est connecté et a le rôle approprié
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !in_array($_SESSION['role'], ['Administrateur', 'Gérant'])) {
    header("location: ../../public/login.php");
    exit;
}

// --- Récupération des Statistiques (dupliqué de statistiques_rapports.php pour l'export) ---

// Gestion des filtres depuis l'URL
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
$sql_produits_filters = '';
if (!empty($magasin_id_filter)) {
    // $sql_produits_filters .= " AND magasin_id = " . mysqli_real_escape_string($conn, $magasin_id_filter); // Si produits sont liés au magasin
}

$sql_dettes_clients_filters = '';
if (!empty($start_date)) {
    $sql_dettes_clients_filters .= " AND date_creation >= '" . mysqli_real_escape_string($conn, $start_date) . " 00:00:00'";
}
if (!empty($end_date)) {
    $sql_dettes_clients_filters .= " AND date_creation <= '" . mysqli_real_escape_string($conn, $end_date) . " 23:59:59'";
}

$sql_dettes_magasins_filters = '';
if (!empty($start_date)) {
    $sql_dettes_magasins_filters .= " AND date_emission >= '" . mysqli_real_escape_string($conn, $start_date) . "'";
}
if (!empty($end_date)) {
    $sql_dettes_magasins_filters .= " AND date_emission <= '" . mysqli_real_escape_string($conn, $end_date) . "'";
}
if (!empty($magasin_id_filter)) {
    $sql_dettes_magasins_filters .= " AND magasin_id = " . mysqli_real_escape_string($conn, $magasin_id_filter);
}

$sql_epargne_clients_filters = '';


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
$sql_total_produits = "SELECT COUNT(id) AS total_count FROM produits" . $sql_produits_filters;
$result_total_produits = mysqli_query($conn, $sql_total_produits);
if ($result_total_produits && mysqli_num_rows($result_total_produits) > 0) {
    $row = mysqli_fetch_assoc($result_total_produits);
    $total_produits = $row['total_count'] ?? 0;
}

// 3. Nombre de produits en alerte de stock
$produits_alerte_stock = 0;
$sql_alerte_stock = "SELECT COUNT(id) AS alert_count FROM produits WHERE quantite_stock <= seuil_alerte_stock" . $sql_produits_filters;
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
$sql_dettes_magasins = "SELECT SUM(montant_restant) AS total_debt FROM dettes_magasins WHERE (statut = 'en_cours' OR statut = 'partiellement_payee')" . $sql_dettes_magasins_filters;
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

// Fermer la connexion à la base de données
mysqli_close($conn);

// --- Génération du fichier CSV ---

$filename = "rapport_statistiques_" . date('Ymd_His') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// Entêtes CSV
fputcsv($output, ['Statistique', 'Valeur']);

// Données
fputcsv($output, ['Date du Rapport', date('d/m/Y H:i')]);
fputcsv($output, ['Generé par', $_SESSION['username'] . ' (' . $_SESSION['role'] . ')']);
if (!empty($start_date)) {
    fputcsv($output, ['Période de début', date('d/m/Y', strtotime($start_date))]);
}
if (!empty($end_date)) {
    fputcsv($output, ['Période de fin', date('d/m/Y', strtotime($end_date))]);
}
if (!empty($magasin_id_filter)) {
    // Pour afficher le nom du magasin dans l'export, il faudrait le récupérer avant de fermer la connexion
    // Ici, nous n'avons que l'ID, donc nous affichons l'ID.
    fputcsv($output, ['ID Magasin filtré', $magasin_id_filter]);
}
fputcsv($output, ['']); // Ligne vide pour la clarté

fputcsv($output, ['Bénéfices Total des Ventes', number_format($total_benefices, 2, ',', '') . ' XOF']);
fputcsv($output, ['Total des Ventes', number_format($total_ventes, 2, ',', '') . ' XOF']); // Nouvelle statistique
fputcsv($output, ['Total des Produits', number_format($total_produits, 0, ',', '')]);
fputcsv($output, ['Produits en Alerte de Stock', number_format($produits_alerte_stock, 0, ',', '')]);
fputcsv($output, ['Total Dettes Clients', number_format($total_dettes_clients, 2, ',', '') . ' XOF']);
fputcsv($output, ['Total Dettes Magasins', number_format($total_dettes_magasins, 2, ',', '') . ' XOF']);
fputcsv($output, ['Total Épargne Clients', number_format($total_epargne_clients, 2, ',', '') . ' XOF']);

fclose($output);
exit;
?>
<?php
// modules/ventes/ajax_get_sale_details_for_receipt.php
session_start();
include '../../db_connect.php';

header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté et a le rôle approprié
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['error' => 'Non autorisé', 'sale' => null, 'items' => []]);
    exit;
}

$sale_id = isset($_GET['sale_id']) ? (int) $_GET['sale_id'] : 0;
$sale_details = ['sale' => null, 'items' => []];

if ($sale_id > 0) {
    // Récupérer les détails de la vente
    $sql_sale = "SELECT v.*, c.nom AS client_nom, c.prenom AS client_prenom, c.telephone AS client_telephone,
                        m.nom AS nom_magasin, p.nom AS personnel_nom, p.prenom AS personnel_prenom
                 FROM ventes v
                 LEFT JOIN clients c ON v.client_id = c.id
                 LEFT JOIN magasins m ON v.magasin_id = m.id
                 LEFT JOIN personnel p ON v.personnel_id = p.id
                 WHERE v.id = ?";
    if ($stmt_sale = mysqli_prepare($conn, $sql_sale)) {
        mysqli_stmt_bind_param($stmt_sale, "i", $sale_id);
        mysqli_stmt_execute($stmt_sale);
        $result_sale = mysqli_stmt_get_result($stmt_sale);
        $sale_data = mysqli_fetch_assoc($result_sale);
        if ($sale_data) {
            $sale_details['sale'] = $sale_data;
        }
        mysqli_stmt_close($stmt_sale);
    }

    // Récupérer les détails des articles de la vente
    if ($sale_details['sale']) {
        $sql_items = "SELECT dv.*, prod.nom AS produit_nom
                      FROM details_vente dv
                      JOIN produits prod ON dv.produit_id = prod.id
                      WHERE dv.vente_id = ?";
        if ($stmt_items = mysqli_prepare($conn, $sql_items)) {
            mysqli_stmt_bind_param($stmt_items, "i", $sale_id);
            mysqli_stmt_execute($stmt_items);
            $result_items = mysqli_stmt_get_result($stmt_items);
            while ($row = mysqli_fetch_assoc($result_items)) {
                $sale_details['items'][] = $row;
            }
            mysqli_stmt_close($stmt_items);
        }
    }
} else {
    $sale_details['error'] = 'ID de vente invalide';
}

echo json_encode($sale_details);
mysqli_close($conn);
?>
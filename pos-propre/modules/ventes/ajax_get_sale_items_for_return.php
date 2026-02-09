<?php
// modules/ventes/ajax_get_sale_items_for_return.php
session_start();
include '../../db_connect.php';

header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté et a le rôle approprié
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !in_array($_SESSION['role'], ['Administrateur', 'Caissier', 'Vendeur'])) {
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

$sale_id = isset($_GET['sale_id']) ? (int)$_GET['sale_id'] : 0;
$response_data = ['sale' => null, 'items' => []];

if ($sale_id > 0) {
    // Récupérer les détails de la vente
    $sql_sale = "SELECT v.id, v.date_vente, v.montant_total, v.magasin_id, m.nom AS nom_magasin,
                        c.nom AS client_nom, c.prenom AS client_prenom, c.telephone AS client_telephone
                 FROM ventes v
                 JOIN magasins m ON v.magasin_id = m.id
                 LEFT JOIN clients c ON v.client_id = c.id
                 WHERE v.id = ?";
    if ($stmt_sale = mysqli_prepare($conn, $sql_sale)) {
        mysqli_stmt_bind_param($stmt_sale, "i", $sale_id);
        mysqli_stmt_execute($stmt_sale);
        $result_sale = mysqli_stmt_get_result($stmt_sale);
        $response_data['sale'] = mysqli_fetch_assoc($result_sale);
        mysqli_stmt_close($stmt_sale);
    }

    if ($response_data['sale']) {
        // Récupérer les articles de la vente et les quantités déjà retournées
        $sql_items = "SELECT dv.produit_id, p.nom AS produit_nom, dv.quantite, dv.prix_vente_unitaire,
                             dv.reduction_ligne_montant, dv.reduction_ligne_pourcentage,
                             COALESCE(SUM(rv.quantite_retournee), 0) AS total_returned_qty
                      FROM details_vente dv
                      JOIN produits p ON dv.produit_id = p.id
                      LEFT JOIN retours_vente rv ON dv.vente_id = rv.vente_id AND dv.produit_id = rv.produit_id
                      WHERE dv.vente_id = ?
                      GROUP BY dv.produit_id, dv.quantite, dv.prix_vente_unitaire, dv.reduction_ligne_montant, dv.reduction_ligne_pourcentage, p.nom";
        if ($stmt_items = mysqli_prepare($conn, $sql_items)) {
            mysqli_stmt_bind_param($stmt_items, "i", $sale_id);
            mysqli_stmt_execute($stmt_items);
            $result_items = mysqli_stmt_get_result($stmt_items);
            while ($row = mysqli_fetch_assoc($result_items)) {
                $response_data['items'][] = $row;
            }
            mysqli_stmt_close($stmt_items);
        }
    }
}

echo json_encode($response_data);
mysqli_close($conn);
?>

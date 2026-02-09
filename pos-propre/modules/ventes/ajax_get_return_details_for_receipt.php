<?php
// modules/ventes/ajax_get_return_details_for_receipt.php
session_start();
include '../../db_connect.php';

header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté et a le rôle approprié
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

$return_id = isset($_GET['return_id']) ? (int)$_GET['return_id'] : 0;
$response_data = ['return' => null, 'sale' => null, 'product' => null, 'personnel' => null, 'magasin' => null, 'client' => null];

if ($return_id > 0) {
    $sql_return = "SELECT rv.*,
                          p.nom AS product_name,
                          pers.nom AS personnel_nom, pers.prenom AS personnel_prenom,
                          m.nom AS magasin_name,
                          v.client_id
                   FROM retours_vente rv
                   JOIN produits p ON rv.produit_id = p.id
                   JOIN personnel pers ON rv.personnel_id = pers.id
                   JOIN magasins m ON rv.magasin_id = m.id
                   JOIN ventes v ON rv.vente_id = v.id
                   WHERE rv.id = ?";
    if ($stmt_return = mysqli_prepare($conn, $sql_return)) {
        mysqli_stmt_bind_param($stmt_return, "i", $return_id);
        mysqli_stmt_execute($stmt_return);
        $result_return = mysqli_stmt_get_result($stmt_return);
        $return_data = mysqli_fetch_assoc($result_return);
        mysqli_stmt_close($stmt_return);

        if ($return_data) {
            $response_data['return'] = $return_data;
            $response_data['product'] = ['nom' => $return_data['product_name']];
            $response_data['personnel'] = ['nom' => $return_data['personnel_nom'], 'prenom' => $return_data['personnel_prenom']];
            $response_data['magasin'] = ['nom' => $return_data['magasin_name']];

            // Récupérer les détails de la vente originale
            $sql_original_sale = "SELECT * FROM ventes WHERE id = ?";
            if ($stmt_original_sale = mysqli_prepare($conn, $sql_original_sale)) {
                mysqli_stmt_bind_param($stmt_original_sale, "i", $return_data['vente_id']);
                mysqli_stmt_execute($stmt_original_sale);
                $result_original_sale = mysqli_stmt_get_result($stmt_original_sale);
                $response_data['sale'] = mysqli_fetch_assoc($result_original_sale);
                mysqli_stmt_close($stmt_original_sale);
            }

            // Récupérer les détails du client si existant
            if ($return_data['client_id']) {
                $sql_client = "SELECT nom, prenom, telephone FROM clients WHERE id = ?";
                if ($stmt_client = mysqli_prepare($conn, $sql_client)) {
                    mysqli_stmt_bind_param($stmt_client, "i", $return_data['client_id']);
                    mysqli_stmt_execute($stmt_client);
                    $result_client = mysqli_stmt_get_result($stmt_client);
                    $response_data['client'] = mysqli_fetch_assoc($result_client);
                    mysqli_stmt_close($stmt_client);
                }
            }
        }
    }
}

echo json_encode($response_data);
mysqli_close($conn);
?>

<?php
// modules/produits/ajax_get_product_store.php
header('Content-Type: application/json');
include '../../db_connect.php';

if (isset($_GET['product_id'])) {
    $product_id = intval($_GET['product_id']);

    $sql = "SELECT magasin_id FROM produits WHERE id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $product_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $magasin_id);

        if (mysqli_stmt_fetch($stmt)) {
            echo json_encode(['success' => true, 'magasin_id' => $magasin_id]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Produit introuvable']);
        }
        mysqli_stmt_close($stmt);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur de base de données']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'ID produit manquant']);
}
mysqli_close($conn);
?>
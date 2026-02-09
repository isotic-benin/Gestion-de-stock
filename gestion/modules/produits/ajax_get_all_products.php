<?php
// modules/produits/ajax_get_all_products.php
// Endpoint AJAX pour récupérer tous les produits pour l'impression du stock

session_start();
header('Content-Type: application/json; charset=utf-8');

// Inclure la connexion à la base de données
include '../../db_connect.php';

// Vérifier si l'utilisateur est connecté et a le rôle approprié
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !in_array($_SESSION['role'], ['Administrateur', 'Gérant'])) {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit;
}

// Récupérer tous les produits avec leurs informations complètes
$sql = "SELECT p.id, p.nom, p.description, p.prix_achat, p.prix_vente, p.quantite_stock, p.seuil_alerte_stock,
               c.nom AS nom_categorie, f.nom AS nom_fournisseur, m.nom AS nom_magasin
        FROM produits p
        LEFT JOIN categories c ON p.categorie_id = c.id
        LEFT JOIN fournisseurs f ON p.fournisseur_id = f.id
        LEFT JOIN magasins m ON p.magasin_id = m.id
        ORDER BY p.nom ASC";

$result = mysqli_query($conn, $sql);

if ($result) {
    $produits = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $produits[] = $row;
    }
    mysqli_free_result($result);

    echo json_encode([
        'success' => true,
        'produits' => $produits,
        'total' => count($produits)
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la récupération des produits: ' . mysqli_error($conn)
    ], JSON_UNESCAPED_UNICODE);
}

mysqli_close($conn);
?>
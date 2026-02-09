<?php
// modules/ventes/ajax_get_sales_list.php
session_start();
include '../../db_connect.php';

header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté et a le rôle approprié
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !in_array($_SESSION['role'], ['Administrateur', 'Caissier', 'Vendeur'])) {
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

$ventes = [];

// Récupérer la liste des ventes récentes (les 100 dernières pour performance)
$sql = "SELECT v.id, v.date_vente, v.montant_total, 
               m.nom AS nom_magasin,
               c.nom AS client_nom, c.prenom AS client_prenom
        FROM ventes v
        JOIN magasins m ON v.magasin_id = m.id
        LEFT JOIN clients c ON v.client_id = c.id
        ORDER BY v.date_vente DESC, v.id DESC
        LIMIT 100";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $ventes[] = $row;
    }
    mysqli_stmt_close($stmt);
}

echo json_encode($ventes);
mysqli_close($conn);
?>


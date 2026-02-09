<?php
// modules/clients/ajax_get_transactions.php
session_start();
include '../../db_connect.php';

header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté et a le rôle approprié
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !in_array($_SESSION['role'], ['Administrateur', 'Caissier', 'Vendeur'])) {
    echo json_encode([]); // Retourne un tableau vide si non autorisé
    exit;
}

$compte_id = isset($_GET['compte_id']) ? (int)$_GET['compte_id'] : 0;

$transactions = [];

if ($compte_id > 0) {
    $sql = "SELECT id, type_transaction, montant, description, date_transaction FROM transactions_epargne WHERE compte_epargne_id = ? ORDER BY date_transaction DESC";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $compte_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $transactions[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
}

echo json_encode($transactions);

mysqli_close($conn);
?>

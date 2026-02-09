<?php
// modules/finance/ajax_get_dette_payments.php
session_start();
include '../../db_connect.php';

header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté et a le rôle approprié
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'Administrateur') {
    echo json_encode([]);
    exit;
}

$dette_id = isset($_GET['dette_id']) ? (int)$_GET['dette_id'] : 0;
$payments = [];

if ($dette_id > 0) {
    $sql = "SELECT pdm.id, pdm.montant_paye, pdm.date_paiement, pdm.description,
                   p.nom AS personnel_nom, p.prenom AS personnel_prenom
            FROM paiements_dettes_magasins pdm
            JOIN personnel p ON pdm.personnel_id = p.id
            WHERE pdm.dette_magasin_id = ? ORDER BY pdm.date_paiement DESC";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $dette_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $payments[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
}

echo json_encode($payments);
mysqli_close($conn);
?>

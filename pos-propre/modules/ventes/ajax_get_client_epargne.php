<?php
// modules/ventes/ajax_get_client_epargne.php
// Ce fichier récupère le solde d'épargne d'un client pour le point de vente.

session_start();
include '../../db_connect.php';

header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté et a le rôle approprié
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !in_array($_SESSION['role'], ['Administrateur', 'Caissier', 'Vendeur'])) {
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

$client_id = isset($_GET['client_id']) ? (int) sanitize_input($_GET['client_id']) : 0;

if ($client_id <= 0) {
    echo json_encode(['error' => 'ID client invalide']);
    exit;
}

// Récupérer le compte épargne du client
$sql = "SELECT ce.id, ce.solde, ce.numero_compte 
        FROM comptes_epargne ce 
        WHERE ce.client_id = ? 
        LIMIT 1";

$result = ['has_account' => false, 'solde' => 0, 'compte_id' => null, 'numero_compte' => null];

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $client_id);
    mysqli_stmt_execute($stmt);
    $query_result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($query_result)) {
        $result = [
            'has_account' => true,
            'solde' => (float) $row['solde'],
            'compte_id' => (int) $row['id'],
            'numero_compte' => $row['numero_compte']
        ];
    }
    mysqli_stmt_close($stmt);
}

echo json_encode($result);
mysqli_close($conn);
?>


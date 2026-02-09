<?php
// modules/ventes/ajax_search_products.php
// Ce fichier gère la recherche de produits pour le point de vente.

session_start();
include '../../db_connect.php'; // Assurez-vous que ce chemin est correct

header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté et a le rôle approprié
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !in_array($_SESSION['role'], ['Administrateur', 'Caissier', 'Vendeur'])) {
    echo json_encode([]);
    exit;
}

$query = isset($_GET['query']) ? sanitize_input($_GET['query']) : '';
$magasin_id = isset($_GET['magasin_id']) ? sanitize_input($_GET['magasin_id']) : null; // Récupérer l'ID du magasin

$products = [];

// Vérifier si un terme de recherche valide et un ID de magasin sont fournis
if (!empty($query) && strlen($query) >= 2 && $magasin_id !== null) {
    // Fonction pour normaliser une chaîne (supprimer les accents et caractères spéciaux, mettre en minuscules)
    // Cette fonction est cruciale pour la recherche insensible aux accents et caractères spéciaux.
    function normalizeStringForSearch($str) {
        // Convertir en ASCII (enlève les accents)
        // Note: iconv peut nécessiter l'extension php_iconv activée sur votre serveur
        $str = iconv('UTF-8', 'ASCII//TRANSLIT', $str);
        // Convertir en minuscules
        $str = strtolower($str);
        // Supprimer les caractères non alphanumériques (sauf les espaces)
        $str = preg_replace('/[^a-z0-9\s]/', '', $str);
        // Remplacer les espaces multiples par un seul espace
        $str = preg_replace('/\s+/', ' ', $str);
        return trim($str);
    }

    // Normaliser la requête de l'utilisateur pour la comparaison
    $normalized_query_param = '%' . normalizeStringForSearch($query) . '%';

    // Requête SQL pour rechercher les produits.
    // 1. Filtrer par `magasin_id` pour n'afficher que les produits du magasin sélectionné.
    // 2. S'assurer que `quantite_stock` est supérieure à 0.
    // 3. Utiliser les fonctions REPLACE et LOWER pour rendre la recherche sur le 'nom' insensible
    //    aux accents et à la casse.
    // 4. Sélectionner `prix_achat` en plus des autres champs.
    $sql = "SELECT id, nom, quantite_stock, prix_vente, prix_achat
            FROM produits
            WHERE magasin_id = ?
              AND quantite_stock > 0
              AND (LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(nom, 'é', 'e'), 'è', 'e'), 'ê', 'e'), 'ë', 'e'), 'à', 'a'), 'â', 'a'), 'ä', 'a'), 'ù', 'u'), 'û', 'u'), 'ü', 'u'), 'ô', 'o'), 'ö', 'o'), 'î', 'i'), 'ï', 'i')) LIKE ?)
            LIMIT 10"; // Limite les résultats à 10 pour la performance

    if ($stmt = mysqli_prepare($conn, $sql)) {
        // Lier les paramètres : 'is' pour integer (magasin_id) et string (normalized_query_param)
        mysqli_stmt_bind_param($stmt, "is", $magasin_id, $normalized_query_param);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $products[] = $row;
        }
        mysqli_stmt_close($stmt);
    } else {
        // En cas d'erreur de préparation de la requête, loguer l'erreur (pour le débogage)
        error_log("Erreur de préparation de la requête de recherche de produits: " . mysqli_error($conn));
    }
}

echo json_encode($products);
mysqli_close($conn);
?>

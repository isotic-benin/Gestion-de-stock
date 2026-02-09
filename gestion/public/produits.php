<?php
// public/produits.php

// 1. Démarre la session en premier, avant toute autre sortie ou inclusion de fichiers HTML.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. Inclut db_connect.php en premier. Ce fichier inclut à son tour config.php,
//    ce qui garantit que BASE_URL et les détails de connexion à la BDD sont définis
//    avant que header.php ne soit inclus et ne génère du HTML.
include '../db_connect.php'; // Connexion à la base de données (inclut config.php)

// 3. Inclut le header.php une fois que les constantes et la session sont gérées.
include '../includes/header.php'; // Inclut le header avec le début du HTML

// --- Logique de listage (Pagination et Recherche) ---

$limit = 12; // Nombre de produits par page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$search_query = isset($_GET['search']) ? htmlspecialchars(trim($_GET['search'])) : '';

// Construction de la clause WHERE pour la recherche
$where_clause = '';
$params = [];
$param_types = '';

if (!empty($search_query)) {
    $where_clause .= " WHERE (nom LIKE ? OR description LIKE ?)";
    $search_param = '%' . $search_query . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'ss';
}

// Requête pour le nombre total de produits (pour la pagination)
$count_sql = "SELECT COUNT(id) AS total FROM produits" . $where_clause;
$stmt_count = mysqli_prepare($conn, $count_sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt_count, $param_types, ...$params);
}
mysqli_stmt_execute($stmt_count);
$count_result = mysqli_stmt_get_result($stmt_count);
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $limit);
mysqli_stmt_close($stmt_count);

// Requête pour récupérer les produits avec pagination et recherche
$sql = "SELECT id, nom, description, image_url, prix_vente
        FROM produits"
        . $where_clause . " ORDER BY nom ASC LIMIT ? OFFSET ?";

$stmt = mysqli_prepare($conn, $sql);
// Ajouter les paramètres de LIMIT et OFFSET aux paramètres existants
$params[] = $limit;
$params[] = $offset;
$param_types .= 'ii';

mysqli_stmt_bind_param($stmt, $param_types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$produits = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

mysqli_close($conn); // Fermer la connexion à la base de données
?>

<div class="container mx-auto p-6">
    <h1 class="text-4xl font-bold text-gray-800 mb-8 text-center">Nos Produits Disponibles</h1>

    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <form method="GET" action="" class="flex flex-col md:flex-row gap-4 w-full md:w-auto items-center">
            <input type="text" name="search" placeholder="Rechercher un produit par nom ou description..." value="<?php echo htmlspecialchars($search_query); ?>" class="flex-grow shadow appearance-none border rounded-md py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md shadow-md transition duration-300">
                <i class="fas fa-search mr-2"></i> Rechercher
            </button>
        </form>
    </div>

    <?php if (!empty($produits)) : ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            <?php foreach ($produits as $produit) : ?>
                <div class="bg-white rounded-lg shadow-md overflow-hidden transform hover:scale-105 transition duration-300 flex flex-col">
                    <?php if (!empty($produit['image_url'])) : ?>
                        <img src="<?php echo BASE_URL . htmlspecialchars($produit['image_url']); ?>" alt="<?php echo htmlspecialchars($produit['nom']); ?>" class="w-full h-48 object-cover">
                    <?php else : ?>
                        <div class="w-full h-48 bg-gray-200 flex items-center justify-center text-gray-400">
                            <i class="fas fa-image text-5xl"></i>
                        </div>
                    <?php endif; ?>
                    <div class="p-4 flex flex-col flex-grow">
                        <h2 class="text-xl font-semibold text-gray-800 mb-2 truncate"><?php echo htmlspecialchars($produit['nom']); ?></h2>
                        <p class="text-gray-600 text-sm mb-3 line-clamp-3"><?php echo htmlspecialchars($produit['description']); ?></p>
                        <div class="mt-auto flex justify-between items-center pt-2 border-t border-gray-200">
                            <span class="text-blue-700 font-bold text-lg"><?php echo number_format($produit['prix_vente'], 2, ',', ' '); ?> XOF</span>
                            </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="pagination flex justify-center items-center space-x-2 mt-8">
            <?php if ($page > 1) : ?>
                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search_query); ?>" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-md transition duration-300">Précédent</a>
            <?php else : ?>
                <span class="bg-gray-300 text-gray-700 font-bold py-2 px-4 rounded-md cursor-not-allowed">Précédent</span>
            <?php endif; ?>

            <span class="text-gray-700 px-2">Page <?php echo $page; ?> sur <?php echo $total_pages; ?></span>

            <?php if ($page < $total_pages) : ?>
                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search_query); ?>" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-md transition duration-300">Suivant</a>
            <?php else : ?>
                <span class="bg-gray-300 text-gray-700 font-bold py-2 px-4 rounded-md cursor-not-allowed">Suivant</span>
            <?php endif; ?>
        </div>

    <?php else : ?>
        <div class="bg-white p-6 rounded-lg shadow-md text-center">
            <p class="text-gray-700 text-lg">Aucun produit trouvé pour votre recherche.</p>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
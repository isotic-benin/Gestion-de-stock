<?php
// includes/topbar.php
// Barre supérieure du dashboard avec le nom de l'administrateur connecté

// S'assurer que BASE_URL est défini
if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}

$current_username = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Utilisateur';
$current_role = isset($_SESSION['role']) ? htmlspecialchars($_SESSION['role']) : '';
$current_page_title = isset($page_title) ? $page_title : 'Tableau de Bord';
?>

<!-- Top Bar Desktop -->
<header
    class="hidden md:flex bg-white mx-4 mt-3 rounded-xl shadow-md px-6 py-3 justify-between items-center border border-gray-100">
    <div class="flex items-center">
        <h1 class="text-lg font-semibold text-gray-800">
            <?php echo $current_page_title; ?>
        </h1>
    </div>
    <div class="flex items-center space-x-4">
        <div class="flex items-center space-x-2">
            <div
                class="w-8 h-8 bg-gradient-to-br from-gray-500 to-gray-700 rounded-full flex items-center justify-center shadow-sm">
                <i class="fas fa-user text-white text-sm"></i>
            </div>
            <div class="text-right">
                <p class="text-sm font-medium text-gray-800">
                    <?php echo $current_username; ?>
                </p>
                <p class="text-xs text-gray-500">
                    <?php echo $current_role; ?>
                </p>
            </div>
        </div>
        <a href="<?php echo BASE_URL; ?>/logout.php" class="text-gray-400 hover:text-red-500 transition duration-300"
            title="Déconnexion">
            <i class="fas fa-sign-out-alt text-lg"></i>
        </a>
    </div>
</header>

<!-- Top Bar Mobile -->
<header
    class="md:hidden bg-white mx-3 mt-3 rounded-xl shadow-md p-3 flex justify-between items-center border border-gray-100">
    <button id="sidebar-toggle-open" class="text-gray-700 focus:outline-none">
        <i class="fas fa-bars text-xl"></i>
    </button>
    <h1 class="text-base font-semibold text-gray-800">
        <?php echo $current_page_title; ?>
    </h1>
    <div class="flex items-center space-x-3">
        <span class="text-xs text-gray-600 font-medium hidden sm:inline">
            <?php echo $current_username; ?>
        </span>
        <a href="<?php echo BASE_URL; ?>logout.php" class="text-gray-400 hover:text-red-500 transition duration-300">
            <i class="fas fa-sign-out-alt text-lg"></i>
        </a>
    </div>
</header>
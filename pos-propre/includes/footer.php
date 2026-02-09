<?php
// includes/footer.php
// Pied de page du dashboard avec positionnement sticky en bas

// S'assurer que BASE_URL est défini
if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}

$current_year = date('Y');
$company_name = isset($company_info['nom']) && $company_info['nom'] !== '' ? $company_info['nom'] : 'HGB Multi';
?>

<!-- Footer Desktop (sticky at bottom) -->
<footer
    class="hidden md:flex bg-white mx-4 mb-3 rounded-xl shadow-md px-6 py-3 justify-between items-center border border-gray-100 sticky bottom-0 z-10">
    <div class="flex items-center space-x-4">
        <div class="flex items-center space-x-2">
            <div
                class="w-8 h-8 bg-gradient-to-br from-gray-500 to-gray-700 rounded-full flex items-center justify-center shadow-sm">
                <i class="fas fa-store text-white text-sm"></i>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($company_name); ?></p>
                <p class="text-xs text-gray-500">Gestion de Stock</p>
            </div>
        </div>
    </div>
    <div class="text-center">
        <p class="text-sm text-gray-600">
            &copy;
            <?php echo $current_year; ?> <?php echo htmlspecialchars($company_name); ?>. Tous droits réservés.
        </p>
    </div>
    <div class="flex items-center space-x-4">
        <a href="#" class="text-gray-400 hover:text-gray-600 transition duration-300" title="Support">
            <i class="fas fa-life-ring text-lg"></i>
        </a>
        <a href="<?php echo BASE_URL; ?>documentation.php" class="text-gray-400 hover:text-gray-600 transition duration-300" title="Documentation">
            <i class="fas fa-book text-lg"></i>
        </a>
    </div>
</footer>

<!-- Footer Mobile (sticky at bottom) -->
<footer
    class="md:hidden bg-white mx-3 mb-3 rounded-xl shadow-md p-3 flex flex-col items-center border border-gray-100 sticky bottom-0 z-10">
    <div class="flex items-center space-x-2 mb-2">
        <div
            class="w-6 h-6 bg-gradient-to-br from-gray-500 to-gray-700 rounded-full flex items-center justify-center shadow-sm">
            <i class="fas fa-store text-white text-xs"></i>
        </div>
        <p class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($company_name); ?></p>
    </div>
    <p class="text-xs text-gray-500 text-center">
        &copy;
        <?php echo $current_year; ?> <?php echo htmlspecialchars($company_name); ?>. Tous droits réservés.
    </p>
</footer>
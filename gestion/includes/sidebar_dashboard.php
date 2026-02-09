<?php
// includes/sidebar_dashboard.php
// La session est censée être démarrée par config.php (via db_connect.php)

// Vérifions si BASE_URL est défini (mesure de sécurité, ne devrait pas être nécessaire avec la bonne configuration)
if (!defined('BASE_URL')) {
    // Fallback si config.php n'a pas été chargé. Cela indique un problème de configuration.
    // Pour éviter une erreur fatale, nous définissons une URL de base relative par défaut.
    define('BASE_URL', '/');
}

// Inclure le helper de permissions s'il n'est pas déjà inclus
if (!function_exists('canAccessModule')) {
    include_once dirname(__FILE__) . '/permissions_helper.php';
}

$current_page = basename($_SERVER['PHP_SELF']);
// Pour les modules, nous voulons le nom du dossier parent (ex: 'magasins', 'ventes')
$current_module_dir = basename(dirname($_SERVER['PHP_SELF']));

// Fonction pour vérifier si un lien est actif
// Prend le chemin COMPLET du lien (incluant BASE_URL)
function isActive($full_link, $current_page_filename, $link_module_dir, $current_module_dir)
{
    // Extrait le nom du fichier du lien pour la comparaison
    $link_filename = basename(parse_url($full_link, PHP_URL_PATH));

    // Vérifie si le nom du fichier actuel correspond au nom du fichier du lien
    if ($link_filename === $current_page_filename) {
        return true;
    }
    // Vérifie si le répertoire du module actuel correspond au répertoire du module du lien
    // Ceci est utile pour les menus parents qui doivent rester actifs lorsque l'un de leurs sous-menus est actif.
    if (!empty($link_module_dir) && $link_module_dir === $current_module_dir) {
        return true;
    }
    return false;
}

// Définir les liens du menu avec leurs modules et rôles requis
// Ajout du champ 'module_code' pour le système de permissions
$menu_items = [
    [
        'title' => 'Tableau de Bord',
        'icon' => 'fas fa-tachometer-alt',
        'link' => BASE_URL . 'dashboard.php',
        'roles' => ['Administrateur', 'Caissier', 'Vendeur', 'Gérant'],
        'module_dir' => '', // Pas un vrai module, le tableau de bord est à la racine
        'module_code' => 'dashboard'
    ],
    [
        'title' => 'Clients',
        'icon' => 'fas fa-handshake',
        'roles' => ['Administrateur', 'Caissier', 'Vendeur', 'Gérant'],
        'module_dir' => 'clients',
        'module_code' => 'clients',
        'sub_menus' => [
            ['title' => 'Clients', 'icon' => 'fas fa-user-friends', 'link' => BASE_URL . 'modules/clients/gerer_clients.php', 'roles' => ['Administrateur', 'Caissier', 'Vendeur', 'Gérant'], 'module_code' => 'clients'],
            ['title' => 'Comptes Épargne', 'icon' => 'fas fa-piggy-bank', 'link' => BASE_URL . 'modules/clients/gerer_comptes_epargne.php', 'roles' => ['Administrateur', 'Caissier', 'Gérant', 'Vendeur'], 'module_code' => 'comptes_epargne'],
            ['title' => 'Dettes Clients', 'icon' => 'fas fa-money-bill-wave', 'link' => BASE_URL . 'modules/clients/gerer_dettes_clients.php', 'roles' => ['Administrateur', 'Caissier', 'Gérant', 'Vendeur'], 'module_code' => 'dettes_clients'],
        ]
    ],
    [
        'title' => 'Produits & Stock',
        'icon' => 'fas fa-boxes',
        'roles' => ['Administrateur', 'Gérant', 'Caissier', 'Vendeur'],
        'module_dir' => 'produits',
        'module_code' => 'produits',
        'sub_menus' => [
            ['title' => 'Produits & Stock', 'icon' => 'fas fa-box-open', 'link' => BASE_URL . 'modules/produits/gerer_produits_stock.php', 'roles' => ['Administrateur', 'Gérant', 'Caissier', 'Vendeur'], 'module_code' => 'produits'],
            ['title' => 'Transferts de Stock', 'icon' => 'fas fa-truck-ramp-box', 'link' => BASE_URL . 'modules/produits/gerer_transferts_stock.php', 'roles' => ['Administrateur', 'Gérant', 'Caissier', 'Vendeur'], 'module_code' => 'transferts_stock'],
        ]
    ],
    [
        'title' => 'Ventes',
        'icon' => 'fas fa-cash-register',
        'roles' => ['Administrateur', 'Vendeur', 'Caissier', 'Gérant'],
        'module_dir' => 'ventes',
        'module_code' => 'ventes',
        'sub_menus' => [
            ['title' => 'Point de Vente (PdV)', 'icon' => 'fas fa-calculator', 'link' => BASE_URL . 'modules/ventes/point_de_vente.php', 'roles' => ['Administrateur', 'Vendeur', 'Caissier', 'Gérant'], 'module_code' => 'ventes'],
            ['title' => 'Historique des Ventes', 'icon' => 'fas fa-history', 'link' => BASE_URL . 'modules/ventes/historique_ventes.php', 'roles' => ['Administrateur', 'Vendeur', 'Caissier', 'Gérant'], 'module_code' => 'ventes'],
            ['title' => 'Retours de Vente', 'icon' => 'fas fa-undo', 'link' => BASE_URL . 'modules/ventes/retours_ventes.php', 'roles' => ['Administrateur', 'Vendeur', 'Caissier', 'Gérant'], 'module_code' => 'retours_ventes'],
        ]
    ],
    [
        'title' => 'Finances',
        'icon' => 'fas fa-wallet',
        'roles' => ['Administrateur', 'Caissier', 'Gérant', 'Vendeur'],
        'module_dir' => 'finance',
        'module_code' => 'depenses',
        'sub_menus' => [
            ['title' => 'Dépenses', 'icon' => 'fas fa-money-bill-wave', 'link' => BASE_URL . 'modules/finance/gerer_depenses.php', 'roles' => ['Administrateur', 'Caissier', 'Gérant', 'Vendeur'], 'module_code' => 'depenses'],
            ['title' => 'Dettes Magasins', 'icon' => 'fas fa-file-invoice-dollar', 'link' => BASE_URL . 'modules/finance/gerer_dettes_entreprise.php', 'roles' => ['Administrateur', 'Gérant', 'Caissier', 'Vendeur'], 'module_code' => 'dettes_entreprise'],
        ]
    ],
    [
        'title' => 'Fournisseurs',
        'icon' => 'fas fa-truck',
        'roles' => ['Administrateur', 'Gérant', 'Caissier', 'Vendeur'],
        'module_dir' => 'fournisseurs',
        'module_code' => 'fournisseurs',
        'sub_menus' => [
            ['title' => 'Fournisseurs', 'icon' => 'fas fa-dolly', 'link' => BASE_URL . 'modules/fournisseurs/gerer_fournisseurs.php', 'roles' => ['Administrateur', 'Gérant', 'Caissier', 'Vendeur'], 'module_code' => 'fournisseurs'],
        ]
    ],
    [
        'title' => 'Paramètres',
        'icon' => 'fas fa-cog',
        'roles' => ['Administrateur', 'Gérant', 'Caissier', 'Vendeur'],
        'module_dir' => 'parametres',
        'module_code' => 'parametres',
        'sub_menus' => [
            ['title' => 'Configuration', 'icon' => 'fas fa-building', 'link' => BASE_URL . 'modules/parametres/configuration_entreprise.php', 'roles' => ['Administrateur', 'Gérant', 'Caissier', 'Vendeur'], 'module_code' => 'parametres'],
            ['title' => 'Permissions', 'icon' => 'fas fa-user-shield', 'link' => BASE_URL . 'modules/parametres/gerer_permissions.php', 'roles' => ['Administrateur', 'Gérant', 'Caissier', 'Vendeur'], 'module_code' => 'parametres'],
            ['title' => 'Magasins', 'icon' => 'fas fa-store-alt', 'link' => BASE_URL . 'modules/magasins/gerer_magasins.php', 'roles' => ['Administrateur', 'Gérant', 'Caissier', 'Vendeur'], 'module_code' => 'magasins'],
            ['title' => 'Personnels', 'icon' => 'fas fa-users', 'link' => BASE_URL . 'modules/magasins/gerer_personnel.php', 'roles' => ['Administrateur', 'Gérant', 'Caissier', 'Vendeur'], 'module_code' => 'personnel'],
        ]
    ],
    [
        'title' => 'Statistiques',
        'icon' => 'fas fa-chart-pie',
        'roles' => ['Administrateur', 'Gérant', 'Caissier', 'Vendeur'],
        'module_dir' => 'rapports',
        'module_code' => 'rapports',
        'sub_menus' => [
            ['title' => 'Statistiques Générales', 'icon' => 'fas fa-chart-line', 'link' => BASE_URL . 'modules/rapports/statistiques_rapports.php', 'roles' => ['Administrateur', 'Gérant', 'Caissier', 'Vendeur'], 'module_code' => 'rapports'],
        ]
    ],

];

$user_role = $_SESSION['role'] ?? '';
$company_name = isset($company_info['nom']) && $company_info['nom'] !== '' ? $company_info['nom'] : 'HGB Multi';
$company_name_short = mb_strtoupper($company_name, 'UTF-8');

// Fonction pour vérifier l'accès à un module (combinaison rôle + permissions)
function checkMenuAccess($item, $user_role, $conn)
{
    // Vérification du rôle classique (rétrocompatibilité)
    $has_role_access = in_array($user_role, $item['roles']);

    // Si l'utilisateur n'a pas le rôle, pas d'accès
    if (!$has_role_access) {
        return false;
    }

    // Si un module_code est défini, vérifier aussi les permissions dynamiques
    if (isset($item['module_code']) && !empty($item['module_code']) && isset($conn)) {
        // Vérifier si la fonction canAccessModule existe et si les tables existent
        if (function_exists('canAccessModule') && function_exists('permissionTablesExist')) {
            if (permissionTablesExist($conn)) {
                return canAccessModule($conn, $item['module_code']);
            }
        }
    }

    // Fallback: accès basé sur le rôle uniquement
    return true;
}
?>

<!-- Sidebar -->
<div id="sidebar"
    class="fixed top-3 bottom-3 left-3 w-60 bg-gray-800 text-white transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out z-50 flex flex-col rounded-xl shadow-xl">
    <!-- Header de la Sidebar -->
    <div class="flex items-center justify-between p-4 border-b border-gray-700">
        <h2 class="text-xl font-bold text-white"><?php echo htmlspecialchars($company_name_short); ?></h2>
        <button id="sidebar-toggle-close" class="md:hidden text-gray-300 hover:text-white focus:outline-none">
            <i class="fas fa-times text-xl"></i>
        </button>
    </div>

    <!-- Navigation principale - avec scroll -->
    <nav class="flex-1 overflow-y-auto p-3 space-y-1">
        <!-- Titre du menu -->
        <div class="px-3 py-2 mb-2">
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Menu</p>
        </div>

        <?php foreach ($menu_items as $item): ?>
            <?php
            $has_access_parent = checkMenuAccess($item, $user_role, $conn);
            $is_parent_active = false;
            // Vérifier si un sous-menu est actif pour activer le parent
            if (isset($item['sub_menus'])) {
                foreach ($item['sub_menus'] as $sub_item) {
                    if (checkMenuAccess($sub_item, $user_role, $conn) && isActive($sub_item['link'], $current_page, basename(dirname(parse_url($sub_item['link'], PHP_URL_PATH))), $current_module_dir)) {
                        $is_parent_active = true;
                        break;
                    }
                }
            } else if (isset($item['link']) && isActive($item['link'], $current_page, $item['module_dir'], $current_module_dir)) {
                $is_parent_active = true;
            }

            if ($has_access_parent):
                ?>
                <div class="relative">
                    <?php if (isset($item['link']) && !isset($item['sub_menus'])): ?>
                        <!-- Lien direct (comme le dashboard) -->
                        <a href="<?php echo $item['link']; ?>"
                            class="w-full flex items-center px-3 py-2 text-sm rounded-md text-left text-gray-300 hover:bg-gray-700 hover:text-white focus:outline-none focus:bg-gray-700 transition duration-200 <?php echo $is_parent_active ? 'bg-gray-700 text-white' : ''; ?>">
                            <i class="<?php echo $item['icon']; ?> mr-3 text-sm w-4 text-center"></i>
                            <span class="flex-1 text-sm"><?php echo $item['title']; ?></span>
                        </a>
                    <?php else: ?>
                        <!-- Bouton avec dropdown (pour les menus avec sous-menus) -->
                        <button
                            class="w-full flex items-center px-3 py-2 text-sm rounded-md text-left text-gray-300 hover:bg-gray-700 hover:text-white focus:outline-none focus:bg-gray-700 transition duration-200 <?php echo $is_parent_active ? 'bg-gray-700 text-white' : ''; ?>"
                            data-dropdown-toggle="<?php echo str_replace(' ', '-', $item['title']); ?>">
                            <i class="<?php echo $item['icon']; ?> mr-3 text-sm w-4 text-center"></i>
                            <span class="flex-1 text-sm"><?php echo $item['title']; ?></span>
                            <?php if (isset($item['sub_menus'])): ?>
                                <i class="fas fa-chevron-down ml-auto text-xs"></i>
                            <?php endif; ?>
                        </button>
                        <?php if (isset($item['sub_menus'])): ?>
                            <div id="<?php echo str_replace(' ', '-', $item['title']); ?>"
                                class="dropdown-menu hidden bg-gray-750 rounded-md mt-1 ml-6 py-1 transition-all duration-300 ease-out overflow-hidden">
                                <?php foreach ($item['sub_menus'] as $sub_item): ?>
                                    <?php if (checkMenuAccess($sub_item, $user_role, $conn)): ?>
                                        <a href="<?php echo $sub_item['link']; ?>"
                                            class="flex items-center px-3 py-2 text-xs rounded-md text-gray-400 hover:bg-gray-600 hover:text-white transition duration-200 <?php echo isActive($sub_item['link'], $current_page, '', '') ? 'bg-gray-600 text-white font-medium' : ''; ?>">
                                            <i class="<?php echo $sub_item['icon']; ?> mr-3 text-xs w-4 text-center"></i>
                                            <span class="text-xs"><?php echo $sub_item['title']; ?></span>
                                        </a>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <!-- Bouton de déconnexion - fixé en bas -->
    <div class="p-3 border-t border-gray-700">
        <a href="<?php echo BASE_URL; ?>logout.php"
            class="flex items-center px-3 py-2 text-sm rounded-md text-gray-300 hover:bg-red-600 hover:text-white transition duration-200">
            <i class="fas fa-sign-out-alt mr-3 text-sm"></i>
            <span class="text-sm">Déconnexion</span>
        </a>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const dropdownToggles = document.querySelectorAll('[data-dropdown-toggle]');
        dropdownToggles.forEach(toggle => {
            toggle.addEventListener('click', function () {
                const dropdownId = this.dataset.dropdownToggle;
                const dropdownMenu = document.getElementById(dropdownId);
                if (dropdownMenu) {
                    dropdownMenu.classList.toggle('hidden');
                    // Optional: Rotate chevron icon
                    const chevron = this.querySelector('.fa-chevron-down');
                    if (chevron) {
                        chevron.classList.toggle('rotate-180');
                    }
                }
            });
        });

        // Open active parent dropdown on page load
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
            const activeLink = menu.querySelector('a.bg-blue-600');
            if (activeLink) {
                menu.classList.remove('hidden');
                const parentButton = menu.previousElementSibling;
                if (parentButton) {
                    const chevron = parentButton.querySelector('.fa-chevron-down');
                    if (chevron) {
                        chevron.classList.add('rotate-180');
                    }
                }
            }
        });

        // Mobile sidebar toggle
        const sidebar = document.getElementById('sidebar');
        const sidebarToggleOpen = document.getElementById('sidebar-toggle-open');
        const sidebarToggleClose = document.getElementById('sidebar-toggle-close');

        if (sidebarToggleOpen) {
            sidebarToggleOpen.addEventListener('click', function () {
                sidebar.classList.remove('-translate-x-full');
                sidebar.classList.add('translate-x-0');
            });
        }

        if (sidebarToggleClose) {
            sidebarToggleClose.addEventListener('click', function () {
                sidebar.classList.remove('translate-x-0');
                sidebar.classList.add('-translate-x-full');
            });
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function (event) {
            if (window.innerWidth < 768 && !sidebar.contains(event.target) && sidebarToggleOpen && !sidebarToggleOpen.contains(event.target) && sidebar.classList.contains('translate-x-0')) {
                closeSidebar();
            }
        });

        // Close sidebar when a link is clicked on mobile
        const sidebarLinks = sidebar.querySelectorAll('a');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', function () {
                if (window.innerWidth < 768) {
                    sidebar.classList.remove('translate-x-0');
                    sidebar.classList.add('-translate-x-full');
                }
            });
        });
    });
</script>
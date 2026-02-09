<?php
// modules/parametres/gerer_permissions.php
session_start();

include '../../db_connect.php';
include '../../includes/permissions_helper.php'; // Helper de permissions
include '../../includes/header.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("location: " . BASE_URL . "public/login.php");
    exit;
}

// Vérifier les permissions d'accès au module parametres (lecture et modification car c'est de l'admin)
// On utilise 'parametres' comme code module pour l'instant
if (!hasPermission($conn, 'parametres', 'read')) {
    header("location: " . BASE_URL . "dashboard.php?error=access_denied");
    exit;
}

$page_title = "Gestion des Permissions";
$message = '';

// Vérifier si les tables de permissions existent
$tables_exist = permissionTablesExist($conn);

// Récupérer la liste du personnel
$personnel_list = [];
$sql_personnel = "SELECT p.id, p.nom, p.prenom, p.nom_utilisateur, p.role, m.nom as nom_magasin
                  FROM personnel p
                  LEFT JOIN magasins m ON p.magasin_id = m.id
                  ORDER BY p.nom, p.prenom";
$result_personnel = mysqli_query($conn, $sql_personnel);
if ($result_personnel) {
    while ($row = mysqli_fetch_assoc($result_personnel)) {
        $personnel_list[] = $row;
    }
    mysqli_free_result($result_personnel);
}
?>

<div class="flex h-screen bg-gray-100">
    <?php include '../../includes/sidebar_dashboard.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden md:ml-64">
        <?php include '../../includes/topbar.php'; ?>

        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6 pb-20">
            <div class="container mx-auto">
                <h1 class="text-3xl font-bold text-gray-800 mb-6">
                    <?php echo $page_title; ?>
                </h1>

                <div class="bg-white p-6 rounded-lg shadow-md">

                    <?php if (!$tables_exist): ?>
                        <!-- Message d'initialisation des tables -->
                        <div class="text-center py-8">
                            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-yellow-100 mb-4">
                                <i class="fas fa-database text-yellow-600 text-2xl"></i>
                            </div>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Initialisation requise</h3>
                            <p class="text-gray-500 mb-6">Les tables de permissions n'ont pas encore été créées. Cliquez
                                sur le bouton ci-dessous pour les initialiser.</p>
                            <button id="initTablesBtn" onclick="initPermissionTables()"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-md shadow-md transition duration-300">
                                <i class="fas fa-cog mr-2"></i>Initialiser les tables de permissions
                            </button>
                        </div>
                    <?php else: ?>

                        <!-- Sélecteur de personnel -->
                        <div class="mb-6">
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="personnel_select">
                                <i class="fas fa-user mr-2"></i>Sélectionner un membre du personnel
                            </label>
                            <select id="personnel_select" onchange="loadPermissions(this.value)"
                                class="shadow appearance-none border rounded-md w-full md:w-1/2 py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                <option value="">-- Choisir un personnel --</option>
                                <?php foreach ($personnel_list as $p): ?>
                                    <option value="<?php echo $p['id']; ?>">
                                        <?php echo htmlspecialchars($p['nom'] . ' ' . $p['prenom']); ?>
                                        (
                                        <?php echo htmlspecialchars($p['role']); ?>)
                                        <?php if ($p['nom_magasin']): ?> -
                                            <?php echo htmlspecialchars($p['nom_magasin']); ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Indicateur de chargement -->
                        <div id="loading-permissions" class="hidden text-center py-8">
                            <div class="inline-flex items-center">
                                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-blue-600"
                                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                        stroke-width="4">
                                    </circle>
                                    <path class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                    </path>
                                </svg>
                                <span class="text-gray-600">Chargement des permissions...</span>
                            </div>
                        </div>

                        <!-- Message si aucun personnel sélectionné -->
                        <div id="no-selection-message" class="text-center py-8 text-gray-500">
                            <i class="fas fa-info-circle text-4xl mb-4 text-gray-300"></i>
                            <p>Sélectionnez un membre du personnel pour gérer ses permissions.</p>
                        </div>

                        <!-- Tableau des permissions -->
                        <div id="permissions-container" class="hidden">
                            <div class="mb-4 flex flex-col md:flex-row justify-between items-start md:items-center">
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900" id="selected-personnel-name">
                                        Permissions de</h3>
                                    <p class="text-sm text-gray-500" id="selected-personnel-role"></p>
                                </div>
                                <div class="mt-4 md:mt-0 flex gap-2">
                                    <button onclick="selectAll(true)"
                                        class="bg-green-100 hover:bg-green-200 text-green-700 text-sm font-medium py-1 px-3 rounded-md transition duration-300">
                                        <i class="fas fa-check-double mr-1"></i>Tout cocher
                                    </button>
                                    <button onclick="selectAll(false)"
                                        class="bg-red-100 hover:bg-red-200 text-red-700 text-sm font-medium py-1 px-3 rounded-md transition duration-300">
                                        <i class="fas fa-times mr-1"></i>Tout décocher
                                    </button>
                                </div>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col"
                                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Module
                                            </th>
                                            <th scope="col"
                                                class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                <i class="fas fa-plus-circle text-green-500 mr-1"></i>Créer
                                            </th>
                                            <th scope="col"
                                                class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                <i class="fas fa-eye text-blue-500 mr-1"></i>Lire
                                            </th>
                                            <th scope="col"
                                                class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                <i class="fas fa-edit text-yellow-500 mr-1"></i>Modifier
                                            </th>
                                            <th scope="col"
                                                class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                <i class="fas fa-trash-alt text-red-500 mr-1"></i>Supprimer
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody id="permissions-tbody" class="bg-white divide-y divide-gray-200">
                                        <!-- Les lignes seront générées dynamiquement par JavaScript -->
                                    </tbody>
                                </table>
                            </div>

                            <div class="mt-6 flex justify-end">
                                <button id="savePermissionsBtn" onclick="savePermissions()"
                                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-md shadow-md transition duration-300">
                                    <i class="fas fa-save mr-2"></i>Enregistrer les permissions
                                </button>
                            </div>
                        </div>

                    <?php endif; ?>
                </div>

            </div>
        </main>

        <?php include '../../includes/footer.php'; ?>
    </div>
</div>

<script>
    // Variables globales
    let currentPersonnelId = null;
    let permissionsData = [];

    // Fonction pour initialiser les tables de permissions
    function initPermissionTables() {
        const btn = document.getElementById('initTablesBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Initialisation en cours...';

        fetch('ajax_permissions.php?action=init_tables')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Tables créées avec succès ! La page va se recharger.');
                    location.reload();
                } else {
                    alert('Erreur : ' + data.message);
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-cog mr-2"></i>Initialiser les tables de permissions';
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur lors de l\'initialisation des tables.');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-cog mr-2"></i>Initialiser les tables de permissions';
            });
    }

    // Fonction pour charger les permissions d'un personnel
    function loadPermissions(personnelId) {
        if (!personnelId) {
            document.getElementById('no-selection-message').classList.remove('hidden');
            document.getElementById('permissions-container').classList.add('hidden');
            currentPersonnelId = null;
            return;
        }

        currentPersonnelId = personnelId;

        // Afficher l'indicateur de chargement
        document.getElementById('no-selection-message').classList.add('hidden');
        document.getElementById('permissions-container').classList.add('hidden');
        document.getElementById('loading-permissions').classList.remove('hidden');

        fetch('ajax_permissions.php?action=get_permissions&personnel_id=' + personnelId)
            .then(response => response.json())
            .then(data => {
                document.getElementById('loading-permissions').classList.add('hidden');

                if (data.success) {
                    permissionsData = data.permissions;

                    // Mettre à jour les infos du personnel
                    document.getElementById('selected-personnel-name').textContent =
                        'Permissions de ' + data.personnel.prenom + ' ' + data.personnel.nom;
                    document.getElementById('selected-personnel-role').textContent =
                        'Rôle actuel : ' + data.personnel.role;

                    // Générer le tableau des permissions
                    renderPermissionsTable(data.permissions);

                    document.getElementById('permissions-container').classList.remove('hidden');
                } else {
                    alert('Erreur : ' + data.message);
                    if (data.tables_missing) {
                        location.reload();
                    }
                }
            })
            .catch(error => {
                document.getElementById('loading-permissions').classList.add('hidden');
                console.error('Erreur:', error);
                alert('Erreur lors du chargement des permissions.');
            });
    }

    // Fonction pour générer le tableau des permissions
    function renderPermissionsTable(permissions) {
        const tbody = document.getElementById('permissions-tbody');
        tbody.innerHTML = '';

        permissions.forEach((perm, index) => {
            const row = document.createElement('tr');
            row.className = index % 2 === 0 ? 'bg-white' : 'bg-gray-50';

            row.innerHTML = `
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                    <i class="${perm.module_icone} text-gray-500 mr-3"></i>
                    <span class="text-sm font-medium text-gray-900">${perm.module_nom}</span>
                </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-center">
                <input type="checkbox" 
                       id="perm_create_${perm.module_id}" 
                       data-module="${perm.module_id}" 
                       data-type="create"
                       ${perm.can_create == 1 ? 'checked' : ''}
                       class="perm-checkbox h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded cursor-pointer">
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-center">
                <input type="checkbox" 
                       id="perm_read_${perm.module_id}" 
                       data-module="${perm.module_id}" 
                       data-type="read"
                       ${perm.can_read == 1 ? 'checked' : ''}
                       class="perm-checkbox h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded cursor-pointer">
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-center">
                <input type="checkbox" 
                       id="perm_update_${perm.module_id}" 
                       data-module="${perm.module_id}" 
                       data-type="update"
                       ${perm.can_update == 1 ? 'checked' : ''}
                       class="perm-checkbox h-4 w-4 text-yellow-600 focus:ring-yellow-500 border-gray-300 rounded cursor-pointer">
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-center">
                <input type="checkbox" 
                       id="perm_delete_${perm.module_id}" 
                       data-module="${perm.module_id}" 
                       data-type="delete"
                       ${perm.can_delete == 1 ? 'checked' : ''}
                       class="perm-checkbox h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300 rounded cursor-pointer">
            </td>
        `;

            tbody.appendChild(row);
        });
    }

    // Fonction pour tout cocher/décocher
    function selectAll(checked) {
        document.querySelectorAll('.perm-checkbox').forEach(checkbox => {
            checkbox.checked = checked;
        });
    }

    // Fonction pour sauvegarder les permissions
    function savePermissions() {
        if (!currentPersonnelId) {
            alert('Veuillez sélectionner un membre du personnel.');
            return;
        }

        const btn = document.getElementById('savePermissionsBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Enregistrement...';

        // Collecter les permissions depuis les checkboxes
        const permissions = [];
        const modules = {};

        document.querySelectorAll('.perm-checkbox').forEach(checkbox => {
            const moduleId = checkbox.dataset.module;
            const permType = checkbox.dataset.type;

            if (!modules[moduleId]) {
                modules[moduleId] = {
                    module_id: moduleId,
                    can_create: false,
                    can_read: false,
                    can_update: false,
                    can_delete: false
                };
            }

            modules[moduleId]['can_' + permType] = checkbox.checked;
        });

        // Convertir l'objet en tableau
        for (const moduleId in modules) {
            permissions.push(modules[moduleId]);
        }

        fetch('ajax_permissions.php?action=save_permissions', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                personnel_id: currentPersonnelId,
                permissions: permissions
            })
        })
            .then(response => response.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save mr-2"></i>Enregistrer les permissions';

                if (data.success) {
                    // Afficher un message de succès
                    const container = document.getElementById('permissions-container');
                    const successDiv = document.createElement('div');
                    successDiv.className = 'alert alert-success mb-4';
                    successDiv.innerHTML = '<i class="fas fa-check-circle mr-2"></i>' + data.message;
                    container.insertBefore(successDiv, container.firstChild);

                    // Supprimer le message après 3 secondes
                    setTimeout(() => {
                        successDiv.remove();
                    }, 3000);
                } else {
                    alert('Erreur : ' + data.message);
                }
            })
            .catch(error => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save mr-2"></i>Enregistrer les permissions';
                console.error('Erreur:', error);
                alert('Erreur lors de l\'enregistrement des permissions.');
            });
    }
</script>

</body>

</html>
<?php
// documentation.php
session_start();

// Inclure les fichiers nécessaires
include 'db_connect.php';
include 'includes/header.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("location: " . BASE_URL . "public/login.php");
    exit;
}

$page_title = "Documentation";
?>

<div class="flex h-screen bg-gray-100">
    <!-- Sidebar -->
    <?php include 'includes/sidebar_dashboard.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden md:ml-64">
        <!-- Top Bar -->
        <?php include 'includes/topbar.php'; ?>

        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6 pb-20">
            <div class="container mx-auto max-w-5xl">
                <div class="bg-white rounded-lg shadow-md p-8">
                    <!-- En-tête -->
                    <div class="mb-8">
                        <h1 class="text-4xl font-bold text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-book text-blue-600 mr-3"></i>
                            Documentation de l'Application
                        </h1>
                        <p class="text-gray-600 text-lg">
                            Guide complet des fonctionnalités du système de gestion de stock
                        </p>
                    </div>

                    <!-- Table des matières -->
                    <div class="bg-blue-50 rounded-lg p-6 mb-8 border-l-4 border-blue-600">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Table des Matières</h2>
                        <ul class="space-y-2">
                            <li><a href="#configuration" class="text-blue-600 hover:text-blue-800">1. Configuration de l'Entreprise</a></li>
                            <li><a href="#magasins" class="text-blue-600 hover:text-blue-800">2. Gestion des Magasins</a></li>
                            <li><a href="#personnel" class="text-blue-600 hover:text-blue-800">3. Gestion du Personnel</a></li>
                            <li><a href="#produits" class="text-blue-600 hover:text-blue-800">4. Gestion des Produits et Stock</a></li>
                            <li><a href="#transferts" class="text-blue-600 hover:text-blue-800">5. Transferts de Stock</a></li>
                            <li><a href="#clients" class="text-blue-600 hover:text-blue-800">6. Gestion des Clients</a></li>
                            <li><a href="#ventes" class="text-blue-600 hover:text-blue-800">7. Gestion des Ventes</a></li>
                            <li><a href="#retours" class="text-blue-600 hover:text-blue-800">8. Retours de Vente</a></li>
                            <li><a href="#finances" class="text-blue-600 hover:text-blue-800">9. Gestion Financière</a></li>
                            <li><a href="#fournisseurs" class="text-blue-600 hover:text-blue-800">10. Gestion des Fournisseurs</a></li>
                            <li><a href="#statistiques" class="text-blue-600 hover:text-blue-800">11. Statistiques et Rapports</a></li>
                            <li><a href="#dashboard" class="text-blue-600 hover:text-blue-800">12. Tableau de Bord</a></li>
                        </ul>
                    </div>

                    <!-- Section 1: Configuration de l'Entreprise -->
                    <section id="configuration" class="mb-12 scroll-mt-20">
                        <div class="border-l-4 border-green-500 pl-6 mb-6">
                            <h2 class="text-2xl font-bold text-gray-800 mb-2 flex items-center">
                                <i class="fas fa-building text-green-600 mr-2"></i>
                                1. Configuration de l'Entreprise
                            </h2>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-6 space-y-4">
                            <p class="text-gray-700">
                                La configuration de l'entreprise est la première étape pour personnaliser votre système. 
                                Cette section permet de définir les informations de base de votre entreprise.
                            </p>
                            
                            <h3 class="text-xl font-semibold text-gray-800 mt-4 mb-2">Accès</h3>
                            <p class="text-gray-700 mb-4">
                                <strong>Chemin :</strong> Paramètres → Configuration<br>
                                <strong>Rôle requis :</strong> Administrateur uniquement
                            </p>

                            <h3 class="text-xl font-semibold text-gray-800 mt-4 mb-2">Fonctionnalités</h3>
                            <ul class="list-disc list-inside space-y-2 text-gray-700 ml-4">
                                <li><strong>Nom de l'entreprise :</strong> Définit le nom officiel de votre entreprise (obligatoire)</li>
                                <li><strong>Adresse :</strong> Adresse complète de l'entreprise</li>
                                <li><strong>Téléphone :</strong> Numéro de téléphone de contact</li>
                                <li><strong>Email :</strong> Adresse email de l'entreprise</li>
                                <li><strong>IFU :</strong> Identifiant Fiscal Unique (pour les factures)</li>
                                <li><strong>RCCM :</strong> Numéro d'immatriculation au registre du commerce</li>
                                <li><strong>Devise :</strong> Sélection de la devise monétaire (XOF, EUR, USD, GBP, NGN)</li>
                            </ul>

                            <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mt-4 rounded">
                                <p class="text-sm text-gray-700">
                                    <strong>💡 Astuce :</strong> La devise sélectionnée sera utilisée automatiquement sur toutes les factures générées par le système.
                                </p>
                            </div>
                        </div>
                    </section>

                    <!-- Section 2: Gestion des Magasins -->
                    <section id="magasins" class="mb-12 scroll-mt-20">
                        <div class="border-l-4 border-blue-500 pl-6 mb-6">
                            <h2 class="text-2xl font-bold text-gray-800 mb-2 flex items-center">
                                <i class="fas fa-store-alt text-blue-600 mr-2"></i>
                                2. Gestion des Magasins
                            </h2>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-6 space-y-4">
                            <p class="text-gray-700">
                                La gestion des magasins permet de créer et administrer plusieurs points de vente dans votre système.
                            </p>
                            
                            <h3 class="text-xl font-semibold text-gray-800 mt-4 mb-2">Accès</h3>
                            <p class="text-gray-700 mb-4">
                                <strong>Chemin :</strong> Paramètres → Magasins<br>
                                <strong>Rôle requis :</strong> Administrateur uniquement
                            </p>

                            <h3 class="text-xl font-semibold text-gray-800 mt-4 mb-2">Fonctionnalités</h3>
                            <ul class="list-disc list-inside space-y-2 text-gray-700 ml-4">
                                <li><strong>Ajouter un magasin :</strong> Créer un nouveau point de vente avec nom, adresse et informations de contact</li>
                                <li><strong>Modifier un magasin :</strong> Mettre à jour les informations d'un magasin existant</li>
                                <li><strong>Supprimer un magasin :</strong> Retirer un magasin du système (attention : vérifier qu'aucune vente n'est associée)</li>
                                <li><strong>Recherche et filtres :</strong> Trouver rapidement un magasin dans la liste</li>
                            </ul>

                            <h3 class="text-xl font-semibold text-gray-800 mt-4 mb-2">Utilisation</h3>
                            <ol class="list-decimal list-inside space-y-2 text-gray-700 ml-4">
                                <li>Cliquez sur le bouton "Ajouter un Magasin"</li>
                                <li>Remplissez le formulaire avec les informations du magasin</li>
                                <li>Cliquez sur "Enregistrer" pour créer le magasin</li>
                                <li>Le magasin apparaîtra dans la liste et pourra être utilisé pour les ventes et le stock</li>
                            </ol>
                        </div>
                    </section>

                    <!-- Section 3: Gestion du Personnel -->
                    <section id="personnel" class="mb-12 scroll-mt-20">
                        <div class="border-l-4 border-purple-500 pl-6 mb-6">
                            <h2 class="text-2xl font-bold text-gray-800 mb-2 flex items-center">
                                <i class="fas fa-users text-purple-600 mr-2"></i>
                                3. Gestion du Personnel
                            </h2>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-6 space-y-4">
                            <p class="text-gray-700">
                                La gestion du personnel permet d'ajouter, modifier et supprimer les utilisateurs du système avec leurs rôles et permissions.
                            </p>
                            
                            <h3 class="text-xl font-semibold text-gray-800 mt-4 mb-2">Accès</h3>
                            <p class="text-gray-700 mb-4">
                                <strong>Chemin :</strong> Paramètres → Personnels<br>
                                <strong>Rôle requis :</strong> Administrateur uniquement
                            </p>

                            <h3 class="text-xl font-semibold text-gray-800 mt-4 mb-2">Fonctionnalités</h3>
                            <ul class="list-disc list-inside space-y-2 text-gray-700 ml-4">
                                <li><strong>Ajouter un membre du personnel :</strong> Créer un nouveau compte utilisateur avec nom, prénom, nom d'utilisateur, mot de passe et rôle</li>
                                <li><strong>Modifier un membre :</strong> Mettre à jour les informations (le mot de passe est optionnel lors de la modification)</li>
                                <li><strong>Supprimer un membre :</strong> Retirer un utilisateur du système</li>
                                <li><strong>Affectation à un magasin :</strong> Associer un membre du personnel à un magasin spécifique</li>
                            </ul>

                            <h3 class="text-xl font-semibold text-gray-800 mt-4 mb-2">Rôles Disponibles</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                <div class="bg-white p-4 rounded-lg border border-gray-200">
                                    <h4 class="font-semibold text-red-600 mb-2">Administrateur</h4>
                                    <p class="text-sm text-gray-600">Accès complet à toutes les fonctionnalités du système</p>
                                </div>
                                <div class="bg-white p-4 rounded-lg border border-gray-200">
                                    <h4 class="font-semibold text-orange-600 mb-2">Gérant</h4>
                                    <p class="text-sm text-gray-600">Gestion des produits, stock et transferts</p>
                                </div>
                                <div class="bg-white p-4 rounded-lg border border-gray-200">
                                    <h4 class="font-semibold text-blue-600 mb-2">Caissier</h4>
                                    <p class="text-sm text-gray-600">Gestion des ventes, clients et retours</p>
                                </div>
                                <div class="bg-white p-4 rounded-lg border border-gray-200">
                                    <h4 class="font-semibold text-green-600 mb-2">Vendeur</h4>
                                    <p class="text-sm text-gray-600">Accès au point de vente et historique</p>
                                </div>
                            </div>

                            <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 mt-4 rounded">
                                <p class="text-sm text-gray-700">
                                    <strong>⚠️ Important :</strong> Lors de la modification d'un membre du personnel, vous pouvez laisser le champ mot de passe vide si vous ne souhaitez pas le changer.
                                </p>
                            </div>
                        </div>
                    </section>

                    <!-- Section 4: Gestion des Produits et Stock -->
                    <section id="produits" class="mb-12 scroll-mt-20">
                        <div class="border-l-4 border-indigo-500 pl-6 mb-6">
                            <h2 class="text-2xl font-bold text-gray-800 mb-2 flex items-center">
                                <i class="fas fa-box-open text-indigo-600 mr-2"></i>
                                4. Gestion des Produits et Stock
                            </h2>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-6 space-y-4">
                            <p class="text-gray-700">
                                La gestion des produits permet d'ajouter, modifier et suivre l'inventaire de tous vos produits par magasin.
                            </p>
                            
                            <h3 class="text-xl font-semibold text-gray-800 mt-4 mb-2">Accès</h3>
                            <p class="text-gray-700 mb-4">
                                <strong>Chemin :</strong> Produits & Stock → Produits & Stock<br>
                                <strong>Rôle requis :</strong> Administrateur, Gérant
                            </p>

                            <h3 class="text-xl font-semibold text-gray-800 mt-4 mb-2">Fonctionnalités</h3>
                            <ul class="list-disc list-inside space-y-2 text-gray-700 ml-4">
                                <li><strong>Ajouter un produit :</strong> Créer un nouveau produit avec nom, description, prix d'achat, prix de vente, catégorie, fournisseur, magasin et image</li>
                                <li><strong>Modifier un produit :</strong> Mettre à jour les informations et le stock d'un produit existant</li>
                                <li><strong>Supprimer un produit :</strong> Retirer un produit du système</li>
                                <li><strong>Gestion du stock :</strong> Suivi de la quantité en stock et du seuil d'alerte</li>
                                <li><strong>Alerte de stock :</strong> Les produits dont le stock est inférieur ou égal au seuil d'alerte sont mis en évidence en rouge</li>
                                <li><strong>Recherche et filtres :</strong> Rechercher par nom, filtrer par catégorie, fournisseur ou magasin</li>
                            </ul>

                            <h3 class="text-xl font-semibold text-gray-800 mt-4 mb-2">Champs Importants</h3>
                            <ul class="list-disc list-inside space-y-2 text-gray-700 ml-4">
                                <li><strong>Seuil d'alerte stock :</strong> Quantité minimale avant alerte (le produit apparaîtra en rouge dans le dashboard)</li>
                                <li><strong>Prix d'achat :</strong> Prix auquel vous achetez le produit (pour le calcul des bénéfices)</li>
                                <li><strong>Prix de vente :</strong> Prix de vente au client</li>
                                <li><strong>Magasin :</strong> Chaque produit est associé à un magasin spécifique</li>
                            </ul>
                        </div>
                    </section>

                    <!-- Section 5: Transferts de Stock -->
                    <section id="transferts" class="mb-12 scroll-mt-20">
                        <div class="border-l-4 border-teal-500 pl-6 mb-6">
                            <h2 class="text-2xl font-bold text-gray-800 mb-2 flex items-center">
                                <i class="fas fa-truck-ramp-box text-teal-600 mr-2"></i>
                                5. Transferts de Stock
                            </h2>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-6 space-y-4">
                            <p class="text-gray-700">
                                Les transferts de stock permettent de déplacer des produits d'un magasin à un autre avec un système de validation.
                            </p>
                            
                            <h3 class="text-xl font-semibold text-gray-800 mt-4 mb-2">Accès</h3>
                            <p class="text-gray-700 mb-4">
                                <strong>Chemin :</strong> Produits & Stock → Transferts de Stock<br>
                                <strong>Rôle requis :</strong> Administrateur, Gérant
                            </p>

                            <h3 class="text-xl font-semibold text-gray-800 mt-4 mb-2">Processus de Transfert</h3>
                            <ol class="list-decimal list-inside space-y-2 text-gray-700 ml-4">
                                <li><strong>Demande de transfert :</strong> Un utilisateur crée une demande de transfert (statut : "en_attente")</li>
                                <li><strong>Confirmation :</strong> Un responsable confirme le transfert, ce qui met à jour automatiquement les stocks des deux magasins</li>
                                <li><strong>Rejet :</strong> Un responsable peut rejeter le transfert si nécessaire</li>
                                <li><strong>Annulation :</strong> Le demandeur peut annuler sa propre demande si elle est encore en attente</li>
                            </ol>

                            <h3 class="text-xl font-semibold text-gray-800 mt-4 mb-2">Fonctionnalités</h3>
                            <ul class="list-disc list-inside space-y-2 text-gray-700 ml-4">
                                <li><strong>Créer un transfert :</strong> Sélectionner le produit, le magasin source, le magasin destination et la quantité</li>
                                <li><strong>Vérification automatique :</strong> Le système vérifie que le stock source est suffisant</li>
                                <li><strong>Confirmation :</strong> Lors de la confirmation, le stock est déduit du magasin source et ajouté au magasin destination</li>
                                <li><strong>Suivi :</strong> Historique complet de tous les transferts avec dates et responsables</li>
                            </ul>

                            <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mt-4 rounded">
                                <p class="text-sm text-gray-700">
                                    <strong>💡 Note :</strong> Si le produit n'existe pas dans le magasin destination, il sera automatiquement créé lors de la confirmation du transfert.
                                </p>
                            </div>
                        </div>
                    </section>

                    <!-- Section 6: Gestion des Clients -->
                    <section id="clients" class="mb-12 scroll-mt-20">
                        <div class="border-l-4 border-pink-500 pl-6 mb-6">
                            <h2 class="text-2xl font-bold text-gray-800 mb-2 flex items-center">
                                <i class="fas fa-user-friends text-pink-600 mr-2"></i>
                                6. Gestion des Clients
                            </h2>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-6 space-y-4">
                            <p class="text-gray-700">
                                La gestion des clients permet de créer et administrer votre base de données clients avec leurs informations et comptes d'épargne.
                            </p>
                            
                            <h3 class="text-xl font-semibold text-gray-800 mt-4 mb-2">Sous-sections</h3>
                            
                            <div class="space-y-4 mt-4">
                                <div class="bg-white p-4 rounded-lg border border-gray-200">
                                    <h4 class="font-semibold text-gray-800 mb-2">6.1. Clients</h4>
                                    <p class="text-sm text-gray-700 mb-2">
                                        <strong>Chemin :</strong> Clients → Clients<br>
                                        <strong>Fonctionnalités :</strong> Ajouter, modifier, supprimer des clients avec leurs coordonnées complètes
                                    </p>
                                </div>

                                <div class="bg-white p-4 rounded-lg border border-gray-200">
                                    <h4 class="font-semibold text-gray-800 mb-2">6.2. Comptes Épargne</h4>
                                    <p class="text-sm text-gray-700 mb-2">
                                        <strong>Chemin :</strong> Clients → Comptes Épargne<br>
                                        <strong>Fonctionnalités :</strong> Gérer les comptes d'épargne des clients, créditer/débiter, consulter l'historique des transactions
                                    </p>
                                    <p class="text-sm text-gray-600 mt-2">
                                        Les clients peuvent utiliser leur solde d'épargne lors des achats au point de vente.
                                    </p>
                                </div>

                                <div class="bg-white p-4 rounded-lg border border-gray-200">
                                    <h4 class="font-semibold text-gray-800 mb-2">6.3. Dettes Clients</h4>
                                    <p class="text-sm text-gray-700 mb-2">
                                        <strong>Chemin :</strong> Clients → Dettes Clients<br>
                                        <strong>Fonctionnalités :</strong> Suivre les dettes des clients, enregistrer les paiements partiels, gérer les remboursements
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Section 7: Gestion des Ventes -->
                    <section id="ventes" class="mb-12 scroll-mt-20">
                        <div class="border-l-4 border-green-500 pl-6 mb-6">
                            <h2 class="text-2xl font-bold text-gray-800 mb-2 flex items-center">
                                <i class="fas fa-cash-register text-green-600 mr-2"></i>
                                7. Gestion des Ventes
                            </h2>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-6 space-y-4">
                            <p class="text-gray-700">
                                Le système de gestion des ventes comprend le point de vente, l'historique et les retours.
                            </p>
                            
                            <h3 class="text-xl font-semibold text-gray-800 mt-4 mb-2">7.1. Point de Vente (PdV)</h3>
                            <p class="text-gray-700 mb-4">
                                <strong>Chemin :</strong> Ventes → Point de Vente (PdV)<br>
                                <strong>Rôle requis :</strong> Administrateur, Caissier, Vendeur
                            </p>
                            
                            <h4 class="text-lg font-semibold text-gray-800 mt-4 mb-2">Fonctionnalités principales :</h4>
                            <ul class="list-disc list-inside space-y-2 text-gray-700 ml-4">
                                <li><strong>Recherche de produits :</strong> Recherche rapide par nom de produit</li>
                                <li><strong>Ajout au panier :</strong> Ajouter des produits avec quantités</li>
                                <li><strong>Réductions :</strong> Réductions par ligne (montant ou pourcentage) et réduction globale</li>
                                <li><strong>Gestion des paiements :</strong> Paiement en espèces, Mobile Money, ou combinaison</li>
                                <li><strong>Comptes d'épargne :</strong> Utilisation du solde d'épargne du client si disponible</li>
                                <li><strong>Dettes :</strong> Possibilité de laisser un montant dû par le client</li>
                                <li><strong>Impression de facture :</strong> Génération automatique d'une facture professionnelle</li>
                            </ul>

                            <h3 class="text-xl font-semibold text-gray-800 mt-6 mb-2">7.2. Historique des Ventes</h3>
                            <p class="text-gray-700 mb-4">
                                <strong>Chemin :</strong> Ventes → Historique des Ventes<br>
                                <strong>Fonctionnalités :</strong> Consulter toutes les ventes, rechercher, filtrer par magasin ou statut de paiement, réimprimer les factures
                            </p>

                            <h3 class="text-xl font-semibold text-gray-800 mt-6 mb-2">7.3. Retours de Vente</h3>
                            <p class="text-gray-700 mb-4">
                                <strong>Chemin :</strong> Ventes → Retours de Vente<br>
                                <strong>Fonctionnalités :</strong> Gérer les retours de produits, rembourser les clients, mettre à jour automatiquement le stock
                            </p>

                            <h4 class="text-lg font-semibold text-gray-800 mt-4 mb-2">Processus de retour :</h4>
                            <ol class="list-decimal list-inside space-y-2 text-gray-700 ml-4">
                                <li>Sélectionner la vente concernée depuis une liste déroulante</li>
                                <li>Choisir le produit à retourner</li>
                                <li>Indiquer la quantité à retourner (ne peut pas dépasser la quantité vendue moins les retours déjà effectués)</li>
                                <li>Spécifier la raison du retour</li>
                                <li>Le système calcule automatiquement le montant à rembourser et met à jour le stock</li>
                                <li>Impression du reçu de retour</li>
                            </ol>

                            <div class="bg-green-50 border-l-4 border-green-500 p-4 mt-4 rounded">
                                <p class="text-sm text-gray-700">
                                    <strong>✅ Avantage :</strong> Les ventes avec des retours sont automatiquement marquées dans l'historique pour une traçabilité complète.
                                </p>
                            </div>
                        </div>
                    </section>

                    <!-- Section 8: Retours de Vente -->
                    <section id="retours" class="mb-12 scroll-mt-20">
                        <div class="border-l-4 border-orange-500 pl-6 mb-6">
                            <h2 class="text-2xl font-bold text-gray-800 mb-2 flex items-center">
                                <i class="fas fa-undo text-orange-600 mr-2"></i>
                                8. Retours de Vente (Détails)
                            </h2>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-6 space-y-4">
                            <p class="text-gray-700">
                                Le système de retours permet de gérer efficacement les retours de produits avec remboursement automatique.
                            </p>

                            <h3 class="text-xl font-semibold text-gray-800 mt-4 mb-2">Caractéristiques</h3>
                            <ul class="list-disc list-inside space-y-2 text-gray-700 ml-4">
                                <li><strong>Sélection facile :</strong> Liste déroulante des ventes avec toutes les informations (ID, date, client, montant, magasin)</li>
                                <li><strong>Contrôle des quantités :</strong> Le système empêche de retourner plus que ce qui a été vendu</li>
                                <li><strong>Calcul automatique :</strong> Le montant remboursé est calculé en fonction du prix unitaire et des réductions appliquées</li>
                                <li><strong>Mise à jour du stock :</strong> Le stock est automatiquement réapprovisionné lors du retour</li>
                                <li><strong>Reçu de retour :</strong> Impression d'un reçu professionnel avec tous les détails</li>
                                <li><strong>Traçabilité :</strong> Les ventes avec retours sont marquées dans l'historique</li>
                            </ul>
                        </div>
                    </section>

                    <!-- Section 9: Gestion Financière -->
                    <section id="finances" class="mb-12 scroll-mt-20">
                        <div class="border-l-4 border-yellow-500 pl-6 mb-6">
                            <h2 class="text-2xl font-bold text-gray-800 mb-2 flex items-center">
                                <i class="fas fa-wallet text-yellow-600 mr-2"></i>
                                9. Gestion Financière
                            </h2>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-6 space-y-4">
                            <p class="text-gray-700">
                                La gestion financière permet de suivre les dépenses et les dettes de l'entreprise.
                            </p>

                            <h3 class="text-xl font-semibold text-gray-800 mt-4 mb-2">9.1. Dépenses</h3>
                            <p class="text-gray-700 mb-4">
                                <strong>Chemin :</strong> Finances → Dépenses<br>
                                <strong>Fonctionnalités :</strong> Enregistrer toutes les dépenses de l'entreprise, catégoriser, suivre les dépenses par période
                            </p>

                            <h3 class="text-xl font-semibold text-gray-800 mt-6 mb-2">9.2. Dettes Magasins</h3>
                            <p class="text-gray-700 mb-4">
                                <strong>Chemin :</strong> Finances → Dettes Magasins<br>
                                <strong>Fonctionnalités :</strong> Gérer les dettes de l'entreprise envers les magasins, suivre les paiements, consulter l'historique
                            </p>
                        </div>
                    </section>

                    <!-- Section 10: Gestion des Fournisseurs -->
                    <section id="fournisseurs" class="mb-12 scroll-mt-20">
                        <div class="border-l-4 border-cyan-500 pl-6 mb-6">
                            <h2 class="text-2xl font-bold text-gray-800 mb-2 flex items-center">
                                <i class="fas fa-truck text-cyan-600 mr-2"></i>
                                10. Gestion des Fournisseurs
                            </h2>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-6 space-y-4">
                            <p class="text-gray-700">
                                La gestion des fournisseurs permet de maintenir une base de données de tous vos fournisseurs.
                            </p>
                            
                            <h3 class="text-xl font-semibold text-gray-800 mt-4 mb-2">Accès</h3>
                            <p class="text-gray-700 mb-4">
                                <strong>Chemin :</strong> Fournisseurs → Fournisseurs<br>
                                <strong>Rôle requis :</strong> Administrateur uniquement
                            </p>

                            <h3 class="text-xl font-semibold text-gray-800 mt-4 mb-2">Fonctionnalités</h3>
                            <ul class="list-disc list-inside space-y-2 text-gray-700 ml-4">
                                <li><strong>Ajouter un fournisseur :</strong> Enregistrer les informations complètes (nom, contact, adresse)</li>
                                <li><strong>Modifier un fournisseur :</strong> Mettre à jour les informations</li>
                                <li><strong>Supprimer un fournisseur :</strong> Retirer un fournisseur (vérifier qu'aucun produit n'est associé)</li>
                                <li><strong>Association avec produits :</strong> Les fournisseurs peuvent être associés aux produits lors de leur création</li>
                            </ul>
                        </div>
                    </section>

                    <!-- Section 11: Statistiques et Rapports -->
                    <section id="statistiques" class="mb-12 scroll-mt-20">
                        <div class="border-l-4 border-red-500 pl-6 mb-6">
                            <h2 class="text-2xl font-bold text-gray-800 mb-2 flex items-center">
                                <i class="fas fa-chart-pie text-red-600 mr-2"></i>
                                11. Statistiques et Rapports
                            </h2>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-6 space-y-4">
                            <p class="text-gray-700">
                                Les statistiques et rapports fournissent une vue d'ensemble complète de votre activité.
                            </p>
                            
                            <h3 class="text-xl font-semibold text-gray-800 mt-4 mb-2">Accès</h3>
                            <p class="text-gray-700 mb-4">
                                <strong>Chemin :</strong> Statistiques → Statistiques Générales<br>
                                <strong>Rôle requis :</strong> Administrateur, Gérant
                            </p>

                            <h3 class="text-xl font-semibold text-gray-800 mt-4 mb-2">Indicateurs Disponibles</h3>
                            <ul class="list-disc list-inside space-y-2 text-gray-700 ml-4">
                                <li><strong>Bénéfices totaux :</strong> Calcul automatique des bénéfices sur les ventes</li>
                                <li><strong>Total des produits :</strong> Nombre total de produits en stock</li>
                                <li><strong>Produits en alerte :</strong> Nombre de produits avec stock faible</li>
                                <li><strong>Dettes clients :</strong> Montant total des dettes en cours</li>
                                <li><strong>Dettes magasins :</strong> Montant total des dettes de l'entreprise</li>
                                <li><strong>Épargne clients :</strong> Total des soldes d'épargne</li>
                                <li><strong>Chiffre d'affaires :</strong> Total des ventes</li>
                            </ul>

                            <h3 class="text-xl font-semibold text-gray-800 mt-4 mb-2">Exportation</h3>
                            <p class="text-gray-700 mb-4">
                                Possibilité d'exporter les statistiques en PDF ou Excel pour analyse approfondie.
                            </p>
                        </div>
                    </section>

                    <!-- Section 12: Tableau de Bord -->
                    <section id="dashboard" class="mb-12 scroll-mt-20">
                        <div class="border-l-4 border-indigo-500 pl-6 mb-6">
                            <h2 class="text-2xl font-bold text-gray-800 mb-2 flex items-center">
                                <i class="fas fa-tachometer-alt text-indigo-600 mr-2"></i>
                                12. Tableau de Bord
                            </h2>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-6 space-y-4">
                            <p class="text-gray-700">
                                Le tableau de bord offre une vue d'ensemble en temps réel de votre activité avec des indicateurs clés.
                            </p>
                            
                            <h3 class="text-xl font-semibold text-gray-800 mt-4 mb-2">Widgets de Statistiques</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                <div class="bg-white p-4 rounded-lg border border-gray-200">
                                    <h4 class="font-semibold text-red-600 mb-2">Produits en Alerte</h4>
                                    <p class="text-sm text-gray-600">Affiche le nombre de produits dont le stock est inférieur ou égal au seuil d'alerte</p>
                                </div>
                                <div class="bg-white p-4 rounded-lg border border-gray-200">
                                    <h4 class="font-semibold text-orange-600 mb-2">Transferts en Attente</h4>
                                    <p class="text-sm text-gray-600">Nombre de transferts de stock en attente de confirmation</p>
                                </div>
                                <div class="bg-white p-4 rounded-lg border border-gray-200">
                                    <h4 class="font-semibold text-blue-600 mb-2">Produits Vendus (30j)</h4>
                                    <p class="text-sm text-gray-600">Total des quantités de produits vendus sur les 30 derniers jours</p>
                                </div>
                                <div class="bg-white p-4 rounded-lg border border-gray-200">
                                    <h4 class="font-semibold text-green-600 mb-2">CA Total (30j)</h4>
                                    <p class="text-sm text-gray-600">Chiffre d'affaires total sur les 30 derniers jours</p>
                                </div>
                            </div>

                            <h3 class="text-xl font-semibold text-gray-800 mt-6 mb-2">Sections Détaillées</h3>
                            <ul class="list-disc list-inside space-y-2 text-gray-700 ml-4">
                                <li><strong>Produits en Alerte de Stock :</strong> Liste des 10 premiers produits nécessitant un réapprovisionnement</li>
                                <li><strong>Transferts de Stock en Attente :</strong> Liste des transferts non confirmés avec détails</li>
                                <li><strong>Produits les Plus Vendus :</strong> Top 10 des produits les plus vendus sur 30 jours avec quantités et CA</li>
                                <li><strong>Meilleurs Clients :</strong> Top 10 des clients avec le plus d'achats sur 30 jours</li>
                                <li><strong>Magasins - Chiffre d'Affaires :</strong> Classement des magasins par CA sur 30 jours</li>
                            </ul>
                        </div>
                    </section>

                    <!-- Section Aide et Support -->
                    <section class="mb-12">
                        <div class="bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg p-8 text-white">
                            <h2 class="text-2xl font-bold mb-4 flex items-center">
                                <i class="fas fa-question-circle mr-3"></i>
                                Besoin d'Aide ?
                            </h2>
                            <p class="text-lg mb-4">
                                Si vous avez des questions ou rencontrez des problèmes, n'hésitez pas à contacter le support technique.
                            </p>
                            <div class="flex flex-wrap gap-4">
                                <a href="#" class="bg-white text-blue-600 px-6 py-2 rounded-lg font-semibold hover:bg-gray-100 transition duration-300">
                                    <i class="fas fa-life-ring mr-2"></i> Support Technique
                                </a>
                                <a href="<?php echo BASE_URL; ?>dashboard.php" class="bg-white text-blue-600 px-6 py-2 rounded-lg font-semibold hover:bg-gray-100 transition duration-300">
                                    <i class="fas fa-home mr-2"></i> Retour au Tableau de Bord
                                </a>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </main>
        <?php include 'includes/footer.php'; ?>
    </div>
</div>

<script>
    // Smooth scroll pour les ancres
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
</script>

<script src="/public/assets/js/script.js"></script>
</body>
</html>


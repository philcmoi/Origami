<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Commandes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .statut-en_attente { background-color: #fff3cd; }
        .statut-confirmee { background-color: #d1ecf1; }
        .statut-expediee { background-color: #d4edda; }
        .statut-livree { background-color: #e2e3e5; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1 class="mb-4">Gestion des Commandes</h1>

        <!-- Formulaire d'ajout/modification -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 id="form-title">Nouvelle Commande</h5>
            </div>
            <div class="card-body">
                <form id="commande-form" action="actions.php" method="POST">
                    <input type="hidden" id="commande-id" name="id">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="client_nom" class="form-label">Nom du client</label>
                                <input type="text" class="form-control" id="client_nom" name="client_nom" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="client_email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="client_email" name="client_email" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="produit" class="form-label">Produit</label>
                                <input type="text" class="form-control" id="produit" name="produit" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="quantite" class="form-label">Quantité</label>
                                <input type="number" class="form-control" id="quantite" name="quantite" min="1" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="prix_unitaire" class="form-label">Prix unitaire (€)</label>
                                <input type="number" class="form-control" id="prix_unitaire" name="prix_unitaire" step="0.01" min="0" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="statut" class="form-label">Statut</label>
                        <select class="form-select" id="statut" name="statut">
                            <option value="en_attente">En attente</option>
                            <option value="confirmee">Confirmée</option>
                            <option value="expediee">Expédiée</option>
                            <option value="livree">Livrée</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary" name="action" value="create">Créer la commande</button>
                    <button type="button" id="btn-cancel" class="btn btn-secondary" style="display:none;">Annuler</button>
                </form>
            </div>
        </div>

        <!-- Recherche -->
        <div class="card mb-4">
            <div class="card-body">
                <form action="index.php" method="GET">
                    <div class="input-group">
                        <input type="text" class="form-control" name="search" placeholder="Rechercher une commande..." value="<?= $_GET['search'] ?? '' ?>">
                        <button class="btn btn-outline-secondary" type="submit">Rechercher</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Liste des commandes -->
        <div class="card">
            <div class="card-header">
                <h5>Liste des Commandes</h5>
            </div>
            <div class="card-body">
                <?php
                include_once 'config.php';
                include_once 'Commande.php';

                $database = new Database();
                $db = $database->getConnection();
                $commande = new Commande($db);

                if(isset($_GET['search'])) {
                    $stmt = $commande->search($_GET['search']);
                } else {
                    $stmt = $commande->readAll();
                }

                if($stmt->rowCount() > 0) {
                ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Client</th>
                                <th>Produit</th>
                                <th>Quantité</th>
                                <th>Prix Unitaire</th>
                                <th>Total</th>
                                <th>Statut</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $stmt->fetch()) { ?>
                            <tr class="statut-<?= $row['statut'] ?>">
                                <td><?= $row['id'] ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($row['client_nom']) ?></strong><br>
                                    <small><?= htmlspecialchars($row['client_email']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($row['produit']) ?></td>
                                <td><?= $row['quantite'] ?></td>
                                <td><?= number_format($row['prix_unitaire'], 2, ',', ' ') ?> €</td>
                                <td><strong><?= number_format($row['quantite'] * $row['prix_unitaire'], 2, ',', ' ') ?> €</strong></td>
                                <td>
                                    <span class="badge bg-secondary"><?= ucfirst(str_replace('_', ' ', $row['statut'])) ?></span>
                                </td>
                                <td><?= date('d/m/Y H:i', strtotime($row['date_commande'])) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-warning edit-btn" data-id="<?= $row['id'] ?>">Modifier</button>
                                    <button class="btn btn-sm btn-danger delete-btn" data-id="<?= $row['id'] ?>">Supprimer</button>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                <?php } else { ?>
                <p class="text-center">Aucune commande trouvée.</p>
                <?php } ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Édition d'une commande
        document.querySelectorAll('.edit-btn').forEach(button => {
            button.addEventListener('click', function() {
                const commandeId = this.getAttribute('data-id');
                
                fetch(`actions.php?action=read&id=${commandeId}`)
                    .then(response => response.json())
                    .then(data => {
                        document.getElementById('commande-id').value = data.id;
                        document.getElementById('client_nom').value = data.client_nom;
                        document.getElementById('client_email').value = data.client_email;
                        document.getElementById('produit').value = data.produit;
                        document.getElementById('quantite').value = data.quantite;
                        document.getElementById('prix_unitaire').value = data.prix_unitaire;
                        document.getElementById('statut').value = data.statut;
                        
                        document.getElementById('form-title').textContent = 'Modifier la Commande';
                        document.querySelector('button[name="action"]').value = 'update';
                        document.querySelector('button[name="action"]').textContent = 'Mettre à jour';
                        document.getElementById('btn-cancel').style.display = 'inline-block';
                    });
            });
        });

        // Annulation de l'édition
        document.getElementById('btn-cancel').addEventListener('click', function() {
            resetForm();
        });

        // Suppression d'une commande
        document.querySelectorAll('.delete-btn').forEach(button => {
            button.addEventListener('click', function() {
                if(confirm('Êtes-vous sûr de vouloir supprimer cette commande ?')) {
                    const commandeId = this.getAttribute('data-id');
                    window.location.href = `actions.php?action=delete&id=${commandeId}`;
                }
            });
        });

        function resetForm() {
            document.getElementById('commande-form').reset();
            document.getElementById('commande-id').value = '';
            document.getElementById('form-title').textContent = 'Nouvelle Commande';
            document.querySelector('button[name="action"]').value = 'create';
            document.querySelector('button[name="action"]').textContent = 'Créer la commande';
            document.getElementById('btn-cancel').style.display = 'none';
        }
    </script>
</body>
</html>
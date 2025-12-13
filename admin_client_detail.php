<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails Client #<?= $client_id ?> - Youki and Co</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
            color: #333;
            line-height: 1.6;
            min-height: 100vh;
        }

        .header {
            background: white;
            padding: 15px 30px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .logo h1 {
            color: #d40000;
            font-size: clamp(20px, 4vw, 26px);
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo h1:before {
            content: "🐾";
            font-size: 24px;
        }

        .admin-info {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 15px;
            font-size: 15px;
            color: #555;
        }

        .btn-logout {
            background: linear-gradient(to right, #d40000, #ff3333);
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            white-space: nowrap;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(212, 0, 0, 0.2);
        }

        .btn-logout:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(212, 0, 0, 0.3);
        }

        .container {
            display: flex;
            flex-direction: column;
            min-height: calc(100vh - 80px);
            padding: 25px;
            gap: 25px;
        }

        @media (min-width: 992px) {
            .container {
                flex-direction: row;
                gap: 30px;
            }
        }

        .sidebar {
            width: 100%;
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
            order: 2;
            height: fit-content;
        }

        @media (min-width: 992px) {
            .sidebar {
                width: 280px;
                order: 1;
                min-height: calc(100vh - 130px);
            }
        }

        .main-content {
            flex: 1;
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
            order: 1;
        }

        @media (min-width: 992px) {
            .main-content {
                order: 2;
            }
        }

        .sidebar-nav ul {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        @media (min-width: 768px) {
            .sidebar-nav ul {
                flex-direction: row;
                flex-wrap: wrap;
            }
        }

        @media (min-width: 992px) {
            .sidebar-nav ul {
                flex-direction: column;
            }
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px 20px;
            text-decoration: none;
            color: #444;
            background: #f8f9fa;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }

        .sidebar-nav a:hover {
            background: #e9ecef;
            color: #d40000;
            border-left-color: #d40000;
            transform: translateX(5px);
        }

        .sidebar-nav a.active {
            background: #fff0f0;
            color: #d40000;
            border-left-color: #d40000;
            font-weight: 600;
        }

        .sidebar-nav i {
            font-size: 18px;
            width: 24px;
            text-align: center;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .page-header h2 {
            color: #222;
            font-size: 28px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .page-header h2:before {
            content: "👤";
            font-size: 32px;
        }

        .btn-back {
            background: #6c757d;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-back:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .client-detail-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            border-left: 5px solid #d40000;
        }

        .client-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .info-label {
            font-size: 13px;
            color: #666;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-value {
            font-size: 16px;
            color: #222;
            font-weight: 500;
            padding: 10px 15px;
            background: white;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }

        .section-title {
            font-size: 20px;
            color: #222;
            font-weight: 600;
            margin: 25px 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .orders-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }

        .orders-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #444;
            border-bottom: 2px solid #e0e0e0;
        }

        .orders-table td {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }

        .orders-table tr:hover {
            background: #f9f9f9;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            text-align: center;
            display: inline-block;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }

        .btn-edit, .btn-delete {
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-edit {
            background: #007bff;
            color: white;
        }

        .btn-edit:hover {
            background: #0056b3;
            transform: translateY(-2px);
        }

        .btn-delete {
            background: #dc3545;
            color: white;
        }

        .btn-delete:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #777;
            font-style: italic;
            background: #f9f9f9;
            border-radius: 10px;
            margin: 20px 0;
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .main-content, .sidebar {
                padding: 20px;
            }
            
            .client-info-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .orders-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="header">
        <div class="logo">
            <h1>Youki and Co - Admin</h1>
        </div>
        <div class="admin-info">
            <span>Connecté en tant que: <strong>Admin</strong></span>
            <a href="logout.php" class="btn-logout">
                <i class="fas fa-sign-out-alt"></i> Déconnexion
            </a>
        </div>
    </div>

    <div class="container">
        <aside class="sidebar">
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Tableau de bord</a></li>
                    <li><a href="admin_clients.php" class="active"><i class="fas fa-users"></i> Gestion Clients</a></li>
                    <li><a href="admin_products.php"><i class="fas fa-box"></i> Produits</a></li>
                    <li><a href="admin_orders.php"><i class="fas fa-shopping-cart"></i> Commandes</a></li>
                    <li><a href="admin_messages.php"><i class="fas fa-envelope"></i> Messages</a></li>
                    <li><a href="admin_stats.php"><i class="fas fa-chart-bar"></i> Statistiques</a></li>
                    <li><a href="admin_settings.php"><i class="fas fa-cog"></i> Paramètres</a></li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <h2>Détails du Client #<?= htmlspecialchars($client_id) ?></h2>
                <a href="admin_clients.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Retour à la liste
                </a>
            </div>

            <div class="client-detail-card">
                <h3>Informations Personnelles</h3>
                <div class="client-info-grid">
                    <div class="info-item">
                        <span class="info-label">Nom Complet</span>
                        <div class="info-value">Jean Dupont</div>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Email</span>
                        <div class="info-value">jean.dupont@example.com</div>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Téléphone</span>
                        <div class="info-value">+33 1 23 45 67 89</div>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Date d'inscription</span>
                        <div class="info-value">15 mars 2024</div>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Statut</span>
                        <div class="info-value">
                            <span class="status-badge status-active">Actif</span>
                        </div>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Dernière connexion</span>
                        <div class="info-value">Aujourd'hui, 14:30</div>
                    </div>
                </div>
            </div>

            <div class="client-detail-card">
                <h3>Adresse</h3>
                <div class="client-info-grid">
                    <div class="info-item">
                        <span class="info-label">Adresse</span>
                        <div class="info-value">123 Rue de la Paix</div>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Complément</span>
                        <div class="info-value">Appartement 45</div>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Code Postal</span>
                        <div class="info-value">75001</div>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Ville</span>
                        <div class="info-value">Paris</div>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Pays</span>
                        <div class="info-value">France</div>
                    </div>
                </div>
            </div>

            <h3 class="section-title">
                <i class="fas fa-shopping-cart"></i> Historique des Commandes
            </h3>

            <?php if (isset($orders) && count($orders) > 0): ?>
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>N° Commande</th>
                            <th>Date</th>
                            <th>Montant</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>#<?= htmlspecialchars($order['id']) ?></td>
                            <td><?= htmlspecialchars($order['date']) ?></td>
                            <td><?= htmlspecialchars($order['amount']) ?> €</td>
                            <td>
                                <span class="status-badge <?= $order['status'] === 'livré' ? 'status-active' : 'status-inactive' ?>">
                                    <?= htmlspecialchars($order['status']) ?>
                                </span>
                            </td>
                            <td>
                                <a href="admin_order_detail.php?id=<?= $order['id'] ?>" class="btn-edit" style="padding: 6px 12px; font-size: 13px;">
                                    <i class="fas fa-eye"></i> Voir
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-shopping-cart fa-2x" style="margin-bottom: 15px;"></i>
                    <p>Aucune commande pour ce client</p>
                </div>
            <?php endif; ?>

            <div class="action-buttons">
                <a href="admin_client_edit.php?id=<?= $client_id ?>" class="btn-edit">
                    <i class="fas fa-edit"></i> Modifier le client
                </a>
                <a href="admin_client_delete.php?id=<?= $client_id ?>" class="btn-delete" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce client ?');">
                    <i class="fas fa-trash"></i> Supprimer le client
                </a>
            </div>
        </main>
    </div>

    <script>
        // Script pour la confirmation de suppression
        document.addEventListener('DOMContentLoaded', function() {
            const deleteBtn = document.querySelector('.btn-delete');
            if (deleteBtn) {
                deleteBtn.addEventListener('click', function(e) {
                    if (!confirm('Êtes-vous sûr de vouloir supprimer ce client ? Cette action est irréversible.')) {
                        e.preventDefault();
                    }
                });
            }
        });
    </script>
</body>
</html>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGDI - Système de Gestion des Dossiers d'Implantation | MINEE/DPPG</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <style>
        :root {
            --primary-color: #1e3a8a;
            --secondary-color: #059669;
            --accent-color: #d97706;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .landing-container {
            max-width: 1200px;
            padding: 2rem;
        }

        .hero-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .hero-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #1e40af 100%);
            color: white;
            padding: 3rem 2rem;
            text-align: center;
        }

        .hero-header h1 {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 1rem;
        }

        .hero-header p {
            font-size: 1.2rem;
            opacity: 0.9;
        }

        .hero-body {
            padding: 3rem 2rem;
        }

        .feature-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            height: 100%;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        .feature-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .feature-icon.primary { color: var(--primary-color); }
        .feature-icon.success { color: var(--secondary-color); }
        .feature-icon.warning { color: var(--accent-color); }

        .cta-section {
            padding: 0;
            margin-top: 2rem;
        }

        .access-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 3rem 2rem;
            border-radius: 15px;
            text-align: center;
            height: 100%;
        }

        .login-card {
            background: white;
            border: 2px solid #e5e7eb;
            padding: 3rem 2rem;
            border-radius: 15px;
            height: 100%;
        }

        .login-card h3 {
            color: var(--primary-color);
        }

        .btn-large {
            padding: 1rem 3rem;
            font-size: 1.2rem;
            border-radius: 50px;
            font-weight: bold;
            margin: 0.5rem;
            transition: transform 0.2s;
        }

        .btn-large:hover {
            transform: scale(1.05);
        }

        .footer-landing {
            text-align: center;
            color: white;
            margin-top: 2rem;
            opacity: 0.9;
        }

        .stats-badge {
            background: white;
            color: var(--primary-color);
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            display: inline-block;
            margin: 0.5rem;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="landing-container">
        <div class="hero-card">
            <!-- Header -->
            <div class="hero-header">
                <div class="mb-3">
                    <i class="fas fa-oil-can fa-3x"></i>
                </div>
                <h1>SGDI</h1>
                <h2 class="h4">Système de Gestion des Dossiers d'Implantation</h2>
                <p class="mt-3">Ministère de l'Eau et de l'Énergie<br>Direction du Pétrole, du Produit Pétrolier et du Gaz (DPPG)</p>
            </div>

            <!-- Body -->
            <div class="hero-body">
                <!-- Features -->
                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <div class="feature-card">
                            <div class="feature-icon primary">
                                <i class="fas fa-map-marked-alt"></i>
                            </div>
                            <h5>Carte Interactive</h5>
                            <p class="text-muted">Visualisez toutes les infrastructures pétrolières autorisées sur une carte interactive du Cameroun</p>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="feature-card">
                            <div class="feature-icon success">
                                <i class="fas fa-search"></i>
                            </div>
                            <h5>Recherche Avancée</h5>
                            <p class="text-muted">Recherchez des infrastructures par type, région, ville, opérateur ou numéro de dossier</p>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="feature-card">
                            <div class="feature-icon warning">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <h5>Statistiques</h5>
                            <p class="text-muted">Consultez les statistiques détaillées par type d'infrastructure et par région</p>
                        </div>
                    </div>
                </div>

                <!-- Types d'infrastructures -->
                <div class="text-center mb-4">
                    <h4 class="mb-3">Types d'Infrastructures</h4>
                    <div>
                        <span class="stats-badge"><i class="fas fa-gas-pump"></i> Stations-service</span>
                        <span class="stats-badge"><i class="fas fa-industry"></i> Points consommateurs</span>
                        <span class="stats-badge"><i class="fas fa-warehouse"></i> Dépôts GPL</span>
                        <span class="stats-badge"><i class="fas fa-fire"></i> Centres emplisseurs</span>
                    </div>
                </div>

                <!-- CTA Section -->
                <div class="cta-section">
                    <div class="row">
                        <!-- Accès Public -->
                        <div class="col-md-6 mb-3">
                            <div class="access-card">
                                <h3 class="mb-3">Accès Public</h3>
                                <p class="mb-4">Consultez les infrastructures pétrolières autorisées au Cameroun</p>
                                <div>
                                    <a href="modules/registre_public/index.php" class="btn btn-light btn-large w-100 mb-3">
                                        <i class="fas fa-list"></i> Voir le Registre
                                    </a>
                                    <a href="modules/registre_public/carte.php" class="btn btn-outline-light btn-large w-100">
                                        <i class="fas fa-map-marked-alt"></i> Voir la Carte
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Connexion Personnel DPPG -->
                        <div class="col-md-6 mb-3">
                            <div class="login-card">
                                <h3 class="mb-3 text-center"><i class="fas fa-user-lock"></i> Personnel DPPG</h3>
                                <p class="text-muted text-center mb-4">Connectez-vous pour accéder au système de gestion</p>

                                <form action="login.php" method="POST">
                                    <div class="mb-3">
                                        <label class="form-label">Nom d'utilisateur</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                                            <input type="text" class="form-control" name="username" placeholder="Votre nom d'utilisateur" required autofocus>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Mot de passe</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                            <input type="password" class="form-control" name="password" placeholder="••••••••" required>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-primary btn-lg w-100 mb-3">
                                        <i class="fas fa-sign-in-alt"></i> Se connecter
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer-landing">
            <p class="mb-1">
                <strong>République du Cameroun</strong><br>
                Paix - Travail - Patrie
            </p>
            <p class="mb-0 small">
                © <?php echo date('Y'); ?> MINEE/DPPG - Tous droits réservés
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

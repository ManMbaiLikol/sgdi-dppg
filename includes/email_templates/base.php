<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
            font-size: 14px;
        }
        .content {
            padding: 30px;
        }
        .content h2 {
            color: #667eea;
            margin-top: 0;
        }
        .alert {
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            border-left: 4px solid;
        }
        .alert-info {
            background-color: #e7f3ff;
            border-color: #2196F3;
            color: #0c5ba0;
        }
        .alert-success {
            background-color: #e8f5e9;
            border-color: #4CAF50;
            color: #2e7d32;
        }
        .alert-warning {
            background-color: #fff3e0;
            border-color: #FF9800;
            color: #e65100;
        }
        .alert-danger {
            background-color: #ffebee;
            border-color: #f44336;
            color: #c62828;
        }
        .details {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .details p {
            margin: 8px 0;
        }
        .details strong {
            color: #667eea;
        }
        .button {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 4px;
            margin: 20px 0;
            font-weight: bold;
        }
        .footer {
            background: #333;
            color: #ffffff;
            padding: 20px;
            text-align: center;
            font-size: 12px;
        }
        .footer a {
            color: #667eea;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>SGDI - DPPG</h1>
            <p>Système de Gestion des Dossiers d'Implantation</p>
            <p>Ministère de l'Eau et de l'Énergie - MINEE</p>
        </div>
        <div class="content">
            <?php echo $email_content; ?>
        </div>
        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> MINEE/DPPG - République du Cameroun</p>
            <p>Ceci est un email automatique, merci de ne pas y répondre.</p>
        </div>
    </div>
</body>
</html>

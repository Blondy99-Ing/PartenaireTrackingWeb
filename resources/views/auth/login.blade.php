<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <style>
        /* Généralement du style pour le body */
        body {
            font-family: 'Roboto', sans-serif;
            margin: 0;
            padding: 0;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: #f4f4f4;
        }

        .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            width: 100%;
            padding: 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        /* Image à gauche */
        .left {
            flex: 1;
            padding-right: 20px;
        }

        .left img {
            width: 100%;
            border-radius: 8px;
        }

        /* Formulaire à droite */
        .right {
            flex: 1;
        }

        .right h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #333;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            font-size: 14px;
            color: #555;
        }

        .form-group input {
            width: 100%;
            padding: 10px;
            font-size: 16px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }

        .form-group input:focus {
            border-color: #007bff;
            outline: none;
        }

        .btn {
            width: 100%;
            padding: 12px;
            background-color: #007bff;
            color: white;
            font-size: 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .btn:hover {
            background-color: #0056b3;
        }

        /* Responsivité : display block sur petits écrans */
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
                text-align: center;
            }

            .left {
                padding-right: 0;
                margin-bottom: 20px;
            }

            .right {
                width: 100%;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Image et texte à gauche -->
    <div class="left">
        <!-- Image en ligne (URL) -->
        <img src="https://via.placeholder.com/500x400" alt="Login Image">
        <p style="text-align: center; margin-top: 20px; color: #333;">Bienvenue dans notre plateforme. Connectez-vous pour continuer.</p>
    </div>

    <!-- Formulaire à droite -->
    <div class="right">
        <h2>Se connecter</h2>
        <form action="{{ url('login') }}" method="POST">
            @csrf
            <div class="form-group">
                <label for="agence_unique_id">ID de l'agence</label>
                <input type="text" id="agence_unique_id" name="agence_unique_id" required>
            </div>
            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn">Se connecter</button>
        </form>
    </div>
</div>

</body>
</html>

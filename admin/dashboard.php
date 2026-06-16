<?php
// On configure la session de manière sécurisée avant de la démarrer
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1); // À désactiver uniquement si tu es en local sans HTTPS
ini_set('session.cookie_samesite', 'Strict');
session_start();

// Si l'utilisateur n'est pas connecté ou n'a pas le rôle 'admin', retour immédiat à l'accueil
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Scuderia Ferrari - Dashboard Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css"> </head>
<body>
    <h1>Bienvenue sur le panneau d'administration, <?= htmlspecialchars($_SESSION['user']['prenom']) ?> !</h1>
    <p>Ici, tu vas pouvoir gérer les voitures, modérer les avis et lire les messages de contact.</p>
    
    <a href="../index.php">Retour au site</a>
</body>
</html>
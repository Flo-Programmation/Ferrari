<?php
// config/database.php

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_path', '/'); // Assure que la session est partagée entre l'API et l'index
    session_start();
}

try {
    $bdd = new PDO('mysql:host=localhost;dbname=ferrari_vitrine;charset=utf8', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (Exception $e) {
    die('Erreur de connexion à la base de données : ' . $e->getMessage());
}

/**
 * Génère un jeton CSRF unique pour le formulaire HTML d'index.php
 */
function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
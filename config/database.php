<?php
// config/database.php

$host     = 'localhost';
$dbname   = 'ferrari_vitrine'; // <--- Vérifiez que ce nom correspond à votre phpMyAdmin
$username = 'root';       // Identifiant par défaut XAMPP / Wamp
$password = '';           // Mot de passe vide par défaut sur XAMPP / Wamp

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    // Si l'appel est fait en AJAX, on renvoie du JSON, sinon un message propre
    if (isset($_POST['action'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => "Erreur BDD : " . $e->getMessage()]);
        exit;
    } else {
        die("Impossible de se connecter à la base de données : " . $e->getMessage());
    }
}
<?php
// api/get_vehicules.php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php'; // Votre instance PDO $pdo

try {
    // Sélection de TOUTES les informations de la table voiture
    $stmt = $bdd->prepare("SELECT id, modele, annee, puissance_ch, vitesse_max, moteur, prix, description, glb_url, sound_url, image_url FROM voiture ORDER BY id ASC");
    $stmt->execute();
    $cars = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'cars' => $cars
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données : ' . $e->getMessage()
    ]);
}
exit;
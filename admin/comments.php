<?php
// api/comments.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
$action = $_GET['action'] ?? '';

switch ($action) {
    // ─── RÉCUPÉRER LES AVIS D'UN VÉHICULE (ACCESSIBLE À TOUS) ───
    case 'get':
        $vehicle_index = isset($_GET['vehicle_index']) ? (int)$_GET['vehicle_index'] : -1;

        if ($vehicle_index < 0) {
            echo json_encode(['success' => false, 'message' => 'Véhicule invalide.']);
            exit;
        }

        try {
            // Jointure pour récupérer dynamiquement le nom, prénom et avatar de l'auteur
            $stmt = $pdo->prepare("
                SELECT c.*, u.nom, u.prenom, u.avatar_url 
                FROM comments c 
                JOIN users u ON c.user_id = u.id 
                WHERE c.vehicle_index = ? 
                ORDER BY c.created_at DESC
            ");
            $stmt->execute([$vehicle_index]);
            $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'comments' => $comments]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la récupération des avis.']);
        }
        break;

    // ─── AJOUTER UN AVIS (RÉSERVÉ AUX MEMBRES CONNECTÉS) ───
    case 'add':
    if (!isset($_SESSION['user'])) {
        echo json_encode(['success' => false, 'message' => 'Accès refusé']);
        exit;
    }

    // Correction ici : ajouter les underscores (_) à $_POST
    $user_id = $_SESSION['user']['id'];
    $vehicle_index = isset($_POST['vehicle_index']) ? (int)$_POST['vehicle_index'] : -1;
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

        if ($vehicle_index < 0 || $rating < 1 || $rating > 5 || empty($comment)) {
            echo json_encode(['success' => false, 'message' => 'Données du formulaire invalides ou incomplètes.']);
            exit;
        }

        try {
            // Insertion en base de données
            $stmt = $pdo->prepare("INSERT INTO comments (user_id, vehicle_index, rating, comment) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $vehicle_index, $rating, $comment]);

            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la sauvegarde de votre avis.']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Action inconnue.']);
        break;
}
<?php
// api/main.php
header('Content-Type: application/json');
require_once '../config/database.php';

$action = $_GET['action'] ?? '';

if ($action === 'get_car') {
    $id = intval($_GET['id'] ?? 1);
    
    // 1. Récupérer les infos de la voiture
    $stmt = $pdo->prepare("SELECT * FROM VOITURE WHERE id = ?");
    $stmt->execute([$id]);
    $car = $stmt->fetch();
    
    if (!$car) {
        echo json_encode(['error' => 'Voiture non trouvée']);
        exit;
    }
    
    // 2. Récupérer les messages/avis associés (via la table MESSAGES jointe à USERS)
    // Note: Suivant ton MCD, si les messages ne sont pas liés par car_id, on récupère les derniers avis généraux.
    $stmtImg = $pdo->prepare("SELECT m.coontent, m.created_at, u.prenom, u.nom, u.avatar_url 
                            FROM MESSAGES m 
                            JOIN USERS u ON m.user_id = u.id 
                            WHERE m.is_deleted = 0 
                            ORDER BY m.created_at DESC LIMIT 3");
    $stmtImg->execute();
    $reviews = $stmtImg->fetchAll();
    
    echo json_encode([
        'car' => $car,
        'reviews' => $reviews
    ]);
    exit;
}
?>
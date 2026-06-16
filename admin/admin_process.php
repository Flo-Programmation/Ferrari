<?php
// admin/admin_process.php

error_reporting(E_ALL);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php'; 

// 1. VÉRIFICATION STRICTE DE SÉCURITÉ (ACCÈS ADMIN)
if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Accès interdit.']);
    exit;
}

$response = [
    'success' => false,
    'message' => 'Action inconnue ou non autorisée.'
];

function verify_csrf_token(?string $token): bool {
    if (!$token || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// -------------------------------------------------------------------------
// TRAITEMENT DES REQUÊTES GET : Récupération de tous les avis
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'getAllComments') {
        try {
            // Jointure pour récupérer le nom de l'auteur de l'avis
            // Note : on récupère même les avis signalés (is_deleted = 1) pour permettre la modération
            $stmt = $bdd->prepare("
                SELECT c.id, c.comment, c.rating, c.vehicle_index, c.is_deleted, u.prenom, u.nom 
                FROM comments c
                INNER JOIN users u ON c.user_id = u.id
                ORDER BY c.id DESC
            ");
            $stmt->execute();
            $all_comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'comments' => $all_comments], JSON_UNESCAPED_UNICODE);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur de base de données : ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}

// -------------------------------------------------------------------------
// TRAITEMENT DES REQUÊTES POST : Actions de modération
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf_received = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf_received)) {
        echo json_encode(['success' => false, 'message' => 'Erreur de sécurité : Jeton CSRF invalide ou expiré.']);
        exit;
    }

    $comment_id = (int)($_POST['comment_id'] ?? 0);

    if ($comment_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Identifiant de commentaire invalide.']);
        exit;
    }

    // ACTION : Masquer/Signaler un avis (Soft delete)
    if ($action === 'flagComment') {
        try {
            $stmt = $bdd->prepare("UPDATE comments SET is_deleted = 1 WHERE id = ?");
            $stmt->execute([$comment_id]);

            echo json_encode(['success' => true, 'message' => 'Le commentaire a été masqué du site public.']);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur lors du masquage de l\'avis.']);
            exit;
        }
    }

    // ACTION : Supprimer définitivement un avis (Hard delete)
    elseif ($action === 'deleteComment') {
        try {
            $stmt = $bdd->prepare("DELETE FROM comments WHERE id = ?");
            $stmt->execute([$comment_id]);

            echo json_encode(['success' => true, 'message' => 'Le commentaire a été définitivement supprimé de la base de données.']);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression définitive de l\'avis.']);
            exit;
        }
    }
}

echo json_encode($response);
exit;
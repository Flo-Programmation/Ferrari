<?php
// admin/admin_process.php

header('Content-Type: application/json; charset=UTF-8');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'path' => '/',
        'secure' => false, 
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// 1. Contrôle d'accès strict
if (!isset($_SESSION['user']) || strtolower(trim($_SESSION['user']['role'])) !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Accès refusé. Autorisation insuffisante.']);
    exit;
}

// 2. Connexion à la base de données
require_once '../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];

// --- TRAITEMENT DES REQUÊTES DE LECTURE (GET) ---
if ($method === 'GET') {
    $action = $_GET['action'] ?? '';

    // ACTION : Récupérer tous les avis (AVEC LE VRAI CODE SQL AJOUTÉ ICI)
    if ($action === 'getAllComments') {
        try {
            // Récupération de tous les commentaires avec les vrais noms de colonnes de ta table
            $stmt = $bdd->query("
                SELECT 
                    c.id, 
                    c.vehicle_index, 
                    c.rating, 
                    c.comment, 
                    c.is_deleted,
                    u.prenom, 
                    u.nom 
                FROM comments c
                INNER JOIN users u ON c.user_id = u.id
                ORDER BY c.id DESC
            ");
            $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'comments' => $comments]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la récupération des avis : ' . $e->getMessage()]);
            exit;
        }
    }

    // ACTION : Récupérer tous les messages de contact
    if ($action === 'getAllMessages') {
        try {
            $stmt = $bdd->query("SELECT * FROM contact_requests ORDER BY id DESC");
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'messages' => $messages]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la récupération : ' . $e->getMessage()]);
            exit;
        }
    }
}

// --- TRAITEMENT DES ACTIONS DE MODÉRATION (POST) ---
if ($method === 'POST') {
    $action = $_POST['action'] ?? '';
    $comment_id = intval($_POST['comment_id'] ?? 0);
    $csrf_token = $_POST['csrf_token'] ?? '';

    // Vérification du jeton de sécurité CSRF
    if (empty($csrf_token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        echo json_encode(['success' => false, 'message' => 'Erreur de sécurité : Jeton CSRF invalide.']);
        exit;
    }

    if ($comment_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Identifiant de commentaire invalide.']);
        exit;
    }

    // Action A : Masquer l'avis
    if ($action === 'flagComment') {
        try {
            $stmt = $bdd->prepare("UPDATE comments SET is_deleted = 1 WHERE id = ?");
            $stmt->execute([$comment_id]);

            echo json_encode(['success' => true, 'message' => 'L\'avis a été masqué avec succès du showroom public.']);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur lors du masquage : ' . $e->getMessage()]);
            exit;
        }
    }

    // Action B : Supprimer définitivement
    if ($action === 'deleteComment') {
        try {
            $stmt = $bdd->prepare("DELETE FROM comments WHERE id = ?");
            $stmt->execute([$comment_id]);

            echo json_encode(['success' => true, 'message' => 'L\'avis a été définitivement supprimé de la base de données.']);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression : ' . $e->getMessage()]);
            exit;
        }
    }
}

echo json_encode(['success' => false, 'message' => 'Action ou méthode non reconnue par le serveur.']);
exit;
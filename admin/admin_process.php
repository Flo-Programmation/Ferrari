<?php
// admin/admin_process.php

header('Content-Type: application/json; charset=UTF-8');

error_reporting(E_ALL);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'path' => '/',
        'secure' => false, 
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// 2. Connexion à la base de données
require_once '../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];

// =========================================================================
// ACTION PUBLIQUE : ENVOI DU FORMULAIRE DE CONTACT (Doit être TOUT EN HAUT)
// =========================================================================
if ($method === 'POST' && isset($_POST['action']) && $_POST['action'] === 'contact_submit') {
    $csrf_token = $_POST['csrf_token'] ?? '';

    // Décommenter la ligne suivante si tu veux temporairement désactiver le CSRF pour tester si c'est lui qui bloque :
    // if (true) { 
    if (!empty($csrf_token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        
        $nom = trim($_POST['nom'] ?? '');
        $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
        $telephone = trim($_POST['telephone'] ?? '');
        $sujet = trim($_POST['sujet'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if (empty($nom) || !$email || empty($sujet) || empty($message)) {
            echo json_encode(['success' => false, 'message' => 'Veuillez remplir correctement tous les champs obligatoires.']);
            exit;
        }

        try {
            $stmt = $bdd->prepare("
                INSERT INTO contact_requests (nom, email, telephone, sujet, message, created_at, is_read) 
                VALUES (:nom, :email, :telephone, :sujet, :message, NOW(), 0)
            ");
            $stmt->execute([
                'nom' => $nom,
                'email' => $email,
                'telephone' => $telephone,
                'sujet' => $sujet,
                'message' => $message
            ]);

            echo json_encode(['success' => true, 'message' => 'Votre message a bien été transmis avec succès !']);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'envoi en BDD : ' . $e->getMessage()]);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur de sécurité : Jeton CSRF invalide ou expiré.']);
        exit;
    }
}

// =========================================================================
// CONTRÔLE D'ACCÈS STRICT POUR LE RESTE DES ACTIONS (Réservé aux Admins uniquement)
// =========================================================================
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

    // ACTION : Récupérer tous les avis
    if ($action === 'getAllComments') {
        try {
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

            echo json_encode(['success' => true, 'comments' => $comments], JSON_UNESCAPED_UNICODE);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la récupération des avis : ' . $e->getMessage()]);
            exit;
        }
    }

    // ACTION : Récupérer tous les messages de contact
    if ($action === 'getAllMessages') {
        try {
            $stmt = $bdd->query("SELECT id, nom, email, telephone, sujet, message, created_at, is_read FROM contact_requests ORDER BY id DESC");
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Sécurité contre les caractères spéciaux ou émojis qui corrompent le JSON
            echo json_encode(
                ['success' => true, 'messages' => $messages], 
                JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
            );
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la récupération : ' . $e->getMessage()]);
            exit;
        }
    }
}

// --- TRAITEMENT DES ACTIONS (POST) ---
if ($method === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';

    // Vérification globale du jeton de sécurité CSRF pour toutes les actions POST
    if (empty($csrf_token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        echo json_encode(['success' => false, 'message' => 'Erreur de sécurité : Jeton CSRF invalide.']);
        exit;
    }

    // =========================================================================
    // SECTION A : MODÉRATION DES AVIS
    // =========================================================================
    if ($action === 'flagComment' || $action === 'deleteComment') {
        $comment_id = intval($_POST['comment_id'] ?? 0);
        if ($comment_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Identifiant de commentaire invalide.']);
            exit;
        }

        // Action : Masquer l'avis
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

        // Action : Supprimer définitivement l'avis
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

    // ACTION : Marquer un message comme lu
    if ($action === 'markMessageAsRead') {
        $message_id = intval($_POST['message_id'] ?? 0);
        if ($message_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Identifiant de message invalide.']);
            exit;
        }
        try {
            $stmt = $bdd->prepare("UPDATE contact_requests SET is_read = 1 WHERE id = ?");
            $stmt->execute([$message_id]);
            echo json_encode(['success' => true, 'message' => 'Message marqué comme lu.']);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
            exit;
        }
    }

    // =========================================================================
    // SECTION B : GESTION DES VÉHICULES
    // =========================================================================
    
    // ACTION : Supprimer un modèle de véhicule
    if ($action === 'deleteVehicle') {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Identifiant de véhicule invalide.']);
            exit;
        }
        try {
            $stmt = $bdd->prepare("DELETE FROM voiture WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Le modèle a été supprimé avec succès !']);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression du modèle : ' . $e->getMessage()]);
            exit;
        }
    }

    // ACTION : Ajouter ou Modifier un modèle de véhicule
    if ($action === 'addVehicle' || $action === 'editVehicle') {
        $id = intval($_POST['id'] ?? 0);
        $modele = trim($_POST['modele'] ?? '');
        $moteur = trim($_POST['moteur'] ?? '');
        $puissance_ch = intval($_POST['puissance_ch'] ?? 0);
        $vitesse_max = intval($_POST['vitesse_max'] ?? 0);
        $annee = intval($_POST['annee'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $glb_url = trim($_POST['glb_url'] ?? '');
        $sound_url = trim($_POST['sound_url'] ?? '');

        if (empty($modele) || empty($moteur) || $puissance_ch <= 0 || $vitesse_max <= 0 || $annee <= 0 || empty($glb_url)) {
            echo json_encode(['success' => false, 'message' => 'Veuillez remplir correctement tous les champs obligatoires.']);
            exit;
        }

        if ($action === 'addVehicle') {
            try {
                $stmt = $bdd->prepare("
                    INSERT INTO voiture (modele, moteur, puissance_ch, vitesse_max, annee, description, glb_url, sound_url) 
                    VALUES (:modele, :moteur, :puissance_ch, :vitesse_max, :annee, :description, :glb_url, :sound_url)
                ");
                $stmt->execute([
                    'modele' => $modele,
                    'moteur' => $moteur,
                    'puissance_ch' => $puissance_ch,
                    'vitesse_max' => $vitesse_max,
                    'annee' => $annee,
                    'description' => !empty($description) ? $description : null,
                    'glb_url' => $glb_url,
                    'sound_url' => !empty($sound_url) ? $sound_url : null
                ]);
                echo json_encode(['success' => true, 'message' => 'Le modèle a été ajouté avec succès au showroom !']);
                exit;
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'ajout du modèle : ' . $e->getMessage()]);
                exit;
            }
        }

        if ($action === 'editVehicle') {
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Identifiant de véhicule manquant pour la modification.']);
                exit;
            }
            try {
                $stmt = $bdd->prepare("
                    UPDATE voiture 
                    SET modele = :modele, 
                        moteur = :moteur, 
                        puissance_ch = :puissance_ch, 
                        vitesse_max = :vitesse_max, 
                        annee = :annee, 
                        description = :description, 
                        glb_url = :glb_url, 
                        sound_url = :sound_url 
                    WHERE id = :id
                ");
                $stmt->execute([
                    'modele' => $modele,
                    'moteur' => $moteur,
                    'puissance_ch' => $puissance_ch,
                    'vitesse_max' => $vitesse_max,
                    'annee' => $annee,
                    'description' => !empty($description) ? $description : null,
                    'glb_url' => $glb_url,
                    'sound_url' => !empty($sound_url) ? $sound_url : null,
                    'id' => $id
                ]);
                echo json_encode(['success' => true, 'message' => 'Le modèle a été mis à jour avec succès !']);
                exit;
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Erreur lors de la modification du modèle : ' . $e->getMessage()]);
                exit;
            }
        }
    }

    // ==========================================
    // ACTION : MODIFIER UN UTILISATEUR (Correction de table -> users)
    // ==========================================
    if ($action === 'editUser') {
        $userId = $_POST['id'] ?? null;
        $prenom = trim($_POST['prenom'] ?? '');
        $nom = trim($_POST['nom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = trim($_POST['role'] ?? 'user');

        if (!$userId || empty($prenom) || empty($nom) || empty($email)) {
            echo json_encode(['success' => false, 'message' => 'Veuillez remplir tous les champs obligatoires.']);
            exit;
        }

        try {
            $stmt = $bdd->prepare("UPDATE users SET prenom = ?, nom = ?, email = ?, role = ? WHERE id = ?");
            $success = $stmt->execute([$prenom, $nom, $email, $role, $userId]);
            echo json_encode(['success' => $success, 'message' => $success ? 'Utilisateur modifié !' : 'Erreur lors de la mise à jour.']);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur SQL : ' . $e->getMessage()]);
            exit;
        }
    }

    // ==========================================
    // ACTION : SUPPRIMER UN UTILISATEUR (Correction de table -> users)
    // ==========================================
    if ($action === 'deleteUser') {
        $userId = $_POST['user_id'] ?? null;

        if (!$userId) {
            echo json_encode(['success' => false, 'message' => 'ID utilisateur manquant.']);
            exit;
        }

        if (isset($_SESSION['user']['id']) && $_SESSION['user']['id'] == $userId) {
            echo json_encode(['success' => false, 'message' => 'Vous ne pouvez pas détruire votre propre compte depuis le panel.']);
            exit;
        }

        try {
            $stmt = $bdd->prepare("DELETE FROM users WHERE id = ?");
            $success = $stmt->execute([$userId]);
            echo json_encode(['success' => $success, 'message' => $success ? 'Le compte a été rayé de la base de données.' : 'Erreur lors de la suppression.']);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur SQL : ' . $e->getMessage()]);
            exit;
        }
    }
}

echo json_encode(['success' => false, 'message' => 'Action ou méthode non reconnue par le serveur.']);
exit;
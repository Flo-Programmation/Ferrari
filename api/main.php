<?php
// api/main.php

require_once __DIR__ . '/../config/database.php'; 

/**
 * @var PDO $bdd
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (!isset($bdd)) {
    echo json_encode(['success' => false, 'message' => 'Base de données indisponible.'], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Vérifie si le jeton fourni correspond à celui stocké en session
 */
function verify_csrf_token(?string $token): bool {
    if (!$token || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Fonction utilitaire pour renvoyer une réponse JSON et arrêter le script
 */
function json_response(bool $success, string $message, array $extraData = []) {
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $extraData), JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
if (empty($action)) {
    json_response(false, 'Action non spécifiée.');
}

// -------------------------------------------------------------------------
// REQUÊTES GET
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    if ($action === 'get') {
        $vehicle_index = (int)($_GET['vehicle_index'] ?? 0);

        try {
            $stmt = $bdd->prepare("SELECT id, comment, rating, prenom, nom FROM comments WHERE vehicle_index = :vi AND is_deleted = 0 ORDER BY id DESC");
            $stmt->execute(['vi' => $vehicle_index]);
            $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            json_response(true, 'Avis chargés avec succès.', ['comments' => $comments]);
        } catch (Exception $e) {
            json_response(false, 'Erreur lors de la récupération des avis : ' . $e->getMessage());
        }
    }

    if ($action === 'getUserComments') {
        if (empty($_SESSION['user_id'])) {
            json_response(false, 'Utilisateur non connecté.');
        }

        try {
            $stmt = $bdd->prepare("SELECT vehicle_index, comment, rating FROM comments WHERE user_id = :uid ORDER BY id DESC");
            $stmt->execute(['uid' => $_SESSION['user_id']]);
            $my_comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            json_response(true, 'Activité chargée.', ['my_comments' => $my_comments]);
        } catch (Exception $e) {
            json_response(false, 'Erreur lors du chargement de l\'activité.');
        }
    }
}

// -------------------------------------------------------------------------
// REQUÊTES POST
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $csrf_received = $_POST['csrf_token'] ?? $_POST['csrf'] ?? '';

    if (!verify_csrf_token($csrf_received)) {
        json_response(false, 'Erreur de sécurité : Jeton CSRF invalide ou expiré.');
    }

    // --- CONNEXION ---
    if ($action === 'login') {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            json_response(false, 'Veuillez remplir tous les champs.');
        }

        try {
            $stmt = $bdd->prepare("SELECT id, prenom, nom, password, role FROM users WHERE email = :email");
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['prenom'] = $user['prenom'];
                $_SESSION['nom'] = $user['nom'];
                $_SESSION['role'] = $user['role'];

                // Régénération du token juste avant la réponse de succès
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                json_response(true, 'Connexion réussie.', [
                    'role' => $_SESSION['role'],
                    'csrf_token' => $_SESSION['csrf_token']
                ]);
            } else {
                json_response(false, 'Identifiants ou mot de passe incorrects.');
            }
        } catch (Exception $e) {
            json_response(false, 'Erreur technique lors de la connexion.');
        }
    }

    // --- INSCRIPTION ---
    // --- INSCRIPTION ---
    if ($action === 'register') {
        $prenom = trim($_POST['prenom'] ?? '');
        $nom = trim($_POST['nom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($prenom) || empty($nom) || empty($email) || empty($password)) {
            json_response(false, 'Tous les champs sont requis.');
        }

        // --- CORRECTION : VERIFICATION DU FORMAT DE L'EMAIL ---
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_response(false, 'L\'adresse email saisie n\'est pas valide (ex: nom@domaine.com).');
        }

        if (strlen($password) < 8) {
            json_response(false, 'Le mot de passe doit contenir au moins 8 caractères.');
        }

        try {
            $checkEmail = $bdd->prepare("SELECT id FROM users WHERE email = ?");
            $checkEmail->execute([$email]);
            if ($checkEmail->fetch()) {
                json_response(false, 'Cette adresse email est déjà enregistrée.');
            }

            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $bdd->prepare("INSERT INTO users (prenom, nom, email, password, role) VALUES (?, ?, ?, ?, 'member')");
            $stmt->execute([$prenom, $nom, $email, $password_hash]);

            // Récupération de l'ID généré
            $new_user_id = $bdd->lastInsertId();

            // Ouverture immédiate de la session de l'utilisateur
            $_SESSION['user_id'] = $new_user_id;
            $_SESSION['prenom'] = $prenom;
            $_SESSION['nom'] = $nom;
            $_SESSION['role'] = 'member';
            
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            // CORRECTION : On renvoie les infos de session au JS pour qu'il puisse adapter l'interface directement
            json_response(true, 'Compte créé avec succès !', [
                'role' => 'member',
                'prenom' => $prenom,
                'nom' => $nom,
                'csrf_token' => $_SESSION['csrf_token']
            ]);
        } catch (Exception $e) {
            json_response(false, 'Erreur lors de la création de votre compte.');
        }
    }

    // --- AJOUTER UN AVIS ---
    if ($action === 'add') {
        if (empty($_SESSION['user_id'])) {
            json_response(false, 'Vous devez être connecté pour laisser un avis.');
        }

        $vehicle_index = (int)($_POST['vehicle_index'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');
        $rating = min(5, max(1, (int)($_POST['rating'] ?? 5)));

        if (empty($comment)) {
            json_response(false, 'Veuillez écrire un commentaire.');
        }

        if (mb_strlen($comment) > 2000) {
            json_response(false, 'Le commentaire est trop long.');
        }

        try {
            $stmt = $bdd->prepare("INSERT INTO comments (user_id, vehicle_index, rating, comment, prenom, nom, is_deleted) VALUES (?, ?, ?, ?, ?, ?, 0)");
            $stmt->execute([
                $_SESSION['user_id'], 
                $vehicle_index, 
                $rating, 
                $comment, 
                $_SESSION['prenom'] ?? 'Anonyme', 
                $_SESSION['nom'] ?? ''
            ]);

            json_response(true, 'Votre avis a été enregistré avec succès !');
        } catch (Exception $e) {
            json_response(false, 'Impossible d\'enregistrer votre avis pour le moment.');
        }
    }

    // --- MODÉRATION ADMIN ---
    if ($action === 'report_or_delete') {
        if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            json_response(false, 'Action non autorisée.');
        }

        $id = (int)($_POST['id'] ?? 0);

        try {
            $stmt = $bdd->prepare("UPDATE comments SET is_deleted = 1 WHERE id = :id");
            $stmt->execute(['id' => $id]);

            json_response(true, 'L\'avis a bien été supprimé par l\'administrateur.');
        } catch (Exception $e) {
            json_response(false, 'Erreur lors de la modération de l\'avis.');
        }
    }

    // --- DÉCONNEXION ---
    if ($action === 'logout') {
        $_SESSION = [];
        session_destroy();
        json_response(true, 'Déconnexion réussie.');
    }
}

json_response(false, 'Méthode non supportée.');
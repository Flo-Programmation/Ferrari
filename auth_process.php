<?php
// auth_process.php

error_reporting(E_ALL);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'path' => '/',
        'secure' => false, // Passez à true si vous utilisez du HTTPS
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
require_once 'config/database.php'; 

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
// REQUÊTES GET
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    // Récupérer les avis d'un véhicule spécifique
    if ($action === 'get') {
        $vehicle_index = (int)($_GET['vehicle_index'] ?? 0);
        try {
            $stmt = $bdd->prepare("
                SELECT c.id, c.comment, c.rating, u.prenom, u.nom 
                FROM comments c
                INNER JOIN users u ON c.user_id = u.id
                WHERE c.vehicle_index = :vi AND c.is_deleted = 0 
                ORDER BY c.id DESC
            ");
            $stmt->execute(['vi' => $vehicle_index]);
            $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'comments' => $comments], JSON_UNESCAPED_UNICODE);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur de chargement.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // Récupérer l'historique des avis de l'utilisateur connecté
    if ($action === 'getUserComments') {
        if (empty($_SESSION['user']['id'])) {
            echo json_encode(['success' => false, 'message' => 'Utilisateur non connecté.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {
            $stmt = $bdd->prepare("
                SELECT id, vehicle_index, comment, rating 
                FROM comments 
                WHERE user_id = :uid AND is_deleted = 0 
                ORDER BY id DESC
            ");
            $stmt->execute(['uid' => $_SESSION['user']['id']]);
            $my_comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'my_comments' => $my_comments], JSON_UNESCAPED_UNICODE);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur d\'activité.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}

// -------------------------------------------------------------------------
// REQUÊTES POST
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf_received = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf_received)) {
        echo json_encode(['success' => false, 'message' => 'Erreur de sécurité : Jeton CSRF invalide.']);
        exit;
    }

    // --- INSCRIPTION ---
    if ($action === 'register') {
        $prenom = trim($_POST['prenom'] ?? '');
        $nom = trim($_POST['nom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($prenom) || empty($nom) || empty($email) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Tous les champs sont requis.']);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Le format de l\'adresse email est invalide.']);
            exit;
        }

        $emailParts = explode('@', $email);
        $domain = strtolower(end($emailParts));
        $allowedDomains = ['gmail.com', 'outlook.fr', 'outlook.com'];

        if (!in_array($domain, $allowedDomains)) {
            echo json_encode(['success' => false, 'message' => 'Seules les adresses @gmail.com, @outlook.fr et @outlook.com sont autorisées.']);
            exit;
        }

        if (strlen($password) < 8) {
            echo json_encode(['success' => false, 'message' => 'Le mot de passe doit contenir au moins 8 caractères.']);
            exit;
        }

        try {
            $stmtCheck = $bdd->prepare("SELECT id FROM users WHERE email = :email");
            $stmtCheck->execute(['email' => $email]);
            if ($stmtCheck->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Cette adresse email est déjà utilisée.']);
                exit;
            }

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmtInsert = $bdd->prepare("INSERT INTO users (prenom, nom, email, password, role) VALUES (:prenom, :nom, :email, :password, 'member')");
            $stmtInsert->execute(['prenom' => $prenom, 'nom' => $nom, 'email' => $email, 'password' => $hashedPassword]);

            $_SESSION['user'] = [
                'id' => $bdd->lastInsertId(), 
                'prenom' => $prenom, 
                'nom' => $nom, 
                'email' => $email, 
                'role' => 'member'
            ];
            
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            echo json_encode([
                'success' => true, 
                'message' => 'Compte créé avec succès !', 
                'csrf_token' => $_SESSION['csrf_token'], 
                'role' => 'member',
                'prenom' => $prenom
            ], JSON_UNESCAPED_UNICODE);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'inscription.']);
            exit;
        }
    }

    // --- CONNEXION ---
    elseif ($action === 'login') {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        try {
            $stmt = $bdd->prepare("SELECT id, prenom, nom, email, password, role FROM users WHERE email = :email");
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                $userRole = isset($user['role']) ? strtolower(trim($user['role'])) : 'member';
                
                $_SESSION['user'] = [
                    'id' => $user['id'], 
                    'prenom' => $user['prenom'], 
                    'nom' => $user['nom'], 
                    'email' => $user['email'], 
                    'role' => $userRole
                ];
                
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                echo json_encode([
                    'success' => true, 
                    'message' => 'Connecté !', 
                    'role' => $userRole, 
                    'csrf_token' => $_SESSION['csrf_token'],
                    'prenom' => $user['prenom']
                ], JSON_UNESCAPED_UNICODE);
                exit;
            } else {
                echo json_encode(['success' => false, 'message' => 'Identifiants ou mot de passe incorrects.']);
                exit;
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la connexion.']);
            exit;
        }
    }

    // --- FORMULAIRE DE CONTACT ---
    elseif ($action === 'contact') {
        $nom = trim($_POST['nom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $sujet = trim($_POST['sujet'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if (empty($nom) || empty($email) || empty($sujet) || empty($message)) {
            echo json_encode(['success' => false, 'message' => 'Veuillez remplir tous les champs obligatoires.']);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'L\'adresse email saisie est invalide.']);
            exit;
        }

        try {
            $stmt = $bdd->prepare("
                INSERT INTO contact_requests (nom, email, telephone, sujet, message) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$nom, $email, !empty($telephone) ? $telephone : null, $sujet, $message]);

            $to = $email;
            $email_subject = "Accusé de réception : " . $sujet;
            $headers = "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\nFrom: Showcase <no-reply@votre-domaine.com>\r\n";

            $email_body = "<html><body>
                <p>Bonjour <strong>" . htmlspecialchars($nom) . "</strong>,</p>
                <p>Nous vous confirmons la bonne réception de votre message.</p>
                <p><em>\"" . nl2br(htmlspecialchars($message)) . "\"</em></p>
            </body></html>";

            @mail($to, $email_subject, $email_body, $headers);

            echo json_encode(['success' => true, 'message' => 'Votre message a été transmis !'], JSON_UNESCAPED_UNICODE);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur technique lors de l\'envoi du message.']);
            exit;
        }
    }

    // --- TOUTES LES ACTIONS SUIVANTES NÉCESSITENT D'ÊTRE CONNECTÉ ---
    if (empty($_SESSION['user']['id'])) {
        echo json_encode(['success' => false, 'message' => 'Action non autorisée (non connecté).']);
        exit;
    }

    // --- AJOUTER UN AVIS ---
    if ($action === 'add') {
        $vehicle_index = (int)($_POST['vehicle_index'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');
        $rating = min(5, max(1, (int)($_POST['rating'] ?? 5)));

        if (empty($comment)) {
            echo json_encode(['success' => false, 'message' => 'Commentaire vide.']);
            exit;
        }

        try {
            $stmt = $bdd->prepare("INSERT INTO comments (user_id, vehicle_index, rating, comment, is_deleted) VALUES (?, ?, ?, ?, 0)");
            $stmt->execute([$_SESSION['user']['id'], $vehicle_index, $rating, $comment]);
            echo json_encode(['success' => true, 'message' => 'Votre avis a été enregistré !']);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'enregistrement de l\'avis.']);
            exit;
        }
    }

    // --- MODIFIER UN AVIS ---
    elseif ($action === 'edit') {
        $comment_id = (int)($_POST['comment_id'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');
        $rating = min(5, max(1, (int)($_POST['rating'] ?? 5)));

        if (empty($comment)) {
            echo json_encode(['success' => false, 'message' => 'Le commentaire ne peut pas être vide.']);
            exit;
        }

        try {
            $stmt = $bdd->prepare("UPDATE comments SET comment = ?, rating = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$comment, $rating, $comment_id, $_SESSION['user']['id']]);
            
            echo json_encode(['success' => true, 'message' => 'Votre avis a été modifié avec succès !']);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la modification.']);
            exit;
        }
    }

    // --- SUPPRIMER UN AVIS ---
    elseif ($action === 'delete') {
        $comment_id = (int)($_POST['comment_id'] ?? 0);

        try {
            $stmt = $bdd->prepare("UPDATE comments SET is_deleted = 1 WHERE id = ? AND user_id = ?");
            $stmt->execute([$comment_id, $_SESSION['user']['id']]);
            
            echo json_encode(['success' => true, 'message' => 'Votre avis a été supprimé.']);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression.']);
            exit;
        }
    }

    // --- DÉCONNEXION ---
    elseif ($action === 'logout') {
        unset($_SESSION['user']);
        session_destroy();
        echo json_encode(['success' => true, 'message' => 'Déconnecté.']);
        exit;
    }
}

echo json_encode($response);
exit;
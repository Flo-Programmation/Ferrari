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
            echo json_encode(['success' => false, 'message' => 'Erreur de chargement : ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

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
            echo json_encode(['success' => false, 'message' => 'Erreur d\'activité : ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
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

    // --- INSCRIPTION & CONNEXION ---
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

    // --- FORMULAIRE DE CONTACT (AVEC ACCUSÉ DE RÉCEPTION) ---
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
            // 1. Insertion en Base de Données
            $stmt = $bdd->prepare("
                INSERT INTO contact_requests (nom, email, telephone, sujet, message) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$nom, $email, !empty($telephone) ? $telephone : null, $sujet, $message]);

            // 2. ENVOI DU MAIL AUTOMATIQUE AU CLIENT
            $to = $email;
            $email_subject = "Accusé de réception : " . $sujet;
            
            // Entêtes pour envoyer un mail propre au format HTML
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: Scuderia Ferrari Exposition <no-reply@votre-domaine.com>" . "\r\n";

            // Corps du mail au design élégant (Dark mode type Ferrari)
            $email_body = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; background-color: #0b0b0b; color: #ffffff; padding: 20px; }
                    .container { max-width: 600px; background-color: #141414; border: 1px solid #222; padding: 30px; border-radius: 8px; margin: 0 auto; }
                    .header { border-bottom: 2px solid #ff2828; padding-bottom: 15px; margin-bottom: 20px; }
                    h2 { color: #ff2828; margin: 0; }
                    .content { font-size: 14px; line-height: 1.6; color: #ccccce; }
                    .recap { background-color: #1c1c1c; padding: 15px; border-radius: 5px; margin-top: 20px; border-left: 3px solid #ffaa00; }
                    .footer { font-size: 11px; color: #555; text-align: center; margin-top: 30px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>Scuderia Ferrari - Showroom</h2>
                    </div>
                    <div class='content'>
                        <p>Bonjour <strong>" . htmlspecialchars($nom) . "</strong>,</p>
                        <p>Nous vous confirmons la bonne réception de votre message. Notre équipe d'ingénieurs et de conseillers va l'étudier avec la plus grande attention.</p>
                        <p>Vous recevrez une réponse personnalisée sous un délai de 24 heures.</p>
                        
                        <div class='recap'>
                            <strong>Rappel de votre demande :</strong><br>
                            <span style='color:#ffaa00;'>Sujet :</span> " . htmlspecialchars($sujet) . "<br>
                            <span style='color:#ffaa00;'>Message :</span><br>
                            <em>\"" . nl2br(htmlspecialchars($message)) . "\"</em>
                        </div>
                    </div>
                    <div class='footer'>
                        Ceci est un message automatique, merci de ne pas y répondre directement.<br>
                        © " . date('Y') . " Ferrari Vitrine Showcase. Tous droits réservés.
                    </div>
                </div>
            </body>
            </html>";

            // Envoi effectif du mail (ne bloque pas le script si l'envoi échoue)
            @mail($to, $email_subject, $email_body, $headers);

            // 3. Réponse AJAX retournée au JavaScript
            echo json_encode(['success' => true, 'message' => 'Votre message a été transmis ! Un e-mail de confirmation vous a été envoyé.'], JSON_UNESCAPED_UNICODE);
            exit;

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur technique lors de l\'envoi du message : ' . $e->getMessage()]);
            exit;
        }
    }

    // --- AJOUTER UN AVIS ---
    elseif ($action === 'add') {
        if (empty($_SESSION['user']['id'])) {
            echo json_encode(['success' => false, 'message' => 'Non connecté.']);
            exit;
        }

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
            echo json_encode(['success' => false, 'message' => 'Erreur SQL : ' . $e->getMessage()]);
            exit;
        }
    }

    // --- MODIFIER UN AVIS ---
    elseif ($action === 'edit') {
        if (empty($_SESSION['user']['id'])) {
            echo json_encode(['success' => false, 'message' => 'Action non autorisée.']);
            exit;
        }

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
        if (empty($_SESSION['user']['id'])) {
            echo json_encode(['success' => false, 'message' => 'Action non autorisée.']);
            exit;
        }

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
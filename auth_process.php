<?php
// auth_process.php (À la racine du projet)

error_reporting(E_ALL);
ini_set('display_errors', 0); // Empêche les avertissements de corrompre le JSON

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'path' => '/',
        'secure' => false, // Mets à true si tu es en https://
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // -------------------------------------------------------------------------
    // ACTION : INSCRIPTION
    // -------------------------------------------------------------------------
    if ($action === 'register') {
        $prenom   = trim($_POST['prenom'] ?? '');
        $nom      = trim($_POST['nom'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($prenom) || empty($nom) || empty($email) || empty($password)) {
            $response['message'] = "Tous les champs sont obligatoires.";
            echo json_encode($response);
            exit;
        }

        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $response['message'] = "Cette adresse email est déjà utilisée.";
                echo json_encode($response);
                exit;
            }

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $avatarUrl = "https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&q=80&w=150";

            $insert = $pdo->prepare("INSERT INTO users (prenom, nom, email, password, avatar_url, role) VALUES (?, ?, ?, ?, ?, 'user')");
            $insert->execute([$prenom, $nom, $email, $hashedPassword, $avatarUrl]);

            $_SESSION['user'] = [
                'id'         => $pdo->lastInsertId(),
                'prenom'     => $prenom,
                'nom'        => $nom,
                'email'      => $email,
                'avatar_url' => $avatarUrl,
                'role'       => 'user'
            ];

            $response['success'] = true;
            $response['message'] = "Compte créé avec succès !";

        } catch (PDOException $e) {
            $response['message'] = "Erreur SQL lors de l'enregistrement : " . $e->getMessage();
        }
    }

    // -------------------------------------------------------------------------
    // ACTION : CONNEXION
    // -------------------------------------------------------------------------
    elseif ($action === 'login') {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        try {
            $stmt = $pdo->prepare("SELECT id, prenom, nom, email, password, avatar_url, role FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                
                // Nettoyage strict du rôle (minuscules et sans espaces cachés)
                $userRole = isset($user['role']) ? strtolower(trim($user['role'])) : 'user';

                $_SESSION['user'] = [
                    'id'         => $user['id'],
                    'prenom'     => $user['prenom'],
                    'nom'        => $user['nom'],
                    'email'      => $user['email'],
                    'avatar_url' => $user['avatar_url'] ?? 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&q=80&w=150',
                    'role'       => $userRole
                ];
                
                $response['success'] = true;
                $response['message'] = "Connexion réussie !";
                $response['role']    = $userRole; // Transmis au JS pour redirection

            } else {
                $response['message'] = "Identifiants ou mot de passe incorrects.";
            }
        } catch (PDOException $e) {
            $response['message'] = "Erreur SQL lors de la connexion : " . $e->getMessage();
        }
    }

    // -------------------------------------------------------------------------
    // ACTION : DÉCONNEXION
    // -------------------------------------------------------------------------
    elseif ($action === 'logout') {
        unset($_SESSION['user']);
        session_destroy();
        $response['success'] = true;
        $response['message'] = "Déconnexion réussie.";
    }
}

echo json_encode($response);
exit;
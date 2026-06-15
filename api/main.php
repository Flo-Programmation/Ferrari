<?php
// api/main.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
$action = $_GET['action'] ?? '';

switch ($action) {
    // ─── INSCRIPTION ───────────────────────────────────────────
    case 'register':
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$nom || !$prenom || !$email || !$password) {
            echo json_encode(['success' => false, 'message' => 'Tous les champs sont obligatoires.']);
            exit;
        }

        // Vérification email existant
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Cet email est déjà enregistré.']);
            exit;
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $avatar_default = 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png'; // Avatar par défaut

        $stmt = $pdo->prepare("INSERT INTO users (nom, prenom, email, password, avatar_url) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$nom, $prenom, $email, $hashedPassword, $avatar_default])) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la création du compte.']);
        }
        break;

    // ─── CONNEXION ──────────────────────────────────────────────
    case 'login':
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user'] = [
                'id' => $user['id'],
                'nom' => $user['nom'],
                'prenom' => $user['prenom'],
                'email' => $user['email'],
                'avatar_url' => $user['avatar_url'] ?: 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png',
                'role' => $user['role']
            ];
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Identifiants ou mot de passe incorrects.']);
        }
        break;

    // ─── DÉCONNEXION ────────────────────────────────────────────
    case 'logout':
        session_destroy();
        echo json_encode(['success' => true]);
        break;

    // ─── VOS AUTRES LOGIQUES (POST/GET/DELETE Commentaires) ──────
    /* case 'get_commentaires':
        ... votre code existant ...
        break;
    */

    default:
        echo json_encode(['success' => false, 'message' => 'Action inconnue.']);
        break;
}
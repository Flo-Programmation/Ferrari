<?php
// ============================================
// CONFIGURATION SESSION SÉCURISÉE
// ============================================
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);       // Désactiver si pas de HTTPS en dev
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.gc_maxlifetime', 3600);   // 1h max d'inactivité
session_start();

header('Content-Type: application/json');

// Inclusion de la connexion à la base de données
require_once '../config/database.php';

// ============================================
// RATE LIMITING (stocké en session + fichier pour persistance)
// ============================================
function check_rate_limit(string $action_name, int $max_attempts = 5, int $window = 300): bool {
    $key = 'rate_limit_' . $action_name . '_' . $_SERVER['REMOTE_ADDR'];
    $file = sys_get_temp_dir() . '/' . md5($key) . '.lock';
    $now = time();

    $attempts = [];
    if (file_exists($file)) {
        $data = file_get_contents($file);
        $attempts = json_decode($data, true) ?? [];
        // Nettoyer les tentatives expirées
        $attempts = array_filter($attempts, fn($t) => ($now - $t) < $window);
    }

    if (count($attempts) >= $max_attempts) {
        return false; // Bloqué
    }

    $attempts[] = $now;
    file_put_contents($file, json_encode($attempts), LOCK_EX);
    return true;
}

// ============================================
// GÉNÉRATION / VÉRIFICATION CSRF
// ============================================
function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ============================================
// FONCTIONS UTILITAIRES DE SÉCURITÉ
// ============================================
function sanitize_output(string $data): string {
    return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function validate_email(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function json_response(bool $success, string $message, array $extra = []): void {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

// ============================================
// DÉTERMINATION DE L'ACTION (GET for reading, POST for writing)
// ============================================
$method = $_SERVER['REQUEST_METHOD'];
$action = '';

if ($method === 'POST') {
    $action = isset($_POST['action']) ? trim($_POST['action']) : '';
} elseif ($method === 'GET') {
    $action = isset($_GET['action']) ? trim($_GET['action']) : '';
} else {
    json_response(false, 'Méthode HTTP non autorisée.');
}

// ============================================
// SWITCH PRINCIPAL
// ============================================
switch ($action) {

    // —————— RÉCUPÉRER LES COMMENTAIRES D'UN VÉHICULE (GET) ——————
    case 'get':
        if ($method !== 'GET') {
            json_response(false, 'Méthode non autorisée pour cette action.');
        }

        $vehicle_index = isset($_GET['vehicle_index']) ? (int)$_GET['vehicle_index'] : -1;

        if ($vehicle_index < 0) {
            json_response(false, 'Index de véhicule invalide.');
        }

        try {
            $stmt = $pdo->prepare("
                SELECT c.id, c.vehicle_index, c.rating, c.comment, c.created_at,
                       u.prenom, u.nom
                FROM comments c
                JOIN users u ON c.user_id = u.id
                WHERE c.vehicle_index = :vehicle_index
                ORDER BY c.created_at DESC
            ");
            $stmt->execute(['vehicle_index' => $vehicle_index]);
            $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Échappement XSS de chaque commentaire avant envoi
            foreach ($comments as &$c) {
                $c['comment'] = sanitize_output($c['comment']);
                $c['prenom']  = sanitize_output($c['prenom']);
                $c['nom']     = sanitize_output($c['nom']);
            }
            unset($c);

            json_response(true, 'Commentaires récupérés.', ['comments' => $comments]);
        } catch (PDOException $e) {
            // Log serveur de l'erreur réelle sans la divulguer
            error_log('Erreur get comments : ' . $e->getMessage());
            json_response(false, 'Erreur lors de la lecture des avis.');
        }
        break;

    // —————— AJOUTER UN AVIS (POST, connecté, CSRF) ——————
    case 'add':
        if ($method !== 'POST') {
            json_response(false, 'Méthode non autorisée pour cette action.');
        }

        // Vérification connexion
        if (!isset($_SESSION['user'])) {
            json_response(false, 'Vous devez être connecté pour laisser un avis.');
        }

        // Vérification CSRF
        $csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
        if (!verify_csrf_token($csrf_token)) {
            json_response(false, 'Token CSRF invalide. Rechargez la page.');
        }

        // Rate limiting
        if (!check_rate_limit('add_comment', 10, 60)) {
            json_response(false, 'Trop de commentaires. Réessayez dans une minute.');
        }

        $user_id = (int)$_SESSION['user']['id'];
        $vehicle_index = isset($_POST['vehicle_index']) ? (int)$_POST['vehicle_index'] : -1;
        $rating = isset($_POST['rating']) ? min(5, max(1, (int)$_POST['rating'])) : 5;
        $comment = isset($_POST['comment']) ? trim(strip_tags($_POST['comment'])) : '';

        if ($vehicle_index < 0 || empty($comment)) {
            json_response(false, 'Veuillez rédiger un commentaire valide.');
        }

        // Limiter la longueur du commentaire
        if (mb_strlen($comment) > 2000) {
            json_response(false, 'Le commentaire ne peut pas dépasser 2000 caractères.');
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO comments (user_id, vehicle_index, rating, comment, created_at)
                VALUES (:user_id, :vehicle_index, :rating, :comment, NOW())
            ");
            $stmt->execute([
                'user_id'        => $user_id,
                'vehicle_index'  => $vehicle_index,
                'rating'         => $rating,
                'comment'        => $comment
            ]);

            json_response(true, 'Votre avis a été enregistré !');
        } catch (PDOException $e) {
            error_log('Erreur add comment : ' . $e->getMessage());
            json_response(false, 'Erreur de base de données lors du traitement de l\'avis.');
        }
        break;

    // —————— CONNEXION UTILISATEUR (POST) ——————
    case 'login':
        if ($method !== 'POST') {
            json_response(false, 'Méthode non autorisée pour cette action.');
        }

        // Rate limiting
        if (!check_rate_limit('login', 5, 300)) {
            json_response(false, 'Trop de tentatives de connexion. Réessayez dans 5 minutes.');
        }

        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';

        if (empty($email) || empty($password)) {
            json_response(false, 'Veuillez remplir tous les champs.');
        }

        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                // Régénérer l'ID de session pour éviter la fixation
                session_regenerate_id(true);

                $_SESSION['user'] = [
                    'id'     => (int)$user['id'],
                    'prenom' => $user['prenom'],
                    'nom'    => $user['nom'],
                    'email'  => $user['email']
                ];

                json_response(true, 'Connexion réussie.', [
                    'csrf_token' => generate_csrf_token()
                ]);
            } else {
                json_response(false, 'Identifiants incorrects.');
            }
        } catch (PDOException $e) {
            error_log('Erreur login : ' . $e->getMessage());
            json_response(false, 'Erreur système lors de l\'authentification.');
        }
        break;

    // —————— INSCRIPTION UTILISATEUR (POST) ——————
    case 'register':
        if ($method !== 'POST') {
            json_response(false, 'Méthode non autorisée pour cette action.');
        }

        // Rate limiting
        if (!check_rate_limit('register', 3, 3600)) {
            json_response(false, 'Trop de comptes créés depuis cette adresse IP. Réessayez dans une heure.');
        }

        $prenom   = isset($_POST['prenom']) ? trim(strip_tags($_POST['prenom'])) : '';
        $nom      = isset($_POST['nom']) ? trim(strip_tags($_POST['nom'])) : '';
        $email    = isset($_POST['email']) ? trim($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';

        if (empty($prenom) || empty($nom) || empty($email) || empty($password)) {
            json_response(false, 'Tous les champs requis doivent être complétés.');
        }

        // Validation email
        if (!validate_email($email)) {
            json_response(false, 'Format d\'email invalide.');
        }

        // Force du mot de passe (minimum 8 caractères)
        if (mb_strlen($password) < 8) {
            json_response(false, 'Le mot de passe doit contenir au moins 8 caractères.');
        }

        // Limiter la taille des champs
        if (mb_strlen($prenom) > 50 || mb_strlen($nom) > 50) {
            json_response(false, 'Prénom et nom limités à 50 caractères.');
        }
        if (mb_strlen($email) > 255) {
            json_response(false, 'Email trop long.');
        }

        try {
            // Vérification unicité email
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
            $stmt->execute(['email' => $email]);
            if ($stmt->fetch()) {
                json_response(false, 'Cette adresse email est déjà utilisée.');
            }

            // Hachage sécurisé avec Argon2id si disponible
            $hashedPassword = password_hash($password, PASSWORD_ARGON2ID);

            $insert = $pdo->prepare("
                INSERT INTO users (prenom, nom, email, password)
                VALUES (:prenom, :nom, :email, :password)
            ");
            $insert->execute([
                'prenom'   => $prenom,
                'nom'      => $nom,
                'email'    => $email,
                'password' => $hashedPassword
            ]);

            // Auto-connexion
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id'     => (int)$pdo->lastInsertId(),
                'prenom' => $prenom,
                'nom'    => $nom,
                'email'  => $email
            ];

            json_response(true, 'Compte créé avec succès !', [
                'csrf_token' => generate_csrf_token()
            ]);
        } catch (PDOException $e) {
            error_log('Erreur register : ' . $e->getMessage());
            json_response(false, 'Impossible de créer le compte. Veuillez réessayer.');
        }
        break;

    // —————— DÉCONNEXION (POST) ——————
    case 'logout':
        if ($method !== 'POST') {
            json_response(false, 'Méthode non autorisée pour cette action.');
        }

        // Nettoyage complet de la session
        $_SESSION = [];

        // Supprimer le cookie de session
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_regenerate_id(true);
        session_destroy();

        json_response(true, 'Déconnexion réussie.');
        break;

    // —————— RÉCUPÉRER LE TOKEN CSRF (GET, connecté) ——————
    case 'csrf':
        if ($method !== 'GET') {
            json_response(false, 'Méthode non autorisée.');
        }
        if (!isset($_SESSION['user'])) {
            json_response(false, 'Non connecté.');
        }
        json_response(true, 'Token généré.', ['csrf_token' => generate_csrf_token()]);
        break;

    default:
        json_response(false, 'Action inconnue.');
        break;
}
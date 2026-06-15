<?php
session_start();
header('Content-Type: application/json');

// Inclusion de la connexion à la base de données
require_once '../config/database.php'; 

// Détermination de l'action demandée (GET ou POST)
$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

switch ($action) {

    // —————— RÉCUPÉRER LES COMMENTAIRES D'UN VÉHICULE ——————
    case 'get':
        $vehicle_index = isset($_GET['vehicle_index']) ? (int)$_GET['vehicle_index'] : -1;

        if ($vehicle_index < 0) {
            echo json_encode(['success' => false, 'message' => 'Index de véhicule invalide.']);
            exit;
        }

        try {
            // Jointure pour récupérer le prénom et le nom de l'utilisateur relié au commentaire
            $stmt = $pdo->prepare("
                SELECT c.*, u.prenom, u.nom 
                FROM comments c 
                JOIN users u ON c.user_id = u.id 
                WHERE c.vehicle_index = :vehicle_index 
                ORDER BY c.created_at DESC
            ");
            $stmt->execute(['vehicle_index' => $vehicle_index]);
            $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'comments' => $comments]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la lecture des avis.']);
        }
        break;

    // —————— AJOUTER UN AVIS (RÉSERVÉ AUX MEMBRES CONNECTÉS) ——————
    case 'add':
        if (!isset($_SESSION['user'])) {
            echo json_encode(['success' => false, 'message' => 'Vous devez être connecté pour laisser un avis.']);
            exit;
        }

        $user_id = $_SESSION['user']['id'];
        
        // CORRECTION MAJEURE ICI : Remplacement de $POST par $_POST
        $vehicle_index = isset($_POST['vehicle_index']) ? (int)$_POST['vehicle_index'] : -1;
        $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 5;
        $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

        if ($vehicle_index < 0 || empty($comment)) {
            echo json_encode(['success' => false, 'message' => 'Veuillez rédiger un commentaire valide.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO comments (user_id, vehicle_index, rating, comment, created_at) 
                VALUES (:user_id, :vehicle_index, :rating, :comment, NOW())
            ");
            $stmt->execute([
                'user_id' => $user_id,
                'vehicle_index' => $vehicle_index,
                'rating' => $rating,
                'comment' => $comment
            ]);

            echo json_encode(['success' => true, 'message' => 'Votre avis de télémétrie a été enregistré !']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur de base de données lors du traitement de l\'avis.']);
        }
        break;

    // —————— CONNEXION UTILSATEUR ——————
    case 'login':
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';

        if (empty($email) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Veuillez remplir tous les champs.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'prenom' => $user['prenom'],
                    'nom' => $user['nom'],
                    'email' => $user['email']
                ];
                echo json_encode(['success' => true, 'message' => 'Connexion réussie.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Identifiants incorrects.']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur système lors de l\'authentification.']);
        }
        break;

    // —————— INSCRIPTION UTILISATEUR ——————
    case 'register':
        $prenom = isset($_POST['prenom']) ? trim($_POST['prenom']) : '';
        $nom = isset($_POST['nom']) ? trim($_POST['nom']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';

        if (empty($prenom) || empty($nom) || empty($email) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Tous les champs requis doivent être complétés.']);
            exit;
        }

        try {
            // Vérification de l'unicité de l'email
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
            $stmt->execute(['email' => $email]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Cette adresse email est déjà utilisée.']);
                exit;
            }

            // Hachage sécurisé du mot de passe
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $insert = $pdo->prepare("
                INSERT INTO users (prenom, nom, email, password) 
                VALUES (:prenom, :nom, :email, :password)
            ");
            $insert->execute([
                'prenom' => $prenom,
                'nom' => $nom,
                'email' => $email,
                'password' => $hashedPassword
            ]);

            // Auto-connexion immédiate après la création
            $_SESSION['user'] = [
                'id' => $pdo->lastInsertId(),
                'prenom' => $prenom,
                'nom' => $nom,
                'email' => $email
            ];

            echo json_encode(['success' => true, 'message' => 'Compte créé avec succès !']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Impossible de générer le profil membre.']);
        }
        break;

    // —————— DÉCONNEXION ——————
    case 'logout':
        session_destroy();
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Action inconnue.']);
        break;
}
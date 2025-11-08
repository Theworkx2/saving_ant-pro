<?php
session_start();
require_once __DIR__ . '/db.php';

class Auth {
    private PDO $db;
    private ?array $user = null;
    
    public function __construct() {
        $this->db = getPDO();
        if (isset($_SESSION['user_id'])) {
            $this->loadUser($_SESSION['user_id']);
        }
    }

    // Get PDO instance
    public function getPdo(): PDO {
        return $this->db;
    }

    // Load user data including roles
    private function loadUser(int $userId): void {
        $stmt = $this->db->prepare("
            SELECT u.*, GROUP_CONCAT(r.name) as roles 
            FROM users u 
            LEFT JOIN user_roles ur ON u.id = ur.user_id 
            LEFT JOIN roles r ON ur.role_id = r.id 
            WHERE u.id = ? AND u.is_active = 1 
            GROUP BY u.id
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if ($user) {
            $user['roles'] = $user['roles'] ? explode(',', $user['roles']) : [];
            $this->user = $user;
        }
    }

    // Register a new user
    public function register(array $data): array {
        try {
            // Validate input
            $errors = $this->validateRegistration($data);
            if (!empty($errors)) {
                return ['success' => false, 'errors' => $errors];
            }

            // Start transaction
            $this->db->beginTransaction();

            // Insert user
            $stmt = $this->db->prepare("
                INSERT INTO users (username, email, password_hash, full_name)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['username'],
                $data['email'],
                password_hash($data['password'], PASSWORD_DEFAULT),
                $data['full_name']
            ]);
            $userId = $this->db->lastInsertId();

            // Assign selected role or default to 'user'
            $role = $data['role'] ?? 'user';
            $stmt = $this->db->prepare("
                INSERT INTO user_roles (user_id, role_id)
                SELECT ?, id FROM roles WHERE name = ?
            ");
            $stmt->execute([$userId, $role]);

            $this->db->commit();
            return ['success' => true, 'message' => 'Registration successful'];

        } catch (PDOException $e) {
            $this->db->rollBack();
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                if (strpos($e->getMessage(), 'username') !== false) {
                    return ['success' => false, 'errors' => ['username' => 'Username already taken']];
                }
                if (strpos($e->getMessage(), 'email') !== false) {
                    return ['success' => false, 'errors' => ['email' => 'Email already registered']];
                }
            }
            return ['success' => false, 'errors' => ['general' => 'Registration failed. Please try again.']];
        }
    }

    // Login user
    public function login(string $username, string $password): array {
        try {
            $stmt = $this->db->prepare("
                SELECT id, password_hash 
                FROM users 
                WHERE (username = ? OR email = ?) AND is_active = 1
            ");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                return ['success' => false, 'message' => 'Invalid credentials'];
            }

            // Update last login
            $stmt = $this->db->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$user['id']]);

            // Set session
            $_SESSION['user_id'] = $user['id'];
            $this->loadUser($user['id']);

            return ['success' => true, 'message' => 'Login successful'];

        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Login failed. Please try again.'];
        }
    }

    // Logout user
    public function logout(): void {
        session_destroy();
        $this->user = null;
    }

    // Check if user is logged in
    public function isLoggedIn(): bool {
        return $this->user !== null;
    }

    // Check if user has specific role
    public function hasRole(string $role): bool {
        return $this->user && in_array($role, $this->user['roles']);
    }

    // Get current user data
    public function getUser(): ?array {
        return $this->user;
    }

    // Input validation
    private function validateRegistration(array $data): array {
        $errors = [];
        
        if (empty($data['username']) || strlen($data['username']) < 3) {
            $errors['username'] = 'Username must be at least 3 characters';
        }
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Valid email required';
        }
        if (empty($data['password']) || strlen($data['password']) < 8) {
            $errors['password'] = 'Password must be at least 8 characters';
        }
        if (empty($data['full_name'])) {
            $errors['full_name'] = 'Full name is required';
        }
        
        return $errors;
    }

    // Get all roles (useful for admin UI)
    public function getRoles(): array {
        $stmt = $this->db->query("SELECT * FROM roles ORDER BY name");
        return $stmt->fetchAll();
    }

    // Get all users with their roles
    public function getAllUsers(): array {
        $stmt = $this->db->query("
            SELECT 
                u.id,
                u.username,
                u.email,
                u.full_name,
                u.is_active,
                u.created_at,
                u.last_login,
                GROUP_CONCAT(r.name) as roles
            FROM users u
            LEFT JOIN user_roles ur ON u.id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id
            GROUP BY u.id
            ORDER BY u.id DESC
        ");
        
        $users = $stmt->fetchAll();
        foreach ($users as &$user) {
            $user['roles'] = $user['roles'] ? explode(',', $user['roles']) : [];
        }
        
        return $users;
    }

    // Create a new user (admin function)
    public function createUser(array $data): array {
        try {
            // Validate input
            $errors = $this->validateRegistration($data);
            if (!empty($errors)) {
                return ['success' => false, 'errors' => $errors];
            }

            $this->db->beginTransaction();

            // Insert user
            $stmt = $this->db->prepare("
                INSERT INTO users (username, email, password_hash, full_name, is_active)
                VALUES (?, ?, ?, ?, 1)
            ");
            $stmt->execute([
                $data['username'],
                $data['email'],
                password_hash($data['password'], PASSWORD_DEFAULT),
                $data['full_name']
            ]);
            $userId = $this->db->lastInsertId();

            // Assign roles
            if (!empty($data['roles'])) {
                $stmt = $this->db->prepare("
                    INSERT INTO user_roles (user_id, role_id)
                    SELECT ?, id FROM roles WHERE name IN (" . str_repeat('?,', count($data['roles']) - 1) . "?)
                ");
                $stmt->execute(array_merge([$userId], $data['roles']));
            }

            $this->db->commit();
            return ['success' => true];

        } catch (PDOException $e) {
            $this->db->rollBack();
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                if (strpos($e->getMessage(), 'username') !== false) {
                    return ['success' => false, 'message' => 'Username already taken'];
                }
                if (strpos($e->getMessage(), 'email') !== false) {
                    return ['success' => false, 'message' => 'Email already registered'];
                }
            }
            return ['success' => false, 'message' => 'Failed to create user'];
        }
    }

    // Update user information (admin function)
    public function updateUser(int $userId, array $data): bool {
        try {
            $this->db->beginTransaction();

            $updates = [];
            $params = [];

            if (isset($data['username'])) {
                $updates[] = "username = ?";
                $params[] = $data['username'];
            }
            if (isset($data['email'])) {
                $updates[] = "email = ?";
                $params[] = $data['email'];
            }
            if (isset($data['full_name'])) {
                $updates[] = "full_name = ?";
                $params[] = $data['full_name'];
            }
            if (!empty($data['password'])) {
                $updates[] = "password_hash = ?";
                $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
            }

            if (!empty($updates)) {
                $params[] = $userId;
                $sql = "UPDATE users SET " . implode(", ", $updates) . " WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }

            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            return false;
        }
    }

    // Update user status (activate/deactivate)
    public function updateUserStatus(int $userId, bool $status): bool {
        try {
            $stmt = $this->db->prepare("UPDATE users SET is_active = ? WHERE id = ?");
            return $stmt->execute([$status ? 1 : 0, $userId]);
        } catch (PDOException $e) {
            return false;
        }
    }

    // Update user roles (admin function)
    public function updateUserRoles(int $userId, array $roles): bool {
        try {
            $this->db->beginTransaction();

            // Remove existing roles
            $stmt = $this->db->prepare("DELETE FROM user_roles WHERE user_id = ?");
            $stmt->execute([$userId]);

            // Add new roles if any
            if (!empty($roles)) {
                $stmt = $this->db->prepare("
                    INSERT INTO user_roles (user_id, role_id)
                    SELECT ?, id FROM roles WHERE name IN (" . str_repeat('?,', count($roles) - 1) . "?)
                ");
                $stmt->execute(array_merge([$userId], $roles));
            }

            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            return false;
        }
    }
}
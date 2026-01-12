<?php
/**
 * Secure Login System
 * 
 * Security Features:
 * - SQL Injection Prevention (Prepared Statements)
 * - CSRF Token Protection
 * - Session Security
 * - Rate Limiting
 * - Password Hashing (bcrypt)
 */

// Start secure session
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,    // Prevent JavaScript access
        'cookie_secure' => true,       // HTTPS only
        'cookie_samesite' => 'Strict', // CSRF protection
        'use_strict_mode' => true      // Strict session mode
    ]);
}

// Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('DB_NAME', 'your_database');
define('RATE_LIMIT_ATTEMPTS', 5);
define('RATE_LIMIT_WINDOW', 900); // 15 minutes in seconds

/**
 * Generate CSRF Token
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF Token
 */
function validateCSRFToken($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Check Rate Limiting
 */
function checkRateLimit($identifier) {
    $key = 'rate_limit_' . md5($identifier);
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [
            'attempts' => 0,
            'first_attempt' => time()
        ];
    }
    
    $window = time() - $_SESSION[$key]['first_attempt'];
    
    // Reset if outside time window
    if ($window > RATE_LIMIT_WINDOW) {
        $_SESSION[$key] = [
            'attempts' => 0,
            'first_attempt' => time()
        ];
    }
    
    // Check if limit exceeded
    if ($_SESSION[$key]['attempts'] >= RATE_LIMIT_ATTEMPTS) {
        return false;
    }
    
    $_SESSION[$key]['attempts']++;
    return true;
}

/**
 * Hash Password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verify Password
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Get Database Connection (PDO for prepared statements)
 */
function getDBConnection() {
    try {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        return $pdo;
    } catch (PDOException $e) {
        error_log('Database connection failed: ' . $e->getMessage());
        die('Database connection error. Please try again later.');
    }
}

/**
 * Authenticate User (SQL Injection Safe)
 */
function authenticateUser($username, $password) {
    try {
        $pdo = getDBConnection();
        
        // Prepared statement prevents SQL injection
        $stmt = $pdo->prepare('SELECT id, username, password_hash, email FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        
        $user = $stmt->fetch();
        
        if ($user && verifyPassword($password, $user['password_hash'])) {
            return $user;
        }
        
        return false;
    } catch (PDOException $e) {
        error_log('Authentication error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Register User (SQL Injection Safe)
 */
function registerUser($username, $email, $password) {
    try {
        $pdo = getDBConnection();
        
        // Check if username already exists
        $checkStmt = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
        $checkStmt->execute([$username]);
        
        if ($checkStmt->fetch()) {
            return ['success' => false, 'message' => 'Username already exists'];
        }
        
        // Check if email already exists
        $checkStmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $checkStmt->execute([$email]);
        
        if ($checkStmt->fetch()) {
            return ['success' => false, 'message' => 'Email already exists'];
        }
        
        // Hash password and insert user
        $passwordHash = hashPassword($password);
        $stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash, created_at) VALUES (?, ?, ?, NOW())');
        $stmt->execute([$username, $email, $passwordHash]);
        
        return ['success' => true, 'message' => 'User registered successfully'];
    } catch (PDOException $e) {
        error_log('Registration error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Registration failed. Please try again later.'];
    }
}

/**
 * Sanitize Input
 */
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Login Handler
 */
function handleLogin() {
    $errors = [];
    
    // Check CSRF token
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (empty($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            $errors[] = 'Invalid security token. Please try again.';
        }
    }
    
    if (empty($errors) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = sanitizeInput($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $rememberMe = isset($_POST['remember_me']);
        
        // Validate input
        if (empty($username) || empty($password)) {
            $errors[] = 'Username and password are required';
        }
        
        if (empty($errors)) {
            // Check rate limiting using IP address
            $clientIP = $_SERVER['REMOTE_ADDR'];
            if (!checkRateLimit($clientIP)) {
                $errors[] = 'Too many login attempts. Please try again in 15 minutes.';
            }
        }
        
        if (empty($errors)) {
            $user = authenticateUser($username, $password);
            
            if ($user) {
                // Regenerate session ID to prevent session fixation
                session_regenerate_id(true);
                
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['login_time'] = time();
                $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
                $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
                
                // Set remember me cookie (if requested)
                if ($rememberMe) {
                    $token = bin2hex(random_bytes(32));
                    // Store token in database linked to user
                    // Set secure cookie
                    setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', true, true);
                }
                
                return ['success' => true, 'message' => 'Login successful'];
            } else {
                $errors[] = 'Invalid username or password';
            }
        }
    }
    
    return ['success' => false, 'errors' => $errors];
}

/**
 * Verify Session Security
 */
function verifySessionSecurity() {
    // Check if session variables exist
    if (empty($_SESSION['user_id'])) {
        return false;
    }
    
    // Verify IP address hasn't changed (optional, but recommended)
    if ($_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR']) {
        error_log('Session hijacking attempt detected for user: ' . $_SESSION['user_id']);
        return false;
    }
    
    // Verify user agent hasn't changed (optional)
    if ($_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
        return false;
    }
    
    // Check session timeout (30 minutes)
    if (time() - $_SESSION['login_time'] > 1800) {
        return false;
    }
    
    return true;
}

/**
 * Logout Handler
 */
function handleLogout() {
    // Unset all session variables
    $_SESSION = array();
    
    // Delete session cookie
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
    
    // Destroy session
    session_destroy();
    
    return ['success' => true, 'message' => 'Logged out successfully'];
}

/**
 * HTML Login Form
 */
function displayLoginForm() {
    $csrfToken = generateCSRFToken();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Secure Login</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                justify-content: center;
                align-items: center;
            }
            
            .container {
                background: white;
                padding: 40px;
                border-radius: 10px;
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
                width: 100%;
                max-width: 400px;
            }
            
            h1 {
                text-align: center;
                color: #333;
                margin-bottom: 30px;
                font-size: 28px;
            }
            
            .form-group {
                margin-bottom: 20px;
            }
            
            label {
                display: block;
                margin-bottom: 8px;
                color: #555;
                font-weight: 500;
            }
            
            input[type="text"],
            input[type="password"] {
                width: 100%;
                padding: 12px;
                border: 1px solid #ddd;
                border-radius: 5px;
                font-size: 14px;
                transition: border-color 0.3s;
            }
            
            input[type="text"]:focus,
            input[type="password"]:focus {
                outline: none;
                border-color: #667eea;
                box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            }
            
            .checkbox-group {
                display: flex;
                align-items: center;
                margin-bottom: 20px;
            }
            
            input[type="checkbox"] {
                margin-right: 8px;
                cursor: pointer;
            }
            
            .checkbox-group label {
                margin: 0;
                cursor: pointer;
            }
            
            button {
                width: 100%;
                padding: 12px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                border-radius: 5px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: transform 0.2s;
            }
            
            button:hover {
                transform: translateY(-2px);
            }
            
            button:active {
                transform: translateY(0);
            }
            
            .alert {
                padding: 12px;
                margin-bottom: 20px;
                border-radius: 5px;
                font-size: 14px;
            }
            
            .alert-error {
                background: #fee;
                color: #c33;
                border: 1px solid #fcc;
            }
            
            .alert-success {
                background: #efe;
                color: #3c3;
                border: 1px solid #cfc;
            }
            
            .register-link {
                text-align: center;
                margin-top: 20px;
                font-size: 14px;
                color: #666;
            }
            
            .register-link a {
                color: #667eea;
                text-decoration: none;
                font-weight: 600;
            }
            
            .register-link a:hover {
                text-decoration: underline;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Secure Login</h1>
            
            <?php if (isset($_GET['logout'])): ?>
                <div class="alert alert-success">
                    You have been logged out successfully.
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                
                <div class="form-group">
                    <label for="username">Username</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        required 
                        autocomplete="username"
                        placeholder="Enter your username"
                    >
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        required 
                        autocomplete="current-password"
                        placeholder="Enter your password"
                    >
                </div>
                
                <div class="checkbox-group">
                    <input 
                        type="checkbox" 
                        id="remember_me" 
                        name="remember_me"
                    >
                    <label for="remember_me">Remember me for 30 days</label>
                </div>
                
                <button type="submit">Login</button>
            </form>
            
            <div class="register-link">
                Don't have an account? <a href="register.php">Register here</a>
            </div>
        </div>
    </body>
    </html>
    <?php
}

// Main execution
$response = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = handleLogin();
}

// Display form
displayLoginForm();
?>

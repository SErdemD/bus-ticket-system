<?php
// src/pages/login.php

// ... (error handling and form setup)

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // ... (data validation)

    if (empty($errors)) {
        // 1. FETCH THE USER FROM THE DATABASE
        // It finds the user row that matches the email provided in the form.
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'];  
        $stmt = $pdo->prepare("SELECT id, full_name, email, password, role, balance, gender FROM User WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // 2. VERIFY THE PASSWORD
        // It securely checks if the submitted password matches the hashed password in the database.
        // It will return 'true' only if they match.
        if ($user && password_verify($password, $user['password'])) {

            // create session
            session_regenerate_id(); // A security step to prevent session fixation attacks.
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_gender'] = $user['gender'];
            if($user['role'] == 'company'){
                $_SESSION['company_id'] = $user['company_id'];
            }
            $_SESSION['user_balance'] = $user['balance'];
            
            header("Location: /home");
            
            exit();
        } else {
            $errors[] = "Invalid email or password.";
        }
    }
}
?>






<div class="row justify-content-center">
    <div class="col-md-6 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="card-title text-center mb-4">Login</h2>
                <form action="/login" method="POST">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email address</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Login</button>
                    </div>
                    <p class="text-center mt-3">
                        Don't have an account? <a href="/signup">Sign up</a>
                    </p>
                </form>
            </div>
        </div>
    </div>
</div>

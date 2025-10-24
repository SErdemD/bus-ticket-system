<?php

$full_name = '';
$email = '';
$gender = '';
//Hold error messages to show later
$errors = [];

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    //Role is going to be user automatically, only admin can create account with other roles

    if (empty($full_name)) {
        $errors[] = "Full Name is required.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "A valid email address is required.";
    }

    if (!in_array($gender, ['male', 'female'])) $errors[] = "Please select a valid gender option.";

    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }

    // Check for at least one number
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number.";
    }
    // Check for at least one uppercase letter
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter.";
    }
    // Check for at least one lowercase letter
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter.";
    }
    // Check for at least one special character
    if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?~`]/', $password)) {
        $errors[] = "Password must contain at least one special character.";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    // Check if user already exists
    // If there is error in validation then don't process these 
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM User WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "An account with this email address already exists.";
        }
    }

 
    // If there is no error then we can create acc
    if (empty($errors)) {
        //hash pass
        $hashed_password = password_hash($password, PASSWORD_ARGON2ID);
        
        //prepare query , role and balance has default so we will use them
        $sql = "INSERT INTO User (id, full_name, email, gender, password) VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        
        try {
            // Generate a UUID for the user ID.
            $user_id = bin2hex(random_bytes(16)); 
            
            $stmt->execute([$user_id, $full_name, $email, $gender, $hashed_password]);

            // Success! Set a session message and redirect to the login page.
            $_SESSION['success_message'] = "Your account has been created successfully! Please log in.";
            header("Location: /login");
            exit();

        } catch (PDOException $e) {
            $errors[] = "Something went wrong. Please try again later.";
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3>Create an Account</h3>
            </div>
            <div class="card-body">

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($errors as $error): ?>
                            <div><?php echo htmlspecialchars($error); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form action="/signup" method="POST" novalidate>
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" required value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>

                    <div class="mb-3">
                        <label for="gender" class="form-label">Gender</label>
                        <select class="form-select" id="gender" name="gender" required>
                            <option value="" disabled <?php echo empty($gender) ? 'selected' : ''; ?>>Select your gender</option>
                            <option value="male" <?php echo $gender === 'male' ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo $gender === 'female' ? 'selected' : ''; ?>>Female</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                        <div class="form-text">
                            Must be at least 8 characters and include an uppercase letter, a lowercase letter, a number, and a special character.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Sign Up</button>
                </form>

            </div>
            <div class="card-footer text-center">
                <p class="mb-0">Already have an account? <a href="/login">Log In</a></p>
            </div>
        </div>
    </div>
</div>

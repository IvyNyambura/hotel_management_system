<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include 'config.php';

function prepareAndExecute($conn, $sql, $params)
{
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        die('mysqli error: ' . htmlspecialchars($conn->error));
    }
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
    $stmt->execute();
    return $stmt;
}

// USER LOGIN
if (isset($_POST['user_login_submit'])) {
    $email = $_POST['Email'];
    $password = $_POST['Password'];
    $sql = "SELECT * FROM signup WHERE Email = ? AND Password = BINARY ?";
    $stmt = prepareAndExecute($conn, $sql, [$email, $password]);
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $_SESSION['usermail'] = $email;
        echo "<script>
            alert('Login successful');
            window.location.href = 'home.php';
        </script>";
        exit();
    } else {
        echo "<script>alert('Invalid user credentials');</script>";
    }
}

// STAFF LOGIN
if (isset($_POST['Emp_login_submit'])) {
    $email = $_POST['Emp_Email'];
    $password = $_POST['Emp_Password'];
    $sql = "SELECT * FROM emp_login WHERE Emp_Email = ? AND Emp_Password = BINARY ?";
    $stmt = prepareAndExecute($conn, $sql, [$email, $password]);
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $_SESSION['usermail'] = $email;
        echo "<script>
            alert('Staff login successful');
            window.location.href = 'admin/admin.php';
        </script>";
        exit();
    } else {
        echo "<script>alert('Invalid staff credentials');</script>";
    }
}

// USER SIGNUP
if (isset($_POST['user_signup_submit'])) {
    $username = $_POST['Username'];
    $email = $_POST['Email'];
    $password = $_POST['Password'];
    $cpassword = $_POST['CPassword'];

    if ($username == "" || $email == "" || $password == "") {
        echo "<script>alert('Fill in all fields');</script>";
    } elseif ($password !== $cpassword) {
        echo "<script>alert('Passwords do not match');</script>";
    } else {
        $sql_check = "SELECT * FROM signup WHERE Email = ?";
        $stmt_check = prepareAndExecute($conn, $sql_check, [$email]);
        $result = $stmt_check->get_result();

        if ($result->num_rows > 0) {
            echo "<script>alert('Email already exists');</script>";
        } else {
            $sql_insert = "INSERT INTO signup (Username, Email, Password) VALUES (?, ?, ?)";
            $stmt_insert = prepareAndExecute($conn, $sql_insert, [$username, $email, $password]);

            if ($stmt_insert->affected_rows > 0) {
                $_SESSION['usermail'] = $email;
                echo "<script>
                    alert('Signup successful');
                    window.location.href = 'home.php';
                </script>";
                exit();
            } else {
                echo "<script>alert('Signup failed');</script>";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dalton Hotel</title>
    <link rel="stylesheet" href="./css/login.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
</head>
<body>
    <div class="container mt-5" style="max-width: 500px;">
        <h2 class="text-center mb-4">User Login</h2>
        <form method="POST">
            <div class="form-floating mb-3">
                <input type="email" class="form-control" name="Email" placeholder="Email" required>
                <label for="Email">Email</label>
            </div>
            <div class="form-floating mb-3">
                <input type="password" class="form-control" name="Password" placeholder="Password" required>
                <label for="Password">Password</label>
            </div>
            <button type="submit" name="user_login_submit" class="btn btn-primary w-100">Log in as User</button>
        </form>

        <hr class="my-5">

        <h2 class="text-center mb-4">Staff Login</h2>
        <form method="POST">
            <div class="form-floating mb-3">
                <input type="email" class="form-control" name="Emp_Email" placeholder="Email" required>
                <label for="Emp_Email">Staff Email</label>
            </div>
            <div class="form-floating mb-3">
                <input type="password" class="form-control" name="Emp_Password" placeholder="Password" required>
                <label for="Emp_Password">Staff Password</label>
            </div>
            <button type="submit" name="Emp_login_submit" class="btn btn-dark w-100">Log in as Staff</button>
        </form>

        <hr class="my-5">

        <h2 class="text-center mb-4">User Sign Up</h2>
        <form method="POST">
            <div class="form-floating mb-3">
                <input type="text" class="form-control" name="Username" placeholder="Username" required>
                <label for="Username">Username</label>
            </div>
            <div class="form-floating mb-3">
                <input type="email" class="form-control" name="Email" placeholder="Email" required>
                <label for="Email">Email</label>
            </div>
            <div class="form-floating mb-3">
                <input type="password" class="form-control" name="Password" placeholder="Password" required>
                <label for="Password">Password</label>
            </div>
            <div class="form-floating mb-3">
                <input type="password" class="form-control" name="CPassword" placeholder="Confirm Password" required>
                <label for="CPassword">Confirm Password</label>
            </div>
            <button type="submit" name="user_signup_submit" class="btn btn-success w-100">Sign Up</button>
        </form>
    </div>
</body>
</html>
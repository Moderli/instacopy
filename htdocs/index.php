<?php
session_start();
require_once 'db_connect.php';

// Initialize variables for error messages
$loginError = $signupError = "";

// Function to generate a unique UID
function generateUniqueUID($conn)
{
    $uid = '#' . rand(1000, 9999);

    // Check if the UID already exists
    $sql = "SELECT * FROM users WHERE UID = '$uid'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        // If the UID exists, generate a new one recursively
        return generateUniqueUID($conn);
    }

    return $uid;
}

// Function to generate a unique username
function generateUniqueUsername($conn, $firstname, $uid)
{
    $username = strtolower($firstname) . $uid;
    // Check if the username already exists
    $sql = "SELECT * FROM users WHERE username = '$username'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        // If the username exists, generate a new one recursively
        return generateUniqueUsername($conn, $firstname, $uid);
    }

    return $username;
}

// Function to hash the password
function hashPassword($password)
{
    return password_hash($password, PASSWORD_DEFAULT);
}

// Function to get the client IP address
function getClientIP()
{
    if (isset($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (isset($_SERVER['HTTP_X_FORWARDED'])) {
        return $_SERVER['HTTP_X_FORWARDED'];
    } elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_FORWARDED_FOR'];
    } elseif (isset($_SERVER['HTTP_FORWARDED'])) {
        return $_SERVER['HTTP_FORWARDED'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

// Function to get the current date and time
function getCurrentDateTime()
{
    return date('Y-m-d H:i:s');
}

// Function to set the user's status as online
function setUserStatusOnline($conn, $userID)
{
    $sql = "UPDATE users SET status = 'online' WHERE user_id = '$userID'";
    $conn->query($sql);
}

// Function to validate and process login
function login($conn, $username, $password)
{
    // Sanitize input
    $username = $conn->real_escape_string($username);
    $password = $conn->real_escape_string($password);

    // Retrieve user from the database
    $sql = "SELECT * FROM users WHERE (email = '$username' OR username = '$username')";
    $result = $conn->query($sql);

    if ($result === false) {
        die("Error: " . $conn->error);
    }

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password'])) {
            // Store essential user information in the session
            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['username'] = $row['username'];
            setUserStatusOnline($conn, $row['user_id']);
            // Redirect to the desired page after successful login
            header("Location: welcome.php");
            exit();
        } else {
            return "Invalid username or password.";
        }
    } else {
        return "Invalid username or password.";
    }
}

// Function to validate and process signup
function signup($conn, $firstname, $lastname, $email, $password)
{
    // Sanitize input
    $firstname = $conn->real_escape_string($firstname);
    $lastname = $conn->real_escape_string($lastname);
    $email = $conn->real_escape_string($email);
    $password = $conn->real_escape_string($password);

    // Check if the email is already registered
    $sql = "SELECT * FROM users WHERE email = '$email'";
    $result = $conn->query($sql);

    if ($result === false) {
        die("Error: " . $conn->error);
    }

    if ($result->num_rows > 0) {
        return "Email already registered.";
    }

    // Generate unique UID
    $uid = generateUniqueUID($conn);

    // Generate username with attached UID
    $username = generateUniqueUsername($conn, $firstname, $uid);

    // Hash the password
    $hashedPassword = hashPassword($password);

    // Get client IP, current date, and time
    $ip = getClientIP();
    $date = getCurrentDateTime();

    // Insert user into the database with status as "offline"
    $status = "offline";
    $sql = "INSERT INTO users (username, UID, fname, lname, email, password, ip, date, status) 
            VALUES ('$username', '$uid', '$firstname', '$lastname', '$email', '$hashedPassword', '$ip', '$date', '$status')";

    if ($conn->query($sql) === TRUE) {
        // Store essential user information in the session
        $_SESSION['user_id'] = $conn->insert_id;
        $_SESSION['username'] = $username;
        setUserStatusOnline($conn, $conn->insert_id);
        // Redirect to the desired page after successful signup
        header("Location: welcome.php");
        exit();
    } else {
        return "Error: " . $sql . "<br>" . $conn->error;
    }
}

// Check if the users table exists
$sql = "SHOW TABLES LIKE 'users'";
$result = $conn->query($sql);

if ($result === false) {
    die("Error: " . $conn->error);
}

if ($result->num_rows == 0) {
    // The users table doesn't exist, create it
    $createTableSql = "CREATE TABLE users (
        user_id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(20) NOT NULL,
        UID VARCHAR(50) NOT NULL,
        fname VARCHAR(50) NOT NULL,
        lname VARCHAR(50) NOT NULL,
        email VARCHAR(90) NOT NULL,
        password VARCHAR(85) NOT NULL,
        ip VARCHAR(200) NOT NULL,
        date DATETIME NOT NULL,
        status VARCHAR(20) NOT NULL,
        img BLOB NOT NULL
    ) ENGINE = MyISAM";

    if ($conn->query($createTableSql) === TRUE) {
        echo "Users table created successfully!";
    } else {
        die("Error creating users table: " . $conn->error);
    }
}

// Handle login form submission
if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $loginError = login($conn, $username, $password);
}

// Handle signup form submission
if (isset($_POST['signup'])) {
    $firstname = $_POST['firstname'];
    $lastname = $_POST['lastname'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $signupError = signup($conn, $firstname, $lastname, $email, $password);
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="style.css">  
    <link rel="icon" type="image/jpg" href="images/icon.jpg">
    <title>cowency.online</title>
</head>
<body>
    <div class="wrapper">
        <nav class="nav">
            <div class="nav-logo">
                <p>COWENCY</p>
            </div>
            <div class="nav-menu" id="navMenu">
                <ul>
                    <li><a href="#" class="link active">Home</a></li>
                    <li><a href="#" class="link">Blog</a></li>
                    <li><a href="#" class="link">Services</a></li>
                    <li><a href="#" class="link">About</a></li>
                </ul>
            </div>
            <?php if (!isset($_SESSION['user_id'])) { ?>
                <div class="nav-button">
                    <button class="btn white-btn" id="loginBtn" onclick="login()">Sign In</button>
                    <button class="btn" id="registerBtn" onclick="register()">Sign Up</button>
                </div>
            <?php } else { ?>
                <div class="nav-button">
                    <p>Welcome, <?php echo $_SESSION['username']; ?>!</p>
                    <button class="btn"><a href="logout.php">Logout</a>
                </div>
                <div class="nav-button">
                    <button class="btn"><a href="welcome.php">Dashboard</a>
                </div>
            <?php } ?>
            <div class="nav-menu-btn">
                <i class="bx bx-menu" onclick="myMenuFunction()"></i>
            </div>
        </nav>
        

        <!----------------------------- Form box ----------------------------------->
        <div class="form-box">
            <!------------------- login form -------------------------->
            <div class="login-container" id="login">
                <div class="top">
                    <span>Don't have an account? <a href="#" onclick="register()">Sign Up</a></span>
                    <header>Login</header>
                </div>
                <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                    <div class="input-box">
                        <input type="text" class="input-field" name="username" placeholder="Username or Email">
                        <i class="bx bx-user"></i>
                    </div>
                    <div class="input-box">
                        <input type="password" class="input-field" name="password" placeholder="Password">
                        <i class="bx bx-lock-alt"></i>
                    </div>
                    <div class="input-box">
                        <input type="submit" class="submit" name="login" value="Sign In">
                    </div>
                    <div class="error-message"><?php echo $loginError; ?></div>
                </form>
                <div class="two-col">
                    <div class="one">
                        <input type="checkbox" id="login-check">
                        <label for="login-check"> Remember Me</label>
                    </div>
                    <div class="two">
                        <label><a href="#">Forgot password?</a></label>
                    </div>
                </div>
            </div>

            <!------------------- registration form -------------------------->
            <div class="register-container" id="register">
                <div class="top">
                    <span>Have an account? <a href="#" onclick="login()">Login</a></span>
                    <header>Sign Up</header>
                </div>
                <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                    <div class="two-forms">
                        <div class="input-box">
                            <input type="text" class="input-field" name="firstname" placeholder="Firstname">
                            <i class="bx bx-user"></i>
                        </div>
                        <div class="input-box">
                            <input type="text" class="input-field" name="lastname" placeholder="Lastname">
                            <i class="bx bx-user"></i>
                        </div>
                    </div>
                    <div class="input-box">
                        <input type="text" class="input-field" name="email" placeholder="Email">
                        <i class="bx bx-envelope"></i>
                    </div>
                    <div class="input-box">
                        <input type="password" class="input-field" name="password" placeholder="Password">
                        <i class="bx bx-lock-alt"></i>
                    </div>
                    <div class="input-box">
                        <input type="submit" class="submit" name="signup" value="Register">
                    </div>
                    <div class="error-message"><?php echo $signupError; ?></div>
                </form>
                <div class="two-col">
                    <div class="one">
                        <input type="checkbox" id="register-check">
                        <label for="register-check"> Remember Me</label>
                    </div>
                    <div class="two">
                        <label><a href="#">Terms & conditions</a></label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function myMenuFunction() {
            var i = document.getElementById("navMenu");

            if (i.className === "nav-menu") {
                i.className += " responsive";
            } else {
                i.className = "nav-menu";
            }
        }
    </script>

    <script>
        var a = document.getElementById("loginBtn");
        var b = document.getElementById("registerBtn");
        var x = document.getElementById("login");
        var y = document.getElementById("register");

        function login() {
            x.style.left = "4px";
            y.style.right = "-520px";
            a.className += " white-btn";
            b.className = "btn";
            x.style.opacity = 1;
            y.style.opacity = 0;
        }

        function register() {
            x.style.left = "-510px";
            y.style.right = "5px";
            a.className = "btn";
            b.className += " white-btn";
            x.style.opacity = 0;
            y.style.opacity = 1;
        }
    </script>
</body>
</html>

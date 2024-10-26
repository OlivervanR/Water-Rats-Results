<?php
$errors = array();

$password = $_POST['password'] ?? "";

// if user submits post
if(isset($_POST['submit'])) {
    $c_password = "racecommittee";
    $hash_password = password_hash($c_password, PASSWORD_DEFAULT);
    if (password_verify($password, $hash_password)) {
        // start the session
        ini_set('session.save_path',realpath(dirname($_SERVER['DOCUMENT_ROOT']) . '../../session'));
        session_start();
        $_SESSION['user'] = true;

        // redirect to main page and exit
        header("Location:index.php");
        exit();
    }
    else {
        $errors['password'] = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="main1.css">
    <title>Login</title>
</head>
<body>
    <form method="post">
        <label for="password">Password</label>
        <input type="password" id="password" name="password">
        <span class="error <?= !isset($errors['password']) ? 'hidden' : '' ?>">That password is incorrect.</span>

        <button type="submit" name="submit">Login</button></div>
    </form>
</body>
</html>
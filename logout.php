<?php
session_start();
// if the user is logged in destroy the session and redirect to main page
if(isset($_SESSION['user'])) {
    session_destroy(); 
    header("Location: index.php");
} 
exit;
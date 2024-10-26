<?php
ini_set('session.save_path',realpath(dirname($_SERVER['DOCUMENT_ROOT']) . '../../session'));
session_start();
// if the user is logged in destroy the session and redirect to main page
if(isset($_SESSION['user'])) {
    session_destroy(); 
    header("Location:index.php");
} 
exit;
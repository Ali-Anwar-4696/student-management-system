<?php

session_start();

require_once "../config/Database.php";
require_once "../classes/Auth.php";

// Database connection
$database = new Database();
$pdo = $database->connect();

// Auth object
$auth = new Auth($pdo);

// Logout
$auth->logout();

// Redirect to login
header("Location: login.php");
exit;
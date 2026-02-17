<?php
$server = "localhost";
$username = "root";
$password = "";
$database = "cmms";

$connect = mysqli_connect($server, $username, $password, $database);

if (!$connect) {
    die("❌ Database connection failed: " . mysqli_connect_error());
}
?>

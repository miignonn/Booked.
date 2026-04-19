<?php
$servername = "localhost";
$username = "root";
$password = "Mignon123rooT!";
$dbname = "Booked";

//create connection
$conn = new mysqli($servername, $username, $password, $dbname);

//check connection
if($conn->connect_error){
    die("Connection failed: " . $conn->connect_error);
} echo "Connection successful";

?>
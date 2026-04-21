<?php

if (session_status() == PHP_SESSION_NONE){
    session_start();

    //checks is session variable exists
    if(!isset($_SESSION['user_id'])){
        header("Location: /login.php");
        exit();
    }

    function require_role(string $role): void{
         if (!isset($_SESSION['role']) || $_SESSION['role'] !== $role) {
            header("Location: /public/index.php"); //sends non admin to home page instead of login
            exit();
        }

    }

}
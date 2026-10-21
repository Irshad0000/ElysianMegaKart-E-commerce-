<?php
//step1: create connection
$con=mysqli_connect("localhost","root","");

// if($con){
//     echo "Connection Successful";
// }else{
//     echo "Connection Failed";
// }
//step2: create database this is one time process 
// $create_db = "create database Elysian_MegaKart";
// if(mysqli_query($con,$create_db)){
//     echo "Database Created";
// }else{
//     echo "error in creating database";
// }

//step3:select database
try{
    mysqli_select_db($con,"Elysian_MegaKart");
} catch (Exception) {
    echo "Error in connecting with DB";
}

// step4: create table
// $create_table = "create table register(
//     id int auto_increment primary key ,
//     name varchar(30) , email varchar(20),
//     password varchar(20),
//     mobile bigint(10),
//     gender varchar(10),
//     role char(20) default 'user',
//     status char(10) default 'Inactive'
// )";

// $create_table = "CREATE TABLE register(
//     id INT AUTO_INCREMENT PRIMARY KEY,
//     name VARCHAR(100) NOT NULL,
//     email VARCHAR(100) NOT NULL UNIQUE,
//     password VARCHAR(255) NOT NULL,
//     mobile VARCHAR(15) NOT NULL,
//     gender ENUM('male','female','others') NOT NULL,
//     profile_pic VARCHAR(255) DEFAULT 'default.png',
//     role ENUM('admin','user') DEFAULT 'user',
//     status ENUM('Active','Inactive') DEFAULT 'Inactive',
//     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
// )";
// if(mysqli_query($con,$create_table)){
//     echo "Table Created Successfully";
// }else{
//     echo "Error in creating table"; 
// }



<?php
       // $db_name="mydb";
		$db_name="u177687112_csuki";
        $mysql_user = "u177687112_ikkis";
        $mysql_pass = "d:cYzcUX>:ce8;*&@`";
        $server_name = "combosuki.com";
		/*
		$mysql_user = "root";
        $mysql_pass = "";
        $server_name = "localhost:3307";*/

        $conn = mysqli_connect($server_name, $mysql_user, $mysql_pass,$db_name);
		
		$dbfz = "1.14";
        /*if($conn){
                echo "Success: A proper connection to MySQL was made! The my_db database is great." . PHP_EOL;
                echo "Host information: " . mysqli_get_host_info($conn) . PHP_EOL;
        }else{
                echo "Error: Unable to connect to MySQL." . PHP_EOL;
                echo "Debugging errno: " . mysqli_connect_errno() . PHP_EOL;
                echo "Debugging error: " . mysqli_connect_error() . PHP_EOL;
                exit;
        }*/
?>

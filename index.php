<!doctype php>
<html>
	<head>
	
	<link rel="apple-touch-icon" sizes="57x57" href="img/apple-icon-57x57.png">
	<link rel="apple-touch-icon" sizes="60x60" href="img/apple-icon-60x60.png">
	<link rel="apple-touch-icon" sizes="72x72" href="img/apple-icon-72x72.png">
	<link rel="apple-touch-icon" sizes="76x76" href="img/apple-icon-76x76.png">
	<link rel="apple-touch-icon" sizes="114x114" href="img/apple-icon-114x114.png">
	<link rel="apple-touch-icon" sizes="120x120" href="img/apple-icon-120x120.png">
	<link rel="apple-touch-icon" sizes="144x144" href="img/apple-icon-144x144.png">
	<link rel="apple-touch-icon" sizes="152x152" href="img/apple-icon-152x152.png">
	<link rel="apple-touch-icon" sizes="180x180" href="img/apple-icon-180x180.png">
	<link rel="icon" type="image/png" sizes="192x192"  href="img/android-icon-192x192.png">
	<link rel="icon" type="image/png" sizes="32x32" href="img/favicon-32x32.png">
	<link rel="icon" type="image/png" sizes="96x96" href="img/favicon-96x96.png">
	<link rel="icon" type="image/png" sizes="16x16" href="img/favicon-16x16.png">
	<link rel="manifest" href="img/manifest.json">
	<meta name="msapplication-TileColor" content="#ffffff">
	<meta name="msapplication-TileImage" content="img/ms-icon-144x144.png">
	<meta name="theme-color" content="#ffffff">
	
	
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=yes">
		<meta name="description" content="">
		<meta name="Ikkisoad" content="">
		<link rel="icon" href="img/favicon.ico">

		<title>FGCC</title>
    <!-- Bootstrap core CSS -->
		<link href="css/bootstrap.min.css" rel="stylesheet">

    <!-- Custom styles for this template -->
		<link href="jumbotron.css" rel="stylesheet">
		<style>
			body{
				background-color: #00190f;
				background: url("img/yellow-honeycomb.png");
				color: white;
			}
			.jumbotron{
				background: url("img/black-honeycomb.png");
				max-height: 190px;
				background-color: #000000;
			}		
			.footer {
			  position: fixed;
			  left: 0;
			  bottom: 0;
			  width: 100%;
			  background-color: black;
			  color: white;
			  
			  background: url("img/black-honeycomb.png");
			}
		</style> <!-- BACKGROUND COLOR-->
	</head>
	
	<body>
		<main role="main">
			<div class="jumbotron">
				<div class="container">
					<h1 class="display-3"></h1>
						<p>
							<a href="index.php"><img src="img/combosukibeta.png" align="middle" height="100" ></a>
						</p>
				</div>
			</div>
			<div class="container">
			<?php
				if(!isset($_GET['about'])){
						require "server/conexao.php";
						$query = "SELECT idgame,png FROM game ORDER BY name;";
						$result = $conn -> prepare($query);
						$result -> execute();
						
						foreach($result -> get_result()	as $gameid){
							echo '<a style="margin-left:5em" href=game.php?gameid=';
							echo $gameid['idgame'];
							echo '><img src=img/games/';
							echo $gameid['png'];
							echo '.png height=100 ';
							echo '></a>';
						}
				}else if($_GET['about'] == 1){
					echo 'About:<br><br><p>This application started as a fun project in the end of 2018, and the main motivation to keep at it is to help out the FGC assemble their findings and sort out the best options with a determined set of resources.</p>';
				}else if($_GET['about'] == 2){
					echo '<img src="img/numpadNotationBlack.jpg">';
				}
			?>
			</div>
		</main>
		<div class="footer">
		  <p style="text-align: right;">Created by: <a href="https://twitter.com/Ikkisoad">@Ikkisoad</a></p>
		  <p style="text-align: right;">Buttons designed by: <a href="https://twitter.com/Makaaaaai">@Makaaaaai</a></p>
		  <p style="text-align: right;"><a href="https://goo.gl/forms/xzjGo1dQEGOTzZGT2">Request a new game. </a></p>
		  <p style="text-align: middle;"><a href="index.php?about=1">About. </a></p>
		  <p style="text-align: middle;"><a href="index.php?about=2">Combo Guidelines. </a></p>
		</div>
	</body>
	    <!-- Bootstrap core JavaScript
		================================================== -->
		<!-- Placed at the end of the document so the pages load faster -->
		<script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>
		<script>window.jQuery || document.write('<script src="../../../../assets/js/vendor/jquery.min.js"><\/script>')</script>
		<script src="../../../../assets/js/vendor/popper.min.js"></script>
		<script src="../../../../dist/js/bootstrap.min.js"></script>
</html>

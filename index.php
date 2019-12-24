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
		
		<meta property="og:title" content="Combo好き" />
		<meta property="og:type" content="website" />
		<meta property="og:image" content="http://combosuki.com/img/combosuki.png" />
		<meta property="og:url" content="http://combosuki.com/index.php" />
		
		<meta property="og:description" 
  content="Community-fueled searchable environment that shares and perfects combos." />
		<meta name="theme-color" content="#d94040" />
		
		<meta name="description" content="Community-fueled searchable environment that shares and perfects combos.">

		<title>Combo好き</title>
		<!-- Bootstrap core CSS -->
		<link href="css/bootstrap.min.css" rel="stylesheet">

    <!-- Custom styles for this template -->
		<!-- <link href="jumbotron.css" rel="stylesheet"> -->
		
		<style>
			body{
				padding: 0;
				margin: 0;
				background-color: #00190f;
				background: url("img/<?php
					/*if(!isset($_SESSION)){
						session_start();
					}*/
					
					if(isset($_POST['color'])){
						setcookie("color", $_POST['color'], time() + (10 * 365 * 24 * 60 * 60), "/"); // 86400 = 1 day
						//$_SESSION["color"] = $_POST['color'];
					}
					
					if(isset($_POST['display'])){
						setcookie("display", $_POST['display'], time() + (10 * 365 * 24 * 60 * 60), "/"); // 86400 = 1 day
						//$_SESSION["color"] = $_POST['color'];
					}
					
					if(isset($_COOKIE['color'])){
						echo 'bg/'.$_COOKIE["color"].'honeycomb.png';
					}else{
						echo 'dark-honeycomb.png';
					}
				
				?>");
				color: white;
			}
			.container{
			  
			 
			  height: 100vh;
			}
			.jumbotron{
				background: url("img/black-honeycomb.png");
				max-height: 190px;
				background-color: #000000;
			}		
			.footer {
				flex: 0 0 50px;
			  position: fixed;
			  left: 0;
			  bottom: 0;
			  width: 100%;
			  background-color: black;
			  color: white;
			   opacity: 0.9;
			  
			  background: url("img/black-honeycomb.png");
			}
			
		</style> <!-- BACKGROUND COLOR-->
	</head>
	
	<body>
	<?php //echo phpversion(); ?>
	
		<div class="jumbotron jumbotron-fluid">
			<div class="container">
				<h1 class="display-4"></h1>
					<p class="lead">
						<a href="index.php"><img src="img/combosuki.png" align="middle" height="150" ></a>
					</p>
			</div>
		</div>
	
		<main role="main">
			
			<div class="container">
				<div class="btn-group" role="group" aria-label="Basic example">
					<form method="get" action="index.php">
						<button class="btn btn-secondary">Home</button>
					</form>
					<form method="get" action="list.php">
						<button class="btn btn-secondary">Listing</button>
					</form>
					
				</div>
				<div class="body">
					
					<?php
						require "server/functions.php";
						if(!isset($_GET['about'])){
							game_title();
						}else if($_GET['about'] == 1){
							count_combos();
							
							
							echo '<h2>About the application:</h2><p>This application started as a fun project in the end of 2018, and the main motivation to keep at it is to help out the FGC assemble their findings and sort out the best options with a determined set of resources.</p>';
							echo 'Hopefully with this database we will be able to keep the best combos known, without losing them to endless feeds.';
							//echo '<p><h2>About me:</h2>Brazilian Computer Science stundent that spends most of his time playing games.<br>My first fighting game was SFV, one year after DBFZ came out and it became my main game. I play 21, Kid Buu and Frieza.';
							//echo '<br><br><h3>This is the best explanation for this application:</h3>';
							//echo '<br><br>"Combo好きs main purpose is to be a community-fueled searchable environment that shares and perfects combos."<p>u/madninjaman @ reddit </p>';
							
							echo '<br><br><form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
							<input type="hidden" name="cmd" value="_s-xclick" />
							<input type="hidden" name="hosted_button_id" value="JNX6A2HZETH5Y" />
							<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_SM.gif" border="0" name="submit" title="PayPal - The safer, easier way to pay online!" alt="Donate with PayPal button" />
							<img alt="" border="0" src="https://www.paypal.com/en_BR/i/scr/pixel.gif" width="1" height="1" />
							</form>';
							
							echo '<h2>All Games</h2>';
							game_text_only();
							
						}else if($_GET['about'] == 2){
							echo '<img src="img/numpadNotationBlack.jpg">';
						}else if($_GET['about'] == 3){
							echo '<h2>Log</h2>';
							logs();
						}else if($_GET['about'] == 4){
							echo '<form method="post" action="index.php">Background Color:<p>';
							echo '<select name="color" class="custom-select">
							<option value="0">Green</option>
							<option value="1">Red</option>
							<option value="2">Yellow</option>
							<option value="3">Purple</option>
							<option value="4">Black</option>
							<option value="5">Gray</option>
							<option value="6">Cyan</option>
							<option value="7">Orange</option>
							<option value="8">Blue</option>
							<option value="9">White</option>
							</select></p>Display method:<p>
							<select name="display" class="custom-select"><option value="1">Image</option><option value="0">Text</option></select>
							</p><button class="btn btn-secondary">Save</button></form>';
						}
						echo '<br><br><br><br><br><br><br>';
					?>
				</div>
			</div>
		</main>
		<div class="footer">
		  <!-- <div style="text-align: right;">Created by: <a href="https://twitter.com/Ikkisoad" target="_blank">@Ikkisoad</a>   E-Mail: <a href="mailto:ikkisoad@combosuki.com">ikkisoad@combosuki.com</a></div>
		  <div style="text-align: right;">Buttons designed by: <a href="https://twitter.com/Makaaaaai" target="_blank">@Makaaaaai</a></div> -->
		  <div style="text-align: center;">
		  <!-- <a href="https://goo.gl/forms/xzjGo1dQEGOTzZGT2" target="_blank">Request a new game   </a> -->
		  <a href="http://localhost/combosuki/addgame.php" target="_blank">Add game   </a>/ 
		  <a href="index.php?about=1" style="padding-right: 5px;">About </a> / 
		  <a href="index.php?about=2">Combo Guidelines </a>
		   / <a href="index.php?about=3" style="padding-right: 5px;">Logs </a> / <a href="https://docs.google.com/spreadsheets/d/1ac2nRBy0tTPz6k6heook5w8R7dDngutW8OkK5alhI7k/edit#gid=1066344564" target="_blank" style="padding-right: 5px;">FGC Discord Compendium </a> / <a href="index.php?about=4" style="padding-right: 5px;">Preferences </a>
		   
		   
		  </div>
		</div>
	</body>
	    <!-- Bootstrap core JavaScript
		================================================== -->
		<!-- Placed at the end of the document so the pages load faster -->
		<script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>
		<script>window.jQuery || document.write('<script src="../../../../assets/js/vendor/jquery.min.js"><\/script>')</script>
		<!-- <script src="../../../../assets/js/vendor/popper.min.js"></script> 
		<script src="../../../../dist/js/bootstrap.min.js"></script> -->
</html>

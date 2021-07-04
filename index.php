<!doctype php>
<?php
	include_once "server/functions.php";
	include_once "server/conexao.php";
	strip_POSTtags();
	if(isset($_POST['color'])){
		setcookie("color", $_POST['color'], time() + (10 * 365 * 24 * 60 * 60), "/"); // 86400 = 1 day
	}
	if(isset($_POST['display'])){
		setcookie("display", $_POST['display'], time() + (10 * 365 * 24 * 60 * 60), "/"); // 86400 = 1 day
		header("Refresh:0");
	}
?>
<html>
	<head>
		<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-+0n0xVW2eSR5OomGNYDnhzAbDsOXxcvSN1TPprVMTNDbiYZCxYbOOl7+AMvyTG2x" crossorigin="anonymous">
		<link rel="icon" type="image/png" sizes="32x32" href="img/favicon-32x32.png">
		<link rel="icon" type="image/png" sizes="96x96" href="img/favicon-96x96.png">
		<link rel="icon" type="image/png" sizes="16x16" href="img/favicon-16x16.png">
		<meta name="msapplication-TileImage" content="img/ms-icon-144x144.png">
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

		<link href="css/bootstrap.min.css" rel="stylesheet">
		<style>
			<?php
				background();
			?>
			.container{
				height: 100vh;
			}
			.jumbotron{
				max-height: 190px;
				background-color: #000000;
			}
		</style>
	</head>
	<body>
		<main role="main">
			<?php 
				jumbotron(1,150);
				header_buttons(1, 0, 0, 0);
			?>
			<div class="container">
				<div class="body">
					<?php
						if(!isset($_GET['about'])){
							game_title($conn);
						}else if($_GET['about'] == 1){
							echo '
							<div class="card border-dark mb-3 bg-dark">
								<div class="row g-0">
									<div class="col-md-4">
										<img src="img/pfp.jpg" alt="...">
									</div>
									<div class="col-md-8">
										<div class="card-body">
											<h5 class="card-title">Ikkisoad</h5>
											<p class="card-text">Creator of Combo好き.</p>
											<p class="card-text">This application started as a fun project at the end of 2018, and the motivation to keep improving it is to help out the FGC assemble their findings and sort out the best options with specific sets of resources.</p>
											<p class="card-text">Hopefully with this database we will be able to keep the best combos known, without losing them to endless feeds...</p>
											<p class="card-text"><small class="text-muted">...</small></p>
											<a href="https://twitter.com/Ikkisoad" class="card-link" target="_blank">@Ikkisoad</a>
										</div>
									</div>
								</div>
							</div>';
							count_combos($conn);
							echo '
							<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
								<input type="hidden" name="cmd" value="_s-xclick" />
								<input type="hidden" name="hosted_button_id" value="JNX6A2HZETH5Y" />
								<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_SM.gif" border="0" name="submit" title="PayPal - The safer, easier way to pay online!" alt="Donate with PayPal button" />
								<img alt="" border="0" src="https://www.paypal.com/en_BR/i/scr/pixel.gif" width="1" height="1" />
							</form>';
							echo '<h2>All Games</h2>';
							game_text_only($conn);
						}else if($_GET['about'] == 2){
							echo '<img src="img/numpadNotationBlack.jpg">';
						}else if($_GET['about'] == 3){
							echo '<h2>Log</h2>';
							logs($conn);
						}else if($_GET['about'] == 4){
							echo '
							<form method="post" action="index.php">Background Color:<p>';
							echo '
								<p>
								  <label for="color">Background color</label>
								  <input type="color" name="color" id="color" value="';
								  if(isset($_COOKIE['color'])){
									echo $_COOKIE['color'];
								  }else{
									  echo '920000';
								  }
								  echo'">
								</p>
								</p>Display method:<p>
								<select name="display" class="custom-select">
									<option value="1">Image</option>
									<option value="0">Text</option>
								</select>
								</p><button class="btn btn-secondary">Save</button>
							</form>';
						}
						mysqli_close($conn);
					?>
				</div>
			</div>
		</main>
		<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js" integrity="sha384-gtEjrD/SeCtmISkJkNUaaKMoLD0//ElJ19smozuHV6z3Iehds+3Ulb9Bn9Plx0x4" crossorigin="anonymous"></script>
	</body>
</html>

<!doctype php>
<?php
	require_once "server/initialize.php";
	strip_POSTtags();
	set_cookies();
	if(isset($_POST['color'])){
		setcookie("color", str_replace('#','',$_POST['color']), time() + (10 * 365 * 24 * 60 * 60), "/"); // 86400 = 1 day
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
		<meta name="theme-color" content="#C62114" />

		<meta name="description" content="Community-fueled searchable environment that shares and perfects combos.">
		<title>Combo好き</title>

		<link href="css/bootstrap.min.css" rel="stylesheet">
		<link rel="stylesheet" href="css/combosuki.css">
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
		<?php 
			jumbotron(1,150);
			header_buttons(1, 0, 0, 0);
		?>
		<div class="container my-3">
			<div class="body">
				<?php
					if(!isset($_GET['about'])){
						game_title($conn);
					}else if($_GET['about'] == 1){ //About
						echo '
						<div class="card combosuki-main-reversed mb-3">
							<div class="row g-0">
								<div class="col-md-4">
									<img src="img/Ikki.jpg" alt="...">
								</div>
								<div class="col-md-8">
									<div class="card-body">
										<h5 class="card-title">Ikki</h5>
										<p class="card-text">Creator of Combo好き.</p>
										<p class="card-text">This application started as a fun project at the end of 2018, and the motivation to keep improving it is to help out the FGC assemble their findings and sort out the best options with specific sets of resources.</p>
										<p class="card-text">Hopefully with this database we will be able to keep the best combos known, without losing them to endless feeds...</p>
										<p class="card-text"><small class="text-muted">...</small></p>
										<a href="https://twitter.com/Ikkisoad" class="card-link" target="_blank">@Ikkisoad</a>
									</div>
								</div>
							</div>
						</div>';
						echo '
						<div class="card combosuki-main-reversed mb-3">
							<div class="row g-0">
								<div class="col-md-4">
									<img src="img/Makai.jpg" alt="...">
								</div>
								<div class="col-md-8">
									<div class="card-body">
										<h5 class="card-title">Makai</h5>
										<p class="card-text">Branding.</p>
										<p class="card-text">Has literally nothing to say.</p>
										<p class="card-text">Graphic Designer</p>
										<p class="card-text"><small class="text-muted">...</small></p>
										<a href="https://twitter.com/Makaai_" class="card-link" target="_blank">@Makaai_</a>
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
						
						echo '
									<h2>Similar websites</h2>
										<a href="https://combotier.com/" class="card-link" target="_blank">Combotier</a>
										<a href="https://www.kombatakademy.com/" class="card-link" target="_blank">Kombat Academy</a>';
						echo '
						<h2>Other FGC websites</h2>
							<a href="https://glossary.infil.net/" class="card-link" target="_blank">The Fighting Game Glossary</a>
							<a href="http://www.dustloop.com/" class="card-link" target="_blank">Dustloop wiki</a>
							<a href="https://wiki.gbl.gg/" class="card-link" target="_blank">Mizuumi wiki</a>
							<a href="https://www.dreamcancel.com/" class="card-link" target="_blank">Dream Cancel wiki</a>
							<a href="https://srk.shib.live/w/Main_Page" class="card-link" target="_blank">Shoryuken wiki</a>
							<a href="https://fgcombo.com/" class="card-link" target="_blank">FGCombo</a>
							<a href="https://www.top8er.com/" class="card-link" target="_blank">Top8er</a>';
					}else if($_GET['about'] == 2){ //Combo Guidelines
						echo '<img src="img/numpadNotationBlack.jpg">';
					}else if($_GET['about'] == 3){ //Logs
						echo '<h2>Log</h2>';
						logs($conn);
					}else if($_GET['about'] == 4){ //Preferences
						echo '
						<form method="post" class="form-control combosuki-main-reversed text-white" action="index.php">';
						echo '<div class="row">
							<div class="col-auto mx-auto">
								  <label for="color">Background Color:</label>
								  <input type="color" class="form-control form-control-color" name="color" id="headcolor" value="';
								  if(isset($_COOKIE['color'])){
									echo '#'.$_COOKIE['color'];
								  }else{
									  echo '920000';
								  }
								  echo'">'; ?>
									</div>
									<div class="col-auto mx-auto">
									<label>Default Colors:</label>
									<button onclick="func('#C62114')" class="bg-combosuki-main-1" style="padding: 12px;" type="button"/>
									<button onclick="func('#020202')" class="bg-combosuki-main-2" style="padding: 12px;" type="button"/>
									<button onclick="func('#920000')" class="bg-combosuki-secondary-1" style="padding: 6px;" type="button"/>
									<button onclick="func('#FA591C')" class="bg-combosuki-secondary-2" style="padding: 6px;" type="button"/>
									<button onclick="func('#2E2E2E')" class="bg-combosuki-secondary-3" style="padding: 6px;" type="button"/>
							<?php echo '
								</div>
							</div>
							<div class="row">
								<div class="col-auto mx-auto">
									  Display method:
									<select name="display" class="form-select input-small">
										<option value="1">Image</option>
										<option value="0">Text</option>
									</select>
								</div>
							</div>
							<div class="row">
								<div class="col-auto mx-auto">
									<button class="btn btn-combosuki">Save</button>
								</div>
							</div>
							</div>
						</form>';
					}else if($_GET['about'] == 5){ //Games
						echo '<h2>Games</h2>';
						game_text_only($conn);
					}
					mysqli_close($conn);
				?>
			</div>
		</div>
		<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js" integrity="sha384-gtEjrD/SeCtmISkJkNUaaKMoLD0//ElJ19smozuHV6z3Iehds+3Ulb9Bn9Plx0x4" crossorigin="anonymous"></script>
		<script>
			function func(color) {
				document.getElementById("headcolor").value = color;
			}
		</script>
	</body>
</html>

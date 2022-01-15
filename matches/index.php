<!doctype php>
<?php
	$urldepth = '../';
	require_once "../server/initialize.php";
	if(isset($_POST['color'])){
		setcookie("color", str_replace('#','',$_POST['color']), time() + (10 * 365 * 24 * 60 * 60), "/"); // 86400 = 1 day
		$_COOKIE['color'] = str_replace('#','',$_POST['color']);
	}
	if(isset($_POST['display'])){
		setcookie("display", $_POST['display'], time() + (10 * 365 * 24 * 60 * 60), "/"); // 86400 = 1 day
		//header("Refresh:0");
	}
?>
<html>
	<head>
		<?php
			headerHTML(); 
			openGraphProtocol();
		?>
		<title>Combo好き</title>
		<?php
			getCSS();
		?>
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
			jumbotron(150);
			header_buttons(1, 0, 0, 0);
		?>
		<div class="container my-3">
			<div class="body">
			matches teste
			</div>
		</div>
		<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js" integrity="sha384-gtEjrD/SeCtmISkJkNUaaKMoLD0//ElJ19smozuHV6z3Iehds+3Ulb9Bn9Plx0x4" crossorigin="anonymous"></script>
		<?php
			returnColorJS();
		?>
	</body>
</html>

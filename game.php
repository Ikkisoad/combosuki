<!doctype php>
<html>
	<head>
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
				background-color: #35340a;
				background: url("img/yellow-honeycomb.png");
			}
			.jumbotron{
				max-height: 190px;
				background-color: #000000;
			}
		</style> <!-- BACKGROUND COLOR-->
		<?php
			if(isset($_POST['action'])){
				require "server/conexao.php";

				if($_POST['action'] == 0){

					$query = "DELETE FROM `resources` WHERE `combo_idcombo` = ?";
					$result = $conn -> prepare($query);
					$result -> bind_param("i", $_POST['idcombo']);
					$result -> execute();

					$query = "DELETE FROM `combo` WHERE `idcombo` = ?";
					$result = $conn -> prepare($query);
					$result -> bind_param("i", $_POST['idcombo']);
					$result -> execute();
				}
				//UPDATE `combo` SET `type` = '3' WHERE `combo`.`idcombo` = 18;
				if($_POST['action'] == 1){

					$query = "UPDATE `combo` SET `type` = '3' WHERE `combo`.`idcombo` = ?";
					$result = $conn -> prepare($query);
					$result -> bind_param("i", $_POST['idcombo']);
					$result -> execute();
				}

			}
		?>

	</head>

	<body>
		<main role="main">
			<div class="jumbotron">
				<div class="container">
					<h1 class="display-3"></h1>
						<p>
							<a href="index.php"><img src="img/combosuki.png" align="middle" height="100" ></a>
						</p>
				</div>
			</div>
			<div class="container">
				<div class="btn-group" role="group" aria-label="Basic example">
					<form method="post" action="forms.php?gameid=<?php echo $_GET['gameid']; ?>">
						<button class="btn btn-secondary">Submit</button>
						<input type="hidden" id="type" name="type" value="1">
					</form>

					<form id="MyForm" method="get" action="forms.php">
						<button class="btn btn-secondary">Search</button>
						<input type="hidden" id="gameid" name="gameid" value="<?php echo $_GET['gameid'] ?>">
					</form>
				</div>
			</div>
		</main>
	</body>
	    <!-- Bootstrap core JavaScript
		================================================== -->
		<!-- Placed at the end of the document so the pages load faster -->
		<script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>
		<script>window.jQuery || document.write('<script src="../../../../assets/js/vendor/jquery.min.js"><\/script>')</script>
		<script src="../../../../assets/js/vendor/popper.min.js"></script>
		<script src="../../../../dist/js/bootstrap.min.js"></script>
</html>

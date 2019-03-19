<!doctype php>
<html>
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=yes">
		<meta name="description" content="">
		<meta name="Ikkisoad" content="">
		<link rel="icon" href="img/favicon.ico">

		<title>ComboSuki</title>
    <!-- Bootstrap core CSS -->
		<link href="css/bootstrap.min.css" rel="stylesheet">

    <!-- Custom styles for this template -->
		<link href="jumbotron.css" rel="stylesheet">
		<style>
			body{
				background-color: #35340a;
				background: url("img/red-honeycomb.png");
				color: white;
			}
			.jumbotron{
				max-height: 190px;
				background-color: #000000;
			}
			table {
				border-spacing: 0;
				width: 100%;
				border: 1px solid #ddd;
				
			}

			th, td {
				text-align: left;
				padding: 16px;
			}

			tr:nth-child(even) {
				background-color: #212121
			}
			
			tr:nth-child(odd) {
				background-color: #000000
			}
			textare{
				color: #000000;	
			}
			.img-responsive{width:100%;}
		</style> <!-- BACKGROUND COLOR-->
		<?php
			$_POST = array_map("strip_tags", $_POST);
			$_GET = array_map("strip_tags", $_GET);
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
							<a href="index.php"><img <?php
									echo 'src=img/games/';
									echo $_GET['gameid'];
									echo '.png ';
								?> align="middle" height="100" ></a>
						</p>
				</div>
			</div>
			<div class="container">
				<div class="btn-group" role="group" aria-label="Basic example">
					<form id="MyForm" method="get" action="index.php">
						<button class="btn btn-secondary">Home</button>
					</form>
					<form method="post" action="forms.php?gameid=<?php echo $_GET['gameid']; ?>">
						<button class="btn btn-secondary">Submit</button>
						<input type="hidden" id="type" name="type" value="1">
					</form>

					<form id="MyForm" method="get" action="forms.php">
						<button class="btn btn-secondary">Search</button>
						<input type="hidden" id="gameid" name="gameid" value="<?php echo $_GET['gameid'] ?>">
					</form>
				</div>
					<p><h2>Latest submissions</h2></p>
					<?php
						require "server/conexao.php";
						$query = "SELECT idcombo,Name,combo,damage,type FROM `combo` INNER JOIN `character` ON `combo`.`character_idcharacter` = `character`.`idcharacter` WHERE `character`.`game_idgame` = ? ORDER BY submited DESC LIMIT 0,25";
						$result = $conn -> prepare($query);
						$result -> bind_param("i",$_GET['gameid']);
						$result -> execute();
						echo '<table>';
							echo '<tr>';
								echo '<th>Character</th><th>Inputs</th><th>Damage</th><th>Type</th>';
							echo '</tr>';
						
							foreach($result -> get_result() as $data){
								echo '<tr><td>';
								echo $data['Name'];
								echo '</td><td>';
								echo		'<a href="combo.php?gameid='.$_GET['gameid'].'&idcombo='.$data['idcombo'].'">'.$data['combo'].'</a>';
								echo '</td><td>';
								echo $data['damage'];
								echo '</td><td>';
								switch($data['type']){
									case 0:
										echo 'Combo';
										break;
									case 1:
										echo 'Blockstring';
										break;
									case 2:
										echo 'Mix Up';
										break;
									case 3:
										echo 'Archive';
										break;
								}
								echo '</td></tr>';
							}
							echo '</table>';
					?>
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

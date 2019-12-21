<!doctype php>
<?php
	include "server/conexao.php";
	if(!empty($_POST)){
	//	print_r($_POST);
		$query = "SELECT globalPass FROM game WHERE idgame = ?";
		$result = $conn -> prepare($query);
		$result -> bind_param("i", $_GET['gameid']);
		$result -> execute();
		foreach($result -> get_result() as $pass){
		//	echo 'hi';
			if($pass['globalPass'] != $_POST['password']){
				header("Location: index.php");
				exit();
			}
		}
		
		if($_POST['action'] == 'Submit'){
		
			$query = "UPDATE `game` SET `name`= ?,`image`= ? WHERE `idgame` = ?";
			$result = $conn -> prepare($query);
			//print_r($_POST);
			$result -> bind_param("ssi", $_POST['title'], $_POST['image'], $_GET['gameid']);
			$result -> execute();
		}else if($_POST['action'] == 'Delete'){
			
		}
	}
?>
<html>
	<head>
	<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=yes">
	<meta name="description" content="">
	<meta name="Ikkisoad" content="">
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

		<title>ComboSuki</title>
    <!-- Bootstrap core CSS -->
		<link href="css/bootstrap.min.css" rel="stylesheet">

    <!-- Custom styles for this template 
		<link href="jumbotron.css" rel="stylesheet"> -->
		<style>
			body{
				background-color: #190000;
				background: url("img/<?php
				include "server/functions.php";
				if(isset($_COOKIE['color'])){
					echo 'bg/'.$_COOKIE["color"].'honeycomb.png';
				}else{
					echo 'dark-honeycomb.png';
				}?>");
				color: white;
			}
			.jumbotron{
				max-height: 250px;
				background-color: #000000;
			}
			textare{
				color: #000000;	
			}
		</style> <!-- BACKGROUND COLOR-->
	</head>
	
	<body>
		<main role="main">
			<div class="container">
				<div class="form-group">
					<!-- <button id="Mybtn" class="btn btn-primary" onclick="changeMethod(this)">Submit a Combo</button> -->
					<div class="btn-group" role="group" aria-label="Basic example">
						<form id="MyForm" method="get" action="index.php">
							<button class="btn btn-secondary">Home</button>
						</form>
					</div>
						<form method="post" action="editgame.php?gameid=<?php echo $_GET['gameid']?>">
					<?php
						//$game = get_game($_GET['gameid']);
						//print_r($game);
						
						require "server/conexao.php";
						$query = "SELECT name,image FROM game WHERE idgame = ?;";
						$result = $conn -> prepare($query);
						$result -> bind_param("i",$_GET['gameid']);
						$result -> execute();
						//print_r($result);
						
						//$game -> get_result()
						foreach($result -> get_result()	as $lol){
							//print_r($lol);
							echo '<div class="input-group mb-3">
							<div class="input-group-prepend">
								<span class="input-group-text">Game Title:
							</span></div>
							<input type="text" name="title" class="form-control" value="'.$lol['name'].'">
						</div>';
						echo '<div class="input-group mb-3">
							<div class="input-group-prepend">
								<span class="input-group-text">Game Image:
							</span></div>
							<input type="text" name="image" class="form-control" value="'.$lol['image'].'">
						</div>';
						game_image($_GET['gameid'],250);
						echo '<div class="input-group mb-3">
							<div class="input-group-prepend">
								<span class="input-group-text">Game Password:
							</span></div>
							<input type="password" maxlength="16" name="password" class="form-control">
						</div>';
						
						}
						
						
					?>
					
						<p><button type="submit" name="action" value="Submit" class="btn btn-primary btn-block">Submit</button></p>
						<p><button type="submit" name="action" value="Delete" class="btn btn-danger btn-block" onclick="return confirm('Are you sure you want to delete this game?');">Delete</button></p>
					
						</form>
						
						<div class="btn-group" role="group">
							<form method="get" action="game.php">
								<input type="hidden" id="gameid" name="gameid" value="<?php echo $_GET['gameid'] ?>">
								<button class="btn btn-secondary"><< back</button>
							</form>
							<form method="get" action="index.php">
								<button class="btn btn-secondary">Characters</button>
							</form>
							<form method="post" action="forms.php?gameid=<?php echo $_GET['gameid']; ?>">
								<button class="btn btn-secondary">Buttons</button>
								<input type="hidden" id="type" name="type" value="1">
							</form>

							<form method="get" action="forms.php">
								<button class="btn btn-secondary">Resources</button>
								<input type="hidden" id="gameid" name="gameid" value="<?php echo $_GET['gameid'] ?>">
							</form>
						</div>
				</div>
			</div>
		</main>
		<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>
	</body>
	    <!-- Bootstrap core JavaScript
		================================================== -->
		<!-- Placed at the end of the document so the pages load faster -->
		<script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>
		<script>window.jQuery || document.write('<script src="../../../../assets/js/vendor/jquery.min.js"><\/script>')</script>
		<script src="../../../../assets/js/vendor/popper.min.js"></script>
		<script src="../../../../dist/js/bootstrap.min.js"></script>
		<script>
		https://tutorialdeep.com/knowhow/show-form-on-button-click-jquery/
		</script>
		
		<script> 
		function moveNumbers(num) { 
				var txt=document.getElementById("comboarea").value; 
				txt=txt + num + " "; 
				document.getElementById("comboarea").value=txt; 
		}
		function backspace(){
			var txt=document.getElementById("comboarea").value;
			if(txt.length == 0){return;}
			if(txt[txt.length-1] == " ")txt = txt.substring(0, txt.length - 1);
			while(txt[txt.length-1] != " " ){
				if(txt.length == 1){
					txt = "";
					break;
				}
				txt = txt.substring(0, txt.length - 1);
				if(txt.legth == 0){
					break;
				}
			}
			document.getElementById("comboarea").value=txt; 
		}
		</script>
		<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js"></script>
</html>

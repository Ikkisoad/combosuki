<!doctype php>
<?php
	include_once "server/conexao.php";
	include_once "server/functions.php";
	if(!empty($_POST)){
		$_POST = array_map("strip_tags", $_POST);
		$_GET = array_map("strip_tags", $_GET);
		$query = "SELECT globalPass FROM game WHERE idgame = ?";
		$result = $conn -> prepare($query);
		$result -> bind_param("i", $_GET['gameid']);
		$result -> execute();
		foreach($result -> get_result() as $pass){
			if($pass['globalPass'] != $_POST['password']){
				header("Location: index.php");
				exit();
			}
		}
		
		if($_POST['action'] == 'Submit'){
			if($_POST['modPass'] != ''){
				$query = "UPDATE `game` SET `name`= ?,`image`= ?, `modPass`= ?, `patch`= ?, `description` = ?, `notation` = ? WHERE `idgame` = ?";
				$result = $conn -> prepare($query);
				$_POST['modPass'] = password_hash($_POST['modPass'],PASSWORD_DEFAULT);
				$result -> bind_param("ssssssi", $_POST['title'], $_POST['image'], $_POST['modPass'], $_POST['patch'],$_POST['description'],$_POST['notation'], $_GET['gameid']);
			}else{
				$query = "UPDATE `game` SET `name`= ?,`image`= ?, `patch`= ?, `description` = ?, `notation` = ? WHERE `idgame` = ?";
				$result = $conn -> prepare($query);
				$result -> bind_param("sssssi", $_POST['title'], $_POST['image'], $_POST['patch'],$_POST['description'],$_POST['notation'],$_GET['gameid']);	
			}
			$result -> execute();
		}else if($_POST['action'] == 'Delete'){
			$query = "DELETE `resources` FROM `resources` 
JOIN `combo` ON `combo`.`idcombo` = `resources`.`combo_idcombo`
JOIN `character` ON `character`.`idcharacter` = `combo`.`character_idcharacter`
JOIN `game` ON `game`.`idgame` = `character`.`game_idgame`
WHERE `game`.`idgame` = ?";
			$result = $conn -> prepare($query);
			//print_r($_POST);
			$result -> bind_param("i", $_GET['gameid']);
			$result -> execute();
			
			$query = "DELETE `resources_values` FROM `resources_values` 
JOIN `game_resources` ON `game_resources`.`idgame_resources` = `resources_values`.`game_resources_idgame_resources`
WHERE `game_resources`.`game_idgame` = ?";
			$result = $conn -> prepare($query);
			//print_r($_POST);
			$result -> bind_param("i", $_GET['gameid']);
			$result -> execute();
			
			$query = "DELETE FROM `game_resources` WHERE `game_idgame` = ?";
			$result = $conn -> prepare($query);
			//print_r($_POST);
			$result -> bind_param("i", $_GET['gameid']);
			$result -> execute();
			
			$query = "DELETE `combo_listing` FROM `combo_listing` 
JOIN `list` ON `list`.`idlist` = `combo_listing`.`idlist`
WHERE `list`.`game_idgame` = ?";
			$result = $conn -> prepare($query);
			//print_r($_POST);
			$result -> bind_param("i", $_GET['gameid']);
			$result -> execute();
			
			$query = "DELETE FROM `list` WHERE `game_idgame` = ?";
			$result = $conn -> prepare($query);
			//print_r($_POST);
			$result -> bind_param("i", $_GET['gameid']);
			$result -> execute();
			
			$query = "DELETE `combo` FROM `combo`
JOIN `character` ON `character`.`idcharacter` = `combo`.`character_idcharacter`
JOIN `game` ON `game`.`idgame` = `character`.`game_idgame`
WHERE `game`.`idgame` = ?";
			$result = $conn -> prepare($query);
			//print_r($_POST);
			$result -> bind_param("i", $_GET['gameid']);
			$result -> execute();
			
			$query = "DELETE FROM `character` WHERE `game_idgame` = ?";
			$result = $conn -> prepare($query);
			//print_r($_POST);
			$result -> bind_param("i", $_GET['gameid']);
			$result -> execute();
			
			$query = "DELETE FROM `button` WHERE `game_idgame` = ?";
			$result = $conn -> prepare($query);
			//print_r($_POST);
			$result -> bind_param("i", $_GET['gameid']);
			$result -> execute();
			
			$query = "DELETE FROM `game` WHERE `idgame` = ?";
			$result = $conn -> prepare($query);
			//print_r($_POST);
			$result -> bind_param("i", $_GET['gameid']);
			$result -> execute();
			
			header("Location: index.php");
			exit();
			
		}else if($_POST['action'] == 'Lock'){
			(get_gameComplete($conn)>0)?$i=2:$i=-1;
			$query = "UPDATE `game` SET `complete`= ? WHERE `idgame` = ?";
			$result = $conn -> prepare($query);
			$result -> bind_param("ii", $i,$_GET['gameid']);
			$result -> execute();	
		}else if($_POST['action'] == 'Unlock'){
			(get_gameComplete($conn)>0)?$i=1:$i=0;
			$query = "UPDATE `game` SET `complete`= ? WHERE `idgame` = ?";
			$result = $conn -> prepare($query);
			$result -> bind_param("ii", $i,$_GET['gameid']);
			$result -> execute();	
		}
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
				table();
			?>
		</style>
	</head>
	
	<body>
		<?php header_buttons(2, 1, 'game.php',get_gamename($_GET['gameid'], $conn)); ?>
			<div class="container-fluid my-3">
					<div class="form-group">
							
							<form method="post" action="editgame.php?gameid=<?php echo $_GET['gameid']?>">
						<?php
							$query = "SELECT name,image, patch, description, notation FROM game WHERE idgame = ?;";
							$result = $conn -> prepare($query);
							$result -> bind_param("i",$_GET['gameid']);
							$result -> execute();
							
							foreach($result -> get_result()	as $lol){
								echo '<div class="input-group mb-3">
									<div class="input-group-prepend">
										<span class="input-group-text">Title:
									</span></div>
									<input type="text" name="title" class="form-control" value="'.$lol['name'].'">
								</div>';
								echo '<div class="input-group mb-3">
									<div class="input-group-prepend">
										<span class="input-group-text">Current Version:
									</span></div>
									<input type="text" maxlength="10" name="patch" class="form-control" value="'.$lol['patch'].'">
								</div>';
								echo '<div class="input-group mb-3">
									<div class="input-group-prepend">
										<span class="input-group-text">Image:
									</span></div>
									<input type="text" name="image" class="form-control" value="'.$lol['image'].'">
								</div>';
								game_image($_GET['gameid'],250, $conn);
								echo '<div class="input-group mb-3">
									<div class="input-group-prepend">
										<span class="input-group-text">Description:
									</span></div>
									<textarea style="background-color: #474747; color:#999999;" name="description" class="form-control" id="description" rows="1" maxlength="255" title="description" placeholder="255 bytes brief description of the game page.">'.$lol['description'].'</textarea>
								</div>';
								echo '<div class="input-group mb-3">
									<div class="input-group-prepend">
										<span class="input-group-text">Notation Guidelines:
									</span></div>
									<textarea style="background-color: #474747; color:#999999;" name="notation" class="form-control" id="notation" rows="1" maxlength="950" title="notation" placeholder="1000 bytes guideline about combo notation.">'.$lol['notation'].'</textarea>
								</div>';
								echo '<div class="input-group mb-3">
									<div class="input-group-prepend">
										<span class="input-group-text">Moderation Password:
									</span></div>
									<input type="password" maxlength="16" name="modPass" class="form-control">
								</div>';
								echo '<div class="input-group mb-3">
									<div class="input-group-prepend">
										<span class="input-group-text">Game Password:
									</span></div>
									<input type="password" maxlength="16" name="password" class="form-control">
								</div>';
								
							}
							
							
						?>
							<div class="btn-group">
								<button type="submit" name="action" value="Submit" class="btn btn-primary btn-block">Update</button>
								<button type="submit" name="action" value="Delete" class="btn btn-danger btn-block" onclick="return confirm('Are you sure you want to delete this game?');">Delete</button>
								<?php game_lock($conn); ?>
							</div>
							</form>
							
							<?php
								edit_controls($_GET['gameid']);
								mysqli_close($conn);
							?>
							<p>Moderation password can be changed anytime. And it can be used to edit anything about the game if it is unlocked, if the game is locked Moderation Password can only edit submissions and lists. <br>It can not edit data from this page.</p>
					</div>
			</div>
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

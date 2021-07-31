<!doctype php>
<?php
	include_once "server/conexao.php";
	include_once "server/functions.php";
	if(!empty($_POST)){
		$_POST = array_map("strip_tags", $_POST);
		$_GET = array_map("strip_tags", $_GET);
		verify_password($conn);
		
		if($_POST['action'] == 'Submit'){
		
			$query = "UPDATE `combo` SET `combo`= REPLACE(`combo`, ?, ?) WHERE ";
			if($_POST['listingtype'] != '-' && $_POST['characterid'] != '-'){
				$query .= "`character_idcharacter` = ? AND `type` = ?";
				$result = $conn -> prepare($query);
				$result -> bind_param("ssii", $_POST['replace'], $_POST['with'], $_POST['characterid'], $_POST['listingtype']);
			}else if($_POST['characterid'] != '-'){
				$query .= "`character_idcharacter` = ?";
				$result = $conn -> prepare($query);
				$result -> bind_param("ssi", $_POST['replace'], $_POST['with'], $_POST['characterid']);
			}else if($_POST['listingtype'] != '-'){
				$query .= "`type` = ?";
				$result = $conn -> prepare($query);
				$result -> bind_param("ssi", $_POST['replace'], $_POST['with'], $_POST['listingtype']);
			}else{
				$query = "UPDATE `combo` JOIN `character` ON `combo`.`character_idcharacter` = `character`.`idcharacter`
JOIN `game` ON `game`.`idgame` = `character`.`game_idgame` SET `combo`= REPLACE(`combo`, ?, ?)  WHERE `game`.`idgame` = ?";
				$result = $conn -> prepare($query);
				$result -> bind_param("ssi", $_POST['replace'], $_POST['with'], $_GET['gameid']);
			}
			$result -> execute();
		}else if($_POST['action'] == 'SubmitResource'){
			if(!verify_gameresource($_POST['with'],$conn)){
				header("Location: index.php");
				exit();
			}
			$query = "UPDATE `resources` SET `Resources_values_idResources_values`= ? WHERE `Resources_values_idResources_values` = ?";
			$result = $conn -> prepare($query);
			$result -> bind_param("ii", $_POST['with'],$_POST['replace']);
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
		<?php header_buttons(2, 1, 'game.php', get_gamename($_GET['gameid'], $conn));?>
			<div class="container-fluid my-3">
				<div class="form-group">
					<?php
						
						echo '<table id="myTable" class="table table-hover align-middle caption-top combosuki-main-reversed text-white">';
						echo '<tr>';
						echo '<th>Mass Edit Submissions</th';
						echo '</tr>';
						
						echo '<tr><td>';
							echo '<form method="post" action="editcombos.php?gameid='.$_GET['gameid'].'">';
							character_dropdown($conn);
							entry_select(0,2,$conn);
							echo '<div class="input-group"><textarea name="replace" maxlength="45" style="background-color: #474747; color:#ffffff;" class="form-control" rows="1" placeholder="Replace"></textarea>';
							
							echo '<textarea name="with" maxlength="45" style="background-color: #474747; color:#ffffff;" class="form-control" rows="1" placeholder="With"></textarea>';
							echo '
  <input name="gamePass" type="password" maxlength="16" style="background-color: #474747; color:#999999;" class="form-control" rows="1" placeholder="Game Password">
  <div class="input-group-append" id="button-addon4">
    <button type="submit" name="action" value="Submit" class="btn btn-primary"';
							if(1):?>
							onclick="return confirm('Are you sure you want to mass edit combos?');"
							<?php
							endif;
							echo '>Mass Edit</button>
  </div>
</div>';
							echo '</td>';
							echo '</form>';
							echo '</tr>';
						
						echo '</table>';
						?>
						<h3> How does this work? </h3>
						Select a character and a type of entry. Then on the "Replace" box type what you want to replace, "qcf" for example. And then on "With" type what "qcf" should be replaced with, we will got with "236".
						Type the game password and then submit, now every entry of the character and type of entry you selected will have qcf replaced with 236.
						<?php
						echo '<table id="myTable" class="table table-hover align-middle caption-top combosuki-main-reversed text-white">';
						echo '<tr>';
						echo '<th>Mass Edit Resources</th';
						echo '</tr>';
						
						echo '<tr><td>';
							echo '<form method="post" action="editcombos.php?gameid='.$_GET['gameid'].'">';
							echo '<div class="input-group"><textarea name="replace" maxlength="45" style="background-color: #474747; color:#ffffff;" class="form-control" rows="1" placeholder="Replace Resource ID"></textarea>';
							
							echo '<textarea name="with" maxlength="45" style="background-color: #474747; color:#ffffff;" class="form-control" rows="1" placeholder="With Resource ID"></textarea>';
							echo '
  <input name="gamePass" type="password" maxlength="16" style="background-color: #474747; color:#999999;" class="form-control" rows="1" placeholder="Game Password">
  <div class="input-group-append" id="button-addon4">
    <button type="submit" name="action" value="SubmitResource" class="btn btn-primary"';
							if(1):?>
							onclick="return confirm('Are you sure you want to mass edit resources?');"
							<?php
							endif;
							echo '>Mass Edit</button>
  </div>
</div>';
							echo '</td>';
							echo '</form>';
							echo '</tr>';
						
						echo '</table>';
						?>
						<h3> How does this work? </h3>
						Just like the example above, type the ID of the resource you want to be replaced on the "Replace" box, and the ID of the resource you want to replace in the "With" box.
						You can check their IDs on the Edit Resources page.<br>
						
						<?php
						edit_controls($_GET['gameid']);
						mysqli_close($conn);
					?>
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
		<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js"></script>
</html>

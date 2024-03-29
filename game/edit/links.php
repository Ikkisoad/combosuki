<!doctype php>
<?php
	$URLDepth = '../../';
	require_once "../../server/initialize.php";
	if(!empty($_POST)){
		$_POST = array_map("strip_tags", $_POST);
		$_GET = array_map("strip_tags", $_GET);
		//print_r($_POST);
		verify_password();
		
		if($_POST['action'] == 'Update'){
			$query = "UPDATE `link` SET `Title`=?,`Link`=? WHERE `idLink` = ?";
			$result = $conn -> prepare($query);
			$result -> bind_param("ssi", $_POST['title'], $_POST['link'], $_POST['idLink']);
			$result -> execute();
		}else if($_POST['action'] == 'Delete'){
			$query = "DELETE FROM `link` WHERE `idLink` = ?";
			$result = $conn -> prepare($query);
			//print_r($_POST);
			$result -> bind_param("i", $_POST['idLink']);
			$result -> execute();
		}else if($_POST['action'] == 'Add'){
			$query = "INSERT INTO `link`(`idLink`, `idGame`, `Title`, `Link`) VALUES (NULL, ?, ?, ?)";
			$result = $conn -> prepare($query);
			$result -> bind_param("iss", $_GET['gameid'], $_POST['title'], $_POST['link']);
			$result -> execute();
			
		}
	}
?>
<html>
	<head>
		
		<?php headerHTML(); ?>

		<meta property="og:title" content="Combo好き" />
		<meta property="og:type" content="website" />
		<meta property="og:image" content="http://combosuki.com/img/combosuki.png" />
		<meta property="og:url" content="http://combosuki.com/index.php" />
		<meta property="og:description" 
		content="Community-fueled searchable environment that shares and perfects combos." />
		<meta name="theme-color" content="#C62114" />

		<meta name="description" content="Community-fueled searchable environment that shares and perfects combos.">
		<title>Combo好き</title>
		<?php getCSS(); ?>

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
						
						$query = "SELECT * FROM `link` WHERE `idGame` = ? ORDER BY `title`;";
						$result = $conn -> prepare($query);
						$result -> bind_param("i",$_GET['gameid']);
						$result -> execute();
						//print_r($result);
						
						//$game -> get_result()
						echo '<table id="myTable" class="table table-hover align-middle caption-top combosuki-main-reversed text-white">';
						echo '<tr>';
						echo '<th>Link</th';
						echo '</tr>';
						foreach($result -> get_result()	as $lol){
							
							echo '<tr><td>';
							echo '<form method="post" action="links.php?gameid='.$_GET['gameid'].'">';
							echo '<div class="input-group"><textarea name="title" maxlength="50" style="background-color: #474747; color:#ffffff;" class="form-control" rows="1" placeholder="Link Title">'.$lol['Title'].'</textarea>';					
							echo '<textarea name="link" maxlength="255" style="background-color: #474747; color:#ffffff;" class="form-control" rows="1" placeholder="Link URL">'.$lol['Link'].'</textarea>';
							echo '
  <input name="gamePass" type="password" maxlength="16" style="background-color: #474747; color:#999999;" class="form-control" rows="1" placeholder="Game Password">
  <div class="input-group-append" id="button-addon4">
  <input type="hidden" name="idLink" value="'.$lol['idLink'].'">
    <button type="submit" name="action" value="Update" class="btn btn-primary">Update</button>
    <button type="submit" name="action" value="Delete" class="btn btn-danger" ';
							if(1):?>
							onclick="return confirm('Are you sure you want to delete this link?');"
							<?php
							endif;
							echo ' >Delete</button>
  </div>
</div>';
							echo '</td>';
							echo '</form>';
							echo '</tr>';
						
						}
						
						echo '<tr><td>';
							echo '<form method="post" action="links.php?gameid='.$_GET['gameid'].'">';
							echo '<div class="input-group"><textarea name="title" maxlength="50" style="background-color: #474747; color:#ffffff;" class="form-control" rows="1" placeholder="Link Title" autofocus></textarea>';					
							echo '<textarea name="link" maxlength="255" style="background-color: #474747; color:#ffffff;" class="form-control" rows="1" placeholder="Link URL"></textarea>';
							echo '
  <input name="gamePass" type="password" maxlength="16" style="background-color: #474747; color:#999999;" class="form-control" rows="1" placeholder="Game Password">
  <div class="input-group-append" id="button-addon4">
    <button type="submit" name="action" value="Add" class="btn btn-primary">Add</button>
  </div>
</div>';
							echo '</td>';
							echo '</form>';
							echo '</tr>';
						
						echo '</table><br>';
						
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
		<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js"></script>
</html>

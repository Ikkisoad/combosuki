<!doctype php>
<?php
	$URLDepth = '../../';
	require_once "../../server/initialize.php";
	if(!empty($_POST)){
		$_POST = array_map("strip_tags", $_POST);
		$_GET = array_map("strip_tags", $_GET);
		verify_password($conn);
		
		if($_POST['action'] == 'Update'){
		
			$query = "UPDATE `game_entry` SET `title`= ?, `order` = ? WHERE `entryid` = ?";
			$result = $conn -> prepare($query);
			$result -> bind_param("sii", $_POST['entry'], $_POST['order'], $_POST['entryid']);
			$result -> execute();
		}else if($_POST['action'] == 'Delete'){
			
			$query = "DELETE FROM `game_entry` WHERE `entryid` = ?";
			$result = $conn -> prepare($query);
			$result -> bind_param("i", $_POST['entryid']);
			$result -> execute();
			
			
		}else if($_POST['action'] == 'Add'){
			insertEntry($_POST['entry'], $_GET['gameid'], $_POST['order']);
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
						$query = "SELECT `entryid`, `title`, `order` FROM `game_entry` WHERE `gameid` = ? ORDER BY `order`,`title`";
						$result = $conn -> prepare($query);
						$result -> bind_param("i",$_GET['gameid']);
						$result -> execute();
						//print_r($result);
						
						//$game -> get_result()
						echo '<table class="table table-hover align-middle caption-top combosuki-main-reversed text-white">';
						echo '<tr>';
						echo '<th>Entry</th>';
						echo '</tr>';
						foreach($result -> get_result()	as $lol){
							
							echo '<tr><td>';
							echo '<form method="post" action="entries.php?gameid='.$_GET['gameid'].'">';
							echo '<div class="input-group"><textarea name="entry" maxlength="45" style="background-color: #474747; color:#ffffff;" class="form-control" rows="1" placeholder="Entry Title">'.$lol['title'].'</textarea>';
							echo '<input class="form-control" type="number" name="order" placeholder="Order" value="'.$lol['order'].'" step="any">';
							echo '
  <input name="gamePass" type="password" maxlength="16" style="background-color: #474747; color:#999999;" class="form-control" rows="1" placeholder="Game Password">
  <div class="input-group-append" id="button-addon4">
  <input type="hidden" name="entryid" value="'.$lol['entryid'].'">
    <button type="submit" name="action" value="Update" class="btn btn-primary">Update</button>
    <button type="submit" name="action" value="Delete" class="btn btn-danger" ';
							if(1):?>
							onclick="return confirm('Are you sure you want to delete this entry?');"
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
							echo '<form method="post" action="entries.php?gameid='.$_GET['gameid'].'">';
							echo '<div class="input-group"><textarea name="entry" maxlength="45" style="background-color: #474747; color:#ffffff;" class="form-control" rows="1" placeholder="Entry Name" autofocus></textarea>';
							echo '<input class="form-control" type="number" name="order" placeholder="Order" value="" step="any">';
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
					<p>Entry types are the way you categorize submissions, a few examples are: Combo, Blockstring, Okizeme...</p>
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

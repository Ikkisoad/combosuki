<!doctype php>
<?php
	$URLDepth = '../';
	require_once "../server/initialize.php";
	if(!empty($_POST)){
		if($_POST['action'] == 'Submit'){
			if($_POST['gameName'] == '' || $_POST['gameImage'] == '' || $_POST['gamePass'] == ''){
				redictIndex();
			}
			$gameid = insertGame($_POST['gameName'], $_POST['gameImage'], $_POST['gamePass']);
			insertDefaultButtons($gameid);
			insertDefaultCharacter($gameid);
			insertDefaultResource($gameid);
			insertDefaultEntry($gameid);
			header("Location: ".$URLDepth."game/edit/game.php?gameid=".$gameid."");
			exit();
		}
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
				table();
			?>
		</style>
	</head>
	
	<body>
		<?php header_buttons(1, 0, 0, 0);?>
			<div class="container">
				<div class="form-group">
					<form method="post" action="add.php">
						<div class="input-group mb-3">
							<div class="input-group-prepend">
								<span class="input-group-text">Game Name </span></div>
							<input class="form-control" type="text" name="gameName" required> 
						</div>
						<div class="input-group mb-3">
							<div class="input-group-prepend">
								<span class="input-group-text">Game Image </span></div>
							<input class="form-control" type="text" name="gameImage" required> 
						</div>
						
						<?php
							mysqli_close($conn);
						 ?>
						<label for="exampleFormControlTextarea1">Game Password:</label>
						<input name="gamePass" type="password" maxlength="16" required style="background-color: #474747; color:#999999;" class="form-control" rows="1" placeholder="Refrain from using personal passwords.">
						<p class="mt-3"><button type="submit" name="action" value="Submit" class="btn btn-primary btn-block">Submit</button></p>
					</form>
				</div>
				<p>Game Password can be used to edit EVERYTHING related to the game entry, submissions, resources, buttons, characters, lists etc.</p>
				<p>Game Password can not be changed by users, so please careful. If you somehow end up needing to change it, please contact me @Ikkisoad.</p>
				<?php
				embed_video_notable('https://youtu.be/59b63HqIz_g');
				?>
			</div>
		<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js" integrity="sha384-gtEjrD/SeCtmISkJkNUaaKMoLD0//ElJ19smozuHV6z3Iehds+3Ulb9Bn9Plx0x4" crossorigin="anonymous"></script>
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

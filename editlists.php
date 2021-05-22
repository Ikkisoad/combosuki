<!doctype php>
<?php
	include_once "server/conexao.php";
	include_once "server/functions.php";
	if(!empty($_POST)){
		$_POST = array_map("strip_tags", $_POST);
		$_GET = array_map("strip_tags", $_GET);
		verify_password($conn);
		
		if($_POST['action'] == 'Update'){
			if(isset($_POST['listname'])){
				if($_POST['listpass'] != ''){
					$query = "UPDATE `list` SET `list_name`= ?,`password`= ?,`type`= ? WHERE `idlist` = ? AND `game_idgame` = ?";
					$result = $conn -> prepare($query);
					$result -> bind_param("ssiii", $_POST['listname'], $_POST['listpass'], $_POST['type'], $_POST['idlist'], $_GET['gameid']);
					$result -> execute();
				}else{
					$query = "UPDATE `list` SET `list_name`= ?,`type`= ? WHERE `idlist` = ? AND `game_idgame` = ?";
					$result = $conn -> prepare($query);
					$result -> bind_param("siii", $_POST['listname'], $_POST['type'], $_POST['idlist'], $_GET['gameid']);
					$result -> execute();
				}
			}else{
				if($_POST['listpass'] != ''){
					$query = "UPDATE `list` SET `password`=?,`type`=? WHERE `idlist` = ? AND `game_idgame` = ?";
					$result = $conn -> prepare($query);
					$result -> bind_param("siii", $_POST['listpass'], $_POST['type'], $_POST['idlist'], $_GET['gameid']);
					$result -> execute();
				}else{
					$query = "UPDATE `list` SET `type`=? WHERE `idlist` = ? AND `game_idgame` = ?";
					$result = $conn -> prepare($query);
					$result -> bind_param("iii", $_POST['type'], $_POST['idlist'], $_GET['gameid']);
					$result -> execute();
				}
				
			}
			
		}else if($_POST['action'] == 'Delete'){
			$query = "DELETE FROM `combo_listing` WHERE `idlist` = ?";
			$result = $conn -> prepare($query);
			$result -> bind_param("i", $_POST['idlist']);
			$result -> execute();
			
			$query = "DELETE FROM `list` WHERE `idlist` = ?";
			$result = $conn -> prepare($query);
			$result -> bind_param("i", $_POST['idlist']);
			$result -> execute();
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

		<title>Combo好き</title>
    <!-- Bootstrap core CSS -->
		<link href="css/bootstrap.min.css" rel="stylesheet">

    <!-- Custom styles for this template 
		<link href="jumbotron.css" rel="stylesheet"> -->
		<style>
			body{
				background-color: #190000;
				background: url("img/<?php
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
			
			table {
				border-spacing: 0;
				width: 100%;
				border: 1px solid #ddd;
				
			}
			
			th, td {
				text-align: center;
				padding:7px;
			}

			tr:nth-child(even) {
				background-color: #212121
			}
			
			tr:nth-child(odd) {
				background-color: #000000
			}
		</style> <!-- BACKGROUND COLOR-->
	</head>
	
	<body>
		<main role="main">
			<div class="container">
				<div class="form-group">
					<?php
						header_buttons(2, 1, 'game.php', get_gamename($_GET['gameid'], $conn));
						$query = "SELECT * FROM `list` WHERE `type` != 1 AND `game_idgame` = ? ORDER BY `type` DESC,`list_name`;";
						$result = $conn -> prepare($query);
						$result -> bind_param("i", $_GET['gameid']);
						$result -> execute();
						
						echo '<table>';
						echo '<tr>';
						echo '<th>List</th';
						echo '</tr>';
						foreach($result -> get_result()	as $lol){
							
							echo '<tr><td>';
							echo '<form method="post" action="editlists.php?gameid='.$_GET['gameid'].'">';
							echo '<div class="input-group"><button class="btn btn-secondary" disabled>ID:'.$lol['idlist'].'</button><textarea name="listname" maxlength="50" style="background-color: #474747; color:#ffffff;" class="form-control" rows="1" placeholder="List Name">'.$lol['list_name'].'</textarea>';					
							echo '<input name="listpass" type="password" maxlength="16" style="background-color: #474747; color:#999999;" class="form-control" rows="1" placeholder="List Password">';
							echo '<select name="type" class="custom-select">
									<option value="0">Flagged</option>
									<option value="1">Normal</option>
									<option value="2" ';
									
							if($lol['type'] == 2) echo 'selected';
							echo '>Verified</option>
									<option value="3" ';
							if($lol['type'] == 3) echo 'selected';
							echo '>Moderated</option>
								  </select>';
							echo '
  <input name="gamePass" type="password" maxlength="16" style="background-color: #474747; color:#999999;" class="form-control" rows="1" placeholder="Game Password">
  <div class="input-group-append" id="button-addon4">
  <input type="hidden" name="idlist" value="'.$lol['idlist'].'">
    <button type="submit" name="action" value="Update" class="btn btn-primary">Update</button>
    <button type="submit" name="action" value="Delete" class="btn btn-danger" ';
							if(1):?>
							onclick="return confirm('Are you sure you want to delete this list?');"
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
							echo '<form method="post" action="editlists.php?gameid='.$_GET['gameid'].'">';
							echo '<div class="input-group"><textarea name="idlist" maxlength="50" style="background-color: #474747; color:#ffffff;" class="form-control" rows="1" placeholder="List ID" autofocus></textarea>';					
							echo '<input name="listpass" type="password" maxlength="16" style="background-color: #474747; color:#999999;" class="form-control" rows="1" placeholder="List Password">';
							echo '<select name="type" class="custom-select">
									<option value="0">Flagged</option>
									<option value="1" selected>Normal</option>
									<option value="2">Verified</option>
									<option value="3">Moderated</option>
								  </select>';
							echo '
  <input name="gamePass" type="password" maxlength="16" style="background-color: #474747; color:#999999;" class="form-control" rows="1" placeholder="Game Password">
  <div class="input-group-append" id="button-addon4">
    <button type="submit" name="action" value="Update" class="btn btn-primary">Update</button>
    <button type="submit" name="action" value="Delete" class="btn btn-danger" ';
							if(1):?>
							onclick="return confirm('Are you sure you want to delete this list?');"
							<?php
							endif;
							echo ' >Delete</button>
  </div>
</div>';
							echo '</td>';
							echo '</form>';
							echo '</tr>';
						
						echo '</table><br>';
						
						edit_controls($_GET['gameid']);
						mysqli_close($conn);
					?>
					<p>If someone tries to delete a list with moderator password the list will appear here as flagged.</p>
					<p>Moderated lists have preference in searches and will always appear on top, followed by verified lists and then normal lists. Flagged lists do not show up.</p>
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

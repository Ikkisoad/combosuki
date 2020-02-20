<?php
	include_once "server/conexao.php";
	include_once "server/functions.php";
		if(!empty($_POST)){
			$_POST = array_map("strip_tags", $_POST);
			$_GET = array_map("strip_tags", $_GET);
			if($_POST['action'] != 'Search'){
				if($_POST['submission_type'] == 1){
					if($_POST['list_name'] == '' || $_POST['gameid'] == 0){                                           //This prevents '' lists from being created
						header("Location: list.php");
						exit();	
					}
					$query = "INSERT INTO `list`(`list_name`, `game_idgame`, `password`, `type`) VALUES (?,?,?,?)";
					$result = $conn -> prepare($query);
					$i = 1;
					$result -> bind_param("sisi", $_POST['list_name'], $_POST['gameid'], $_POST['listPass'], $i);
					$result -> execute();
							
					$listid = mysqli_insert_id($conn);
					header("Location: list.php?listid=".$listid."");
					exit();
				}else if($_POST['submission_type'] == 2){
					if($_POST['action'] == 'DeleteList'){
						$query = "SELECT `password`, `game_idgame` FROM `list` WHERE idlist = ?";
						$result = $conn -> prepare($query);
						$result -> bind_param("i",$_GET['listid']);
						$result -> execute();
						foreach($result -> get_result() as $lul){
							$modPass = get_mod_password($lul['game_idgame'], $conn);
							if(password_verify($_POST['listPass'], $modPass)){
								$query = "UPDATE `list` SET `type`=? WHERE `idlist` = ?";
								$result = $conn -> prepare($query);
								$i = 0;
								$result -> bind_param("ii", $i, $_GET['listid']);
								$result -> execute();
							}
							$gamepass = get_gamepassword($lul['game_idgame'], $conn);
							if($lul['password'] == $_POST['listPass'] || $_POST['listPass'] == $gamepass){
								$query = "DELETE FROM `combo_listing` WHERE `idlist` = ?";
								$result = $conn -> prepare($query);
								$result -> bind_param("i", $_GET['listid']);
								$result -> execute();
								
								$query = "DELETE FROM `list` WHERE `idlist` = ?";
								$result = $conn -> prepare($query);
								$result -> bind_param("i", $_GET['listid']);
								$result -> execute();
								
											
								header("Location: list.php");
								exit();
							
							}
						}
					}
					
					if(isset($_POST['idlist']))$_GET['listid'] = $_POST['idlist'];
					
					$query = "SELECT `character`.`game_idgame` FROM `combo` INNER JOIN `character` ON `character`.`idcharacter` = `combo`.`character_idcharacter` WHERE `combo`.`idcombo` = ?";
					$result = $conn -> prepare($query);
					$result -> bind_param("i",$_POST['comboid']);
					$result -> execute();
					foreach($result -> get_result() as $lul){
							$gameid = $lul['game_idgame'];
					}
					
					$query = "SELECT `game_idgame`,`password` FROM `list` WHERE idlist = ?";
					$result = $conn -> prepare($query);
					$result -> bind_param("i",$_GET['listid']);
					$result -> execute();
					foreach($result -> get_result() as $lul){
						$gamepass = get_gamepassword($lul['game_idgame'], $conn);
						if($gameid != $lul['game_idgame'] && $lul['game_idgame'] != 0){
							header("Location: list.php?listid=".$_GET['listid']."");
							exit();
						}
						$modPass = get_mod_password($lul['game_idgame'], $conn);
						if($lul['password'] == $_POST['listPass'] || password_verify($_POST['listPass'], $modPass) || $_POST['listPass'] == $gamepass){
							if($_POST['action'] == 'Submit'){
								$query = "INSERT INTO `combo_listing`(`idcombo`, `idlist`, `comment`) VALUES (?,?,?)";
								$result = $conn -> prepare($query);
								$result -> bind_param("iis", $_POST['comboid'], $_GET['listid'], $_POST['comment']);
								$result -> execute();
							}
							if($_POST['action'] == 'Delete'){
								$query = "DELETE FROM `combo_listing` WHERE `idcombo` = ? AND `idlist` = ?";
								$result = $conn -> prepare($query);
								$result -> bind_param("ii", $_POST['comboid'], $_GET['listid']);
								$result -> execute();
							}
						}else{
							header("Location: list.php?listid=".$_GET['listid']."");
							exit();
						}
					}
					
				}
			}
		}
						
?>

<!doctype php>
<html>
	<head>
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
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=yes">
		<meta name="description" content="">
		<meta name="Ikkisoad" content="">
		<meta name="description" content="Community-fueled searchable environment that shares and perfects combos.">

		<title>Combo好き</title>
		<!-- Bootstrap core CSS -->
		<link href="css/bootstrap.min.css" rel="stylesheet">

    <!-- Custom styles for this template -->
		<!-- <link href="jumbotron.css" rel="stylesheet"> -->
		
		<style>
			body{
				padding: 0;
				margin: 0;
				background-color: #00190f;
				background: url("img/<?php
					/*if(!isset($_SESSION)){
						session_start();
					}*/
					
					if(isset($_COOKIE['color'])){
						echo 'bg/'.$_COOKIE["color"].'honeycomb.png';
					}else{
						echo 'dark-honeycomb.png';
					}
				
				?>");
				color: white;
			}
			.container{
			  
			 
			  height: 100vh;
			}
			.jumbotron{
				background: url("img/black-honeycomb.png");
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
	</head>
	
	<body>
	
	<div class="jumbotron jumbotron-fluid">
		<div class="container">
			<h1 class="display-4"></h1>
				<p class="lead">
					<a href="index.php"><img src="img/combosuki.png" align="middle" height="150" ></a>
				</p>
		</div>
	</div>
	
		<main role="main">
			
			<div class="container">
				<div class="body">					
						
								<?php 
									
									$_GET = array_map("strip_tags", $_GET);
									
									if(isset($_GET['listid'])){
										$query = "SELECT list_name, name, type, game_idgame FROM `list` join game ON list.game_idgame = game.idgame where list.idlist = ?";
										$result = $conn -> prepare($query);
										$result -> bind_param("i", $_GET['listid']);
										$result -> execute();
										foreach($result -> get_result() as $list){
											$_GET['gameid'] = $list['game_idgame'];
											header_buttons(2, 1, 'game.php',get_gamename($_GET['gameid'], $conn));
											echo '<h3 class="mt-3">'.$list['list_name'];
											print_listglyph($list['type'], $conn);
											echo '</h3>';
											echo $list['name'].' list';
										}
										copyLinktoclipboard('https://combosuki.com/list.php?listid='.$_GET['listid']);
										echo '<p>';
										
										echo '</p>';
										
										$query = "SELECT `combo_listing`.`idcombo`, `comment`, `combo`.`damage`, `character`.`Name`, `combo`.`combo`, `combo`.`comments`,`combo`.`video`,`combo`.`type` FROM `combo_listing` 
INNER JOIN `combo` ON `combo`.`idcombo` = `combo_listing`.`idcombo` 
LEFT JOIN `character` ON `character`.`idcharacter` = `combo`.`character_idcharacter` 
WHERE `idlist` = ? ORDER BY `comment`, `combo`.`damage` DESC;";
										$result = $conn -> prepare($query);
										$result -> bind_param("i", $_GET['listid']);
										$result -> execute();
										echo '<table id="myTable">';
						echo '<tr>';
						echo '<th>Character</th><th>Inputs</th><th>Damage</th><th>Type</th>';
						echo '</tr>';
						$comment = '';
						
						foreach($result -> get_result() as $data){
							if($comment != $data['comment']){
								echo '</table>';
								echo '<h2 class="mt-3">'.$data['comment'].'</h2>';
								echo '<table>';
								$comment = $data['comment'];
							}
							echo '<tr><td data-toggle="tooltip" data-placement="bottom" title="'.$data['comments'].'">';
							if($data['comments'] != '' || $data['video'] != ''){
								echo '<button class="btn btn-dark" onclick="showDIV('.$data['idcombo'].')">'.$data['Name'].'</button>';
							}else{
								echo $data['Name'];
							}
							echo '</td><td style="min-width:400px">';
							echo		'<a data-toggle="tooltip" data-placement="bottom" title="'.$data['comments'].'" href="combo.php?idcombo='.$data['idcombo'].'&listid='.$_GET['listid'].'">'.$data['combo'].'</a>';
							echo '<div id="'.$data['idcombo'].'" style="display: none;">';
							echo $data['comments'].'<br>';
  
  //####################################################################VIDEO HERE
  
							embed_video_notable($data['video']);
  
  //######################################################################VIDEO ABOVE
  
							echo '</div>';
							echo '</td><td>';
							echo number_format($data['damage'],'0','','.');
							echo '</td><td>';
							print_listingtype($data['type'], $conn);
							echo '</td>';
						}
						echo '</table>';
										
						edit_listForm();
									}else{
										header_buttons(1, 0, 'list.php',0);
										echo '<h3>Listing</h3>
					<p><form class="form-inline" method="post" action="list.php">
							<input type="hidden" name="submission_type" value="1">
							<div class="form-group mb-2"><input placeholder="List Name" style="background-color: #474747; color:#999999;" name="list_name" class="form-control" maxlength="45" rows="1"></input></div>
							<div class="form-group mb-2">';
									
										$query = "SELECT `idgame`, `name` FROM `game` WHERE `complete` > 0 ORDER BY `name`";
										$result = $conn -> prepare($query);
										$result -> execute();
											
										echo '<select name="gameid" class="custom-select">';
											
										echo '<option value="0">Game</option>';
										foreach($result -> get_result() as $game){
											echo '<option value="'.$game['idgame'].'" ';
											if(isset($_POST)){
												if(isset($_POST['gameid'])){
													if($_POST['gameid'] == $game['idgame']){
														echo 'selected';
													}
												}
											}
											echo '>'.$game['name'].'</option>';
										}
											
										echo '</select>'; 
										echo '</div>
							<div class="form-group mb-2"><input placeholder="List Password" name="listPass" type="password" maxlength="16" style="background-color: #474747; color:#999999;" class="form-control" rows="1"></input></div>
							<div class="form-group mb-2"><button type="submit" name="action" value="Search" class="btn btn-info btn-block">Search</button></div>
							<div class="form-group mb-2"><button type="submit" name="action" value="Submit" class="btn btn-primary btn-block">Create List</button></div>
						</form></p>';
										if(isset($_POST)){
											if(isset($_POST['action'])){
												if($_POST['action'] == 'Search'){
														$query = "SELECT `idlist`, `list_name`, `type` FROM `list` WHERE `list_name` LIKE ? AND `game_idgame` = ? AND `list`.`type` != 0 ORDER BY `type` DESC, `list_name` LIMIT 0,50";
														$result = $conn -> prepare($query);
														$_POST['list_name'] = '%'.$_POST['list_name'].'%';
														$result -> bind_param("si",$_POST['list_name'], $_POST['gameid']);
														$result -> execute();
														echo '<table id="myTable">';
														echo '<tr>';
														echo '<th>List Name</th>';
														echo '</tr>';
														foreach($result -> get_result() as $search){
															if($search['list_name'] != ''){
																echo '<tr><td><a  href="list.php?listid='.$search['idlist'].'">'.$search['list_name'].'</a>';
																print_listglyph($search['type'], $conn);
																echo '</tr></td>';
															}
														}
													
												}
											}else{
											$query = "SELECT `idlist`, `list_name`, `type` FROM `list` ORDER BY `idlist` DESC LIMIT 0,50";
											$result = $conn -> prepare($query);
											$result -> execute();
											echo '<table id="myTable">';
											echo '<tr>';
											echo '<th>List Name</th>';
											echo '</tr>';
											foreach($result -> get_result() as $search){
												if($search['list_name'] != ''){
													echo '<tr><td><a  href="list.php?listid='.$search['idlist'].'">'.$search['list_name'].'</a>';
													print_listglyph($search['type'], $conn);
													echo '</tr></td>';
												}
											}
										}
										}
									}
									
									mysqli_close($conn);
								?>
							
					
				</div>
			</div>
		</main>
	</body>
	    <!-- Bootstrap core JavaScript
		================================================== -->
		<!-- Placed at the end of the document so the pages load faster -->
		<script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>
		<script>window.jQuery || document.write('<script src="../../../../assets/js/vendor/jquery.min.js"><\/script>')</script>
		<script>
			function showDIV(DIV_ID) {
				var x = document.getElementById(DIV_ID);
				if (x.style.display === "none") {
					x.style.display = "block";
				}else{
					x.style.display = "none";
				}
			}
		</script>
		<script>
			function copytoclip(link) {
				var dummy = document.createElement("textarea");
				document.body.appendChild(dummy);
				dummy.value = link;
				dummy.select();
				document.execCommand("copy");
				document.body.removeChild(dummy);
			}
		</script>
</html>

<?php
	include_once "server/conexao.php";
	include_once "server/functions.php";
		if(!empty($_POST)){
			$_POST = array_map("strip_tags", $_POST);
			$_GET = array_map("strip_tags", $_GET);
			if($_POST['action'] != 'Search'){
				if($_POST['submission_type'] == 1){
					/*if($_POST['list_name'] == ''){ //This prevents '' lists from being created
						header("Location: list.php");
						exit();	
					}*/
					$query = "INSERT INTO `list`(`list_name`, `game_idgame`, `password`) VALUES (?,?,?)";
					$result = $conn -> prepare($query);
					$result -> bind_param("sis", $_POST['list_name'], $_POST['gameid'], $_POST['listPass']);
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
							if($lul['password'] == $_POST['listPass'] || password_verify($_POST['listPass'], $modPass)){
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
						if($gameid != $lul['game_idgame'] && $lul['game_idgame'] != 0){
							header("Location: list.php?listid=".$_GET['listid']."");
							exit();
						}
						$modPass = get_mod_password($lul['game_idgame'], $conn);
						if($lul['password'] == $_POST['listPass'] || password_verify($_POST['listPass'], $modPass)){
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
									header_buttons(1, 0, 'list.php');
									$_GET = array_map("strip_tags", $_GET);
									
									if(isset($_GET['listid'])){
										$query = "SELECT list_name, name FROM `list` join game ON list.game_idgame = game.idgame where list.idlist = ?";
										$result = $conn -> prepare($query);
										$result -> bind_param("i", $_GET['listid']);
										$result -> execute();
										foreach($result -> get_result() as $list){
											echo '<h3 class="mt-3">'.$list['list_name'].'</h3>';
											echo $list['name'];
										}
										
										echo '<p>
					</p>';
										
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
							echo		'<a data-toggle="tooltip" data-placement="bottom" title="'.$data['comments'].'" href="combo.php?idcombo='.$data['idcombo'].'">'.$data['combo'].'</a>';
							echo '<div id="'.$data['idcombo'].'" style="display: none;">';
							echo $data['comments'].'<br>';
  
  //####################################################################VIDEO HERE
  
							if($data['video'] != ''){
								if (strpos($data['video'], 'twitter') !== false && strpos($data['video'], 'https') !== false) {
									echo '<blockquote class="twitter-tweet" data-conversation="none" data-lang="en"><p lang="en" dir="ltr">
									<a href="';
									echo $data['video'];
									echo '"></a>
								</blockquote>
								<script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>';
								}else if (strpos($data['video'], 'youtu') !== false) {
									preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $data['video'], $match);
									$youtube_id = $match[1];
									//print_r($match);
									//echo '<br> URL: ';
									//echo $youtube_id;
									$whatIWant = substr($data['video'], strpos($data['video'], "=") + 1);    
									//echo '<br>what I want:';
									//echo $whatIWant;
									echo '<div class="embed-responsive embed-responsive-16by9">
									<iframe class="embed-responsive-item" src="https://www.youtube.com/embed/';
									echo $youtube_id;
									echo '?start=';echo $whatIWant; echo '" allowfullscreen></iframe></div>';
								}else if(strpos($data['video'], 'streamable') !== false && strpos($data['video'], 'https') !== false){
														/*echo '<div class="embed-responsive embed-responsive-16by9">
														<iframe class="embed-responsive-item" src="';
														echo $data['video'];
														echo '" allowfullscreen></iframe>
													</div>';*/
													//$i = substr_replace($data['video'], "/s", 22,0);
									echo '<div style="width: 100%; height: 0px; position: relative; padding-bottom: 56.250%;">
									<iframe src="';
									echo $data['video'];
									echo '" frameborder="0" width="100%" height="100%" allowfullscreen style="width: 100%; height: 100%; position: absolute;">
									</iframe>
									</div>';
														
														
														/*echo '<div style="width: 100%; height: 0px; position: relative; padding-bottom: 56.250%;"><iframe src="';
														echo $streamable;
														
														echo '" frameborder="0" width="100%" height="100%" allowfullscreen style="width: 100%; height: 100%; position: absolute;"></iframe></div>';*/
														
								}else if(strpos($data['video'], 'twitch') !== false && strpos($data['video'], 'clips') !== false && strpos($data['video'], 'https') !== false){
									$i = substr_replace($data['video'], "embed?autoplay=false&clip=", 24,0);
									echo '<iframe
										src="'.$i.'"
										preload="none"
										autoplay="false"
										height="360"
										width="640"
										frameborder="0"
										scrolling="no"
										allowfullscreen="true">
									</iframe>';
									
								}/*else if(strpos($data['video'], 'imgur') !== false && strpos($data['video'], 'https') !== false){
									//echo '<blockquote class="imgur-embed-pub" lang="en" data-id="a/amuAPtr"><a href="//imgur.com/amuAPtr">Jesus saves</a></blockquote><script async src="//s.imgur.com/min/embed.js" charset="utf-8"></script>'; https://imgur.com/sJyThyf
									$i = substr($data['video'], 18);
									echo '<blockquote class="imgur-embed-pub" lang="en" data-id="';
									echo $i;
									echo '"><a href="';
									echo $data['video'];
									echo '"></a></blockquote><script async src="//s.imgur.com/min/embed.js" charset="utf-8"></script>';
								}*/else{
									echo $data['video'];
								}
							}
  
  //######################################################################VIDEO ABOVE
  
							echo '</div>';
							echo '</td><td>';
							echo number_format($data['damage'],'0','','.');
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
								case 4:
									echo 'Okizeme';
									break;	
							}
							echo '</td>';
						}
						echo '</table>';
										
										
										echo '<h3 class="mt-3">Edit List</h3>
					<p><form class="form-inline" method="post" action="list.php?listid='.$_GET['listid'].'">
							<input type="hidden" name="submission_type" value="2">';
							if(isset($_GET['listid'])): ?>
								<div class="form-group mb-2"><button type="submit" name="action" value="DeleteList" class="btn btn-warning btn-block" onclick="return confirm('Are you sure you want to delete this list?');">Delete List</button></div>
							<?php
							endif;
							echo '<div class="form-group mb-2"><input placeholder="Combo ID" style="background-color: #474747; color:#999999;" name="comboid" class="form-control" maxlength="45" rows="1"></input></div>
							
							<div class="form-group mb-2"><input placeholder="List Password" name="listPass" type="password" maxlength="16" style="background-color: #474747; color:#999999;" class="form-control" rows="1"></input></div>
							<div class="form-group mb-2"><input placeholder="Tag to order combo by" name="comment" maxlength="45" style="background-color: #474747; color:#999999;" class="form-control" rows="1"></input></div>
							<div class="form-group mb-2"><button type="submit" name="action" value="Submit" class="btn btn-primary btn-block">Add Combo</button></div>
							<div class="form-group mb-2"><button type="submit" name="action" value="Delete" class="btn btn-danger btn-block">Remove Combo</button></div>
						</form></p>';
									}else{
										echo '<h3>Listing</h3>
					<p><form class="form-inline" method="post" action="list.php">
							<input type="hidden" name="submission_type" value="1">
							<div class="form-group mb-2"><input placeholder="List Name" style="background-color: #474747; color:#999999;" name="list_name" class="form-control" maxlength="45" rows="1"></input></div>
							<div class="form-group mb-2">';
									
										$query = "SELECT `idgame`, `name` FROM `game` WHERE `complete` = 1 ORDER BY `name`";
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
							<div class="form-group mb-2"><button type="submit" name="action" value="Submit" class="btn btn-primary btn-block">Create List</button></div>
							<div class="form-group mb-2"><button type="submit" name="action" value="Search" class="btn btn-info btn-block">Search</button></div>
						</form></p>';
										if(isset($_POST)){
											if(isset($_POST['action'])){
												if($_POST['action'] == 'Search'){
														$query = "SELECT `idlist`, `list_name` FROM `list` WHERE `list_name` LIKE ? AND `game_idgame` = ?";
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
																echo '<tr><td><a  href="list.php?listid='.$search['idlist'].'">'.$search['list_name'].'</a></tr></td>';
															}
														}
													
												}
											}else{
											$query = "SELECT `idlist`, `list_name` FROM `list` ORDER BY `idlist` DESC LIMIT 0,25";
											$result = $conn -> prepare($query);
											$result -> execute();
											echo '<table id="myTable">';
											echo '<tr>';
											echo '<th>List Name</th>';
											echo '</tr>';
											foreach($result -> get_result() as $search){
												if($search['list_name'] != ''){
													echo '<tr><td><a  href="list.php?listid='.$search['idlist'].'">'.$search['list_name'].'</a></tr></td>';
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
</html>

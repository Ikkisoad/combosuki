<!doctype php>
<html>
	<head>
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

    <!-- Custom styles for this template -->
		<link href="jumbotron.css" rel="stylesheet">
	<style>
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
			
			body{
				background-color: #35340a;
				background: url("img/dark-honeycomb.png");
				color: white;
			}
			.jumbotron{
				max-height: 250px;
				background-color: #000000;
			}
			textare{
				color: #000000;	
			}
			.img-responsive{width:100%;}
				
		</style> <!-- BACKGROUND COLOR-->
	</head>
	
	<body>
		<main role="main">
			<div class="jumbotron">
				<div class="container">
					<h1 class="display-3"></h1>
						<p>
							<a href="<?php
									if(!empty($_GET) && isset($_GET['gameid'])){
										echo 'game.php?gameid=';
										echo $_GET['gameid'];
									}else{
										echo 'index.php';	
									}
								?>"><img 
								<?php
									if(!empty($_GET) && isset($_GET['gameid'])){
										echo 'src=img/games/';
										echo $_GET['gameid'];
										echo '.png ';
									}else{
										echo 'src="img/combosuki.png"';	
									}
								?>
								align="middle" height="200" >
							</a>
						</p>
				</div>
			</div>
			<div class="container">
				<div class="btn-group" role="group" aria-label="Basic example">
					<form id="MyForm" method="get" action="<?php if(isset($_GET['gameid'])){echo 'game.php';}else{echo 'index.php';} ?>">
							<input type="hidden" id="gameid" name="gameid" value="<?php  if(isset($_GET['gameid'])){echo $_GET['gameid'];} ?>">
							<button class="btn btn-secondary"><< back</button>
					</form>
					<form id="MyForm" method="get" action="index.php">
								<button class="btn btn-secondary">Home</button>
					</form>
				</div>
				<p><button class="btn btn-secondary" onclick="change_display()">Display Method</button></p>
				
				
				<?php
					require "server/conexao.php";
					$_GET = array_map("strip_tags", $_GET);
					
					$i = 0;
					
					$primaryTitle = array();
					$primaryValue = array();
					$secondaryTitle = array();
					$secondaryValue = array();
					
					$query = "SELECT `idcombo`,`combo`,`damage`,`value`,`idResources_values`,`number_value`,`character`.`idcharacter`,`character`.`Name`, `video`, `game_resources`.`text_name`,`game_resources`.`type`, `combo`.`type` as listingtype, `combo`.`comments`,`game_resources`.`primaryORsecundary`, `character`.`game_idgame`, `resources_values`.`order`, `combo`.`submited`, `combo`.`patch`
FROM `combo` 
INNER JOIN `resources` ON `combo`.`idcombo` = `resources`.`combo_idcombo` 
LEFT JOIN `resources_values` ON `resources_values`.`idResources_values` = `resources`.`Resources_values_idResources_values` 
LEFT JOIN `character` ON `character`.`idcharacter` = `combo`.`character_idcharacter` 
LEFT JOIN `game_resources` ON `game_resources`.`idgame_resources` = `resources_values`.`game_resources_idgame_resources` 
WHERE `idcombo` = ? ";
					
					$j = array();
					$query = $query . "ORDER BY  `game_resources`.`primaryORsecundary` DESC, `idcombo`, `text_name`;"; //`character_idcharacter` DESC,
					
					//echo $query;
					$result = $conn -> prepare($query);
					$result -> bind_param("i",$_GET['idcombo']);
					$result -> execute();
					
					$id_combo = -1;
					
					$k_res = 0;
					$primaryORsecundary = 0;
					
					$secondaryNames = array();
					
					foreach($result -> get_result() as $data){
						
						//print_r($data);
						if($id_combo != $data['idcombo']){
							$patch = $data['patch'];
							if($data['patch'] != ''){
								echo '<p><button class="btn btn-dark" disabled>';
								echo 'Patch: '.$data['patch'].'</button></p>';
							}
							
							$query = "SELECT name,png FROM `button` WHERE `game_idgame` = ?";
							$result = $conn -> prepare($query);
							$result -> bind_param("i",$data['game_idgame']);
							$result -> execute();
							
							$buttonsName = array();
							$buttonsPNG = array();
							
							foreach($result -> get_result() as $each){
								array_push($buttonsName,$each['name']);
								array_push($buttonsPNG,$each['png']);
							}
							echo '<p><table>';
							echo '<tr>';
							echo '<th>'; 
							$listing_type = $data['listingtype'];
							$character = $data['idcharacter'];
							echo $data['Name'];
							switch($data['listingtype']){
								case 0:
									echo ' Combo:<br>';
									break;
								case 1:
									echo ' Blockstring:<br>';
									break;
								case 2:
									echo ' Mix Up:<br>';
									break;
								case 3:
									echo ' Archive:<br>';
									break;
							}
							
							echo '</th>';
							echo '</tr>';
							$id_combo = $data['idcombo'];
							// ###################################BUTTON PRINTING###############################################
								echo '<tr>';
								echo '<td id="combo_line">';
								$combo_image = '<img class="img-fluid" alt="Responsive image" src=img/buttons/start.png>';
									$buttonID;
									$combo = $data['combo'];
									
									$array = str_split($combo);
									$image = '';
									foreach($array as $char){
										
											if(isset($char) && $char != ' '){
												
												$image .= $char;
															
											}else if($image != ''){
												$buttonID = array_search($image,$buttonsName);
												//echo $buttonID;
												if($buttonID > -1){
													if($image == '->'){echo '<br>';}
													$combo_image .= '<img class="img-fluid" alt="Responsive image" src=img/buttons/';
													$combo_image .= $buttonsPNG[$buttonID];
													$combo_image .= '.png>';
												}else{
													$combo_image .= ' '.$image.' ';
												}
												$image = '';
											}
									}
									if($image != ''){
												$buttonID = array_search($image,$buttonsName);
												//echo $buttonID;
												if($buttonID > -1){
													if($image == '->'){echo '<br>';}
													$combo_image .= '<img class="img-fluid" alt="Responsive image" src=img/buttons/';
													$combo_image .= $buttonsPNG[$buttonID];
													$combo_image .= '.png>';
												}else{
													$combo_image .= ' '.$image.' ';
												}
									}
							//#####################################BUTTON PRINTING##############################################
							if(!isset($_COOKIE['display_preference'])){
								echo $combo_image;
								$_COOKIE['display_preference'] = 1;
							}else if($_COOKIE['display_preference']){
								echo $combo_image;
							}else{
								echo $combo;
							}
							//echo 'THE COOKIE IS:'. $_COOKIE['display_preference'];
							echo		'</td></table>';
						}
						
						
						$comment = $data['comments'];
						$video = $data['video'];
						$damage = $data['damage'];
						$submited = new DateTime($data['submited']);
						if($data['primaryORsecundary'] == 0){
								array_push($secondaryTitle,$data['text_name']);
								if($data['type'] == 1){
									array_push($secondaryValue, $data['value']);
								}else{
									array_push($secondaryValue, $data['number_value']);
								}
						}else{
							array_push($primaryTitle,$data['text_name']);
							if($data['type'] == 1){
								array_push($primaryValue, $data['value']);
							}else{
								array_push($primaryValue, $data['number_value']);
							}
						}
					}
					
				//echo $query;
				if(!isset($damage)){ exit();}
					echo '</td></table><p><table>';
							echo '<tr>';
							echo '<th>Damage</th>';
							for($i = 0; $i<sizeof($primaryTitle); $i++){
								echo '<th>';
								echo $primaryTitle[$i];
								echo '</th>';
							}
							echo '</tr><tr>';
							echo '<td>'.$damage.'</td>';
							for($i = 0; $i<sizeof($primaryTitle); $i++){
								echo '<td>';
								echo $primaryValue[$i];
								echo '</td>';
							}
							echo '</tr>';
					echo '</table></p>';
					if(sizeof($secondaryTitle)){
						echo '</td></table><p><table>';
								echo '<tr>';
								for($i = 0; $i<sizeof($secondaryTitle); $i++){
									echo '<th>';
									echo $secondaryTitle[$i];
									echo '</th>';
								}
								echo '</tr><tr>';
								for($i = 0; $i<sizeof($secondaryTitle); $i++){
									echo '<td>';
									echo $secondaryValue[$i];
									echo '</td>';
								}
								echo '</tr>';
						echo '</table></p>';
					}
				
				?>
			
			<!-- </tr></table></p> -->
			
			<div id="combo_text" style="display: none;">
				<?php 
				
					if($_COOKIE['display_preference']){
						echo $combo;
					}else{
						echo $combo_image;
					}
				?>
			</div>
			<?php
				if($comment != ''){
					echo '<p><table>';
						echo '<tr><td>';
							echo 'Comment:';
						echo '</td></tr>';
						echo '<tr><td>';
							echo nl2br($comment);
						echo '</td></tr>';
					echo '</table></p>';
				}
				if($video != ''){
					echo '<p><table>';
							echo '<tr><td>';
								echo 'Video:';
							echo '</td></tr>';
							echo '<tr><td>';
					if (strpos($video, 'twitter') !== false && strpos($video, 'https') !== false) {
						echo '<blockquote class="twitter-tweet" data-conversation="none" data-lang="en"><p lang="en" dir="ltr">
						<a href="';
						echo $video;
						echo '"></a>
					</blockquote>
					<script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>';
					}else if (strpos($video, 'youtu') !== false) {
						preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $video, $match);
						$youtube_id = $match[1];
						//print_r($match);
						//echo '<br> URL: ';
						//echo $youtube_id;
						$whatIWant = substr($video, strpos($video, "=") + 1);    
						//echo '<br>what I want:';
						//echo $whatIWant;
						echo '<div class="embed-responsive embed-responsive-16by9">
						<iframe class="embed-responsive-item" src="https://www.youtube.com/embed/';
						echo $youtube_id;
						echo '?start=';echo $whatIWant; echo '" allowfullscreen></iframe></div>';
					}else if(strpos($video, 'streamable') !== false && strpos($video, 'https') !== false){
											/*echo '<div class="embed-responsive embed-responsive-16by9">
											<iframe class="embed-responsive-item" src="';
											echo $video;
											echo '" allowfullscreen></iframe>
										</div>';*/
										//$i = substr_replace($video, "/s", 22,0);
										echo '<div style="width: 100%; height: 0px; position: relative; padding-bottom: 56.250%;">
						<iframe src="';
											echo $video;
											echo '" frameborder="0" width="100%" height="100%" allowfullscreen style="width: 100%; height: 100%; position: absolute;">
						</iframe>
						</div>';
											
											
											/*echo '<div style="width: 100%; height: 0px; position: relative; padding-bottom: 56.250%;"><iframe src="';
											echo $streamable;
											
											echo '" frameborder="0" width="100%" height="100%" allowfullscreen style="width: 100%; height: 100%; position: absolute;"></iframe></div>';*/
											
					}else if(strpos($video, 'twitch') !== false && strpos($video, 'clips') !== false && strpos($video, 'https') !== false){
						$i = substr_replace($video, "embed?clip=", 24,0);
						echo '<iframe
							src="'.$i.'"
							height="360"
							width="640"
							frameborder="0"
							scrolling="no"
							allowfullscreen="true">
						</iframe>';
						
					}else{
						echo $video;	
					}
					
					echo '</td></tr>';
					echo '</table></p>';
				}
			
			?>
			<div class="btn-group" role="group">
				<!-- <form method="post" onsubmit="return confirm('Do you really want to delete this <?php
							/*switch($listing_type){
								case 0:
									echo 'combo';
									break;
								case 1:
									echo 'blockstring';
									break;
								case 2:
									echo 'mix up';
									break;
								case 3:
									echo 'archive';
									break;
							}*/
						?>?');" action="game.php?gameid=<?php //echo $_GET['gameid']; ?>">
					<input type="hidden" id="action" name="action" value="0">
					<input type="hidden" id="idcombo" name="idcombo" value="<?php //echo $_GET['idcombo'] ?>">
					<button type="submit" class="btn btn-danger">Delete</button>
				</form> -->
				
				<?php
					if($listing_type != 3){
						echo '<form method="post" onsubmit="return confirm("Are you sure you want to archive this';
						switch($listing_type){
							case 0:
								echo 'combo';
								break;
							case 1:
								echo 'blockstring';
								break;
							case 2:
								echo 'mix up';
								break;
							case 3:
								echo 'archive';
								break;
						}
						echo '?");" action="game.php?gameid=';
						if(isset($_GET['gameid'])){echo $_GET['gameid'];}
						echo '">
						<input type="hidden" id="action" name="action" value="1">
						<input type="hidden" id="idcombo" name="idcombo" value="';
						echo $_GET['idcombo'];
						echo '">
						<button type="submit" class="btn btn-warning">Archive</button>
						</form></p>';
					}
				?>
				
				<form method="post" action="forms.php?gameid=<?php if(isset($_GET['gameid'])){echo $_GET['gameid'];} ?>">
					<?php //print_r($secondaryTitle);?>
					<!-- <input type="hidden" name="<?php //echo $secondaryTitle; ?>" value="<?php //echo $secondaryValue; ?>"> -->
					<?php
						
						for($i = 0; $i<sizeof($primaryTitle); $i++){
								echo '<input type="hidden" name="';
								echo $primaryTitle[$i];
								echo '" value="';
								echo $primaryValue[$i].'">';
						}
						for($i = 0; $i<sizeof($secondaryTitle); $i++){
								echo '<input type="hidden" name="';
								echo $secondaryTitle[$i];
								echo '" value="';
								echo $secondaryValue[$i].'">';
						}
					?>
					<input type="hidden" id="listingtype" name="listingtype" value="<?php echo $listing_type; ?>">
					<input type="hidden" id="type" name="type" value="2">
					<input type="hidden" name="patch" value="<?php echo $patch; ?>">
					<input type="hidden" name="characterid" value="<?php echo $character; ?>">
					<input type="hidden" name="damage" value="<?php echo $damage; ?>">
					<input type="hidden" name="idcombo" value="<?php echo $id_combo; ?>">
					<input type="hidden" name="combo" value="<?php echo $combo; ?>">
					<input type="hidden" name="comment" value="<?php echo $comment; ?>">
					<input type="hidden" name="video" value="<?php echo $video; ?>">
					<button class="btn btn-primary">Edit</button><p>Submited: <?php echo $submited->format('d-m-Y');?>
				</form>
			</div>
			</div>
		</main>
	</body>
		<script>
			function change_display(){
				/*var preference = getCookie("display_preference");
				if(preference == 1){
					document.cookie = "display_preference=0;";
				}else{
					document.cookie = "display_preference=1;";
				}*/
				var temp = document.getElementById("combo_line").innerHTML;
				document.getElementById("combo_line").innerHTML = document.getElementById("combo_text").innerHTML;
				document.getElementById("combo_text").innerHTML = temp;
			}
		</script>
	    <!-- Bootstrap core JavaScript
		================================================== -->
		<!-- Placed at the end of the document so the pages load faster -->
		<script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>
		<script>window.jQuery || document.write('<script src="../../../../assets/js/vendor/jquery.min.js"><\/script>')</script>
		<script src="../../../../assets/js/vendor/popper.min.js"></script>
		<script src="../../../../dist/js/bootstrap.min.js"></script>
</html>

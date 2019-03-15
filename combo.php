<!doctype php>
<html>
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=yes">
		<meta name="description" content="">
		<meta name="Ikkisoad" content="">
		<link rel="icon" href="img/favicon.ico">

		<title>FGCC</title>
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
							<a href="index.php"><img 
								<?php
									if(!empty($_GET)){
										echo 'src=img/games/';
										echo $_GET['gameid'];
										echo '.png ';
									}else{
										echo 'src="img/FGCcombos.png"';	
									}
								?>
								align="middle" height="200" >
							</a>
						</p>
				</div>
			</div>
			<div class="container"><p>
				
				<?php
					require "server/conexao.php";
					$_GET = array_map("strip_tags", $_GET);
					$query = "SELECT name,png FROM `button` WHERE `game_idgame` = ? OR `game_idgame` IS NULL";
					$result = $conn -> prepare($query);
					$result -> bind_param("i",$_GET['gameid']);
					$result -> execute();
					
					$buttonsName = array();
					$buttonsPNG = array();
					
					foreach($result -> get_result() as $each){
						array_push($buttonsName,$each['name']);
						array_push($buttonsPNG,$each['png']);
					}
					
					//print_r($buttonsName);
					//print_r($buttonsPNG);
					$i = 0;
					
					$primaryTitle = array();
					$primaryValue = array();
					$secondaryTitle = array();
					$secondaryValue = array();
					
					echo array_search('5555',$buttonsName);
					echo '<table>';
					echo '<tr>';
					//$query = 
					
					
					
					$query = "SELECT `idcombo`,`combo`,`damage`,`value`,`idResources_values`,`number_value`,`character`.`idcharacter`,`character`.`Name`, `video`, `game_resources`.`text_name`,`game_resources`.`type`, `combo`.`type` as listingtype, `combo`.`comments`,`game_resources`.`primaryORsecundary`
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
					echo '<th>'; 
					$id_combo = -1;
					
					$k_res = 0;
					$primaryORsecundary = 0;
					
					$secondaryNames = array();
					
					foreach($result -> get_result() as $data){
						
						//print_r($data);
						if($id_combo != $data['idcombo']){
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
								echo '<td>';
								echo '<img class="img-fluid" alt="Responsive image" src=img/buttons/start.png>';
									$buttonID;
									$combo = $data['combo'];
									
									$array = str_split($combo);
									$image = '';
									foreach($array as $char){
										
											if(isset($char) && $char != ' '){
												
												$image .= $char;
															
											}else if($image != ''){
												echo '<img class="img-fluid" alt="Responsive image" src=img/buttons/';
												
												$buttonID = array_search($image,$buttonsName);
												echo $buttonsPNG[$buttonID];
													
												echo '.png>';
												$image = '';
												
											}
									}
							//#####################################BUTTON PRINTING##############################################
							echo		'</td></table>';
						}
						
						
						$comment = $data['comments'];
						$video = $data['video'];
						$damage = $data['damage'];
						if($data['primaryORsecundary'] == 0){
							array_push($secondaryTitle,$data['text_name']);
							if($data['type'] == 'list'){
								array_push($secondaryValue, $data['value']);
							}else{
								array_push($secondaryValue, $data['number_value']);
							}
						}else{
							array_push($primaryTitle,$data['text_name']);
							if($data['type'] == 'list'){
								array_push($primaryValue, $data['value']);
							}else{
								array_push($primaryValue, $data['number_value']);
							}
						}
					}
					
				//echo $query;
				
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
				?>
			
			<!-- </tr></table></p> -->
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
					if (strpos($video, 'twitter') !== false) {
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
					}else if(strpos($video, 'streamable') !== false){
											/*echo '<div class="embed-responsive embed-responsive-16by9">
											<iframe class="embed-responsive-item" src="';
											echo $video;
											echo '" allowfullscreen></iframe>
										</div>';*/
										
										echo '<div style="width: 100%; height: 0px; position: relative; padding-bottom: 56.250%;">
						<iframe src="';
											echo $video;
											echo '" frameborder="0" width="100%" height="100%" allowfullscreen style="width: 100%; height: 100%; position: absolute;">
						</iframe>
						</div>';
											
											
											/*echo '<div style="width: 100%; height: 0px; position: relative; padding-bottom: 56.250%;"><iframe src="';
											echo $streamable;
											
											echo '" frameborder="0" width="100%" height="100%" allowfullscreen style="width: 100%; height: 100%; position: absolute;"></iframe></div>';*/
											
					}else{
						echo $video;	
					}					
					
					echo '</td></tr>';
					echo '</table></p>';
				}
			?>
			<div class="btn-group" role="group">
				<form method="post" onsubmit="return confirm('Do you really want to delete this <?php
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
							} ?>?');" action="game.php?gameid=<?php echo $_GET['gameid']; ?>">
					<input type="hidden" id="action" name="action" value="0">
					<input type="hidden" id="idcombo" name="idcombo" value="<?php echo $_GET['idcombo'] ?>">
					<button type="submit" class="btn btn-danger">Delete</button>
				</form>
				
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
						echo $_GET['gameid'];
						echo '">
						<input type="hidden" id="action" name="action" value="1">
						<input type="hidden" id="idcombo" name="idcombo" value="';
						echo $_GET['idcombo'];
						echo '">
						<button type="submit" class="btn btn-warning">Archive</button>
						</form></p>';
					}
				?>
				
				<form method="post" action="forms.php?gameid=<?php echo $_GET['gameid']; ?>">
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
					<input type="hidden" id="type" name="type" value="2">
					<input type="hidden" name="characterid" value="<?php echo $character; ?>">
					<input type="hidden" name="damage" value="<?php echo $damage; ?>">
					<input type="hidden" name="idcombo" value="<?php echo $id_combo; ?>">
					<input type="hidden" name="combo" value="<?php echo $combo; ?>">
					<input type="hidden" name="comment" value="<?php echo $comment; ?>">
					<input type="hidden" name="video" value="<?php echo $video; ?>">
					<button class="btn btn-primary">Edit</button>
				</form>
			</div>
			</div>
		</main>
	</body>
	    <!-- Bootstrap core JavaScript
		================================================== -->
		<!-- Placed at the end of the document so the pages load faster -->
		<script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>
		<script>window.jQuery || document.write('<script src="../../../../assets/js/vendor/jquery.min.js"><\/script>')</script>
		<script src="../../../../assets/js/vendor/popper.min.js"></script>
		<script src="../../../../dist/js/bootstrap.min.js"></script>
</html>

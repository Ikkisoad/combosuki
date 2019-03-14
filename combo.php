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
				<div class="btn-group" role="group">
					<form method="post" onsubmit="return confirm('Do you really want to delete this combo?');" action="game.php?gameid=<?php echo $_GET['gameid']; ?>">
						<input type="hidden" id="action" name="action" value="0">
						<input type="hidden" id="idcombo" name="idcombo" value="<?php echo $_GET['idcombo'] ?>">
						<button type="submit" class="btn btn-danger">Delete</button>
					</form>
					<form method="post" onsubmit="return confirm('Are you sure you want to archive this combo?');" action="game.php?gameid=<?php echo $_GET['gameid']; ?>">
						<input type="hidden" id="action" name="action" value="1">
						<input type="hidden" id="idcombo" name="idcombo" value="<?php echo $_GET['idcombo'] ?>">
						<button type="submit" class="btn btn-warning">Archive</button>
					</form></p>
				</div>
				<?php
					require "server/conexao.php";
					$_GET = array_map("strip_tags", $_GET);
					$i = 0;
					$secondaryTitle = array();
					$secondaryValue = array();
					echo '<table>';
					echo '<tr>';
					//$query = 
					
					$query = "SELECT `idcombo`,`combo`,`value`,`idResources_values`,`number_value`,`resources`.`character_idcharacter`,`character`.`Name`, `video`, `game_resources`.`text_name`,`game_resources`.`type`, `combo`.`type` as listingtype, `combo`.`comments`,`game_resources`.`primaryORsecundary`
FROM `combo` 
INNER JOIN `resources` ON `combo`.`idcombo` = `resources`.`combo_idcombo` 
LEFT JOIN `resources_values` ON `resources_values`.`idResources_values` = `resources`.`Resources_values_idResources_values` 
LEFT JOIN `character` ON `character`.`idcharacter` = `resources`.`character_idcharacter` 
LEFT JOIN `game_resources` ON `game_resources`.`idgame_resources` = `resources_values`.`game_resources_idgame_resources` 
WHERE `idcombo` = ? ";
					
					$j = array();
					$query = $query . "ORDER BY  `game_resources`.`primaryORsecundary` DESC, `idcombo`, `text_name`;"; //`character_idcharacter` DESC,
					
					//echo $query;
					$result = $conn -> prepare($query);
					$result -> bind_param("i",$_GET['idcombo']);
					$result -> execute();
					echo '<th>'; 
					//echo '<br>';
					//print_r($result -> get_result());
					$id_combo = -1;
					//echo '<br>';
					//print_r($j);
					
					$k_res = 0;
					$primaryORsecundary = 0;
					
					$secondaryNames = array();
					
					foreach($result -> get_result() as $data){
						//print_r($data);
						if($id_combo != $data['idcombo']){
							switch($data['listingtype']){
								case 0:
									echo 'Combo:<br>';
									break;
								case 1:
									echo 'Blockstring:<br>';
									break;
								case 2:
									echo 'Mix Up:<br>';
									break;
								case 3:
									echo 'Archive:<br>';
									break;
							}
							
							//echo $data['listingtype'];
							echo '</th>';
							echo '</tr>';
							$k = 0;
							$id_combo = $data['idcombo'];
							/*echo '<tr>';
							echo		'<td><a href="combo.php?idcombo='.$data['idcombo'].'">'.$data['combo'].'</a></td>';*/
							// ##################################################################################
								echo '<tr>';
								//echo $data['combo'];
								echo '<td>';
								echo '<img class="img-fluid" alt="Responsive image" src=img/buttons/UjlgFNr.png>';
									$i = 0;
									$l = 0;
									//$m = 0;
									$combo = $data['combo'];
									$image = array();
									$array = str_split($combo);
									$letter = array();
									unset($image);
									unset($letter);
									foreach($array as $char){
											if($char != '>' && isset($char) && $char != ' '){
												switch($char){
													case 1:case 2:case 3:case 4:case 5:case 6:case 7:case 8:case 9:
														if(!isset($letter)){
															$image[$i] = $char;
															$i++;
														}else{
															$letter[$l] = $char;
															$l++;
														}
														break;
												default:
														$letter[$l] = $char;
														$l++;
													break;
												}				
											}else if(isset($image) || isset($letter)){
												$l = $i = 0;
												if(isset($image)){
													echo '<img class="img-fluid" alt="Responsive image" src=img/buttons/';
													/*echo $_GET['gameid'];
													echo '/';*/
													foreach($image as $lul){
														echo $lul;
													}
													echo '.png>';
													unset($image);
												}
												if(isset($letter)){
													echo '<img class="img-fluid" alt="Responsive image" src=img/buttons/';
													/*echo $_GET['gameid'];
													echo '/';*/
													foreach($letter as $lul){
														echo $lul;
													}
													echo '.png>';
													unset($letter);
												}
												/*$m++;
												if($m%10 == 0){echo '<br>';}*/
												
											}
											if($char == '>'){echo '<img class="img-fluid" alt="Responsive image" src=img/buttons/gap.png>';}
									}
									if(isset($image)){
										echo '<img src=img/';
										foreach($image as $lul){
											echo $lul;
										}
										echo '.png>';
										unset($image);
									}
									if(isset($letter)){
										echo '<img src=img/buttons/';
										echo $_GET['gameid'];
										foreach($letter as $lul){
											echo $lul;
										}
										echo '.png>';
										unset($letter);
									}
							//###################################################################################
							echo		'</td></table>';
						}
						if($k_res == 0){
							$resource_result = $conn -> prepare("SELECT text_name,type,idgame_resources, primaryORsecundary FROM `game_resources` WHERE game_idgame = ? ORDER BY primaryORsecundary DESC, text_name;");
							$resource_result -> bind_param("i",$_GET['gameid']);
							$resource_result -> execute();
							$i = 0;
							echo		'<br><p><table><tr>';
							foreach($resource_result -> get_result() as $resource){
							//	print_r($resource); echo '<br>';
								if($resource['type'] == 'character'){
									
									$j[$k_res++] = $resource['text_name'];
									
								}
								if($resource['primaryORsecundary']){
									$primaryORsecundary++;
									echo '<th>'; echo $resource['text_name']; echo '</th>';
								}else{
									$secondaryNames[$i++] = $resource['text_name'];
								}
							}
							echo		'</tr>';
							//print_r($j);
							echo '<br>';
							//print_r($data);
							$k_res++;
						}
							//echo		'<td><a href="combo.php?idcombo='.$data['idcombo'].'">'.$data['combo'].'</a></td>';
					//	$test_row = $data['character_idcharacter'];
					
						/*if($k == $primaryORsecundary){
							echo		'</td></table>';
							echo		'<br><p><table><tr>';
							for($i = 0; sizeof($secondaryNames) > $i; $i++){
								echo '<th>'; echo $secondaryNames[$i]; echo '</th>';
							}
							echo		'</tr>';
						}*/
					
						/*if($data['type'] == ''){
							//echo $j[$k];
								if(!empty($data['character_idcharacter'])){
									echo		'<td>'.$data['Name'].'</td>';
									$k++;
									
								}else{
									echo		'<td>No Assist</td>';
									$k++;
									
								}
								
						}*/
						if($data['type'] == 'list' && $data['primaryORsecundary']){
							echo		'<td>'.$data['value'].'</td>';
							//$k++;
						}
						
						if($data['type'] == 'number' && $data['primaryORsecundary']){
							echo		'<td>'.$data['number_value'].'</td>';
							
						}
						$k++;
						//echo '<tr>';
						
						$comment = $data['comments'];
						$video = $data['video'];
						if($data['primaryORsecundary'] == 0){
							array_push($secondaryTitle,$data['text_name']);
							array_push($secondaryValue, $data['value']);
						}
						//echo '/<tr>';
					}
					/*for($k; $k<sizeof($j); $k++){
						echo '<td></td>';
					}*/
					
				//echo $query;
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
							echo $comment;
						echo '</td></tr>';
					echo '</table></p>';
				}
				if (strpos($video, 'twitter') !== false) {
					echo '<p><table>';
						echo '<tr><td>';
							echo 'Video:';
						echo '</td></tr>';
						echo '<tr><td>';
					echo '<blockquote class="twitter-tweet" data-conversation="none" data-lang="en"><p lang="en" dir="ltr">
					<a href="';
					echo $video;
					echo '"></a>
				</blockquote>
				<script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>';
				echo '</td></tr>';
					echo '</table></p>';
				}
				
				if (strpos($video, 'youtu') !== false) {
					//echo $video;
					echo '<p><table>';
						echo '<tr><td>';
							echo 'Video:';
						echo '</td></tr>';
						echo '<tr><td>';
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
					echo '?start=';echo $whatIWant; echo '" allowfullscreen></iframe>
				</div>';
				echo '</td></tr>';
					echo '</table></p>';
				}
				
				if(strpos($video, 'streamable') !== false){
					echo '<p><table>';
						echo '<tr><td>';
							echo 'Video:';
						echo '</td></tr>';
						echo '<tr><td>';
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
										echo '</td></tr>';
					echo '</table></p>';
				}
			?>
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

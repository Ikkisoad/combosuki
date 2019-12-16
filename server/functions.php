<?php

function embed_video($video){
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
						
					}else if(strpos($video, 'imgur') !== false && strpos($video, 'https') !== false){
						//echo '<blockquote class="imgur-embed-pub" lang="en" data-id="a/amuAPtr"><a href="//imgur.com/amuAPtr">Jesus saves</a></blockquote><script async src="//s.imgur.com/min/embed.js" charset="utf-8"></script>'; https://imgur.com/sJyThyf
						$i = substr($video, 18);
						echo '<blockquote class="imgur-embed-pub" lang="en" data-id="';
						echo $i;
						echo '"><a href="';
						echo $video;
						echo '"></a></blockquote><script async src="//s.imgur.com/min/embed.js" charset="utf-8"></script>';
					}else{
						echo $video;	
					}
					
					echo '</td></tr>';
					echo '</table></p>';
				}
}

function embed_video_notable($video){
	if($video != ''){
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
														echo $data['video'];
														echo '" allowfullscreen></iframe>
													</div>';*/
													//$i = substr_replace($data['video'], "/s", 22,0);
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
									$i = substr_replace($video, "embed?autoplay=false&clip=", 24,0);
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
									
								}else if(strpos($video, 'imgur') !== false && strpos($video, 'https') !== false){
									//echo '<blockquote class="imgur-embed-pub" lang="en" data-id="a/amuAPtr"><a href="//imgur.com/amuAPtr">Jesus saves</a></blockquote><script async src="//s.imgur.com/min/embed.js" charset="utf-8"></script>'; https://imgur.com/sJyThyf
									$i = substr($video, 18);
									echo '<blockquote class="imgur-embed-pub" lang="en" data-id="';
									echo $i;
									echo '"><a href="';
									echo $video;
									echo '"></a></blockquote><script async src="//s.imgur.com/min/embed.js" charset="utf-8"></script>';
								}else{
									echo $video;
								}
							}
}

function combo_table($gameid){
	require "server/conexao.php";
						$query = "SELECT idcombo,Name,combo,damage,type, comments, submited, video FROM `combo` INNER JOIN `character` ON `combo`.`character_idcharacter` = `character`.`idcharacter` WHERE `character`.`game_idgame` = ? ORDER BY submited DESC LIMIT 0,5";
						$result = $conn -> prepare($query);
						$result -> bind_param("i",$gameid);
						$result -> execute();
						
						echo '<table id="myTable">';
						echo '<tr>';
						echo '<th onclick="sortTable(0)">Character</th><th onclick="sortTable(1)">Inputs</th><th onclick="sortTable(2,1)">Damage</th><th onclick="sortTable(3)">Type</th><th onclick="sortTable(4)">Submited</th>';
						echo '</tr>';
						
						foreach($result -> get_result() as $data){
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
							embed_video_notable($data['video']);
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
							echo '</td><td>';
							$i = new DateTime($data['submited']);
							echo $i->format('d-m');
							echo '</td></tr>';
						}
						echo '</table>';
}

function game_title(){
	require "server/conexao.php";
	$query = "SELECT idgame, name, image FROM game WHERE complete = 1 ORDER BY name;";
	$result = $conn -> prepare($query);
	$result -> execute();
	
	foreach($result -> get_result()	as $gameid){
		echo '<a style="margin-left:5em;" href=game.php?gameid=';
		echo $gameid['idgame'];
		echo '><img style="
		max-height:150px;
		max-width:150px;
		height:auto;
		width:auto;
		" ';
		if($gameid['image'] == ''){
			echo 'src=img/games/';
			echo $gameid['idgame'];
			echo '.png height=100 alt="Responsive image"';
			echo '></a>';
		}else{
			echo 'src=';
			echo $gameid['image'];
			echo ' height=100 alt="Responsive image"';
			echo '></a>';
		}
		
		/* TEXT ONLY
		echo '<a style="margin-left:5em;" href=game.php?gameid=';
		echo $gameid['idgame'];
		echo '>';
		echo $gameid['name'].'</a><br>';*/
	}
		
}

function game_image($gameid, $height){
	require "server/conexao.php";
	$query = "SELECT image FROM game WHERE idgame = ?;";
	$result = $conn -> prepare($query);
	$result -> bind_param("i",$gameid);
	$result -> execute();
	
	foreach($result -> get_result()	as $gameimage){
		if($gameimage['image'] == ''){
			echo 'src=img/games/';
			echo $gameid;
			echo '.png ';
		}else{
			echo '<img src=';
			echo $gameimage['image'];
			echo ' align="middle" height="'.$height.'" >';
		}
	}
}

function count_combos(){
	require "server/conexao.php";
	$query = "SELECT COUNT(idcombo) as total FROM `combo`;";
	$result = $conn -> prepare($query);
	$result -> execute();
	
	foreach($result -> get_result()	as $combo_count){
		echo 'Total submissions: ';
		echo $combo_count['total'];
	}
	
}

function logs(){
	require "server/conexao.php";
	$query = "SELECT * FROM logs ORDER BY Date DESC;";
	$result = $conn -> prepare($query);
	$result -> execute();
	
	foreach($result -> get_result()	as $log){
		echo $log['Date'].': ';
		echo $log['Description'].'<br>';
	}
}

function count_char(){
	require "server/conexao.php";
	$query = "SELECT `character_idcharacter`, COUNT(idcombo) as amount, `character`.`Name` FROM `combo` INNER JOIN `character` ON `character`.`idcharacter` = `combo`.`character_idcharacter` WHERE `character`.`game_idgame` = ? GROUP BY `character_idcharacter` ORDER BY amount DESC";
	$result = $conn -> prepare($query);
	$result -> bind_param("i",$_GET['gameid']);
	$result -> execute();
	
	/*echo '<table>';
	echo '<tr>';
	echo '<th>Character</th><th>Total Entries</th></th>';
	echo '</tr>';*/
	echo '<p><h3> Total Entries </h3>';
	//echo '├┬┴┬┴';
	foreach($result -> get_result() as $data){
		/*echo '<tr><td>'.$data['Name'].'</td>';
		echo '<td>'.$data['amount'].'</td></tr>';*/
		echo ' '.$data['Name'].': '.$data['amount'].' ▰ ';
	}
}

function delete_combo($idcombo){
	require "server/conexao.php";
	$query = "DELETE FROM `resources` WHERE `combo_idcombo` = ?";
	$result = $conn -> prepare($query);
	$result -> bind_param("i", $idcombo);
	$result -> execute();

	$query = "DELETE FROM `combo` WHERE `idcombo` = ?";
	$result = $conn -> prepare($query);
	$result -> bind_param("i", $idcombo);
	$result -> execute();	
}

function quick_search_form($gameid){
						require "server/conexao.php";
							
						$query = "SELECT `Name`,`idcharacter` FROM `character` WHERE game_idgame = ? ORDER BY name;";
						$result = $conn -> prepare($query);
						$result -> bind_param("i", $gameid);
						$result -> execute();
							
						echo '<p><select name="characterid" class="custom-select">';
							
						echo '<option value="-">Character</option>';
						foreach($result -> get_result() as $character){
							echo '<option value="'.$character['idcharacter'].'" ';
							echo '>'.$character['Name'].'</option>';
						}
						echo '</select></p>'; 
							
						echo '<p>
							<select name="listingtype" class="custom-select">
								<option value="0">Combo</option>
								<option value="1">Blockstring</option>
								<option value="2">Mix Up</option>
								<option value="4">Okizeme</option>
								<option value="3">Archive</option>
								<option value="-">Show All</option>
							</select>
						</p>';
						
						
							require "server/conexao.php";
							
							$query = "SELECT text_name,type,idgame_resources FROM `game_resources` WHERE game_idgame = ? AND primaryORsecundary = 1 ORDER BY game_resources.primaryORsecundary DESC,text_name;";
							$result = $conn -> prepare($query);
							$result -> bind_param("i", $gameid);
							$result -> execute();
							
							echo '<br><p>';
							
							foreach($result -> get_result()	as $resource){
								if($resource['type'] == 1){
									echo '<div class="input-group mb-3">
  <div class="input-group-prepend">
    <label class="input-group-text">'; 
									echo '</label></div>  <select name="';
									echo $resource['text_name'];
									
									echo '"class="custom-select input-small">';
									
									$query = "SELECT idResources_values,value FROM `resources_values` WHERE `game_resources_idgame_resources` = ".$resource['idgame_resources']." ORDER BY resources_values.order, value;";
									$result = $conn -> prepare($query);
									$result -> execute();
									
									echo '<option value="-">'.$resource['text_name'].'</option>';
									
									foreach($result -> get_result() as $resource_value){
										echo '<option value="';
										echo $resource_value['idResources_values'].'" ';
										echo '>';
										echo $resource_value['value'];
										echo '</option>';
									}
									echo '</select></div> ';
									
								}else if($resource['type'] == 2){
									
									$query = "SELECT idResources_values,value FROM `resources_values` WHERE `game_resources_idgame_resources` = ?;";
									$result = $conn -> prepare($query);
									$result -> bind_param("i", $resource['idgame_resources']);
									$result -> execute();
									
									foreach($result -> get_result() as $resource_value){
										echo '<p>	<div class="input-group mb-3">
							<div class="input-group-prepend">
								<span class="input-group-text">';
										echo ' </div>
							<input class="form-control" type="number" min="0" class="input-sm" name="';
										echo $resource['text_name'];
										echo '" placeholder="'.$resource['text_name'].'"';
										echo ' max="';
										echo $resource_value['value'];
										echo '"step="any"' ;
										echo '> </div> </p>';
									}
								}
							}
							
							echo '</p><br>';
}

function button_printing($idgame, $dataCombo){
	require 'server/conexao.php';
	$query = "SELECT name,png FROM `button` WHERE `game_idgame` = ?";
	$result = $conn -> prepare($query);
	$result -> bind_param("i",$idgame);
	$result -> execute();
	
	$buttonsName = array();
	$buttonsPNG = array();
	
	foreach($result -> get_result() as $each){
		array_push($buttonsName,$each['name']);
		array_push($buttonsPNG,$each['png']);
	}
	$combo_image = '<img class="img-fluid" alt="Responsive image" src=img/buttons/start.png>';
		$buttonID;
		
		
		$array = str_split($dataCombo);
		$image = '';
		foreach($array as $char){
			
				if(isset($char) && $char != ' '){
					
					$image .= $char;
								
				}else if($image != ''){
					$buttonID = array_search($image,$buttonsName);
					//echo $buttonID;
					if($buttonID > -1){
						if($image == '->'){$combo_image .= '<br>';}
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
		
	return $combo_image;
}
?>
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
					}else if(strpos($video, 'nicovideo') !== false && strpos($video, 'https') !== false){
						$i = substr($video, 12);
						//echo $i;
						echo '<script type="application/javascript" src="https://embed.'.$i.'/script?w=640&h=360"></script>';
						
					}else if(strpos($video, 'nico') !== false && strpos($video, 'https') !== false){
						$i = substr($video, 16);
						$i = substr_replace($i,'w=640&h=360&',11	,0);
						$i = substr_replace($i,'/script',10	,0);
						//echo $i;
						echo '<script type="application/javascript" src="https://embed.nicovideo.jp/watch/'.$i.'"></script>';
						
					}else{
						echo $video;	
					}
					
					echo '</td></tr>';
					echo '</table></p>';
				}
}

function embed_video_notable($video){ //Twitter, Youtube, Twitch clip, Nico Nico, Streamable, imgur
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
								}else if(strpos($video, 'nicovideo') !== false && strpos($video, 'https') !== false){
									$i = substr($video, 12);
									//echo $i;
									echo '<script type="application/javascript" src="https://embed.'.$i.'/script?w=640&h=360"></script>';
									
								}else if(strpos($video, 'nico') !== false && strpos($video, 'https') !== false){
									$i = substr($video, 16);
									$i = substr_replace($i,'w=640&h=360&',11	,0);
									$i = substr_replace($i,'/script',10	,0);
									//echo $i;
									echo '<script type="application/javascript" src="https://embed.nicovideo.jp/watch/'.$i.'"></script>';
									
								}else{
									echo $video;	
								}
							}
}

function print_listingtype($listingtype, $conn){
	//require "server/conexao.php";
	$query = "SELECT `title` FROM `game_entry` WHERE `entryid` = ?;";
	$result = $conn -> prepare($query);
	$result -> bind_param("i",$listingtype);
	$result -> execute();
	foreach($result -> get_result()	as $lol){
		echo ' '.$lol['title'];
	}
}

function combo_table($gameid, $conn){
	//require "server/conexao.php";
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
							print_listingtype($data['type'], $conn);
							echo '</td><td>';
							$i = new DateTime($data['submited']);
							echo $i->format('d-m-y');
							echo '</td></tr>';
						}
						echo '</table>';
}

function game_lock($conn){
	$query = "SELECT complete FROM game WHERE idgame = ?";
	$result = $conn -> prepare($query);
	$result -> bind_param("i",$_GET['gameid']);
	$result -> execute();
	
	foreach($result -> get_result() as $lol){
		echo '<p><button type="submit" name="action" value="';
		if($lol['complete'] == 1){
			echo 'Lock" class="btn btn-success btn-block">Lock</button></p>';
		}else{
			echo 'Unlock" class="btn btn-warning btn-block">Unlock</button></p>';	
		}
	}
}

function game_title($conn){
	//require "server/conexao.php";
	$query = "SELECT idgame, name, image FROM game WHERE complete > 0 ORDER BY name;";
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

function game_text_only($conn){
	//require "server/conexao.php";
	$query = "SELECT idgame, name, image FROM game WHERE 1 ORDER BY name;";
	$result = $conn -> prepare($query);
	$result -> execute();
	
	foreach($result -> get_result()	as $gameid){
		
		//TEXT ONLY
		echo '<a style="margin-left:5em;" href=game.php?gameid=';
		echo $gameid['idgame'];
		echo '>';
		echo $gameid['name'].'</a><br>';
	}
		
}

function game_image($gameid, $height, $conn){
	//require "server/conexao.php";
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

function game_patch($gameid, $conn){
	//require "server/conexao.php";
	$query = "SELECT patch FROM game WHERE idgame = ?;";
	$result = $conn -> prepare($query);
	$result -> bind_param("i",$gameid);
	$result -> execute();
	
	foreach($result -> get_result()	as $lol){
		return $lol['patch'];
	}
}

function count_combos($conn){
	//require "server/conexao.php";
	$query = "SELECT COUNT(idcombo) as total FROM `combo`;";
	$result = $conn -> prepare($query);
	$result -> execute();
	
	foreach($result -> get_result()	as $combo_count){
		echo 'Total submissions: ';
		echo $combo_count['total'];
	}
	
}

function logs($conn){
	//require "server/conexao.php";
	$query = "SELECT * FROM logs ORDER BY Date DESC;";
	$result = $conn -> prepare($query);
	$result -> execute();
	
	foreach($result -> get_result()	as $log){
		echo $log['Date'].': ';
		echo $log['Description'].'<br>';
	}
}

function count_char($conn){
	//require "server/conexao.php";
	$query = "SELECT `character_idcharacter`, COUNT(idcombo) as amount, `character`.`Name` FROM `combo` INNER JOIN `character` ON `character`.`idcharacter` = `combo`.`character_idcharacter` WHERE `character`.`game_idgame` = ? GROUP BY `character_idcharacter` ORDER BY amount DESC";
	$result = $conn -> prepare($query);
	$result -> bind_param("i",$_GET['gameid']);
	$result -> execute();
	$i = 1;
	foreach($result -> get_result() as $data){
		if($i){
			echo '<p><h3> Total Entries </h3>';
			echo '▰';
			$i--;
		}
		echo ' '.$data['Name'].': '.$data['amount'].' ▰ ';
	}
}

function delete_combo($idcombo, $conn){
	//require "server/conexao.php";
	$query = "DELETE FROM `resources` WHERE `combo_idcombo` = ?";
	$result = $conn -> prepare($query);
	$result -> bind_param("i", $idcombo);
	$result -> execute();

	$query = "DELETE FROM `combo` WHERE `idcombo` = ?";
	$result = $conn -> prepare($query);
	$result -> bind_param("i", $idcombo);
	$result -> execute();	
}

function entry_select($selected, $showall, $conn){ //Has to come before quick_search_form showall = 1 -> Show All showall = 2 -> Every Entry
	//require "server/conexao.php";
	$query = "SELECT `entryid`, `title` FROM `game_entry` WHERE `gameid` = ? ORDER BY `order`,`title`;";
	$result = $conn -> prepare($query);
	$result -> bind_param("i",$_GET['gameid']);
	$result -> execute();
	echo '<p><select name="listingtype" class="custom-select">';
	foreach($result -> get_result()	as $lol){
		echo 	'<option value="'.$lol['entryid'].'"';
		if($selected == $lol['entryid'])echo ' selected ';
		echo '>'.$lol['title'].'</option>';
	}
	if($showall == 1)echo '<option value="-">Show All</option>';
	if($showall == 2)echo '<option value="-" selected>Every Entry</option>';
	echo '</select></p>';
}

function character_dropdown($conn){
	$query = "SELECT `Name`,`idcharacter` FROM `character` WHERE game_idgame = ? ORDER BY name;";
	$result = $conn -> prepare($query);
	$result -> bind_param("i", $_GET['gameid']);
	$result -> execute();
		
	echo '<p><select name="characterid" class="custom-select">';
		
	echo '<option value="-">Character</option>';
	foreach($result -> get_result() as $character){
		echo '<option value="'.$character['idcharacter'].'" ';
		echo '>'.$character['Name'].'</option>';
	}
	echo '</select></p>'; 
}

function quick_search_form($gameid, $conn){
						//require "server/conexao.php";
						
						echo '<form class="form-inline" method="get" action="submit.php">
					<input type="hidden" id="gameid" name="gameid" value="'.$_GET['gameid'].'">';
						
						character_dropdown($conn);
							
						entry_select(0,1, $conn);
						
							echo '<p><textarea style="background-color: #474747; color:#999999;" name="combo" class="form-control" id="comboarea" rows="1" title="combo" placeholder="Starter"></textarea>';
							echo '</p>';
							
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
								}else if($resource['type'] == 3){
								$duplicated_resource = 0;
								while($duplicated_resource++ != 2){
										echo '<div class="input-group mb-3">
	  <div class="input-group-prepend">
		<label class="input-group-text">'; 
										echo '</label></div>  <select name="';
										echo $resource['text_name'];
										
										echo '[]"class="custom-select input-small">';
										
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
										
									}
								}
							}
							
							echo '</p><br>';
							echo '
						
					<div class="form-group mb-2">
						<button type="submit" class="btn btn-info mb-2">Quick Search</button>
					</div>
					
				</form>';
}

function button_printing($idgame, $dataCombo, $conn){
	//require 'server/conexao.php';
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

function get_game($gameid, $conn){
	//require "server/conexao.php";
	$query = "SELECT name,complete,image FROM game WHERE idgame = ?;";
	$result = $conn -> prepare($query);
	$result -> bind_param("i",$gameid);
	$result -> execute();
	
	return $result;// -> get_result();
}

function verify_resourcevalue_game($idresourcevalue, $conn){
	//require "server/conexao.php";
	$query = "SELECT `game`.`idgame` as game FROM `resources_values` 
JOIN `game_resources` ON `game_resources`.`idgame_resources` = `resources_values`.`game_resources_idgame_resources`
JOIN `game` ON `game`.`idgame` = `game_resources`.`game_idgame`
WHERE `idResources_values` = ?";
	$result = $conn -> prepare($query);
	//print_r($_POST);
	$result -> bind_param("i",$idresourcevalue);
	$result -> execute();
	$result = $result -> get_result();
	$row = $result -> fetch_array(MYSQLI_ASSOC);
	return $row['game'];
}

function verify_resource_game($idresource, $conn){
	//require "server/conexao.php";
	$query = "SELECT `game_idgame` as game FROM `game_resources` WHERE `idgame_resources` = ?";
	$result = $conn -> prepare($query);
	//print_r($_POST);
	$result -> bind_param("i",$idresource);
	$result -> execute();
	$result = $result -> get_result();
	$row = $result -> fetch_array(MYSQLI_ASSOC);
	return $row['game'];
}

function print_all_buttons(){
	$directory = "img/buttons";
	$images = glob($directory . "/*.png");

	foreach($images as $image){
		//echo $image;
		echo '<button type="button" style="border:none;background:none;margin-left:1em;margin-bottom:1em" value="'; //style="border:none;background:none;"border-color: #000000;background-color: #002f7c;
		$image = str_replace("img/buttons/", "", $image);
		echo str_replace(".png", "", $image);
		echo '"onclick="moveNumbers(this.value)"><img src=img/buttons/';
		echo $image;
		echo '> </button> ';	

	}
}

function print_game_links($idgame, $conn){
	//require "server/conexao.php";
	$query = "SELECT * FROM `link` WHERE `idGame` = ? ORDER BY `title`;";
	$result = $conn -> prepare($query);
	$result -> bind_param("i",$idgame);
	$result -> execute();
	$i = 1;
	foreach($result -> get_result()	as $lol){
		if($i){
			echo '<h3>Related Links: </h3>';
			echo '▰ ';
			$i--;
		}
		echo '<a href="'.$lol['Link'].'" target="_blank">'.$lol['Title'].'</a> ▰ ';
	}
}

function edit_controls($gameid){
	echo '<div class="btn-group" role="group">
							<form method="get" action="editcharacters.php">
								<button class="btn btn-secondary">Characters</button>
								<input type="hidden" name="gameid" value="'.$gameid.'">
							</form>
							<form method="get" action="editbuttons.php">
							<input type="hidden" name="gameid" value="'.$gameid.'">
								<button class="btn btn-secondary">Buttons</button>
							</form>

							<form method="get" action="editresources.php">
							<input type="hidden" name="gameid" value="'.$gameid.'">
								<button class="btn btn-secondary">Resources</button>
							</form>
							
							<form method="get" action="editlinks.php">
								<input type="hidden" name="gameid" value="'.$gameid.'">
								<button class="btn btn-secondary">Links</button>
							</form>
							
							<form method="get" action="editentries.php">
								<input type="hidden" name="gameid" value="'.$gameid.'">
								<button class="btn btn-secondary">Entries</button>
							</form>
							
							<form method="get" action="editcombos.php">
								<input type="hidden" name="gameid" value="'.$gameid.'">
								<button class="btn btn-secondary">Mass Edit</button>
							</form>
							
							<form method="get" action="editlists.php">
								<input type="hidden" name="gameid" value="'.$gameid.'">
								<button class="btn btn-secondary">Lists</button>
							</form>
						</div>';
}

function header_buttons($buttons, $back, $backDestination, $backto){ //Buttons=1 -> Home/Listing Buttons>1 -> Home/Listing/Submit/Search/Edit Game Back=1 -> Game Back=2 -> Combo
	if($buttons): ?>
		<div class="btn-group" role="group" aria-label="Basic example">
		
			<?php 
				if($back){
					echo '	<form method="get" action="'.$backDestination.'">';
					if($back == 1){
						echo '	<input type="hidden" id="gameid" name="gameid" value="'.$_GET['gameid'].'">';
						echo '		<button class="btn btn-secondary">'.$backto.'</button>';
					}else if($back == 2){
						echo '	<input type="hidden" id="idcombo" name="idcombo" value="'.$_POST['idcombo'].'">';
						echo '		<button class="btn btn-secondary">'.$backto.'</button>';
					}else{
						echo '		<button class="btn btn-secondary"> << back</button>';
					}
								
				//	echo '		<button class="btn btn-secondary"> << back</button>
					echo '		</form>';
					
				}			
			?>
			<form method="get" action="index.php">
				<button class="btn btn-secondary">Combo好き</button>
			</form>
			
			<form method="get" action="list.php">
				<button class="btn btn-secondary">Listing</button>
			</form>
			
			<?php if($buttons > 1): ?>
			
			<form method="post" action="forms.php?gameid=<?php echo $_GET['gameid']; ?>">
				<button class="btn btn-secondary">Submit</button>
				<input type="hidden" id="type" name="type" value="1">
			</form>

			<form method="get" action="forms.php">
				<button class="btn btn-secondary">Search</button>
				<input type="hidden" id="gameid" name="gameid" value="<?php echo $_GET['gameid'] ?>">
			</form>
			
			<form method="get" action="editgame.php">
				<button class="btn btn-secondary">Edit Game</button>
				<input type="hidden" name="gameid" value="<?php echo $_GET['gameid'] ?>">
			</form>
			<?php endif; ?>
		</div>
	<?php
	endif;
}

function get_mod_password($gameid, $conn){
	$query = "SELECT modPass FROM game WHERE idgame = ?";
	$result = $conn -> prepare($query);
	$result -> bind_param("i", $gameid);
	$result -> execute();
	foreach($result -> get_result() as $pass){
		return $pass['modPass'];
	}
}

function verify_password($conn){
	$query = "SELECT globalPass, modPass, complete FROM game WHERE idgame = ?";
	$result = $conn -> prepare($query);
	$result -> bind_param("i", $_GET['gameid']);
	$result -> execute();
	foreach($result -> get_result() as $pass){
		if($pass['globalPass'] != $_POST['gamePass'] && (!password_verify($_POST['gamePass'], $pass['modPass']) || $pass['complete'] == 2)){
			header("Location: index.php");
			exit();
		}
	}	
}

function get_combogame($comboid, $conn){
	$query = "	SELECT `game`.`idgame` as game FROM `combo` 
JOIN `character` ON `character`.`idcharacter` = `combo`.`character_idcharacter`
JOIN `game` ON `game`.`idgame` = `character`.`game_idgame`
WHERE `idcombo` = ?";
	$result = $conn -> prepare($query);
	$result -> bind_param("i", $comboid);
	$result -> execute();
	foreach($result -> get_result() as $lol){
		return $lol['game'];
	}	

}

function get_gamepassword($gameid, $conn){
	$query = "SELECT `globalPass` FROM `game` WHERE `idgame` = ?";
	$result = $conn -> prepare($query);
	$result -> bind_param("i", $gameid);
	$result -> execute();
	foreach($result -> get_result() as $lol){
		return $lol['globalPass'];
	}
}

function get_combolink($comboid,$conn){
	return 'http://combosuki.com/combo.php?idcombo='.$comboid;
}

function print_listglyph($type, $conn){
	if($type == 2)echo ' <img src="img/buttons/vanish.png" height="35" name="image-579" data-toggle="tooltip" title="Verified List">';
	if($type == 3)echo ' <img src="img/buttons/kicharge.png" height="35" name="image-579" data-toggle="tooltip" title="Moderated List">';
}

function meta_embedvideo($video){
	if($video != '')echo '<meta property="og:type" content="video">';
	if(strpos($video, 'youtu') !== false){
		echo '<meta property="og:url" content="'.$video.'" />';
	}else if(strpos($video, 'twitter') !== false && strpos($video, 'https') !== false){
		echo '<meta property="og:url" content="'.$video.'" />';
	}else{
		echo '<meta property="og:image" content="http://combosuki.com/img/combosuki.png" />';
	}
}

function strip_POSTtags(){
	foreach($_POST as $key => $each){
		if(is_scalar($each)){
		   //treat any scalar value as string and do stuff:
		 //  echo $each.'<-->'.$key.'next>';
		   $_POST[$key] = strip_tags($each);
		}else{
		   foreach($each as $key_array => $each_array){
			   $_POST[$key][$key_array] = strip_tags($each_array);
		   }
		}
	}
	
}

function strip_GETtags(){
	foreach($_GET as $key => $each){
		if(is_scalar($each)){
		   //treat any scalar value as string and do stuff:
		 //  echo $each.'<-->'.$key.'next>';
		   $_GET[$key] = strip_tags($each);
		}else{
		   foreach($each as $key_array => $each_array){
			   $_GET[$key][$key_array] = strip_tags($each_array);
		   }
		}
	}
	
}

function verify_gameresource($resourceValueID, $conn){
	$query = "SELECT `game_idgame` as idgame FROM `game_resources` 
JOIN `resources_values` ON `game_resources`.`idgame_resources` = `resources_values`.`game_resources_idgame_resources`
WHERE `resources_values`.`idResources_values` = ?";
	$result = $conn -> prepare($query);
	$result -> bind_param("i", $resourceValueID);
	$result -> execute();
	foreach($result -> get_result() as $game){
		return $game['idgame'];
	}
	return 0;
}

function get_gamename($gameid, $conn){
		$query = "SELECT `name` FROM `game` WHERE `idgame` = ?";
	$result = $conn -> prepare($query);
	$result -> bind_param("i", $gameid);
	$result -> execute();
	foreach($result -> get_result() as $lol){
		return $lol['name'];
	}	

}

?>
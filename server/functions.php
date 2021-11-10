<?php

function embed_video($video){
	if($video != ''){
		echo '<table class="table table-hover align-middle combosuki-main-reversed text-white">';
			echo '<tr>
				<td>';
					echo 'Video:';
				echo '</td>
			</tr>';
			echo '<tr>
				<td>';
					embed_video_notable($video);
				echo '</td>
			</tr>';
		echo '</table>';
	}
}

function embed_video_notable($video){ //Twitter, Youtube, Twitch clip, Nico Nico, imgur
	if($video != ''){
		echo '<div class="card card-body p-3 mb-2 bg-dark border border-5 border-dark">';
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
			$whatIWant = substr($video, strpos($video, "=") + 1);    
			echo '<div class="ratio ratio-16x9">
			<iframe src="https://www.youtube.com/embed/';
			echo $youtube_id;
			echo '?start=';echo $whatIWant; echo '" allowfullscreen></iframe></div>';
		}else if(strpos($video, 'streamable') !== false && strpos($video, 'https') !== false){
								/*echo '<div class="embed-responsive embed-responsive-16by9">
								<iframe class="embed-responsive-item" src="';
								echo $data['video'];
								echo '" allowfullscreen></iframe>
							</div>';*/
							//$i = substr_replace($data['video'], "/s", 22,0);
			echo '<div style="width: 100%; height: 0px; position: relative; padding-bottom: 56.250%;"><iframe src="';
			echo $video;
			echo '" frameborder="0" width="100%" height="100%" allowfullscreen style="width: 100%; height: 100%; position: absolute;"></iframe></div>';
			echo '<br>Please consider uploading your video to another platform, streamable Videos that are inactive for 3 months are deleted.';
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
			echo '<script type="application/javascript" src="https://embed.'.$i.'/script?w=640&h=360"></script>';
		}else if(strpos($video, 'nico') !== false && strpos($video, 'https') !== false){
			$i = substr($video, 16);
			$i = substr_replace($i,'w=640&h=360&',11	,0);
			$i = substr_replace($i,'/script',10	,0);
			echo '<script type="application/javascript" src="https://embed.nicovideo.jp/watch/'.$i.'"></script>';
			
		}else if(strpos($video, 'gfycat') !== false && strpos($video, 'https') !== false){
			$i = $video;
			$i = substr_replace($i,'/ifr',18,0);
			echo '<div style="position:relative; padding-bottom:calc(56.40% + 44px)"><iframe src='.$i.' frameborder="0" scrolling="no" width="100%" height="100%" style="position:absolute;top:0;left:0;" allowfullscreen></iframe></div>';
			
		}else if(strpos($video, 'medal') !== false && strpos($video, 'https') !== false){
			$video = str_replace('/clips/','/clip/',$video);
			echo '<iframe 
				width="640" 
				height="360" 
				style="border: none;" 
				src="'.$video.'"
				allowfullscreen></iframe>';
			
		}else{
			echo $video;	
		}
		echo '</div>';
	}
}

function embed_video_on_demand($video, $id){ //Twitter, Youtube, Twitch clip, Nico Nico, imgur
	//Currently incomplite, only working with youtube and streamable.
	if($video != ''){
		echo '<div class="card card-body p-3 mb-2 bg-dark border border-5 border-dark ">';
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
			$whatIWant = substr($video, strpos($video, "=") + 1);
			echo '<div class="ratio ratio-16x9">
			<iframe id="v'.$id.'" class="embed-responsive-item" data-src="https://www.youtube.com/embed/';
			echo $youtube_id;
			echo '?start=';echo $whatIWant; echo '" src="about:blank" allowfullscreen></iframe></div>';
		}else if(strpos($video, 'streamable') !== false && strpos($video, 'https') !== false){
								/*echo '<div class="embed-responsive embed-responsive-16by9">
								<iframe class="embed-responsive-item" src="';
								echo $data['video'];
								echo '" allowfullscreen></iframe>
							</div>';*/
							//$i = substr_replace($data['video'], "/s", 22,0);
			echo '<div style="width: 100%; height: 0px; position: relative; padding-bottom: 56.250%;"><iframe id="v'.$id.'" data-src="';
			echo $video;
			echo '" frameborder="0" width="100%" height="100%" allowfullscreen style="width: 100%; height: 100%; position: absolute;" src="about:blank" ></iframe></div>';
			echo '<br>Please consider uploading your video to another platform, streamable Videos that are inactive for 3 months are deleted.';
								
								
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
			
		}else if(strpos($video, 'gfycat') !== false && strpos($video, 'https') !== false){
			$i = $video;
			$i = substr_replace($i,'/ifr',18,0);
			//echo $i;
			echo '<div style="position:relative; padding-bottom:calc(56.40% + 44px)"><iframe src='.$i.' frameborder="0" scrolling="no" width="100%" height="100%" style="position:absolute;top:0;left:0;" allowfullscreen></iframe></div>';
			
		}else if(strpos($video, 'medal') !== false && strpos($video, 'https') !== false){
			$video = str_replace('/clips/','/clip/',$video);
			echo '<iframe 
				id="v'.$id.'"
				width="640" 
				height="360" 
				style="border: none;" 
				data-src="'.$video.'"
				allowfullscreen></iframe>';
			
		}else{
			echo $video;	
		}
		echo '</div>';
	}
}

function print_listingtype($listingtype, $conn){
	$query = "SELECT `title` FROM `game_entry` WHERE `entryid` = ?;";
	$result = $conn -> prepare($query);
	$result -> bind_param("i",$listingtype);
	$result -> execute();
	foreach($result -> get_result()	as $lol){
		echo ' '.$lol['title'];
	}
}

function combo_table($gameid, $conn){
						$query = "SELECT idcombo,Name,combo,damage,type, comments, submited, video FROM `combo` INNER JOIN `character` ON `combo`.`character_idcharacter` = `character`.`idcharacter` WHERE `character`.`game_idgame` = ? ORDER BY submited DESC LIMIT 0,5";
						$result = $conn -> prepare($query);
						$result -> bind_param("i",$gameid);
						$result -> execute();
						
						echo '<table id="myTable" class="table table-hover align-middle caption-top combosuki-main-reversed text-white">';
						echo '<caption>Click the character name to see comments/video if the entry has them.</caption><tr>';
						echo '<th onclick="sortTable(0)">Character</th><th onclick="sortTable(1)">Inputs</th><th onclick="sortTable(2,1)">Damage</th><th onclick="sortTable(3)">Type</th><th onclick="sortTable(4)">Submited</th>';
						echo '</tr>';
						
						foreach($result -> get_result() as $data){
							echo '<tr><td data-toggle="tooltip" data-placement="bottom" title="'.$data['comments'].'">';
							if($data['comments'] != '' || $data['video'] != ''){
								echo '<button class="btn btn-dark" type="button" data-bs-toggle="collapse" data-bs-target="#collapse'.$data['idcombo'].'"">'.$data['Name'].'</button>'; //onclick="showDIV('.$data['idcombo'].')
							}else{
								echo $data['Name'];
							}
							echo '</td><td style="min-width:400px">';
							echo		'<text data-toggle="tooltip" data-placement="bottom" title="'.$data['comments'].'" href="combo.php?idcombo='.$data['idcombo'].'">'.$data['combo'].'</text>';
							echo '	<a href="combo.php?idcombo='.$data['idcombo'].'">
										<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-box-arrow-up-right" viewBox="0 0 16 16">
										  <path fill-rule="evenodd" d="M8.636 3.5a.5.5 0 0 0-.5-.5H1.5A1.5 1.5 0 0 0 0 4.5v10A1.5 1.5 0 0 0 1.5 16h10a1.5 1.5 0 0 0 1.5-1.5V7.864a.5.5 0 0 0-1 0V14.5a.5.5 0 0 1-.5.5h-10a.5.5 0 0 1-.5-.5v-10a.5.5 0 0 1 .5-.5h6.636a.5.5 0 0 0 .5-.5z"/>
										  <path fill-rule="evenodd" d="M16 .5a.5.5 0 0 0-.5-.5h-5a.5.5 0 0 0 0 1h3.793L6.146 9.146a.5.5 0 1 0 .708.708L15 1.707V5.5a.5.5 0 0 0 1 0v-5z"/>
										</svg>
									</a>';
							echo '<div class="collapse" id="collapse'.$data['idcombo'].'">'; //style="display: none;"
							echo $data['comments'].'<br>';
  
  //####################################################################VIDEO HERE
							embed_video_notable($data['video']);
  //######################################################################VIDEO ABOVE
  
							echo '</div></div>';
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

function game_title($flImageCard){
	global $conn;
	$query = "SELECT `subQuery`.`idgame`, `subQuery`.`name`, `subQuery`.`image` FROM (SELECT `game`.`idgame`, `game`.`name`, `game`.`image`, `game`.`complete` FROM `combo`
INNER JOIN `character` ON `character`.`idcharacter` = `combo`.`character_idcharacter`
INNER JOIN `game` ON `game`.`idgame` = `character`.`game_idgame`
WHERE `game`.`complete` > 0
GROUP BY `game`.`idgame`
ORDER BY COUNT(`combo`.`character_idcharacter`) DESC LIMIT 20) as subQuery ORDER BY subQuery.`name`;";
	$result = $conn -> prepare($query);
	$result -> execute();
	echo'<div class="row">';
		foreach($result -> get_result()	as $gameid){
			echo '<div class="col my-3">
				<div class="card text-center w-100 p-3 h-100 combosuki-main-reversed">';
				if($flImageCard){
				echo '
					<div class="card text-center w-100 p-3 h-100" style="background-color:#';
					echo $_COOKIE['color'];					
					echo ';">
					<span style=" display: inline-block;
					height: 30%;
					vertical-align: middle;"></span>
						<img class="rounded mx-auto d-block" style="
						max-height:200px;
						max-width:200px;
						height:auto;
						width:auto;
						align: center;
						vertical-align: middle;
						display: block;
						" src="'.$gameid['image'].'" class="card-img-top rounded mx-auto d-block" alt="Responsive image"></img></div>';
				}
						echo '<div class="card-body">';
						echo '<a class="card-title text ';
						echo !$flImageCard ? 'text-nowrap ' : '';
						echo 'text-white " ';
						echo 'href="game/index.php?gameid=';
						echo $gameid['idgame'];
						echo '">'.$gameid['name'].'</a>';
						echo '
					</div>
				</div>
			</div>';
		}
	echo '</div>';	
}

function game_text_only(){
	global $conn;
	//require "server/conexao.php";
	$query = "SELECT idgame, `game`.`name`, COUNT(DISTINCT `combo`.`idcombo`) AS Entries
FROM game 
LEFT JOIN `character` ON `character`.`game_idgame` = `game`.`idgame`
LEFT JOIN `combo` ON `combo`.`character_idcharacter` = `character`.`idcharacter`
WHERE 1 
GROUP BY `game`.`name`
ORDER BY `game`.`name`";
	$result = $conn -> prepare($query);
	$result -> execute();
	echo '<div class="container">
		<div class="row combosuki-main-reversed rounded">';
		foreach($result -> get_result()	as $gameid){
			echo '<div class="col border-combosuki text-start">';
				echo '<a class="link-light text text-nowrap" style="margin-left:5em;" href=game/index.php?gameid=';
				echo $gameid['idgame'];
				echo '>';
				echo $gameid['name'].' ('.$gameid['Entries'].')</a>';
			echo '</div>';
		}
	echo '</div>
	</div>';
		
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
			echo ' align="bottom" height="'.$height.'" >';
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
		echo 'Submissions total: ';
		echo $combo_count['total'];
	}
	
}

function logs($conn){
	//require "server/conexao.php";
	$query = "SELECT * FROM logs ORDER BY Date DESC;";
	$result = $conn -> prepare($query);
	$result -> execute();
	echo '<div class="combosuki-main-reversed">';
	foreach($result -> get_result()	as $log){
		echo $log['Date'].': ';
		echo $log['Description'].'<br>';
	}
	echo '</div>';
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
			echo '<h3> Entries per Character </h3>
			<div class="row text-center combosuki-main-reversed">';
			$i--;
		}
		echo '<div class="col text-nowrap">';
		echo '▰';
		echo ' '.$data['Name'].': '.$data['amount'].' ▰ ';
		echo '</div>';
	}
	if(!$i){
		echo '</div>';
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

function entry_select($selected, $showall, $conn){ //Showall = 1 -> Show All showall = 2 -> Every Entry
	$query = "SELECT `entryid`, `title` FROM `game_entry` WHERE `gameid` = ? ORDER BY `order`,`title`;";
	$result = $conn -> prepare($query);
	$result -> bind_param("i",$_GET['gameid']);
	$result -> execute();
	echo '
	<div class="col-auto my-1">
		<select name="listingtype" class="form-select">';
			foreach($result -> get_result()	as $lol){
				echo 	'<option value="'.$lol['entryid'].'"';
				if($selected == $lol['entryid'])echo ' selected ';
				if(isset($_GET['listingtype']) && $selected == 0){
						if($_GET['listingtype'] == $lol['entryid'])echo ' selected ';
				}
				echo '>'.$lol['title'].'</option>';
			}
			if($showall == 1)echo '<option value="-">Show All</option>';
			if($showall == 2)echo '<option value="-" selected>Every Entry</option>';
			echo '
		</select>
	</div>';
}

function character_dropdown($conn){
	$query = "SELECT `Name`,`idcharacter` FROM `character` WHERE game_idgame = ? ORDER BY name;";
	$result = $conn -> prepare($query);
	$result -> bind_param("i", $_GET['gameid']);
	$result -> execute();
	echo '<div class="col-auto my-1"><select name="characterid" class="form-select">';
	echo '<option value="-">Character</option>';
	foreach($result -> get_result() as $character){
		echo '<option value="'.$character['idcharacter'].'" ';
		if(isset($_GET['characterid'])){
			if($_GET['characterid'] == $character['idcharacter']){
				echo ' selected ';
			}
		}
		echo '>'.$character['Name'].'</option>';
	}
	echo '</select></div>'; 
}

function quick_search_form($gameid, $conn){
	echo '<form class="form-control bg-dark" method="get" action="submit.php"><div class="row">
	<input type="hidden" name="gameid" value="'.$_GET['gameid'].'">';
	character_dropdown($conn);
	entry_select(0,1, $conn);
	echo '<div class="col-auto my-1"><textarea style="background-color: #474747; color:#999999;" name="combo" class="form-control" id="comboarea" rows="1" title="combo" placeholder="Starter">';
	if(isset($_GET['combo'])){
		echo $_GET['combo'];
	}
	echo '</textarea></div>';
	$query = "SELECT text_name,type,idgame_resources FROM `game_resources` WHERE game_idgame = ? AND primaryORsecundary = 1 ORDER BY game_resources.primaryORsecundary DESC,text_name;";
	$result = $conn -> prepare($query);
	$result -> bind_param("i", $gameid);
	$result -> execute();
	foreach($result -> get_result()	as $resource){
		$getName = str_replace(' ','_',$resource['text_name']);
		if($resource['type'] == 1){ //List resource
			echo '<div class="col-auto my-1"><div class="input-group mb-3">'; 
			echo '<select name="';
			echo $resource['text_name'];
			echo '"class="form-select input-small">';
			$query = "SELECT idResources_values,value FROM `resources_values` WHERE `game_resources_idgame_resources` = ".$resource['idgame_resources']." ORDER BY resources_values.order, value;";
			$result = $conn -> prepare($query);
			$result -> execute();
			echo '<option value="-">'.$resource['text_name'].'</option>';
			foreach($result -> get_result() as $resource_value){
				echo '<option value="';
				echo $resource_value['idResources_values'].'" ';
				if(isset($_GET[$getName])){
					if($_GET[$getName] == $resource_value['idResources_values']){
						echo ' selected ';
					}
				}
				echo '>';
				echo $resource_value['value'];
				echo '</option>';
			}
			echo '</select></div></div> ';
		}else if($resource['type'] == 2){ //Number Resource
			$query = "SELECT idResources_values,value FROM `resources_values` WHERE `game_resources_idgame_resources` = ?;";
			$result = $conn -> prepare($query);
			$result -> bind_param("i", $resource['idgame_resources']);
			$result -> execute();
			foreach($result -> get_result() as $resource_value){
				$compare = $resource['text_name'];
				$compare = str_replace(' ','_',$compare);
				echo '<div class="col-auto my-1">';
				echo '<select name="';
				echo $resource['text_name'].'compare';
				echo '"class="form-select"><option value=0>less than</options><option value=1';
				if(isset($_GET) && isset($_GET[$compare.'compare'])){
					if($_GET[$compare.'compare'] == 1){
						echo ' selected ';
					}
				}
				echo '>greater than</options><option value=2';
				if(isset($_GET) && isset($_GET[$compare.'compare'])){
					if($_GET[$compare.'compare'] == 2){
						echo ' selected ';
					}
				}
				unset($compare);
				echo '>equal to</options></select></div>';
				echo '<div class="col-auto my-1">';
				echo '<input class="form-control" type="number" min="-'.$resource_value['value'].'" name="';
				echo $resource['text_name'];
				echo '" placeholder="'.$resource['text_name'].'"';
				echo ' max="';
				echo $resource_value['value'];
				echo '"step="any"' ;
				if(isset($_GET[$getName])){
					echo ' value="'.$_GET[$getName].'" ';
				}
				echo '> </div>';
			}
		}else if($resource['type'] == 3){ //Duplicated Resource
			$duplicated_resource = 0;
			while($duplicated_resource++ != 2){
				echo '<div class="col-auto my-1"><select name="';
				echo $resource['text_name'];
				echo '[]"class="form-select">';
				$query = "SELECT idResources_values,value FROM `resources_values` WHERE `game_resources_idgame_resources` = ".$resource['idgame_resources']." ORDER BY resources_values.order, value;";
				$result = $conn -> prepare($query);
				$result -> execute();
				echo '<option value="-">'.$resource['text_name'].'</option>';
				foreach($result -> get_result() as $resource_value){
					echo '<option value="';
					echo $resource_value['idResources_values'].'" ';
					if(isset($_GET[$getName][$duplicated_resource-1])){
						if($_GET[$getName][$duplicated_resource-1] == $resource_value['idResources_values']){
							echo ' selected ';
						}
					}
					echo '>';
					echo $resource_value['value'];
					echo '</option>';
				}
				echo '</select></div> ';
			}
		}
	}
	echo '

	<div class="col-sm-auto my-1">
	<button type="submit" class="btn btn-info mb-2">Quick Search</button>
	</div>
	</div>
	</form>';
}

function button_printing($idgame, $dataCombo, $conn){
	//require 'server/conexao.php';
	global $URLDepth;
	$query = "SELECT name,png FROM `button` WHERE `game_idgame` = ?";
	$result = $conn -> prepare($query);
	$result -> bind_param("i",$idgame);
	$result -> execute();
	//$size = '4%';
	
	$buttonsName = array();
	$buttonsPNG = array();
	
	foreach($result -> get_result() as $each){
		array_push($buttonsName,$each['name']);
		array_push($buttonsPNG,$each['png']);
	}
	$combo_image = '<img class="img-fluid" alt="Responsive image" src='.$URLDepth.'img/buttons/start.png>';
	$buttonID;
	$buttonsName = array_map('strtolower', $buttonsName);
	
	$array = str_split($dataCombo);
	$image = '';
	foreach($array as $char){
		
			if(isset($char) && $char != ' '){
				
				$image .= strtolower($char);
							
			}else if($image != ''){
				//$buttonID = array_search($image,$buttonsName);
				$buttonID = array_search($image, $buttonsName);
				//array_search(strtolower($image), array_map('strtolower', $buttonsName));
				//echo $buttonID;
				if($buttonID > -1){
					if($image == '\n'){$combo_image .= '<br>';}
					$combo_image .= '<img class="img-fluid" alt="Responsive image" src='.$URLDepth.'img/buttons/';
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
					$combo_image .= '<img class="img-fluid" alt="Responsive image" src='.$URLDepth.'img/buttons/';
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
function print_game_description($idgame, $conn){
	//require "server/conexao.php";
	$query = "SELECT `description` FROM `game` WHERE `idGame` = ?;";
	$result = $conn -> prepare($query);
	$result -> bind_param("i",$idgame);
	$result -> execute();
	foreach($result -> get_result()	as $lol){
		//echo '<h3>Description: </h3>';
		echo $lol['description'];
	}
}

function print_game_notation($idgame, $conn){
	//require "server/conexao.php";
	$query = "SELECT `notation` FROM `game` WHERE `idGame` = ?;";
	$result = $conn -> prepare($query);
	$result -> bind_param("i",$idgame);
	$result -> execute();
	foreach($result -> get_result()	as $lol){
		echo $lol['notation'];
	}
}

function edit_controls($gameid){
	echo '<div class="btn-group">
			<form method="get" action="characters.php">
				<button class="btn btn-secondary">Characters</button>
				<input type="hidden" name="gameid" value="'.$gameid.'">
			</form>
			<form method="get" action="buttons.php">
			<input type="hidden" name="gameid" value="'.$gameid.'">
				<button class="btn btn-secondary">Buttons</button>
			</form>

			<form method="get" action="resources.php">
			<input type="hidden" name="gameid" value="'.$gameid.'">
				<button class="btn btn-secondary">Resources</button>
			</form>
			
			<form method="get" action="links.php">
				<input type="hidden" name="gameid" value="'.$gameid.'">
				<button class="btn btn-secondary">Links</button>
			</form>
			
			<form method="get" action="entries.php">
				<input type="hidden" name="gameid" value="'.$gameid.'">
				<button class="btn btn-secondary">Entries</button>
			</form>
			
			<form method="get" action="mass.php">
				<input type="hidden" name="gameid" value="'.$gameid.'">
				<button class="btn btn-secondary">Mass Edit</button>
			</form>
			
			<form method="get" action="lists.php">
				<input type="hidden" name="gameid" value="'.$gameid.'">
				<button class="btn btn-secondary">Lists</button>
			</form>
		</div>';
}

function header_buttons($buttons, $back, $backDestination, $backto){ //Buttons=1 -> Home/Lists Buttons>1 -> Home/Lists/Submit/Search/Edit Game Back=1 -> Game Back=2 -> Combo $backDestination == URL $backto == gameName
	global $URLDepth;
	if($buttons): ?>
		<nav class="navbar navbar-expand-lg navbar-dark bg-combosuki-main-2">
			<?php if($back == 1):?>
			<a class="navbar-brand" href="<?php echo $URLDepth; ?>index.php">
				<img src="<?php echo $URLDepth; ?>img/selo.png" style="margin-left:20px" width="30" height="30">
			</a>
			<?php endif;?>
			<div class="container-fluid">
				<a class="navbar-brand" href="<?php echo $URLDepth?>index.php">Combo好き</a>
				<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
					<span class="navbar-toggler-icon"></span>
				</button>
				<div class="collapse navbar-collapse" id="navbarSupportedContent">
					<ul class="navbar-nav me-auto mb-2 mb-lg-0">
						<?php
							
							if($back){
								if($back == 1){
									echo '<li class="nav-item">';
										echo '<a class="nav-link active" href="'.$URLDepth.'game/index.php?gameid='.$_GET['gameid'].'">';
										echo $backto.'</a>';
									echo '</li>';
								}else if($back == 2){
									echo '<li class="nav-item">';
										echo '<form method="get" action="combo.php">';
											echo '	<input type="hidden" name="idcombo" value="'.$_POST['idcombo'].'">';
											echo '	<button class="btn btn-secondary">Entry ID: '.$_POST['idcombo'].'</button>';
										echo '</form>';
									echo '</li>';
								}
							}
							if(!isset($_GET['gameid'])){
								echo '<li class="nav-item">
										<a class="nav-link" href="index.php?about=1">About</a>
									</li>';
								echo '<li class="nav-item">
										<a class="nav-link" href="index.php?about=5">Games</a>
									</li>';
								echo '<li class="nav-item">
										<a class="nav-link" href="https://github.com/Ikkisoad/combosuki" target="_blank">GitHub</a>
									</li>';
							}
						?>
						<li class="nav-item">
							<a class="nav-link" href="<?php echo $URLDepth; ?>list.php">Lists</a>
						</li>
						
						<li class="nav-item dropdown">
							<a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
							...
							</a>
							<ul class="dropdown-menu" aria-labelledby="navbarDropdown">
								<?php

									echo '<li><a class="dropdown-item" href="'.$URLDepth.'game/add.php">Add Game</a></li>
										<li><hr class="dropdown-divider"></li>';
									echo '<li><a class="dropdown-item" href="index.php?about=2">Combo Guidelines</a></li>';
									echo '<li><a class="dropdown-item" href="https://srk.shib.live/w/Shoryuken_Wiki:Community_portal/Discords/Game" target="_blank">FGC Discord Compendium</a></li>';
									echo '<li><hr class="dropdown-divider"></li>';
									echo '<li><a class="dropdown-item" href="index.php?about=4">Preferences</a></li>';
									echo '<li><a class="dropdown-item" href="index.php?about=3">Logs</a></li>';
								?>
							</ul>
						</li>
						<li class="nav-item">
							<a class="nav-link disabled" href="#" tabindex="-1" aria-disabled="true">I am doing some changes, sorry if I broke anytthing.</a>
						</li>
					</ul>
					<?php
						if(isset($_GET['listid'])){
							echo '<li class="nav-item" style="list-style-type: none;">
								<form class="d-flex" method="get" action="list.php">';
									echo '<input type="hidden" name="listid" value="'.$_GET['listid'].'">';
									echo '<button class="btn btn-secondary">List ID:'.$_GET['listid'].'</button>';
								echo '</form>
								</li>';
						}
						
					?>
					<?php if($buttons > 1): ?>
						<form method="post" action="<?php echo $URLDepth?>game/forms.php?gameid=<?php echo $_GET['gameid']; ?>">
							<button class="btn btn-combosuki text-white">Submit</button>
							<input type="hidden" id="type" name="type" value="1">
						</form>

						<form method="get" action="<?php echo $URLDepth?>game/forms.php">
							<button class="btn btn-combosuki text-white">Search</button>
							<input type="hidden" name="gameid" value="<?php echo $_GET['gameid'] ?>">
						</form>

						<form method="get" action="<?php echo $URLDepth;?>game/edit/game.php">
							<button class="btn btn-combosuki text-white">Edit Game</button>
							<input type="hidden" name="gameid" value="<?php echo $_GET['gameid'] ?>">
						</form>
					<?php endif; ?>
				</div>
			</div>
		</nav>
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

function verify_password(){
	global $URLDepth, $conn;
	$query = "SELECT globalPass, modPass, complete FROM game WHERE idgame = ?";
	$result = $conn -> prepare($query);
	$result -> bind_param("i", $_GET['gameid']);
	$result -> execute();
	foreach($result -> get_result() as $pass){
		if($pass['globalPass'] != $_POST['gamePass'] && (!password_verify($_POST['gamePass'], $pass['modPass']) || $pass['complete'] == 2 || $pass['complete'] == -1)){
			redictIndex();
		}
	}	
}

function redictIndex(){
	global $URLDepth;
	header("Location: ".$URLDepth."index.php");
	exit();
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

function print_listglyph($type){
	if($type == 2)echo ' <img src="img/misc/verified.png" height="13" name="image-579" data-toggle="tooltip" title="Verified List">';
	if($type == 3)echo ' <img src="img/misc/mod.png" height="19" name="image-579" data-toggle="tooltip" title="Moderated List">';
}

function print_gameglyph($gameid, $conn){
	$query = "SELECT `image`,`name` FROM `game` WHERE `idgame` = ?";
	$result = $conn -> prepare($query);
	$result -> bind_param("i", $gameid);
	$result -> execute();
	foreach($result -> get_result() as $lol){
		echo ' <img src="'.$lol['image'].'" height="19" name="image-579" data-toggle="tooltip" title="'.$lol['name'].'">';
	}
}

function meta_embedvideo($video){
	if($video != '')echo '<meta property="og:type" content="video">';
	if(strpos($video, 'youtu') !== false){
		echo '<meta property="og:url" content="'.$video.'" />';
		echo '
		<meta property="og:url" content="'.$video.'" />
		<meta property="og:video"              content="'.$video.'" />
		<meta property="og:video:secure_url"   content="'.$video.'" />
		<meta property="og:video:type"         content="video/mp4" />
		<meta property="og:video:width"        content="720" />
		<meta property="og:video:height"       content="480" />';
	}else if(strpos($video, 'twitter') !== false && strpos($video, 'https') !== false){
		echo '<meta property="og:url" content="'.$video.'" />';
		echo '  <meta name="twitter:card" content="player" />
    <meta name="twitter:title" content="'.$video.'" />
	<meta name="twitter:player:width" content="720" />
    <meta name="twitter:player:height" content="480" />
    <meta name="twitter:player:stream" content="'.$video.'" />
    <meta name="twitter:player:stream:content_type" content="video/mp4"/> ';
	}else{
		echo '<meta property="og:image" content="http://combosuki.com/img/combosuki.png" />';
	}
}

function strip_POSTtags(){
	foreach($_POST as $key => $each){
		if(is_scalar($each)){
		   //treat any scalar value as string and do stuff:
		 //  echo $each.'<-->'.$key.'next>';
		   $_POST[$key] = htmlentities(strip_tags($each),ENT_QUOTES);
		}else{
		   foreach($each as $key_array => $each_array){
			   $_POST[$key][$key_array] = htmlentities(strip_tags($each_array),ENT_QUOTES);
		   }
		}
	}
	
}

function strip_GETtags(){
	foreach($_GET as $key => $each){
		if(is_scalar($each)){
		   //treat any scalar value as string and do stuff:
		 //  echo $each.'<-->'.$key.'next>';
		   $_GET[$key] = htmlentities(strip_tags($each),ENT_QUOTES);
		}else{
		   foreach($each as $key_array => $each_array){
			   $_GET[$key][$key_array] = htmlentities(strip_tags($each_array),ENT_QUOTES);
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

function edit_listForm($conn){
	echo '
	<div class="row combosuki-main-reversed gap-1">
		<h3 class="mt-3" id="edit">Edit List</h3>
		<small>Use , to Add or Remove multiple entries from the list. (Eg.: 777,26 would add or remove entries 777 and 26 from the list.)</small>

		<form class="form-inline gap-3" method="post" action="list.php?listid='.$_GET['listid'].'">
			<input type="hidden" name="submission_type" value="2">';

			echo '
			<div class="row">
				<div class="col">
					<input placeholder="Entry ID" style="background-color: #474747; color:#999999; min-width:150px;" name="comboid" class="form-control" rows="1"></input>
				</div>

				<div class="col">
					<input placeholder="List Password" name="listPass" type="password" maxlength="16" style="background-color: #474747; color:#999999; min-width:150px;" class="form-control" rows="1"></input>
				</div>
				<div class="col">
					<input placeholder="Category" name="comment" maxlength="45" style="background-color: #474747; color:#999999; min-width:150px;" class="form-control" rows="1"></input>
				</div>
			</div>';

			$query = "SELECT `idlist_category`, `title` FROM `list_category` WHERE `list_idlist` = ? ORDER BY `list_category`.`order`,`list_category`.`title`;";
			$result = $conn -> prepare($query);
			$result -> bind_param("i", $_GET['listid']);
			$result -> execute();
			echo '
			<div class="form-group mb-2">
				<select name="categoryid" class="form-select">';
					echo '
					<option value="0">New Category</option>';
					foreach($result -> get_result() as $category){
						echo '<option value="'.$category['idlist_category'].'" ';
						echo '>'.$category['title'].'</option>';
					}
				echo '</select>
			</div>'; 

			echo '
			<div class="row align-center">
				<div class="col">
					<button type="submit" name="action" value="Submit" class="btn btn-primary btn-block">Add Entry</button>
					<button type="submit" name="action" value="Delete" class="btn btn-danger btn-block">Remove Entry</button>
				</div>
			</div>';
			echo '
		</form>
	</div>';
}

function addtoListForm(){
	if(1):?>
		<form method="post" action="list.php">
			<div class="input-group">
				<input type="hidden" name="submission_type" value="2">
				<input type="hidden" name="comboid" value="<?php echo $_GET['idcombo'] ?>">
				<textarea name="idlist" maxlength="50" style="background-color: #474747; color:#ffffff;" class="form-control" rows="1" placeholder="List ID"></textarea>
				<input name="listPass" type="password" maxlength="16" style="background-color: #474747; color:#999999;" class="form-control" rows="1" placeholder="List Password">
				<input placeholder="Category" name="comment" maxlength="45" style="background-color: #474747; color:#999999;" class="form-control" rows="1">
				<div class="input-group-append" id="button-addon4">
				<button type="submit" name="action" value="Submit" class="btn btn-primary">Add to list</button>
				</div>
			</div>
		</form>
	<?php endif;
}

function get_gameComplete($conn){
	$query = "SELECT `complete` FROM `game` WHERE `idgame` = ?";
	$result = $conn -> prepare($query);
	$result -> bind_param("i", $_GET['gameid']);
	$result -> execute();
	foreach($result -> get_result() as $game){
		return $game['complete'];
	}
}

function game_lock($conn){
	$complete = get_gameComplete($conn);
	echo '<button type="submit" name="action" value="';
	if($complete == 1 || $complete == 0){
		echo 'Lock" class="btn btn-success btn-block">Lock</button>';
	}else{
		echo 'Unlock" class="btn btn-warning btn-block">Unlock</button>';	
	}
}

function build_pagebutton($page){ //Use build_GETbutton instead, no need to rebuild the GET everytime.
	$button = '<a href="submit.php?page=';
					
	$button .= $page;
	
	foreach ($_GET as $key => $entry){
		if($entry != '-' && $entry != '' && $key != 'page'){
			if(is_scalar($entry)){
				$button .= '&';
				$button .= $key;
				$button .= '=';
				$button .= $entry;
			}else{
				foreach($entry as $arraykey => $arrayentry){
					if($arrayentry != '-'){
						$button .= '&';
						$button .= $key.'[]';
						$button .= '=';
						$button .= $arrayentry;
					}
				}
			}
		}
	}
	$button .= '" style="padding-right: 5px;">'.$page.' </a>';
	
	return $button;
}

function build_GETbutton(){
	$button = '';
	foreach ($_GET as $key => $entry){
		if($entry != '-' && $entry != '' && $key != 'page'){
			if(is_scalar($entry)){
				$button .= '&';
				$button .= $key;
				$button .= '=';
				$entry = urlencode(str_replace(' ','',$entry));
				$button .= $entry;
			}else{
				foreach($entry as $arraykey => $arrayentry){
					if($arrayentry != '-'){
						$button .= '&';
						$button .= $key.'[]';
						$button .= '=';
						$button .= $arrayentry;
					}
				}
			}
		}
	}
	return $button;
}

function build_buttonFromVariables($pTitle, $pType, $pID, $pValue, $sTitle, $sType, $sID, $sValue){
	$button = '';
	for ($i = 0; $i<sizeof($pTitle);$i++){
		if($pType[$i] == 1){
			$button .= '&';
			$button .= $pTitle[$i];
			$button .= '=';
			$button .= $pID[$i];
		}else if($pType[$i] == 3){
			$button .= '&';
			$button .= $pTitle[$i].'[]';
			$button .= '=';
			$button .= $pID[$i];
		}else if($pType[$i] == 2){
			$button .= '&';
			$button .= $pTitle[$i];
			$button .= '=';
			$button .= $pValue[$i];
		}
	}
	for ($i = 0; $i<sizeof($sTitle);$i++){
		if($sType[$i] == 1){
			$button .= '&';
			$button .= $sTitle[$i];
			$button .= '=';
			$button .= $sID[$i];
		}else if($sType[$i] == 3){
			$button .= '&';
			$button .= $sTitle[$i].'[]';
			$button .= '=';
			$button .= $sID[$i];
		}else if($sType[$i] == 2){
			$button .= '&';
			$button .= $sTitle[$i];
			$button .= '=';
			$button .= $sValue[$i];
		}
	}
	return $button;
}

function background(){
	global $URLDepth;
	echo '
	body{
		padding: 0;
		margin: 0;';
		if(isset($_COOKIE['color'])){
			echo 'background-color:#'.$_COOKIE['color'].';';
		}else{
			echo '
			background-color: #920000;';
		}
		echo'
		color: white;
		background-image: url('.$URLDepth.'img/bg/bolinhas2.png), url('.$URLDepth.'img/bg/risco2.png);
		background-attachment: fixed;
		background-position: center;
		background-repeat: no-repeat;
		background-size: cover;
	}';

}

function copyLinktoclipboard($link){
	if(1):?>
	<button alignt="right" style="float: right;" class="btn btn-secondary" onclick="copytoclip('<?php echo $link; ?>')">
		Copy URL
		<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-share" viewBox="0 0 16 16">
		  <path d="M13.5 1a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zM11 2.5a2.5 2.5 0 1 1 .603 1.628l-6.718 3.12a2.499 2.499 0 0 1 0 1.504l6.718 3.12a2.5 2.5 0 1 1-.488.876l-6.718-3.12a2.5 2.5 0 1 1 0-3.256l6.718-3.12A2.5 2.5 0 0 1 11 2.5zm-8.5 4a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zm11 5.5a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3z"/>
		</svg>
	</button>
	<?php endif;
}

function add_listCategory($conn){
	if($_POST['comment'] != '' && $_POST['categoryid'] == 0){
		$query = "INSERT INTO `list_category`(`idlist_category`, `title`, `list_idlist`, `order`) VALUES (NULL,?,?,0)";
		$result = $conn -> prepare($query);
		$result -> bind_param("si",$_POST['comment'],$_GET['listid']);
		$result -> execute();//echo $query; print_r($_POST);
		return mysqli_insert_id($conn);
	}
	return $_POST['categoryid'];
}

function alter_List($conn){
	
	$ids = explode(",", $_POST['comboid']);
	$category = add_listCategory($conn);
	for($i = 0; $i<sizeof($ids);$i++){
		
		$query = "SELECT `character`.`game_idgame` FROM `combo` INNER JOIN `character` ON `character`.`idcharacter` = `combo`.`character_idcharacter` WHERE `combo`.`idcombo` = ?";
		$result = $conn -> prepare($query);
		$result -> bind_param("i",$ids[$i]);
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
					$query = "INSERT INTO `combo_listing`(`idcombo`, `idlist`, `comment`, `list_category_idlist_category`) VALUES (?,?,NULL,?)";
					$result = $conn -> prepare($query);
					$result -> bind_param("iii", $ids[$i], $_GET['listid'], $category);
					$result -> execute();
				}
				if($_POST['action'] == 'Delete'){
					$query = "DELETE FROM `combo_listing` WHERE `idcombo` = ? AND `idlist` = ?";
					$result = $conn -> prepare($query);
					$result -> bind_param("ii", $ids[$i], $_GET['listid']);
					$result -> execute();
				}
			}else{
				header("Location: list.php?listid=".$_GET['listid']."");
				exit();
			}
		}
	}
}

function verify_ListPassword($conn){
	$query = "	SELECT `password`,`modPass`,`globalPass` FROM `list` 
JOIN `game` ON `game`.`idgame` = `list`.`game_idgame`
WHERE `idlist` = ?";
	$result = $conn -> prepare($query);
	$result -> bind_param("i",$_GET['listid']);
	$result -> execute();
	
	foreach($result -> get_result() as $pass){
		if($pass['globalPass'] != $_POST['listPass'] && !password_verify($_POST['listPass'], $pass['modPass']) && $pass['password'] != $_POST['listPass']){
			redictIndex();
		}
	}
}

function pagination($numberOfPages, $getAtributes, $currentPage){
	$count = 0;
	echo '<nav><ul class="pagination pagination-sm flex-wrap">';
	while($count < $numberOfPages){
		if($count == $currentPage){
			//echo '<li class="page-item active"> <span class="page-link">'.$count++.' </span></li>';
			echo '<li class="page-item active combosuki-main"> <span class="page-link">'.$count++.' </span></li>';
		}else{
			//echo ' <li class="page-item"><a class="page-link" href="submit.php?page='.$count.$getAtributes.'"">'.$count++.' </a></li>'; //style="padding-right: 5px;
			echo ' <li class="combosuki-main-reversed"><a class="page-link" href="submit.php?page='.$count.$getAtributes.'"">'.$count++.' </a></li>';
		}
	}
	echo'  </ul></nav>';
}

function list_categories($listid,$conn){
	$query = "SELECT `list_category`.`title`
FROM `combo_listing` 
INNER JOIN `combo` ON `combo`.`idcombo` = `combo_listing`.`idcombo` 
LEFT JOIN `character` ON `character`.`idcharacter` = `combo`.`character_idcharacter` 
LEFT JOIN `list_category` ON `list_category`.`idlist_category` = `combo_listing`.`list_category_idlist_category`
WHERE `idlist` = ?  GROUP BY `list_category`.`title` ORDER BY `list_category`.`order`, `list_category`.`title`,`combo`.`damage` DESC;";
	$result = $conn -> prepare($query);
	$result -> bind_param("i", $listid);
	$result -> execute();
	echo '
	<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block sidebar show collapse combosuki-main-reversed">
		<ul class="list-unstyled mb-0 py-3 pt-md-1">
			<li class="list-group-item bg-transparent "><a href="#edit"><span>Edit</span></a></li>';
			foreach($result -> get_result() as $data){
				echo '<li class="list-group-item bg-transparent "><a href="#'.$data['title'].'"><span>'.$data['title'].'</span></a></li>';
			}
			echo '
		</ul>
	</nav>';
}

function jumbotron($conn, $imageHeight){
	if(isset($_GET['gameid'])){
		echo '
			<div class="jumbotron jumbotron-fluid">
				<div class="container">
					<a href="index.php"><img style="margin-top: 20px;" ';
						game_image($_GET['gameid'], $imageHeight, $conn);
						echo '</a>
				</div>
			</div>';
	}else{
		echo '
			<div class="jumbotron jumbotron-fluid">
				<div class="container">
					<a href="index.php">
						<img src="img/combosuki.png" style="margin-top: 20px;" height="'.$imageHeight.'" >
					</a>
				</div>
			</div>'; //<img src="img/selo.png" style="max-height:200; margin-left:200px;">
	}
}

function table(){
	echo '
	tr:nth-child(even) {';
		if(isset($_COOKIE['color'])){
			echo ' background-color:#'.$_COOKIE['color'].';';
		}else{
			echo '
			background-color: #920000;';
		}
	echo '
	}

	tr:nth-child(odd) {
		background-color: #';
	//echo $_COOKIE['color'] - 0xC41F12;
	echo '020202';
	echo '}';
}

function set_cookies(){
	if(!isset($_COOKIE['color'])){
		setcookie("display", 0, time() + (10 * 365 * 24 * 60 * 60), "/");
		$_COOKIE['color'] = dechex(0xC62114);
		setcookie("color", dechex(0xC62114), time() + (10 * 365 * 24 * 60 * 60), "/"); // 86400 = 1 day
	}
}

function create_list_form($conn){
	echo '<h3>Create List</h3>
	<form class="form-control combosuki-main-reversed text-white" method="post" action="list.php">
		<input type="hidden" name="submission_type" value="1">
		<div class="form-group mb-2">
			<input placeholder="List Name" style="background-color: #474747; color:#999999;" name="list_name" class="form-control" maxlength="45" rows="1"></input>
		</div>
		<div class="form-group mb-2">';

			$query = "SELECT `idgame`, `name` FROM `game` WHERE `complete` > 0 ORDER BY `name`";
			$result = $conn -> prepare($query);
			$result -> execute();

			echo '<select name="gameid" class="form-select">';

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
	</form>';
}

function search_list_form($conn){
	echo '<h3>Search List</h3>
	<form class="form-control combosuki-main-reversed text-white" method="post" action="list.php">
		<input type="hidden" name="submission_type" value="1">
		<div class="form-group mb-2">
			<input placeholder="List Name" style="background-color: #474747; color:#999999;" name="list_name" class="form-control" maxlength="45" rows="1"></input>
		</div>
		<div class="form-group mb-2">';

			$query = "SELECT `idgame`, `name` FROM `game` WHERE `complete` > 0 ORDER BY `name`";
			$result = $conn -> prepare($query);
			$result -> execute();

			echo '<select name="gameid" class="form-select">';

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
		<div class="form-group mb-2"><button type="submit" name="action" value="Search" class="btn btn-info btn-block">Search</button></div>
	</form>';
}

function game_lists($conn){
	$query = "SELECT idlist, list_name FROM `list` WHERE `type` = 3 AND `game_idgame` = ? ORDER BY `type` DESC,`list_name`;";
	$result = $conn -> prepare($query);
	$result -> bind_param("i", $_GET['gameid']);
	$result -> execute();
	$i = 1;
	foreach($result -> get_result()	as $lol){
		if($i){
			echo '<h1>Lists</h1>';
			echo '<div class="row text-center combosuki-main-reversed">';
			$i--;
		}
			
		echo '<div class="col">';
		echo '<text href="list.php?listid='.$lol['idlist'].'">'.$lol['list_name'].'</text>';
		echo '	<a href="list.php?listid='.$lol['idlist'].'">
			<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-box-arrow-up-right" viewBox="0 0 16 16">
			  <path fill-rule="evenodd" d="M8.636 3.5a.5.5 0 0 0-.5-.5H1.5A1.5 1.5 0 0 0 0 4.5v10A1.5 1.5 0 0 0 1.5 16h10a1.5 1.5 0 0 0 1.5-1.5V7.864a.5.5 0 0 0-1 0V14.5a.5.5 0 0 1-.5.5h-10a.5.5 0 0 1-.5-.5v-10a.5.5 0 0 1 .5-.5h6.636a.5.5 0 0 0 .5-.5z"/>
			  <path fill-rule="evenodd" d="M16 .5a.5.5 0 0 0-.5-.5h-5a.5.5 0 0 0 0 1h3.793L6.146 9.146a.5.5 0 1 0 .708.708L15 1.707V5.5a.5.5 0 0 0 1 0v-5z"/>
			</svg>
		</a>';
		echo '</div>';
	
	}
}

function getCSS(){
	global $URLDepth;
	?>
	<link href="<?php echo $URLDepth; ?>css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="<?php echo $URLDepth; ?>css/combosuki.css">
	<?php
}
?>
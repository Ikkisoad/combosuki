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
	$query = "SELECT idgame, name FROM game WHERE complete = 1 ORDER BY name;";
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
		" src=img/games/';
		echo $gameid['idgame'];
		echo '.png height=100 alt="Responsive image"';
		echo '></a>';
		
		/* TEXT ONLY
		echo '<a style="margin-left:5em;" href=game.php?gameid=';
		echo $gameid['idgame'];
		echo '>';
		echo $gameid['name'].'</a><br>';*/
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



?>
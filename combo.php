<!doctype php>
<?php
	include_once "server/conexao.php";
	include_once "server/functions.php";
	$_GET = array_map("strip_tags", $_GET);
	
	$primaryTitle = array();
	$primaryValue = array();
	$secondaryTitle = array();
	$secondaryValue = array();

	$query = "SELECT `idcombo`,`combo`,`damage`,`value`,`idResources_values`,`number_value`,`character`.`idcharacter`,`character`.`Name`, `video`, `game_resources`.`text_name`,`game_resources`.`type`, `combo`.`type` as listingtype, `combo`.`comments`,`game_resources`.`primaryORsecundary`, `character`.`game_idgame`, `resources_values`.`order`, `combo`.`submited`, `combo`.`patch`, `combo`.`author`
FROM `combo` 
INNER JOIN `resources` ON `combo`.`idcombo` = `resources`.`combo_idcombo` 
LEFT JOIN `resources_values` ON `resources_values`.`idResources_values` = `resources`.`Resources_values_idResources_values` 
LEFT JOIN `character` ON `character`.`idcharacter` = `combo`.`character_idcharacter` 
LEFT JOIN `game_resources` ON `game_resources`.`idgame_resources` = `resources_values`.`game_resources_idgame_resources` 
WHERE `idcombo` = ? ";
	
	$query = $query . "ORDER BY  `game_resources`.`primaryORsecundary` DESC, `idcombo`, `text_name`;";
	//echo $query;
	$result = $conn -> prepare($query);
	$result -> bind_param("i",$_GET['idcombo']);
	$result -> execute();
	
	$id_combo = -1;
	
	$primaryORsecundary = 0;
	
	$secondaryNames = array();
	
	foreach($result -> get_result() as $data){
		if($id_combo != $data['idcombo']){
			$patch = $data['patch'];
			
			$listing_type = $data['listingtype'];
			$character = $data['idcharacter'];
			$name = $data['Name'];
			$id_combo = $data['idcombo'];
			//$combo = $data['combo'];
			$combo = str_replace('->', '<br>', $data['combo']);
			$combo_image = button_printing($data['game_idgame'], $data['combo'], $conn);
	
		}
		
		$comment = $data['comments'];
		$video = $data['video'];
		$damage = $data['damage'];
		$submited = new DateTime($data['submited']);
		$author = $data['author'];
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
?>
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
		
		<!-- <meta property="og:url" content="<?php //echo $video;?>" /> -->
		<meta property="og:title" content="<?php echo $name.' > '.$damage.' damage';?>" />
		<meta property="og:description" content="<?php echo $combo;?>" />
		<meta property="og:image" content="http://combosuki.com/img/combosuki.png" />
		<meta name="theme-color" content="#d94040" />
		

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
									$query = "SELECT `character`.`game_idgame` FROM `character` INNER JOIN `combo` ON `character`.`idcharacter` = `combo`.`character_idcharacter` WHERE `combo`.`idcombo` = ?";
									$result = $conn -> prepare($query);
									$result -> bind_param("i",$_GET['idcombo']);
									$result -> execute();
									
									foreach($result -> get_result() as $data){
										echo 'game.php?gameid=';
										echo $data['game_idgame'].'">';
										game_image($data['game_idgame'], 200, $conn);
										$gameid = $data['game_idgame'];
									}
								?>
								
							</a>
						</p>
				</div>
			</div>
			<div class="container">
				<?php
				$_GET['gameid'] = get_combogame($_GET['idcombo'], $conn);
				header_buttons(2,1,'game.php');
				
				?>			
				
				<?php
				
				echo '<p><table>';
				echo '<tr>';
				echo '<th>'; 
				echo 'Entry ID: '.$id_combo.' / ';
				echo $name;
				print_listingtype($listing_type, $conn);
				if($patch != ''){
					echo '<button class="btn btn-dark" style="float: right;" disabled>';
					echo 'Patch: '.$patch.'</button>';
				}
				if(1): ?>
					
					<button alignt="right" style="float: right;" class="btn btn-secondary" onclick="change_display()">Display Method</button>
					<button alignt="right" style="float: right;" class="btn btn-secondary" onclick="copytoclip('<?php echo get_combolink($id_combo,$conn); ?>')">Copy Combo URL</button>

				<?php endif;
				echo '</th>';
				echo '</tr>';
				
					echo '<tr>';
					echo '<td id="combo_line">';
				
					
				if(!isset($_COOKIE['display'])){
					echo $combo;
					$_COOKIE['display'] = 0;
				}else if($_COOKIE['display']){
					echo $combo_image;
				}else{
					echo $combo;
				}
				echo		'</td></table>';
					
				if(!isset($damage)){ exit();}
				embed_video($video);
					echo '</td></table><p><table>';
							echo '<tr>';
							echo '<th>Damage</th>';
							for($i = 0; $i<sizeof($primaryTitle); $i++){
								echo '<th>';
								echo $primaryTitle[$i];
								echo '</th>';
							}
							echo '</tr><tr>';
							echo '<td>'.number_format($damage,'0','','.').'</td>';
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
			
			<div id="combo_text" style="display: none;">
				<?php 
				
					if($_COOKIE['display']){
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
			
			?>
			<div class="btn-group" role="group">
				
				<form method="post" action="forms.php?gameid=<?php if(isset($gameid)){echo $gameid;} ?>">
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
						mysqli_close($conn);
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
					<input type="hidden" name="author" value="<?php echo $author; ?>">
					<button class="btn btn-primary">Edit</button>
					<p>Submitted: <?php echo $submited->format('d-m-Y');?></p>
				</form>
			</div>
			</div>
		</main>
	</body>
		<script>
			function change_display(){
				var temp = document.getElementById("combo_line").innerHTML;
				document.getElementById("combo_line").innerHTML = document.getElementById("combo_text").innerHTML;
				document.getElementById("combo_text").innerHTML = temp;
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
	    <!-- Bootstrap core JavaScript
		================================================== -->
		<!-- Placed at the end of the document so the pages load faster -->
		<script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>
		<script>window.jQuery || document.write('<script src="../../../../assets/js/vendor/jquery.min.js"><\/script>')</script>
		<script src="../../../../assets/js/vendor/popper.min.js"></script>
		<script src="../../../../dist/js/bootstrap.min.js"></script>
</html>

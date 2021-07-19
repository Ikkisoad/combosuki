<!doctype php>
<?php
	include_once "server/conexao.php";
	include_once "server/functions.php";
	strip_GETtags();
	$primaryTitle = array();
	$primaryValue = array();
	$primaryType = array();
	$primaryID = array();
	$secondaryTitle = array();
	$secondaryValue = array();
	$secondaryType = array();
	$secondaryID = array();
	$query = "SELECT `idcombo`,`combo`,`damage`,`value`,`idResources_values`,`number_value`,`character`.`idcharacter`,`character`.`Name`, `video`, `game_resources`.`text_name`,`game_resources`.`type`, `combo`.`type` as listingtype, `combo`.`comments`,`game_resources`.`primaryORsecundary`, `character`.`game_idgame`, `resources_values`.`order`, `combo`.`submited`, `combo`.`patch`, `combo`.`author`
	FROM `combo` 
	INNER JOIN `resources` ON `combo`.`idcombo` = `resources`.`combo_idcombo` 
	LEFT JOIN `resources_values` ON `resources_values`.`idResources_values` = `resources`.`Resources_values_idResources_values` 
	LEFT JOIN `character` ON `character`.`idcharacter` = `combo`.`character_idcharacter` 
	LEFT JOIN `game_resources` ON `game_resources`.`idgame_resources` = `resources_values`.`game_resources_idgame_resources` 
	WHERE `idcombo` = ? ";
	$query = $query . "ORDER BY  `game_resources`.`primaryORsecundary` DESC, `idcombo`, `text_name`,`resources`.`idResources`;";
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
			$combo = $data['combo'];
			$combo_image = button_printing($data['game_idgame'], $data['combo'], $conn);
		}
		$comment = $data['comments'];
		$video = $data['video'];
		$damage = $data['damage'];
		$submited = new DateTime($data['submited']);
		$author = $data['author'];
		if($data['primaryORsecundary'] == 0){
			array_push($secondaryTitle,$data['text_name']);
			array_push($secondaryType,$data['type']);
			array_push($secondaryID,$data['idResources_values']);
			if($data['type'] == 1 || $data['type'] == 3){
				array_push($secondaryValue, $data['value']);
			}else{
				array_push($secondaryValue, $data['number_value']);
			}
		}else{
			array_push($primaryTitle,$data['text_name']);
			array_push($primaryType,$data['type']);
			array_push($primaryID,$data['idResources_values']);
			if($data['type'] == 1 || $data['type'] == 3){
				array_push($primaryValue, $data['value']);
			}else{
				array_push($primaryValue, $data['number_value']);
			}
		}
	}
	if(!isset($damage)){
		header("Location: index.php");
		exit();
	}
?>
<html>
	<head>
		<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-+0n0xVW2eSR5OomGNYDnhzAbDsOXxcvSN1TPprVMTNDbiYZCxYbOOl7+AMvyTG2x" crossorigin="anonymous">
		<link rel="icon" type="image/png" sizes="32x32" href="img/favicon-32x32.png">
		<link rel="icon" type="image/png" sizes="96x96" href="img/favicon-96x96.png">
		<link rel="icon" type="image/png" sizes="16x16" href="img/favicon-16x16.png">
		<meta name="msapplication-TileImage" content="img/ms-icon-144x144.png">
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=yes">
		<meta property="og:title" content="Combo好き" />
		<meta property="og:type" content="website" />
		<meta property="og:image" content="http://combosuki.com/img/combosuki.png" />
		<meta property="og:url" content="http://combosuki.com/index.php" />
		<meta property="og:description" 
		content="Community-fueled searchable environment that shares and perfects combos." />
		<meta name="theme-color" content="#d94040" />
		<meta name="description" content="Community-fueled searchable environment that shares and perfects combos.">
		<title>Combo好き</title>
		<link href="css/bootstrap.min.css" rel="stylesheet">
		<link rel="stylesheet" href="css/combosuki.css">
		<style>
			<?php
				background();
				table();
			?>
		</style>
	</head>
	<body>
		<?php 
			$_GET['gameid'] = get_combogame($_GET['idcombo'], $conn);
			jumbotron($conn,200);
			header_buttons(2,1,'game.php',get_gamename($_GET['gameid'], $conn));
		?>
		<div class="container-fluid px-5 my-3">
			<div class="row">
				

				<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4"><div class="chartjs-size-monitor"><div class="chartjs-size-monitor-expand"><div class=""></div></div><div class="chartjs-size-monitor-shrink"><div class=""></div></div></div>
					<?php
					echo '
					<table class="table table-hover align-middle combosuki-main-reversed text-white">';
						echo '<tr>';
							echo '<th>'; 
								echo 'Entry ID: '.$id_combo.' / ';
								echo $name;
								print_listingtype($listing_type, $conn);
								if($patch != ''){
									echo '<button class="btn btn-dark" style="float: right;" disabled>';
									echo 'Patch: '.$patch.'</button>';
								}
								?>
								<button alignt="right" style="float: right;" class="btn btn-secondary" onclick="change_display()">Display Method</button>

								<?php
								copyLinktoclipboard(get_combolink($id_combo,$conn));
							echo '</th>';
						echo '</tr>';
						echo '<tr>';
							echo '<td id="combo_line">';
								if(!isset($_COOKIE['display'])){
									echo str_replace('\n', '<br>', $combo);
									$_COOKIE['display'] = 0;
								}else if($_COOKIE['display']){
									echo $combo_image;
								}else{
									echo str_replace('\n', '<br>', $combo);
								}
								echo '
							</td>
						</table>';
						if(!isset($damage)){ exit();}
						embed_video($video);
					
					?>
					<div id="combo_text" style="display: none;">
					<?php 
						if($_COOKIE['display']){
							echo str_replace('\n', '<br>', $combo);;
						}else{
							echo $combo_image;
						}
					?>
					</div>
					<?php
						if($comment != ''){
							echo '<table class="table table-hover align-middle combosuki-main-reversed text-white">';
								echo '<tr>
								<td>';
								echo 'Comment:';
								echo '</td>
								</tr>';
								echo '<tr>
								<td>';
								echo nl2br($comment);
								echo '</td>
								</tr>';
							echo '</table>';
						}
						//addtoListForm();
					?>
					
				</main>
				<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block sidebar show collapse">
					<?php 
						echo '
						<table class="table table-hover align-middle combosuki-main-reversed text-white">';
							echo '<tr>';
							echo '<th>Damage</th>';
							echo '<td>'.number_format($damage,'0','','.').'</td>';
								for($i = 0; $i<sizeof($primaryTitle); $i++){
									echo '<tr><th>';
									echo $primaryTitle[$i];
									echo '</th><td>'.$primaryValue[$i];
							echo '</td></tr>';
							}
						echo '</tr>';
					echo '</table>';
						if(sizeof($secondaryTitle)){
							echo '
							<table class="table table-hover align-middle combosuki-main-reversed text-white">';
									for($i = 0; $i<sizeof($secondaryTitle); $i++){
										echo '<tr><th>';
										echo $secondaryTitle[$i].'</th>';
										echo '<td>';
											echo $secondaryValue[$i];
										echo '</td>';
									}
								echo '</tr>';
							echo '</table>';
						}
					?>
					<table class="table table-hover align-middle combosuki-main-reversed text-white">
						<th>
							Submitted:
						</th>
						<td>
							<?php echo $submited->format('d-m-Y');?>
						</td>
					</table>
					<form method="post" action="<?php echo 'submit.php?gameid='.$_GET['gameid'].'&characterid='.$character.'&listingtype='.$listing_type.build_buttonFromVariables($primaryTitle,$primaryType,$primaryID,$primaryValue,$secondaryTitle,$secondaryType,$secondaryID,$secondaryValue);?>">
						<button class="btn btn-dark">Search same resources</button>
					</form>
					<form method="post" action="forms.php?gameid=<?php echo $_GET['gameid']; ?>">
						<?php
							for($i = 0; $i<sizeof($primaryTitle); $i++){
								echo '<input type="hidden" name="';
								echo $primaryTitle[$i];
								if($primaryType[$i] == 3){
									echo '[]" value="';
								}else{
									echo '" value="';
								}
								echo $primaryValue[$i].'">';
							}
							for($i = 0; $i<sizeof($secondaryTitle); $i++){
								echo '<input type="hidden" name="';
								echo $secondaryTitle[$i];
								if($secondaryType[$i] == 3){
									echo '[]" value="';
								}else{
									echo '" value="';
								}
								echo $secondaryValue[$i].'">';
							}
							mysqli_close($conn);
						?>
						<input type="hidden" name="listingtype" value="<?php echo $listing_type; ?>">
						<input type="hidden" name="type" value="2">
						<input type="hidden" name="patch" value="<?php echo $patch; ?>">
						<input type="hidden" name="characterid" value="<?php echo $character; ?>">
						<input type="hidden" name="damage" value="<?php echo $damage; ?>">
						<input type="hidden" name="idcombo" value="<?php echo $id_combo; ?>">
						<input type="hidden" name="combo" value="<?php echo $combo; ?>">
						<input type="hidden" name="comment" value="<?php echo $comment; ?>">
						<input type="hidden" name="video" value="<?php echo $video; ?>">
						<input type="hidden" name="author" value="<?php echo $author; ?>">
						<button class="btn btn-primary">
						Edit
						<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-pencil-square mx-auto" viewBox="0 0 16 16">
						  <path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"></path>
						  <path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5v11z"></path>
						</svg>
						</button>
					</form>
				</nav>
		</div>
		<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js" integrity="sha384-gtEjrD/SeCtmISkJkNUaaKMoLD0//ElJ19smozuHV6z3Iehds+3Ulb9Bn9Plx0x4" crossorigin="anonymous"></script>
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
</html>
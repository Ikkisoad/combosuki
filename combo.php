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
		<style>
			<?php
				background();
			?>
			.jumbotron{
				max-height: 190px;
				background-color: #000000;
			}
			.container{
				height: 100vh;
			}{
				max-height: 190px;
				background-color: #000000;
			}
			table {
				border-spacing: 0;
				width: 100%;
				border: 1px solid #ddd;
			}
			th {
				cursor: pointer;
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
		<main role="main">
			<?php 
				$_GET['gameid'] = get_combogame($_GET['idcombo'], $conn);
				jumbotron($conn,200);
				header_buttons(2,1,'game.php',get_gamename($_GET['gameid'], $conn));
			?>
			<div class="container">
				<?php
				echo '
				<table>';
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
								echo str_replace('->', '<br>', $combo);;
								$_COOKIE['display'] = 0;
							}else if($_COOKIE['display']){
								echo $combo_image;
							}else{
								echo str_replace('->', '<br>', $combo);;
							}
							echo '
						</td>
					</table>';
					if(!isset($damage)){ exit();}
					embed_video($video);
					echo '
					<table>';
							echo '<tr>';
								echo '<th>Damage</th>';
								for($i = 0; $i<sizeof($primaryTitle); $i++){
									echo '<th>';
										echo $primaryTitle[$i];
									echo '</th>';
								}
						echo '</tr>
						<tr>';
							echo '<td>'.number_format($damage,'0','','.').'</td>';
								for($i = 0; $i<sizeof($primaryTitle); $i++){
									echo '<td>';
									echo $primaryValue[$i];
							echo '</td>';
							}
						echo '</tr>';
					echo '</table>';
				if(sizeof($secondaryTitle)){
				echo '
				<table>';
					echo '<tr>';
						for($i = 0; $i<sizeof($secondaryTitle); $i++){
							echo '<th>';
								echo $secondaryTitle[$i];
							echo '</th>';
						}
					echo '</tr>
					<tr>';
						for($i = 0; $i<sizeof($secondaryTitle); $i++){
							echo '<td>';
								echo $secondaryValue[$i];
							echo '</td>';
						}
					echo '</tr>';
				echo '</table>';
				}
				?>
				<div id="combo_text" style="display: none;">
				<?php 
					if($_COOKIE['display']){
						echo str_replace('->', '<br>', $combo);;
					}else{
						echo $combo_image;
					}
				?>
				</div>
				<?php
					if($comment != ''){
						echo '<table>';
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
				<div class="btn-group" role="group">
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
					<button class="btn btn-primary">Edit</button>
				</form>
				<form method="post" action="<?php echo 'submit.php?gameid='.$_GET['gameid'].'&characterid='.$character.'&listingtype='.$listing_type.build_buttonFromVariables($primaryTitle,$primaryType,$primaryID,$primaryValue,$secondaryTitle,$secondaryType,$secondaryID,$secondaryValue);?>">
					<button class="btn btn-dark">Search same resources</button>
				</form>
				<form>
					<button class="btn btn-info" disabled>Submitted: <?php echo $submited->format('d-m-Y');?></button>
				</form>
			</div>
		</main>
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
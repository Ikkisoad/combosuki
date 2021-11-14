<!doctype php>
<?php
	$URLDepth = '../';
	require_once "../server/initialize.php";
	$primaryTitle = array();
	$primaryValue = array();
	$primaryType = array();
	$primaryID = array();
	$secondaryTitle = array();
	$secondaryValue = array();
	$secondaryType = array();
	$secondaryID = array();
	$id_combo = -1;
	$primaryORsecundary = 0;
	$secondaryNames = array();
	foreach(getComboDetailedBy_ID($_GET['idcombo']) as $data){
		if($id_combo != $data['idcombo']){
			$patch = $data['patch'];
			$listing_type = $data['listingtype'];
			$character = $data['idcharacter'];
			$name = $data['Name'];
			$id_combo = $data['idcombo'];
			$combo = $data['combo'];
			$combo_image = button_printing($data['game_idgame'], $data['combo']);
		}
		$comment = $data['comments'];
		$video = $data['video'];
		$damage = $data['damage'];
		$submited = new DateTime($data['submited']);
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
		redictIndex();
	}
?>
<html>
	<head>
		
		<?php headerHTML(); ?>
		<?php
		openGraphProtocol($name.' > '.$damage.' damage',$combo);
		meta_embedvideo($video); ?>
		<title>Combo好き</title>
		<?php
			getCSS();
		?>
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
			jumbotron(200);
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
								copyLinktoclipboard(get_combolink($id_combo));
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
								if($primaryType[$i] == 2){
									echo $primaryValue[$i].'">';
								}else{
									echo $primaryID[$i].'">';
								}
							}
							for($i = 0; $i<sizeof($secondaryTitle); $i++){
								echo '<input type="hidden" name="';
								echo $secondaryTitle[$i];
								if($secondaryType[$i] == 3){
									echo '[]" value="';
								}else{
									echo '" value="';
								}
								if($secondaryType[$i] == 2){
									echo $secondaryValue[$i].'">';
								}else{
									echo $secondaryID[$i].'">';
								}
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
	<?php
		copytoclipJS();
		changeDisplayJS();
	?>
</html>
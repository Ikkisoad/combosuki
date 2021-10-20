<!doctype php>
<?php
	include_once "server/functions.php";
	include_once "server/conexao.php";
	
	strip_POSTtags();
	strip_GETtags();
?>
<html>
	<head>
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
		<meta name="theme-color" content="#C62114" />

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
				jumbotron($conn,200);
				if(isset($_POST['type'])){
					if($_POST['type'] == 1){ //Submit
						header_buttons(2, 1, 'game.php',get_gamename($_GET['gameid'], $conn));
					}else if($_POST['type'] == 2){ //Edit
						header_buttons(2, 2, 'combo.php', 69);
					}
				}else{
					header_buttons(2, 1, 'game.php',get_gamename($_GET['gameid'], $conn));
				}
			?>
			<div class="container-fluid my-3">
					<div class="form-group">
						<form method="<?php
							//print_r($_POST);
							if(isset($_POST['type'])){
								echo 'post';
							}else{
								echo 'get';
							}
						
						
						?>" action="submit.php">
						<?php
							
							if(!isset($_POST['type'])){
								//echo '<button type="submit" class="btn btn-info btn-block">Search</button>';
								echo '<h2>Search</h2>';
							}
							if(isset($_POST['type'])){
								if($_POST['type'] == 2){ //Edit
									entry_select($_POST['listingtype'], 0, $conn);
								}else{
									entry_select(0,0, $conn);
								}
							}else{
								entry_select(0,1, $conn);
							}
							?>
							
							<input type="hidden" name="gameid" value="<?php echo $_GET['gameid'] ?>">
							<?php
								//Could make a function out of this <>><><>><><><><><><>><><><><>>>><><><><>><><>><><><><>><><><><><><>
								$query = "SELECT `Name`,`idcharacter` FROM `character` WHERE game_idgame = ? ORDER BY name;";
								$result = $conn -> prepare($query);
								$result -> bind_param("i", $_GET['gameid']);
								$result -> execute();
								
								?>
								<div class="input-group mb-3">
									<div class="input-group-prepend">
										<label class="input-group-text">Character:</label>
									</div>
								<?php
								echo '<select name="characterid" class="form-select">';
								if(!isset($_POST['type'])){echo '<option value="-">-</option>';}
								foreach($result -> get_result() as $character){
									echo '<option value="'.$character['idcharacter'].'" ';
									if(isset($_POST['type'])){
										if($_POST['type'] == 2){ //Edit
											if($character['idcharacter'] == $_POST['characterid']){
												echo 'selected';
											}
										}
									}
									echo '>'.$character['Name'].'</option>';
								}
								echo '</select>';
								echo '</div>';
								//Could make a function out of this <>><><>><><><><><><>><><><><>>>><><><><>><><>><><><><>><><><><><><>
								
								$query = "SELECT name,png,game_idgame FROM `button` WHERE game_idgame = ? ORDER BY game_idgame, `order`, idbutton";
								$result = $conn -> prepare($query);
								$result -> bind_param("i", $_GET['gameid']);
								$result -> execute();
								
								if(!isset($_POST['type'])){
									echo '
									<div class="input-group mb-3">
										<div class="input-group-prepend">
											<label class="input-group-text">Order By:</label>
										</div>';
										echo '<select name="Submitted" class="form-select">';
											echo '<option value="-">-</option>';
											echo '<option value="0">Newest</option>';
											echo '<option value="1">Oldest</option>';
										echo '</select>';
									echo '
									</div>';
									echo '
									<div class="input-group mb-3">
										<div class="input-group-prepend">
											<label class="input-group-text">The Combo:</label>
										</div>';
										echo '<select name="combolike" class="form-select">';
											echo '<option value="0">Starts with</option>';
											echo '<option value="2">Has </option>';
											echo '<option value="1">Ends with</option>';
											echo '<option value="3">Does not have</option>';
										echo '</select>';
										echo '
									</div>';
								}
								
								foreach($result -> get_result()	as $button){
									echo '<button type="button" style="border:none;background:none;margin-left:1em;margin-bottom:1em" value="'; //style="border:none;background:none;"border-color: #000000;background-color: #002f7c;
									echo $button['name'];
									if($button['game_idgame']){
										echo '"onclick="moveNumbers(this.value)"><img src="img/buttons/';
										echo $button['png'];
									}else{
										echo '"onclick="moveNumbers(this.value)"><img src="img/buttons/';
										echo $button['png'];
									}
									echo '.png"></button> ';
								}
							?>
							<button type="button" style="border:#ffffff;background:none;" value="backspace"  name="no" onclick="backspace()">
								<img src="img/buttons/hidden/backspace.png">
							</button>
							<textarea style="background-color: #474747; color:#999999;" name="combo" class="form-control" id="comboarea" rows="4" title="<?php
							print_game_notation($_GET['gameid'], $conn);
							?>" placeholder="<?php
							print_game_notation($_GET['gameid'], $conn);
							?>"><?php 
								if(isset($_POST['type'])){
									if($_POST['type'] == 2){
										echo $_POST['combo'];
									}
								}
							
							?></textarea>
							<?php
							if(isset($_POST['type'])){
								echo '<a href="https://goo.gl/forms/6Q8dVlNbdOyTMA4h2" target="_blank"> Is something missing?</a>';
							}
							?>
							<div class="row my-3">
							<div class="col">
							<div class="input-group mb-3">
								<div class="input-group-prepend">
									<span class="input-group-text">Damage:
								</div>
								<input class="form-control" type="number" name="damage" min="0" <?php 
								if(isset($_POST['type'])){
									if($_POST['type'] == 2){
										echo ' value="'.$_POST['damage'].'"';
									}
								}
							
							?>>
							</div>
							</div>
								<div class="col">
								<div class="input-group mb-3">
								<div class="input-group-prepend">
									<span class="input-group-text">Patch:
								</div>
								<input type="text" name="patch" class="form-control"<?php 
								if(isset($_POST['type'])){
									if($_POST['type'] == 2 && isset($_POST['patch'])){
										echo ' value="'.$_POST['patch'].'"';
									}else{
										echo ' value="'.game_patch($_GET['gameid'], $conn).'"';
									}
								}
							
							?>>
							</div>
							</div>
							</div>
							<div class="row align-items-center">
							<?php
								require "server/conexao.php";
								$query = "SELECT text_name,type,idgame_resources,primaryORsecundary FROM `game_resources` WHERE game_idgame = ? ORDER BY game_resources.primaryORsecundary DESC,text_name;";
								//echo $query;
								$result = $conn -> prepare($query);
								$result -> bind_param("i", $_GET['gameid']);
								$result -> execute();
								$lap = 0;
								foreach($result -> get_result()	as $resource){
									if($resource['primaryORsecundary'] == 0 && $lap == 0){
										echo '
										</div>
										<div class="row">
										<h3>Secondary Resources:</h3>';
										$lap++;
									}
									if($resource['type'] == 1){ //List resource
										echo '
										<div class="col">
										<div class="input-group mb-3 flex-nowrap">
									  <div class="input-group-prepend">
										<label class="input-group-text">'; 

										echo $resource['text_name'];echo'</label></div>  <select name="';
										echo $resource['text_name'];
										
										echo '"class="form-select input-small">';
										$query = "SELECT idResources_values,value FROM `resources_values` WHERE `game_resources_idgame_resources` = ".$resource['idgame_resources']." ORDER BY resources_values.order, value;";
										
										$result = $conn -> prepare($query);
										$result -> execute();
										if(!isset($_POST['type']) || $lap){echo '<option value="-">-</option>';}
										foreach($result -> get_result() as $resource_value){
											echo '<option value="';
											echo $resource_value['idResources_values'].'" ';
											if(isset($_POST['type'])){
												if($_POST['type'] == 2){
													$name = str_replace(' ', '_', $resource['text_name']);
													if(isset($_POST[$name])){
														if($_POST[$name] == $resource_value['idResources_values']){
															echo 'selected';
															
														}
													}
												}
											}
											echo '>';
											echo $resource_value['value'];
											echo '</option>';
										}
										echo '</select></div></div>';
									}else if($resource['type'] == 2){//Number resource
										echo '
										<div class="col">';
											$query = "SELECT idResources_values,value FROM `resources_values` WHERE `game_resources_idgame_resources` = ?;";
											$result = $conn -> prepare($query);
											$result -> bind_param("i", $resource['idgame_resources']);
											$result -> execute();
											foreach($result -> get_result() as $resource_value){
												if($resource['text_name'] != 'Damage'){
													if(!isset($_POST['type'])){
														echo '
														<div class="input-group mb-3 flex-nowrap">
														<div class="input-group-prepend">
														<label class="input-group-text">';
														echo $resource['text_name'] . '</label></div>';
														echo '<select name="';
														echo $resource['text_name'].'compare';
														echo '"class="form-select input-small"><option value=0>less than</options><option value=1';
														echo '>greater than</options><option value=2';
														echo '>equal to</options></select></div>';
													}
													echo '<div class="input-group mb-3 flex-nowrap">
													<div class="input-group-prepend">
													<span class="input-group-text">';
													echo $resource['text_name'];
													echo ' </div>
													<input class="form-control" type="number" class="input-sm" name="';
													echo $resource['text_name'];
													echo '" max="';
													echo $resource_value['value'];
													echo '" min="-';
													echo $resource_value['value'];
													echo '"step="any"' ;
													if(isset($_POST['type'])){
														if($_POST['type'] == 2){
															$name = str_replace(' ', '_', $resource['text_name']);
															if(isset($_POST[$name])){
															echo ' value="';
															echo $_POST[$name].'"';
															}
														}
													}
													echo '>
													</div>';
												}
											}
										echo '</div>';
									}else if($resource['type'] == 3){ //Duplicated resource
										$duplicated = 0;
										while($duplicated++ != 2){
											echo '
											<div class="col">
												<div class="input-group mb-3 flex-nowrap">
													<div class="input-group-prepend">
														<label class="input-group-text">'; 
														echo $resource['text_name'].' '.$duplicated;echo'</label>
													</div>
													<select name="';
														echo $resource['text_name'];
														echo '[]"class="form-select input-small">';
														$query = "SELECT idResources_values,value FROM `resources_values` WHERE `game_resources_idgame_resources` = ".$resource['idgame_resources']." ORDER BY resources_values.order, value;";
														$result = $conn -> prepare($query);
														$result -> execute();
														if(!isset($_POST['type']) || $lap){echo '<option value="-">-</option>';}
														foreach($result -> get_result() as $resource_value){
															echo '<option value="';
															echo $resource_value['idResources_values'].'" ';
															if(isset($_POST['type'])){
																if($_POST['type'] == 2){
																	$name = str_replace(' ', '_', $resource['text_name']);
																	if(isset($_POST[$name])){
																		if($_POST[$name][$duplicated-1] == $resource_value['idResources_values']){
																		echo 'selected';
																		}
																	}
																}
															}
															echo '>';
															echo $resource_value['value'];
															echo '</option>';
														}
														echo '
													</select>
												</div>
											</div>';
										}
									}
								}
							?>
							</div>
							<div class="row">
							<div class="col">
							<label>Comments:</label>
							<textarea name="comments" style="background-color: #474747; color:#999999;" class="form-control" title="trabalho" <?php if(!isset($_POST['type'])){echo 'placeholder="Use # to search for multiple variables in a comment - e.g. #universal #corner"';}else{

							echo 'placeholder="Comments like: Corner only, universal, etc... Are recommended to make it easier to search specific situations."';
							}?>><?php 
								if(isset($_POST['type'])){
									if($_POST['type'] == 2){
										echo $_POST['comment'];
									}
								}
								
							
							?></textarea>
							
							<?php
								if(!isset($_POST['type'])){
									echo '<label>Comment does not have:</label>';
									echo '<textarea name="notcomments" style="background-color: #474747; color:#999999;" class="form-control" placeholder="Use # to add multiple variables - e.g. #universal #corner"></textarea>';
								}
							
							?>
							
							<label for="exampleFormControlTextarea1">Video:</label>
							<textarea name="video" style="background-color: #474747; color:#999999;" class="form-control" id="exampleFormControlTextarea1" rows="1" maxlength="255" title="trabalho" placeholder="Currently supports youtube, niconico, twitter, imgur, Gfycat and MedalTv."><?php 
								if(isset($_POST['type'])){
									if($_POST['type'] == 2){
										echo $_POST['video'];
									}
								}
							
							?></textarea>
							<?php 
								if(isset($_POST['type'])){
									if($_POST['type'] == 2){
										echo '<input type="hidden" id="idcombo" name="idcombo" value="'.$_POST['idcombo'].'">';
									}
								}
							
							?>
							
							<?php if(isset($_POST['type'])): ?>
							<label>Combo Password:</label>
							<input name="comboPass" type="password" required maxlength="16" style="background-color: #474747; color:#999999;" class="form-control" rows="1" maxlength="255" placeholder="<?php
							
							if(isset($_POST['type'])){
									if($_POST['type'] == 2){
										echo 'This combo´s password';
									}else{
										echo 'Refrain from using personal passwords.';
									}
								}
							
							
							?>"></input>
							<?php endif; ?>
							</div>
							</div>
							<div class="row">
							<div class="col my-3">
							<div class="d-grid gap-2">
							<?php 
								if(isset($_POST['type'])){
									echo '<button type="submit" name="action" value="Submit" class="btn btn-primary btn-block">Submit</button>';
									if($_POST['type'] == 2){
										echo '<button type="submit" name="action" value="Delete" class="btn btn-danger btn-block" onclick="return confirm(\'Are you sure you want to delete this entry?\');">Delete</button>';
									}
								}
								if(!isset($_POST['type'])){
									echo '<button type="submit" class="btn btn-info btn-block">Search</button>';
								}
								
								mysqli_close($conn);
							?>
							</div>
							</div>
							</div>
						</form>
					</div>
				</div>
			</div>
		<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js" integrity="sha384-gtEjrD/SeCtmISkJkNUaaKMoLD0//ElJ19smozuHV6z3Iehds+3Ulb9Bn9Plx0x4" crossorigin="anonymous"></script>
	</body>

		<script> 
		function moveNumbers(num) { 
				var txt=document.getElementById("comboarea").value; 
				txt=txt + num + " "; 
				document.getElementById("comboarea").value=txt; 
		}
		function backspace(){
			var txt=document.getElementById("comboarea").value;
			if(txt.length == 0){return;}
			if(txt[txt.length-1] == " ")txt = txt.substring(0, txt.length - 1);
			while(txt[txt.length-1] != " " ){
				if(txt.length == 1){
					txt = "";
					break;
				}
				txt = txt.substring(0, txt.length - 1);
				if(txt.legth == 0){
					break;
				}
			}
			document.getElementById("comboarea").value=txt; 
		}
		</script>
		<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js"></script>
		<!-- <script type="text/javascript">
			function changeMethod(objButton) {
				if(document.getElementById("MyForm").method == "post"){
					$("#MyForm").attr("method", "get");
					objButton.value = "Search";
				}else{
					$("#MyForm").attr("method", "post");
					objButton.value = "Submit";
				}
			}
		</script> -->
</html>
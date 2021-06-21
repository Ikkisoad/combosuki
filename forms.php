<!doctype php>
<?php
	include_once "server/functions.php";
	include_once "server/conexao.php";
	
	strip_POSTtags();
	strip_GETtags();
?>
<html>
	<head>
	<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
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
	<meta name="theme-color" content="#ffffff">

	<title>Combo好き</title>
	<link href="css/bootstrap.min.css" rel="stylesheet">
	<style>
			body{
				background-color: #190000;
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
		</style> <!-- BACKGROUND COLOR-->
	</head>
	
	<body>
		<main role="main">
			<?php
				jumbotron($conn,200);
				if(isset($_POST['type'])){
					if($_POST['type'] == 1){
						header_buttons(2, 1, 'game.php',get_gamename($_GET['gameid'], $conn));
					}else if($_POST['type'] == 2){
						header_buttons(2, 2, 'combo.php', 69);
					}
				}else{
					header_buttons(2, 1, 'game.php',get_gamename($_GET['gameid'], $conn));
				}
			?>
			<div class="container">
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
									echo '<p><button type="submit" class="btn btn-info btn-block">Search</button></p>';
								
						}
						//print_r($_POST);
						if(isset($_POST['type'])){
							if($_POST['type'] == 2){
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
							
							?><label>Character: </label>
							<?php
							echo '<p><select name="characterid" class="custom-select">';
							if(!isset($_POST['type'])){echo '<option value="-">-</option>';}
							foreach($result -> get_result() as $character){
								echo '<option value="'.$character['idcharacter'].'" ';
								if(isset($_POST['type'])){
									if($_POST['type'] == 2){
										if($character['idcharacter'] == $_POST['characterid']){
											echo 'selected';
										}
									}
								}
								echo '>'.$character['Name'].'</option>';
							}
							echo '</select></p>';
							
							//Could make a function out of this <>><><>><><><><><><>><><><><>>>><><><><>><><>><><><><>><><><><><><>
							
							$query = "SELECT name,png,game_idgame FROM `button` WHERE game_idgame = ? ORDER BY game_idgame, `order`, idbutton";
							$result = $conn -> prepare($query);
							$result -> bind_param("i", $_GET['gameid']);
							$result -> execute();
							
							if(!isset($_POST['type'])){
								echo 'Order by:';
								echo '<select name="Submitted" class="custom-select">';
								echo '<option value="-">-</option>';
								echo '<option value="0">Newest</option>';
								echo '<option value="1">Oldest</option>';
								echo '</select><p><br>';
							}
							
							if(!isset($_POST['type'])){
								echo 'The combo:';
								echo '<select name="combolike" class="custom-select">';
								echo '<option value="0">Starts with</option>'; // STARTS WITH HAS ENDS WITH IADA IADA
								echo '<option value="2">Has </option>';
								echo '<option value="1">Ends with</option>';
								echo '</select><p><br>';
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
								echo '.png"> </button> ';
							}
						?>
						<button type="button" style="border:#ffffff;background:none;" value="backspace"  name="no" onclick="backspace()"><img src="img/buttons/hidden/backspace.png"> </button> 
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
									echo '<p><a href="https://goo.gl/forms/6Q8dVlNbdOyTMA4h2" target="_blank"> Is something missing?</a></p>';
								
							}
						?>
						<p>	<div class="input-group mb-3">
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
						</div> </p>
						
						<p>	<div class="input-group mb-3">
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
						</div> </p>
						
						<?php
							require "server/conexao.php";
							$query = "SELECT text_name,type,idgame_resources,primaryORsecundary FROM `game_resources` WHERE game_idgame = ? ORDER BY game_resources.primaryORsecundary DESC,text_name;";
							//echo $query;
							$result = $conn -> prepare($query);
							$result -> bind_param("i", $_GET['gameid']);
							$result -> execute();
							echo '<br><p>';
							$lap = 0;
							foreach($result -> get_result()	as $resource){
								if($resource['primaryORsecundary'] == 0 && $lap == 0){
									echo '<br><br>';
									$lap++;
								}
								if($resource['type'] == 1){ //List resource
										//print_r($_POST);
									echo '<div class="input-group mb-3">
  <div class="input-group-prepend">
    <label class="input-group-text">'; 

									echo $resource['text_name'];echo'</label></div>  <select name="';
									echo $resource['text_name'];
									
									echo '"class="custom-select input-small">';
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
													if($_POST[$name] == $resource_value['value']){
														echo 'selected';
														
													}
												}
											}
										}
										echo '>';
										echo $resource_value['value'];
										echo '</option>';
									}
									echo '</select></div> ';
								}else if($resource['type'] == 2){//Number resource
									
									$query = "SELECT idResources_values,value FROM `resources_values` WHERE `game_resources_idgame_resources` = ?;";
									$result = $conn -> prepare($query);
									$result -> bind_param("i", $resource['idgame_resources']);
									$result -> execute();
									foreach($result -> get_result() as $resource_value){
										if($resource['text_name'] != 'Damage'){
											if(!isset($_POST['type'])){
												echo '<p>	<div class="input-group mb-3">
												<div class="input-group-prepend">
												<label class="input-group-text">';
												echo $resource['text_name'] . '</label></div>';
												echo '<select name="';
												echo $resource['text_name'].'compare';
												echo '"class="custom-select input-small"><option value=0>less than</options><option value=1';
												echo '>greater than</options><option value=2';
												echo '>equal to</options></select></div></p>';
											}
											echo '<p>	<div class="input-group mb-3">
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
											echo '> </div> </p>';
										}
									}
								}else if($resource['type'] == 3){
									$duplicated = 0;
									//print_r($_POST['Assist']);
									while($duplicated++ != 2){
										echo '<div class="input-group mb-3">
	  <div class="input-group-prepend">
		<label class="input-group-text">'; 

										echo $resource['text_name'].' '.$duplicated;echo'</label></div>  <select name="';
										echo $resource['text_name'];
										
										echo '[]"class="custom-select input-small">';
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
														if($_POST[$name][$duplicated-1] == $resource_value['value']){
															echo 'selected';
															
														}
													}
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
							echo '</p><br>';
						?>
						<label>Comments:</label>
						<textarea name="comments" style="background-color: #474747; color:#999999;" class="form-control" title="trabalho" <?php if(!isset($_POST['type'])){echo 'placeholder="Use # to search for multiple variables in a comment - e.g. #universal #corner"';}else{

						echo 'placeholder="Comments like: Corner only, universal, etc... Are recommended to make it easier to search specific situations."';
						}?>><?php 
							if(isset($_POST['type'])){
								if($_POST['type'] == 2){
									echo $_POST['comment'];
								}
							}
							
						
						?></textarea></p>
						
						<?php
							if(!isset($_POST['type'])){
								echo '<label>Comment does not have:</label>';
								echo '<textarea name="notcomments" style="background-color: #474747; color:#999999;" class="form-control" placeholder="Use # to add multiple variables - e.g. #universal #corner"></textarea>';
							}
						
						?>
						
						<label for="exampleFormControlTextarea1">Video:</label>
						<textarea name="video" style="background-color: #474747; color:#999999;" class="form-control" id="exampleFormControlTextarea1" rows="1" maxlength="255" title="trabalho" placeholder="Currently supports youtube, niconico, twitter, imgur and Gfycat."><?php 
							if(isset($_POST['type'])){
								if($_POST['type'] == 2){
									echo $_POST['video'];
								}
							}
						
						?></textarea></p>
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
						
						
						?>"></input></p>
						<?php endif; ?>
						
						<?php 
							if(isset($_POST['type'])){
								echo '<p><button type="submit" name="action" value="Submit" class="btn btn-primary btn-block">Submit</button></p>';
								if($_POST['type'] == 2){
									echo '<p><button type="submit" name="action" value="Delete" class="btn btn-danger btn-block" onclick="return confirm(\'Are you sure you want to delete this entry?\');">Delete</button></p>';
								}
							}
							
							mysqli_close($conn);
						?>
					</form>
				</div>
			</div>
		</main>
		<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script>
		<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>
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
		<script>/*
			$(document).ready(function(){
				$('#Mybtn').click(function(){
					$('#MyForm').toggle(500);
			  });
			});*/
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

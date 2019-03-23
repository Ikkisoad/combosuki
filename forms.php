<!doctype php>
<html>
	<head>
	<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=yes">
		<meta name="description" content="">
		<meta name="Ikkisoad" content="">
		<link rel="icon" href="img/favicon.ico">

		<title>ComboSuki</title>
    <!-- Bootstrap core CSS -->
		<link href="css/bootstrap.min.css" rel="stylesheet">

    <!-- Custom styles for this template 
		<link href="jumbotron.css" rel="stylesheet"> -->
		<style>
			body{
				background-color: #190000;
				background: url("img/purple-honeycomb.png");
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
			<div class="jumbotron">
				<div class="container">
					<h1 class="display-3"></h1>
						<p>
							<a href="<?php
									if(!empty($_GET)){
										echo 'game.php?gameid=';
										echo $_GET['gameid'];
									}else{
										echo 'index.php';	
									}
								?>"><img 
								<?php
									echo 'src=img/games/';
									echo $_GET['gameid'];
									echo '.png ';
								?>
								align="middle" height="200" >
							</a>
						</p>
				</div>
			</div>
			<div class="container">
				<div class="form-group">
					<!-- <button id="Mybtn" class="btn btn-primary" onclick="changeMethod(this)">Submit a Combo</button> -->
					<div class="btn-group" role="group" aria-label="Basic example">
						<form id="MyForm" method="get" action="<?php
							if(isset($_POST['type'])){
								if($_POST['type'] == 1){
									echo 'game.php';
								}else if($_POST['type'] == 2){
									echo 'combo.php">';
									echo '<input type="hidden" id="idcombo" name="idcombo" value="'.$_POST['idcombo'].'';
								}
							}else{
								echo 'game.php';
							}
						?>
						">		
								<input type="hidden" id="gameid" name="gameid" value="<?php echo $_GET['gameid'] ?>">
								<button class="btn btn-secondary"><< back</button>
						</form>
						<form id="MyForm" method="get" action="index.php">
							<button class="btn btn-secondary">Home</button>
						</form>
					</div>
					<form method="<?php
						//print_r($_POST);
						$_POST = array_map("strip_tags", $_POST);
						$_GET = array_map("strip_tags", $_GET);
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
						?>
						
						<input type="hidden" id="gameid" name="gameid" value="<?php echo $_GET['gameid'] ?>">
						<p><select name="listingtype" class="custom-select">
						<option value="0">combo</option>
						<option value="1">blockstring</option>
						<option value="2">mixup</option>
						<option value="3">archive</option>
						<?php if(!isset($_POST['type'])){echo '<option value="-">-</option>';} ?>
						</select></p>
						<?php
							require "server/conexao.php";
							
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
							
							$query = "SELECT name,png,game_idgame FROM `button` WHERE game_idgame = ? OR game_idgame is NULL ORDER BY game_idgame, `order`, idbutton";
							$result = $conn -> prepare($query);
							$result -> bind_param("i", $_GET['gameid']);
							$result -> execute();
							//echo $query;
							
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
									/*echo $button['game_idgame'];
									echo '/';*/
									echo $button['png'];
								}else{
									echo '"onclick="moveNumbers(this.value)"><img src="img/buttons/';
									echo $button['png'];
								}
								echo '.png"> </button> ';
							}
						?>
						<button type="button" style="border:#ffffff;background:none;" value="backspace"  name="no" onclick="backspace()"><img src="img/buttons/backspace.png"> </button> 

						<textarea style="background-color: #474747; color:#999999;" name="combo" class="form-control" id="comboarea" rows="4" maxlength="255" title="combo" placeholder="Make sure to leave a space between every button. Text can be added if needed."><?php 
							if(isset($_POST['type'])){
								if($_POST['type'] == 2){
									echo $_POST['combo'];
								}
							}
						
						?></textarea>
						<?php
						if(isset($_POST['type'])){
									echo '<p><a href="https://goo.gl/forms/6Q8dVlNbdOyTMA4h2"> Is something missing?</a></p>';
								
							}
						?>
						<p>	<div class="input-group mb-3">
							<div class="input-group-prepend">
								<span class="input-group-text">Damage:
							</div>
							<input type="number" name="damage" min="0" class="input-sm"<?php 
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
							<input type="text" name="patch" class="input-sm"<?php 
							if(isset($_POST['type'])){
								if($_POST['type'] == 2 && isset($_POST['patch'])){
									echo ' value="'.$_POST['patch'].'"';
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
								if($resource['type'] == 1){
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
								}else if($resource['type'] == 2){
									
									$query = "SELECT idResources_values,value FROM `resources_values` WHERE `game_resources_idgame_resources` = ?;";
									$result = $conn -> prepare($query);
									$result -> bind_param("i", $resource['idgame_resources']);
									$result -> execute();
									foreach($result -> get_result() as $resource_value){
										if($resource['text_name'] != 'Damage'){
											echo '<p>	<div class="input-group mb-3">
							<div class="input-group-prepend">
								<span class="input-group-text">';
											echo $resource['text_name'];
											echo ' </div>
							<input type="number" min="0" class="input-sm" name="';
											echo $resource['text_name'];
											echo '" max="';
											echo $resource_value['value'];
											echo '"step=".01"' ;
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
								}
							}
							echo '</p><br>';
						?>
						<label>Comments:</label>
						<textarea name="comments" style="background-color: #474747; color:#999999;" class="form-control" id="exampleFormControlTextarea1" title="trabalho" <?php if(!isset($_POST['type'])){echo 'placeholder="Use # to search for multiple variables in a comment - e.g. #universal #corner"';}else{

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
								echo '<textarea name="notcomments" style="background-color: #474747; color:#999999;" class="form-control" id="exampleFormControlTextarea1" placeholder="Use # to add multiple variables - e.g. #universal #corner"></textarea>';
							}
						
						?>
						
						<label for="exampleFormControlTextarea1">Video:</label>
						<textarea name="video" style="background-color: #474747; color:#999999;" class="form-control" id="exampleFormControlTextarea1" rows="1" maxlength="255" title="trabalho" placeholder="Currently supports youtube, twitter, streamable and twitch clips."><?php 
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
						
						<?php if(isset($_POST['type'])){
							echo '<p><button type="submit" class="btn btn-primary btn-block">Submit</button></p>';
						}?>
					</form>
				</div>
			</div>
		</main>
		<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>
	</body>
	    <!-- Bootstrap core JavaScript
		================================================== -->
		<!-- Placed at the end of the document so the pages load faster -->
		<script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>
		<script>window.jQuery || document.write('<script src="../../../../assets/js/vendor/jquery.min.js"><\/script>')</script>
		<script src="../../../../assets/js/vendor/popper.min.js"></script>
		<script src="../../../../dist/js/bootstrap.min.js"></script>
		<script>
		https://tutorialdeep.com/knowhow/show-form-on-button-click-jquery/
		</script>
		
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

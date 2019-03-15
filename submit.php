<?php

				require "server/conexao.php";
				if(!empty($_POST)){
					if($_POST['combo'] == ''){
						exit();
					}
					//print_r($_POST); echo ' -> first<br>';
					$_POST = array_map("strip_tags", $_POST);
					//print_r($_POST); echo ' -> second<br>';
					$query = "INSERT INTO `combo`(`idcombo`, `combo`, `comments`, `video`, `patch`, `user_iduser`, `character_idcharacter`, `submited`, `damage`, `type`) 
											VALUES (NULL,		?,		?,			?,			?,	NULL,			?,						?, ?, ?)";
					$result = $conn -> prepare($query);
					$date = date("Y-m-d H:i:s");
					$result -> bind_param("ssssisii", $_POST['combo'], $_POST['comments'],$_POST['video'],$dbfz,$_POST['characterid'],$date, $_POST['damage'], $_POST['listingtype']);
					$result -> execute();
					
					$comboid = mysqli_insert_id($conn);
					
					$query = "SELECT text_name,type,idgame_resources FROM `game_resources` WHERE game_idgame = ".$_POST['gameid']." ORDER BY text_name;";
					$result = $conn -> prepare($query);
					$result -> execute();
					//print_r($_POST);
					foreach($result -> get_result()	as $resource){
						if($resource['type'] == 'list'){
							$query = "INSERT INTO `resources`(`idResources`, `combo_idcombo`, `Resources_values_idResources_values`, `number_value`, `character_idcharacter`) 
										VALUES (				NULL,					?		,							?			,		NULL	, NULL)";
							$result = $conn -> prepare($query);
							$name = str_replace(' ', '_', $resource['text_name']);
							$result -> bind_param("ii", $comboid, $_POST[$name]);
							$result -> execute();
						}else if($resource['type'] == 'character'){
							$query = "INSERT INTO `resources`(`idResources`, `combo_idcombo`, `Resources_values_idResources_values`, `number_value`, `character_idcharacter`) 
										VALUES (				NULL,					?		,							NULL,		NULL			, ?)";
							$result = $conn -> prepare($query);
							$name = str_replace(' ', '_', $resource['text_name']);
							$result -> bind_param("ii", $comboid, $_POST[$name] );
							$result -> execute();
						}else if($resource['type'] == 'number'){
							$query = "SELECT idResources_values FROM resources_values WHERE game_resources_idgame_resources = ".$resource['idgame_resources']."";
							$result = $conn -> prepare($query);
							$result -> execute();
							
							foreach($result -> get_result()	as $id){
								
								$query = "INSERT INTO `resources`(`idResources`, `combo_idcombo`, `Resources_values_idResources_values`, `number_value`) 
											VALUES (				NULL,					?		,							?,		?)";
								$result = $conn -> prepare($query);
								$name = str_replace(' ', '_', $resource['text_name']);
								//print_r($_POST);
								$result -> bind_param("iid", $comboid, $id['idResources_values'], $_POST[$name]);
								$result -> execute();
								
							}
						}
					}
					header("Location: combo.php?gameid=".$_POST['gameid']."&idcombo=".$comboid."");
					//echo $comboid;
				}
			

			?>


<!doctype php>
<html>
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=yes">
		<meta name="description" content="">
		<meta name="Ikkisoad" content="">
		<link rel="icon" href="img/favicon.ico">

		<title>FGCC</title>
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
			
			body{
				background-color: #35340a;
				color: white;
				background: url("img/cyan-honeycomb.png");
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
							<a href="index.php"><img 
								<?php
									if(!empty($_GET)){
										echo 'src=img/games/';
										echo $_GET['gameid'];
										echo '.png ';
									}else{
										echo 'src="img/combosuki.png"';	
									}
								?>
								align="middle" height="200" >
							</a>
						</p>
				</div>
			</div>
			<div class="container">
			
			<?php
				if(!empty($_GET)){
					/*SELECT `idcombo`, `combo`, `comments`, `video`, `patch`, `user_iduser`, `character_idcharacter`, `submited`, `damage` FROM `combo` WHERE 1
					SELECT combo,damage,value  FROM `combo` INNER JOIN resources ON combo.idcombo = resources.combo_idcombo INNER JOIN 
					resources_values ON resources_values.idResources_values = resources.Resources_values_idResources_values 
					WHERE `character_idcharacter` = 1 AND combo LIKE 'L%'*/
					/*SELECT idcombo,combo,value,idResources_values,number_value, resources.character_idcharacter FROM `combo` INNER JOIN resources ON combo.idcombo = resources.combo_idcombo LEFT JOIN resources_values ON resources_values.idResources_values = resources.Resources_values_idResources_values WHERE combo LIKE 'L %' AND resources.character_idcharacter = 1 AND resources.Resources_values_idResources_values = 6*/
					/* SELECT idcombo,combo,value,idResources_values,number_value FROM `combo` INNER JOIN resources ON combo.idcombo = resources.combo_idcombo INNER JOIN resources_values ON resources_values.idResources_values = resources.Resources_values_idResources_values WHERE idcombo IN (SELECT resources.combo_idcombo FROM resources WHERE resources.number_value >= 1) AND
idcombo IN (SELECT resources.combo_idcombo FROM resources WHERE resources.Resources_values_idResources_values = 6)*/
/*SELECT idcombo,combo,value,idResources_values,number_value,resources.character_idcharacter, character.Name FROM `combo` INNER JOIN resources ON combo.idcombo = resources.combo_idcombo LEFT JOIN resources_values ON resources_values.idResources_values = resources.Resources_values_idResources_values RIGHT JOIN `character` ON character.idcharacter = resources.character_idcharacter WHERE combo LIKE '%' AND idcombo IN (SELECT resources.combo_idcombo FROM resources WHERE resources.number_value >= 1) AND idcombo IN (SELECT resources.combo_idcombo FROM resources WHERE resources.Resources_values_idResources_values = 6) ORDER BY idcombo*/
					$_GET = array_map("strip_tags", $_GET);
					$i = 0;
					$combo = '';
					$parameters_counter = 0;
					
					$binded_parameters = array();
					if(isset($_GET['listingtype'])){
						switch($_GET['listingtype']){
							case 0:
								echo '<h2>Combos:</h2>';
								break;
							case 1:
								echo '<h2>Blockstrings:</h2>';
								break;
							case 2:
								echo '<h2>Mix Ups:</h2>';
								break;
							case 3:
								echo '<h2>Archives:</h2>';
								break;
						}
					}else{
						echo '<h2>Show All:</h2>';
					}
					echo '<table id="myTable">';
					echo '<tr>';
					$query = "SELECT text_name,type,idgame_resources,primaryORsecundary FROM `game_resources` WHERE game_idgame = ? ORDER BY text_name;";
					$resource_result = $conn -> prepare($query);
					$resource_result -> bind_param("i", $_GET['gameid']);
					$resource_result -> execute();
					$query = "SELECT `Main`.`Name` AS `Main`,`game_resources`.`text_name`,`combo`.`character_idcharacter`,`idcombo`,`combo`,`damage`,`value`,`idResources_values`,`number_value`,`resources`.`character_idcharacter`,`character`.`Name` FROM `combo` INNER JOIN `resources` ON `combo`.`idcombo` = `resources`.`combo_idcombo` LEFT JOIN `resources_values` ON `resources_values`.`idResources_values` = `resources`.`Resources_values_idResources_values` LEFT JOIN `character` 
					ON `character`.`idcharacter` = `resources`.`character_idcharacter` LEFT JOIN `character` AS `Main` ON `Main`.`idcharacter` = `combo`.`character_idcharacter`
					LEFT JOIN `game_resources` ON `game_resources`.`idgame_resources` = `resources_values`.`game_resources_idgame_resources` ";
					$query = $query . "WHERE  `game_resources`.`primaryORsecundary` = 1 AND `Main`.`game_idgame` = ? ";
					$parameter_type = "i";
					$binded_parameters[$parameters_counter++] = $_GET['gameid'];
					
					if(isset($_GET['combo'])){
							if($_GET['combolike'] == 1 || $_GET['combolike'] == 2){
								$combo .= '%';
							}
							$combo .= $_GET['combo'];
							if($_GET['combolike'] == 0 || $_GET['combolike'] == 2){
								$combo .= '%';
							}
							$query .= "AND `combo` LIKE ? ";
							$parameter_type .= "s";
							$binded_parameters[$parameters_counter++] = $combo;
						
					}
					//$binded_parameters[$parameters_counter++] = $combo;
					if(isset($_GET['damage'])){
						if($_GET['damage'] != '-'){
							$query .= "AND `damage` >= ? ";
							$parameter_type .= "d";
							$binded_parameters[$parameters_counter++] = $_GET['damage'];
						}
					}
					if(isset($_GET['comments'])){
							$comment = '';
							$comment .= '%';
							$comment .= $_GET['comments'];
							$comment .= '%';
							$query .= "AND `comments` LIKE ? ";
							$parameter_type .= "s";
							$binded_parameters[$parameters_counter++] = $comment;
						
					}
					
					if(isset($_GET['video'])){
							$video = '';
							$video .= '%';
							$video .= $_GET['video'];
							$video .= '%';
							$query .= "AND `video` LIKE ? ";
							$parameter_type .= "s";
							$binded_parameters[$parameters_counter++] = $video;
						
					}
					
					//$binded_parameters[$parameters_counter++] = $_GET['damage'];
					if(isset($_GET['listingtype'])){
						if($_GET['listingtype'] != '-'){
							$query .= "AND `combo`.`type` = ? ";
							$parameter_type .= "i";
							$binded_parameters[$parameters_counter++] = $_GET['listingtype'];
						}
					}
					//$binded_parameters[$parameters_counter++] = $_GET['listingtype'];
					if(isset($_GET['characterid'])){
						if($_GET['characterid'] != '-'){
							$query .= "AND `combo`.`character_idcharacter` = ? ";
							$parameter_type .= "i";
							$binded_parameters[$parameters_counter++] = $_GET['characterid'];
						}
					}
					echo '<th onclick="sortTable('; echo $i++; echo ')">'; echo 'Character</th>';
					echo '<th onclick="sortTable('; echo $i++; echo ')">'; echo 'Combo</th>';
					echo '<th onclick="sortTable('; echo $i++; echo ',1)">'; echo 'Damage</th>';
					$j = array();
					$k = 0;
					//print_r($_GET);
					foreach($resource_result -> get_result() as $resource){
						if($resource['primaryORsecundary'] == 1){
							$j[$k++] = $resource['type'];
							echo '<th onclick="sortTable('; echo $i++; 
								if($resource['type'] == 'number'){
									echo ',1';
								}
							echo ')">'; echo $resource['text_name']; echo '</th>';
						}
						$name = str_replace(' ', '_', $resource['text_name']);
						if($resource['type'] == 'list'){
							if(isset($_GET[$name])){
								if($_GET[$name] != '-'){
									$query = $query . "AND
	idcombo IN (SELECT resources.combo_idcombo FROM resources WHERE resources.Resources_values_idResources_values = ? ) ";
									$parameter_type .= "i";
									$binded_parameters[$parameters_counter++] = $_GET[$name];
								}
							}
						}else if($resource['type'] == 'character'){
							if($_GET[$name] != '-'){
								$query = $query . "AND
idcombo IN (SELECT resources.combo_idcombo FROM resources WHERE resources.character_idcharacter =  ? ) ";
								$parameter_type .= "i";
								$binded_parameters[$parameters_counter++] = $_GET[$name];
							}
						}else if($resource['type'] == 'number'){
							if(isset($_GET[$name])){
								if($_GET[$name] != '-' && $_GET[$name] != ''){
									$query = $query . "AND idcombo IN (SELECT resources.combo_idcombo FROM resources WHERE resources.number_value >= ? ) ";
									$parameter_type .= "d";
									$binded_parameters[$parameters_counter++] = $_GET[$name];
								}
							}
						}
					}
					$query = $query . "ORDER BY damage DESC, idcombo;";
					//echo $query;
					echo '</tr>';
					//echo $query; echo '<br>';
					$a_params = array();
					$a_params[] = & $parameter_type;
					
					for($i = 0; $i < $parameters_counter; $i++) {
						/* with call_user_func_array, array params must be passed by reference */
						$a_params[] = & $binded_parameters[$i];
					}
					//print_r($a_params);
					$result = $conn -> prepare($query);
					//print_r($a_params);echo 'HELOOOOOOOOOOOOOOOOOOOOOOOOOO<br>';
					if($a_params[0] != ''){
							
							call_user_func_array(array($result, 'bind_param'), $a_params);
					}
					
					//$result -> bind_param($parameter_type, $binded_parameters);
					$result -> execute();
					echo '<br>';
					//print_r($resource_result);
					//print_r($result -> get_result());
					$id_combo = -1;
					echo '<br>';
					//print_r($j);
					foreach($result -> get_result() as $data){
						if($id_combo != $data['idcombo']){
							/*echo '<br>Array: ';
							echo sizeof($j); echo '<br>';*/
							if($id_combo == -1){
									echo '<tr>';
							}else{
								for($k; $k<sizeof($j); $k++){
									echo '<td></td>';
								}
								
									echo '</tr>';
									echo '<tr>';
							}
							$k = 0;
							$id_combo = $data['idcombo'];
							//echo '<tr>';
							echo		'<td>'.$data['Main'].'</td>';
							echo		'<td><a href="combo.php?gameid='.$_GET['gameid'].'&idcombo='.$data['idcombo'].'">'.$data['combo'].'</a></td>';
							echo		'<td>'.$data['damage'].'</td>';
						}
							//echo		'<td><a href="combo.php?idcombo='.$data['idcombo'].'">'.$data['combo'].'</a></td>';
						/*if($j[$k] == 'list'){
							echo		'<td>'.$data['value'].'</td>';
						}else if($j[$k] == 'character'){
							echo		'<td>'.$data['Name'].'</td>';
						}else if($j[$k] == 'number'){
							echo		'<td>'.$data['number_value'].'</td>';
						}*/
						
						while($j[$k] == 'character'){
							if($j[$k] == 'character'){
								if(!empty($data['character_idcharacter'])){
									echo		'<td>'.$data['Name'].'</td>';
								}else{
									echo		'<td>No Assist</td>';
									$k++;
								}
							}
						}
						if($j[$k] == 'list'){
							echo		'<td>'.$data['value'].'</td>';
						}
						
						if($j[$k] == 'number'){
							echo		'<td>'.$data['number_value'].'</td>';
							/*if(!empty($data['number_value'])){
								echo		'<td>'.$data['number_value'].'</td>';
							}else{
								echo		'<td>0</td>';
								
							}*/
						}
						$k++;
					}
					
					
				
				}
				//echo $query;
			?>
			</div>
		</main>
	</body>
	    <!-- Bootstrap core JavaScript
		================================================== -->
		<!-- Placed at the end of the document so the pages load faster -->
		<script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>
		<script>window.jQuery || document.write('<script src="../../../../assets/js/vendor/jquery.min.js"><\/script>')</script>
		<script src="../../../../assets/js/vendor/popper.min.js"></script>
		<script src="../../../../dist/js/bootstrap.min.js"></script>
		<script>
			function sortTable(n,isNumber) {
			  var table, rows, switching, i, x, y, shouldSwitch, dir, switchcount = 0;
			  table = document.getElementById("myTable");
			  switching = true;
			  //Set the sorting direction to ascending:
			  dir = "asc"; 
			  /*Make a loop that will continue until
			  no switching has been done:*/
			  while (switching) {
				//start by saying: no switching is done:
				switching = false;
				rows = table.rows;
				/*Loop through all table rows (except the
				first, which contains table headers):*/
				for (i = 1; i < (rows.length - 1); i++) {
				  //start by saying there should be no switching:
				  shouldSwitch = false;
				  /*Get the two elements you want to compare,
				  one from current row and one from the next:*/
				  x = rows[i].getElementsByTagName("TD")[n];
				  y = rows[i + 1].getElementsByTagName("TD")[n];
				  /*check if the two rows should switch place,
				  based on the direction, asc or desc:*/
				  if (dir == "asc") {
					if (x.innerHTML.toLowerCase() > y.innerHTML.toLowerCase() && !isNumber) {
					  //if so, mark as a switch and break the loop:
					  shouldSwitch= true;
					  break;
					}else if(Number(x.innerHTML.toLowerCase()) > Number(y.innerHTML.toLowerCase())){
						shouldSwitch= true;
						break;
					}
				  } else if (dir == "desc") {
					if (x.innerHTML.toLowerCase() < y.innerHTML.toLowerCase() && !isNumber) {
					  //if so, mark as a switch and break the loop:
					  shouldSwitch = true;
					  break;
					}else if(Number(x.innerHTML.toLowerCase()) < Number(y.innerHTML.toLowerCase())){
						shouldSwitch= true;
						break;
					}
				  }
				}
				if (shouldSwitch) {
				  /*If a switch has been marked, make the switch
				  and mark that a switch has been done:*/
				  rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
				  switching = true;
				  //Each time a switch is done, increase this count by 1:
				  switchcount ++;      
				} else {
				  /*If no switching has been done AND the direction is "asc",
				  set the direction to "desc" and run the while loop again.*/
				  if (switchcount == 0 && dir == "asc") {
					dir = "desc";
					switching = true;
				  }
				}
			  }
			}
		</script>
</html>

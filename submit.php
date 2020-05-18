<?php
	include_once "server/conexao.php";
	include_once "server/functions.php";
	if(!empty($_POST)){
		strip_POSTtags();
		if($_POST['combo'] == ''){
			header("Location: index.php");
			exit();
		}
		if(!isset($_POST['idcombo'])){
			$query = "INSERT INTO `combo`(`idcombo`, `combo`, `comments`, `video`, `user_iduser`, `character_idcharacter`, `submited`, `damage`, `type`, `patch`, `author`, `password`) 
									VALUES (NULL,		?,		?,			?,	NULL,			?,						?, ?, ?, ?, ?, ?)";
			$result = $conn -> prepare($query);
			$date = date("Y-m-d H:i:s");
			$result -> bind_param("sssisdisss", $_POST['combo'], $_POST['comments'],$_POST['video'],$_POST['characterid'],$date, $_POST['damage'], $_POST['listingtype'], $_POST['patch'], $_POST['author'], $_POST['comboPass']);
			$result -> execute();
			$comboid = mysqli_insert_id($conn);
		}else{
			$comboid = $_POST['idcombo'];
			$query = "SELECT `password`, (SELECT game.globalPass FROM game WHERE game.idgame = ?) as gPass, (SELECT game.modPass FROM game WHERE game.idgame = ?) as mPass FROM `combo` WHERE `idcombo` = ?";
			$result = $conn -> prepare($query);
			$result -> bind_param("iii", $_POST['gameid'], $_POST['gameid'], $comboid);
			$result -> execute();
			foreach($result -> get_result() as $pass){
				if($pass['password'] != $_POST['comboPass'] && $pass['gPass'] != $_POST['comboPass'] && !password_verify($_POST['comboPass'], $pass['mPass'])){
					header("Location: combo.php?idcombo=".$comboid."");
					exit();
				}
			}
			$query = "DELETE FROM `resources` WHERE `combo_idcombo` = ?";
			$result = $conn -> prepare($query);
			$result -> bind_param("i", $_POST['idcombo']);
			$result -> execute();
			if($_POST['action'] == 'Submit'){
				$query = "UPDATE `combo` SET 
				`combo`= ?,`comments`= ?,`video`= ?,`character_idcharacter`= ?, `damage`= ?,`type`= ?, `patch` = ?, `author`= ?
				WHERE `idcombo` = ?";
				$result = $conn -> prepare($query);
				$result -> bind_param("sssidissi", $_POST['combo'], $_POST['comments'],$_POST['video'],$_POST['characterid'], $_POST['damage'], $_POST['listingtype'], $_POST['patch'], $_POST['author'], $comboid);
				$result -> execute();
			}else{
				$query = "DELETE FROM `combo` WHERE `idcombo` = ?";
				$result = $conn -> prepare($query);
				$result -> bind_param("i", $comboid);
				$result -> execute();
				header("Location: game.php?gameid=".$_POST['gameid']."");
				exit();
			}
		}
		$query = "SELECT text_name,type,idgame_resources FROM `game_resources` WHERE game_idgame = ".$_POST['gameid']." ORDER BY text_name;";
		$result = $conn -> prepare($query);
		$result -> execute();
		foreach($result -> get_result()	as $resource){
			$name = str_replace(' ', '_', $resource['text_name']);
			if($_POST[$name] != '-'){
				if($resource['type'] == 1){
					$query = "INSERT INTO `resources`(`idResources`, `combo_idcombo`, `Resources_values_idResources_values`, `number_value`) 
								VALUES (				NULL,					?		,							?			,		NULL)";
					$result = $conn -> prepare($query);
					$result -> bind_param("ii", $comboid, $_POST[$name]);
					$result -> execute();
				}else if($resource['type'] == 2){
					$query = "SELECT idResources_values FROM resources_values WHERE game_resources_idgame_resources = ".$resource['idgame_resources']."";
					$result = $conn -> prepare($query);
					$result -> execute();
					foreach($result -> get_result()	as $id){
						$query = "INSERT INTO `resources`(`idResources`, `combo_idcombo`, `Resources_values_idResources_values`, `number_value`) 
									VALUES (				NULL,					?		,							?,		?)";
						$result = $conn -> prepare($query);
						$result -> bind_param("iid", $comboid, $id['idResources_values'], $_POST[$name]);
						$result -> execute();
					}
				}else if($resource['type'] == 3){
					foreach($_POST[$name] as $duplicated_resource){
						$query = "INSERT INTO `resources`(`idResources`, `combo_idcombo`, `Resources_values_idResources_values`, `number_value`) 
									VALUES (				NULL,					?		,							?			,		NULL)";
						$result = $conn -> prepare($query);
						$result -> bind_param("ii", $comboid, $duplicated_resource);
						$result -> execute();
					}
				}
			}
		}
		header("Location: combo.php?idcombo=".$comboid."");
		exit();
	}
?>
<!doctype php>
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
	<meta name="theme-color" content="#ffffff">
	<title>Combo好き</title>
    <!-- Bootstrap core CSS -->
	<link href="css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom styles for this template -->
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
				background: url("img/<?php background(); ?>");
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
								?>"> 
								<?php
									game_image($_GET['gameid'], 200, $conn);
								?>
							</a>
						</p>
				</div>
			</div>
			<div class="container">
			<?php
				if(!empty($_GET)){
					strip_GETtags();
					header_buttons(2, 1, 'game.php', get_gamename($_GET['gameid'], $conn));
					quick_search_form($_GET['gameid'], $conn);
					if(isset($_GET["page"])) { $page  = $_GET["page"]; } else { $page=0; };
					$query = "SELECT sum(case when `game_resources`.`type` = 3 then 2 else 1 end) total FROM `game_resources` WHERE `game_idgame` = ? AND `primaryORsecundary` = 1";
					$result = $conn -> prepare($query);
					$result -> bind_param("i",$_GET['gameid']);
					$result -> execute();
					foreach($result -> get_result() as $each){
						$num_rows = $each['total'];
					}
					$limit = $num_rows * $page * 10; //HAVE TO CHANGE WHEN COMPARING TO $I BELLOW AS WELL
					$offset = $num_rows * 10;
					$i = 0;
					$parameterValue = '';
					$parameters_counter = 0;
					$duplicatedResourceCounter = 0;
					$binded_parameters = array();
					if(isset($_GET['listingtype'])){
						echo '<h2>';
						print_listingtype($_GET['listingtype'], $conn);
						if($_GET['listingtype'] == '-')echo 'Show All';
						echo '</h2>';
					}else{
						echo '<h2>Show All:</h2>';
					}
					copyLinktoclipboard('https://combosuki.com/submit.php?page='.$page.build_GETbutton().'');
					echo '<table id="myTable">';
					echo '<tr>';
					$query = "SELECT text_name,type,idgame_resources,primaryORsecundary FROM `game_resources` WHERE game_idgame = ? ORDER BY text_name;";
					$resource_result = $conn -> prepare($query);
					$resource_result -> bind_param("i", $_GET['gameid']);
					$resource_result -> execute();
					$query = "SELECT `character`.`Name`,`game_resources`.`text_name`,`combo`.`character_idcharacter`,`idcombo`,`combo`,`damage`,`value`,`idResources_values`,`number_value`, `comments`, `video`
FROM `combo` 
INNER JOIN `resources` ON `combo`.`idcombo` = `resources`.`combo_idcombo` 
LEFT JOIN `resources_values` ON `resources_values`.`idResources_values` = `resources`.`Resources_values_idResources_values`
LEFT JOIN `character` ON `character`.`idcharacter` = `combo`.`character_idcharacter` 
LEFT JOIN `game_resources` ON `game_resources`.`idgame_resources` = `resources_values`.`game_resources_idgame_resources` 
WHERE `game_resources`.`primaryORsecundary` = 1 
AND `character`.`game_idgame` = ? ";
					$parameter_type = "i";
					$binded_parameters[$parameters_counter++] = $_GET['gameid'];
					if(isset($_GET['combo'])){
						if($_GET['combo'] != ''){
							if(isset($_GET['combolike'])){
								if($_GET['combolike'] == 1 || $_GET['combolike'] == 2){
									$parameterValue .= '%';
								}
								$parameterValue .= $_GET['combo'];
								if($_GET['combolike'] == 0 || $_GET['combolike'] == 2){
									$parameterValue .= '%';
								}
							}else{
								$parameterValue .= $_GET['combo'].'%';
							}
							$query .= "AND REPLACE(REPLACE(`combo`, ' ', ''),'>','') LIKE REPLACE(REPLACE(?, ' ', ''),'>','') ";
							$parameter_type .= "s";
							$binded_parameters[$parameters_counter++] = $parameterValue;
						}
					}
					if(isset($_GET['damage'])){
						if($_GET['damage'] != ''){
							$query .= "AND `damage` <= ? ";
							$parameter_type .= "d";
							$binded_parameters[$parameters_counter++] = $_GET['damage'];
						}
					}
					if(isset($_GET['patch'])){
						if($_GET['patch'] != ''){
							$query .= "AND `patch` LIKE ? ";
							$parameter_type .= "s";
							$binded_parameters[$parameters_counter++] = $_GET['patch'];
						}
					}
					if(isset($_GET['comments'])){
						if($_GET['comments'] != ''){
							$pieces = explode("#", $_GET['comments']);
							for($i = 0; $i < sizeof($pieces); $i++){
								if($pieces[$i] != ''){
									$parameterValue = '';
									$parameterValue .= $pieces[$i];
									$query .= "AND `comments` LIKE ? ";
									$parameter_type .= "s";
									$binded_parameters[$parameters_counter++] = '%'.$parameterValue.'%';
								}
							}
						}
					}
					if(isset($_GET['notcomments'])){
						if($_GET['notcomments'] != ''){
							$pieces = explode("#", $_GET['notcomments']);
							for($i = 0; $i < sizeof($pieces); $i++){
								if($pieces[$i] != ''){
									$parameterValue = '';
									$parameterValue .= $pieces[$i];
									$query .= "AND `comments` NOT LIKE ? ";
									$parameter_type .= "s";
									$binded_parameters[$parameters_counter++] = '%'.$parameterValue.'%';
								}
							}
						}
					}
					if(isset($_GET['video'])){
						if($_GET['video'] != ''){
							$parameterValue = '';
							$parameterValue .= '%';
							$parameterValue .= $_GET['video'];
							$parameterValue .= '%';
							$query .= "AND `video` LIKE ? ";
							$parameter_type .= "s";
							$binded_parameters[$parameters_counter++] = $parameterValue;
						}
					}
					if(isset($_GET['author'])){
						if($_GET['author'] != ''){
							$parameterValue .= $_GET['author'];
							$query .= "AND `combo`.`author` LIKE ? ";
							$parameter_type .= "s";
							$binded_parameters[$parameters_counter++] = $parameterValue;
						}
					}
					if(isset($_GET['listingtype'])){
						if($_GET['listingtype'] != '-'){
							$query .= "AND `combo`.`type` = ? ";
							$parameter_type .= "i";
							$binded_parameters[$parameters_counter++] = $_GET['listingtype'];
						}
					}
					if(isset($_GET['characterid'])){
						if($_GET['characterid'] != '-'){
							$query .= "AND `combo`.`character_idcharacter` = ? ";
							$parameter_type .= "i";
							$binded_parameters[$parameters_counter++] = $_GET['characterid'];
						}
					}
					$i = 0;
					echo '<th onclick="sortTable('; echo $i++; echo ')">'; echo 'Character</th>';
					echo '<th onclick="sortTable('; echo $i++; echo ')">'; echo 'Combo</th>';
					echo '<th onclick="sortTable('; echo $i++; echo ',1)">'; echo 'Damage</th>';
					$j = array();
					$k = 0;
					foreach($resource_result -> get_result() as $resource){
						if($resource['primaryORsecundary'] == 1){
							$j[$k++] = $resource['type'];
							echo '<th onclick="sortTable('; echo $i++; 
								if($resource['type'] == 2){
									echo ',1';
								}
							echo ')">'; echo $resource['text_name']; echo '</th>';
							if($resource['type'] == 3){
								echo '<th onclick="sortTable('; echo $i++;
								echo ')">'; echo $resource['text_name']; echo '</th>';
							}
						}
						$parameterValue = str_replace(' ', '_', $resource['text_name']);
						if($resource['type'] == 1){
							if(isset($_GET[$parameterValue])){
								if($_GET[$parameterValue] != '-'){
									$query = $query . "AND
	idcombo IN (SELECT resources.combo_idcombo FROM resources WHERE resources.Resources_values_idResources_values = ? ) ";
									$parameter_type .= "i";
									$binded_parameters[$parameters_counter++] = $_GET[$parameterValue];
								}
							}
						}else if($resource['type'] == 2){
							if(isset($_GET[$parameterValue])){
								if($_GET[$parameterValue] != '-' && $_GET[$parameterValue] != ''){
									$query = $query . "AND idcombo IN (SELECT resources.combo_idcombo FROM resources 
JOIN resources_values ON resources.Resources_values_idResources_values = resources_values.idResources_values
JOIN game_resources ON resources_values.game_resources_idgame_resources = game_resources.idgame_resources
WHERE resources.number_value <= ? and game_resources.idgame_resources = ".$resource['idgame_resources'].") ";
									$parameter_type .= "d";
									$binded_parameters[$parameters_counter++] = $_GET[$parameterValue];
								}
							}
						}else if($resource['type'] == 3){
							if(isset($_GET[$parameterValue])){
								foreach($_GET[$parameterValue] as $eachDuplicate){
									if($eachDuplicate != '-' && $duplicatedResourceCounter){
										if($duplicated_firstValue > $eachDuplicate){
											$query = $query . "AND idcombo IN (SELECT `combo_idcombo` FROM `resources` 
	WHERE `Resources_values_idResources_values` IN (?,?) GROUP BY `combo_idcombo`
	HAVING GROUP_CONCAT(DISTINCT `Resources_values_idResources_values` ORDER BY `Resources_values_idResources_values`) = ?) ";
											$parameter_type .= "iis";
											$binded_parameters[$parameters_counter++] = $eachDuplicate;
											$binded_parameters[$parameters_counter++] = $duplicated_firstValue;
											$binded_parameters[$parameters_counter++] = $eachDuplicate.','.$duplicated_firstValue;
										}else if($duplicated_firstValue < $eachDuplicate){
											$query = $query . "AND idcombo IN (SELECT `combo_idcombo` FROM `resources` 
	WHERE `Resources_values_idResources_values` IN (?,?) GROUP BY `combo_idcombo`
	HAVING GROUP_CONCAT(DISTINCT `Resources_values_idResources_values` ORDER BY `Resources_values_idResources_values`) = ?) ";
											$parameter_type .= "iis";
											$binded_parameters[$parameters_counter++] = $duplicated_firstValue;
											$binded_parameters[$parameters_counter++] = $eachDuplicate;
											$binded_parameters[$parameters_counter++] = $duplicated_firstValue.','.$eachDuplicate;
										}else if($duplicated_firstValue == $eachDuplicate){
											$query = $query . "AND idcombo IN (SELECT `resources`.`combo_idcombo` FROM `resources` 
											WHERE `Resources_values_idResources_values` = ? 
											GROUP BY `resources`.`combo_idcombo`
											HAVING COUNT(*)>1) ";
											$parameter_type .= "i";
											$binded_parameters[$parameters_counter++] = $duplicated_firstValue;
										}
										$duplicatedResourceCounter = 0;
									}else if($eachDuplicate != '-'){
										$duplicated_firstValue = $eachDuplicate;
										$duplicatedResourceCounter++;
									}
								}
								if($duplicatedResourceCounter){
									$query = $query . "AND idcombo IN (SELECT `combo_idcombo` FROM `resources` WHERE `Resources_values_idResources_values` = ?)";
									$parameter_type .= "i";
									$binded_parameters[$parameters_counter++] = $duplicated_firstValue;
									$duplicatedResourceCounter = 0;
								}
							}
						}
					}
					if(isset($_GET['Submitted'])){
						if($_GET['Submitted'] == '-'){
							$query = $query . "ORDER BY damage DESC, idcombo, text_name  LIMIT ?, ?;";
						}else if($_GET['Submitted'] == 1){
							$query = $query . "ORDER BY `submited` ASC, damage DESC, idcombo, text_name  LIMIT ?, ?;";
						}else{
							$query = $query . "ORDER BY `submited` DESC, damage DESC, idcombo, text_name  LIMIT ?, ?;";
						}
					}else{
						$query = $query . "ORDER BY damage DESC, idcombo, text_name  LIMIT ?, ?;";
					}
					$parameter_type .= "i";
					$binded_parameters[$parameters_counter++] = $limit;
					$parameter_type .= "i";
					$binded_parameters[$parameters_counter++] = $offset;
					echo '</tr>';
					$parameterValue = array();
					$parameterValue[] = & $parameter_type;
					for($i = 0; $i < $parameters_counter; $i++) {
						$parameterValue[] = & $binded_parameters[$i];
					}
					$result = $conn -> prepare($query);
					if($parameterValue[0] != ''){
						call_user_func_array(array($result, 'bind_param'), $parameterValue);
					}
					$result -> execute();
					echo '<br>';
					$id_combo = -1;
					echo '<br>';
					$i = 0;
					foreach($result -> get_result() as $data){
						$i++;
						if($id_combo != $data['idcombo']){
							if($id_combo == -1){
								echo '<tr>';
							}else{
								echo '</tr>';
								echo '<tr>';
							}
							$k = 0;
							$duplicatedCounter = 0;
							$id_combo = $data['idcombo'];
							echo '<td>';
							if($data['comments'] != '' || $data['video'] != ''){
								echo '<button class="btn btn-dark" onclick="showDIV('.$data['idcombo'].')">'.$data['Name'].'</button>';
							}else{
								echo $data['Name'];
							}
							echo '</td>';
							echo		'<td style="min-width:400px"><a data-toggle="tooltip" data-placement="bottom" title="'.$data['comments'].'" href="combo.php?idcombo='.$data['idcombo'].'">'.$data['combo'].'</a>';
							echo '<div id="'.$data['idcombo'].'" style="display: none;">';
								echo $data['comments'];
								embed_video_notable($data['video']);
								echo '</div>';
							echo '</td>';
							echo		'<td>'.number_format($data['damage'],'0','','.').'</td>';
						}
						if($j[$k] == 1 || $j[$k] == 3){
							echo		'<td>'.$data['value'].'</td>';
						}
						if($j[$k] == 2){
							echo		'<td>'.$data['number_value'].'</td>';
						}
						if($j[$k] != 3 || $duplicatedCounter){
							$k++;
							$duplicatedCounter = 0;
						}else{
							$duplicatedCounter++;
						}
					}
					$query = str_replace('`character`.`Name`,`game_resources`.`text_name`,`combo`.`character_idcharacter`,`idcombo`,`combo`,`damage`,`value`,`idResources_values`,`number_value`, `comments`, `video`','COUNT(*) as totalrows ',$query);
					if($page){
						$query = str_replace('  LIMIT ?, ?','',$query);
						$parameterValue[0]=substr($parameterValue[0],0,-2);
						$i = array_splice($parameterValue, -2);
					}
					if($parameterValue[0] != ''){
						$result = $conn -> prepare($query);
						call_user_func_array(array($result, 'bind_param'), $parameterValue);
					}
					$result -> execute();
					foreach($result -> get_result() as $data){
						$pages = $data['totalrows']/$offset;
					}
				}
					$count = 0;
					$i = build_GETbutton();
					while($count < $pages){
						if($count == $page){
							echo '< '.$count++.' > ';
						}else{
							echo '<a href="submit.php?page='.$count.$i.'" style="padding-right: 5px;">'.$count++.' </a>';
						}
					}
			?></table>
			<?php
				$count = 0;
				while($count < $pages){
					if($count == $page){
						echo '< '.$count++.' > ';
					}else{
						echo '<a href="submit.php?page='.$count.$i.'" style="padding-right: 5px;">'.$count++.' </a>';
					}
				}
				mysqli_close($conn);
			 ?>
		</div></main>
	</body>
	    <!-- Bootstrap core JavaScript
		================================================== -->
		<!-- Placed at the end of the document so the pages load faster -->
		<script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>
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
		<script>
		function showDIV(DIV_ID) {
		  var x = document.getElementById(DIV_ID);
		  if (x.style.display === "none") {
			x.style.display = "block";
		  } else {
			x.style.display = "none";
		  }
		}</script>
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
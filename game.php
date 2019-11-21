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

    <!-- Custom styles for this template 
		<link href="jumbotron.css" rel="stylesheet">  -->
		<style>
			body{
				background-color: #35340a;
				background: url("img/<?php
				if(isset($_COOKIE['color'])){
					echo 'bg/'.$_COOKIE["color"].'honeycomb.png';
				}else{
					echo 'dark-honeycomb.png';
				}?>
					");
				color: white;
			}
			.jumbotron{
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
		<?php
			$_POST = array_map("strip_tags", $_POST);
			$_GET = array_map("strip_tags", $_GET);
			if(isset($_POST['action'])){
				require "server/conexao.php";

				if($_POST['action'] == 0){

					$query = "DELETE FROM `resources` WHERE `combo_idcombo` = ?";
					$result = $conn -> prepare($query);
					$result -> bind_param("i", $_POST['idcombo']);
					$result -> execute();

					$query = "DELETE FROM `combo` WHERE `idcombo` = ?";
					$result = $conn -> prepare($query);
					$result -> bind_param("i", $_POST['idcombo']);
					$result -> execute();
				}
				//UPDATE `combo` SET `type` = '3' WHERE `combo`.`idcombo` = 18;
				if($_POST['action'] == 1){

					$query = "UPDATE `combo` SET `type` = '3' WHERE `combo`.`idcombo` = ?";
					$result = $conn -> prepare($query);
					$result -> bind_param("i", $_POST['idcombo']);
					$result -> execute();
				}

			}
		?>

	</head>

	<body>
		<main role="main">
		
			<div class="jumbotron jumbotron-fluid">
				<div class="container">
					<h1 class="display-4"></h1>
						<p>
							<a href="index.php"><img <?php
									echo 'src=img/games/';
									echo $_GET['gameid'];
									echo '.png ';
								?> align="middle" height="100" ></a>
						</p>
				</div>
			</div>
			
			<div class="container">
				<div class="btn-group" role="group" aria-label="Basic example">
					<form id="MyForm" method="get" action="index.php">
						<button class="btn btn-secondary">Home</button>
					</form>
					
					<form method="post" action="forms.php?gameid=<?php echo $_GET['gameid']; ?>">
						<button class="btn btn-secondary">Submit</button>
						<input type="hidden" id="type" name="type" value="1">
					</form>

					<form id="MyForm" method="get" action="forms.php">
						<button class="btn btn-secondary">Search</button>
						<input type="hidden" id="gameid" name="gameid" value="<?php echo $_GET['gameid'] ?>">
					</form>
				</div>
				
				<form class="form-inline" method="get" action="submit.php">
					<input type="hidden" id="gameid" name="gameid" value="<?php echo $_GET['gameid'] ?>">
					<?php 
						require "server/conexao.php";
							
						$query = "SELECT `Name`,`idcharacter` FROM `character` WHERE game_idgame = ? ORDER BY name;";
						$result = $conn -> prepare($query);
						$result -> bind_param("i", $_GET['gameid']);
						$result -> execute();
							
						echo '<p><select name="characterid" class="custom-select">';
							
						if(!isset($_POST['type'])){echo '<option value="-">Character</option>';}
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
							
						echo '</select></p>'; ?>
							
						<p>
							<select name="listingtype" class="custom-select">
								<option value="0">Combo</option>
								<option value="1">Blockstring</option>
								<option value="2">Mix Up</option>
								<option value="4">Okizeme</option>
								<option value="3">Archive</option>
								<option value="-">Show All</option>
							</select>
						</p>
						
						<?php
							require "server/conexao.php";
							
							$query = "SELECT text_name,type,idgame_resources FROM `game_resources` WHERE game_idgame = ? AND primaryORsecundary = 1 ORDER BY game_resources.primaryORsecundary DESC,text_name;";
							//echo $query;
							$result = $conn -> prepare($query);
							$result -> bind_param("i", $_GET['gameid']);
							$result -> execute();
							
							echo '<br><p>';
							
							foreach($result -> get_result()	as $resource){
								if($resource['type'] == 1){
										//print_r($_POST);
									echo '<div class="input-group mb-3">
  <div class="input-group-prepend">
    <label class="input-group-text">'; 
									echo '</label></div>  <select name="';
									echo $resource['text_name'];
									
									echo '"class="custom-select input-small">';
									
									$query = "SELECT idResources_values,value FROM `resources_values` WHERE `game_resources_idgame_resources` = ".$resource['idgame_resources']." ORDER BY resources_values.order, value;";
									$result = $conn -> prepare($query);
									$result -> execute();
									
									echo '<option value="-">'.$resource['text_name'].'</option>';
									
									foreach($result -> get_result() as $resource_value){
										echo '<option value="';
										echo $resource_value['idResources_values'].'" ';
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
										echo '<p>	<div class="input-group mb-3">
							<div class="input-group-prepend">
								<span class="input-group-text">';
											//echo $resource['text_name'];
										echo ' </div>
							<input class="form-control" type="number" min="0" class="input-sm" name="';
										echo $resource['text_name'];
										echo '" placeholder="'.$resource['text_name'].'"';
										echo ' max="';
										echo $resource_value['value'];
										echo '"step="any"' ;
										echo '> </div> </p>';
									}
								}
							}
							
							echo '</p><br>';
							
						?>
						
					<div class="form-group mb-2">
						<button type="submit" class="btn btn-info mb-2">Quick Search</button>
					</div>
					
				</form>
				
					<p>
						<h2>Latest submissions</h2>
					</p>
					
					<p>
						<small>Click the character name to see comments/video if the combo has them.</small>
					</p>
					
					<?php
					//$query = "SELECT COUNT(`idcombo`) as amount, `character`.`Name`, `combo`.`type` FROM `combo` INNER JOIN `character` ON `character`.`idcharacter` = `combo`.`character_idcharacter` WHERE `character`.`game_idgame` = ? GROUP BY `combo`.`type`,`combo`.`character_idcharacter` ORDER BY `character`.`Name`, `combo`.`type`;";
						require "server/functions.php";
						combo_table($_GET['gameid']);
						$query = "SELECT `character_idcharacter`, COUNT(idcombo) as amount, `character`.`Name` FROM `combo` INNER JOIN `character` ON `character`.`idcharacter` = `combo`.`character_idcharacter` WHERE `character`.`game_idgame` = ? GROUP BY `character_idcharacter` ORDER BY amount DESC";
						$result = $conn -> prepare($query);
						$result -> bind_param("i",$_GET['gameid']);
						$result -> execute();
						
						/*echo '<table>';
						echo '<tr>';
						echo '<th>Character</th><th>Total Entries</th></th>';
						echo '</tr>';*/
						echo '<p><h3> Total Entries </h3>';
						//echo '├┬┴┬┴';
						foreach($result -> get_result() as $data){
							/*echo '<tr><td>'.$data['Name'].'</td>';
							echo '<td>'.$data['amount'].'</td></tr>';*/
							echo ' '.$data['Name'].': '.$data['amount'].' ▰ ';
						}
						//echo '┬┴┬┴┤';
					?>
					</p>
			</div>
		</main>
	</body>
	    <!-- Bootstrap core JavaScript
		================================================== -->
		<!-- Placed at the end of the document so the pages load faster -->
	<script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>
	<script>window.jQuery || document.write('<script src="../../../../assets/js/vendor/jquery.min.js"><\/script>')</script>
		<!-- <script src="../../../../assets/js/vendor/popper.min.js"></script>
		<script src="../../../../dist/js/bootstrap.min.js"></script> -->
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
					}else if (dir == "desc") {
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
				if(shouldSwitch){
					  /*If a switch has been marked, make the switch
					  and mark that a switch has been done:*/
					rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
					switching = true;
					  //Each time a switch is done, increase this count by 1:
					switchcount ++;      
				}else{
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
			}else{
				x.style.display = "none";
			}
		}
	</script>
</html>

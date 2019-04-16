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

		<title>ComboSuki</title>
    <!-- Bootstrap core CSS -->
		<link href="css/bootstrap.min.css" rel="stylesheet">

    <!-- Custom styles for this template -->
		<link href="jumbotron.css" rel="stylesheet">
		<style>
			body{
				background-color: #35340a;
				background: url("img/<?php
				if(isset($_COOKIE['color'])){
					echo 'bg/'.$_COOKIE["color"].'honeycomb.png';
				}else{
					echo 'yellow-honeycomb.png';
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
			$_GET = array_map("strip_tags", $_GET);
		?>

	</head>

	<body>
		<main role="main">
			<div class="jumbotron jumbotron-fluid">
				<div class="container">
					<h1 class="display-4"></h1>
						<p>
							<a href="index.php"><img  src="img/combosuki.png" align="middle" height="170" ></a>
						</p>
				</div>
			</div>
			<div class="container">
				<div class="btn-group" role="group" aria-label="Basic example">
					<form id="MyForm" method="get" action="index.php">
						<button class="btn btn-secondary">Home</button>
					</form>
				</div>
				
					<p><h2><?php echo $_GET['author']; ?> Latest submissions</h2></p>
					<?php
						if (isset($_GET["page"])) { $page  = $_GET["page"]; } else { $page=0; };
						$limit = $page * 50;
						
						if($page > 0){
							echo '<a href="profile.php?author='.$_GET['author'].'&page=';
							
								echo $page - 1;
							
							foreach ($_GET as $key => $entry){
								if($entry != '-' && $entry != '' && $key != 'page'){
									echo '&';
									echo $key;
									echo '=';
									echo $entry;
								}
							}
							echo '" style="padding-right: 5px;">Previous </a>';
							echo '/ ';
						}
						
							echo '<a href="profile.php?author='.$_GET['author'].'&page=';
							echo $page + 1;
							foreach ($_GET as $key => $entry){
								if($entry != '-' && $entry != '' && $key != 'page'){
									echo '&';
									echo $key;
									echo '=';
									echo $entry;
								}
								
							}
							
							echo '" style="padding-right: 5px;">Next </a>';
						
						require "server/conexao.php";
						$query = "SELECT idcombo,Name,combo,damage,type, comments, video, submited FROM `combo` INNER JOIN `character` ON `combo`.`character_idcharacter` = `character`.`idcharacter` WHERE `combo`.`author` LIKE ? ORDER BY submited DESC LIMIT ?,50";
						$result = $conn -> prepare($query);
						$result -> bind_param("si",$_GET['author'],$limit);
						$result -> execute();
						echo '<table id="myTable">';
							echo '<tr>';
								echo '<th onclick="sortTable(0)">Character</th><th onclick="sortTable(1)">Inputs</th><th onclick="sortTable(2,1)">Damage</th><th onclick="sortTable(3)">Type</th><th onclick="sortTable(4)">Submited</th>';
							echo '</tr>';
						
							foreach($result -> get_result() as $data){
								echo '<tr><td data-toggle="tooltip" data-placement="bottom" title="'.$data['comments'].'">';
								echo '<button class="btn btn-dark" onclick="showDIV('.$data['idcombo'].')">'.$data['Name'].'</button>';
								echo '</td><td style="min-width:400px">';
								echo		'<a data-toggle="tooltip" data-placement="bottom" title="'.$data['comments'].'" href="combo.php?idcombo='.$data['idcombo'].'">'.$data['combo'].'</a>';
								echo '<div id="'.$data['idcombo'].'" style="display: none;">';
								echo $data['comments'];
  
  //####################################################################VIDEO HERE
  
  if($data['video'] != ''){
					if (strpos($data['video'], 'twitter') !== false && strpos($data['video'], 'https') !== false) {
						echo '<blockquote class="twitter-tweet" data-conversation="none" data-lang="en"><p lang="en" dir="ltr">
						<a href="';
						echo $data['video'];
						echo '"></a>
					</blockquote>
					<script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>';
					}else if (strpos($data['video'], 'youtu') !== false) {
						preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $data['video'], $match);
						$youtube_id = $match[1];
						//print_r($match);
						//echo '<br> URL: ';
						//echo $youtube_id;
						$whatIWant = substr($data['video'], strpos($data['video'], "=") + 1);    
						//echo '<br>what I want:';
						//echo $whatIWant;
						echo '<div class="embed-responsive embed-responsive-16by9">
						<iframe class="embed-responsive-item" src="https://www.youtube.com/embed/';
						echo $youtube_id;
						echo '?start=';echo $whatIWant; echo '" allowfullscreen></iframe></div>';
					}else if(strpos($data['video'], 'streamable') !== false && strpos($data['video'], 'https') !== false){
											/*echo '<div class="embed-responsive embed-responsive-16by9">
											<iframe class="embed-responsive-item" src="';
											echo $data['video'];
											echo '" allowfullscreen></iframe>
										</div>';*/
										//$i = substr_replace($data['video'], "/s", 22,0);
										echo '<div style="width: 100%; height: 0px; position: relative; padding-bottom: 56.250%;">
						<iframe src="';
											echo $data['video'];
											echo '" frameborder="0" width="100%" height="100%" allowfullscreen style="width: 100%; height: 100%; position: absolute;">
						</iframe>
						</div>';
											
											
											/*echo '<div style="width: 100%; height: 0px; position: relative; padding-bottom: 56.250%;"><iframe src="';
											echo $streamable;
											
											echo '" frameborder="0" width="100%" height="100%" allowfullscreen style="width: 100%; height: 100%; position: absolute;"></iframe></div>';*/
											
					}else if(strpos($data['video'], 'twitch') !== false && strpos($data['video'], 'clips') !== false && strpos($data['video'], 'https') !== false){
						$i = substr_replace($data['video'], "embed?clip=", 24,0);
						echo '<iframe
							src="'.$i.'"
							height="360"
							width="640"
							frameborder="0"
							scrolling="no"
							allowfullscreen="true">
						</iframe>';
						
					}else{
						echo $data['video'];	
					}
				}
  
  //######################################################################VIDEO ABOVE
  
								echo '</div>';
								echo '</td><td>';
								echo number_format($data['damage'],'0','','.');
								echo '</td><td>';
								switch($data['type']){
									case 0:
										echo 'Combo';
										break;
									case 1:
										echo 'Blockstring';
										break;
									case 2:
										echo 'Mix Up';
										break;
									case 3:
										echo 'Archive';
										break;
								}
								echo '</td><td>';
								$i = new DateTime($data['submited']);
								echo $i->format('d-m');
								echo '</td></tr>';
							}
							echo '</table>';
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
				<script>
		function showDIV(DIV_ID) {
		  var x = document.getElementById(DIV_ID);
		  if (x.style.display === "none") {
			x.style.display = "block";
		  } else {
			x.style.display = "none";
		  }
		}</script>
</html>

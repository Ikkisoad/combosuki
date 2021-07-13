<!doctype php>
<?php
	include_once "server/functions.php";
	include_once "server/conexao.php";
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
			jumbotron($conn,100);
			//header_buttons(2, 0, 0,get_gamename($_GET['gameid'], $conn));
			header_buttons(2,1,'game.php',get_gamename($_GET['gameid'], $conn));
		?>
		<div class="container-fluid">
			<?php
				quick_search_form($_GET['gameid'], $conn);
			?>
			<div class="row">
				<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block sidebar show collapse">
					<?php count_char($conn);?>
				</nav>

				<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4"><div class="chartjs-size-monitor"><div class="chartjs-size-monitor-expand"><div class=""></div></div><div class="chartjs-size-monitor-shrink"><div class=""></div></div></div>
					<?php 
						print_game_description($_GET['gameid'], $conn);
						print_game_links($_GET['gameid'], $conn); 
					?>
					<h2>Latest submissions</h2>
					<div class="table-responsive">
						<table class="table table-striped table-sm">
							<?php combo_table($_GET['gameid'],$conn); ?>
						</table>
					</div>
				</main>
				<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script>
				<!-- Horizontal AD Unit -->
				<ins class="adsbygoogle"
				style="display:block"
				data-ad-client="ca-pub-5026741930646917"
				data-ad-slot="5901997200"
				data-ad-format="auto"
				data-full-width-responsive="true"></ins>
				<script>
				(adsbygoogle = window.adsbygoogle || []).push({});
				</script>
				<?php 
				mysqli_close($conn);?>
			</div>
		</div>
		<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js" integrity="sha384-gtEjrD/SeCtmISkJkNUaaKMoLD0//ElJ19smozuHV6z3Iehds+3Ulb9Bn9Plx0x4" crossorigin="anonymous"></script>
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
</html>
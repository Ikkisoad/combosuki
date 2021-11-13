<?php
	$URLDepth = '../';
	require_once "../server/initialize.php";
	$listName = '';
	$gameid = -1;
	if(!empty($_POST)){ //Creating a List
		if($_POST['submission_type'] == 1){
			if($_POST['list_name'] == '' || $_POST['gameid'] == 0){//This prevents '' lists from being created
				$listName = $_POST['list_name'];
				$gameid = $_POST['gameid'];
			}else{
				$query = "INSERT INTO `list`(`list_name`, `game_idgame`, `password`, `type`) VALUES (?,?,?,1)";
				$result = $conn -> prepare($query);
				$result -> bind_param("sis", $_POST['list_name'], $_POST['gameid'], $_POST['listPass']);
				$result -> execute();

				$listid = mysqli_insert_id($conn);
				header("Location: ".$URLDepth."list/show.php?id=".$listid."");
				exit();
			}
		}
	}

?>
<!doctype php>
<html>
	<head>
		
		<?php
			headerHTML(); 
			openGraphProtocol();
		?>
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
			jumbotron(150);
			header_buttons(1, 0, 0, 0);
		?>

			<div class="container-fluid my-3">
				<div class="row">
				<?php
					echo '<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block sidebar collapse">';
					create_list_form($listName, $gameid);
					search_list_form();
					echo '</nav>';
					echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4"><div class="chartjs-size-monitor"><div class="chartjs-size-monitor-expand"><div class=""></div></div><div class="chartjs-size-monitor-shrink"><div class=""></div></div></div>';
					listsTable(getFirst50Lists());
					echo '</main>';

					mysqli_close($conn);
				?>
				</div>
			</div>
		<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js" integrity="sha384-gtEjrD/SeCtmISkJkNUaaKMoLD0//ElJ19smozuHV6z3Iehds+3Ulb9Bn9Plx0x4" crossorigin="anonymous"></script>
	</body>
	<!-- Bootstrap core JavaScript
	================================================== -->
	<!-- Placed at the end of the document so the pages load faster -->
	<script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>
	<script>window.jQuery || document.write('<script src="../../../../assets/js/vendor/jquery.min.js"><\/script>')</script>
</html>
<?php
	$URLDepth = '../';
	require_once "../server/initialize.php";
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
			jumbotron(1,150);
			header_buttons(1, 0, 0, 0);
		?>

			<div class="container-fluid my-3">
				<div class="row">
				<?php
					echo '<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block sidebar collapse">';
					create_list_form($conn);
					search_list_form();
					echo '</nav>';
					echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4"><div class="chartjs-size-monitor"><div class="chartjs-size-monitor-expand"><div class=""></div></div><div class="chartjs-size-monitor-shrink"><div class=""></div></div></div>
				';
							if(isset($_GET['q'])){
								if($_GET['gameid']){
									$query = "SELECT `idlist`, `list_name`, `type` FROM `list` WHERE `list_name` LIKE ? AND `game_idgame` = ? AND `list`.`type` != 0 ORDER BY `type` DESC, `list_name` LIMIT 0,50";
								}else{
									$query = "SELECT `idlist`, `list_name`, `type` FROM `list` WHERE `list_name` LIKE ? AND `list`.`type` != 0 ORDER BY `type` DESC, `list_name` LIMIT 0,50";
								}
								$result = $conn -> prepare($query);
								$_GET['q'] = '%'.$_GET['q'].'%';
								if($_GET['gameid']){
									$result -> bind_param("si",$_GET['q'], $_GET['gameid']);
								}else{
									$result -> bind_param("s",$_GET['q']);
								}
								$result -> execute();
								echo '<table id="myTable" class="table table-hover align-middle caption-top combosuki-main-reversed text-white">';
								echo '<tr>';
								echo '<th>List Name</th>';
								echo '</tr>';
								foreach($result -> get_result() as $search){
									if($search['list_name'] != ''){
										echo '<tr><td><a href="list.php?listid='.$search['idlist'].'">'.$search['list_name'].'</a>';
										print_listglyph($search['type']);
										echo '</tr></td>';
									}
								}
							}
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
	<?php 
		resetVideoJS();
		playVideoJS();
		showDIV_JS();
		copytoclipJS();
	?>
</html>

<!doctype php>
<?php
	$URLDepth = '../';
	require_once "../server/initialize.php";
?>
<html>
	<head>
		
		<?php headerHTML(); ?>

		<meta property="og:title" content="Combo好き" />
		<meta property="og:type" content="website" />
		<meta property="og:image" content="http://combosuki.com/img/combosuki.png" />
		<meta property="og:url" content="http://combosuki.com/index.php" />
		<meta property="og:description" 
		content="Community-fueled searchable environment that shares and perfects combos." />
		<meta name="theme-color" content="#d94040" />

		<meta name="description" content="Community-fueled searchable environment that shares and perfects combos.">
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
			jumbotron(100);
			//header_buttons(2, 0, 0,get_gamename($_GET['gameid'], $conn));
			header_buttons(2,1,'game/index.php',get_gamename($_GET['gameid'], $conn));
		?>
		<div class="container-fluid my-3">
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
							<?php 
								combo_table($_GET['gameid'],$conn);
							?>
						</table>
					</div>
					
						<?php
							game_lists($conn);
						?>
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
	<?php
		sortTableJS();
	?>
</html>
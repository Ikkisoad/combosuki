<!doctype php>
<?php
	$URLDepth = '../../';
	require_once "../../server/initialize.php";
	if(!empty($_POST)){
		verify_password();
		
		$_POST['name'] = str_replace(' ', '', $_POST['name']);
		
		if($_POST['action'] == 'Update'){
			updateButton($_POST['name'], $_POST['png'], $_POST['order'], $_GET['gameid'], $_POST['idbutton']);
		}else if($_POST['action'] == 'Delete'){
			deleteButton($_POST['idbutton'], $_GET['gameid']);			
		}else if($_POST['action'] == 'Add'){
			insertButton($_POST['name'], $_POST['png'], $_GET['gameid'], $_POST['order']);
		}
	}
?>
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
			.container{
				height: 100vh;
			}
			.jumbotron{
				max-height: 190px;
				background-color: #000000;
			}
		</style>
	</head>	
	
	<body>
		<?php header_buttons(2, 1, 'game.php', get_gamename($_GET['gameid'], $conn));?>
			<div class="container-fluid my-3">
				<div class="form-group">
					<?php
					
						
						editButtons();
						addButton();
						
						edit_controls($_GET['gameid']);
						mysqli_close($conn);
					?>
					<p>Buttons are used as shortcuts when creating a submission.
						<br>Button Name is what is typed on the text box when you click it.
						<br>You can also choose how the button looks and its order.
						<br>If you have any new image suggestion please send them over <a href="https://goo.gl/forms/6Q8dVlNbdOyTMA4h2" target="_blank">here</a>.
						<br>Use \n for a newline.
					</p>
				</div>
			</div>
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
		<?php
			setImageJS();
		?>
		<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js"></script>
</html>

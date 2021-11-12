<?php
	$URLDepth = '../';
	require_once "../server/initialize.php";
	
	if(!empty($_POST){
		if($_POST['submission_type'] == 2){
			if($_POST['action'] == 'DeleteList'){
				$query = "SELECT `password`, `game_idgame` FROM `list` WHERE idlist = ?";
				$result = $conn -> prepare($query);
				$result -> bind_param("i",$_GET['listid']);
				$result -> execute();
				foreach($result -> get_result() as $lul){
					$modPass = get_mod_password($lul['game_idgame'], $conn);
					if(password_verify($_POST['listPass'], $modPass)){
						$query = "UPDATE `list` SET `type`=? WHERE `idlist` = ?";
						$result = $conn -> prepare($query);
						$i = 0;
						$result -> bind_param("ii", $i, $_GET['listid']);
						$result -> execute();
					}
					$gamepass = get_gamepassword($lul['game_idgame'], $conn);
					if($lul['password'] == $_POST['listPass'] || $_POST['listPass'] == $gamepass){
						$query = "DELETE FROM `combo_listing` WHERE `idlist` = ?";
						$result = $conn -> prepare($query);
						$result -> bind_param("i", $_GET['listid']);
						$result -> execute();

						$query = "DELETE FROM `list_category` WHERE `list_idlist` = ?";
						$result = $conn -> prepare($query);
						$result -> bind_param("i", $_GET['listid']);
						$result -> execute();

						$query = "DELETE FROM `list` WHERE `idlist` = ?";
						$result = $conn -> prepare($query);
						$result -> bind_param("i", $_GET['listid']);
						$result -> execute();


						header("Location: list.php");
						exit();

					}
				}
			}

			if(isset($_POST['idlist']))$_GET['listid'] = $_POST['idlist'];
			verify_ListPassword($conn);
			if($_POST['action'] == 'UpdateList'){
				if($_POST['listName'] == ''){
					header("Location: list.php");
					exit();
				}
				$query = "UPDATE `list` SET `list_name`= ? WHERE `idlist` = ?";
				$result = $conn -> prepare($query);
				$result -> bind_param("si", $_POST['listName'],$_GET['listid']);
				$result -> execute();
			}else{
				alter_List($conn);
			}

		}else if($_POST['submission_type'] == 3){

			verify_ListPassword($conn);

			if($_POST['action'] == 'DeleteCategory'){
				$query = "DELETE FROM `combo_listing` WHERE `list_category_idlist_category` = ?";
				$result = $conn -> prepare($query);
				$result -> bind_param("i", $_POST['categoryid']);
				$result -> execute();

				$query = "DELETE FROM `list_category` WHERE `idlist_category` = ?";
				$result = $conn -> prepare($query);
				$result -> bind_param("i", $_POST['categoryid']);
				$result -> execute();
			}else if($_POST['action'] == 'UpdateCategory' && $_POST['category'] != ''){
				$query = "UPDATE `list_category` SET `title`=?,`order`=? WHERE `idlist_category` = ?";
				$result = $conn -> prepare($query);
				$result -> bind_param("sii", $_POST['category'],$_POST['order'],$_POST['categoryid']);
				$result -> execute();
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
					list_categories($_GET['id']);
					echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4"><div class="chartjs-size-monitor"><div class="chartjs-size-monitor-expand"><div class=""></div></div><div class="chartjs-size-monitor-shrink"><div class=""></div></div></div>
				';
						if(isset($_GET['id'])){
							$query = "SELECT list_name, name, type, game_idgame FROM `list` join game ON list.game_idgame = game.idgame where list.idlist = ?";
							$result = $conn->prepare($query);
							$result->bind_param("i",$_GET['id']);
							$result->execute();
							listHeader($result->get_result());
							$query = "SELECT `combo_listing`.`idcombo`, `combo`.`damage`, `character`.`Name`, `combo`.`combo`, `combo`.`comments`,`combo`.`video`,`combo`.`type`, `list_category`.`title`, `list_category`.`idlist_category`,`list_category`.`order` 
							FROM `combo_listing` 
							INNER JOIN `combo` ON `combo`.`idcombo` = `combo_listing`.`idcombo` 
							LEFT JOIN `character` ON `character`.`idcharacter` = `combo`.`character_idcharacter` 
							LEFT JOIN `list_category` ON `list_category`.`idlist_category` = `combo_listing`.`list_category_idlist_category`
							WHERE `idlist` = ? ORDER BY `list_category`.`order`, `list_category`.`title`,`combo`.`damage` DESC;";
							$result = $conn->prepare($query);
							$result->bind_param("i",$_GET['id']);
							$result->execute();
							listContent($result->get_result());
							edit_listForm();
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
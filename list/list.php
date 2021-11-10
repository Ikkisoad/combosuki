<?php
	$URLDepth = '../';
	require_once "../server/initialize.php";
	
	if(!empty($_POST)){
		strip_POSTtags();
		strip_GETtags();
		if($_POST['action'] != 'Search'){
			if($_POST['submission_type'] == 1){
				if($_POST['list_name'] == '' || $_POST['gameid'] == 0){//This prevents '' lists from being created
					header("Location: list.php");
					exit();	
				}
				$query = "INSERT INTO `list`(`list_name`, `game_idgame`, `password`, `type`) VALUES (?,?,?,?)";
				$result = $conn -> prepare($query);
				$i = 1;
				$result -> bind_param("sisi", $_POST['list_name'], $_POST['gameid'], $_POST['listPass'], $i);
				$result -> execute();

				$listid = mysqli_insert_id($conn);
				header("Location: list.php?listid=".$listid."");
				exit();
			}else if($_POST['submission_type'] == 2){
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
	}

?>

<!doctype php>
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
			jumbotron(1,150);
			header_buttons(1, 0, 0, 0);
		?>

			<div class="container-fluid my-3">
				<div class="row">
				<?php

					strip_GETtags();

					if(isset($_GET['listid'])){
						list_categories($_GET['listid'],$conn);
						echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4"><div class="chartjs-size-monitor"><div class="chartjs-size-monitor-expand"><div class=""></div></div><div class="chartjs-size-monitor-shrink"><div class=""></div></div></div>
					';
						$query = "SELECT list_name, name, type, game_idgame FROM `list` join game ON list.game_idgame = game.idgame where list.idlist = ?";
						$result = $conn -> prepare($query);
						$result -> bind_param("i", $_GET['listid']);
						$result -> execute();
						foreach($result -> get_result() as $list){
							$_GET['gameid'] = $list['game_idgame'];
							//header_buttons(2, 1, 'game.php',get_gamename($_GET['gameid'], $conn));
							echo '<h3 class="mt-3">'.$list['list_name'];
								print_listglyph($list['type']);
								echo '
								<button class="btn btn-dark" onclick="showDIV(0)">
									<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-pencil-square" viewBox="0 0 16 16">
									  <path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/>
									  <path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5v11z"/>
									</svg>
								</button>';
							echo '</h3>';
							echo '
							<div id="0" style="display: none;">
								<form method="post" action="list.php?listid='.$_GET['listid'].'">
									<input type="hidden" name="submission_type" value="2">
									<input type="hidden" name="idlist" value="'.$_GET['listid'].'">
									<div class="input-group">
										<textarea name="listName" maxlength="45" style="background-color: #474747; color:#ffffff;" class="form-control" rows="1" placeholder="List Name">'.$list['list_name'].'</textarea>
										<input name="listPass" type="password" maxlength="16" style="background-color: #474747; color:#999999;" class="form-control" rows="1" placeholder="List Password">
										<div class="input-group-append" id="button-addon4">
											<button type="submit" name="action" value="UpdateList" class="btn btn-primary">Update</button>
											<button type="submit" name="action" value="DeleteList" class="btn btn-danger" onclick="return confirm();">Delete List</button>
										</div>
									</div>
								</form>
							</div>';
							echo '<div class="row">';
								echo '<div class="col">';
									print_gameglyph($list['game_idgame'],$conn);
									echo $list['name'].' list';
									copyLinktoclipboard('https://combosuki.com/list.php?listid='.$_GET['listid']);
								echo '</div>';
							echo '</div>';
						}
						echo '<div class="row"><div class="table-responsive">';
						echo '<table id="myTable" class="table table-hover align-middle caption-top combosuki-main-reversed text-white">';
							echo '<tr>';
								echo '<th>Character</th><th>Inputs</th><th>Damage</th><th>Type</th>';
							echo '</tr>';
							$title = '';
							$query = "SELECT `combo_listing`.`idcombo`, `combo`.`damage`, `character`.`Name`, `combo`.`combo`, `combo`.`comments`,`combo`.`video`,`combo`.`type`, `list_category`.`title`, `list_category`.`idlist_category`,`list_category`.`order` 
							FROM `combo_listing` 
							INNER JOIN `combo` ON `combo`.`idcombo` = `combo_listing`.`idcombo` 
							LEFT JOIN `character` ON `character`.`idcharacter` = `combo`.`character_idcharacter` 
							LEFT JOIN `list_category` ON `list_category`.`idlist_category` = `combo_listing`.`list_category_idlist_category`
							WHERE `idlist` = ? ORDER BY `list_category`.`order`, `list_category`.`title`,`combo`.`damage` DESC;";
							$result = $conn -> prepare($query);
							$result -> bind_param("i", $_GET['listid']);
							$result -> execute();
						echo '</table>';
						foreach($result -> get_result() as $data){
							if($title != $data['title']){
								echo '</table>';
								echo '<h2 class="mt-3"><span class="mw-headline" id="'.$data['title'].'">'.$data['title'].'</span>
								<button class="btn btn-dark" onclick="showDIV(-'.$data['idlist_category'].')">
									<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-pencil-square" viewBox="0 0 16 16">
									  <path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/>
									  <path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5v11z"/>
									</svg>
								</button></h2>';
								echo '
								<div id="-'.$data['idlist_category'].'" style="display: none;">
									<form method="post" action="list.php?listid='.$_GET['listid'].'">
										<input type="hidden" name="submission_type" value="3">
										<input type="hidden" name="categoryid" value="'.$data['idlist_category'].'">
										<div class="input-group">
											<textarea name="category" maxlength="45" style="background-color: #474747; color:#ffffff;" class="form-control" rows="1" placeholder="Category Name">'.$data['title'].'</textarea>
											<input class="form-control" type="number" name="order" placeholder="Order" value="'.$data['order'].'" step="any">
											<input name="listPass" type="password" maxlength="16" style="background-color: #474747; color:#999999;" class="form-control" rows="1" placeholder="List Password">
											<div class="input-group-append" id="button-addon4">
												<input type="hidden" name="entryid" value="54">
												<button type="submit" name="action" value="UpdateCategory" class="btn btn-primary">Update</button>
												<button type="submit" name="action" value="DeleteCategory" class="btn btn-danger" onclick="return confirm();">Delete</button>
											</div>
										</div>
									</form>
								</div>';
								echo '<table class="table table-hover align-middle caption-top combosuki-main-reversed  text-white">';
								$title = $data['title'];
							}
							echo '<tr><td data-toggle="tooltip" data-placement="bottom" title="'.$data['comments'].'">';
							if($data['comments'] != '' || $data['video'] != ''){
								echo '<button class="btn btn-dark" onclick="showDIV('.$data['idcombo'].')">'.$data['Name'].'</button>';
								//echo '<button class="btn btn-dark" type="button" data-bs-toggle="collapse" onclick="showDIV('.$data['idcombo'].')" data-bs-target="#collapse'.$data['idcombo'].'"">'.$data['Name'].'</button>';
							}else{
								echo $data['Name'];
							}
							echo '</td><td style="min-width:400px">';
							echo		'<text data-toggle="tooltip" data-placement="bottom" title="'.$data['comments'].'" href="'.$URLDepth.'game/combo.php?idcombo='.$data['idcombo'].'&listid='.$_GET['listid'].'">'.$data['combo'].'</text>';
							echo '	<a href="'.$URLDepth.'game/combo.php?idcombo='.$data['idcombo'].'">
										<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-box-arrow-up-right" viewBox="0 0 16 16">
										  <path fill-rule="evenodd" d="M8.636 3.5a.5.5 0 0 0-.5-.5H1.5A1.5 1.5 0 0 0 0 4.5v10A1.5 1.5 0 0 0 1.5 16h10a1.5 1.5 0 0 0 1.5-1.5V7.864a.5.5 0 0 0-1 0V14.5a.5.5 0 0 1-.5.5h-10a.5.5 0 0 1-.5-.5v-10a.5.5 0 0 1 .5-.5h6.636a.5.5 0 0 0 .5-.5z"/>
										  <path fill-rule="evenodd" d="M16 .5a.5.5 0 0 0-.5-.5h-5a.5.5 0 0 0 0 1h3.793L6.146 9.146a.5.5 0 1 0 .708.708L15 1.707V5.5a.5.5 0 0 0 1 0v-5z"/>
										</svg>
									</a>';
							echo '<div id="'.$data['idcombo'].'" style="display: none;">';
							//echo '<div class="collapse" id="collapse'.$data['idcombo'].'">';
								echo $data['comments'].'<br>';

								//####################################################################VIDEO HERE

								embed_video_on_demand($data['video'], $data['idcombo']);

								//######################################################################VIDEO ABOVE

							echo '</div>';
							echo '</td><td>';
							echo number_format($data['damage'],'0','','.');
							echo '</td><td>';
							print_listingtype($data['type'], $conn);
							echo '</td>';
						}
						echo '</table>';
						echo '</div></div>';
						edit_listForm($conn);
						echo '</main>';
					}else{
						echo '<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block sidebar collapse">';
						create_list_form($conn);
						search_list_form($conn);
						echo '</nav>';
						echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4"><div class="chartjs-size-monitor"><div class="chartjs-size-monitor-expand"><div class=""></div></div><div class="chartjs-size-monitor-shrink"><div class=""></div></div></div>
					';
						if(isset($_POST)){
							if(isset($_POST['action'])){
								if($_POST['action'] == 'Search'){
									if($_POST['gameid']){
										$query = "SELECT `idlist`, `list_name`, `type` FROM `list` WHERE `list_name` LIKE ? AND `game_idgame` = ? AND `list`.`type` != 0 ORDER BY `type` DESC, `list_name` LIMIT 0,50";
									}else{
										$query = "SELECT `idlist`, `list_name`, `type` FROM `list` WHERE `list_name` LIKE ? AND `list`.`type` != 0 ORDER BY `type` DESC, `list_name` LIMIT 0,50";
									}
									$result = $conn -> prepare($query);
									$_POST['list_name'] = '%'.$_POST['list_name'].'%';
									if($_POST['gameid']){
										$result -> bind_param("si",$_POST['list_name'], $_POST['gameid']);
									}else{
										$result -> bind_param("s",$_POST['list_name']);
									}
									$result -> execute();
									echo '<table id="myTable" class="table table-hover align-middle caption-top combosuki-main-reversed text-white">';
									echo '<tr>';
									echo '<th>List Name</th>';
									echo '</tr>';
									foreach($result -> get_result() as $search){
										if($search['list_name'] != ''){
											echo '<tr><td><a href="list.php?listid='.$search['idlist'].'">'.$search['list_name'].'</a>';
											print_listglyph($search['type'], $conn);
											echo '</tr></td>';
										}
									}
								}
							}else{
								$query = "SELECT `idlist`, `list_name`, `type`,`game_idgame` FROM `list` ORDER BY `idlist` DESC LIMIT 0,50";
								$result = $conn -> prepare($query);
								echo '<table id="myTable" class="table table-hover align-middle caption-top combosuki-main-reversed text-white">';
								$result -> execute();
								echo '<tr>';
								echo '<th>List Name</th>';
								echo '</tr>';
								foreach($result -> get_result() as $search){
									if($search['list_name'] != ''){
										echo '<tr><td>';
										print_gameglyph($search['game_idgame'],$conn);
										echo '<text href="list.php?listid='.$search['idlist'].'">'.$search['list_name'].'</text>';
										print_listglyph($search['type']);
										echo '	<a href="list.php?listid='.$search['idlist'].'">
											<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-box-arrow-up-right" viewBox="0 0 16 16">
											  <path fill-rule="evenodd" d="M8.636 3.5a.5.5 0 0 0-.5-.5H1.5A1.5 1.5 0 0 0 0 4.5v10A1.5 1.5 0 0 0 1.5 16h10a1.5 1.5 0 0 0 1.5-1.5V7.864a.5.5 0 0 0-1 0V14.5a.5.5 0 0 1-.5.5h-10a.5.5 0 0 1-.5-.5v-10a.5.5 0 0 1 .5-.5h6.636a.5.5 0 0 0 .5-.5z"/>
											  <path fill-rule="evenodd" d="M16 .5a.5.5 0 0 0-.5-.5h-5a.5.5 0 0 0 0 1h3.793L6.146 9.146a.5.5 0 1 0 .708.708L15 1.707V5.5a.5.5 0 0 0 1 0v-5z"/>
											</svg>
										</a>';
										echo '</tr></td>';
									}
								}
							}
						}
						echo '</main>';
					}

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
	<script>
		function playVideo(video_id) {
			var video = document.getElementById(video_id);
			var src = video.dataset.src;

			video.src = src + '?autoplay=1';
		}

		function resetVideo(video_id) {
			var video = document.getElementById(video_id);
			var src = video.src.replace('?autoplay=1', '');

			video.src = '';
			video.dataset.src = src;
		}

		function showDIV(DIV_ID) {
			var x = document.getElementById(DIV_ID);
			if (x.style.display === "none") {
				x.style.display = "block";
				playVideo('v'+DIV_ID);
			}else{
				x.style.display = "none";
				resetVideo('v'+DIV_ID);
			}
		}
	</script>
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

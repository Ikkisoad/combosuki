<!doctype php>
<?php
	$URLDepth = '../../';
	require_once "../../server/initialize.php";
	$edit = 0;
	if(!empty($_POST)){
		$_POST = array_map("strip_tags", $_POST);
		$_GET = array_map("strip_tags", $_GET);
		if($_POST['action'] != 'Edit'){
			verify_password();
			
			if($_POST['action'] == 'Update'){
				if(verify_resource_game($_POST['idresource'], $conn) != $_GET['gameid']){
					redictIndex();
				}
				$query = "UPDATE `game_resources` SET `text_name`= ?,`type`= ?,`primaryORsecundary`= ? WHERE `idgame_resources` = ? AND `game_idgame` = ?";
				$result = $conn -> prepare($query);
				$result -> bind_param("siiii", $_POST['resource'], $_POST['type'],$_POST['primaryORsecundary'],$_POST['idresource'], $_GET['gameid']);
				$result -> execute();
			}else if($_POST['action'] == 'Delete'){
				if(verify_resource_game($_POST['idresource'], $conn) != $_GET['gameid']){
					redictIndex();
				}
				$query = "DELETE FROM `resources_values` WHERE `game_resources_idgame_resources` = ?";
				$result = $conn -> prepare($query);
				$result -> bind_param("i",$_POST['idresource']);
				$result -> execute();
				
				$query = "DELETE FROM `game_resources` WHERE `idgame_resources` = ?";
				$result = $conn -> prepare($query);
				$result -> bind_param("i",$_POST['idresource']);
				$result -> execute();
			}else if($_POST['action'] == 'Add'){
				insertResource($_GET['gameid'], $_POST['resource'],$_POST['type'],$_POST['primaryorsecundary']);
			}else if($_POST['action'] == 'EditAdd'){
				insertResourceValue($_POST['resourcevalue'],$_POST['order'],$_POST['idresource']);
				$edit = 1;
			}else if($_POST['action'] == 'EditUpdate'){
					if(verify_resourcevalue_game($_POST['idresourcevalue'], $conn) != $_GET['gameid']){
						redictIndex();
					}
					$query = "UPDATE `resources_values` SET `value`=?,`order`=? WHERE `idResources_values` = ?";
					$result = $conn -> prepare($query);
					$result -> bind_param("sii", $_POST['resourcevalue'], $_POST['order'],$_POST['idresourcevalue']);
					$result -> execute();
					$edit = 1;
			}else if($_POST['action'] == 'EditDelete'){
				if(verify_resourcevalue_game($_POST['idresourcevalue'], $conn) != $_GET['gameid']){
					redictIndex();	
				}
				
				$query = "DELETE FROM `resources` WHERE `Resources_values_idResources_values` = ?";
				$result = $conn -> prepare($query);
				$result -> bind_param("i",$_POST['idresourcevalue']);
				$result -> execute();
				
				$query = "DELETE FROM `resources_values` WHERE `idResources_values` = ?";
				$result = $conn -> prepare($query);
				$result -> bind_param("i",$_POST['idresourcevalue']);
				$result -> execute();
				$edit = 1;
			}
			
		}else if($_POST['action'] == 'Edit'){
			$edit = 1;
		}
	}
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
		<meta name="theme-color" content="#C62114" />

		<meta name="description" content="Community-fueled searchable environment that shares and perfects combos.">
		<title>Combo好き</title>
		<?php getCSS(); ?>

		<style>
			<?php
				background();
				table();
			?>
		</style>
	</head>
	
	<body>
		<?php header_buttons(2, 1, 'game.php', get_gamename($_GET['gameid'], $conn));?>
			<div class="container-fluid my-3">
				<div class="form-group">
					<?php
						if($edit){
							$query = "SELECT `idResources_values`,`value`, `order` FROM `resources_values` WHERE `game_resources_idgame_resources` = ? ORDER BY `order`, `value`;";
							$result = $conn -> prepare($query);
							$result -> bind_param("i",$_POST['idresource']);
							$result -> execute();
							echo '<table id="myTable" class="table table-hover align-middle caption-top combosuki-main-reversed text-white">';
							echo '<tr>';
							echo '<th>'.$_POST['resource'].'</th';
							echo '</tr>';
							foreach($result -> get_result()	as $lol){
								
								echo '<tr><td>';
								echo '<form method="post" action="resources.php?gameid='.$_GET['gameid'].'">';
								echo '<div class="input-group"><button class="btn btn-secondary" disabled>ID:'.$lol['idResources_values'].'</button><textarea name="resourcevalue" maxlength="45" style="background-color: #474747; color:#ffffff;" class="form-control" rows="1" placeholder="Resource Name">'.$lol['value'].'</textarea>';
								echo '<input class="form-control" type="number" name="order" placeholder="Order" value="'.$lol['order'].'" step="any">';
								echo '
	  <input name="gamePass" type="password" maxlength="16" style="background-color: #474747; color:#999999;" class="form-control" rows="1" placeholder="Game Password">
	  <div class="input-group-append" id="button-addon4">
	  <input type="hidden" name="idresourcevalue" value="'.$lol['idResources_values'].'">
	  <input type="hidden" name="resource" value="'.$_POST['resource'].'">
	  <input type="hidden" name="idresource" value="'.$_POST['idresource'].'">
		<button type="submit" name="action" value="EditUpdate" class="btn btn-primary">Update</button>
		<button type="submit" name="action" value="EditDelete" class="btn btn-danger" ';
							if(1):?>
							onclick="return confirm('Are you sure you want to delete this resource?');"
							<?php
							endif;
							echo ' >Delete</button>
	  </div>
	</div>';
								echo '</td>';
								echo '</form>';
								echo '</tr>';
							
							}
							
							echo '<tr><td>';
								echo '<form method="post" action="resources.php?gameid='.$_GET['gameid'].'">';
								echo '<div class="input-group"><textarea name="resourcevalue" maxlength="45" style="background-color: #474747; color:#ffffff;" class="form-control" rows="1" placeholder="Resource Name" autofocus></textarea>';
								echo '<input class="form-control" type="number" name="order" placeholder="Order" value="" step="any">';
								echo '
	  <input name="gamePass" type="password" maxlength="16" style="background-color: #474747; color:#999999;" class="form-control" rows="1" placeholder="Game Password">
	  <div class="input-group-append" id="button-addon4">
		<input type="hidden" name="idresource" value="'.$_POST['idresource'].'">
		<input type="hidden" name="resource" value="'.$_POST['resource'].'">
		<button type="submit" name="action" value="EditAdd" class="btn btn-primary">Add</button>
	  </div>
	</div>';
								echo '</td>';
								echo '</form>';
								echo '</tr>';
							
							echo '</table><br>';
						}else{
							$query = "SELECT `idgame_resources`, `text_name`, `type`, `primaryORsecundary` FROM `game_resources` WHERE `game_idgame` = ? ORDER BY primaryORsecundary DESC, text_name";
							$result = $conn -> prepare($query);
							$result -> bind_param("i",$_GET['gameid']);
							$result -> execute();
							echo '<table id="myTable" class="table table-hover align-middle caption-top combosuki-main-reversed text-white">';
							echo '<tr>';
							echo '<th>Resource</th';
							echo '</tr>';
							foreach($result -> get_result()	as $lol){
								
								echo '<tr><td>';
								echo '<form method="post" action="resources.php?gameid='.$_GET['gameid'].'">';
								echo '<div class="input-group"><textarea name="resource" maxlength="45" style="background-color: #474747; color:#ffffff;" class="form-control" rows="1" placeholder="Resource Name">'.$lol['text_name'].'</textarea>';
								echo '<select name="type" class="custom-select">
								<option value="1" ';
								if($lol['type'] == 1)echo 'selected';
								echo '>List</option>
								<option value="2" ';
								if($lol['type'] == 2)echo 'selected';
								echo '>Number</option>
								<option value="3" ';
								if($lol['type'] == 3)echo 'selected';
								echo '>Duplicated</option>
								</select>';
								echo '<select name="primaryORsecundary" class="custom-select">
								<option value="1" ';
								if($lol['primaryORsecundary'] == 1)echo 'selected';
								echo '>Primary</option>
								<option value="0" ';
								if($lol['primaryORsecundary'] != 1)echo 'selected';
								echo '>Secondary</option>
								</select>';
								echo '
	  <input name="gamePass" type="password" maxlength="16" style="background-color: #474747; color:#999999;" class="form-control" rows="1" placeholder="Game Password">
	  <div class="input-group-append" id="button-addon4">
	  <input type="hidden" name="idresource" value="'.$lol['idgame_resources'].'">
		<button type="submit" name="action" value="Update" class="btn btn-primary">Update</button>
		<button type="submit" name="action" value="Edit" class="btn btn-secondary">Edit</button>
		<button type="submit" name="action" value="Delete" class="btn btn-danger" ';
							if(1):?>
							onclick="return confirm('Are you sure you want to delete this resource?');"
							<?php
							endif;
							echo ' >Delete</button>
	  </div>
	</div>';
								echo '</td>';
								echo '</form>';
								echo '</tr>';
							
							}
							
							echo '<tr><td>';
								echo '<form method="post" action="resources.php?gameid='.$_GET['gameid'].'">';
								echo '<div class="input-group"><textarea name="resource" maxlength="45" style="background-color: #474747; color:#ffffff;" class="form-control" rows="1" placeholder="Resource Name" autofocus></textarea>';
								echo '<select name="type" class="custom-select">
								<option value="1"';
								echo '>List</option>
								<option value="2"';
								echo '>Number</option>
								<option value="3">Duplicated</option>
								</select>';
								echo '<select name="primaryorsecundary" class="custom-select">
								<option value="1"';
								echo '>Primary</option>
								<option value="0"';
								echo '>Secondary</option>
								</select>';
								echo '
	  <input name="gamePass" type="password" maxlength="16" style="background-color: #474747; color:#999999;" class="form-control" rows="1" placeholder="Game Password">
	  <div class="input-group-append" id="button-addon4">
		<button type="submit" name="action" value="Add" class="btn btn-primary">Add</button>
	  </div>
	</div>';
								echo '</td>';
								echo '</form>';
								echo '</tr>';
							
							echo '</table><br>';
						}
						
						edit_controls($_GET['gameid']);
						mysqli_close($conn);
					?>
					<p>Any game needs at least ONE primary resource for it to work properly. (That includes searching/viewing submissions).<br>
					Primary resources are the ones that always have to be listed along submissions, secundary are more specific resources that do not have to be on every entry.<br>
					Currently there are three types of resources:<br>
					1: List<br>
					> A simple list of options, it should have at least one option to work properly.<br>
					2: Number<br>
					> In its options, number resources should have its max value.<br>
					3: Duplicated<br>
					> Created for use on games that have assists, duplicated resources will appear twice and it allows for searches to ignore the order of the assists.<br>
					For example: Before a combo with assist A + B was considered different from combos with assist B + A. But with duplicated resource type A + B = B + A.<br>
					<br>
					Resources are what are used to do searches, so that is why you need at least one primary resource for the search to work.
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
		<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js"></script>
</html>

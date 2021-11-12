<?php
function headerHTML(){
	global $URLDepth;
	?>
		<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-+0n0xVW2eSR5OomGNYDnhzAbDsOXxcvSN1TPprVMTNDbiYZCxYbOOl7+AMvyTG2x" crossorigin="anonymous">
		<link rel="icon" type="image/png" sizes="32x32" href="<?php echo $URLDepth; ?>img/favicon-32x32.png">
		<link rel="icon" type="image/png" sizes="96x96" href="<?php echo $URLDepth; ?>img/favicon-96x96.png">
		<link rel="icon" type="image/png" sizes="16x16" href="<?php echo $URLDepth; ?>img/favicon-16x16.png">
		<meta name="msapplication-TileImage" content="<?php echo $URLDepth; ?>img/ms-icon-144x144.png">
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=yes">
		<?php
}

function openGraphProtocol(){
	?>
	
		<meta property="og:title" content="Combo好き" />
		<meta property="og:type" content="website" />
		<meta property="og:image" content="http://combosuki.com/img/combosuki.png" />
		<meta property="og:url" content="http://combosuki.com/index.php" />
		<meta property="og:description" 
		content="Community-fueled searchable environment that shares and perfects combos." />
		<meta name="theme-color" content="#d94040" />

		<meta name="description" content="Community-fueled searchable environment that shares and perfects combos.">
		
	<?php
}

function listsTable($lists){
	global $URLDepth;
	echo '<table id="myTable" class="table table-hover align-middle caption-top combosuki-main-reversed text-white">';
	echo '<tr>';
	echo '<th>List Name</th>';
	echo '</tr>';
	while($list = $lists->fetch_array(MYSQLI_ASSOC)){
		if($list['list_name'] != ''){
			echo '<tr><td>';
			print_gameglyph($list['game_idgame']);
			echo '<text href="'.$URLDepth.'list/show.php?id='.$list['idlist'].'">'.$list['list_name'].'</text>';
			print_listglyph($list['type']);
			echo '	<a href="'.$URLDepth.'list/show.php?id='.$list['idlist'].'">
				<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-box-arrow-up-right" viewBox="0 0 16 16">
				  <path fill-rule="evenodd" d="M8.636 3.5a.5.5 0 0 0-.5-.5H1.5A1.5 1.5 0 0 0 0 4.5v10A1.5 1.5 0 0 0 1.5 16h10a1.5 1.5 0 0 0 1.5-1.5V7.864a.5.5 0 0 0-1 0V14.5a.5.5 0 0 1-.5.5h-10a.5.5 0 0 1-.5-.5v-10a.5.5 0 0 1 .5-.5h6.636a.5.5 0 0 0 .5-.5z"/>
				  <path fill-rule="evenodd" d="M16 .5a.5.5 0 0 0-.5-.5h-5a.5.5 0 0 0 0 1h3.793L6.146 9.146a.5.5 0 1 0 .708.708L15 1.707V5.5a.5.5 0 0 0 1 0v-5z"/>
				</svg>
			</a>';
			echo '</tr></td>';
		}
	}
}

function jumbotronCombosukiLogo($imageHeight){
	global $URLDepth;
	?>
	<div class="jumbotron jumbotron-fluid">
		<div class="container">
			<a href="<?php echo $URLDepth; ?>index.php">
				<img src="<?php echo $URLDepth; ?>img/combosuki.png" style="margin-top: 20px;" height="<?php echo $imageHeight; ?>" >
			</a>
		</div>
	</div><!--<img src="img/selo.png" style="max-height:200; margin-left:200px;">-->
	<?php
}

function listHeader($result){
	global $URLDepth;
	while($list = $result->fetch_array(MYSQLI_ASSOC)){
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
			<form method="post" action="'.$URLDepth.'list/show.php?id='.$_GET['id'].'">
				<input type="hidden" name="submission_type" value="2">
				<input type="hidden" name="idlist" value="'.$_GET['id'].'">
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
				print_gameglyph($list['game_idgame']);
				echo $list['name'].' list';
				copyLinktoclipboard('https://combosuki.com/list.php?listid='.$_GET['id']);
			echo '</div>';
		echo '</div>';
	}
}

function listContent($result){
	global $URLDepth, $conn;
	$title = '';
	while($data = $result->fetch_array(MYSQLI_ASSOC)){
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
				<form method="post" action="'.$URLDepth.'list/show.php?id='.$_GET['id'].'">
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
		echo		'<text data-toggle="tooltip" data-placement="bottom" title="'.$data['comments'].'" href="'.$URLDepth.'game/combo.php?idcombo='.$data['idcombo'].'&listid='.$_GET['id'].'">'.$data['combo'].'</text>';
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
}

?>
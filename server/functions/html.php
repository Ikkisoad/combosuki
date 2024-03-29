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

function openGraphProtocol($title = 'Combo好き', $description = 'Community-fueled searchable environment that shares and perfects combos.'){
	?>
		<meta property="og:title" content="<?php echo $title; ?>" />
		<meta property="og:type" content="website" />
		<meta property="og:image" content="http://combosuki.com/img/combosuki.png" />
		<meta property="og:url" content="http://combosuki.com/index.php" />
		<meta property="og:description" 
		content="<?php echo $description;?>" />
		<meta name="theme-color" content="#C62114" />
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
			<h3 class="mt-3">Add page</h3>
			<form method="post" action="'.$URLDepth.'list/show.php?id='.$_GET['id'].'">
				<input type="hidden" name="submission_type" value="4">
				<input type="hidden" name="idlist" value="'.$_GET['id'].'">
				<div class="input-group">
					<textarea name="pageTitle" maxlength="255" style="background-color: #474747; color:#ffffff;" class="form-control" rows="1" placeholder="Page Title"></textarea>
					<textarea name="pageDescription" maxlength="1000" style="background-color: #474747; color:#ffffff;" class="form-control" rows="1" placeholder="Page Description"></textarea>
					<input class="form-control" type="number" name="pageOrder" placeholder="Order" step="any">
					<input name="listPass" type="password" maxlength="16" style="background-color: #474747; color:#999999;" class="form-control" rows="1" placeholder="List Password">
					<div class="input-group-append" id="button-addon4">
						<button type="submit" name="action" value="AddPage" class="btn btn-primary">Add</button>
					</div>
				</div>
			</form>
		</div>';
		echo '<div class="row">';
			echo '<div class="col">';
				print_gameglyph($list['game_idgame']);
				echo $list['name'].' list';
				copyLinktoclipboard('https://combosuki.com/list/show.php?id='.$_GET['id']);
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


function listContentDetailed($result, $idPage = 0, $idList = 0){
	global $URLDepth, $conn;
	$title = '';
	$comboID ='';
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
						<input class="form-control" type="number" name="order" placeholder="Order" value="'.$data['order'].'" step="any">';
						listPageDropdown($idList, $idPage);
						echo '<input name="listPass" type="password" maxlength="16" style="background-color: #474747; color:#999999;" class="form-control" rows="1" placeholder="List Password">
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
		if($comboID != $data['idcombo']){
			$comboID = $data['idcombo'];
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
			echo '</td><td>';
			echo $data['text_name']. ': ';
			echo $data['number_value'] === NULL ? $data['value'] : $data['number_value'];
			echo '<br>';
		}else{
			echo $data['text_name']. ': ';
			echo $data['number_value'] === NULL ? $data['value'] : $data['number_value'];
			echo '<br>';
		}
	}
	echo '</table>';
}

function listPageDropdown($idList = 0, $idPage = 0){
	?>
	<select name="idPage" class="form-select">
	<?php
	foreach(getListPages($idList) as $page){
		?>
		<option value="<?php echo $page['idListPage'];?>"
		<?php if($idPage == $page['idListPage'])echo 'selected';?>>
		<?php echo $page['Title'];?>
		</option>
		<?php
	}
	?>
	</select>
	<?php
}

function gameCards($hasCards = 1, $games = 12, $completeGame = 1){
	echo'<div class="row">';
	foreach(getGames($games, $completeGame) as $gameid){
		echo '<div class="col my-3">
			<div class="card text-center w-100 p-3 h-100 combosuki-main-reversed">';
			if($hasCards){
			echo '
				<div class="card text-center w-100 p-3 h-100" style="background-color:#';
				echo $gameid['New'] ? dechex((int) hexdec($_COOKIE['color']) + 100) : $_COOKIE['color'];					
				echo ';">
				<span style=" display: inline-block;
				height: 30%;
				vertical-align: middle;"></span>
					<a href="game/index.php?gameid='.$gameid['idgame'].'"><img class="rounded mx-auto d-block" style="
					max-height:200px;
					max-width:200px;
					height:auto;
					width:auto;
					align: center;
					vertical-align: middle;
					display: block;
					" src="'.$gameid['image'].'" class="card-img-top rounded mx-auto d-block" alt="Responsive image"></img></a></div>';
			}	
					echo '<div class="card-body">';
					echo '<a class="card-title text ';
					echo !$hasCards ? 'text-nowrap ' : '';
					echo 'text-white " ';
					echo 'href="game/index.php?gameid='.$gameid['idgame'].'"';
					echo '">'.$gameid['name'].'</a>';
					echo $gameid['New'] ? '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-stars" viewBox="0 0 16 16">
  <path d="M7.657 6.247c.11-.33.576-.33.686 0l.645 1.937a2.89 2.89 0 0 0 1.829 1.828l1.936.645c.33.11.33.576 0 .686l-1.937.645a2.89 2.89 0 0 0-1.828 1.829l-.645 1.936a.361.361 0 0 1-.686 0l-.645-1.937a2.89 2.89 0 0 0-1.828-1.828l-1.937-.645a.361.361 0 0 1 0-.686l1.937-.645a2.89 2.89 0 0 0 1.828-1.828l.645-1.937zM3.794 1.148a.217.217 0 0 1 .412 0l.387 1.162c.173.518.579.924 1.097 1.097l1.162.387a.217.217 0 0 1 0 .412l-1.162.387A1.734 1.734 0 0 0 4.593 5.69l-.387 1.162a.217.217 0 0 1-.412 0L3.407 5.69A1.734 1.734 0 0 0 2.31 4.593l-1.162-.387a.217.217 0 0 1 0-.412l1.162-.387A1.734 1.734 0 0 0 3.407 2.31l.387-1.162zM10.863.099a.145.145 0 0 1 .274 0l.258.774c.115.346.386.617.732.732l.774.258a.145.145 0 0 1 0 .274l-.774.258a1.156 1.156 0 0 0-.732.732l-.258.774a.145.145 0 0 1-.274 0l-.258-.774a1.156 1.156 0 0 0-.732-.732L9.1 2.137a.145.145 0 0 1 0-.274l.774-.258c.346-.115.617-.386.732-.732L10.863.1z"/>
</svg>' : '';
					echo '
				</div>
			</div>
		</div>';
	}
	echo '</div>';	
}

function editButtons(){
	global $URLDepth, $conn;
	?>
	<table id="myTable" class="table table-hover align-middle caption-top combosuki-main-reversed text-white">
		<tr>
			<th>Button</th>
		</tr>
		<?php foreach(getButtonsBy_gameID($_GET['gameid']) as $button){ ?>
			<tr>
				<td>
					<form method="post" action="<?php echo $URLDepth.'game/edit/buttons.php?gameid='.$_GET['gameid'];?>">
						<div class="input-group"><textarea name="name" maxlength="45" style="background-color: #474747; color:#ffffff;" class="form-control" rows="1" placeholder="Character Name"><?php echo $button['name']; ?></textarea>
						<?php $directory = $URLDepth . "img/buttons/";
						$images = glob($directory . "*.png");?>
						<select name="png" class="custom-select" onchange="setImage(this,<?php echo $button['idbutton']?>);">
						<?php foreach($images as $image){
							$image = str_replace($directory, "", $image);
							$image = str_replace(".png", "", $image);?>
							<option value="<?php echo $image;?>"
								<?php if($image == $button['png']){ ?> selected <?php } ?>
								>
								<?php echo $image; ?>
							</option>
						<?php } ?>
						</select>
						<img src="<?php echo $URLDepth.'img/buttons/'.$button['png']; ?>.png" height=35 name="image-<?php echo $button['idbutton']; ?>"></img>
						<input class="form-control" type="number" name="order" placeholder="Order" value="<?php echo $button['order']; ?>" step="any">
						<input name="gamePass" type="password" maxlength="16" style="background-color: #474747; color:#999999;" class="form-control" rows="1" placeholder="Game Password">
						<div class="input-group-append" id="button-addon4">
							<input type="hidden" name="idbutton" value="<?php echo $button['idbutton']; ?>">
							<button type="submit" name="action" value="Update" class="btn btn-primary">Update</button>
							<button type="submit" name="action" value="Delete" class="btn btn-danger" 
							onclick="return confirm('Are you sure you want to delete this button?');"
							>Delete</button>
						</div>
						</div>
					</form>
				</td>
			</tr>
		<?php } ?>
	</table>
	<?php
}

function addButton(){
	global $conn, $URLDepth;?>
	<table id="myTable" class="table table-hover align-middle caption-top combosuki-main-reversed text-white">
		<tr>
			<td>
				<form method="post" action="<?php echo $URLDepth.'game/edit/buttons.php?gameid='.$_GET['gameid'];?>">
					<div class="input-group">
						<textarea name="name" maxlength="45" style="background-color: #474747; color:#ffffff;" class="form-control" rows="1" placeholder="Button Name" autofocus></textarea>
						<?php $directory = $URLDepth . "img/buttons/";
						$images = glob($directory . "*.png");?>
						<select name="png" class="custom-select" onchange="setImage(this,0);">
							<?php foreach($images as $image){
								$image = str_replace($directory, "", $image);
								$image = str_replace(".png", "", $image);?>
								<option value="<?php echo $image; ?>">
									<?php echo $image; ?>
								</option>
							<?php } ?>
						</select>
						<img src="<?php echo $URLDepth.'img/buttons/+.png';?>" height="35" name="image-0" />
						<input class="form-control" type="number" name="order" placeholder="Order" value="" step="any">
						<input name="gamePass" type="password" maxlength="16" style="background-color: #474747; color:#999999;" class="form-control" rows="1" placeholder="Game Password">
						<div class="input-group-append" id="button-addon4">
							<button type="submit" name="action" value="Add" class="btn btn-primary">Add</button>
						</div>
					</div>
				</form>
			</td>
		</tr>
	</table>
	<?php
}

function listPages($idList = 0, $idPage = 0){
	global $conn, $URLDepth;
	$description = '';
	?>
		<ul class="nav nav-tabs combosuki-main-reversed">
		<li class="nav-item">
			<a class="nav-link" aria-current="page" href="show.php?id=<?php echo $idList; ?>&page=0">First Page</a>
		</li>
	<?php foreach(getListPages($idList) as $page){ ?>
		<li class="nav-item">
			<a class="nav-link" aria-current="page" href="show.php?id=<?php echo $idList; ?>&page=
			<?php 
			echo $page['idListPage'];?>">
			<?php
			echo $page['Title'];?></a>
		</li>
		
	<?php
			if($idPage != 0 && $idPage == $page['idListPage']){
				$description = $page['Description'];
			}
		}
		?>
		</ul>
		<?php
		echo $description;
}

function listCategories($idList = 0,$idPage = 0){
	echo '
	<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block sidebar show collapse combosuki-main-reversed">
		<ul class="list-unstyled mb-0 py-3 pt-md-1">
			<li class="list-group-item bg-transparent "><a href="#edit"><span>Edit</span></a></li>';
			foreach(getListPageCategories($idList, $idPage) as $category){
				echo '<li class="list-group-item bg-transparent "><a href="#'.$category['title'].'"><span>'.$category['title'].'</span></a></li>';
			}
			echo '
		</ul>
	</nav>';
}

function editListForm($idPage = 0){
	global $conn,$URLDepth;
	echo '
	<div class="row combosuki-main-reversed gap-1">
		<h3 class="mt-3" id="edit">Edit List</h3>
		<small>Use , to Add or Remove multiple entries from the list. (Eg.: 777,26 would add or remove entries 777 and 26 from the list.)</small>

		<form class="form-inline gap-3" method="post" action="'.$URLDepth.'list/show.php?id='.$_GET['id'].'&page='.$idPage.'">
			<input type="hidden" name="submission_type" value="2">';

			echo '
			<div class="row">
				<div class="col">
					<input placeholder="Entry ID" style="background-color: #474747; color:#999999; min-width:150px;" name="comboid" class="form-control" rows="1"></input>
				</div>

				<div class="col">
					<input placeholder="List Password" name="listPass" type="password" maxlength="16" style="background-color: #474747; color:#999999; min-width:150px;" class="form-control" rows="1"></input>
				</div>
				<div class="col">
					<input placeholder="Category" name="comment" maxlength="45" style="background-color: #474747; color:#999999; min-width:150px;" class="form-control" rows="1"></input>
				</div>
			</div>';
			echo '
			<div class="form-group mb-2">
				<select name="categoryid" class="form-select">';
					echo '
					<option value="0">New Category</option>';
					foreach(getListPageCategories($_GET['id'], $idPage, 1) as $category){
						echo '<option value="'.$category['idlist_category'].'" ';
						echo '>'.$category['title'].'</option>';
					}
				echo '</select>
			</div>'; 

			echo '
			<div class="row align-center">
				<div class="col">
					<button type="submit" name="action" value="Submit" class="btn btn-primary btn-block">Add Entry</button>
					<button type="submit" name="action" value="Delete" class="btn btn-danger btn-block">Remove Entry</button>
				</div>
			</div>';
			echo '
		</form>
	</div>';
	
}
?>
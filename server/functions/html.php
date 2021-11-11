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
	echo '<table id="myTable" class="table table-hover align-middle caption-top combosuki-main-reversed text-white">';
	echo '<tr>';
	echo '<th>List Name</th>';
	echo '</tr>';
	while($list = mysqli_fetch_assoc($lists)){
		if($list['list_name'] != ''){
			echo '<tr><td>';
			print_gameglyph($list['game_idgame']);
			echo '<text href="list.php?listid='.$list['idlist'].'">'.$list['list_name'].'</text>';
			print_listglyph($list['type']);
			echo '	<a href="list.php?listid='.$list['idlist'].'">
				<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-box-arrow-up-right" viewBox="0 0 16 16">
				  <path fill-rule="evenodd" d="M8.636 3.5a.5.5 0 0 0-.5-.5H1.5A1.5 1.5 0 0 0 0 4.5v10A1.5 1.5 0 0 0 1.5 16h10a1.5 1.5 0 0 0 1.5-1.5V7.864a.5.5 0 0 0-1 0V14.5a.5.5 0 0 1-.5.5h-10a.5.5 0 0 1-.5-.5v-10a.5.5 0 0 1 .5-.5h6.636a.5.5 0 0 0 .5-.5z"/>
				  <path fill-rule="evenodd" d="M16 .5a.5.5 0 0 0-.5-.5h-5a.5.5 0 0 0 0 1h3.793L6.146 9.146a.5.5 0 1 0 .708.708L15 1.707V5.5a.5.5 0 0 0 1 0v-5z"/>
				</svg>
			</a>';
			echo '</tr></td>';
		}
	}
}
?>
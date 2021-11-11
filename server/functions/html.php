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
?>
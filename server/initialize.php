<?php

	$URLDepth = $URLDepth ?? '';
	require_once $URLDepth."server/connection.php";
	
	require_once $URLDepth."server/functions.php";
	require_once $URLDepth."server/functions/html.php";
	require_once $URLDepth."server/functions/javascript.php";
	require_once $URLDepth."server/functions/sql.php";
	
	strip_POSTtags();
	strip_GETtags();
	set_cookies();
?>
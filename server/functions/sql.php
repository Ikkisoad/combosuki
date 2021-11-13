<?php

function getListBy_GameID_Name($GameID = 0,$Name = ''){
	global $conn;
	$query = "SELECT `idlist`, `list_name`, `type`, `list`.`game_idgame`
	FROM `list` 
	WHERE `list_name` LIKE ? 
	AND (`game_idgame` = ? OR IFNULL(?,0) = 0) 
	AND `list`.`type` != 0 
	ORDER BY `type` DESC, `list_name` 
	LIMIT 0,50";
	$result = $conn->prepare($query);
	$Name = '%'.$Name.'%';
	$result->bind_param("sii",$Name, $GameID, $GameID);
	$result->execute();
	return $result->get_result();
}

function getListBy_ID($ID = 0){
	global $conn;
	$query = "SELECT list_name, name, type, game_idgame FROM `list` join game ON list.game_idgame = game.idgame where list.idlist = ?";
	$result = $conn->prepare($query);
	$result->bind_param("i",$ID);
	$result->execute();
	return $result->get_result();
}

function getListContentBy_ID($ID = 0){
	global $conn;
	$query = "SELECT `combo_listing`.`idcombo`, `combo`.`damage`, `character`.`Name`, `combo`.`combo`, `combo`.`comments`,`combo`.`video`,`combo`.`type`, `list_category`.`title`, `list_category`.`idlist_category`,`list_category`.`order` 
	FROM `combo_listing` 
	INNER JOIN `combo` ON `combo`.`idcombo` = `combo_listing`.`idcombo` 
	LEFT JOIN `character` ON `character`.`idcharacter` = `combo`.`character_idcharacter` 
	LEFT JOIN `list_category` ON `list_category`.`idlist_category` = `combo_listing`.`list_category_idlist_category`
	WHERE `idlist` = ? ORDER BY `list_category`.`order`, `list_category`.`title`,`combo`.`damage` DESC;";
	$result = $conn->prepare($query);
	$result->bind_param("i",$ID);
	$result->execute();
	return $result->get_result();
}

function getListContentDetailedBy_ID($ID = 0){
	global $conn;
	$query = "SELECT `combo_listing`.`idcombo`, `combo`.`damage`, `character`.`Name`, `combo`.`combo`, `combo`.`comments`,`combo`.`video`,`combo`.`type`, `list_category`.`title`, `list_category`.`idlist_category`,`list_category`.`order`,game_resources.text_name,resources_values.value,number_value 
    FROM `combo_listing` 
    INNER JOIN `combo` ON `combo`.`idcombo` = `combo_listing`.`idcombo` 
    LEFT JOIN `character` ON `character`.`idcharacter` = `combo`.`character_idcharacter` 
    LEFT JOIN `list_category` ON `list_category`.`idlist_category` = `combo_listing`.`list_category_idlist_category`
    INNER JOIN `resources` ON `combo`.`idcombo` = resources.combo_idcombo
    INNER JOIN resources_values ON resources.Resources_values_idResources_values = resources_values.idResources_values
    INNER JOIN game_resources ON game_resources.idgame_resources = game_resources_idgame_resources
	WHERE `idlist` = ? ORDER BY `list_category`.`order`, `list_category`.`title`,`combo`.`damage` DESC";
	$result = $conn->prepare($query);
	$result->bind_param("i",$ID);
	$result->execute();
	return $result->get_result();
}

function getFirst50Lists(){
	global $conn;
	$query = "SELECT `idlist`, `list_name`, `type`,`game_idgame` FROM `list` ORDER BY `idlist` DESC LIMIT 0,50";
	return mysqli_query($conn,$query);
}

?>
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
	$query = "SELECT `combo_listing`.`idcombo`, `combo`.`damage`, `character`.`Name`, `combo`.`combo`, `combo`.`comments`,`combo`.`video`,`combo`.`type`, IFNULL(`list_category`.`title`,'No Category') as title, `list_category`.`idlist_category`,`list_category`.`order`,game_resources.text_name,resources_values.value,number_value 
    FROM `combo_listing` 
    INNER JOIN `combo` ON `combo`.`idcombo` = `combo_listing`.`idcombo` 
    LEFT JOIN `character` ON `character`.`idcharacter` = `combo`.`character_idcharacter` 
    LEFT JOIN `list_category` ON `list_category`.`idlist_category` = `combo_listing`.`list_category_idlist_category`
    INNER JOIN `resources` ON `combo`.`idcombo` = resources.combo_idcombo
    INNER JOIN resources_values ON resources.Resources_values_idResources_values = resources_values.idResources_values
    INNER JOIN game_resources ON game_resources.idgame_resources = game_resources_idgame_resources
	WHERE `idlist` = ? ORDER BY `list_category`.`order`, `list_category`.`title`,`combo`.`damage` DESC, game_resources.idgame_resources,primaryORsecundary DESC, game_resources.text_name";
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

function getGames($games = 0, $completeGame = 1){
	global $conn;
	$query = "SELECT `subQuery`.`idgame`, `subQuery`.`name`, `subQuery`.`image`, (IF(Latest.Latest = EXTRACT(YEAR_MONTH FROM UTC_DATE()),1,0)) as New FROM (SELECT `game`.`idgame`, `game`.`name`, `game`.`image`, `game`.`complete` FROM `combo`
	INNER JOIN `character` ON `character`.`idcharacter` = `combo`.`character_idcharacter`
	RIGHT JOIN `game` ON `game`.`idgame` = `character`.`game_idgame` ";
	$query .= $completeGame ? "WHERE `game`.`complete` > 0 " : "";
	$query .= "GROUP BY `game`.`idgame`
	ORDER BY COUNT(`combo`.`character_idcharacter`) DESC ";
	$query .= $games > 0? "LIMIT " . $games : "";
	$query .= ") as subQuery
    INNER JOIN (
        SELECT EXTRACT(YEAR_MONTH FROM MAX(combo.submited)) as Latest, game.idgame as LIDGame FROM combo 
        INNER JOIN `character` ON combo.character_idcharacter = `character`.`idcharacter`
        RIGHT JOIN game ON game.idgame = `character`.game_idgame
        GROUP BY game.idgame
    ) as Latest ON Latest.LIDGame = subQuery.idGame
    ORDER BY subQuery.`name`;";
	$result = $conn -> prepare($query);
	$result -> execute();
	return $result->get_result();
}

function getComboDetailedBy_ID($comboid = 0){
	global $conn;
	$query = "SELECT `idcombo`,`combo`,`damage`,`value`,`idResources_values`,`number_value`,`character`.`idcharacter`,`character`.`Name`, `video`, `game_resources`.`text_name`,`game_resources`.`type`, `combo`.`type` as listingtype, `combo`.`comments`,`game_resources`.`primaryORsecundary`, `character`.`game_idgame`, `resources_values`.`order`, `combo`.`submited`, `combo`.`patch`
	FROM `combo` 
	INNER JOIN `resources` ON `combo`.`idcombo` = `resources`.`combo_idcombo` 
	LEFT JOIN `resources_values` ON `resources_values`.`idResources_values` = `resources`.`Resources_values_idResources_values` 
	LEFT JOIN `character` ON `character`.`idcharacter` = `combo`.`character_idcharacter` 
	LEFT JOIN `game_resources` ON `game_resources`.`idgame_resources` = `resources_values`.`game_resources_idgame_resources` 
	WHERE `idcombo` = ? ";
	$query = $query . "ORDER BY  `game_resources`.`primaryORsecundary` DESC, `idcombo`, `text_name`,`resources`.`idResources`;";
	$result = $conn -> prepare($query);
	$result -> bind_param("i",$comboid);
	$result -> execute();
	return $result->get_result();
}

?>
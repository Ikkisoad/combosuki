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

function getListContentDetailedBy_ID($ID = 0, $idPage = 0){
	global $conn;
	$query = "SELECT `combo_listing`.`idcombo`, `combo`.`damage`, `character`.`Name`, `combo`.`combo`, `combo`.`comments`,`combo`.`video`,`combo`.`type`, IFNULL(`list_category`.`title`,'No Category') as title, `list_category`.`idlist_category`,`list_category`.`order`,game_resources.text_name,resources_values.value,number_value 
    FROM `combo_listing` 
    INNER JOIN `combo` ON `combo`.`idcombo` = `combo_listing`.`idcombo` 
    LEFT JOIN `character` ON `character`.`idcharacter` = `combo`.`character_idcharacter` 
    LEFT JOIN `list_category` ON `list_category`.`idlist_category` = `combo_listing`.`list_category_idlist_category`
	LEFT JOIN `list_page` ON `list_page`.`idListPage` = `list_category`.`idPage`
    INNER JOIN `resources` ON `combo`.`idcombo` = resources.combo_idcombo
    INNER JOIN resources_values ON resources.Resources_values_idResources_values = resources_values.idResources_values
    INNER JOIN game_resources ON game_resources.idgame_resources = game_resources_idgame_resources
	WHERE `combo_listing`.`idlist` = ? AND (IFNULL(`idPage`,0) = ? OR `idListPage` = ?)
	ORDER BY `list_category`.`order`, `list_category`.`title`,`combo`.`damage` DESC, game_resources.idgame_resources,primaryORsecundary DESC, game_resources.text_name";
	$result = $conn->prepare($query);
	$result->bind_param("iii",$ID,$idPage,$idPage);
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

function insertButton($name,$png,$gameid,$order){
	global $conn;
	$query = "INSERT INTO `button`(`idbutton`, `name`, `png`, `game_idgame`, `order`) VALUES (NULL, ?, ?, ?, ?)";
	$result = $conn -> prepare($query);
	$result -> bind_param("ssii", $name, $png, $gameid, $order);
	$result -> execute();
}

function updateButton($name,$png,$order,$gameid,$idbutton){
	global $conn;
	$query = "UPDATE `button` SET `name`=?,`png`=?,`order`=? WHERE `game_idgame` = ? AND `idbutton` = ?";
	$result = $conn -> prepare($query);
	$result -> bind_param("ssiii", $name,$png,$order,$gameid,$idbutton);
	$result -> execute();
}

function deleteButton($idButton,$gameID){
	global $conn;
	$query = "DELETE FROM `button` WHERE `idbutton` = ? AND `game_idgame` = ?";
	$result = $conn -> prepare($query);
	$result -> bind_param("ii", $idButton,$gameID);
	$result -> execute();	
}

function insertDefaultButtons($gameid){
	insertButton('1','1',$gameid,0);
	insertButton('2','2',$gameid,0);
	insertButton('3','3',$gameid,0);
	insertButton('4','4',$gameid,0);
	insertButton('5','5',$gameid,0);
	insertButton('6','6',$gameid,0);
	insertButton('7','7',$gameid,0);
	insertButton('8','8',$gameid,0);
	insertButton('9','9',$gameid,0);
	insertButton('214','214',$gameid,0);
	insertButton('236','236',$gameid,0);
	insertButton('A','A',$gameid,0);
	insertButton('B','B',$gameid,0);
	insertButton('C','C',$gameid,0);
	insertButton('j','jump',$gameid,0);
	insertButton('>','gap',$gameid,0);
}

function insertGame($name,$image,$password){
	global $conn;
	$query = "INSERT INTO `game`(`idgame`, `name`, `complete`, `image`, `globalPass`, `modPass`) VALUES (NULL, ?,NULL,?,?,?)";
	$result = $conn -> prepare($query);
	$moderationPassword = password_hash($password,PASSWORD_DEFAULT);
	$result -> bind_param("ssss", $name, $image, $password, $moderationPassword);
	$result -> execute();
	return mysqli_insert_id($conn);
}

function insertCharacter($name, $gameid){
	global $conn;
	$query = "INSERT INTO `character`(`idcharacter`, `Name`, `game_idgame`) VALUES (NULL,?,?)";
	$result = $conn -> prepare($query);
	$result -> bind_param("si", $name, $gameid);
	$result -> execute();
}

function insertDefaultCharacter($gameid){
	insertCharacter('Combo Chan', $gameid);
}

function insertResource($gameid,$name,$type,$primary){
	global $conn;
	$query = "INSERT INTO `game_resources`(`idgame_resources`, `game_idgame`, `text_name`, `type`, `primaryORsecundary`) VALUES (NULL,?,?,?,?)";
	$result = $conn -> prepare($query);
	$result -> bind_param("isii", $gameid, $name,$type,$primary);
	$result -> execute();
}

function insertResourceValue($value,$order = 0,$idresource){
	global $conn;
	$query = "INSERT INTO `resources_values`(`idResources_values`, `value`, `order`, `game_resources_idgame_resources`) VALUES (NULL,?,?,?)";
	$result = $conn -> prepare($query);
	$result -> bind_param("sii", $value,$order,$idresource);
	$result -> execute();
}

function insertDefaultResource($gameid){
	global $conn;
	insertResource($gameid,'Where?',1,1);
	$resourceid = mysqli_insert_id($conn);
	insertResourceValue('Midscreen',0,$resourceid);
	insertResourceValue('Corner',1,$resourceid);
}

function insertEntry($entry,$gameid,$order = 0){
	global $conn;
	$query = "INSERT INTO `game_entry`(`entryid`, `title`, `gameid`, `order`) VALUES (NULL, ?, ?, ?)";
	$result = $conn -> prepare($query);
	$result -> bind_param("sii", $entry, $gameid, $order);
	$result -> execute();
}

function insertDefaultEntry($gameid){
	insertEntry('Combo',$gameid,-1);
	insertEntry('Okizeme',$gameid);
	insertEntry('Mix Up',$gameid);
}

function getButtonsBy_gameID($gameID){
	global $conn;
	$query = "SELECT `idbutton`, `name`, `png`, `order` FROM `button` WHERE `game_idgame` = ? ORDER BY `order`, name;";
	$result = $conn -> prepare($query);
	$result -> bind_param("i",$gameID);
	$result -> execute();
	return $result->get_result();
}

function updateGameResource($resource,$type,$primary,$idresource,$gameid){
	global $conn;
	$query = "UPDATE `game_resources` SET `text_name`= ?,`type`= ?,`primaryORsecundary`= ? WHERE `idgame_resources` = ? AND `game_idgame` = ?";
	$result = $conn -> prepare($query);
	$result -> bind_param("siiii",$resource,$type,$primary,$idresource,$gameid);
	$result -> execute();
}

function deleteGameResource($idresource){
	global $conn;
	$query = "DELETE FROM `resources_values` WHERE `game_resources_idgame_resources` = ?";
	$result = $conn -> prepare($query);
	$result -> bind_param("i",$idresource);
	$result -> execute();
	
	$query = "DELETE FROM `game_resources` WHERE `idgame_resources` = ?";
	$result = $conn -> prepare($query);
	$result -> bind_param("i",$idresource);
	$result -> execute();
}

function updateGameResourceValue($resourceValue,$order,$idresourceValue){
	global $conn;
	$query = "UPDATE `resources_values` SET `value`=?,`order`=? WHERE `idResources_values` = ?";
	$result = $conn -> prepare($query);
	$result -> bind_param("sii", $resourceValue,$order,$idresourceValue);
	$result -> execute();
}

function deleteGameResourceValue($idResourceValue){
	global $conn;
	$query = "DELETE FROM `resources` WHERE `Resources_values_idResources_values` = ?";
	$result = $conn -> prepare($query);
	$result -> bind_param("i",$idResourceValue);
	$result -> execute();
	
	$query = "DELETE FROM `resources_values` WHERE `idResources_values` = ?";
	$result = $conn -> prepare($query);
	$result -> bind_param("i",$idResourceValue);
	$result -> execute();
}

function getListPages($idList){
	global $conn;
	$query = "SELECT `idListPage`, `Title`, `Description`, `idList`, `order` FROM `list_page` WHERE `idList` = ? ORDER BY `order`";
	$result = $conn -> prepare($query);
	$result -> bind_param("i",$idList);
	$result -> execute();
	return $result->get_result();
}

function insertPage($title, $description, $idList, $order){
	global $conn;
	$query = "INSERT INTO `list_page`(`idListPage`, `Title`, `Description`, `idList`, `order`) VALUES (NULL,?,?,?,?)";
	$result = $conn -> prepare($query);
	$result -> bind_param("ssii", $title, $description, $idList, $order);
	$result -> execute();
}
?>
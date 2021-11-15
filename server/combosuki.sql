-- phpMyAdmin SQL Dump
-- version 5.0.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Nov 15, 2021 at 03:30 PM
-- Server version: 8.0.11
-- PHP Version: 7.4.9

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u177687112_combosuki`
--

-- --------------------------------------------------------

--
-- Table structure for table `button`
--

DROP TABLE IF EXISTS `button`;
CREATE TABLE IF NOT EXISTS `button` (
  `idbutton` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(45) NOT NULL,
  `png` varchar(45) NOT NULL,
  `game_idgame` int(11) DEFAULT NULL,
  `order` int(11) DEFAULT NULL,
  PRIMARY KEY (`idbutton`),
  KEY `fk_button_game1_idx` (`game_idgame`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `character`
--

DROP TABLE IF EXISTS `character`;
CREATE TABLE IF NOT EXISTS `character` (
  `idcharacter` int(11) NOT NULL AUTO_INCREMENT,
  `Name` varchar(45) NOT NULL,
  `game_idgame` int(11) NOT NULL,
  PRIMARY KEY (`idcharacter`),
  KEY `fk_character_game1_idx` (`game_idgame`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `combo`
--

DROP TABLE IF EXISTS `combo`;
CREATE TABLE IF NOT EXISTS `combo` (
  `idcombo` int(11) NOT NULL AUTO_INCREMENT,
  `combo` longtext NOT NULL,
  `comments` mediumtext,
  `video` mediumtext,
  `user_iduser` int(11) DEFAULT NULL,
  `character_idcharacter` int(11) NOT NULL,
  `submited` datetime DEFAULT NULL,
  `damage` double DEFAULT NULL,
  `type` int(11) NOT NULL,
  `verified` int(11) DEFAULT NULL,
  `patch` varchar(10) DEFAULT NULL,
  `password` varchar(16) DEFAULT NULL,
  PRIMARY KEY (`idcombo`),
  KEY `fk_combo_character1_idx` (`character_idcharacter`),
  KEY `fk_combo_user1` (`user_iduser`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `combo_listing`
--

DROP TABLE IF EXISTS `combo_listing`;
CREATE TABLE IF NOT EXISTS `combo_listing` (
  `idcombo` int(11) NOT NULL,
  `idlist` int(11) NOT NULL,
  `comment` varchar(45) DEFAULT NULL,
  `list_category_idlist_category` int(11) DEFAULT NULL,
  PRIMARY KEY (`idcombo`,`idlist`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `game`
--

DROP TABLE IF EXISTS `game`;
CREATE TABLE IF NOT EXISTS `game` (
  `idgame` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `complete` int(11) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `globalPass` varchar(16) DEFAULT NULL,
  `modPass` char(60) NOT NULL,
  `patch` varchar(10) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `notation` varchar(1000) DEFAULT NULL,
  PRIMARY KEY (`idgame`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `game_entry`
--

DROP TABLE IF EXISTS `game_entry`;
CREATE TABLE IF NOT EXISTS `game_entry` (
  `entryid` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL,
  `gameid` int(11) NOT NULL,
  `order` int(11) DEFAULT NULL,
  PRIMARY KEY (`entryid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `game_resources`
--

DROP TABLE IF EXISTS `game_resources`;
CREATE TABLE IF NOT EXISTS `game_resources` (
  `idgame_resources` int(11) NOT NULL AUTO_INCREMENT,
  `game_idgame` int(11) NOT NULL,
  `text_name` varchar(45) NOT NULL,
  `type` int(11) DEFAULT NULL,
  `primaryORsecundary` int(11) DEFAULT NULL,
  PRIMARY KEY (`idgame_resources`),
  KEY `fk_game_resources_game1_idx` (`game_idgame`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `link`
--

DROP TABLE IF EXISTS `link`;
CREATE TABLE IF NOT EXISTS `link` (
  `idLink` int(11) NOT NULL AUTO_INCREMENT,
  `idGame` int(11) NOT NULL,
  `Title` varchar(50) NOT NULL,
  `Link` varchar(255) NOT NULL,
  PRIMARY KEY (`idLink`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `list`
--

DROP TABLE IF EXISTS `list`;
CREATE TABLE IF NOT EXISTS `list` (
  `idlist` int(11) NOT NULL AUTO_INCREMENT,
  `list_name` varchar(100) NOT NULL,
  `game_idgame` int(11) DEFAULT NULL,
  `password` varchar(16) NOT NULL,
  `type` int(11) NOT NULL,
  PRIMARY KEY (`idlist`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `list_category`
--

DROP TABLE IF EXISTS `list_category`;
CREATE TABLE IF NOT EXISTS `list_category` (
  `idlist_category` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `list_idlist` int(11) NOT NULL,
  `order` int(11) DEFAULT NULL,
  PRIMARY KEY (`idlist_category`),
  KEY `fk_list_category_list1_idx` (`list_idlist`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `logs`
--

DROP TABLE IF EXISTS `logs`;
CREATE TABLE IF NOT EXISTS `logs` (
  `idlog` int(11) NOT NULL AUTO_INCREMENT,
  `Description` text NOT NULL,
  `Date` date NOT NULL,
  PRIMARY KEY (`idlog`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `resources`
--

DROP TABLE IF EXISTS `resources`;
CREATE TABLE IF NOT EXISTS `resources` (
  `idResources` int(11) NOT NULL AUTO_INCREMENT,
  `combo_idcombo` int(11) NOT NULL,
  `Resources_values_idResources_values` int(11) NOT NULL,
  `number_value` double DEFAULT NULL,
  PRIMARY KEY (`idResources`),
  KEY `fk_Resources_combo1_idx` (`combo_idcombo`),
  KEY `fk_Resources_Resources_values1_idx` (`Resources_values_idResources_values`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `resources_values`
--

DROP TABLE IF EXISTS `resources_values`;
CREATE TABLE IF NOT EXISTS `resources_values` (
  `idResources_values` int(11) NOT NULL AUTO_INCREMENT,
  `value` varchar(115) NOT NULL,
  `order` int(11) DEFAULT NULL,
  `game_resources_idgame_resources` int(11) NOT NULL,
  PRIMARY KEY (`idResources_values`),
  KEY `fk_resources_values_game_resources1_idx` (`game_resources_idgame_resources`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

DROP TABLE IF EXISTS `user`;
CREATE TABLE IF NOT EXISTS `user` (
  `iduser` int(11) NOT NULL,
  `nickname` varchar(45) DEFAULT NULL,
  `trusted user` int(11) DEFAULT NULL,
  PRIMARY KEY (`iduser`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `button`
--
ALTER TABLE `button`
  ADD CONSTRAINT `fk_button_game1` FOREIGN KEY (`game_idgame`) REFERENCES `game` (`idgame`);

--
-- Constraints for table `character`
--
ALTER TABLE `character`
  ADD CONSTRAINT `fk_character_game1` FOREIGN KEY (`game_idgame`) REFERENCES `game` (`idgame`);

--
-- Constraints for table `game_resources`
--
ALTER TABLE `game_resources`
  ADD CONSTRAINT `fk_game_resources_game1` FOREIGN KEY (`game_idgame`) REFERENCES `game` (`idgame`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

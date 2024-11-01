<?php

global $wpdb;

$tbl = "CREATE TABLE  `wpss_accom` (
  `aid` int(11) NOT NULL auto_increment,
  `rid` int(11) NOT NULL default '0',
  `name` varchar(100) NOT NULL default '',
  `stars` tinyint(4) NOT NULL default '0',
  `generalinfo` text NOT NULL,
  `address` varchar(200) NOT NULL default '',
  PRIMARY KEY  (`aid`),
  KEY `index_2` (`rid`)
) ENGINE=MyISAM";

$wpdb->query($tbl);

$tbl = "CREATE TABLE  `wpss_accom_pics` (
  `aid` int(10) unsigned NOT NULL auto_increment,
  `pics` varchar(45) NOT NULL,
  PRIMARY KEY  (`aid`)
) ENGINE=MyISAM";

$wpdb->query($tbl);

$tbl = "CREATE TABLE  `wpss_airports` (
  `code` char(3) NOT NULL,
  `name` varchar(100) NOT NULL,
  `arrival` tinyint(3) unsigned NOT NULL,
  PRIMARY KEY  (`code`)
) ENGINE=MyISAM";

$wpdb->query($tbl);

$tbl = "CREATE TABLE  `wpss_country` (
  `cid` int(11) NOT NULL auto_increment,
  `name` varchar(30) NOT NULL default '',
  `offers` varchar(100) NOT NULL default '',
  PRIMARY KEY  (`cid`),
  UNIQUE KEY `Index_2` (`name`)
) ENGINE=MyISAM";

$wpdb->query($tbl);

$tbl = "CREATE TABLE  `wpss_country_region_resort` (
  `cid` int(11) NOT NULL default '0',
  `regid` int(11) NOT NULL default '0',
  `rid` int(11) NOT NULL default '0',
  PRIMARY KEY  (`cid`,`rid`),
  KEY `index_2` (`regid`)
) ENGINE=MyISAM";

$wpdb->query($tbl);

$tbl = "CREATE TABLE  `wpss_region` (
  `regid` int(11) NOT NULL auto_increment,
  `name` varchar(100) NOT NULL default '',
  `cid` int(10) unsigned NOT NULL default '0',
  `offers` varchar(100) NOT NULL default '',
  PRIMARY KEY  (`regid`),
  KEY `Index_2` (`name`)
) ENGINE=MyISAM";

$wpdb->query($tbl);

$tbl = "CREATE TABLE  `wpss_resort` (
  `rid` int(11) NOT NULL auto_increment,
  `name` varchar(100) NOT NULL default '',
  `offers` varchar(100) NOT NULL default '',
  PRIMARY KEY  (`rid`)
) ENGINE=MyISAM";


$wpdb->query($tbl);

$tbl = "CREATE TABLE  `wpss_post_content_map` (
  `pid` int(10) unsigned NOT NULL auto_increment,
  `maptype` tinyint(3) unsigned NOT NULL,
  `mapid` int(10) unsigned NOT NULL,
  PRIMARY KEY  (`pid`,`maptype`)
) ENGINE=InnoDB";

$wpdb->query($tbl);


$tbl = "CREATE TABLE  `wpss_resort_airports` (
  `rid` int(10) unsigned NOT NULL auto_increment,
  `code` varchar(4) NOT NULL,
  PRIMARY KEY  (`rid`)
) ENGINE=InnoDB";

$wpdb->query($tbl);
?>
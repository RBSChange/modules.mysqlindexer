CREATE TABLE IF NOT EXISTS `m_mysqlindexer_props` (
	`final_id` varchar(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
	`prop` varchar(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
	`data` varchar(255) CHARACTER SET latin1 COLLATE latin1_general_ci DEFAULT NULL,
	UNIQUE KEY `PROP` (`final_id`,`prop`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1
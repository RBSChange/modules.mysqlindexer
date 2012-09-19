CREATE TABLE IF NOT EXISTS `m_mysqlindexer_props` (
	`final_id` varchar(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
	`prop` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
	`data` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
	UNIQUE KEY `PROP` (`final_id`,`prop`)
) ENGINE=MyISAM DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;
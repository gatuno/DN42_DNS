<?php
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of InDefero, an open source project management application.
# Copyright (C) 2008 Céondo Ltd and contributors.
#
# InDefero is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# InDefero is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
#
# ***** END LICENSE BLOCK ***** */

/**
 * Add the private column for the project.
 */

function DNS42_Migrations_2ReversePrefix_up($params=null) {
	$table = Gatuf::factory('DNS42_ManagedDomain')->getSqlTable();
	$sql = array();
	//$sql['PostgreSQL'] = 'ALTER TABLE '.$table.' ADD `maestra` TINYINT(1) NOT NULL DEFAULT '1' AFTER `delegacion`, ADD `reversa` INT(1) NOT NULL DEFAULT '0' AFTER `maestra`';
	$sql['MySQL'] = 'ALTER TABLE '.$table." ADD `prefix` VARCHAR(256) NOT NULL AFTER `reversa`";
	$db = Gatuf::db();
	$engine = Gatuf::config('db_engine');
	if (!isset($sql[$engine])) {
		throw new Exception($engine.' migration not supported.');
	}
	$db->execute($sql[$engine]);
}

function DNS42_Migrations_2ReversePrefix_down($params=null) {
	$table = Gatuf::factory('DNS42_ManagedDomain')->getSqlTable();
	$sql = array();
	//$sql['PostgreSQL'] = 'ALTER TABLE '.$table.' DROP COLUMN "maestra", DROP COLUMN "reversa"';
	$sql['MySQL'] = 'ALTER TABLE '.$table.' DROP COLUMN `prefix`';
	$db = Gatuf::db();
	$engine = Gatuf::config('db_engine');
	if (!isset($sql[$engine])) {
		throw new Exception($engine.' migration not supported.');
	}
	$db->execute($sql[$engine]);
}

<?php

function DNS42_Migrations_Install_setup ($params=null) {
	$models = array (
		'DNS42_TopLevelDomain',
		'DNS42_NameServer',
		'DNS42_PingCheck',
	);
	
	$db = Gatuf::db ();
	$schema = new Gatuf_DB_Schema ($db);
	foreach ($models as $model) {
		$schema->model = new $model ();
		$schema->createTables ();
	}
	
	foreach ($models as $model) {
		$schema->model = new $model ();
		$schema->createConstraints ();
	}
}

function DNS42_Migrations_Install_teardown ($params=null) {
	$models = array (
		'DNS42_TopLevelDomain',
		'DNS42_NameServer',
		'DNS42_PingCheck',
	);
	
	$db = Gatuf::db ();
	$schema = new Gatuf_DB_Schema ($db);
	
	foreach ($models as $model) {
		$schema->model = new $model ();
		$schema->dropConstraints();
	}
	
	foreach ($models as $model) {
		$schema->model = new $model ();
		$schema->dropTables ();
	}
}

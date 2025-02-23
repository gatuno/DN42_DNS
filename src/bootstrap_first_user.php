#!/usr/bin/php
<?php

require dirname(__FILE__).'/DNS42/conf/path.php';
require 'Gatuf.php';
Gatuf::start(dirname(__FILE__).'/DNS42/conf/dns42.php');
Gatuf_Despachador::loadControllers(Gatuf::config('dns42_views'));

restore_error_handler ();

$user = new Gatuf_User ();
$user->login = 'admin';
$user->first_name = 'Admin';
$user->last_name = 'Admin';
$user->email = 'admin@somewhere.com';

// No worries, password will be correctly crypted
$user->password = 'abcqwerty123';

$user->administrator = true;

$user->create ();

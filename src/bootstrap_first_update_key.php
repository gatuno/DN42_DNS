#!/usr/bin/php
<?php

require dirname(__FILE__).'/DNS42/conf/path.php';
require 'Gatuf.php';
Gatuf::start(dirname(__FILE__).'/DNS42/conf/dns42.php');
Gatuf_Despachador::loadControllers(Gatuf::config('dns42_views'));

restore_error_handler ();

$update_key = new DNS42_UpdateKey ();
$update_key->nombre = 'key_a';
$update_key->algo = 'hmac-sha256';

$update_key->secret = '==A_SECRET==';

$update_key->create ();

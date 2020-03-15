<?php

class DNS42_NameServer extends Gatuf_Model {
	public $_model = __CLASS__;
	
	function init () {
		$this->_a['table'] = 'name_servers';
		$this->_a['model'] = __CLASS__;
		$this->primary_key = 'id';
		
		$this->_a['cols'] = array (
			'id' =>
			array (
			       'type' => 'Gatuf_DB_Field_Sequence',
			       'blank' => true,
			),
			'nombre' =>
			array (
			       'type' => 'Gatuf_DB_Field_Varchar',
			       'blank' => false,
			       'size' => 256,
			),
			'dominios' =>
			array (
			       'type' => 'Gatuf_DB_Field_Manytomany',
			       'blank' => false,
			       'model' => 'DNS42_TopLevelDomain',
			       'relate_name' => 'name_severs',
			),
			'ipv4' =>
			array (
			       'type' => 'Gatuf_DB_Field_Varchar',
			       'blank' => false,
			       'size' => 256,
			),
			'ipv6' =>
			array (
			       'type' => 'Gatuf_DB_Field_Varchar',
			       'blank' => false,
			       'size' => 256,
			),
			'ping4' =>
			array (
			       'type' => 'Gatuf_DB_Field_Varchar',
			       'blank' => false,
			       'size' => 256,
			),
			'ping6' =>
			array (
			       'type' => 'Gatuf_DB_Field_Varchar',
			       'blank' => false,
			       'size' => 256,
			),
			'estado' =>
			array (
			       'type' => 'Gatuf_DB_Field_Integer',
			       'blank' => false,
			       'default' => 0, /* 0 = desconocido, 1 = mal, 2 = advertencia, 3 = bien */
			),
		);
		
		$this->default_order = 'nombre ASC';
	}
	
	public function estado_as_string () {
		switch ($this->estado) {
			case 0:
				return 'unknown';
			case 1:
				return 'bad';
			case 2:
				return 'warn';
			case 3:
				return 'good';
			default:
				return 'unknown';
		}
	}
}


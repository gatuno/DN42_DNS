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
			'dominio' =>
			array (
			       'type' => 'Gatuf_DB_Field_Foreignkey',
			       'blank' => false,
			       'model' => 'DNS42_Domain',
			       'relate_name' => 'ns',
			),
			'server' =>
			array (
			       'type' => 'Gatuf_DB_Field_Foreignkey',
			       'blank' => false,
			       'model' => 'DNS42_Server',
			       'relate_name' => 'domains',
			),
			'estado' =>
			array (
			       'type' => 'Gatuf_DB_Field_Integer',
			       'blank' => false,
			       'default' => 0, /* 0 = desconocido, 1 = mal, 2 = advertencia, 3 = bien */
			),
			'response4' =>
			array (
			       'type' => 'Gatuf_DB_Field_Integer',
			       'blank' => false,
			       'default' => 0, /* 0 = no-probado, 1 = mal, 2 = bien */
			),
			'response6' =>
			array (
			       'type' => 'Gatuf_DB_Field_Integer',
			       'blank' => false,
			       'default' => 0, /* 0 = no-probado, 1 = mal, 2 = bien */
			),
			'open_transfer4' =>
			array (
			       'type' => 'Gatuf_DB_Field_Integer',
			       'blank' => false,
			       'default' => 0, /* 0 = no-probado, 1 = mal, 2 = bien */
			),
			'open_transfer6' =>
			array (
			       'type' => 'Gatuf_DB_Field_Integer',
			       'blank' => false,
			       'default' => 0, /* 0 = no-probado, 1 = mal, 2 = bien */
			),
			'autoritative4' =>
			array (
			       'type' => 'Gatuf_DB_Field_Integer',
			       'blank' => false,
			       'default' => 0, /* 0 = no-probado, 1 = mal, 2 = bien */
			),
			'autoritative6' =>
			array (
			       'type' => 'Gatuf_DB_Field_Integer',
			       'blank' => false,
			       'default' => 0, /* 0 = no-probado, 1 = mal, 2 = bien */
			),
			'parent_match4' =>
			array (
			       'type' => 'Gatuf_DB_Field_Integer',
			       'blank' => false,
			       'default' => 0, /* 0 = no-probado, 1 = mal, 2 = bien */
			),
			'parent_match6' =>
			array (
			       'type' => 'Gatuf_DB_Field_Integer',
			       'blank' => false,
			       'default' => 0, /* 0 = no-probado, 1 = mal, 2 = bien */
			),
			'soa4' =>
			array (
			       'type' => 'Gatuf_DB_Field_Varchar',
			       'blank' => false,
			       'size' => 256,
			),
			'soa6' =>
			array (
			       'type' => 'Gatuf_DB_Field_Varchar',
			       'blank' => false,
			       'size' => 256,
			),
			'ns_list4' =>
			array (
			       'type' => 'Gatuf_DB_Field_Varchar',
			       'blank' => false,
			       'size' => 2048,
			),
			'ns_list6' =>
			array (
			       'type' => 'Gatuf_DB_Field_Varchar',
			       'blank' => false,
			       'size' => 2048,
			),
		);
		
		$this->_a['idx'] = array (
			'ns_idx' =>
			array (
			       'col' => 'dominio, server',
			       'type' => 'unique',
			),
		);
	}
	
	public function klass_field ($field) {
		switch ($this->$field) {
			case 0:
				return 'unknown';
			case 1:
				return 'bad';
			case 2:
				return 'good';
			default:
				return 'unknown';
		}
	}
}


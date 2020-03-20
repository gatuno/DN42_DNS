<?php

class DNS42_Record extends Gatuf_Model {
	public $_model = __CLASS__;
	
	function init () {
		$this->_a['table'] = 'records';
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
			       'model' => 'DNS42_ManagedDomain',
			       'relate_name' => 'records',
			),
			'name' =>
			array (
			       'type' => 'Gatuf_DB_Field_Varchar',
			       'blank' => false,
			       'size' => 256,
			),
			'type' =>
			array (
			       'type' => 'Gatuf_DB_Field_Varchar',
			       'blank' => false,
			       'size' => 32,
			),
			'ttl' =>
			array (
			       'type' => 'Gatuf_DB_Field_Integer',
			       'blank' => false,
			       'default' => 300,
			),
			'rdata' =>
			array (
			       'type' => 'Gatuf_DB_Field_Varchar',
			       'blank' => false,
			       'size' => 2048,
			),
			'locked' =>
			array (
			       'type' => 'Gatuf_DB_Field_Boolean',
			       'blank' => false,
			       'default' => false,
			),
		);
	}
}


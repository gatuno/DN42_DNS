<?php

class DNS42_ManagedDomain extends Gatuf_Model {
	public $_model = __CLASS__;
	
	function init () {
		$this->_a['table'] = 'managed_domains';
		$this->_a['model'] = __CLASS__;
		$this->primary_key = 'id';
		
		$this->_a['cols'] = array (
			'id' =>
			array (
			       'type' => 'Gatuf_DB_Field_Sequence',
			       'blank' => true,
			),
			'key' =>
			array (
			       'type' => 'Gatuf_DB_Field_Foreignkey',
			       'blank' => false,
			       'model' => 'DNS42_UpdateKey',
			       'relate_name' => 'managed_domains',
			),
			'owner' =>
			array (
			       'type' => 'Gatuf_DB_Field_Foreignkey',
			       'blank' => false,
			       'model' => 'Gatuf_User',
			       'relate_name' => 'owned_domains',
			),
			'users' =>
			array (
			       'type' => 'Gatuf_DB_Field_Manytomany',
			       'blank' => false,
			       'model' => 'Gatuf_User',
			       'relate_name' => 'managed_domains',
			),
			'dominio' =>
			array (
			       'type' => 'Gatuf_DB_Field_Varchar',
			       'blank' => false,
			       'size' => 256,
			       //'unique' => true,
			),
			'delegacion' =>
			array (
			       'type' => 'Gatuf_DB_Field_Integer',
			       'blank' => false,
			       'default' => 0,
			       /* 0 = Delegación en prueba,
			        * 1 = Delegación fallida,
			        * 2 = Delegación buena,
			        * 3 = Falló crear el archivo de zona
			        * 4 = Falló crear la zona contra el bind
			        * 6 = Zona activada PERO sin verificar */
			),
			'maestra' =>
			array (
			       'type' => 'Gatuf_DB_Field_Boolean',
			       'blank' => false,
			       'default' => true, /* TRUE = zona DNS maestra administrada por nosotros, false = somos zona esclava */
			),
			'reversa' =>
			array (
			       'type' => 'Gatuf_DB_Field_Boolean',
			       'blank' => false,
			       'default' => false, /* TRUE = zona dns inversa, solo aplica para las zonas */
			),
			'prefix' =>
			array (
			       'type' => 'Gatuf_DB_Field_Varchar',
			       'blank' => false,
			       'size' => 256,
			       //'unique' => true,
			),
		);
		
		$this->default_order = 'dominio ASC';
	}
	
	public function prefix_nice () {
		return str_replace ('_', '/', $this->prefix);
	}
}


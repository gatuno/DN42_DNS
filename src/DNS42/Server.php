<?php

class DNS42_Server extends Gatuf_Model {
	public $_model = __CLASS__;
	
	function init () {
		$this->_a['table'] = 'servers';
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
			       //'unique' => true,
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
	
	public function preSave ($create = false) {
		/* Actualizar la variable de estado acorde al resultados de los pings */
		if ($this->ipv4 != '' && $this->ipv6 != '') {
			if ($this->ping4 == '' && $this->ping6 == '') {
				$this->estado = 0; /* No probado */
			} else if ($this->ping4 == 'failed' && $this->ping6 == 'failed') {
				/* El color verde se logra con los dos pings en verde */
				$this->estado = 1; /* Rojo */
			} else if ($this->ping4 == 'failed') {
				$this->estado = 2; /* Advertencia */
			} else if ($this->ping6 == 'failed') {
				$this->estado = 2; /* Advertencia */
			} else {
				$this->estado = 3;
			}
		} else if ($this->ipv4 != '') {
			if ($this->ping4 == '') {
				$this->estado = 0;
			} else if ($this->ping4 == 'failed') {
				/* El verde se logra solo con el ping de ipv4 */
				$this->estado = 1;
			} else {
				$this->estado = 3;
			}
		} else if ($this->ipv6 != '') {
			if ($this->ping6 == '') {
				$this->estado = 0;
			} else if ($this->ping6 == 'failed') {
				/* El verde se logra solo con el ping de ipv6 */
				$this->estado = 1;
			} else {
				$this->estado = 3;
			}
		}
	}
	
	public function estado4_as_string () {
		if ($this->ping4 == 'failed') {
			return 'bad';
		}
		return 'good';
	}
	public function estado6_as_string () {
		if ($this->ping6 == 'failed') {
			return 'bad';
		}
		return 'good';
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


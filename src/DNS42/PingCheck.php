<?php

class DNS42_PingCheck extends Gatuf_Model {
	public $_model = __CLASS__;
	
	function init () {
		$this->_a['table'] = 'ping_checks';
		$this->_a['model'] = __CLASS__;
		$this->primary_key = 'id';
		
		$this->_a['cols'] = array (
			'id' =>
			array (
			       'type' => 'Gatuf_DB_Field_Sequence',
			       'blank' => true,
			),
			'server' =>
			array (
			       'type' => 'Gatuf_DB_Field_Foreignkey',
			       'blank' => false,
			       'model' => 'DNS42_Server',
			       'relate_name' => 'ping_checks',
			),
			'prioridad' =>
			array (
			       'type' => 'Gatuf_DB_Field_Integer',
			       'blank' => false,
			       'default' => 100,
			),
			'estado' =>
			array (
			       'type' => 'Gatuf_DB_Field_Integer',
			       'blank' => false,
			       'default' => 0,
			),
		);
	}
	
	public function block_for_check () {
		$con = $this->_con = &Gatuf::db($this);
		
		$req = 'UPDATE '.$this->getSqlTable().' SET '."\n";
		$req .= 'estado = 1'."\n";
		$req .= ' WHERE '.$this->primary_key.' = '.$this->_toDb($this->_data[$this->primary_key], $this->primary_key).' AND estado = 0';
		
		$con->execute ($req);
		
		$affected = $con->getAffectedRows ();
		
		if ($affected == 0) {
			return false;
		}
		
		$this->_data['estado'] = 1;
		
		return true;
	}
}


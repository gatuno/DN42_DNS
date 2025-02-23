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
	
	public function format_priority () {
		if ($this->type == 'MX') {
			$toks = explode (" ", $this->rdata, 2);
			
			return $toks[0];
		}
		return '-';
	}
	
	public function format_rdata () {
		if ($this->type == 'NS' || $this->type == 'CNAME') {
			return rtrim ($this->rdata, ".");
		} else if ($this->type == 'MX') {
			$toks = explode (" ", $this->rdata, 2);
			
			return rtrim ($toks[1], ".");
		}
		return $this->rdata;
	}
	
	public function can_be_updated () {
		$updatable = array ('A', 'AAAA', 'CNAME', 'NS', 'MX', 'TXT', 'PTR');
		if ($this->locked) return false;
		return in_array ($this->type, $updatable);
	}
}


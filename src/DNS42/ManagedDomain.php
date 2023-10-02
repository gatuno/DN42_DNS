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
			'tipo_reversa' =>
			array (
			       'type' => 'Gatuf_DB_Field_Integer',
			       'blank' => false,
			       'default' => 0, /* 4 = para IPv4, 6 = para IPv6; solo aplica a zonas inversas */
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
	
	public function get_reverse_prefix () {
		if ($this->reversa == false) return '';
		
		$toks = explode ('_', $this->prefix);

		if (count ($toks) != 2) {
			throw new Exception (__('Undefined'));
		}

		$network = $toks[0];
		$mask = ((int) $toks[1]);
		
		if ($this->tipo_reversa == 4) {
			$toks = explode ('.', $network);
			$prefix = $toks[0].'.'.$toks[1].'.'.$toks[2].'.';
		} else if ($this->tipo_reversa == 6) {
			$prefix = '';
			$n = $mask;
			$pos = 0;
			while ($n > 0) {
				if ($pos > strlen ($network)) break;
				$char = substr ($network, $pos, 1);
				$prefix .= $char;
				if ($char == ':') {
					/* Completar a restar 16 */
					if (($n % 16) == 0) {
						/* ¿Estoy al final? */
						$n = $n - 0;
					} else {
						$n = $n - ($n % 16);
					}
				} else {
					$n = $n - 4;
				}
				$pos++;
			}
			if (($mask % 16) == 0) {
				$prefix .= ':';
			}
		}
		
		return $prefix;
	}
}


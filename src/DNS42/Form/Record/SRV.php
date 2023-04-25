<?php

class DNS42_Form_Record_SRV extends Gatuf_Form {
	private $dominio;
	public function initFields($extra=array()) {
		$this->dominio = $extra['dominio'];
		$this->fields['name'] = new Gatuf_Form_Field_Varchar (
			array (
				'required' => true,
				'label' => __('Name'),
				'help_text' => __("A name may only contain A-Z, a-z, 0-9, _, -, and .. '@', '*', or the hostname may be used where appropriate. SRV records should be in the format of _servicename._protocol.fqdn.com per the RFC. (ie _jabber._tcp.example.com)"),
				'initial' => '',
				'widget_attrs' => array ('autocomplete' => 'off', 'size' => 60),
		));
		
		$this->fields['priority'] = new Gatuf_Form_Field_Integer (
			array (
				'required' => true,
				'label' => __('Priority'),
				'help_text' => __("The priority must only be within the range 0-65535 (lower is better ). 0 is a good default."),
				'initial' => '',
				'min' => 0,
				'max' => 65535,
				'widget_attrs' => array ('autocomplete' => 'off'),
		));
		
		$this->fields['weight'] = new Gatuf_Form_Field_Integer (
			array (
				'required' => true,
				'label' => __('Weight'),
				'help_text' => __("The weight must only be within the range 0-65535 (higher is better ). 0 is a good default."),
				'initial' => '',
				'min' => 0,
				'max' => 65535,
				'widget_attrs' => array ('autocomplete' => 'off'),
		));
		
		$this->fields['port'] = new Gatuf_Form_Field_Integer (
			array (
				'required' => true,
				'label' => __('Port'),
				'help_text' => __("The port must only be within the range 0-65535. The port varies depending on the service (ie 80, 5222, 5069, etc)."),
				'initial' => '',
				'min' => 0,
				'max' => 65535,
				'widget_attrs' => array ('autocomplete' => 'off'),
		));
		
		$this->fields['target'] = new Gatuf_Form_Field_Varchar (
			array (
				'required' => true,
				'label' => __('Target'),
				'help_text' => __("This is the hostname of the machine running the service. It should exist as an A record and may only contain A-Z, a-z, 0-9, _, -, and .."),
				'initial' => '',
				'widget_attrs' => array ('autocomplete' => 'off', 'size' => 60),
		));
		
		$ttl_values = array (
			__('24 hours (86400)') => 86400,
			__('12 hours (43200)') => 43200,
			__('8 hours (28800)') => 28800,
			__('4 hours (14400)') => 14400,
			__('2 hours (7200)') => 7200,
			__('1 hour (3600)') => 3600,
			__('30 minutes (1800)') => 1800,
			__('15 minutes (900)') => 900,
			__('5 minutes (300)') => 300,
		);
		
		$this->fields['ttl'] = new Gatuf_Form_Field_Integer (
			array (
				'required' => true,
				'label' => __('TTL (Time to live)'),
				'help_text' => __("The TTL (time to live) indicates how long a DNS record is valid for - and therefore when the address needs to be rechecked."),
				'initial' => 86400,
				'widget' => 'Gatuf_Form_Widget_SelectInput',
				'choices' => $ttl_values,
		));
	}
	
	public function clean_name () {
		$name = $this->cleaned_data['name'];
		
		if ($name == '@') return '@';
		
		if (filter_var ($name, FILTER_VALIDATE_DOMAIN, 0) == false) {
			throw new Gatuf_Form_Invalid (__('Invalid domain name'));
		}
		
		return $name;
	}
	
	public function clean_target () {
		$hostname = $this->cleaned_data['target'];
		
		if (filter_var ($hostname, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) == false) {
			throw new Gatuf_Form_Invalid (__('Invalid domain name'));
		}
		
		return $hostname;
	}
	
	public function clean () {
		/* Limpieza general de los registros. */
		
		/* Del nombre, quitar los puntos al final */
		$name = rtrim (trim ($this->cleaned_data['name']), ".");
		$len = strlen ($this->dominio->dominio);
		
		if ($name == '@') {
			$name = $this->dominio->dominio;
		} else if ($name != $this->dominio->dominio) {
			/* Revisar si necesito qualificar este dominio */
			$ending = substr ($name, -($len + 1));
		
			if ($ending != '.' . $this->dominio->dominio) {
				/* Como no es un nombre calificado, agregar el dominio */
				$name = $name . '.' . $this->dominio->dominio;
			}
		}
		
		$this->cleaned_data['name'] = $name;
		
		/* Para el hostname solo asegurarnos que tenga el punto al final */
		$hostname = trim ($this->cleaned_data['target']);
		
		if (substr ($hostname, -1) != '.') {
			$hostname = $hostname . '.';
		}
		
		$this->cleaned_data['target'] = $hostname;
		$rdata = sprintf ("%s %s %s %s", $this->cleaned_data['priority'], $this->cleaned_data['weight'], $this->cleaned_data['port'], $this->cleaned_data ['target']);
		
		/* Un registro con el mismo nombre, mismo tipo y mismo valor no puede existir duplicado */
		$sql = new Gatuf_SQL ('type="SRV" AND dominio=%s AND name=%s AND rdata=%s', array ($this->dominio->id, $this->cleaned_data['name'], $rdata));
		$records_c = Gatuf::factory ('DNS42_Record')->getList (array ('filter' => $sql->gen (), 'count' => true));
		if ($records_c > 0) {
			throw new Gatuf_Form_Invalid (__('This record already exists in this zone with the same name and value'));
		}
		
		return $this->cleaned_data;
	}
	public function save ($commit = true) {
		if (!$this->isValid()) {
			throw new Exception (__('Cannot save an invalid form.'));
		}
		
		$record = new DNS42_Record ();
		
		$record->dominio = $this->dominio;
		$record->name = $this->cleaned_data ['name'];
		$record->type = 'SRV';
		$record->ttl = $this->cleaned_data ['ttl'];
		$rdata = sprintf ("%s %s %s %s", $this->cleaned_data['priority'], $this->cleaned_data['weight'], $this->cleaned_data['port'], $this->cleaned_data ['target']);
		$record->rdata = $rdata;
		
		if ($commit) {
			$record->create ();
		}
		
		return $record;
	}
}

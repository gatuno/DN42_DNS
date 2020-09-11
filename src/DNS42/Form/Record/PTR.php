<?php

class DNS42_Form_Record_PTR extends Gatuf_Form {
	private $dominio;
	public function initFields($extra=array()) {
		$this->dominio = $extra['dominio'];
		$this->fields['name'] = new Gatuf_Form_Field_Varchar (
			array (
				'required' => true,
				'label' => __('Name'),
				'help_text' => __("A name may only contain A-Z, a-z, 0-9, _, -, or .. '@' or the hostname may be used where appropriate."),
				'initial' => '',
		));
		
		$this->fields['hostname'] = new Gatuf_Form_Field_Varchar (
			array (
				'required' => true,
				'label' => __('Host name'),
				'help_text' => __("A hostname should be valid and may only contain A-Z, a-z, 0-9, _, -, and .."),
				'initial' => '',
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
		
		if (filter_var ($name, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) == false) {
			throw new Gatuf_Form_Invalid (__('Invalid domain name'));
		}
		
		return $name;
	}
	
	public function clean_hostname () {
		$hostname = $this->cleaned_data['hostname'];
		
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
		$hostname = trim ($this->cleaned_data['hostname']);
		
		if (substr ($hostname, -1) != '.') {
			$hostname = $hostname . '.';
		}
		
		$this->cleaned_data['hostname'] = $hostname;
		
		return $this->cleaned_data;
	}
	
	public function save ($commit = true) {
		if (!$this->isValid()) {
			throw new Exception (__('Cannot save an invalid form.'));
		}
		
		$record = new DNS42_Record ();
		
		$record->dominio = $this->dominio;
		$record->name = $this->cleaned_data ['name'];
		$record->type = 'PTR';
		$record->ttl = $this->cleaned_data ['ttl'];
		$record->rdata = $this->cleaned_data ['hostname'];
		
		if ($commit) {
			$record->create ();
		}
		
		return $record;
	}
}

<?php

class DNS42_Form_Record_A extends Gatuf_Form {
	private $dominio;
	public function initFields($extra=array()) {
		$this->dominio = $extra['dominio'];
		$this->fields['name'] = new Gatuf_Form_Field_Varchar (
			array (
				'required' => true,
				'label' => __('Name'),
				'help_text' => __("A name may only contain A-Z, a-z, 0-9, _, -, or the hostname may be used where appropriate."),
				'initial' => '',
		));
		
		$this->fields['ipv4'] = new Gatuf_Form_Field_Varchar (
			array (
				'required' => true,
				'label' => __('IPv4 Address'),
				'help_text' => __("An IPv4 address must be a decimal dotted quad string, for example: '192.168.123.10'"),
				'initial' => '',
		));
		
		$ttl_values = array (
			'24 hours (86400)' => 86400,
			'12 hours (43200)' => 43200,
			'8 hours (28800)' => 28800,
			'4 hours (14400)' => 14400,
			'2 hours (7200)' => 7200,
			'1 hour (3600)' => 3600,
			'30 minutes (1800)' => 1800,
			'15 minutes (900)' => 900,
			'5 minutes (300)' => 300,
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
		
		if (filter_var ($name, FILTER_VALIDATE_DOMAIN) == false) {
			throw new Gatuf_Form_Invalid (__('Invalid domain name'));
		}
		
		return $name;
	}
	
	public function clean_ipv4 () {
		$ipv4 = $this->cleaned_data['ipv4'];
		
		if (filter_var ($ipv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) == false) {
			throw new Gatuf_Form_Invalid (__('Invalid IPv4 Address'));
		}
		
		return $ipv4;
	}
	
	public function clean () {
		/* Limpieza general de los registros. */
		
		/* Del nombre, quitar los puntos al final */
		$name = rtrim (trim ($this->cleaned_data['name']), ".");
		$len = strlen ($this->dominio->dominio);
		
		$ending = substr ($name, -($len + 1));
		
		if ($ending == '.' . $this->dominio->dominio) {
			/* Ya es parte del dominio, nada que hacer */
		} else {
			/* Como no es un nombre calificado, agregar el dominio */
			$name = $name . '.' . $this->dominio->dominio;
		}
		
		$this->cleaned_data['name'] = $name;
		
		$ipv4 = inet_ntop (inet_pton ($this->cleaned_data['ipv4']));
		$this->cleaned_data['ipv4'] = $ipv4;
		
		return $this->cleaned_data;
	}
	public function save ($commit = true) {
		if (!$this->isValid()) {
			throw new Exception (__('Cannot save an invalid form.'));
		}
		
		$record = new DNS42_Record ();
		
		$record->dominio = $this->dominio;
		$record->name = $this->cleaned_data ['name'];
		$record->type = 'A';
		$record->ttl = $this->cleaned_data ['ttl'];
		$record->rdata = $this->cleaned_data ['ipv4'];
		
		if ($commit) {
			$record->create ();
		}
		
		return $record;
	}
}

<?php

class DNS42_Form_Record_Update_PTR extends Gatuf_Form {
	private $record;
	private $dominio;
	public function initFields($extra=array()) {
		$this->record = $extra['record'];
		$this->dominio = $this->record->get_dominio ();
		
		$this->fields['hostname'] = new Gatuf_Form_Field_Varchar (
			array (
				'required' => true,
				'label' => __('Hostname'),
				'help_text' => __("A hostname should be valid and may only contain A-Z, a-z, 0-9, _, -, and .."),
				'initial' => $this->record->rdata,
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
				'initial' => $this->record->ttl,
				'widget' => 'Gatuf_Form_Widget_SelectInput',
				'choices' => $ttl_values,
		));
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
		
		$this->record->ttl = $this->cleaned_data ['ttl'];
		$this->record->rdata = $this->cleaned_data ['hostname'];
		
		if ($commit) {
			$this->record->update ();
		}
		
		return $this->record;
	}
}

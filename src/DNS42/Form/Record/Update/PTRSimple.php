<?php

class DNS42_Form_Record_Update_PTRSimple extends Gatuf_Form {
	private $record;
	private $dominio;
	public function initFields($extra=array()) {
		$this->record = $extra['record'];
		$this->dominio = $this->record->get_dominio ();
		if ($this->dominio->reversa == false) {
			throw new Exception (__('Invalid Domain for PTRSimple'));
		}
		
		$this->fields['hostname'] = new Gatuf_Form_Field_Varchar (
			array (
				'required' => true,
				'label' => __('Hostname'),
				'help_text' => __("A hostname should be valid and may only contain A-Z, a-z, 0-9, _, -, and .."),
				'initial' => $this->record->rdata,
				'widget_attrs' => array ('autocomplete' => 'off', 'size' => 60),
		));
	}
	
	public function clean_hostname () {
		/* Limpieza general de los registros. */
		$hostname = $this->cleaned_data['hostname'];
		
		if (filter_var ($hostname, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) == false) {
			throw new Gatuf_Form_Invalid (__('Invalid domain name'));
		}
		
		/* Para el hostname solo asegurarnos que tenga el punto al final */
		$hostname = trim ($hostname);
		
		if (substr ($hostname, -1) != '.') {
			$hostname = $hostname . '.';
		}
		
		return $hostname;
	}
	
	public function save ($commit = true) {
		if (!$this->isValid()) {
			throw new Exception (__('Cannot save an invalid form.'));
		}
		
		$this->record->rdata = $this->cleaned_data ['hostname'];
		
		if ($commit) {
			$this->record->update ();
		}
		
		return $this->record;
	}
}

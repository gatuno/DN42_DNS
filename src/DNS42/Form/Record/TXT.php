<?php

class DNS42_Form_Record_TXT extends Gatuf_Form {
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
		
		$this->fields['txt'] = new Gatuf_Form_Field_Varchar (
			array (
				'required' => true,
				'label' => __('Text data'),
				'help_text' => __("Text data may only contain printable ASCII characters. Very long lines will be automatically broken into multiple 255 character segments."),
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
		
		if (filter_var ($name, FILTER_VALIDATE_DOMAIN) == false) {
			throw new Gatuf_Form_Invalid (__('Invalid domain name'));
		}
		
		return $name;
	}
	
	public function clean_txt () {
		$txt = $this->cleaned_data['txt'];
		
		preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $txt);
		
		return $txt;
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
		
		return $this->cleaned_data;
	}
	public function save ($commit = true) {
		if (!$this->isValid()) {
			throw new Exception (__('Cannot save an invalid form.'));
		}
		
		$record = new DNS42_Record ();
		
		do {
			$record->dominio = $this->dominio;
			$record->name = $this->cleaned_data ['name'];
			$record->type = 'TXT';
			$txt = $this->cleaned_data ['ttl'];
			$record->ttl = substr ($txt, 0, 255);
			$txt = substr ($txt, 255);
			$record->rdata = $this->cleaned_data ['txt'];
		
			$record->create ();
		} while (strlen ($txt) > 0);
		
		return $record;
	}
}

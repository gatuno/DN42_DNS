<?php

class DNS42_Form_Record_Update_CNAME extends Gatuf_Form {
	private $record;
	private $dominio;
	public function initFields($extra=array()) {
		$this->record = $extra['record'];
		$this->dominio = $this->record->get_dominio ();
		$this->fields['name'] = new Gatuf_Form_Field_Varchar (
			array (
				'required' => true,
				'label' => __('Name'),
				'help_text' => __("A name may only contain A-Z, a-z, 0-9, _, -, or .. '@' or the hostname may be used where appropriate."),
				'initial' => $this->record->name,
				'widget_attrs' => array ('autocomplete' => 'off', 'size' => 60),
		));
		
		$this->fields['cname'] = new Gatuf_Form_Field_Varchar (
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
	
	public function clean_name () {
		$name = $this->cleaned_data['name'];
		
		if ($name == '@') return '@';
		
		if (filter_var ($name, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) == false) {
			throw new Gatuf_Form_Invalid (__('Invalid domain name'));
		}
		
		return $name;
	}
	
	public function clean_cname () {
		$cname = $this->cleaned_data['cname'];
		
		if (filter_var ($cname, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) == false) {
			throw new Gatuf_Form_Invalid (__('Invalid domain name'));
		}
		
		return $cname;
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
		
		if ($name == $this->dominio->dominio) {
			throw new Gatuf_Form_Invalid (__('CNAME at zone apex is not allowed. (rfc1912 & rfc2181)'));
		}
		
		$this->cleaned_data['name'] = $name;
		
		/* Para el CNAME solo asegurarnos que tenga el punto al final */
		$cname = trim ($this->cleaned_data['cname']);
		
		if (substr ($cname, -1) != '.') {
			$cname = $cname . '.';
		}
		
		$this->cleaned_data['cname'] = $cname;
		
		/* Un registro con el mismo nombre, mismo tipo y mismo valor no puede existir duplicado */
		$sql = new Gatuf_SQL ('type="CNAME" AND dominio=%s AND name=%s AND rdata=%s AND id != %s', array ($this->dominio->id, $this->cleaned_data['name'], $this->cleaned_data['cname'], $this->record->id));
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
		
		$this->record->name = $this->cleaned_data ['name'];
		$this->record->ttl = $this->cleaned_data ['ttl'];
		$this->record->rdata = $this->cleaned_data ['cname'];
		
		if ($commit) {
			$this->record->update ();
		}
		
		return $this->record;
	}
}

<?php

class DNS42_Form_Record_Update_A extends Gatuf_Form {
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
		
		$this->fields['ipv4'] = new Gatuf_Form_Field_Varchar (
			array (
				'required' => true,
				'label' => __('IPv4 Address'),
				'help_text' => __("An IPv4 address must be a decimal dotted quad string, for example: '192.168.123.10'"),
				'initial' => $this->record->rdata,
				'widget_attrs' => array ('autocomplete' => 'off'),
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
		
		$ipv4 = inet_ntop (inet_pton ($this->cleaned_data['ipv4']));
		$this->cleaned_data['ipv4'] = $ipv4;
		
		/* Si está actualizando un registro, no puede duplicar datos con otros registros con los mismos valores */
		$sql = new Gatuf_SQL ('type="A" AND dominio=%s AND name=%s AND rdata=%s AND id != %s', array ($this->dominio->id, $this->cleaned_data['name'], $this->cleaned_data['ipv4'], $this->record->id));
		$records_c = Gatuf::factory ('DNS42_Record')->getList (array ('filter' => $sql->gen (), 'count' => true));
		if ($records_c > 0) {
			throw new Gatuf_Form_Invalid (__('The update record data duplicates in this zone with the same name and value'));
		}
		
		return $this->cleaned_data;
	}
	
	public function save ($commit = true) {
		if (!$this->isValid()) {
			throw new Exception (__('Cannot save an invalid form.'));
		}
		
		$this->record->name = $this->cleaned_data ['name'];
		$this->record->ttl = $this->cleaned_data ['ttl'];
		$this->record->rdata = $this->cleaned_data ['ipv4'];
		
		if ($commit) {
			$this->record->update ();
		}
		
		return $this->record;
	}
}

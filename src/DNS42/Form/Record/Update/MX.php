<?php

class DNS42_Form_Record_Update_MX extends Gatuf_Form {
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
		
		$toks = explode (" ", $this->record->rdata, 2);
		$this->fields['priority'] = new Gatuf_Form_Field_Integer (
			array (
				'required' => true,
				'label' => __('Priority'),
				'help_text' => __("To differentiate them, each MX record has a priority (lower the number, higher the priority). The MX record with the highest priority is the actual target computer where mail boxes are located. '10' is a good default."),
				'initial' => $toks[0],
				'min' => 0,
				'max' => 65535,
				'widget_attrs' => array ('autocomplete' => 'off'),
		));
		
		$this->fields['hostname'] = new Gatuf_Form_Field_Varchar (
			array (
				'required' => true,
				'label' => __('Hostname'),
				'help_text' => __("A hostname should be valid and may only contain A-Z, a-z, 0-9, _, -, and .. An mx may never be an ip/ipv6 address, and must not point to a cname. Entering incorrect information here can negatively impact your ability to receive and in some cases send mail."),
				'initial' => $toks[1],
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
		$rdata = sprintf ("%s %s", $this->cleaned_data['priority'], $this->cleaned_data ['hostname']);
		
		/* Un registro con el mismo nombre, mismo tipo y mismo valor no puede existir duplicado */
		$sql = new Gatuf_SQL ('type="MX" AND dominio=%s AND name=%s AND rdata=%s AND id != %s', array ($this->dominio->id, $this->cleaned_data['name'], $rdata, $this->record->id));
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
		
		$rdata = sprintf ("%s %s", $this->cleaned_data['priority'], $this->cleaned_data ['hostname']);
		$this->record->name = $this->cleaned_data ['name'];
		$this->record->ttl = $this->cleaned_data ['ttl'];
		$this->record->rdata = $rdata;
		
		if ($commit) {
			$this->record->update ();
		}
		
		return $this->record;
	}
}

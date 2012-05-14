<?php

/**
 * 
 * Extension for creating translatable DataObjects.
 * 
 * You can specify which fields to translate in a static 'translatableFields'-array in your data-class (that extends DataObject).
 * 
 * Please remember to extend the getCMSFields-method with 'updateCMSFields' if you override the default one in DataObject
 * (without calling parent::getCMSFields()), like for example:
 * 	function getCMSFields() {
 * 		$fields = new FieldSet();
 * 		... adding fields etc. ...
 * 		$this->extend('updateCMSFields', $fields);
 * 		return $fields;
 * 	}
 * otherwise automatic field-replacing won't work.
 * 
 * @author Niklas Forsdahl
 *
 */
class TranslatableDataObject extends DataObjectDecorator {
	
	// custom CSS to make the translation group fields look nice
	private $customCSS = '.translationGroup .fieldgroupField label {display: block;}
						  .translationGroup .fieldgroupField input {width: 99% !important;}';
	
	/**
	 * Create a translation field name
	 * (by appending underscore and the locale name to the original field name).
	 */
	private function getLocaleFieldName($originalFieldName, $locale) {
		return $originalFieldName . '_' . $locale;
	}
	
	/**
	 * Add translated columns to the database table.
	 */
	function augmentDatabase() {
		$ownerClass = $this->owner->class;
		foreach (Object::get_static($ownerClass, 'translatableFields') as $field) {
			// find field specification from dataobject's db-fields
			$fieldSpec = $this->owner->db($field);
			if ($fieldSpec) {
				foreach (Translatable::get_allowed_locales() as $locale) {
					$localeFieldName = $this->getLocaleFieldName($field, $locale);
					// create a field object from the specification, with the localized name
					$fieldObj = Object::create_from_string($fieldSpec, $localeFieldName);
					$fieldObj->setTable($ownerClass);
					$fieldObj->requireField();
				}
			}
		}
	}
	
	/**
	 * Fix SQL queries for this DataObject so that translated fields are included in the object
	 * created from the database table.
	 */
	function augmentSQL(SQLQuery &$query) {
		$currentLocale = Translatable::get_current_locale();
		$ownerClass = $this->owner->class;
		foreach (Object::get_static($ownerClass, 'translatableFields') as $field) {
			// select the original field as the translated field for current locale
			$localeFieldName = $this->getLocaleFieldName($field, $currentLocale);
			$query->select[$field] = "\"$ownerClass\".\"$localeFieldName\" AS $field";
			foreach (Translatable::get_allowed_locales() as $locale) {
				$localeFieldName = $this->getLocaleFieldName($field, $locale);
				$query->select[$localeFieldName] = "\"$ownerClass\".\"$localeFieldName\"";
			}
		}
	}
	
	/**
	 * Fix writing so that translated fields get written to the database.
	 */
	function augmentWrite(&$manipulation) {
		$defaultLocale = Translatable::default_locale();
		$ownerClass = $this->owner->class;
		foreach (Object::get_static($ownerClass, 'translatableFields') as $field) {
			// write the translated field value of the default locale to the original field
			$localeFieldName = $this->getLocaleFieldName($field, $defaultLocale);
			$defaultFieldValue = $this->owner->$localeFieldName;
			if ($defaultFieldValue == '') {
				// we always need to write to the default locale
				// find the first non-empty value
				foreach (Translatable::get_allowed_locales() as $locale) {
					$localeFieldName = $this->getLocaleFieldName($field, $locale);
					$localeFieldValue = $this->owner->$localeFieldName;
					if ($localeFieldValue != '') {
						$defaultFieldValue = $localeFieldValue;
						break;
					}
				}
			}
			$manipulation[$this->owner->class]['fields'][$field] = "'" . $defaultFieldValue . "'";
			
			foreach (Translatable::get_allowed_locales() as $locale) {
				$localeFieldName = $this->getLocaleFieldName($field, $locale);
				$localeFieldValue = $this->owner->$localeFieldName;
				if ($localeFieldValue == '') {
					$localeFieldValue = $defaultFieldValue;
				}
				$manipulation[$this->owner->class]['fields'][$localeFieldName] = "'" . $localeFieldValue . "'";
			}
		}
	}
	
	/**
	 * Updates the CMS fields for fields that are marked as translatable by replacing the original
	 * field with a fieldgroup containing one field (of the original type) for each allowed language.
	 */
	function updateCMSFields(FieldSet &$fields) {
		$ownerClass = $this->owner->class;
		foreach (Object::get_static($ownerClass, 'translatableFields') as $field) {
			$existingField = $fields->dataFieldByName($field);
			// only replace existing formfields of the specified type
			if ($existingField) {
				$containerField = new FieldGroup($existingField->Title());
				$containerField->addExtraClass('translationGroup');
				foreach (Translatable::get_allowed_locales() as $locale) {
					$localeFieldName = $this->getLocaleFieldName($field, $locale);
					// create a new instance of the field class, with locale as name
					$containerField->push(new $existingField->class($localeFieldName, i18n::$common_locales[$locale][1]));
				}
				// rename existing field so that we can insert the new field group in the old field's place
				$existingField->setName($existingField->Name().'_old');
				$existingField->setTitle($existingField->Title().'_old');
				$fields->insertAfter($containerField, $existingField->Name());
				$fields->removeByName($existingField->Name());
			}
		}
		
		// require custom css to make the translation group field look nice(er)
		Requirements::customCSS($this->customCSS);
	}
	
	/**
	 * Also add custom css to dataobject-manager popups, because dataobject-manager clears all requirements.
	 */
	function getRequirementsForPopup() {
		Requirements::customCSS($this->customCSS);
	}
	
	function getTranslatedValue($fieldName) {
		$ownerClass = $this->owner->class;
		foreach (Object::get_static($ownerClass, 'translatableFields') as $field) {
			if ($field == $fieldName) {
				$translatedFieldName = $this->getLocaleFieldName($field, Translatable::get_current_locale());
				return $this->owner->$translatedFieldName;
			}
		}
		return $this->owner->$fieldName;
	}
	
	/**
	 * Set the value of a translated field for all languages based on a translation.
	 */
	function setTranslatedValue($fieldName, $translationKey, $defaultValue = '', $append = false) {
		global $lang;
		$ownerClass = $this->owner->class;
		foreach (Object::get_static($ownerClass, 'translatableFields') as $field) {
			if ($field == $fieldName) {
				$storedLocale = i18n::get_locale();
				foreach (Translatable::get_allowed_locales() as $allowedLocale) {
					i18n::set_locale($allowedLocale);
					$translatedFieldName = $this->getLocaleFieldName($field, $allowedLocale);
					$this->owner->$translatedFieldName = ($append ? $this->owner->$translatedFieldName : '') . _t($translationKey, $defaultValue);
				}
				i18n::set_locale($storedLocale);
			}
		}
	}
	
}

?>
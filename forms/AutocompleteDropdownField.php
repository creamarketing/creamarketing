<?php

class AutocompleteDropdownField extends FormField {
	
	protected $sourceClass = '';
	protected $valueProperty = 'Title';
	protected $showAddButton = false;
	
	function __construct($name, $title = null, $sourceClass = "", $valueProperty = '', $value = "", $showAddButton = false, $form = null) {
		parent::__construct($name, ($title===null) ? $name : $title, $value, $form);
		
		$this->sourceClass = $sourceClass;
		if ($valueProperty) {
			$this->valueProperty = $valueProperty;
		}
		
		$this->showAddButton = $showAddButton;
	}
	
	function Field() {
		Requirements::javascript(THIRDPARTY_DIR . '/jquery/jquery.js');
		Requirements::javascript(THIRDPARTY_DIR . '/jquery-ui/jquery-ui-1.8rc3.custom.js');
		Requirements::javascript(THIRDPARTY_DIR . '/jquery-ui/jquery.ui.autocomplete.js');
		Requirements::javascript(THIRDPARTY_DIR . '/jquery-ui/jquery.ui.dialog.js');
		Requirements::css(THIRDPARTY_DIR . '/jquery-ui-themes/smoothness/jquery-ui-1.8rc3.custom.css');
		Requirements::javascript(THIRDPARTY_DIR . '/jquery-form/jquery.form.js');
		$customJS = "
						jQuery('.autocomplete-dropdown').autocomplete({
							source: '{$this->Link()}/items',
							select: function(event, ui) {
								jQuery('#{$this->id()}').val(ui.item.id);
							}
						 });
					 ";
		
		if ($this->showAddButton) {
			$customJS .= "
							jQuery('.autocomplete-addButton').click(function() {
								var content = document.createElement('div');
								jQuery.ajax({
									async: false,
									url: '{$this->Link()}/AddFormHTML',
									dataType: 'html',
									success: function(data){
										content.innerHTML = data;
									},
									error: function() {
										alert('error');
									}
								});
								
								jQuery(content).addClass('right');
							 	jQuery(content).dialog({
									title: 'Add {$this->sourceClass}',
									modal: true,
									buttons: {
										'Ok': function() {
											var dialog = jQuery(this);
											jQuery(this).find('form').ajaxSubmit({
												success: function(responseText, statusText, xhr, form) {
													var parts = responseText.split(':', 2);
													if (parts.length == 2) {
														var id = parts[0];
														var name = parts[1];
														jQuery('#{$this->id()}').val(id);
														jQuery('#{$this->id()}_autocomplete').val(name);
													}
													dialog.dialog('close');
												},
												error: function(XMLHttpRequest, textStatus, errorThrown) {
													alert(XMLHttpRequest.responseText);
												}
											});
										},
										'Cancel': function() {
											jQuery(this).dialog('close');
										}
									}
								});
							 });
						 ";
		}
		Requirements::customScript($customJS);
		$customCSS = ".ui-autocomplete-loading {
					      background: white url('crea/images/ui-anim_basic_16x16.gif') right center no-repeat;
					  }
					  .autocomplete-dropdown {
					      height: 18px;
					  }";
		Requirements::customCSS($customCSS);
		
		$attributes = array(
			'class' => ($this->extraClass() ? $this->extraClass() : ''),
			'id' => $this->id(),
			'name' => $this->name,
			'type' => 'hidden',
			'value' => $this->value
		);
		
		$html = $this->createTag('input', $attributes);
		
		if (is_numeric($this->value) && $this->value > 0) {
			$selectedDataObject = DataObject::get_by_id($this->sourceClass, $this->value);
			if ($selectedDataObject) {
				$valueFromId = $selectedDataObject->{$this->valueProperty};
			} else {
				$valueFromId = 'missing object';
			}
		} else {
			$valueFromId = '';
		}
		
		$attributes = array(
			'class' => 'autocomplete-dropdown',
			'id' => $this->id() . '_autocomplete',
			'name' => $this->name . '_autocomplete',
			'type' => 'text',
			'value' => $valueFromId
		);
		
		$html .= $this->createTag('input', $attributes);
		
		if ($this->showAddButton) {
			$attributes = array(
				'class' => 'autocomplete-addButton',
				'id' => $this->id() . '_addButton',
				'name' => $this->name . '_addButton',
				'type' => 'button',
				'value' => 'Add ' . $this->sourceClass
			);
			
			$html .= $this->createTag('input', $attributes);
		}
		
		return $html;
	}
	
	public function AddForm() {
		$sourceObject = singleton($this->sourceClass);
		$fields = $sourceObject->getCMSFields();
		
		// fake form action with a hidden field with name 'action_ACTION'
		$fields->push(new HiddenField('action_AddObject', 'action_AddObject', 'add'));
		
		$actions = new FieldSet();
		
		$form = new Form($this, 'AddForm', $fields, $actions);
		$form->loadDataFrom($sourceObject);
		return $form;
	}
	
	public function AddObject($data, $form) {
		$sourceObject = new $this->sourceClass();
		$sourceObject->write();
		$form->saveInto($sourceObject);
		$sourceObject->write();
		$valueProperty = $this->valueProperty;
		$value = '';
		if (method_exists($sourceObject, $valueProperty)) {
			$value = $sourceObject->$valueProperty();
		}
		else {
			$value = $sourceObject->$valueProperty;
		}
		return $sourceObject->ID . ':' . $value;
	}
	
	public function AddFormHTML() {
		return $this->AddForm()->forTemplate();
	}
	
	public function Link($action=null) {
		$link = parent::Link($action);
		$link = split('\?', $link);
		return $link[0];
	}
	
	public function items() {
		$search = Convert::raw2sql($_GET['term']);
		$items = array();
		
		$objects = $this->getResults($this->sourceClass, array('Search' => $search));
		if ($objects) {
			$valueProperty = $this->valueProperty;
			foreach ($objects as $object) {
				$value = '';
				if (method_exists($object, $valueProperty)) {
					$value = $object->$valueProperty();
				}
				else {
					$value = $object->$valueProperty;
				}
				$items[] = array(
					'id' => $object->ID,
					'value' => $value
				);
			}
		}
		
		// return response as JSON-data
		$response = new SS_HTTPResponse(json_encode($items));
		$response->addHeader("Content-type", "application/json");
		return $response;
	}
	
	public function getResults($searchClass, $data = null, $pageLength = null){
	 	// legacy usage: $data was defaulting to $_REQUEST, parameter not passed in doc.silverstripe.org tutorials
		if(!isset($data) || !is_array($data)) $data = $_REQUEST;
		
		// set language (if present)
		if(singleton('SiteTree')->hasExtension('Translatable') && isset($data['locale'])) {
			$origLocale = Translatable::get_current_locale();
			Translatable::set_current_locale($data['locale']);
		}
	
		$keywords = $data['Search'];

	 	$andProcessor = create_function('$matches','
	 		return " +" . $matches[2] . " +" . $matches[4] . " ";
	 	');
	 	$notProcessor = create_function('$matches', '
	 		return " -" . $matches[3];
	 	');

	 	$keywords = preg_replace_callback('/()("[^()"]+")( and )("[^"()]+")()/i', $andProcessor, $keywords);
	 	$keywords = preg_replace_callback('/(^| )([^() ]+)( and )([^ ()]+)( |$)/i', $andProcessor, $keywords);
		$keywords = preg_replace_callback('/(^| )(not )("[^"()]+")/i', $notProcessor, $keywords);
		$keywords = preg_replace_callback('/(^| )(not )([^() ]+)( |$)/i', $notProcessor, $keywords);
		
		$keywords = $this->addStarsToKeywords($keywords);

		if(!$pageLength) $pageLength = $this->pageLength;
		$start = isset($_GET['start']) ? (int)$_GET['start'] : 0;
		
		$booleanSearch = false;
		if(strpos($keywords, '"') !== false || strpos($keywords, '+') !== false || strpos($keywords, '-') !== false || strpos($keywords, '*') !== false) {
			$booleanSearch = true;
		}
		$results = $this->searchEngine(array($searchClass), $keywords, $start, $pageLength, $booleanSearch);
		
		// filter by permission
		if($results) foreach($results as $result) {
			if(!$result->canView()) $results->remove($result);
		}
		
		// reset locale
		if(singleton('SiteTree')->hasExtension('Translatable') && isset($data['locale'])) {
			Translatable::set_current_locale($origLocale);
		}

		return $results;
	}
	
	protected function addStarsToKeywords($keywords) {
		if(!trim($keywords)) return "";
		// Add * to each keyword
		$splitWords = preg_split("/ +/" , trim($keywords));
		while(list($i,$word) = each($splitWords)) {
			if($word[0] == '"') {
				while(list($i,$subword) = each($splitWords)) {
					$word .= ' ' . $subword;
					if(substr($subword,-1) == '"') break;
				}
			} else {
				$word .= '*';
			}
			$newWords[] = $word;
		}
		return implode(" ", $newWords);
	}
	
	/**
	 * The core search engine, used by this class and its subclasses to do fun stuff.
	 * Searches any specified DataObject.
	 * 
	 * @param string $keywords Keywords as a string.
	 */
	private function searchEngine($classesToSearch, $keywords, $start, $pageLength, $booleanSearch = false, $sortBy = "Relevance DESC", $extraFilter = array(), $invertedMatch = false) {
		$fileFilter = '';	 	
	 	$keywords = Convert::raw2sql($keywords);
		$htmlEntityKeywords = htmlentities($keywords);
		
		$extraFilters = array();
		foreach ($classesToSearch as $class) {
			$extraFilters[$class] = '';
			$baseClasses[$class] = '';
			$extraFilters[$class] = '';
			$match[$class] = "1 = 1";
			$relevance[$class] = 1;
		}
	 	
	 	if($booleanSearch) $boolean = "IN BOOLEAN MODE";
	
	 	foreach ($extraFilter as $class => $filter) {
	 		$extraFilters[$class] = " AND $filter";
	 	}
	 	
		// Always ensure that only pages with ShowInSearch = 1 can be searched
		if (isset($extraFilters['SiteTree']))
			$extraFilters['SiteTree'] .= " AND ShowInSearch <> 0";

		$limit = $start . ", " . (int) $pageLength;
		
		$notMatch = $invertedMatch ? "NOT " : "";
		if($keywords) {
			// We make the relevance search by converting a boolean mode search into a normal one
			$relevanceKeywords = str_replace(array('*','+','-'),'',$keywords);
			$htmlEntityRelevanceKeywords = str_replace(array('*','+','-'),'',$htmlEntityKeywords);
			
			foreach($classesToSearch as $class) {
				if ($class == 'SiteTree') {
					$match['SiteTree'] = "
						MATCH (Title, MenuTitle, Content, MetaTitle, MetaDescription, MetaKeywords) AGAINST ('$keywords' $boolean)
						+ MATCH (Title, MenuTitle, Content, MetaTitle, MetaDescription, MetaKeywords) AGAINST ('$htmlEntityKeywords' $boolean)";
					$relevance['SiteTree'] = "MATCH (Title, MenuTitle, Content, MetaTitle, MetaDescription, MetaKeywords) AGAINST ('$relevanceKeywords') + MATCH (Title, MenuTitle, Content, MetaTitle, MetaDescription, MetaKeywords) AGAINST ('$htmlEntityRelevanceKeywords')";
				}
				else if ($class == 'File') {
					$match['File'] = "MATCH (Filename, Title, Content) AGAINST ('$keywords' $boolean) AND ClassName = 'File'";
					$relevance['File'] = "MATCH (Filename, Title, Content) AGAINST ('$relevanceKeywords')";
				}
				else {
					$searchable_fields_list = array();
					// need to do an php eval, since php < 5.3.0 doesn't support accesing static variabales dynamically
					eval('if (property_exists("'. $class . '", "searchable_fields")) $searchable_fields_list = ' . $class . '::$searchable_fields;');
					$i = 0;
					$match[$class] = '';
					$relevance[$class] = '';
					foreach ($searchable_fields_list as $searchable_field) {
						$field = "$class.$searchable_field";
						// Fix for selecting with the whole tbl.columnname for Tr
						if ( substr($searchable_field,0,4) == 'Trs.' ) {
							$field = str_replace('Trs.', $class.'Tr.', $searchable_field);
						}
						$match[$class] .= "MATCH ($field) AGAINST ('$keywords' $boolean)";
						$relevance[$class] .= "MATCH ($field) AGAINST ('$relevanceKeywords')";
						if ($i < count($searchable_fields_list) - 1) {
							$match[$class] .= " OR ";
							$relevance[$class] .= " + ";
						}
						$i++;
					}
					
					// check base class name from original query
					$baseClass = reset(singleton($class)->extendedSQL()->from);
					$baseClassName = str_replace(array('`','"'),'',$baseClass);
					
					// if base class name is not equal to the class name, include base class searchable fields in match
					if ($baseClassName != $class) {
						$searchable_fields_list = array();
						eval('if (property_exists("'. $baseClassName . '", "searchable_fields")) $searchable_fields_list = ' . $baseClassName . '::$searchable_fields;');
						$i = 0;
						foreach ($searchable_fields_list as $searchable_field) {
							if ($i == 0) {
								$match[$class] .= " OR ";
								$relevance[$class] .= " + ";
							}
							$match[$class] .= "MATCH ($baseClassName.$searchable_field) AGAINST ('$keywords' $boolean)";
							$relevance[$class] .= "MATCH ($baseClassName.$searchable_field) AGAINST ('$relevanceKeywords')";
							if ($i < count($searchable_fields_list) - 1) {
								$match[$class] .= " OR ";
								$relevance[$class] .= " + ";
							}
							$i++;
						}
					}
				}
			}
		}

		// Generate initial queries and base table names
		$queries = array();
		foreach($classesToSearch as $class) {
			$queries[$class] = singleton($class)->extendedSQL($notMatch . $match[$class] . $extraFilters[$class], "");
			$baseClasses[$class] = reset($queries[$class]->from);
		}
		
		// Make column selection lists
		$select = array();
		foreach($classesToSearch as $class) {
			if ($class == 'SiteTree') {
				$select['SiteTree'] = array("ClassName","{$baseClasses['SiteTree']}.ID","ParentID","Title","MenuTitle","URLSegment","Content","LastEdited","Created","_utf8'' AS Filename", "_utf8'' AS Name", "$relevance[SiteTree] AS Relevance", "CanViewType");
				$queries[$class]->from = array(str_replace('`','',$baseClasses[$class]) => $baseClasses[$class]);
			}
			else if ($class == 'File') {
				$select['File'] = array("ClassName","{$baseClasses['File']}.ID","_utf8'' AS ParentID","Title","_utf8'' AS MenuTitle","_utf8'' AS URLSegment","Content","LastEdited","Created","Filename","Name","$relevance[File] AS Relevance","NULL AS CanViewType");
				$queries[$class]->from = array(str_replace('`','',$baseClasses[$class]) => $baseClasses[$class]);
			}
			else {
			
				// Joining in all from getDefaultSearchContext
				$context = singleton($class)->getDefaultSearchContext();
				$query = $context->getQuery(array());
				$from = null;
				foreach ($query->from as $joinTbl => $joinQuery) {											
					$joinQuery = str_replace(array('`','"'),'',$joinQuery);	
					$from[$joinTbl] = $joinQuery;					
				}				
				
				$queries[$class]->from = $from;
			
				// Selecting all from getDefaultSearchContext
				$select[$class] = null;
				foreach ($query->select as $selectField => $selectQuery) {				
					$select[$class][$selectField] = str_replace(array('`','"'),'', $selectQuery);
				}
			}
			
			$queries[$class]->select = $select[$class];
			
			$queries[$class]->orderby = $query->orderby;//null;
		}

		$results = array();
		// Do queries one by one and append query result to the results array
		$totalCount = 0;
		foreach($queries as $query) {			
			$results[] = DB::query($query->sql());			
			$totalCount += $query->unlimitedRowCount();
		}

		foreach ($results as $records) {
			foreach($records as $record) {				
				$objects[] = new $record['ClassName']($record);
			}
		}
			
		if(isset($objects)) $doSet = new DataObjectSet($objects);
		else $doSet = new DataObjectSet();
		
		$doSet->setPageLimits($start, $pageLength, $totalCount);
		return $doSet;
	}
	
}

?>
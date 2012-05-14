function AdvancedDropdownField_showWithCheckbox(id, request, response) {
	var firstSelectedItem = jQuery('#' + id + 'First');
	var matcher = new RegExp('\\b' + jQuery.ui.autocomplete.escapeRegex(request.term), 'i');
	response(jQuery('#' + id + 'Select').children('option:enabled').map(function(){
		if (jQuery(this).val() == 0 || matcher.test(jQuery(this).text())) {
			var checked = '';
			var first = '';
			var selectedItems = jQuery('#' + id + 'Select option.selected');
			var multipleValues = jQuery(this).val().split(',');	
			
			for (var i = 0; i < selectedItems.length; i++) {
				if (selectedItems[i].value == jQuery(this).val() ||
					(multipleValues.length && selectedItems[i].value in multipleValues)) {
					checked = 'checked = "checked"';
					if (firstSelectedItem.length && selectedItems[i].value == firstSelectedItem.val())
						first = ' class="first"';
					break;
				}
			}
			var text = jQuery(this).text();
			if (jQuery(this).val() != '') {
				text = '<input type=\"checkbox\" ' + checked + ' />' + '<span' + first + '>' + jQuery(this).text() + '</span>';
			}
			return {
				label: text,
				value: jQuery(this).text(),
				option: this
			};
		}
	}));
}

function AdvancedDropdownField_selectCheckbox(id, event, ui) {
	var firstItem = jQuery('#' + id + "First");
	
	if (ui.item.option.value == '') {
		jQuery('#' + id + 'Select option.selected').removeClass('selected');
		jQuery('#' + id).val('');
		
		if (firstItem.length)
			firstItem.val('');
			
		return true;
	}
	
	if (jQuery(ui.item.option).hasClass('selected')) {
		jQuery(ui.item.option).removeClass('selected');
		
		if (firstItem.length && firstItem.val() == jQuery(ui.item.option).val())
			firstItem.val('');
	}
	else {
		jQuery(ui.item.option).addClass('selected');
		
		if (firstItem.length && firstItem.val() == '')
			firstItem.val(jQuery(ui.item.option).val());
	}
	
	var selectedItems = jQuery('#' + id + 'Select option.selected');
	var selection = '';
	var selectionText = '';
	for (var i = 0; i < selectedItems.length; i++) {
		if (firstItem.length && firstItem.val() == '')
			firstItem.val(selectedItems[i].value);
		
		if (selection != '') {
			selection += ',';
		}
		selection += selectedItems[i].value;
		
		if (selectionText != '') {
			selectionText += ', ';
		}
		selectionText += selectedItems[i].innerHTML;
	}
	jQuery('#' + id).val(selection);
	jQuery('#' + id + 'Text').val(selectionText);
	
	return false;
}

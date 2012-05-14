/**
 * Initialize an advanced autocomplete dropdown field.
 * @param String   filterID ID of the filter
 * @param function onSelect	function to call on item selection
 * @param function getSource optional function to call on source generation
 */
function AdvancedDropdownField(filterID, onSelect, getSource) {
	jQuery('#'+filterID+'Text').autocomplete({
		minLength: 0,
		source: function(request, response) {
			if (getSource) {
				getSource(request, response, filterID);
			}
			else {
				var matcher = new RegExp('\\b' + jQuery.ui.autocomplete.escapeRegex(request.term), 'i');
				response(jQuery('#' + filterID + 'Select').children("option:enabled").map(function(){
					if (jQuery(this).val() == 0 || matcher.test(jQuery(this).text())) {
						var text = '';
						if (jQuery(this).hasClass('level1')) {
							text = '&nbsp;&nbsp;' + jQuery(this).text();
						}
						else {
							text = jQuery(this).text();
						}
						return {
							label: text,
							value: jQuery(this).text(),
							option: this
						};
					}
				}));
			}
		},
		select: function(event, ui) {
			itemSelected = true;
			var retval = true;
			
			if (onSelect) {
				retval = onSelect(event, ui, filterID);
			}
			else {
				jQuery('#' + filterID).val(jQuery(ui.item.option).val());
			}

			if (itemSelected) {
				jQuery('#' + filterID + 'Select').val(jQuery(ui.item.option).val());
				jQuery('#' + filterID).trigger('change');
			}
			
			return retval;			
		},
		focus: function(event, ui) {
			return false;
		},
		close: function(event, ui) {
			if (!itemSelected) {
				jQuery('#'+filterID+'Text').val(prevFilterText);
			}
			window.setTimeout("jQuery('#"+filterID+"Text').blur();", 10);
			dropdownVisible = false;
		}
	})
	.data( "autocomplete" )._renderItem = function( ul, item ) {
			return jQuery( "<li></li>" )
				.data( "item.autocomplete", item )
				.append( "<a>" + item.label + "</a>" )
				.appendTo( ul );
	};
	var prevFilterText = jQuery('#'+filterID+'Text').val();
	var itemSelected = false;
	var dropdownVisible = false;
	jQuery('#'+filterID+'Text').click(function() {
		if (dropdownVisible) {
			jQuery('#'+filterID+'Text').autocomplete('close');
		}
		else {
			dropdownVisible = true;
			prevFilterText = jQuery('#'+filterID+'Text').val();
			itemSelected = false;
			document.getElementById(filterID + 'Text').select();
			jQuery('#'+filterID+'Text').autocomplete('search', '');
		}
	});
}

function ShowAddOrEditDialog(id, link, title) {
	var content = document.createElement('div');
	var ajaxLoader = '<div id="DialogAjaxLoader"><h2>Loading...</h2><img src="dataobject_manager/images/ajax-loader-white.gif" alt="Loading in progress..." /></div>';
	content.innerHTML = ajaxLoader;
	
	var parentDialog = jQuery('.ui-dialog').last();
	if (parentDialog.html()) {
		jQuery(parentDialog).animate({
			left: '-=200',
			top: '-=50'
		},
		800);
	}
	
	jQuery(content).addClass('right');
 	jQuery(content).dialog({
		title: title,
		modal: true,
		buttons: {
			'Ok': function() {
				var dialog = jQuery(this);
				jQuery('.ui-button:button').attr('disabled',true).addClass('ui-state-disabled');
				jQuery(this).find('form').ajaxSubmit({
					success: function(responseText, statusText, xhr, form) {
						var parts = responseText.split(':', 2);
						if (parts.length == 2) {
							var newId = parts[0];
							var name = parts[1];
							jQuery('#' + id).val(newId);
							jQuery('#' + id + 'Text').val(name);
							if (jQuery('#' + id + 'Select option[value="' + newId + '"]').html()) {
								jQuery('#' + id + 'Select option[value="' + newId + '"]').html(name);
							}
							else {
								jQuery('#' + id + 'Select').append('<option value="' + newId + '">' + name + '</option>');
							}
						}
						dialog.dialog('close');
						
						jQuery('.ui-button:button').attr('disabled',false).removeClass('ui-state-disabled');
					},
					error: function(XMLHttpRequest, textStatus, errorThrown) {
						alert(XMLHttpRequest.responseText);
						jQuery('.ui-button:button').attr('disabled',false).removeClass('ui-state-disabled');
					}
				});
			},
			'Cancel': function() {
				jQuery(this).dialog('close');
			}
		},
		width: 600,
		height: 600,
		open: function() {
			jQuery('.ui-button:button').attr('disabled',true).addClass('ui-state-disabled');
		},
		close: function() {
			// move the parent dialog back
			if (parentDialog.html()) {
				jQuery(parentDialog).animate({
					left: '+=200',
					top: '+=50'
				},
				800);
			}
			// remove the dialog from the DOM, so that we do not leave a lot of unecessary data in the DOM tree
			jQuery(this).remove();
		}
	});
	
	jQuery.ajax({
		async: false,
		url: link,
		dataType: 'html',
		success: function(data){
			content.innerHTML = data;
			// open tabs, if present
			jQuery(content).find('div.dialogtabset').tabs();
			jQuery('.ui-button:button').attr('disabled',false).removeClass('ui-state-disabled');
		},
		error: function() {
			alert('error');
		}
	});
}

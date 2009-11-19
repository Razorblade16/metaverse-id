mv_id_plugin = {
	add_more   : function()
	{
		var li = document.createElement('li');
		var select = document.createElement('select');
		select.name = 'add[' + mv_id_plugin__num_entries + '][metaverse]';
		for(i in mv_id_plugin.metaverses)
		{
			var option = document.createElement('option');
			option.value = mv_id_plugin.metaverses[i];
			option.title = mv_id_plugin.formats[i];
			option.appendChild(document.createTextNode(mv_id_plugin.nice_names[mv_id_plugin.metaverses[i]]));
			select.appendChild(option);
			select.appendChild(document.createTextNode("\n"));
		}
		var input = document.createElement('input');
		input.type = 'text';
		input.maxLength = 255;
		input.name = 'add[' + (mv_id_plugin__num_entries++) + '][id]';
		li.appendChild(select);
		li.appendChild(input);
		var ol = mv_id_plugin__id_div.getElementsByTagName('ol')[0];
		ol.appendChild(li);
	},
	populate_select_mv : function(mv_element,ids_element,instance)
	{
		var select = document.getElementById(mv_element);
				jQuery(select).empty();
		for(i in mv_id_plugin.ids)
		{
			var value = i;
			var option = '<option value="' + value + '"';
			if(instance != undefined && instance.metaverse == value)
			{
				option += ' selected="selected"';
			}
			option += '>' + mv_id_plugin.nice_names[value] + '</option>';
			jQuery(select).append(option + "\n");
		}
		mv_id_plugin.populate_select_id(mv_element,ids_element);
		jQuery(select).change(function(){mv_id_plugin.populate_select_id(mv_element,ids_element,instance)});
		jQuery(select).click(function(){mv_id_plugin.populate_select_id(mv_element,ids_element,instance)});
	},
	populate_select_id : function(mv_element,ids_element,instance)
	{
		var _select = document.getElementById(mv_element);
		var select = document.getElementById(ids_element);
		var options = _select.getElementsByTagName('option');
		for(i in options)
		{
			if(options[i].selected == true)
			{
				jQuery(select).empty();
				for(x in mv_id_plugin.ids[options[i].value])
				{
					var value = mv_id_plugin.ids[options[i].value][x].id;
					var option = '<option value="' + value + '"';
					if(instance != undefined && instance.id == value)
					{
						option += ' selected="selected"';
					}
					option += '>' + value + '</option>';
					jQuery(select).append(option + "\n");
				}
				break;
			}
		}
	}
};
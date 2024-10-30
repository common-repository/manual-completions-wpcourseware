
jQuery(document).ready(function() {
	window.course_structure = manual_completions_wpcourseware.course_structure;

    jQuery('#manual_completions_wpcourseware select.en_select2').select2({width: "100%"});

	jQuery("#select_all").on("change", function() {
		jQuery("tr[data-completion] [type=checkbox]:not([disabled])").prop('checked', jQuery("#select_all").is(":checked"));
		manual_completions_wpcourseware_update_checked_count();
	});

	jQuery("#manual_completions_wpcourseware_table table").click(function(e) {
		if(jQuery(e.target).attr("type") == "checkbox") {
			manual_completions_wpcourseware_update_checked_count();
		}
	});

	jQuery('#users [role="searchbox"]').on("keypress", function(e) {
		manual_completions_wpcourseware_handle_users_keypress(e, ",");
		manual_completions_wpcourseware_handle_users_keypress(e, " ");
	});
	jQuery('#users [role="searchbox"]').on("keyup change", function(e) {
		if( e.which == 13 )
		manual_completions_wpcourseware_filter_users_list();
		else
		setTimeout( () => manual_completions_wpcourseware_filter_users_list(), 1000 );
	});

	if(typeof manual_completions_wpcourseware.uploaded_data == "object" && manual_completions_wpcourseware.uploaded_data.length > 0) {
		jQuery("#manual_completions_wpcourseware #course").hide();

		jQuery.each(manual_completions_wpcourseware.uploaded_data, function(i, data) {
			manual_completions_wpcourseware_add_row(data, i+1);
		});
	}
});
function manual_completions_wpcourseware_update_checked_count() {
	jQuery("#process_completions .count, #check_completions .count").text(" (" + jQuery("#manual_completions_wpcourseware_table input[type=checkbox]:not(#select_all):checked").length + ")");
}
function manual_completions_wpcourseware_show_user_selection(show) {
	if(show) {
		jQuery('#users').show();
	}
	else
	{
		jQuery('#users').hide();
	}
}
function manual_completions_wpcourseware_filter_users_list() {
	var string = jQuery('#users [role="searchbox"]').val().trim();
	if(typeof window.manual_completions_wpcourseware_filter_users_string  == "string" && window.manual_completions_wpcourseware_filter_users_string == string)
	return;

	window.manual_completions_wpcourseware_filter_users_string = string;

	if( string == "" ) {
		jQuery("#users option").show();
	}
	else {
		var select = "";
		var count = 0;
		jQuery("#users option").each(function(i, option) {
			if( jQuery(option).val() == "" ) {
				select = jQuery(option);
				jQuery(select).html("Updating...");
				select.show();
				return;
			}

			if( jQuery(option).text().toLowerCase().indexOf(string) != -1 ) {
				jQuery(option).show();
				count++;
			}
			else
			jQuery(option).hide();
		});
		if( select ) {
			setTimeout(function(){ jQuery(select).html(" --- Select a User --- (" + count + ")"); }, 200);
		}
	}
}
function manual_completions_wpcourseware_handle_users_keypress(e, splitter) {
    	//console.log(e.which, jQuery("#user_ids").val(), jQuery('#users [role="searchbox"]').val());
    	if(e.which == 32 || e.which == 13) {
			var values = jQuery("#user_ids").val();
			if(values == null)
				values = [];

			var string = jQuery('#users [role="searchbox"]').val();
			var updated = false;
			var input_items = string.split(splitter);
			jQuery.each(input_items, function(i, v) {
				if(v > 0) {
					var value = jQuery("#user_ids option[value=" + v.trim() + "]").val();
					if(value != undefined) {
						updated = true;
						values[values.length] = value;
						jQuery("#user_ids").val(value).trigger("change");
						delete( input_items[i] );
					}
				}
				else
				{
					delete( input_items[i] );
				}
			});
			if( updated ) {
				jQuery('#users [role="searchbox"]').val(input_items.filter(function(el) { return el; }).join(splitter));
			}
	}
}
function manual_completions_wpcourseware_course_selected(context) {
	var course_id = jQuery(context).val();

	manual_completions_wpcourseware_clear_value("module");
	manual_completions_wpcourseware_clear_value("unit");
	manual_completions_wpcourseware_clear_value("quiz");

	if(typeof course_structure[course_id] == "object") {
		manual_completions_wpcourseware_show_elements( course_structure[course_id] );
		return;
	}
	jQuery("#manual_completions_wpcourseware #unit, #manual_completions_wpcourseware #quiz").hide();
	jQuery("#manual_completions_wpcourseware #quiz option.auto").remove();

	if( course_id == "" || course_id == null ) {
		jQuery("#manual_completions_wpcourseware #upload_csv").show();
		return;
	}
	else
		jQuery("#manual_completions_wpcourseware #upload_csv").hide();


	var data = {
		"action" : "manual_completions_wpcourseware_course_selected",
		"course_id" : course_id
	};
	jQuery("#course #gb_loading_animation").show();
	jQuery.post(manual_completions_wpcourseware.ajax_url, data)
	.done(function( data ) {
		console.error(data);
		if( data.status == 1 && typeof data.data == "object" ) {
			jQuery("#course #gb_loading_animation").hide();
			course_structure[course_id] = data.data;
			manual_completions_wpcourseware_show_elements( course_structure[course_id] );
			return;
		}
		else{
			alert("Invalid course data received");
			jQuery("#course #gb_loading_animation").hide();
		}
	})
	.fail(function(xhr, status, error) {
		console.log(xhr, status, error);
		alert("Request to get course data failed");
		jQuery("#course #gb_loading_animation").hide();
	});
}
function manual_completions_wpcourseware_quiz_selected(context) {
	if(jQuery("#manual_completions_wpcourseware #quiz option:selected").hasClass("global"))
	{
		manual_completions_wpcourseware_unselect_value("unit");
	}
	var quiz_id = jQuery("#manual_completions_wpcourseware #quiz_id").val();
	manual_completions_wpcourseware_show_user_selection(quiz_id > 0);
}
function manual_completions_wpcourseware_module_selected(context) {
	var id = jQuery("#manual_completions_wpcourseware #module_id").val();

	// if(id > 0 || !jQuery("#manual_completions_wpcourseware #quiz option:selected").hasClass("global"))
	// 	manual_completions_wpcourseware_clear_value("quiz");

	manual_completions_wpcourseware_clear_value("unit");
	manual_completions_wpcourseware_clear_value("quiz");

	if(id > 0 || id == "all"){
		manual_completions_wpcourseware_show_elements();
		manual_completions_wpcourseware_show_user_selection(true);
	}
}
function manual_completions_wpcourseware_unit_selected(context) {
	var id = jQuery("#manual_completions_wpcourseware #unit_id").val();

	if(id > 0 || !jQuery("#manual_completions_wpcourseware #quiz option:selected").hasClass("global"))
		manual_completions_wpcourseware_clear_value("quiz");

	manual_completions_wpcourseware_show_elements();

	if(id > 0 || id == "all")
		manual_completions_wpcourseware_show_user_selection(true);
}
function manual_completions_wpcourseware_unselect_value(name) {
	if(jQuery("#manual_completions_wpcourseware #" + name + "_id").val() != "")
		jQuery("#manual_completions_wpcourseware #" + name + "_id").val("").trigger("change");
}
function manual_completions_wpcourseware_clear_value(name) {
	manual_completions_wpcourseware_unselect_value(name);

	jQuery("#manual_completions_wpcourseware #" + name + " option.auto:not(.global)").remove();
	if(jQuery("#manual_completions_wpcourseware #" + name + " option").length <= 1 || name == "unit" && jQuery("#manual_completions_tutor #quiz option").length <= 2)
		jQuery("#manual_completions_wpcourseware #" + name).hide();
}
function manual_completions_wpcourseware_show_elements(data) {
	var course_id = jQuery("#manual_completions_wpcourseware #course_id").val();

	if(data == undefined && typeof course_structure[course_id] != "object")
		return;

	var course_id = jQuery("#manual_completions_wpcourseware #course_id").val();
	var module_id = jQuery("#manual_completions_wpcourseware #module_id").val();
	var unit_id = jQuery("#manual_completions_wpcourseware #unit_id").val();
	var quiz_id = jQuery("#manual_completions_wpcourseware #quiz_id").val();

	if(typeof data != "object") {
		data = course_structure[course_id];
	}
	if(typeof data != "object") {
		console.error("Invalid data");
		alert("Invalid data");
		return;
	}

	if(typeof data["modules"] == "object" && module_id == "") {
		manual_completions_wpcourseware_clear_value("module");
		manual_completions_wpcourseware_clear_value("unit");
		manual_completions_wpcourseware_clear_value("quiz");

		jQuery.each(data["modules"], function(module_id, module_data) {
			jQuery("#manual_completions_wpcourseware #module_id").append("<option class='auto' value='" + module_id + "' " + manual_completions_wpcourseware_has_xapi_attr(module_data) +  ">" + module_data.module["post_title"] + "</option>");
		});
		jQuery("#manual_completions_wpcourseware #module").show();
	}

	if(typeof data["modules"] == "object" && module_id > 0) {

		if(typeof data["modules"][module_id] == "object" && typeof data["modules"][module_id]["units"] == "object" && unit_id == "") {
			manual_completions_wpcourseware_clear_value("unit");

			jQuery.each(data["modules"][module_id]["units"], function(unit_id, unit_data) {
				jQuery("#manual_completions_wpcourseware #unit_id").append("<option class='auto' value='" + unit_id + "' " + manual_completions_wpcourseware_has_xapi_attr(unit_data) +  ">" + unit_data.unit["post_title"] + " " + manual_completions_wpcourseware_has_xapi_label(unit_data) + "</option>");
			});
			jQuery("#manual_completions_wpcourseware #unit").show();
		}
		if(typeof data["modules"][module_id] == "object" && typeof data["modules"][module_id]["units"] == "object" && unit_id > 0) {
			if(
				typeof data["modules"][module_id]['units'][unit_id] === "object" &&
				typeof data["modules"][module_id]['units'][unit_id]['quizzes'] === "object" &&
				quiz_id == ""
			) {
				manual_completions_wpcourseware_clear_value("quiz");

				jQuery.each(data["modules"][module_id]['units'][unit_id]['quizzes'], function(quiz_id, quiz_data) {
					jQuery("#manual_completions_wpcourseware #quiz_id").append("<option class='auto' value='" + quiz_id + "' " + manual_completions_wpcourseware_has_xapi_attr(quiz_data) +  ">" + quiz_data.quiz["post_title"] + " (unit quiz) " + manual_completions_wpcourseware_has_xapi_label(quiz_data) + "</option>");
				});
				jQuery("#manual_completions_wpcourseware #quiz").show();
			}
		}

	}

	// if(typeof data["units"] == "object" && unit_id == "") {
	// 	manual_completions_wpcourseware_clear_value("unit");

	// 	jQuery.each(data["units"], function(unit_id, unit_data) {
	// 		jQuery("#manual_completions_wpcourseware #unit_id").append("<option class='auto' value='" + unit_id + "' " + manual_completions_wpcourseware_has_xapi_attr(unit_data) +  ">" + unit_data.unit["post_title"] + " " + manual_completions_wpcourseware_has_xapi_label(unit_data) + "</option>");
	// 	});
	// 	jQuery("#manual_completions_wpcourseware #unit").show();
	// }

	// if(typeof data["units"] == "object" && unit_id > 0) {

	// 	if(typeof data["units"][unit_id]["quizzes"] == "object" && quiz_id == "") {
	// 		manual_completions_wpcourseware_clear_value("quiz");

	// 		jQuery.each(data["units"][unit_id]["quizzes"], function(quiz_id, quiz_data) {
	// 			jQuery("#manual_completions_wpcourseware #quiz_id").append("<option class='auto' value='" + quiz_id + "' " + manual_completions_wpcourseware_has_xapi_attr(quiz_data) +  ">" + quiz_data.quiz["post_title"] + " (unit quiz) " + manual_completions_wpcourseware_has_xapi_label(quiz_data) + "</option>");
	// 		});
	// 		jQuery("#manual_completions_wpcourseware #quiz").show();
	// 	}
	// }

	if( unit_id > 0 || quiz_id > 0 )
		manual_completions_wpcourseware_show_user_selection(true);
	else
		manual_completions_wpcourseware_show_user_selection(false);
}
function manual_completions_wpcourseware_has_xapi_label(data) {
	if(typeof data == "object" && typeof data.xapi_content == "object")
		return " (has xAPI Content) ";
	else
		return "";
}
function manual_completions_wpcourseware_has_xapi_attr(data) {
	if(typeof data == "object" && typeof data.xapi_content == "object")
		return " data-xapi='1' ";
	else
		return "";
}
function manual_completions_wpcourseware_xapi_icon(name, data) {
	var course_id 	= (typeof data.course_id == "undefined")? "":data.course_id;
	var module_id	= (typeof data.module_id == "undefined")? "":data.module_id;
	var quiz_id 	= (typeof data.quiz_id == "undefined")? "":data.quiz_id;
	var unit_id 	= (typeof data.unit_id == "undefined")? "":data.unit_id;

	if(typeof course_structure[course_id] != "object")
		return " ";

	switch(name) {
		case "unit":
			if(unit_id == "" || typeof course_structure[course_id]['modules'][module_id] != "object" || typeof course_structure[course_id]['modules'][module_id].units != "object" || typeof course_structure[course_id]['modules'][module_id].units[unit_id] != "object"  || typeof course_structure[course_id]['modules'][module_id].units[unit_id].xapi_content != "object" )
				return " ";
			else
				return " <span class='has_xapi' title='Has xAPI'></span> ";

		case "quiz":
			if(quiz_id == "")
				return " ";

			if(unit_id > 0 && typeof course_structure[course_id]['modules'][module_id].units == "object" && typeof course_structure[course_id]['modules'][module_id].units[unit_id] == "object") {
				if(typeof course_structure[course_id]['modules'][module_id].units[unit_id].quizzes == "object" && typeof course_structure[course_id]['modules'][module_id].units[unit_id].quizzes[quiz_id] == "object" && typeof course_structure[course_id]['modules'][module_id].units[unit_id].quizzes[quiz_id].xapi_content == "object")
					return " <span class='has_xapi' title='Has xAPI'></span> "; //unit Quiz
			}

			return " ";
	}
	return " ";
}
function manual_completions_wpcourseware_users_selected(context) {
	var course_id = jQuery("#manual_completions_wpcourseware #course_id").val();
	var module_id = jQuery("#manual_completions_wpcourseware #module_id").val();
	var unit_id = jQuery("#manual_completions_wpcourseware #unit_id").val();
	var quiz_id = jQuery("#manual_completions_wpcourseware #quiz_id").val();

	var user_ids = jQuery("#users select").val();
	user_ids = (typeof user_ids != "object" && user_ids * 1 > 0)? [user_ids]:user_ids;

	var sno = jQuery("#manual_completions_wpcourseware_table table tr:last-child .sno").text()*1 + 1;

	if(typeof user_ids == "object" && user_ids != null && user_ids.length > 0)
	jQuery.each(user_ids, function(i, user_id) {
		if( user_id > 0 ) {
			var data = {course_id:course_id, module_id:module_id, unit_id:unit_id, quiz_id:quiz_id, user_id: user_id};
			sno += manual_completions_wpcourseware_add_row(data, sno);
		}
	});

	jQuery("#users select").val("");
}
function manual_completions_wpcourseware_add_row(data, sno) {
	var course_id 	= (typeof data.course_id == "undefined")? "":data.course_id;
	var module_id 	= (typeof data.module_id == "undefined")? "":data.module_id;
	var user_id 	= (typeof data.user_id == "undefined")? "":data.user_id;
	var quiz_id 	= (typeof data.quiz_id == "undefined")? "":data.quiz_id;
	var unit_id 	= (typeof data.unit_id == "undefined")? "":data.unit_id;

	if(typeof course_structure[course_id] == "undefined" ||
		module_id == "" && unit_id > 0 && (typeof course_structure[course_id]["modules"] == "undefined" || typeof course_structure[course_id]["modules"][module_id] == "undefined") ||
		unit_id > 0 && (typeof course_structure[course_id]["modules"][module_id]["units"] == "undefined" ||
		typeof course_structure[course_id]["modules"][module_id]["units"][unit_id] == "undefined")
	){
		console.log("Invalid row: ", data);
		return;
	}
	if( unit_id > 0 && quiz_id > 0 )
	{
		if((typeof course_structure[course_id]["modules"][module_id]["units"][unit_id]["quizzes"] == "undefined" || typeof course_structure[course_id]["modules"][module_id]["units"][unit_id]["quizzes"][quiz_id] == "undefined")) {
			console.log("Invalid row: ", data);
			return;
		}
	}

	var key = "completion_" + course_id + "_" + module_id + "_" + unit_id + "_" + quiz_id + "_" + user_id;
	data["row_id"] = key;

	var row = "<tr id='" + key + "' data-completion='" + JSON.stringify(data) + "'>";

	if(jQuery("#manual_completions_wpcourseware_table #" + key).length == 0)
	{
		var user_label = jQuery("#users option[value=" + user_id+ "]").text();
		if(user_label == "")
			user_label = user_id + " (User Not Found)";

		row += "<td>" + "<input type='checkbox' name='" + key + "'>" + "</td>";
		row += "<td class='sno'>" + sno + "</td>";
		row += "<td>" + user_label + "</td>";
		row += "<td>" + manual_completions_wpcourseware_get_label("course", course_id, module_id, unit_id, quiz_id) + "</td>";
		row += "<td>" + manual_completions_wpcourseware_get_label("module", course_id, module_id, unit_id, quiz_id) + "</td>";
		row += "<td>" + manual_completions_wpcourseware_xapi_icon("unit", data) + manual_completions_wpcourseware_get_label("unit", course_id, module_id, unit_id, quiz_id) + "</td>";
		row += "<td>" + manual_completions_wpcourseware_xapi_icon("quiz", data) 	+  manual_completions_wpcourseware_get_label("quiz", course_id, module_id, unit_id, quiz_id) + "</td>";
		row += "<td>" + manual_completions_wpcourseware_get_mark_complete_button(data) + "</td>";
		row += "<td class='status'>" + "Not Processed" + "</td>";

		if(jQuery(row).find(".has_xapi").length)
			jQuery("#manual_completions_wpcourseware_table .force_completion").slideDown();

		jQuery("#manual_completions_wpcourseware_table table").append(row);
		manual_completions_wpcourseware_update_total_count();
		return true;
	}

	return false;
}
function manual_completions_wpcourseware_update_total_count() {
	jQuery("#manual_completions_wpcourseware_table #list_count .count").text(jQuery("#manual_completions_wpcourseware_table tr").length - 1);
}
function manual_completions_wpcourseware_get_mark_complete_button(data) {
	return " <a onclick='manual_completions_wpcourseware_mark_complete(this)' class='button-secondary'>Mark Complete</a> " + " <a onclick='manual_completions_wpcourseware_check_completion(this)' class='button-secondary'>Check Completion</a> " +  " <a onclick='manual_completions_wpcourseware_remove(this);' class='button-secondary'> X </a> ";
}
function manual_completions_wpcourseware_remove(context) {
	jQuery(context).closest("tr").attr("data-status", "remove");

	setTimeout(function() {
		jQuery(context).closest("tr").remove();
		manual_completions_wpcourseware_update_checked_count();
		manual_completions_wpcourseware_update_total_count();
	}, 600);
}
function manual_completions_wpcourseware_mark_complete(selected) {

	if( jQuery("#manual_completions_wpcourseware_table tr[data-status=processing]").length > 0 )
	{
		alert("Please wait for current queue to complete.");
		return;
	}

	var completion_data = [];

	if( selected != undefined )
		var selected_completions = jQuery(selected).closest("tr");
	else
		var selected_completions = jQuery("#manual_completions_wpcourseware_table input[type=checkbox]:not(#select_all):checked").closest("tr");

	selected_completions.attr("data-status", "waiting");
	selected_completions.find(".status").text("Waiting...");

	var processing_completions = selected_completions.slice(0, 10);

	processing_completions.each(function(i, context) {
		completion_data[i] = jQuery(context).data("completion");

		jQuery(context).attr("data-status", "processing");
		jQuery(context).find(".status").text("Processing...");
		jQuery(context).find("input[type=checkbox]").prop("checked", false).prop("disabled", true);
	});

	if(typeof completion_data != "object" || completion_data == null || completion_data.length == 0) {
		alert("Nothing to process.");
		return;
	}
	var data = {
		"action" : "manual_completions_wpcourseware_mark_complete",
		"data" : completion_data,
		"force_completion" : (jQuery("#force_completion").is(":checked")? 1:0)
	};
	jQuery.post(manual_completions_wpcourseware.ajax_url, data)
	.done(function( data ) {
		console.error(data);

		if(typeof data.data == "object")
		jQuery.each(data.data, function(i, data) {
			var context = "#" + data.row_id;
			if( data.status == 1 )
				jQuery(context).closest("tr").attr("data-status", "processed");
			else
				jQuery(context).closest("tr").attr("data-status", "failed");

			if(typeof data.message == "string")
				jQuery(context).closest("tr").find(".status").text(data.message);
			else
				jQuery(context).closest("tr").find(".status").text("Invalid Response1");
		});
	})
	.fail(function(xhr, status, error) {
		console.log(xhr, status, error);
	//	jQuery(context).closest("tr").find(".status").text("Request Failed");
		processing_completions.find(".status").text("Failed Request");
		processing_completions.attr("data-status", "failed");
	})
	.always(function() {
		manual_completions_wpcourseware_update_checked_count();

		setTimeout(function() {

			var waiting = jQuery("#manual_completions_wpcourseware_table tr[data-status=waiting]");
			if(waiting.length > 0)
			manual_completions_wpcourseware_mark_complete( waiting );
			else if( selected == undefined )
			alert("All Completions Processed.");

		}, 500);
	});
}
function manual_completions_wpcourseware_check_completion(selected) {

	if( jQuery("#manual_completions_wpcourseware_table tr[data-status=processing]").length > 0 )
	{
		alert("Please wait for current queue to complete.");
		return;
	}

	var completion_data = [];

	if( selected != undefined )
		var selected_completions = jQuery(selected).closest("tr");
	else
		var selected_completions = jQuery("#manual_completions_wpcourseware_table input[type=checkbox]:not(#select_all):checked").closest("tr");

	selected_completions.attr("data-status", "waiting");
	selected_completions.find(".status").text("Waiting...");

	var processing_completions = selected_completions.slice(0, 10);

	processing_completions.each(function(i, context) {
		completion_data[i] = jQuery(context).data("completion");

		jQuery(context).attr("data-status", "processing");
		jQuery(context).find(".status").text("Processing...");
		jQuery(context).find("input[type=checkbox]").prop("checked", false).prop("disabled", true);
	});

	if(typeof completion_data != "object" || completion_data == null || completion_data.length == 0) {
		alert("Nothing to process.");
		return;
	}

	var data = {
		"action" : "manual_completions_wpcourseware_check_completion",
		"data" : completion_data
	};
	jQuery.post(manual_completions_wpcourseware.ajax_url, data)
	.done(function( data ) {
		console.error(data);

		if(typeof data.data == "object")
		jQuery.each(data.data, function(i, data) {
			var context = "#" + data.row_id;
			if( data.status == 1 )
				jQuery(context).closest("tr").attr("data-status", "checked");
			else
				jQuery(context).closest("tr").attr("data-status", "failed");

			if(typeof data.message == "string")
				jQuery(context).closest("tr").find(".status").text(data.message);
			else
				jQuery(context).closest("tr").find(".status").text("Invalid Response");

			if(typeof data.completed != "undefined")
				jQuery(context).closest("tr").attr("data-completed", data.completed? "completed":"not_completed");

			if( data.completed != 1 )
				jQuery(context).find("input[type=checkbox]").prop("disabled", false);
		});

		jQuery("#manual_completions_wpcourseware_table tr[data-status=processing]").find(".status").text("Unknown Response");
		jQuery("#manual_completions_wpcourseware_table tr[data-status=processing]").attr("data-status", "failed");
		jQuery("#manual_completions_wpcourseware_table tr[data-status=processing] input[type=checkbox]").prop("disabled", false);

	})
	.fail(function(xhr, status, error) {
		console.log(xhr, status, error);
	//	jQuery(context).closest("tr").find(".status").text("Request Failed");
		processing_completions.find(".status").text("Failed Request1");
		processing_completions.attr("data-status", "failed");
		processing_completions.find("input[type=checkbox]").prop("disabled", false);
	})
	.always(function() {
		manual_completions_wpcourseware_update_checked_count();

		setTimeout(function() {

			var waiting = jQuery("#manual_completions_wpcourseware_table tr[data-status=waiting]");
			if(waiting.length > 0)
			manual_completions_wpcourseware_check_completion( waiting );
			else if( selected == undefined )
			alert("All requests processed.");

		}, 500);
	});
}
function manual_completions_wpcourseware_get_label(name, course_id, module_id, unit_id, quiz_id) {
	switch(name) {
		case "course" :
				return course_id + ". " + course_structure[course_id].course.post_title;
		case "module" :
				if(module_id == "all")
				{
					return "-- Entire Course --";
				}
				if(module_id == "" || module_id == null)
					return module_id;

				return (module_id == "all")? module_id:module_id + ". " + course_structure[course_id]["modules"][module_id].module.post_title;
		case "unit" :
				if(unit_id == "all")
				{
					return "-- Entire module --";
				}
				return (unit_id == "" || unit_id == null)? unit_id:unit_id + ". " + course_structure[course_id]["modules"][module_id]["units"][unit_id].unit.post_title;
		case "quiz" :
				if(quiz_id == "" || quiz_id == null)
					return quiz_id;

				if(unit_id > 0 && typeof course_structure[course_id]["modules"][module_id]["units"][unit_id]["quizzes"] == "object" && typeof course_structure[course_id]["modules"][module_id]["units"][unit_id]["quizzes"][quiz_id] == "object")
				return quiz_id + ". " + course_structure[course_id]["modules"][module_id]["units"][unit_id]["quizzes"][quiz_id].quiz.post_title;

				return quiz_id;
	}
	return "";
}

function grassblade_wpcourseware_plugin_activate_deactivate(el, url) {
	el.children("#gb_loading_animation").show();
	jQuery.get(url, function(data) {
		el.children("#gb_loading_animation").show();
		window.location.reload();
	});
	return false;
}

function manual_completions_wpcourseware_get_enrolled_users() {
	var course_id = jQuery("#course_id").val();
	var module_id = jQuery("#module_id").val();
	var unit_id = jQuery("#unit_id").val();
	var quiz_id = jQuery("#quiz_id").val();

	if(course_id == "")
		return;

	if(module_id != "all" && unit_id == "" && quiz_id == "")
		unit_id = "all";

	var data = {
		"action" : "manual_completions_wpcourseware_get_enrolled_users",
		"course_id" : course_id,
	};

	jQuery.post(manual_completions_wpcourseware.ajax_url, data)
	.done(function( data ) {
		var old_sno = jQuery("#manual_completions_wpcourseware_table tr:last .sno").text()*1;
		var sno = 0;
		if(typeof data.data == "object")
		jQuery.each(data.data, function(i, user_id) {
			var d = {
				user_id: user_id,
				course_id: data.course_id,
				module_id: module_id,
				unit_id: unit_id,
				quiz_id: quiz_id,
			};
			manual_completions_wpcourseware_add_row(d, old_sno + ++sno);
		});

		if(sno > 0)
			alert("Found " + sno + " users.");
		else
			alert("No users found");
	});
}
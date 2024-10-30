<?php
/*
Plugin Name: Manual Completions for WP Courseware
Plugin URI: https://www.nextsoftwaresolutions.com/manual-completions-for-wpcourseware/
Description: Manual Bulk Completions for WP Courseware addon lets you check completion as well as manually mark courses, modules, units and quizzes as complete.
Author: Next Software Solutions
Version: 1.2
Author URI: https://www.nextsoftwaresolutions.com
Text Domain: manual-completions-wpcourseware
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

/**
 * Manual Completions WP Courseware
*/

if (!defined('ABSPATH')) {
	exit;
}

class gbmc_wpcourseware_manual_completions
{
	public $version = "1.2";
	public $install_link = "https://www.nextsoftwaresolutions.com/r/wpcourseware/addon_info_page";

	function __construct()
	{
		if (!is_admin())
			return;

		global $manual_completions_wpcourseware;
		$manual_completions_wpcourseware = array("uploaded_data" => array(), "upload_error" => array(), "course_structure" => array(), "ajax_url" => admin_url("admin-ajax.php"));

		add_action('admin_menu', array($this, 'menu'), 11);
		add_action('wp_ajax_manual_completions_wpcourseware_course_selected', array($this, 'course_selected'));
		add_action('wp_ajax_manual_completions_wpcourseware_mark_complete', array($this, 'mark_complete'));
		add_action('wp_ajax_manual_completions_wpcourseware_check_completion', array($this, 'check_completion'));
		add_action('wp_ajax_manual_completions_wpcourseware_get_enrolled_users', array($this, 'get_enrolled_users'));
		add_action('admin_init', array($this, "process_upload"));
	}

	function get_enrolled_users()
	{
		global $wpdb;
		if (!current_user_can("manage_options") || empty($_REQUEST["course_id"]))
			$this->json_out(array("status" => 0, "message" => __("Invalid Request", 'manual-completions-wpcourseware')));

		if (!empty($_REQUEST["course_id"]) && is_numeric($_REQUEST["course_id"])) {
			$course_post_id = intval($_REQUEST["course_id"]);
			$course_id = $this->get_course_id($course_post_id);
			$students  = wpcw()->students->get_students( array( 'course_id' => $course_id, 'number' => - 1, "fields" => "ids" ), true);

			$user_ids = array();
			if (!empty($students))
			foreach ($students as $student) {
				$user_ids[] = $student['ID'];
			}
			$this->json_out(array("status" => 1, "data" => $user_ids, "course_id" => $course_post_id));
		}
		$this->json_out(array("status" => 0, "message" => __("Invalid Request", 'manual-completions-wpcourseware')));
	}

	function manual_completions_wpcourseware_scripts()
	{
		global $manual_completions_wpcourseware;

		wp_enqueue_script('manual_completions_wpcourseware', plugins_url('/script.js', __FILE__), array('jquery'), $this->version);
		wp_enqueue_style("manual_completions_wpcourseware", plugins_url("/style.css", __FILE__), array(), $this->version);
		wp_enqueue_script("select2js", plugins_url("/vendor/select2/js/select2.min.js", __FILE__), array(), $this->version);
		wp_enqueue_style("select2css", plugins_url("/vendor/select2/css/select2.min.css", __FILE__), array(), $this->version);
		wp_localize_script('manual_completions_wpcourseware', 'manual_completions_wpcourseware',  $manual_completions_wpcourseware);

		wp_add_inline_style("manual_completions_wpcourseware", '#manual_completions_wpcourseware_table .has_xapi {background: url(' . esc_url(plugins_url("img/icon-gb.png", __FILE__)) . '}');
	}
	function upload_mimes($existing_mimes = array())
	{
		// add your extension to the mimes array as below
		$existing_mimes['csv'] = 'text/csv';
		return $existing_mimes;
	}
	function process_upload() {

		if (empty($_GET['page']) || $_GET['page'] != "grassblade-manual-completions-wpcourseware")
			return;

		add_action("admin_print_styles", array($this, "manual_completions_wpcourseware_scripts"));
		if( empty($_FILES) || empty( $_FILES['completions_file']['name'] ))
			return;

		add_filter('upload_mimes', array($this, 'upload_mimes'));
		if (!current_user_can("manage_options") || empty($_POST["manual_completions_wpcourseware_csv_nonce"]) || !wp_verify_nonce($_POST["manual_completions_wpcourseware_csv_nonce"], 'manual_completions_wpcourseware_csv')) {
			$manual_completions_wpcourseware["upload_error"] = esc_html__('Invalid Request', "manual-completions-wpcourseware");
			return;
		}

		global $manual_completions_wpcourseware;
		if(empty($manual_completions_wpcourseware) || !is_array($manual_completions_wpcourseware))
		$manual_completions_wpcourseware = array();

		$file_name = sanitize_text_field($_FILES['completions_file']['name']);
		$file_type = sanitize_text_field($_FILES['completions_file']['type']);
		if(empty($file_name) || strtolower( pathinfo($file_name, PATHINFO_EXTENSION) ) != "csv" || empty($file_type) || $file_type !== "text/csv" && $file_type !== "application/vnd.ms-excel")
		{
			$manual_completions_tutor["upload_error"] = esc_html__('Upload Error: Invalid file format. Please upload a valid csv file', "manual-completions-wpcourseware");
			return;
		}

		require_once(dirname(__FILE__)."/../grassblade/addons/parsecsv.lib.php");
		$tmp_name = sanitize_text_field($_FILES['completions_file']['tmp_name']);
		$csv = new parseCSV($tmp_name);
		if(empty($csv->data) || !is_array($csv->data) || empty($csv->data[0]))
		{
			$manual_completions_wpcourseware["upload_error"] = __('Upload Error: Empty csv file', "manual-completions-wpcourseware");
			return;
		}
		$csv_data = array();
		foreach ($csv->data as $k => $data) {
			$csv_data[$k] = array();
			foreach ($data as $j => $val) {
				$j = str_replace(" ", "_", strtolower(trim($j)));
				$csv_data[$k][$j] = $val;
			}
		}

		if(!isset($csv_data[0]["user_id"]) || !isset($csv_data[0]["course_id"])) {
			$manual_completions_wpcourseware["upload_error"] = __('Upload Error: Invalid file format. Expected columns: user_id, course_id, module_id, unit_id, quiz_id ', "manual-completions-wpcourseware");
			return;
		}

		$uploaded_data = $courses = $course_structure = $rejected_rows = array();
		$allowed_columns = array("user_id", "course_id", "module_id", "unit_id", "quiz_id");
		foreach ($csv_data as $k => $data) {
			$row = array();
			$empty_row = true;

			foreach ($allowed_columns as $col) {
				if(!empty($data[$col]))
					$empty_row = false;

				$row[$col] = (isset($data[$col]) && (is_numeric($data[$col]) || $data[$col] == "all"))? sanitize_text_field($data[$col]):"";
			}

			if($empty_row)
				continue;

			if(empty($row["user_id"])) {
				if(!empty($data["user_email"])) {
					$user = get_user_by("email", sanitize_email($data["user_email"]));
					if(!empty($user->ID))
						$row["user_id"] = $user->ID;
				}
			}
			if(empty($row["user_id"])) {
				if(!empty($data["user_login"])) {
					$user = get_user_by("login", sanitize_user($data["user_login"]));
					if(!empty($user->ID))
						$row["user_id"] = $user->ID;
				}
			}

			if(!empty($row["course_id"]) && !empty($row["user_id"])) {
				$course_id = $row["course_id"];
				if(!empty($courses[$course_id]))
					$course = $courses[$course_id];
				else {
					$course = get_post($course_id);
					if(!empty($course->ID) && $course->post_status == "publish" && $course->post_type == "wpcw_course")
						$courses[$course_id] = $course;
					else
						$course = null;
				}

				if(empty($course->ID)) {
					$rejected_rows[] = $k + 2;
					continue;
				}

				if(!isset($course_structure[$course_id]))
					$course_structure[$course_id] = self::get_course_structure($course);

				if(!empty($row["module_id"]) && is_numeric($row["module_id"]) && empty($row["unit_id"]) && empty($row["quiz_id"]))
					$row["unit_id"] = "all";
				else if(empty($row["module_id"]) && empty($row["unit_id"]) && empty($row["quiz_id"]))
					$row["module_id"] = "all";

				$uploaded_data[] = $row;
			}
			else
				$rejected_rows[] = $k + 2;
		}
		$manual_completions_wpcourseware["uploaded_data"] 		= $uploaded_data;
		$manual_completions_wpcourseware["course_structure"] 	= $course_structure;

		if(!empty($rejected_rows))
		$manual_completions_wpcourseware["upload_error"] = "Rejected Rows: ".implode(", ", $rejected_rows);
	}
	function menu()
	{
		global $submenu, $admin_page_hooks;
		$icon = plugin_dir_url(__FILE__) . "img/icon-gb.png";

		if (empty($admin_page_hooks["grassblade-lrs-settings"])) {
			add_menu_page("GrassBlade", "GrassBlade", "manage_options", "grassblade-lrs-settings", array($this, 'menu_page'), $icon, null);
			add_action("admin_print_styles", array($this, "manual_completions_wpcourseware_scripts"));
		}

		add_submenu_page("grassblade-lrs-settings", __('Manual Completions WP Courseware', "manual-completions-wpcourseware"), __('Manual Completions WP Courseware LMS', "manual-completions-wpcourseware"), 'manage_options', 'grassblade-manual-completions-wpcourseware', array($this, 'menu_page'));
		add_submenu_page("wpcw", __('Manual Completions', "manual-completions-wpcourseware"), __('Manual Completions', "manual-completions-wpcourseware"), 'manage_options', 'grassblade-manual-completions-wpcourseware', array($this, 'menu_page'));
	}

	function form()
	{

		global $wpdb;

		$courses = get_posts("post_type=wpcw_course&posts_per_page=-1&post_status=publish");
		$users = $wpdb->get_results( "SELECT ID, display_name, user_login, user_email FROM $wpdb->users ORDER BY display_name ASC" );

		$this->manual_completions_wpcourseware_scripts();
		include_once(dirname(__FILE__) . "/form.php");
	}
	function menu_page()
	{

		if (!current_user_can('manage_options')) {
			wp_die(__("You do not have sufficient permissions to access this page.", "manual-completions-wpcourseware"));
		}

		$grassblade_plugin_file_path = WP_PLUGIN_DIR . '/grassblade/grassblade.php';
		if (!defined("GRASSBLADE_VERSION") && file_exists($grassblade_plugin_file_path)) {
			$grassblade_plugin_data = get_plugin_data($grassblade_plugin_file_path);
			define('GRASSBLADE_VERSION', @$grassblade_plugin_data['Version']);
		}

		$wpcourseware_plugin_file_path = WP_PLUGIN_DIR . '/wp-courseware/wp-courseware.php';
		if (!defined("WPCW_VERSION") && file_exists($wpcourseware_plugin_file_path)) {
			$wpcourseware_plugin_data = get_plugin_data($wpcourseware_plugin_file_path);
			define('WPCW_VERSION', @$wpcourseware_plugin_data['Version']);
		}

		$dependency_active = true;

		if (!file_exists($grassblade_plugin_file_path)) {
			$xapi_td = '<td><img src="' . plugin_dir_url(__FILE__) . 'img/no.png"/> ' . (defined("GRASSBLADE_VERSION") ? GRASSBLADE_VERSION : "") . '</td>';
			$xapi_td .= '<td>
							<a class="buy-btn" href="https://www.nextsoftwaresolutions.com/grassblade-xapi-companion/">' . __("Buy Now", "manual-completions-wpcourseware") . '</a>
						</td>';
			$dependency_active = false;
		} else {
			$xapi_td = '<td><img src="' . plugin_dir_url(__FILE__) . 'img/check.png"/> ' . (defined("GRASSBLADE_VERSION") ? GRASSBLADE_VERSION : "") . '</td>';
			if (!is_plugin_active('grassblade/grassblade.php')) {
				$xapi_td .= '<td>' . $this->activate_plugin("grassblade/grassblade.php") . '</td>';
				$dependency_active = false;
			} else {
				$xapi_td .= '<td><img src="' . plugin_dir_url(__FILE__) . 'img/check.png"/></td>';
			}
		}

		if (!file_exists($wpcourseware_plugin_file_path)) {
			$wpcourseware_td = '<td><img src="' . plugin_dir_url(__FILE__) . 'img/no.png"/> ' . (defined("WPCW_VERSION") ? WPCW_VERSION : "") . '</td>';
			$wpcourseware_td .= '<td colspan="2">
							<a class="buy-btn" href="' . $this->install_link . '">' . __("Buy Now", "grassblade-xapi-wpcourseware") . '</a>
						</td>';
			$dependency_active = false;
		} else {
			$wpcourseware_td = '<td><img src="' . plugin_dir_url(__FILE__) . 'img/check.png"/> ' . (defined("WPCW_VERSION") ? WPCW_VERSION : "") . '</td>';
			if (!is_plugin_active('wp-courseware/wp-courseware.php')) {
				$wpcourseware_td .= '<td>' . $this->activate_plugin("wp-courseware/wp-courseware.php") . '</td>';
				$dependency_active = false;
			} else {
				$wpcourseware_td .= '<td><img src="' . plugin_dir_url(__FILE__) . 'img/check.png"/></td>';
			}
		}

		if ($dependency_active)
			$this->form();
		else {
			$allowed_html = array(
							'td' => array(
								'colspan' => array(),
								'class' => array(),
							),
							'img' => array(
								'src' => array(),
								'alt' => array(),
							),
							'a' => array(
								'href' => array(),
								'onclick' => array(),
								'class' => array(),
							),
							'span' => array(
								'class' => array(),
								'style' => array(
									'display' => array(),
								),
								'id' => array(),
							),
						);

			?>
			<div id="manual_completions_wpcourseware" class="manual_completions_wpcourseware_requirements">
				<h2>
					<img style="margin-right: 10px;" src="<?php echo esc_url(plugin_dir_url(__FILE__) . "img/icon_30x30.png"); ?>" />
					Manual Completions for WP Courseware
				</h2>
				<hr>
				<div>
					<p class="text">To use Manual Completions for WP Courseware, you need to meet the following requirements.</p>
					<h2>Requirements:</h2>
					<table class="requirements-tbl">
						<thead>
							<tr>
								<th>SNo</th>
								<th>Requirements</th>
								<th>Installed</th>
								<th>Active</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td>1. </td>
								<td><a class="links" href="https://www.nextsoftwaresolutions.com/grassblade-xapi-companion/">GrassBlade xAPI Companion</a></td>
								<<?php echo wp_kses($xapi_td, $allowed_html); ?>
							</tr>
							<tr>
								<td>2. </td>
								<td><a class="links" href="<?php echo esc_url($this->install_link); ?>">WP Courseware LMS</a></td>
								<?php echo wp_kses($wpcourseware_td, $allowed_html); ?>
							</tr>
						</tbody>
					</table>
					<br>
				</div>
			</div>
<?php }
	}
	/**
	 * Generate an activation URL for a plugin like the ones found in WordPress plugin administration screen.
	 *
	 * @param  string $plugin A plugin-folder/plugin-main-file.php path (e.g. "my-plugin/my-plugin.php")
	 *
	 * @return string         The plugin activation url
	 */
	function activate_plugin($plugin)
	{
		$activation_link = wp_nonce_url( 'plugins.php?action=activate&amp;plugin=' . urlencode( $plugin ), 'activate-plugin_' . $plugin );
		$link = '<a href="#" onClick="return grassblade_wpcourseware_plugin_activate_deactivate(jQuery(this),  \''.$activation_link.'\');">'.__("Activate", "manual-completions-wpcourseware").'<span id="gb_loading_animation" style="display:none; position:unset; margin-right: unset;"><span class="dashicons dashicons-update"></span></span></a>';
		return $link;
	}
	function update_plugin($plugin){
		$activation_link = wp_nonce_url( 'update.php?action=upgrade-plugin&amp;plugin=' . urlencode( $plugin ), 'upgrade-plugin_' . $plugin );
		$link = '<a href="#" onClick="return grassblade_wpcourseware_plugin_activate_deactivate(jQuery(this),\''.$activation_link.'\');">'.__("Update", "manual-completions-wpcourseware").'<span id="gb_loading_animation" style="display:none; position:unset; margin-right: unset;"><span class="dashicons dashicons-update"></span></span></a>';
		return $link;
	}
	function course_selected()
	{
		if (!current_user_can("manage_options") || empty($_REQUEST["course_id"]))
			$this->json_out(array("status" => 0));

		$course_id = intval($_REQUEST["course_id"]);
		$course = get_post($course_id);

		if (empty($course->ID) || $course->post_status != "publish")
			$this->json_out(array("status" => 0));


		$this->json_out(array("status" => 1, "data" => $this->get_course_structure($course)));
	}

	function get_message($key){
		$messages = array(
			"course_not_selected" => __("Course not selected.", "manual-completions-wpcourseware"),
			"user_not_selected"   => __("User not selected.", "manual-completions-wpcourseware"),
			"not_enrolled" 		  => __("User not enrolled to course.", "manual-completions-wpcourseware"),
			"quiz_not_selected"   => __("Quiz not selected.", "manual-completions-wpcourseware"),
			"lesson_not_selected" => __("Unit not selected.", "manual-completions-wpcourseware"),
			"already_completed"   => __("Already Completed", "manual-completions-wpcourseware"),
			"completed"			  => __("Successfully Marked Complete", "manual-completions-wpcourseware"),
			"failed" 			  => __("Completion Failed", "manual-completions-wpcourseware"),
			"invalid_data" 		  => __("Invalid Data", "manual-completions-wpcourseware"),
			"not_selected"		  => __("Course/Module/Unit/Quiz not selected.", "manual-completions-wpcourseware"),
			"not_completed"		  => __("Not Completed", "manual-completions-wpcourseware"),
		);
		return isset($messages[$key])? $messages[$key]:"";
	}

	function check_completion($return = false) {

		if(!current_user_can("manage_options") || empty($_REQUEST["data"]) || (!is_array($_REQUEST["data"]) && !is_object($_REQUEST["data"])) )
			$this->json_out(array("status" => 0, "message" => self::get_message("invalid_data")));

		$completions = map_deep( wp_unslash( $_REQUEST['data'] ), 'sanitize_text_field' );
		foreach ($completions as $k => $completion) {
			$course_post_id  = $completion["course_id"]  = intval($completion["course_id"]);
			$module_id  = $completion["module_id"] = (!empty($completion["module_id"]) && $completion["module_id"] != "all") ? intval($completion["module_id"]) : "all";
			$unit_id  	= $completion["unit_id"]   = (!empty($completion["unit_id"]) && $completion["unit_id"] != "all" || $module_id == "all") ? intval($completion["unit_id"]) : "all";
			$quiz_id    = $completion["quiz_id"]   = intval($completion["quiz_id"]);
			$user_id    = $completion["user_id"]   = intval($completion["user_id"]);

			$course_id = self::get_course_id(intval($course_post_id));

			$student_progress = new WPCW_UserProgress($course_id, $user_id);

			if(empty($course_id)) {
				$completions[$k]["message"] = self::get_message("course_not_selected");
				$completions[$k]["status"] = 0;
			}
			else
			if(empty($user_id)) {
				$completions[$k]["message"] = self::get_message("user_not_selected");
				$completions[$k]["status"] = 0;
			}
			else if( !wpcw_is_student_enrolled($user_id, $course_id) ) {
				$completions[$k]["message"] = self::get_message("not_enrolled");
				$completions[$k]["status"] = 0;
			}
			else
			{
				$completed = null;
				if(!empty($quiz_id))
					$completed = $student_progress->isUnitCompleted($unit_id);
				else if(!empty($unit_id)) {
					$completed = ($unit_id == "all") ? self::is_module_complete($module_id, $course_id, $user_id) : $student_progress->isUnitCompleted($unit_id);
				}
				else if(!empty($module_id)){
					$completed = ($module_id == "all") ? $student_progress->isCourseCompleted() : self::is_module_complete($module_id, $course_id, $user_id);
				}
				else
				{
					$completions[$k]["message"] = self::get_message("not_selected");
					$completions[$k]["status"] = 0;
				}

				$completions[$k]['message'] = !empty($completed) ? self::get_message("already_completed") : self::get_message("not_completed");
				$completions[$k]["status"] = 1;
				$completions[$k]["completed"] = intval($completed);
			}
		}
		if( $return )
			return $completions;

		$this->json_out( array("status" => 1, "data" => $completions) );
	}

	function is_module_complete($module_id, $course_id, $user_id){

		$course = wpcw_get_course( $course_id );
		if( empty($course) )
			return false;

		$units = self::get_module_units($module_id, $course_id);
		$student_progress = new WPCW_UserProgress($course_id, $user_id);

		$completed = true;
		foreach ($units as $unit) {
			$completed = $student_progress->isUnitCompleted($unit->unit_id);
			if( !$completed )
				break;
		}
		return $completed;
	}

	function get_module_units($module_id, $course_id){
		$course  = wpcw_get_course( $course_id );

		if( empty($course) )
			return [];

		$modules = $course->get_modules(['module_id' => $module_id, 'number' => 1]);

		if( empty($modules) )
			return [];

		$units = $modules[0]->get_units();
		return $units;
	}

	function mark_complete() {

		if(!current_user_can("manage_options") || empty($_REQUEST["data"]) || (!is_array($_REQUEST["data"]) && !is_object($_REQUEST["data"])) )
			$this->json_out(array("status" => 0, "message" => "Invalid Data"));

		$completions = map_deep( wp_unslash( $_REQUEST['data'] ), 'sanitize_text_field' );
		$check_completions = $this->check_completion(true);

		foreach ($completions as $k => $completion) {
			if(!empty($check_completions[$k]) && !empty($check_completions[$k]["completed"])) {
				$completions[$k]["status"] = 1;
				$completions[$k]["message"] = self::get_message("already_completed");
				$completions[$k]["info"] = $check_completions[$k];
				continue;
			}
			$course_id = $completion["course_id"] = intval($completion["course_id"]);
			$module_id = $completion["module_id"] = (!empty($completion["module_id"]) && $completion["module_id"] != "all") ? intval($completion["module_id"]) : "all";
			$unit_id   = $completion["unit_id"]   = (!empty($completion["unit_id"]) && $completion["unit_id"] != "all" || $module_id == "all") ? intval($completion["unit_id"]) : "all";
			$quiz_id   = $completion["quiz_id"]   = intval($completion["quiz_id"]);
			$user_id   = $completion["user_id"]   = intval($completion["user_id"]);

			$course_id = $completion["course_id"] = self::get_course_id(intval($course_id));

			if(empty($course_id)) {
				$completions[$k]["message"] = self::get_message("course_not_selected");
				$completions[$k]["status"]  = 0;
			}
			else
			if(empty($user_id)) {
				$completions[$k]["message"] = self::get_message("user_not_selected");
				$completions[$k]["status"]  = 0;
			}
			else if( !wpcw_is_student_enrolled($user_id, $course_id) ) {
				$completions[$k]["message"] = self::get_message("not_enrolled");
				$completions[$k]["status"]  = 0;
			}
			else
			{
				if(!empty($_REQUEST["force_completion"])) {
					$completions[$k]["a"] = __("Force Completion", "manual-completions-wpcourseware");
					$force_completion 	  = true;
					remove_filter("wpcourseware_process_mark_complete", "grassblade_wpcourseware_process_mark_complete", 1, 3);
				}else{
					$force_completion = false;
				}
				if(!empty($quiz_id)){
					$completions[$k] = self::mark_complete_unit($completion, $force_completion);
				}
				else if(!empty($unit_id)) {
					if($unit_id == "all"){
						remove_filter("wpcourseware_process_mark_complete", "grassblade_wpcourseware_process_mark_complete", 1, 3);
						$completions[$k] = self::mark_complete_module($completion);
					}
					else{
						$completions[$k] = self::mark_complete_unit($completion, $force_completion);
					}
					if(empty($_REQUEST["force_completion"]) && $unit_id == "all")
						add_filter("wpcourseware_process_mark_complete", "grassblade_wpcourseware_process_mark_complete", 1, 3);
				}else if(!empty($module_id)){
					if($module_id == "all")
						$completions[$k] = self::mark_complete_course($completion);
					else
						$completions[$k] = self::mark_complete_module($completion);
				}
				else
				{
					$completions[$k]["message"] = self::get_message("not_selected");
					$completions[$k]["status"] = 0;
				}
			}
		}
		self::json_out( array("status" => 1, "data" => $completions) );
	}
	function mark_complete_module($completion){
		$module_id 	 = !empty($completion["module_id"]) ? intval($completion["module_id"]) : 0;
		$user_id 	 = !empty($completion["user_id"]) ? intval($completion["user_id"]) : 0;
		$course_id 	 = !empty($completion["course_id"]) ? intval($completion["course_id"]) : 0;
		$is_complete = self::is_module_complete($module_id, $course_id, $user_id);

		if($is_complete){
			$completion["message"] 	= self::get_message("already_completed");
			$completion["status"] 	= 1;
		}
		else{
			$units = self::get_module_units($module_id, $course_id);
			foreach($units as $unit){
				$completion['info']['unit_'.$unit->unit_id] = self::mark_complete_unit(array("course_id" => $course_id, "user_id" => $user_id, "unit_id" => $unit->unit_id), true);
			}
		}
		$is_complete 			= self::is_module_complete($module_id, $course_id, $user_id);
		$completion["status"]  	= ($is_complete)*1;
		$completion["message"]	= $is_complete ? self::get_message("completed") : self::get_message("failed");
		return $completion;
	}
	function mark_complete_course($completion) {
		$course_id = !empty($completion["course_id"]) ? intval($completion["course_id"]) : 0;
		$user_id   = !empty($completion["user_id"]) ? intval($completion["user_id"]) : 0;

		$student_progress = new WPCW_UserProgress($course_id, $user_id);
		$is_complete 	  = $student_progress->isCourseCompleted();

		if($is_complete){
			$completion["message"] 	= self::get_message("already_completed");
			$completion["status"] 	= 1;
		}
		else{
			$course = wpcw_get_course( $course_id );
			$modules = $course->get_modules();
			if(!empty($modules))
			foreach($modules as $module){
				$completion['info']['module_'.$module->module_id] = self::mark_complete_module(array("course_id" => $course_id, "user_id" => $user_id, "module_id" => $module->module_id), true);
			}
		}

		$is_complete 			= ( new WPCW_UserProgress( $course_id, $user_id ) )->isCourseCompleted();
		$completion["status"]  	= ($is_complete)*1;
		$completion["message"]	= $is_complete ? self::get_message("completed") : self::get_message("failed");
		return $completion;
	}
	function mark_complete_quiz($completion, $force_completion)
	{
		$quiz_id   = !empty($completion["quiz_id"]) ? intval($completion["quiz_id"]) : 0;
		$user_id   = !empty($completion["user_id"]) ? intval($completion["user_id"]) : 0;
		$course_id = !empty($completion["course_id"]) ? intval($completion["course_id"]) : 0;
		$unit_id   = !empty($completion["unit_id"]) ? intval($completion["unit_id"]) : 0;

		if (empty($quiz_id) || empty($user_id) || empty($course_id)) {
			$completion["message"] = self::get_message("invalid_request");
			$completion["status"]  = 0;
			return $completion;
		}

		if (self::is_quiz_passed($user_id, $unit_id)) {
			$completion["message"] = self::get_message("already_completed");
			$completion["status"]  = 1;
		} else {
			if ($force_completion)
				self::complete_quiz($user_id, $quiz_id, $course_id, $unit_id);
			else {
				if (self::check_xapi_content_completion($quiz_id, $user_id))
					self::complete_quiz($user_id, $quiz_id, $course_id, $unit_id);
			}
			if (self::is_quiz_passed($user_id, $unit_id)) {
				$completion["message"] = self::get_message("completed");
				$completion["status"]  = 1;
			} else {
				$completion["message"] = self::get_message("failed");
				$completion["status"]  = 0;
			}
		}
		return $completion;
	}

	function complete_quiz($user_id, $quiz_id, $course_id, $unit_id) {

		if($unit_id == 0)
			return;

		$quiz_attempt_id = $this->get_quiz_attempt_id($user_id,$unit_id,$quiz_id);
		$args = array(
					'user_id' => $user_id,
					'unit_id' => $unit_id,
					'quiz_id' => $quiz_id,
					'quiz_needs_marking' => 0,
					'quiz_attempt_id' => $quiz_attempt_id,
					'quiz_completed_date' => date('Y-m-d H:i:s', current_time('timestamp', 0)),
					'quiz_grade' => 100,
				);

		$this->insert_quiz_result($args,$course_id);
		add_filter( 'wpcw_unit_quiz_allow_quiz_progress_without_questions', '__return_true');

		// Save the user progress.
		WPCW_units_saveUserProgress_Complete( $user_id, $unit_id );

		// Update User Course Progress.
		wpcw_update_student_progress( $user_id, $course_id );

		// Get Unit Parent Data.
		$unit_parent_data = WPCW_units_getAssociatedParentData( $unit_id );

		/**
		 * Action: WPCW User Completed Unit.
		 * @param int    $user_id The user id.
		 * @param int    $unit_id The unit id.
		 * @param object $unit_parent_data The unit parent data.
		 */
		do_action( 'wpcw_user_completed_unit', $user_id, $unit_id, $unit_parent_data );
	}

	/**
	 * Insert Quiz Result.
	 *
	 * @param array $args.
	 * @param int $course_id.
	 *
	 */
	function insert_quiz_result($args, $course_id){

		global $wpcwdb, $wpdb;
		$SQL = arrayToSQLInsert( $wpcwdb->user_progress_quiz, $args );
		$wpdb->query( $SQL );
	}


	/**
	 * Get Quiz Attempted ID.
	 *
	 * @param int $user_id.
	 * @param int $unit_id.
	 * @param int $quiz_id.
	 *
	 * @return int quiz_attempt_id.
	 */
	function get_quiz_attempt_id($user_id,$unit_id,$quiz_id) {
		global $wpdb, $wpcwdb;

		$quiz_attempt_id = 0;
		$SQL = $wpdb->prepare( "SELECT * FROM $wpcwdb->user_progress_quiz WHERE user_id = %d AND unit_id = %d AND quiz_id = %d ORDER BY quiz_attempt_id DESC LIMIT 1 ;", $user_id, $unit_id, $quiz_id );

		// if exists, so increment the quiz_attempt_id
		if ( $existingProgress = $wpdb->get_row( $SQL ) ) {
			// we got an existing complete quiz progress , so we need to update it as null instead of latest.
			$SQL = $wpdb->prepare( " UPDATE $wpcwdb->user_progress_quiz	SET quiz_is_latest = ''	WHERE user_id = %d AND unit_id = %d AND quiz_id = %d ;", $user_id, $unit_id, $quiz_id );
			$wpdb->query( $SQL );

			$quiz_attempt_id = $existingProgress->quiz_attempt_id + 1;
		}
		return $quiz_attempt_id;
	}

	function mark_complete_unit($completion, $force_completion = false) {
		$user_id   = !empty($completion["user_id"]) ? intval($completion["user_id"]) : 0;
		$unit_id   = !empty($completion["unit_id"]) ? intval($completion["unit_id"]) : 0;
		$course_id = !empty($completion["course_id"]) ? intval($completion["course_id"]) : 0;

		if( empty($unit_id) || empty($user_id) || empty($course_id) ){
			$completion["message"] = self::get_message("invalid_request");
			$completion["status"]  = 0;
			return $completion;
		}

		if( (new WPCW_UserProgress($course_id, $user_id))->isUnitCompleted($unit_id) ) {
			$completion["message"] = self::get_message("already_completed");
			$completion["status"]  = 1;
		}else{

			$unit = wpcw_get_unit( $unit_id );
			$unit_quizzes = $unit->get_quizzes();

			if(!empty($unit_quizzes)){
				foreach($unit_quizzes as $quiz){
					$quiz_id = $quiz->get_id();

					if($force_completion)
						self::mark_complete_quiz(array("quiz_id" => $quiz_id, "user_id" => $user_id, 'course_id' => $course_id, 'unit_id' => $unit_id), $force_completion);

					if(self::check_xapi_content_completion($quiz->get_id(), $user_id))
						self::mark_complete_quiz(array("quiz_id" => $quiz_id, "user_id" => $user_id, 'course_id' => $course_id, 'unit_id' => $unit_id), $force_completion);

					$is_quiz_complete = self::is_quiz_passed($user_id, $unit_id);

					if(!$is_quiz_complete){
						$completion["status"]  =  0;
						$completion["message"] = self::get_message("failed");

						return $completion;
					}
				}
			}

			$is_complete = (new WPCW_UserProgress($course_id, $user_id))->isUnitCompleted($unit_id);

			if( ! $is_complete ) {
				if($force_completion)
					wpcw_unit_mark_as_complete( $unit_id, $user_id );
				else{
					if( self::check_xapi_content_completion( $unit_id, $user_id ) )
						wpcw_unit_mark_as_complete( $unit_id, $user_id );
				}

				$is_complete = (new WPCW_UserProgress($course_id, $user_id))->isUnitCompleted($unit_id);
			}

			$completion["status"]  = !empty($is_complete) ? 1 : 0;
			$completion["message"] = !empty($is_complete) ? self::get_message("completed") : self::get_message("failed");
		}
		return $completion;
	}

	function get_course_id($course_post_id)
	{
		global $wpdb;
		$sql   = $wpdb->prepare("SELECT course_id FROM `{$wpdb->prefix}wpcw_courses` where course_post_id = %d ", $course_post_id);
		$items = $wpdb->get_col( $sql, 0 );

		return !empty($items[0]) ? $items[0] : 0;
	}

	function json_out($data)
	{
		header('Content-Type: application/json');
		echo wp_json_encode($data);
		exit();
	}
	function get_course_structure($course)
	{
		$course_structure = new stdClass();

		if(!defined("WPCW_VERSION"))
			return $course_structure;

		$course->activity_id = grassblade_post_activityid($course->ID);
		$course_structure->course = $course;

		$_course = wpcw_get_course( $course->ID );
		$modules = $_course->get_modules();

		$structure_modules = new stdClass();

		foreach($modules as $module){
			$module_id = $module->get_id();
			$structure_modules->{$module_id} = new stdClass();
			$structure_modules->{$module_id}->module = new stdClass();
			$structure_modules->{$module_id}->module->ID = $module_id;
			$structure_modules->{$module_id}->module->post_title = $module->module_title;

			$units = $module->get_units();
			$structure_units = new stdClass();
			foreach($units as $unit){
				$unit_id = $unit->get_id();
				$structure_units->{$unit_id} = new stdClass();
				$structure_units->{$unit_id}->unit = get_post($unit_id);
				$structure_units->{$unit_id}->unit->activity_id = grassblade_post_activityid($unit_id);
				$structure_units->{$unit_id} = self::add_xapi_content_structure($structure_units->{$unit_id}, $unit_id);

				$quizzes = $unit->get_quizzes();
				$structure_quizzes = new stdClass();
				if(!empty($quizzes) && is_array($quizzes))
				foreach($quizzes as $quiz){
					$quiz_id = $quiz->get_id();
					$structure_quizzes->{$quiz_id} = new stdClass();

					$structure_quizzes->{$quiz_id}->quiz = new stdClass();
					$structure_quizzes->{$quiz_id}->quiz->ID = $quiz_id;
					$structure_quizzes->{$quiz_id}->quiz->post_title  = $quiz->get_quiz_title();
					$structure_quizzes->{$quiz_id} = self::add_xapi_content_structure_quiz($structure_quizzes->{$quiz_id}, $quiz_id);
				}
				$structure_units->{$unit_id}->quizzes = $structure_quizzes;
			}
			$structure_modules->{$module_id}->units = $structure_units;
		}
		$course_structure->modules = $structure_modules;
		return $course_structure;
	}

	function add_xapi_content_structure_quiz($structure, $post_id) {

		global $wpdb;
		$xapi_content_ids = $wpdb->get_results( $wpdb->prepare( "SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = %s AND meta_value = %d", 'wpcw_quiz', $post_id ));

		if(empty($xapi_content_ids) || !is_array($xapi_content_ids))
			return $structure;

		foreach($xapi_content_ids as $xapi_content_id){
			$xapi_content = get_post($xapi_content_id->post_id);
			if(!empty($xapi_content->ID) && $xapi_content->post_status == "publish") {

				$structure->quiz->activity_id = grassblade_post_activityid($xapi_content->ID);
				$xapi_content->activity_id 	  = grassblade_post_activityid($xapi_content->ID);

				if(empty($structure->xapi_contents))
					$structure->xapi_contents = array();

				$structure->xapi_contents[] = $structure->xapi_content = $xapi_content;
			}
		}

		return $structure;
	}

	function add_xapi_content_structure($structure, $post_id) {
		$xapi_content_ids = grassblade_xapi_content::get_post_xapi_contents( $post_id );
		if(!empty($xapi_content_ids) && is_array($xapi_content_ids)) {
			foreach ($xapi_content_ids as $xapi_content_id) {
				$xapi_content = get_post($xapi_content_id);
				if(!empty($xapi_content->ID) && $xapi_content->post_status == "publish") {
					$xapi_content->activity_id = grassblade_post_activityid($xapi_content->ID);

					if(empty($structure->xapi_contents))
						$structure->xapi_contents = array();

					$structure->xapi_contents[] = $structure->xapi_content = $xapi_content;
				}
			}
		}
		return $structure;
	}

	function is_quiz_passed($user_id, $unit_id){

		global $wpdb;
		$sql  = $wpdb->prepare( "SELECT * from {$wpdb->prefix}wpcw_user_progress_quizzes WHERE user_id = %d AND unit_id = %d and quiz_is_latest = %s", $user_id, $unit_id, 'latest' );
		$item = $wpdb->get_row( $sql );

		$return = false;
		if( empty($item) )
			return $return;

		$unit_quiz = wpcw_get_quiz( $item->quiz_id );
		$quiz_type = $unit_quiz->get_quiz_type();
		if($quiz_type == 'quiz_block')
			$return = $item->quiz_grade >= $unit_quiz->get_quiz_pass_mark() ? true : $return;
		else if($quiz_type == 'quiz_noblock')
			$return = true;

		return $return;
	}


	static function check_xapi_content_completion($post_id, $user_id)
	{

		if (empty($post_id) || empty($user_id))
			return false;

		$completed = grassblade_xapi_content::post_contents_completed($post_id, $user_id);

		if (is_bool($completed) && $completed) //No content
			return true;

		return (empty($completed) || count($completed) == 0) ? false : true;
	}
}

new gbmc_wpcourseware_manual_completions();

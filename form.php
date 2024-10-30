<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
		<div id="manual_completions_wpcourseware" class="manual_completions_wpcourseware">
			<h2>
				<img style="margin-right: 10px;" src="<?php echo esc_url(plugin_dir_url(__FILE__)."img/icon_30x30.png"); ?>"/>
				Manual Completions for WP Courseware LMS
			</h2>
			<hr>
			<table>
				<tr id="upload_csv">
					<td style="min-width: 100px">File</td>
					<td>
						<?php
						if(!empty($upload_error)) {
							?>
							<div style="color: red">
								<?php echo esc_html($upload_error); ?>
							</div>
							<?php
						}
						?>
						<form  method="post" enctype="multipart/form-data"><input type="file" name="completions_file">
							<?php wp_nonce_field('manual_completions_wpcourseware_csv', 'manual_completions_wpcourseware_csv_nonce'); ?>
							<input type="submit" name="manual_completions_wpcourseware" value="Upload">
						</form>
						<div>
							<?php esc_html_e("Upload a CSV file (expected columns: user_id, course_id, module_id, unit_id, quiz_id) or select the options from below. ", "manual-completions-wpcourseware"); ?>
							<a href="<?php echo esc_url(plugins_url("/vendor/example.csv", __FILE__)); ?>"><?php esc_html_e('Example CSV', "manual-completions-wpcourseware"); ?></a>
							<br><br>
						</div>
					</td>
				</tr>
				<tr id="course">
					<td style="min-width: 100px"><?php esc_html_e("Course", "manual-completions-wpcourseware"); ?></td>
					<td style="min-width: 400px">
						<select class="en_select2" id="course_id" name="course_id" onchange="manual_completions_wpcourseware_course_selected(this)">
							<option value=""><?php esc_html_e("-- SELECT --", "manual-completions-wpcourseware"); ?></option>
							<?php foreach ($courses as $key => $course): ?>
								<option value="<?php echo absint($course->ID); ?>"><?php echo esc_html($course->post_title); ?></option>
							<?php endforeach ?>
						</select>
						<div class="" id="gb_loading_animation" style="display:none;">
							<span class="dashicons dashicons-update"></span>
						</div>
					</td>
				</tr>
				<tr id="module" style="display: none;" onchange="manual_completions_wpcourseware_module_selected(this)">
					<td><?php esc_html_e("Module", "manual-completions-wpcourseware") ?></td>
					<td>
						<select class="en_select2" id="module_id" name="module_id">
							<option value=""><?php esc_html_e("-- SELECT --", "manual-completions-wpcourseware"); ?></option>
							<option value="all"><?php esc_html_e("-- Entire Course --", "manual-completions-wpcourseware"); ?></option>
						</select>
					</td>
				</tr>
				<tr id="unit" style="display: none;" onchange="manual_completions_wpcourseware_unit_selected(this)">
					<td>Unit</td>
					<td>
						<select class="en_select2" id="unit_id" name="unit_id">
							<option value=""><?php esc_html_e("-- SELECT --", "manual-completions-wpcourseware"); ?></option>
							<option value="all"><?php esc_html_e("-- Entire Module --", "manual-completions-wpcourseware"); ?></option>
						</select>
					</td>
				</tr>
				<tr id="quiz" style="display: none;">
					<td><?php esc_html_e("Quiz", "manual-completions-wpcourseware") ?></td>
					<td>
						<select class="en_select2" id="quiz_id" name="quiz_id" onchange="manual_completions_wpcourseware_quiz_selected(this)">
							<option value=""><?php esc_html_e("-- SELECT --", "manual-completions-wpcourseware"); ?></option>
						</select>
					</td>
				</tr>
				<tr id="users" style="display: none;">
					<td>Users</td>
					<td>
						<input role="searchbox" value="" placeholder="<?php esc_html_e("Search User", "manual-completions-wpcourseware"); ?>"/>
						<select id="user_ids" name="user_ids" onchange="manual_completions_wpcourseware_users_selected(this)">
							<option value=""><?php esc_html_e("-- Select a User --", "manual-completions-wpcourseware"); ?></option>
							<?php foreach ($users as $user) {
									$name = $user->ID.". ".$user->display_name;

									$additional_info = array();
									if($user->display_name != $user->user_login)
										$additional_info[] = $user->user_login;

									if($user->display_name != $user->user_email && $user->user_login != $user->user_email)
										$additional_info[] = $user->user_email;

									if(!empty($additional_info))
									$name = $name." (".implode(", ", $additional_info).")";
								?>
								<option value="<?php echo esc_attr( $user->ID ); ?>" data-user_login="<?php echo esc_attr(strtolower($user->user_login)); ?>" data-user_email="<?php echo esc_attr(strtolower($user->user_email)); ?>"><?php echo esc_html($name); ?></option>
							<?php } ?>
						</select>
						<?php esc_html_e("(Select Users, or, enter comma separated or space separated user_id. You can even copy/paste from CSV. Hit SPACE BAR after pasting.)", "manual-completions-wpcourseware"); ?>
					</td>
					<br>
					<td><button onclick="manual_completions_wpcourseware_get_enrolled_users()" class="button"><?php esc_html_e("Get All Enrolled Users", "manual-completions-wpcourseware"); ?></button></td>
				</tr>
			</table>
		</div>
		<div id="manual_completions_wpcourseware_table" class="manual_completions_wpcourseware">
			<div class="button-secondary" id="process_completions" onclick="manual_completions_wpcourseware_mark_complete()"><?php esc_html_e("Process Selected Completions", "manual-completions-wpcourseware"); ?> <span class="count"></span></div>
			<div class="button-secondary" id="check_completions" onclick="manual_completions_wpcourseware_check_completion()"><?php esc_html_e("Check Completion Status", "manual-completions-wpcourseware"); ?> <span class="count"></span></div>
			<span id="list_count"><?php echo sprintf( esc_html__("Total %s rows", "manual-completions-wpcourseware"), '<span class="count">0</span>'); ?> </span>
			<br>
			<div class="force_completion">
				<input id="force_completion" type="checkbox"> <?php esc_html_e("Force Completion (Ignore xAPI Content Completion Status)", "manual-completions-wpcourseware"); ?>
			</div>

			<table class="grassblade_table" style="width: 100%">
				<tr class="header">
					<th><input type="checkbox" id="select_all"></th>
					<th><?php esc_html_e("S.No", "manual-completions-wpcourseware"); ?></th>
					<th><?php esc_html_e("User", "manual-completions-wpcourseware"); ?></th>
					<th><?php esc_html_e("Course", "manual-completions-wpcourseware"); ?></th>
					<th><?php esc_html_e("Module", "manual-completions-wpcourseware"); ?></th>
					<th><?php esc_html_e("Unit", "manual-completions-wpcourseware"); ?></th>
					<th><?php esc_html_e("Quiz", "manual-completions-wpcourseware"); ?></th>
					<th><?php esc_html_e("Actions", "manual-completions-wpcourseware"); ?></th>
					<th><?php esc_html_e("Status", "manual-completions-wpcourseware"); ?></th>
				</tr>
			</table>
		</div>
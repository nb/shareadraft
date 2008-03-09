<?php
/*
Plugin Name: Share a Draft
Plugin URI: http://wordpress.org/extends/plugins/shareadraft/
Description: Let your friends preview one of your drafts, without giving them permissions to edit posts in your blog
Author: Nikolay Bachiyski
Version: 0.1
Author URI: http://nb.niichavo.org/
Generated At: www.wp-fun.co.uk;
*/ 

if (!class_exists('ShareADraft')) {
    class ShareADraft	{
		var $adminOptionsName = "ShareADraft_options";

	    function ShareADraft(){
			add_action('init', array(&$this, 'init'));
		}

		function init() {
			add_action("admin_menu", array(&$this,"add_admin_pages"));
			add_filter('the_posts', array(&$this, 'the_posts_intercept'));
			add_filter('posts_results', array(&$this, 'posts_results_intercept'));

			$this->adminOptions = $this->getAdminOptions();
			$this->adminOptions = $this->clearExpired($this->adminOptions);
			$this->saveAdminOptions();	

			load_plugin_textdomain('shareadraft');
        }
	
		function getAdminOptions() {
			global $current_user;
			$savedOptions = get_option($this->adminOptionsName);
			if (!$savedOptions || !isset($savedOptions[$current_user->id]) || !is_array($savedOptions[$current_user->id])) {
				$savedOptions = array();
			} else {
				$savedOptions = $savedOptions[$current_user->id];
			}
			return $savedOptions;
		}

		function saveAdminOptions(){
			global $current_user;
			$savedOptions = get_option($this->adminOptionsName);
			if (!is_array($savedOptions)) {
				$savedOptions = array();
			}
			$savedOptions[$current_user->id] = $this->adminOptions;
			update_option($this->adminOptionsName, $savedOptions);
		}

		function clearExpired($options) {
			$shared = array();
			if (!isset($options['shared']) || !is_array($options['shared'])) {
				return;
			}
			foreach($options['shared'] as $share) {
				if ($share['expires'] < time()) {
					continue;
				}
				$shared[] = $share;
			}
			$options['shared'] = $shared;
			return $options;
		}

		function add_admin_pages(){
			add_submenu_page("edit.php", "Share a Draft", "Share a Draft", 'edit_posts', __FILE__, array(&$this,"output_existing_menu_sub_admin_page"));
		}

		function process_post_options($params) {
			global $current_user;
			if (isset($params['post_id'])) {
				$p = get_post($params['post_id']);
				if (!$p) {
					return 'There is no such post!';
				}
				if ('draft' != get_post_status($p)) {
					return 'The post is not a draft!';
				}
				$exp = 60;
				$multiply = 60;
				if (isset($params['expires']) && ($e = intval($params['expires']))) {
					$exp = $e;
				}
				$mults = array('s' => 1, 'm' => 60, 'h' => 3600, 'd' => 24*3600);
				if (isset($params['measure']) && isset($mults[$params['measure']])) {
					$multiply = $mults[$params['measure']];
				}
				$this->adminOptions['shared'][] = array('id' => $p->ID, 'expires' => time() + $exp*$multiply, 'key' => uniqid('baba'.$p->ID.'_'));
				$this->saveAdminOptions();
			}	
		}

		function process_delete($params) {
			if (!isset($params['key']) || !isset($this->adminOptions['shared']) || !is_array($this->adminOptions['shared'])) {
				return '';
			}
			$shared = array();
			foreach($this->adminOptions['shared'] as $share) {
				if ($share['key'] == $params['key']) {
					continue;
				}
				$shared[] = $share;
			}
			$this->adminOptions['shared'] = $shared;
			$this->saveAdminOptions();
		}

		function get_drafts() {
			global $current_user;
			$my_drafts = get_users_drafts($current_user->id);
			$pending = get_others_pending($current_user->id);
			$others_drafts = get_others_drafts($current_user->id);
			$drafts_struct = array(
				array(
					__('Your Drafts:', 'shareadraft'),
					count($my_drafts),
					&$my_drafts,
				),
				array(
					__('Pending Review:', 'shareadraft'),
					count($pending),
					&$pending,
				),
				array(
					__('Others&#8217; Drafts:', 'shareadraft'),
					count($others_drafts),
					&$others_drafts,
				),
			);
			return $drafts_struct; 
		}

		function get_shared() {
			if (!isset($this->adminOptions['shared']) || !is_array($this->adminOptions['shared'])) {
				return array();
			}
			return $this->adminOptions['shared'];
		}

		function friendly_delta($s) {
			$m = (int)($s/60);
			$free_s = $s - $m*60;
			$h = (int)($s/3600);
			$free_m = (int)(($s - $h*3600)/60);
			$d = (int)($s/(24*3600));
			$free_h = (int)(($s - $d*(24*3600))/3600);
			if ($m < 1) {
				$res = array($s);
			} elseif ($h < 1) {
				$res = array($free_s, $m);
			} elseif ($d < 1) {
				$res = array($free_s, $free_m, $h);
			} else {
				$res = array($free_s, $free_m, $free_h, $d);
			}
			$names = array();
			if (isset($res[0])) $names[] = sprintf(__ngettext('%d second', '%d seconds', $res[0], 'shareadraft'), $res[0]);
			if (isset($res[1])) $names[] = sprintf(__ngettext('%d minute', '%d minutes', $res[1], 'shareadraft'), $res[1]);
			if (isset($res[2])) $names[] = sprintf(__ngettext('%d hour', '%d hours', $res[2], 'shareadraft'), $res[2]);
			if (isset($res[3])) $names[] = sprintf(__ngettext('%d day', '%d days', $res[3], 'shareadraft'), $res[3]);
			return implode(', ', array_reverse($names));
		}

		function output_existing_menu_sub_admin_page(){
			if (isset($_POST['shareadraft_submit'])) {
				$msg = $this->process_post_options($_POST);
			} elseif (isset($_GET['action']) && $_GET['action'] == 'delete') {
				$msg = $this->process_delete($_GET);
			}
		?>
			<div class="wrap">
			<h2><?php _e('Share a Draft', 'shareadraft'); ?></h2>
			<?php if ($msg):?>
			<div id="message" class="updated fade"><?php echo $msg; ?></div>
			<?php endif;?>
			<h3>Currently shared drafts</h3>
			<table class="widefat">
				<thead>
					<tr>
						<th>ID</th>
						<th>Title</th>
						<th>Link</th>
						<th>Expires after</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
			<?php
				$s = $this->get_shared();
				foreach($s as $share):
					$p = get_post($share['id']);
			?>
					<tr>
						<td><?php echo $p->ID; ?></td>
						<td><?php echo $p->post_title; ?></td>
						<!-- TODO: make the draft link selecatble -->
						<td><?php echo get_option('siteurl'); ?>?p=<?php echo $p->ID?>&amp;shareadraft=<?php echo $share['key']; ?></td>
						<td><?php echo $this->friendly_delta($share['expires'] - time()); ?></td>
						<td><a class="delete" href="edit.php?page=<?php echo plugin_basename(__FILE__); ?>&amp;action=delete&amp;key=<?php echo $share['key']; ?>">Delete</a></td>
			<?php
				endforeach;
				if (empty($s)):
			?>
				<tr>
					<td colspan="5">No shared drafts!</td>
				</tr>
			<?php
				endif;
			?>
				</tbody>
			</table>
			<h3>Share a draft</h3>
			<form id="shareadraft-share" action="" method="post">
				<p>
						<select id="shareadraft-postid" name="post_id">
							<option value=""><?php _e('Choose a draft', 'shareadraft'); ?></option>
							<?php
								$drafts_struct = $this->get_drafts();
								print_r($drafts_struct);
								foreach($drafts_struct as $draft_type):
									if ($draft_type[1]):
							?>
							<option value="" disabled="disabled"></option>
							<option value="" disabled="disabled"><?php echo $draft_type[0]; ?></option>
							<?php
										foreach($draft_type[2] as $draft):
							?>
							<option value="<?php echo $draft->ID?>"><?php echo wp_specialchars($draft->post_title); ?></option>
							<?php
										endforeach;
									endif;
								endforeach;
							?>
						</select>
				</p>
				<p>
						<input type="submit" name="shareadraft_submit" value="Share it" />
						for
						<input name="expires" type="text" value="2" size="4"/>
						<select name="measure">
							<option value="s">seconds</option>
							<option value="m">minutes</option>
							<option value="h" selected="selected">hours</option>
							<option value="d">days</option>
						</select>.
				</p>
			</form>
			</div>
		<?php
		}

		function can_view($post_id) {
			if (!isset($_GET['shareadraft']) || !isset($this->adminOptions['shared']) || !is_array($this->adminOptions['shared'])) {
				return false;
			}
			foreach($this->adminOptions['shared'] as $share) {
				if ($share['id'] == $post_id && $share['key'] == $_GET['shareadraft']) {
					return true;
				}
			}
			return false;
		}

		function posts_results_intercept($posts) {
			if (1 != count($posts)) return $posts;
			$post = &$posts[0];
			$status = get_post_status($post);
			if ('draft' == $status && $this->can_view($post->ID)) {
				$this->shared_post = & $post;
			}
			return $posts;
		}

		function the_posts_intercept($posts){
			if (empty($posts) && !is_null($this->shared_post)) {
				return array(&$this->shared_post);
			} else {
				$this->shared_post = null;
				return $posts;
			}
		}
    }
}

if (class_exists('ShareADraft')) {
	$ShareADraft = new ShareADraft();
}

?>

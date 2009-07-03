<?php
/*
Plugin Name: Metaverse ID
Plugin URI: http://signpostmarv.name/mv-id/
Description: Display your identity from around the metaverse!
Version: 0.11.0
Author: SignpostMarv Martin
Author URI: http://signpostmarv.name/
 Copyright 2009 SignpostMarv Martin  (email : mv-id.wp@signpostmarv.name)
    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
require_once('abstracts.php');
require_once('linkify.php');
class mv_id_plugin
{
	protected static $metaverse_classes = array();
	protected static $supported_mvs = array();
	public static function DOMDocument($data)
	{
		$doc = new DOMDocument;
		if($doc instanceof DOMDocument && @$doc->loadHTML($data) !== false)
		{
			return $doc;
		}
		else
		{
			return false;
		}
	}
	public static function SimpleXML($data)
	{
		$XML = @simplexml_load_string($data);
		if($XML instanceof SimpleXMLElement)
		{
			return $XML;
		}
		else
		{
			return false;
		}
	}
	public static function XPath($node,$query,$allowZeroLength=false)
	{
		if($node instanceof DOMDocument)
		{
			$xpath = new DOMXPath($node);
			if($xpath instanceof DOMXPath)
			{
				$result = $xpath->evaluate($query);
				if($result === false)
				{
					return false;
				}
				else if(($result->length == 0 && $allowZeroLength) || $result->length >= 1)
				{
					return $result;
				}
				else
				{
					return false;
				}
			}
			else
			{
				return false;
			}
		}
		elseif($node instanceof SimpleXMLElement)
		{
			$result = $node->xpath($query);
			if(empty($result))
			{
				return false;
			}
			else
			{
				return $result;
			}
		}
		else
		{
			return false;
		}
	}
	public static function curl($url,array $curl_opts=null)
	{
		if(isset($curl_opts['method']) === false || $curl_opts['method'] === 'get')
		{
			$resp = wp_remote_get($url,$curl_opts);
		}
		else
		{
			$resp = wp_remote_post($url,$curl_opts);
		}
		if(is_wp_error($resp))
		{
			return null;
		}
		if(isset($curl_opts,$curl_opts['headers'],$curl_opts['headers']['If-Modified-Since']))
		{
			$header = wp_remote_retrieve_header($resp,'last-modified');
			if(isset($header) && strtotime($header) <= $curl_opts['headers']['If-Modified-Since'])
			{
				return true;
			}
		}
		else
		{
			return wp_remote_retrieve_body($resp);
		}
		$ch = curl_init($url);
		if(empty($curl_opts))
		{
			$curl_opts = array();
		}
		if(isset($curl_opts[CURLOPT_SSL_VERIFYPEER]) === false)
		{
			$curl_opts[CURLOPT_SSL_VERIFYPEER] = false;
		}
		if(isset($curl_opts[CURLOPT_RETURNTRANSFER]) === false)
		{
			$curl_opts[CURLOPT_RETURNTRANSFER] = true;
		}
		$no_hack_needed = (ini_get('safe_mode') !== '1' && ini_get('open_basedir') === false);
		if($no_hack_needed)
		{
			$curl_opts[CURLOPT_FOLLOWLOCATION] = true;
		}
		else
		{
			$curl_opts[CURLOPT_FOLLOWLOCATION] = false;
			$curl_opts[CURLOPT_HEADER] = true;
		}
		if(isset($curl_opts[CURLOPT_TIMEVALUE]) !== false)
		{
			$curl_opts[CURLOPT_FILETIME] = true;
		}
		curl_setopt_array($ch,$curl_opts);
		$data = curl_exec($ch);
		if($no_hack_needed === false)
		{
			$redirects = 5;
			while($redirects>0)
			{
				if(($pos = strpos($data,'Location: http')) !== false)
				{
					$pos = strpos($data,'http',$pos);
					$url = substr($data,$pos,strpos($data,"\r\n",$pos) - $pos);
					$ch = curl_init($url);
					curl_setopt_array($ch,$curl_opts);
					$data = curl_exec($ch);
					--$redirects;
				}
				else
				{
					$redirects = 0;
				}
			}
			$data = trim(substr($data,strpos($data,"\r\n\r\n")));
		}
		if(isset($curl_opts[CURLOPT_TIMEVALUE]) && curl_getinfo($ch,CURLINFO_FILETIME) !== -1 && ($curl_opts[CURLOPT_TIMEVALUE] <= curl_getinfo($ch,CURLINFO_FILETIME)))
		{
			return false;
		}
		curl_close($ch);
		return $data;
	}
	protected static function wpdb()
	{
		global $wpdb;
		return $wpdb;
	}
	public static function bday_label(mv_id_vcard_widget $vcard)
	{
		switch(get_class($vcard))
		{
			case 'mv_id_vcard_agni_sl':
			case 'mv_id_vcard_teen_sl':
				return 'Rezday';
			break;
			default:
				return 'Created';
			break;
		}
	}
	public static function db_tablename()
	{
		global $wpdb;
		return $wpdb->prefix . 'mv_id';
	}
	protected static function install()
	{
		global $wpdb;
		$structure = 'CREATE TABLE IF NOT EXISTS ' . self::db_tablename() . ' (
`user_id` BIGINT( 20 ) UNSIGNED NOT NULL DEFAULT "1",
`metaverse` CHAR( 32 ) NOT NULL ,
`id` CHAR( 255 ) NOT NULL ,
`last_mod` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ,
`cache` BLOB NULL DEFAULT NULL ,
PRIMARY KEY ( `user_id`,`metaverse` , `id` )
)';
		$wpdb->query($structure);
		self::upgrade();
	}
	protected static function upgrade()
	{
		global $wpdb;
		$alter_sql = 'ALTER TABLE ' . self::db_tablename() . ' ADD `user_id` BIGINT( 20 ) UNSIGNED NOT NULL DEFAULT "1" FIRST, DROP PRIMARY KEY, ADD PRIMARY KEY ( `user_id`, `metaverse`, `id`)';
		$check_sql = 'SHOW COLUMNS FROM ' . self::db_tablename();
		$schema = $wpdb->get_results($check_sql);
		if(empty($schema) === false)
		{
			$fields = array();
			foreach($schema as $field)
			{
				$fields[] = $field->Field;
			}
			unset($schema);
			if(in_array('user_id',$fields) === false)
			{
				$wpdb->query($alter_sql);
			}
			unset($fields);
		}
	}
	protected static function uninstall()
	{
		global $wpdb;
		$wpdb->query('DROP TABLE IF EXISTS ' . self::db_tablename());
	}
	public static function activate()
	{
		self::install();
		wp_schedule_event(time(),'hourly','mv_id_plugin__regenerate_cache');
	}
	public static function deactivate()
	{
		self::uninstall();
		wp_clear_scheduled_hook('mv_id_plugin__regenerate_cache');
	}
	public static function register_metaverses()
	{
		do_action('mv_id_plugin__register_metaverses');
	}
	public static function delete_user($user_ID)
	{
		global $wpdb;
		static $delete_sql;
		if(isset($delete_sql) === false)
		{
			$delete_sql = 'DELETE FROM ' . self::db_tablename() . ' WHERE user_id = %s';
		}
		$wpdb->query($wpdb->prepare($delete_sql,$user_ID));
	}
	public static function profile_update($user_ID)
	{
		$user_info = get_userdata($user_ID);
		if(isset($user_info->user_level) === false)
		{
			self::delete_user($user_ID);
		}
	}
	public static function cron()
	{
		global $wpdb;
		$mv_ids = self::get_all_mv_ids(true);
		if(isset($mv_ids) && is_array($mv_ids) && count($mv_ids) > 0)
		{
			foreach($mv_ids as $id)
			{
				self::refresh($id->metaverse,$id->id);
			}
		}
	}
	protected static function refresh($metaverse,$id)
	{
		$vcard = call_user_func_array(self::$metaverse_classes[$metaverse] . '::factory',array($id,self::get_mv_id_last_mod($metaverse,$id)));
		if(isset($vcard) && ($vcard instanceof mv_id_vcard_widget))
		{
			self::cache($metaverse,$id,$vcard);
		}
		else if(isset($vcard) && $vcard === true)
		{
			static $tweak_sql;
			if(isset($tweak_sql) === false)
			{
				$tweak_sql = 'UPDATE ' . self::db_tablename() . ' SET last_mod=NOW() WHERE metaverse = %s AND id = %s';
			}
			self::wpdb()->query(self::wpdb()->prepare($tweak_sql,$metaverse,$id));
		}
	}
	public static function table_exists()
	{
		global $wpdb;
		$tables = $wpdb->get_results($wpdb->prepare('SHOW TABLES LIKE %s',self::db_tablename()),ARRAY_N);
		if(empty($tables) === false)
		{
			foreach($tables as $table)
			{
				list($table) = $table;
				if($table == self::db_tablename())
				{
					return true;
				}
			}
		}
		return false;
	}
	public static function delete($metaverse,$id)
	{
		global $wpdb;
		global $user_ID;
		get_currentuserinfo();
		static $delete_sql;
		if(isset($delete_sql) === false)
		{
			$delete_sql = 'DELETE FROM ' . self::db_tablename() . ' WHERE user_ID = %s AND metaverse = %s AND id = %s';
		}
		$wpdb->query($wpdb->prepare($delete_sql,$user_ID,$metaverse,$id));
	}
	public static function add($metaverse,$id)
	{
		global $user_ID;
		global $user_level;
		get_currentuserinfo();
		if(self::nice_name($metaverse) !== false && self::is_id_valid($metaverse,$id) === true && $user_ID !== '' && $user_level >= 1)
		{
			global $wpdb;
			static $add_sql;
			if(isset($add_sql) === false)
			{
				$add_sql = 
'INSERT INTO ' . self::db_tablename() . ' (user_id,metaverse,id) VALUES(%s,%s,%s)
ON DUPLICATE KEY UPDATE
	cache=NULL';
			}
			$wpdb->query($wpdb->prepare($add_sql,$user_ID,$metaverse,$id));
		}
	}
	public static function cache($metaverse,$id,mv_id_vcard $vcard) // do not call before add
	{
		global $user_ID;
		if(self::nice_name($metaverse) !== false && self::is_id_valid($metaverse,$id) === true)
		{
			global $wpdb;
			static $cache_sql;
			if(isset($cache_sql) === false)
			{
				$cache_sql = 'UPDATE ' . self::db_tablename() . ' SET cache = %s,last_mod=NOW() WHERE metaverse = %s AND id = %s';
			}
			return $wpdb->query($wpdb->prepare($cache_sql,serialize($vcard),$metaverse,$id));
		}
		else
		{
			return false;
		}
	}
	public static function get($metaverse,$id)
	{
		global $wpdb;
		static $get_sql;
		if(isset($get_sql) === false)
		{
			$get_sql = 'SELECT cache FROM ' . self::db_tablename() . ' WHERE metaverse=%s AND id=%s AND cache IS NOT NULL LIMIT 1';
		}
		$cache = $wpdb->get_var($wpdb->prepare($get_sql,$metaverse,$id));
		if(empty($cache) === false)
		{
			return unserialize($cache);
		}
		else
		{
			return false;
		}
	}
	public static function get_mv_id_last_mod($metaverse,$id,$force=false)
	{
		static $get_sql;
		if(isset($get_sql) === false)
		{
			$get_sql = 'SELECT last_mod FROM ' . self::db_tablename() . ' WHERE metaverse=%s AND id=%s AND cache IS NOT NULL ORDER BY last_mod DESC LIMIT 1';
		}
		$last_mod = self::wpdb()->get_var(self::wpdb()->prepare($get_sql,$metaverse,$id));
		if(empty($last_mod))
		{
			return false;
		}
		else
		{
			return strtotime($last_mod);
		}
	}
	public static function get_all_mv_ids($force=false)
	{
		global $wpdb;
		static $get_sql;
		static $mv_ids;
		if(isset($get_sql) === false)
		{
			$get_sql = 'SELECT metaverse,id,user_id FROM ' . self::db_tablename();
		}
		if(empty($mv_ids) || $force == true)
		{
			$mv_ids = $wpdb->get_results($get_sql);
		}
		return $mv_ids;
	}
	public static function get_all_mv_ids_and_cache($force=false)
	{
		global $wpdb;
		global $user_ID;
		get_currentuserinfo();
		static $get_sql;
		static $user_sql = ' WHERE user_id = %s';
		static $mv_ids = array();
		if(isset($get_sql) === false)
		{
			$get_sql = 'SELECT metaverse,id,cache FROM ' . self::db_tablename();
		}
		if(isset($mv_ids[$user_ID]) === false || $force == true)
		{
			if($user_ID === '')
			{
				$mv_ids[$user_ID] = $wpdb->get_results($get_sql);
			}
			else
			{
				$mv_ids[$user_ID] = $wpdb->get_results($wpdb->prepare($get_sql . $user_sql,$user_ID));
			}
			foreach($mv_ids[$user_ID] as $k=>$v)
			{
				if(empty($v->cache) === false)
				{
					$mv_ids[$user_ID][$k]->cache = unserialize($v->cache);
				}
			}
		}
		return $mv_ids[$user_ID];
	}
	public static function get_uncached_mv_ids($force=false,$all_users=false)
	{
		global $wpdb;
		static $mv_ids = array();
		static $get_sql;
		$_zomg_user_ID = '';
		if($all_users === false)
		{
			global $user_ID;
			get_currentuserinfo();
			$_zomg_user_ID = $user_ID;
			static $user_sql = ' AND user_id = %s';
		}
		if(isset($get_sql) === false)
		{
			$get_sql = 'SELECT metaverse,id FROM ' . self::db_tablename() . ' WHERE cache IS NULL';
		}
		if(isset($mv_ids[$_zomg_user_ID]) === false || $force === true)
		{
			if($_zomg_user_ID === '' || $all_users === true)
			{
				$mv_ids[$_zomg_user_ID] = $wpdb->get_results($get_sql);
			}
			else
			{
				$mv_ids[$_zomg_user_ID] = $wpdb->get_results($wpdb->prepare($get_sql . $user_sql,$_zomg_user_ID));
			}
		}
		return $mv_ids[$_zomg_user_ID];
	}
	public static function register_metaverse($nice_name,$metaverse,$class)
	{
		self::$metaverse_classes[$metaverse] = $class;
		self::$supported_mvs[$nice_name] = $metaverse;
	}
	public static function registered_metaverses()
	{
		return self::$metaverse_classes;
	}
	public static function nice_name($metaverse)
	{
		return in_array($metaverse,self::$supported_mvs) ? array_search($metaverse,self::$supported_mvs) : false;
	}
	public static function metaverse($nice_name)
	{
		return isset(self::$supported_mvs[$nice_name]) ? self::$supported_mvs[$nice_name] : false;
	}
	public static function is_id_valid($metaverse,$id)
	{
		return (
			isset(self::$metaverse_classes[$metaverse]) === true &&
			call_user_func_array(self::$metaverse_classes[$metaverse] . '::is_id_valid',array($id)) === true
		);
	}
	public static function mv_needs_admin($metaverse=null)
	{
		static $mvs;
		if(isset($mvs) === false)
		{
			$mvs = array();
			foreach(self::$metaverse_classes as $metaverse=>$class)
			{
				if(in_array('mv_id_needs_admin',class_implements($class,false)))
				{
					$mvs[$metaverse] = $class;
				}
			}
		}
		return isset($metaverse) ? ( isset($mvs[$metaverse]) ? $mvs[$metaverse] : false ) : $mvs;
	}
	public static function admin_actions()
	{
		global $user_level;
		get_currentuserinfo();
		if($user_level >= 1)
		{
			add_submenu_page('profile.php', 'Metaverse ID', 'Metaverse ID', 'read', 'mv-id', 'mv_id_plugin::user_ids');
		}
		if(self::mv_needs_admin() !== array())
		{
			add_options_page('Metaverse ID','Metaverse ID',1,'mv-id','mv_id_plugin::admin');
		}
		add_filter('plugin_action_links', 'mv_id_plugin::plugin_actions', 10, 2);
	}
	public static function plugin_actions($links, $file) {
		static $this_plugin;
		if(isset($this_plugin) === false)
		{
			$this_plugin = plugin_basename(__FILE__);
		}
		if($file === $this_plugin)
		{
			$settings_link = '<a href="profile.php?page=mv-id">Manage</a>';
			$links[] = $settings_link;
		}
		return $links;
	}
	public static function javascript()
	{
		$ids = $mv_ids = $mv_id_formats = $mv_id_nice_names = array();
		foreach(self::registered_metaverses() as $mv_id=>$mv_class)
		{
			$mv_ids[] = $mv_id;
			$mv_id_formats[] = call_user_func(self::$metaverse_classes[$mv_id] . '::id_format');
			$mv_id_nice_names[$mv_id] = mv_id_plugin::nice_name($mv_id);
		}
		foreach(self::get_all_mv_ids() as $mv_id)
		{
			if(isset($ids[$mv_id->metaverse]) === false)
			{
				$ids[$mv_id->metaverse] = array();
			}
			$ids[$mv_id->metaverse][] = array('user'=>$mv_id->user_id,'id'=>$mv_id->id);
		}
?>
<script type="text/javascript">/*<![CDATA[*/
mv_id_plugin = {
	metaverses : <?php echo json_encode($mv_ids);?>,
	ids        : <?php echo json_encode($ids);?>,
	formats    : <?php echo json_encode($mv_id_formats);?>,
	nice_names : <?php echo json_encode($mv_id_nice_names);?>,
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
/*]]>*/</script>
<?php
	}
	public static function user_ids()
	{
		if(isset($_POST['delete']))
		{
			foreach($_POST['delete'] as $delete_this)
			{
				list($metaverse,$id) = explode('::',$delete_this);
				self::delete($metaverse,$id);
				unset($_POST['delete'][$delete_this]);
				if(isset($_POST['add'],$_POST['add'][$metaverse],$_POST['add'][$metaverse][$id]))
				{
					unset($_POST['add'][$metaverse][$id]);
				}
				if(isset($_POST['update']) && ($pos = array_search($delete_this,$_POST['update'])) !== false)
				{
					unset($_POST['update'][$pos]);
				}
			}
		}
		if(isset($_POST['add']))
		{
			foreach($_POST['add'] as $v)
			{
				self::add($v['metaverse'],$v['id']);
			}
		}
		if(isset($_POST['update']))
		{
			foreach($_POST['update'] as $update_this)
			{
				list($metaverse,$id) = explode('::',$update_this);
				self::refresh($metaverse,$id);
			}
		}
		$mv_ids = self::get_all_mv_ids_and_cache();
?>
	<h2>Your Metaverse IDs</h2>
<?php	
	if(count(self::registered_metaverses()) < 1)
	{
?>
		<p>There are no Metaverses available to use.</p>
<?php
		return;
	}
?>
	<form action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>" method="post">
<?php
		if(count($mv_ids) > 0)
		{
?>
		<table class="hreview">
			<caption>Current IDs</caption>
			<tr>
				<th>Delete</th>
				<th>Update</th>
				<th>Metaverse ID</th>
				<th>Preview</th>
			</tr>
<?php

			foreach($mv_ids as $id)
			{
				if(self::nice_name($id->metaverse) !== false)
				{
					$vcard = $id->cache;
?>
			<tr>
				<td><input type="checkbox" name="delete[]" value="<?php echo $id->metaverse,'::',$id->id; ?>" title="Delete '<?php echo $id->id; ?>' ?" /></td>
				<td><input type="checkbox" name="update[]" value="<?php echo $id->metaverse,'::',$id->id; ?>" title="Update '<?php echo $id->id; ?>' ?" <?php if($vcard === NULL){ ?>checked="checked"<?php } ?> /></td>
				<td><?php echo self::nice_name($id->metaverse); ?><br /><strong><?php echo $id->id; ?></strong></td>
				<td><?php
				if($vcard instanceof mv_id_vcard_widget)
				{
					do_action('mv_id_plugin__output_vcard',$vcard);
				}
				else if($id->cache === NULL)
				{
?>Profile is not yet cached.<?php
				}
				else
				{
?>No Preview Available, possibly a problem fetching or caching the profile.<?php
				}
?></td>
			</tr>
<?php
				}
			}

?>
		</table>
<?php
		}
?>
		<div id="add-mv-ids">
			<h3>Add ID</h3>
			<ol>
				<li><select name="add[0][metaverse]">
<?php
		foreach(self::registered_metaverses() as $mv_id=>$mv_class)
		{
?>
				<option value="<?php echo $mv_id; ?>" title="<?php echo htmlentities2(call_user_func(self::$metaverse_classes[$mv_id] . '::id_format')); ?>"><?php echo htmlentities2(mv_id_plugin::nice_name($mv_id)); ?></option>

<?php
		}
?>
				</select> <input name="add[0][id]" type="text" maxlength="255" /></li>
			</ol>
			<script type="text/javascript">/*<![CDATA[*/
var mv_id_plugin__id_div = document.getElementById('add-mv-ids');
var mv_id_plugin__num_entries = 1;
var a = document.createElement('a');
a.href = 'javascript:mv_id_plugin.add_more()';
a.appendChild(document.createTextNode('Add More IDs'));
mv_id_plugin__id_div.appendChild(a);
			/*]]>*/</script>
		</div>
		<p>
			<input type="submit" name="Submit" value="Update/Delete" />
		</p>
	</form>
<?php
	}
	public static function admin()
	{
		if(isset($_POST) && empty($_POST) === false)
		{
			$needs_admin = self::mv_needs_admin();
			foreach(array_keys($_POST) as $metaverse)
			{
				if(isset($needs_admin[$metaverse]) === false)
				{
					unset($_POST[$metaverse]);
				}
				else
				{
					$admin_fields = call_user_func($needs_admin[$metaverse] . '::admin_fields');
					foreach($_POST[$metaverse] as $field=>$value)
					{
						if(in_array($field,array_keys($admin_fields)) === false)
						{
							unset($_POST[$metaverse][$field]);
						}
						else
						{
							if(preg_match($admin_fields[$field]['regex'],$value) !== 1)
							{
								unset($_POST[$metaverse]);
							}
						}
					}
				}
			}
			if(empty($_POST))
			{
				unset($_POST);
			}
			else
			{
				foreach($_POST as $metaverse=>$config)
				{
					$option_label = 'mv-id::' . $metaverse;
					$value = serialize($config);
					if(get_option($option_label))
					{
						update_option($option_label,$value);
					}
					else
					{
						add_option($option_label,$value,'','no');
					}
				}
			}
		}
?>
	<h2>Metaverse ID Admin</h2>
	<form action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>" method="post">
<?php
		foreach(self::mv_needs_admin() as $metaverse=>$class)
		{
			$fields = call_user_func($class . '::admin_fields');
			$option = get_option('mv-id::' . $metaverse);
			if($option)
			{
				$option = unserialize($option);
			}
?>
		<fieldset>
			<legend><?php echo htmlentities2(self::nice_name($metaverse));?></legend>
			<ol>
<?php
			foreach($fields as $id=>$field)
			{
?>				<li><label for="<?php echo str_replace(' ','_',$metaverse),'-',$id; ?>"><?php echo htmlentities2($field['name']);?></label> <input id="<?php echo str_replace(' ','_',$metaverse),'-',$id; ?>" name="<?php echo $metaverse,'[',$id; ?>]" <?php if($option){ echo 'value="',$option[$id],'"';} ?> /></li>
<?php
			}
?>
			</ol>
			<input type="submit" value="Configure" />
		</fieldset>
<?php
		}
?>
	</form>
<?php }
}
class mv_id_plugin_widgets
{
	# regex nabbed from http://svn.wp-plugins.org/sem-autolink-uri/trunk/sem-autolink-uri.php
	const regex_linkify = "/
		\b									# word boundary
		(
			(?:								# link starting with a scheme
				http(?:s)?
			|
				ftp
			)
			:\/\/
		|
			www\.							# link starting with no scheme
		)
		(
			(								# domain
				localhost
			|
				[0-9a-zA-Z_\-]+
				(?:\.[0-9a-zA-Z_\-]+)+
			)
			(?:								# maybe a subdirectory
				\/
				[0-9a-zA-Z~_\-+\.\/,&;]*
			)?
			(?:								# maybe some parameters
				\?[0-9a-zA-Z~_\-+\.\/,&;=]+
			)?
			(?:								# maybe an id
				\#[0-9a-zA-Z~_\-+\.\/,&;]+
			)?
		)
		/imsx";
	public static function name($metaverse,$id)
	{
		return 'Metaverse ID: ' . mv_id_plugin::nice_name($metaverse) . ' (' . $id . ')';
	}
	protected static function current_metaverses()
	{
		if(mv_id_plugin::table_exists() === false)
		{
			return array();
		}
		static $metaverses;
		if(isset($metaverses) === false)
		{
			$metaverses = array();
			global $wpdb;
			$get_sql = 'SELECT DISTINCT metaverse FROM ' . mv_id_plugin::db_tablename() . ' WHERE cache IS NOT NULL';
			$_metaverses = $wpdb->get_results($get_sql);
			foreach($_metaverses as $metaverse)
			{
				$metaverses[] = $metaverse->metaverse;
			}
			unset($_metaverses,$metaverse);
		}
		return $metaverses;
	}
	public static function register()
	{
		$classes = mv_id_plugin::registered_metaverses();
		foreach(self::current_metaverses() as $metaverse)
		{
			if(isset($classes[$metaverse]) === true)
			{
				register_sidebar_widget('Metaverse ID: ' . mv_id_plugin::nice_name($metaverse),$classes[$metaverse] . '::widget');
			}
		}
	}
	public static function output(mv_id_vcard $vcard)
	{
		ob_start();
		echo
			str_repeat("\t",5),'<div class="hresume">',"\n",
			str_repeat("\t",6),'<address class="contact vcard">',"\n",
			str_repeat("\t",7),'<a class="url fn" rel="me" href="',$vcard->url(),'">',htmlentities2($vcard->name()),'</a><br />',"\n",
			str_repeat("\t",7),'<span class="uid" style="display:none;">',$vcard->uid(),'</span>',"\n";
		if($vcard->img() !== null)
		{
			echo str_repeat("\t",7),'<img class="photo" src="',$vcard->image_url(),'" alt="',htmlentities2($vcard->name()),'"  />',"\n";
		}
		echo str_repeat("\t",6),'</address>',"\n";
		if(isset($vcard->stats()->bday))
		{
			echo str_repeat("\t",6),'<div class="vevent account-creation"><span class="summary"><span style="display: none;">',
				htmlentities2($vcard->name()),'\'s',(mv_id_plugin::bday_label($vcard) === 'Created' ? ' account' : ' '),'</span>',
				htmlentities2(mv_id_plugin::bday_label($vcard)),'</span>: <abbr class="dtstart" title="',
				$vcard->stats()->bday,'">',date('jS M, Y',strtotime($vcard->stats()->bday)),'</abbr></div>',"\n";
		}
		if($vcard->description() !== null)
		{
			echo str_repeat("\t",6),'<p class="summary">',str_replace("\n","<br />\n",apply_filters('mv_id_linkify',htmlentities2($vcard->description()),null,array('me'))),'</p>',"\n";
		}
		if(is_array($vcard->skills()))
		{
			echo str_repeat("\t",6),'<ul>',"\n";
			foreach($vcard->skills() as $skill)
			{
				echo str_repeat("\t",6),'<li>',"\n";
				if(is_string($skill->url()))
				{
					echo '<a class="skill" rel="tag" href="',$skill->url(),'">';
				}
				else
				{
					echo '<span class="skill">';
				}
				echo htmlentities2($skill->name());
				if(is_string($skill->url()))
				{
					echo '</a> ';
				}
				else
				{
					echo '</span> ';
				}
				echo htmlentities2($skill->value()),'</li>',"\n";
			}
			echo str_repeat("\t",6),'</ul>',"\n";
		}
		if(is_string(call_user_func(get_class($vcard) . '::affiliations_label')) && is_array($vcard->affiliations()))
		{
			echo
				str_repeat("\t",6),'<strong>',htmlentities2(call_user_func(get_class($vcard) . '::affiliations_label')),'</strong>',"\n",
				str_repeat("\t",7),'<ul>',"\n";
				foreach($vcard->affiliations() as $affiliation)
				{
					echo str_repeat("\t",8),'<li class="affiliation vcard"><span class="fn org">';
					if( $affiliation->url() !== false)
					{
						echo '<a class="url" href="',$affiliation->url(),'">';
					}
					echo htmlentities($affiliation->name(),ENT_QUOTES,'UTF-8');
					if($affiliation->url() !== false)
					{
						echo '</a>';
					}
					echo '</span></li>',"\n";
				}
			echo str_repeat("\t",6),'</ul>',"\n";
		}
		echo str_repeat("\t",5),'</div>',"\n";
		$hresume = ob_get_contents();
		ob_end_clean();
		echo apply_filters('post_output_mv_id_vcard',$hresume,$vcard);
	}
}
class mv_id_plugin_widget extends WP_Widget {
	public function __construct( $id_base = false, $widget_options = array(), $control_options = array() ) {
		parent::__construct($id_base,'Metaverse ID',$widget_options,$control_options);
	}
    public function widget($args, $instance) {
		$vcard = mv_id_plugin::get($instance['metaverse'],$instance['id']);
		if(($vcard instanceof mv_id_vcard_widget) === false)
		{
			return;
		}
        extract( $args );
		echo $before_widget,$before_title,$instance['title'],$after_title,mv_id_plugin_widgets::output($vcard),$after_widget;
    }
	public function form($instance)
	{
		if(empty($instance) === false)
		{
			$vcard = mv_id_plugin::get($instance['metaverse'],$instance['id']);
?>
		<em>Current</em>
		<?php if(($vcard instanceof mv_id_vcard_widget) === false)
			{?>
		<p><?php echo wp_specialchars($instance['metaverse']),'<br />',"\n",wp_specialchars($instance['id']); ?></p>
<?php
			}
			else
			{
				?> <hr /> <?php
				mv_id_plugin_widgets::output($vcard);
				?> <hr /> <?php
			}
		}
?>
		<p><select id="<?php echo $this->get_field_id('metaverse'); ?>" name="<?php echo $this->get_field_name('metaverse'); ?>"></select></p>
		<p><select id="<?php echo $this->get_field_id('id'); ?>" name="<?php echo $this->get_field_name('id'); ?>"></select></p>
		<script type="text/javascript">/*<![CDATA[*/
mv_id_plugin.populate_select_mv('<?php echo $this->get_field_id('metaverse'); ?>','<?php echo $this->get_field_id('id'),'\',',json_encode($instance); ?>);
		/*]]>*/</script>
<?php
	}
	function update($new, $old) {
		$this->name = $new_instance['metaverse'] . '::' . $new_instance['id'];
		return $new;
	}
}
require_once('metaverses/second-life.php');
require_once('metaverses/free-realms.php');
require_once('metaverses/wow.php');
require_once('metaverses/metaplace.php');
require_once('metaverses/lotro.php');
require_once('metaverses/eve.php');
require_once('metaverses/pq.php');
register_activation_hook(__FILE__,'mv_id_plugin::activate');
register_deactivation_hook(__FILE__,'mv_id_plugin::deactivate');
add_action('widgets_init', create_function('', 'return register_widget("mv_id_plugin_widget");'));
add_action('mv_id_plugin__regenerate_cache','mv_id_plugin::cron');
add_action('mv_id_plugin__output_vcard','mv_id_plugin_widgets::output');
add_action('admin_menu','mv_id_plugin::admin_actions');
add_action('plugins_loaded','mv_id_plugin::register_metaverses');
add_action('widgets_init','mv_id_plugin_widgets::register');
add_action('delete_user','mv_id_plugin::delete_user');
add_action('profile_update','mv_id_plugin::profile_update');
add_action('admin_head','mv_id_plugin::javascript');
?>
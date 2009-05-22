<?php
/*
Plugin Name: Metaverse ID
Plugin URI: http://blog.signpostmarv.name/mv-id/
Description: Display your identity from around the metaverse!
Version: 0.6
Author: SignpostMarv Martin
Author URI: http://blog.signpostmarv.name/
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
interface mv_id_vcard_funcs
{
	public function uid();
	public function name();
	public function image_url();
	public function description();
	public function url();
	public static function is_id_valid($id);
	public static function factory($id);
	public static function widget(array $args);
	public static function id_format();
}
abstract class mv_id_vcard implements mv_id_vcard_funcs
{
	protected $uid;
	protected $name;
	protected $image;
	protected $description;
	public function __construct($uid,$name,$image=null,$description=null,$url=null)
	{
		$this->uid = $uid;
		$this->name = $name;
		$this->image = $image;
		$this->description = $description;
		$this->url = $url;
	}
	public function uid()
	{
		return $this->uid;
	}
	public function name()
	{
		return $this->name;
	}
	public function img()
	{
		return $this->image;
	}
	public function description()
	{
		return $this->description;
	}
	public function url()
	{
		if(isset($this->url) === false)
		{
			$this->url = sprintf(constant(get_class($this) . '::sprintf_url'),$this->uid());
		}
		return $this->url;
	}
	public function image_url()
	{
		return sprintf(constant(get_class($this) . '::sprintf_img'),$this->img());
	}
	public static function output(mv_id_vcard $vcard)
	{
?>
					<div class="reviewer item vcard">
						<a class="url fn summary" rel="me" href="<?php echo $vcard->url(); ?>"><?php echo htmlentities2($vcard->name()); ?></a><br />
						<span class="uid" style="display:none;"><?php echo $vcard->uid(); ?></span>
<?php
					if($vcard->img() !== null)
					{
?>
						<img class="photo" src="<?php echo $vcard->image_url(); ?>" alt="<?php echo htmlentities2($vcard->name()); ?>"  />
<?php			
					}
?>
<?php
					if($vcard->description() !== null)
					{
?>
						<p class="description"><?php echo str_replace("\n","<br />\n",htmlentities2($vcard->description())); ?></p>
<?php			
					}
?>
					</div>
<?php
	}
	protected static function get_widgets($metaverse,array $args)
	{
		if(mv_id_plugin::nice_name($metaverse) === false)
		{
			return;
		}
		static $get_sql;
		if(isset($get_sql) === false)
		{
			$get_sql = 'SELECT cache FROM ' . mv_id_plugin::db_tablename() . ' WHERE metaverse = %s AND cache IS NOT NULL';
		}
		global $wpdb;
		$vcards = $wpdb->get_results($wpdb->prepare($get_sql,$metaverse));
		if(empty($vcards) === false)
		{
			extract($args);
			echo $before_widget,$before_title,htmlentities2(mv_id_plugin::nice_name($metaverse)),$after_title,"\n";
			foreach($vcards as $k=>$vcard)
			{
				if(isset($vcard->cache) === false)
				{
					continue;
				}
				self::output(unserialize($vcard->cache));
			}
			echo $after_widget,"\n";
		}
	}
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
}
class mv_id_plugin
{
	public static function db_tablename()
	{
		global $wpdb;
		return $wpdb->prefix . 'mv_id';
	}
	protected static function install()
	{
		global $wpdb;
		$structure = 'CREATE TABLE IF NOT EXISTS ' . self::db_tablename() . ' (
`metaverse` CHAR( 32) NOT NULL ,
`id` CHAR( 255 ) NOT NULL ,
`last_mod` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ,
`cache` BLOB NULL DEFAULT NULL ,
PRIMARY KEY ( `metaverse` , `id` )
)';
		$wpdb->query($structure);
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
	public static function cron()
	{
		global $wpdb;
		$wpdb->query('UPDATE ' . self::db_tablename() . ' SET cache=NULL WHERE (NOW() - last_mod) >= 3600');
		$uncached = self::get_uncached_mv_ids(true);
		if(isset($uncached) && is_array($uncached) && count($uncached) > 0)
		{
			foreach($uncached as $id)
			{
				$vcard = call_user_func_array(self::$metaverse_classes[$id->metaverse] . '::factory',array($id->id));
				if(isset($vcard) && is_object($vcard))
				{
					self::cache($id->metaverse,$id->id,$vcard);
				}
			}
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
		static $delete_sql;
		if(isset($delete_sql) === false)
		{
			$delete_sql = 'DELETE FROM ' . self::db_tablename() . ' WHERE metaverse = %s AND id = %s';
		}
		$wpdb->query($wpdb->prepare($delete_sql,$metaverse,$id));
	}
	public static function add($metaverse,$id)
	{
		if(self::nice_name($metaverse) !== false && self::is_id_valid($metaverse,$id) === true)
		{
			global $wpdb;
			static $add_sql;
			if(isset($add_sql) === false)
			{
				$add_sql = 
'INSERT INTO ' . self::db_tablename() . ' (metaverse,id) VALUES(%s,%s)
ON DUPLICATE KEY UPDATE
	cache=NULL';
			}
			$wpdb->query($wpdb->prepare($add_sql,$metaverse,$id));
		}
	}
	public static function cache($metaverse,$id,mv_id_vcard $vcard) // do not call before add
	{
		if(self::nice_name($metaverse) !== false && self::is_id_valid($metaverse,$id) === true)
		{
			global $wpdb;
			static $cache_sql;
			if(isset($cache_sql) === false)
			{
				$cache_sql = 'UPDATE ' . self::db_tablename() . ' SET cache = %s WHERE metaverse = %s AND id = %s LIMIT 1';
			}
			return $wpdb->query($wpdb->prepare($cache_sql,serialize($vcard),$metaverse,$id));
		}
		else
		{
			return false;
		}
	}
	public static function get_all_mv_ids($force=false)
	{
		global $wpdb;
		static $get_sql;
		static $mv_ids;
		if(isset($get_sql) === false)
		{
			$get_sql = 'SELECT metaverse,id FROM ' . self::db_tablename();
		}
		if(isset($mv_ids) === false || $force === true)
		{
			$mv_ids = $wpdb->get_results($get_sql);
		}
		return $mv_ids;
	}
	public static function get_all_mv_ids_and_cache($force=false)
	{
		global $wpdb;
		static $get_sql;
		static $mv_ids;
		if(isset($get_sql) === false)
		{
			$get_sql = 'SELECT metaverse,id,cache FROM ' . self::db_tablename();
		}
		if(isset($mv_ids) === false || $force == true)
		{
			$mv_ids = $wpdb->get_results($get_sql);
			foreach($mv_ids as $k=>$v)
			{
				if(empty($v->cache) === false)
				{
					$mv_ids[$k]->cache = unserialize($v->cache);
				}
			}
		}
		return $mv_ids;
	}
	public static function get_cached_mv_ids($force=false)
	{
		global $wpdb;
		static $mv_ids;
		static $get_sql;
		if(isset($get_sql) === false)
		{
			$get_sql = 'SELECT metaverse,id FROM ' . self::db_tablename() . ' WHERE cache IS NOT NULL';
		}
		if(isset($mv_ids) === false || $force === true)
		{
			$mv_ids = $wpdb->get_results($get_sql);
		}
		return $mv_ids;
	}
	public static function get_uncached_mv_ids($force=false)
	{
		global $wpdb;
		static $mv_ids;
		static $get_sql;
		if(isset($get_sql) === false)
		{
			$get_sql = 'SELECT metaverse,id FROM ' . self::db_tablename() . ' WHERE cache IS NULL';
		}
		if(isset($mv_ids) === false || $force === true)
		{
			$mv_ids = $wpdb->get_results($get_sql);
		}
		return $mv_ids;
	}
	protected static $metaverse_classes = array();
	protected static $supported_mvs = array();
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
	public static function admin_actions()
	{
		add_options_page('Metaverse ID','Metaverse ID',1,'mv-id','mv_id_plugin::admin');
	}
	public static function admin()
	{
		if(isset($_POST['delete']))
		{
			foreach($_POST['delete'] as $delete_this)
			{
				list($metaverse,$id) = explode('::',$delete_this);
				self::delete($metaverse,$id);
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
				$vcard = call_user_func_array(self::$metaverse_classes[$metaverse] . '::factory',array($id));
				if($vcard instanceof mv_id_vcard_funcs)
				{
					self::cache($metaverse,$id,$vcard);
				}
			}
		}
		$mv_ids = self::get_all_mv_ids_and_cache();
?>
	<h2>Metaverse ID Admin</h2>
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
				if($vcard instanceof mv_id_vcard_funcs)
				{
					mv_id_vcard::output($vcard);
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
	$mv_ids = array();
	$mv_id_formats = array();
		foreach(self::registered_metaverses() as $mv_id=>$mv_class)
		{
			$mv_ids[] = $mv_id;
			$mv_id_format = call_user_func(self::$metaverse_classes[$mv_id] . '::id_format');
			$mv_id_formats[] = $mv_id_format;
			$mv_id_nice_name = mv_id_plugin::nice_name($mv_id);
			$mv_id_nice_names[] = $mv_id_nice_name;
?>
				<option value="<?php echo $mv_id; ?>" title="<?php echo htmlentities2($mv_id_format); ?>"><?php echo htmlentities2($mv_id_nice_name); ?></option>

<?php
		}
?>
				</select> <input name="add[0][id]" type="text" maxlength="255" /></li>
			</ol>
			<script type="text/javascript">
var mv_id_plugin__id_div = document.getElementById('add-mv-ids');
var mv_id_plugin__num_entries = 1;
var mv_id_plugin__metaverse_ids = ["<?php echo implode('","',$mv_ids);?>"];
var mv_id_plugin__metaverse_id_formats = ["<?php echo implode('","',$mv_id_formats);?>"];
var mv_id_plugin__metaverse_nice_names = ["<?php echo implode('","',$mv_id_nice_names);?>"];
function mv_id_plugin__add_more_button()
{
	var li = document.createElement('li');
	var select = document.createElement('select');
	select.name = 'add[' + mv_id_plugin__num_entries + '][metaverse]';
	for(i in mv_id_plugin__metaverse_ids)
	{
		var option = document.createElement('option');
		option.value = mv_id_plugin__metaverse_ids[i];
		option.title = mv_id_plugin__metaverse_id_formats[i];
		option.appendChild(document.createTextNode(mv_id_plugin__metaverse_nice_names[i]));
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
}
var a = document.createElement('a');
a.href = 'javascript:mv_id_plugin__add_more_button()';
a.appendChild(document.createTextNode('Add More IDs'));
mv_id_plugin__id_div.appendChild(a);
			</script>
		</div>
		<p>
			<input type="submit" name="Submit" value="Update/Delete" />
		</p>
	</form>
<?php
	}
}
class mv_id_plugin_widgets
{
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
}
include_once('metaverses.php');
register_activation_hook(__FILE__,'mv_id_plugin::activate');
register_deactivation_hook(__FILE__,'mv_id_plugin::deactivate');
add_action('mv_id_plugin__regenerate_cache','mv_id_plugin::cron');
add_action('admin_menu','mv_id_plugin::admin_actions');
add_action('plugins_loaded','mv_id_plugin_widgets::register');
?>
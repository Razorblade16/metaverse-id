<?php
/*
Plugin Name: MV ID::World of Warcraft
Plugin URI: http://blog.signpostmarv.name/mv-id/
Description: Display your WoW Identity. Requires <a href="http://blog.signpostmarv.name/mv-id/">Metaverse ID</a>.
Version: 1.0
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
if(class_exists('mv_id_vcard') === false)
{
	return;
}
abstract class mv_id_vcard_wow extends mv_id_vcard
{
	const sprintf_url = '#';
	const sprintf_img = '%s';
	public static function id_format()
	{
		return '\'Realm Username\', e.g. \'Alonsus Axilo\'';
	}
	public static function is_id_valid($id)
	{
		return (bool)preg_match('/^(\w+)\ (\w+)$/',$id);
	}
	protected static function format_image_url($sprintf_url,$genderId,$raceId,$classId,$level)
	{
		$level_blurb = '-default';
		if($level >= 80)
		{
			$level_blurb = '-80';
		}
		else if($level >= 70)
		{
			$level_blurb = '-70';
		}
		else if($level >= 60)
		{
			$level_blurb = '';
		}
		return sprintf($sprintf_url,$level_blurb,$genderId,$raceId,$classId);
	}
	public static function affiliations_label()
	{
		return 'Guild';
	}
	protected static function scrape($url,$last_mod=false)
	{
		$curl_opts = array('user-agent' => 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.1.1) Gecko/20090715 Firefox/3.5.1');
		if($last_mod !== false)
		{
			$curl_opts['headers'] = array(
				'If-Modified-Since'=>$last_mod,
			);
		}
		$curl_opts['headers'][CURLOPT_HTTPHEADER] = array(
			'Accept:text/xml'
		);
		$data = mv_id_plugin::curl(
			$url,
			$curl_opts
		);
		if($data === true)
		{
			return true;
		}
		if((($XML = mv_id_plugin::SimpleXML($data)) instanceof SimpleXMLElement) === false)
		{
			return false;
		}
		else
		{
			unset($data);
			$xpath = mv_id_plugin::XPath($XML,'/page/characterInfo/character');
			if($xpath !== false)
			{
				foreach($xpath[0]->attributes() as $attribute => $value)
				{
					$$attribute = (string)$value;
				}
				if($last_mod !== false && (strtotime($lastModified) <= $last_mod))
				{
					return false;
				}
				$skills = array();
				$skills[] = new mv_id_skill('Achievement Points',(int)$points,'http://www.wowwiki.com/Achievement');
				$description = '"' . $prefix . $name . $suffix . '" is a level ' . $level . ' ' . strtolower($gender) . ' ' . $race . ' ' . $class . ', and can be found on the ' . $realm . ' realm.';
				$url = str_replace('&n=','&amp;n=',$url);
				$guild = null;
				if(isset($guildName) && empty($guildName) === false)
				{
					$guild = new mv_id_vcard_affiliation($guildName,substr($url,0,strpos($url,'/character-sheet.xml')) . '/guild-info.xml?r=' . $realm . '&amp;gn=' . urlencode($guildName));
				}
				$xpath = mv_id_plugin::XPath($XML,'//professions/skill');
				if(empty($xpath) === false)
				{
					foreach($xpath as $skill)
					{
						foreach($skill->attributes() as $attribute => $value)
						{
							$attribute = '_' . $attribute;
							$$attribute = (string)$value;
						}
						$skills[] = new mv_id_skill($_name,(int)$_value,sprintf('http://www.wowwiki.com/%s',$_name));
					}
				}
				return array($name,$description,$genderId,$raceId,$classId,$level,$url,null,$guild,$skills);
			}
			else
			{
				return false;
			}
		}
	}
}
class mv_id_vcard_wow_eu extends mv_id_vcard_wow
{
	public static function register_metaverse()
	{
		mv_id_plugin::register_metaverse('WoW Europe','WoW EU','mv_id_vcard_wow_eu');
	}
	public static function factory($id,$last_mod=false)
	{
		if(self::is_id_valid($id) === false)
		{
			return false;
		}
		else
		{
			list($realm,$name) = explode(' ',$id);
			$data = self::scrape(sprintf('http://eu.wowarmory.com/character-sheet.xml?r=%s&n=%s',$realm,$name),$last_mod);
			if(is_array($data))
			{
				list($name,$description,$genderId,$raceId,$classId,$level,$url) = $data;
				$image = self::format_image_url('http://eu.wowarmory.com/images/portraits/wow%s/%u-%u-%u.gif',$genderId,$raceId,$classId,$level);
				$stats = isset($data[7]) ? $data[7] : null;
				$guild = isset($data[8]) ? array($data[8]) : null;
				$skills = isset($data[9]) && is_array($data[9]) ? $data[9] : null;
				return new self($id,$name,$image,$description,$url,$stats,$guild,$skills);
			}
			else
			{
				return false;
			}
		}
	}
	public static function get_widget(array $args)
	{
		self::get_widgets('WoW EU',$args);
	}
}
class mv_id_vcard_wow_us extends mv_id_vcard_wow
{
	public static function register_metaverse()
	{
		mv_id_plugin::register_metaverse('WoW US','WoW US','mv_id_vcard_wow_us');
	}
	public static function factory($id)
	{
		if(self::is_id_valid($id) === false)
		{
			return false;
		}
		else
		{
			list($realm,$name) = explode(' ',$id);
			$data = self::scrape(sprintf('http://www.wowarmory.com/character-sheet.xml?r=%s&n=%s',$realm,$name));
			if(is_array($data))
			{
				list($name,$description,$genderId,$raceId,$classId,$level,$url) = $data;
				$image = self::format_image_url('http://www.wowarmory.com/images/portraits/wow%s/%u-%u-%u.gif',$genderId,$raceId,$classId,$level);
				$stats = isset($data[7]) ? $data[7] : null;
				$guild = isset($data[8]) ? array($data[8]) : null;
				$skills = isset($data[9]) && is_array($data[9]) ? $data[9] : null;
				return new self($id,$name,$image,$description,$url,$stats,$guild,$skills);
			}
			else
			{
				return false;
			}
		}
	}
	public static function get_widget(array $args)
	{
		self::get_widgets('WoW US',$args);
	}
}
add_action('mv_id_plugin__register_metaverses','mv_id_vcard_wow_eu::register_metaverse');
add_action('mv_id_plugin__register_metaverses','mv_id_vcard_wow_us::register_metaverse');
?>
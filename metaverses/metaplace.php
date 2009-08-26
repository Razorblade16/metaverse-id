<?php
/*
Plugin Name: MV ID::Metaplace
Plugin URI: http://signpostmarv.name/mv-id/
Description: Display your Metaplace Identity. Requires <a href="http://signpostmarv.name/mv-id/">Metaverse ID</a>.
Version: 1.0
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
if(class_exists('mv_id_vcard') === false)
{
	return;
}
class mv_id_vcard_metaplace extends mv_id_vcard
{
	const sprintf_url = 'http://beta.metaplace.com/user/%1$s';
	const sprintf_img = '%s';
	public static function register_metaverse()
	{
		mv_id_plugin::register_metaverse('Metaplace','Metaplace','mv_id_vcard_metaplace');
	}
	public static function id_format()
	{
		return 'Username';
	}
	public static function is_id_valid($id)
	{
		return (bool)preg_match('/^([\w\d]+)$/',$id);
	}
	public static function factory($id,$last_mod=false)
	{
		if(self::is_id_valid($id) !== false)
		{
			$url = sprintf('https://api.metaplace.com/api/v0.2/public/user/profile/%s',$id);
			$curl_opts = array();
			if($last_mod !== false)
			{
				$curl_opts['headers'] = array(
					'If-Modified-Since'=>$last_mod,
				);
			}
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
				$foo = $XML->xpath('/userlist/user');
				$attributes = array();
				foreach($foo[0]->attributes() as $attribute => $value)
				{
					$$attribute = $value;
				}
				$name = (string)$username;
				$image = (string)$icon_thumb;
				$start = strrpos($image,'/') + 1;
				$end = strrpos($image,'.');
				$file = substr($image,$start,$end - $start);
				$image = substr_replace($image,rawurlencode($file),$start,$end - $start);
				$xp = array();
				$xp['socializer'] = (int)$social_xp;
				$xp['explorer'] = (int)$play_xp;
				$xp['builder'] = (int)$build_xp;
				$skills = array(
					new mv_id_skill('Socializer',(int)$social_xp),
					new mv_id_skill('Explorer',(int)$play_xp),
					new mv_id_skill('Builder',(int)$build_xp),
				);
				$stats = new mv_id_stats(array(
					new mv_id_stat('bday',(string)$registerDate),
				));
				arsort($xp);
				$xp = key($xp);
				$description = (string)$title . ' ' . $name . ' is a level ' . (string)$level . ' ' . $xp . '.';
				return new self($id,$name,$image,$description,null,$stats,null,$skills);
			}
		}
		else
		{
			return false;
		}
	}
	public static function get_widget(array $args)
	{
		self::get_widgets('Metaplace',$args);
	}
}
add_action('mv_id_plugin__register_metaverses','mv_id_vcard_metaplace::register_metaverse');
?>
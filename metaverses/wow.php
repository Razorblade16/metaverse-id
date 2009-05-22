<?php
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
		return sprintf($sprintf_url,(($level == 80) ? '80' : 'default'),$genderId,$raceId,$classId);
	}
	protected static function scrape($url)
	{
		$ch = curl_init($url);
		curl_setopt_array($ch,array(
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_USERAGENT      => 'This Is Not Firefox/3.0.10',
		));
		$data = curl_exec($ch);
		curl_close($ch);
		if((($XML = mv_id_vcard::SimpleXML($data)) instanceof SimpleXMLElement) === false)
		{
			return false;
		}
		else
		{
			unset($data);
			$xpath = mv_id_vcard::XPath($XML,'/page/characterInfo/character');
			if($xpath !== false)
			{
				foreach($xpath[0]->attributes() as $attribute => $value)
				{
					$$attribute = $value;
				}
				$description = '"' . (string)$prefix . (string)$name . (string)$suffix . '" is a level ' . (string)$level . ' ' . strtolower((string)$gender) . ' ' . (string)$race . ' ' . (string)$class . ', and can be found on the ' . (string)$realm . ' realm.';
				$url = str_replace('&n=','&amp;n=',$url);
				return array((string)$name,(string)$description,(string)$genderId,(string)$raceId,(string)$classId,(string)$level,$url);
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
	public static function factory($id)
	{
		if(self::is_id_valid($id) === false)
		{
			return false;
		}
		else
		{
			list($realm,$name) = explode(' ',$id);
			$data = self::scrape(sprintf('http://eu.wowarmory.com/character-sheet.xml?r=%s&n=%s',$realm,$name));
			if(is_array($data))
			{
				list($name,$description,$genderId,$raceId,$classId,$level,$url) = $data;
				$image = self::format_image_url('http://eu.wowarmory.com/images/portraits/wow-%s/%u-%u-%u.gif',$genderId,$raceId,$classId,$level);
				return new self($id,$name,$image,$description,$url);
			}
			else
			{
				return false;
			}
		}
	}
	public static function widget(array $args)
	{
		self::get_widgets('WoW EU',$args);
	}
}
class mv_id_vcard_wow_us extends mv_id_vcard_wow
{
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
				$image = self::format_image_url('http://www.wowarmory.com/images/portraits/wow-%s/%u-%u-%u.gif',$genderId,$raceId,$classId,$level);
				return new self($id,$name,$image,$description);
			}
			else
			{
				return false;
			}
		}
	}
	public static function widget(array $args)
	{
		self::get_widgets('WoW US',$args);
	}
}
mv_id_plugin::register_metaverse('WoW Europe','WoW EU','mv_id_vcard_wow_eu');
mv_id_plugin::register_metaverse('WoW US','WoW US','mv_id_vcard_wow_us');
?>
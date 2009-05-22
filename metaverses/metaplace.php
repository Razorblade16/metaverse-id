<?php
class mv_id_vcard_metaplace extends mv_id_vcard
{
	const sprintf_url = 'http://beta.metaplace.com/user/%1$s';
	const sprintf_img = '%s';
	public static function id_format()
	{
		return 'Username';
	}
	public static function is_id_valid($id)
	{
		return (bool)preg_match('/^([\w\d]+)$/',$id);
	}
	public static function factory($id)
	{
		if(self::is_id_valid($id) !== false)
		{
			$ch = curl_init(sprintf('https://api.metaplace.com/api/v0.2/public/user/profile/%s',$id));
			curl_setopt_array($ch,array(
				CURLOPT_HEADER => false,
				CURLOPT_SSL_VERIFYPEER=>false,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_RETURNTRANSFER => true,
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
				$foo = $XML->xpath('/userlist/user');
				$attributes = array();
				foreach($foo[0]->attributes() as $attribute => $value)
				{
					$attributes[$attribute] = $value;
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
				arsort($xp);
				$xp = key($xp);
				$description = (string)$title . ' ' . $name . ' is a level ' . (string)$level . ' ' . $xp . '.';
				return new self($id,$name,$image,$description);
			}
		}
		else
		{
			return false;
		}
	}
	public static function widget(array $args)
	{
		self::get_widgets('Metaplace',$args);
	}
}
mv_id_plugin::register_metaverse('Metaplace','Metaplace','mv_id_vcard_metaplace');
?>
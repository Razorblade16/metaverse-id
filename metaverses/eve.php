<?php
class mv_id_vcard_eve extends mv_id_vcard implements mv_id_needs_admin
{
	const sprintf_url         = 'http://www.eveonline.com/character/skilltree.asp?characterID=%u';
	const sprintf_img         = '#';
	const sprintf_description = '%1$s is a %2$s %3$s %4$s.';
	public static function id_format()
	{
		return 'character ID';
	}
	public static function is_id_valid($id)
	{
		return (bool)preg_match('/^(\d+)$/',$id);
	}
	public static function affiliations_label()
	{
		return 'Corporation';
	}
	public static function widget(array $args)
	{
		self::get_widgets('EVE',$args);
	}
	public static function admin_fields()
	{
		static $fields = array(
			'userID'=>array(
				'name'  => 'User ID',
				'regex' => '/^(\d+)$/',
			),
			'apiKey'=>array(
				'name'  => 'API Key',
				'regex' => '/^([\w\d]+)$/',
			),
		);
		return $fields;
	}
	public static function factory($id)
	{
		if(self::is_id_valid($id) === false || ($config = get_option('mv-id::EVE')) === false)
		{
			return false;
		}
		else
		{
			$data_url = 'http://api.eve-online.com/char/CharacterSheet.xml.aspx';
			$url = sprintf('http://www.eveonline.com/character/skilltree.asp?characterID=%u',$id);
			$config = unserialize($config);
			$config['characterID'] = $id;
			$ch = curl_init($data_url);
			curl_setopt_array($ch,array(
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_POST           => true,
				CURLOPT_POSTFIELDS     => $config,
			));
			$data = curl_exec($ch);
			curl_close($ch);
			if((($XML = mv_id_plugin::SimpleXML($data)) instanceof SimpleXMLElement) === false)
			{
				return false;
			}
			else
			{
				$info['name']            = mv_id_plugin::XPath($XML,'//result/name');
				$info['gender']          = mv_id_plugin::XPath($XML,'//result/gender');
				$info['race']            = mv_id_plugin::XPath($XML,'//result/race');
				$info['bloodLine']       = mv_id_plugin::XPath($XML,'//result/bloodLine');
				foreach($info as $k=>$v)
				{
					if($v)
					{
						$info[$k] = (string)$v[0];
					}
					else
					{
						return false;
					}
				}
				$corp['name'] = mv_id_plugin::XPath($XML,'//result/corporationName');
				$corp['uid']  = mv_id_plugin::XPath($XML,'//result/corporationID');
				foreach($corp as $k=>$v)
				{
					if($v)
					{
						$corp[$k] = (string)$v[0];
					}
					else
					{
						$corp = null;
						break;
					}
				}
				if($corp)
				{
					$corp = new mv_id_vcard_affiliation($corp['name'],false,null,null,$corp['uid']);
				}
				$description = sprintf(self::sprintf_description,$info['name'],strtolower($info['gender']),$info['race'],$info['bloodLine']);
				return new self($id,$info['name'],null,$description,null,null,$corp ? array($corp) : $corp);
			}
		}
	}
}
mv_id_plugin::register_metaverse('EVE Online','EVE','mv_id_vcard_eve');
?>
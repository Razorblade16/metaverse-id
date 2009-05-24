<?php
class mv_id_vcard_lotro extends mv_id_vcard
{
	const sprintf_url = '#';
	const sprintf_img = '%s';
	const sprintf_description = '\'%1$s of %2$s\' is a level %3$u %6$s %4$s who hails from %5$s.';
	const sprintf_kinship_url = 'http://my.lotro.com/kinship-%s-%s/';
	public static function id_format()
	{
		return '\'Username of Realm\', e.g. \'Foo of Bar\'';
	}
	public static function is_id_valid($id)
	{
		return (bool)preg_match('/^(\w+)\ (of|\-) (\w+)$/',$id);
	}
	protected static function format_image_url($sprintf_url,$genderId,$raceId,$classId,$level)
	{
		return sprintf($sprintf_url,(($level == 80) ? '80' : 'default'),$genderId,$raceId,$classId);
	}
	public static function affiliations_label()
	{
		return 'Kinship';
	}
	public static function factory($id)
	{
		if(self::is_id_valid($id) === false)
		{
			return false;
		}
		else
		{
			list($name,$realm) = explode(' of ',$id);
			$url = sprintf('http://my.lotro.com/character/%s/%s/',strtolower($realm),strtolower($name));
			$ch = curl_init($url);
			curl_setopt_array($ch,array(
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_RETURNTRANSFER => true,
			));
			$data = curl_exec($ch);
			curl_close($ch);
			$doc = mv_id_plugin::DOMDocument($data);
			if($doc instanceof DOMDocument)
			{
				unset($data);
				$xpath = mv_id_plugin::XPath($doc,'./head/title');
				if($xpath instanceof DOMNodeList)
				{
					list($name,$realm) = explode(' - ',trim($xpath->item(0)->nodeValue));
				}
				else
				{
					return false;
				}
				$xpath = mv_id_plugin::XPath($doc,'//td[@id="pprofile_avatar"]/div[@class="avatar"]/img[@class="avatar"]');
				if($xpath instanceof DOMNodeList)
				{
					$image = $xpath->item(0)->getAttribute('src');
				}
				else
				{
					return false;
				}
				$xpath = mv_id_plugin::XPath($doc,'//div[@id="char_race"]');
				if($xpath instanceof DOMNodeList)
				{
					$race = $xpath->item(0)->nodeValue;
					if($race === 'Race of Man')
					{
						$race = 'Human';
					}
				}
				else
				{
					return false;
				}
				$xpath = mv_id_plugin::XPath($doc,'//div[@id="char_nat"]');
				if($xpath instanceof DOMNodeList)
				{
					$nat = $xpath->item(0)->nodeValue;
				}
				else
				{
					return false;
				}
				$xpath = mv_id_plugin::XPath($doc,'//div[@id="char_class"]');
				if($xpath instanceof DOMNodeList)
				{
					$class = $xpath->item(0)->nodeValue;
				}
				else
				{
					return false;
				}
				$xpath = mv_id_plugin::XPath($doc,'//div[@id="char_level"]');
				if($xpath instanceof DOMNodeList)
				{
					$level = $xpath->item(0)->nodeValue;
				}
				else
				{
					return false;
				}
				$xpath = mv_id_plugin::XPath($doc,'//a[starts-with(@href,"http://my.lotro.com/kinship-elendilmir")]',true);
				if($xpath instanceof DOMNodeList)
				{
					if($xpath->length !== 1)
					{
						$kinship = false;
					}
					else
					{
						$kinship = $xpath->item(0)->nodeValue;
					}
				}
				else
				{
					return false;
				}
				if($kinship !== false)
				{
					$kinship = new mv_id_vcard_affiliation($kinship,sprintf(self::sprintf_kinship_url,$realm,str_replace(' ','_',strtolower($kinship))));
				}
				$description = sprintf(self::sprintf_description,$name,$realm,$level,$class,$nat,$race);
				return new self(sprintf('%s_of_%s',$name,$realm),$name,$image,$description,$url,null,$kinship ? array($kinship) : null);
			}
			else
			{
				return false;
			}
		}
	}
	public static function widget(array $args)
	{
		self::get_widgets('lotro',$args);
	}
}
mv_id_plugin::register_metaverse('Lord of the Rings Online','lotro','mv_id_vcard_lotro');
?>
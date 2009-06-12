<?php
class mv_id_vcard_pq extends mv_id_vcard
{
	const sprintf_url = '#';
	const sprintf_img = '%s';
	const sprintf_description = '\'%1$s of %2$s\' is a level %3$u %4$s "%5$s", who has progressed to %6$s. Their speciality is "%7$s".';
	const sprintf_guild_url = 'http://progressquest.com/guilds.php?id=%1$u#%1$u';
	public static function id_format()
	{
		return '\'Username of Realm\', e.g. \'Foo of Bar\'';
	}
	public static function is_id_valid($id)
	{
		return (bool)preg_match('/^([\w\d\_\ ]+)\ (of|\-) (\w+)$/',$id);
	}
	public static function affiliations_label()
	{
		return 'Guild';
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
			$url = sprintf('http://progressquest.com/%s.php?name=%s',strtolower($realm),strtolower($name));
			$ch = curl_init($url);
			curl_setopt_array($ch,array(
				CURLOPT_RETURNTRANSFER => true,
			));
			$data = curl_exec($ch);
			curl_close($ch);
			$doc = mv_id_plugin::DOMDocument($data);
			if($doc instanceof DOMDocument)
			{
				unset($data);
				$xpath = mv_id_plugin::XPath($doc,'//td[@class="sel"]');
				if($xpath instanceof DOMNodeList)
				{
					$rawData = array();
					for($x=0;$x<$xpath->length;++$x)
					{
						$rawData[$x] = $xpath->item($x)->nodeValue;
					}
					list($rank,$name,$race,$class,$level,$primeStat,$plotStage,$prizedItem,$speciality) = $rawData;
					list($primeStat,$primeStatValue) = explode(' ',$primeStat);
					$primeStatValue = (int)$primeStatValue;
					switch($primeStat)
					{
						case 'STR':
							$primeStat = 'Strength';
						break;
						case 'CON':
							$primeStat = 'Constitution';
						break;
						case 'DEX':
							$primeStat = 'Dexterity';
						break;
						case 'INT':
							$primeStat = 'Intelligence';
						break;
						case 'WIS':
							$primeStat = 'Wisdom';
						break;
						case 'CHA':
							$primeStat = 'Charisma';
						break;
					}
					$skills = array(new mv_id_skill($primeStat,(int)$primeStatValue));
					$description = sprintf(self::sprintf_description,$name,$realm,$level,$race,$class,$plotStage,$speciality);
					if($xpath->item(8)->nextSibling->attributes->getNamedItem('class')->nodeValue === 'sel' && $xpath->item(8)->nextSibling->nodeValue !== '')
					{
						$description .= "\n\n" . '"' . $xpath->item(8)->nextSibling->nodeValue . '"';
						if($xpath->item(8)->nextSibling->nextSibling->localName === 'td' && $xpath->item(8)->nextSibling->nextSibling->firstChild->localName === 'a')
						{
							$guildName = $xpath->item(8)->nextSibling->nextSibling->firstChild->nodeValue;
							$guildID = $xpath->item(8)->nextSibling->nextSibling->firstChild->attributes->getNamedItem('href')->nodeValue;
							$guildID = substr($guildID,strrpos($guildID,'#') + 1);
							$guild = new mv_id_vcard_affiliation($guildName,sprintf(self::sprintf_guild_url,$guildID));
						}
					}
					else if($xpath->item(8)->nextSibling->nextSibling->localName === 'td' && $xpath->item(8)->nextSibling->nextSibling->firstChild->localName === 'a')
					{
						$guildName = $xpath->item(8)->nextSibling->nextSibling->firstChild->nodeValue;
						$guildID = $xpath->item(8)->nextSibling->nextSibling->firstChild->attributes->getNamedItem('href')->nodeValue;
						$guildID = substr($guildID,strrpos($guildID,'#') + 1);
						$guild = new mv_id_vcard_affiliation($guildName,sprintf(self::sprintf_guild_url,$guildID));
					}
				}
				else
				{
					return false;
				}
				return new self(sprintf('%s_of_%s',$name,$realm),$name,null,$description,$url,null,$guild ? array($guild) : null,$skills);
			}
			else
			{
				return false;
			}
		}
	}
	public static function widget(array $args)
	{
		self::get_widgets('pq',$args);
	}
}
mv_id_plugin::register_metaverse('Progress Quest','pq','mv_id_vcard_pq');
?>
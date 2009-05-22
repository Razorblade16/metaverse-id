<?php
class mv_id_vcard_freerealms extends mv_id_vcard
{
	const sprintf_url = 'http://www.freerealms.com/character/profile.action#character/%1$s';
	const sprintf_img = 'http://www.freerealms.com/uploads/%1$s';
	const sprintf_scrape = 'http://www.freerealms.com/character/profile!json.action?characterId=%1$s';
	public static function is_id_valid($id)
	{
		return is_integer($id) ? true : ctype_digit($id);
	}
	public static function id_format()
	{
		return 'Copy & paste the number from the end of your profile URL.';
	}
	public static function factory($id)
	{
		$cookie_jar = tempnam(sys_get_temp_dir(),'mv-id');
		$ch = curl_init(sprintf(self::sprintf_scrape,$id));
		curl_setopt_array($ch,array(
			CURLOPT_SSL_VERIFYPEER=>false,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_COOKIEFILE => $cookie_jar,
			CURLOPT_COOKIEJAR => $cookie_jar,
		));
		$data = curl_exec($ch);
		curl_close($ch);
		$data = json_decode($data);
		if(isset($data->characterList))
		{
			foreach($data->characterList as $character)
			{
				$uid = $character->charId;
				$name = $character->name;
				$img = $character->headshotUrl;
				$description = $character->name . ' is a ' . strtolower($character->player->gender) . ' ' . $character->player->race . ' with ' . $character->player->level . ' levels, ' . $character->player->numQuests . ' quests completed and ' . $character->player->numCollections . '  collections started.';
				break;
			}
			return new self($uid,$name,$img,$description);
		}
		else
		{
			return null;
		}
	}
	public static function widget(array $args)
	{
		self::get_widgets('freerealms',$args);
	}
}
mv_id_plugin::register_metaverse('Free Realms','freerealms','mv_id_vcard_freerealms');
?>
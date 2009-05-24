<?php
class mv_id_vcard_agni_sl extends mv_id_vcard
{
	const regex_sl_id = '/^([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})$/S';
	const sprintf_url = 'http://world.secondlife.com/resident/%1$s';
	const sprintf_img = 'http://secondlife.com/app/image/%1$s/3';
	const sprintf_scrape = 'http://world.secondlife.com/resident/%1$s';
	const string_error_Resident_not_exist   = '<Error><Code>NoSuchKey</Code><Message>The specified key does not exist.</Message>';
	const string_error_aws_internal         = '<Error><Code>InternalError</Code><Message>We encountered an internal error. Please try again.</Message>';
	const string_error_service_unavailable  = '<html><body><b>Http/1.1 Service Unavailable</b></body> </html>';
	const string_cond_web_profile_blocked   = 'This resident has chosen to hide their profile from search';
	const regex_name              = '/<title>([\w\d\]{2,31}\ [\w\d]{2,50})<\/title>/S';
	const regex_get_avatar        = '/<img\ alt="profile\ image"\ src="http:\/\/secondlife\.com\/app\/image\/([\w\d\-]{36})\/1"\ class="parcelimg"\ \/>/S';
	const string_no_avatar                  = '<img alt="profile image" src="http://world.secondlife.com/images/blank.jpg" class="parcelimg" />';
	const regex_get_description   = '/<p\ class="desc">(.*)<\/p>/S';
	const regex_get_rezday        = '/Born\ on\:<\/span>\ ([\d]{4}\-[\d]{2}\-[\d]{2})/S';
	public static function is_id_valid($id)
	{
		return (bool)preg_match(self::regex_sl_id,$id);
	}
	public static function id_format()
	{
		return 'Your avatar UUID.';
	}
	protected static function scrape($url)
	{
		$ch = curl_init($url);
		curl_setopt_array($ch,array(
			CURLOPT_RETURNTRANSFER => true,
		));
		$data = curl_exec($ch);
		curl_close($ch);
		$data = html_entity_decode($data,ENT_QUOTES,'UTF-8');
		static $meta_start = '<meta name="description" content="';
		static $meta_end = '<meta name="mat"';
		if(($start = strpos($data,$meta_start)) !== false)
		{
			$start += strlen($meta_start);
			$end = strpos($data,$meta_end,$start);
			$end = strrpos(substr($data,0,$end),'"');
			$replace = htmlentities(substr($data,$start,$end - $start),ENT_QUOTES,'UTF-8');
			$data = substr_replace($data,$replace,$start,$end - $start);
		}
		if(strpos($data,self::string_error_Resident_not_exist) !== false)
		{
			return null;
		}
		else if(strpos($data,self::string_error_aws_internal) !== false)
		{
			return null;
		}
		else if(strpos($data,self::string_error_service_unavailable) !== false)
		{
			return null;
		}
		else if(strpos($data,self::string_cond_web_profile_blocked) !== false)
		{
			return null;
		}
		else
		{
			if(preg_match(self::regex_name,$data,$matches) === 1)
			{
				$name = $matches[1];
			}
			else
			{
				return null;
			}
			$stats = array();
			if(preg_match(self::regex_get_rezday,$data,$matches) === 1)
			{
				$stats[] = new mv_id_stat('bday',$matches[1]);
			}
			$image = null;
			if(strpos($data,self::string_no_avatar) !== false)
			{
				$image = '00000000-0000-0000-0000-000000000000';
			}
			else if(preg_match(self::regex_get_avatar,$data,$matches) === 1)
			{
				$image = $matches[1];
			}
			$description = null;
			if(preg_match(self::regex_get_description,$data,$matches) === 1)
			{
				$doc = mv_id_plugin::DOMDocument($data);
				if($doc instanceof DOMDocument)
				{
					$xpath = mv_id_plugin::XPath($doc,'*//meta[@name="description"]');
					if($xpath instanceof DOMNodeList)
					{
						$description = $xpath->item(0)->getAttribute('content');
						$description = html_entity_decode($description,ENT_QUOTES,'UTF-8');
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
			return array($name,$image,$description,$url,$stats);
		}
	}
	public static function factory($id)
	{
		$data = self::scrape(sprintf(self::sprintf_scrape,$id));
		if(isset($data))
		{
			$stats = (isset($data[4]) && empty($data[4]) === false) ? new mv_id_stats($data[4]) : null;
			return new self($id,$data[0],$data[1],$data[2],$data[3],$stats);
		}
		else
		{
			return $data;
		}
	}
	public static function widget(array $args)
	{
		self::get_widgets('agni SL',$args);
	}
}
class mv_id_vcard_teen_sl extends mv_id_vcard_agni_sl
{
	const sprintf_url = 'http://teen.world.secondlife.com/resident/%1$s';
	const sprintf_scrape = 'http://teen.world.secondlife.com/resident/%1$s';
	public static function factory($id)
	{
		$data = self::scrape(sprintf(self::sprintf_scrape,$id));
		if(isset($data))
		{
			$stats = (isset($data[4]) && empty($data[4]) === false) ? new mv_id_stats($data[4]) : null;
			return new self($id,$data[0],$data[1],$data[2],$data[3],$stats);
		}
		else
		{
			return $data;
		}
	}
	public static function widget(array $args)
	{
		self::get_widgets('teen SL',$args);
	}
}
mv_id_plugin::register_metaverse('Second Life','agni SL','mv_id_vcard_agni_sl');
mv_id_plugin::register_metaverse('Teen SL','teen SL','mv_id_vcard_teen_sl');
?>
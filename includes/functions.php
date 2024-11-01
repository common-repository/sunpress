<?php

	/**
	 * Helper function to output days of the current month as select options
	 *
	 * @param int $daydep Day to select as default in list
	 * @return string HTML Option list
	 */
	function dayOptions($daydep)
	{
		$monthdays = array(31,28,31,30,31,30,31,31,30,31,30,31);
		$monthdep = date("m");
		for($i=1;$i<=$monthdays[$monthdep-1];$i++)
		{
			if($i<10)
				$pad = "0".$i;
			else
				$pad = $i;
	
			$out.= "<option value=\"$pad\" ".(($pad==$daydep)?"selected=\"selected\"":"").">$i</option>";
		}
		return $out;
	}
	
	/**
	 * Helper function to output months of a year as select options
	 *
	 * @param int $month Month to select as default in list
	 * @return string HTML option list
	 */
	function monthOptions($month)
	{
		$months = array('Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec');
		$yeardepadd=0;
		$yeardisplay=0;
		$curdate = time();
		$out='';
		for($i=date("m",$curdate);$i<=18+date("m",$curdate);$i++)
		{
			$j = $i%12;
			if($j==0)
			{
				$j=12;
				$pad = $j;
			}
			else if($j<10)
				$pad = "0".$j;
			else
				$pad = $j;
	
			$yeardepadd=0;
			if($i>12)
				$yeardepadd=floor(($i-1)/12);
	
			$yeardisplay = date("Y",$curdate) + $yeardepadd;
			$out.="<option ".(("$pad-$yeardisplay"==$month)?"selected=\"selected\"":"")." value=\"".$pad."-".$yeardisplay ."\">" . $months[$j-1] . " ".$yeardisplay . "</option>";
		}
		return $out;
	}
	
	/**
	 * Helper function to output common holiday durations as select options
	 *
	 * @param int $dur Duration to select as default in list
	 * @return string HTML option list
	 */
	function durationOptions($dur)
	{
		$out = "<option value=\"7\">7</option><option value=\"10\">10</option><option value=\"14\">14</option><option value=\"21\">21</option><option value=\"28\">28</option><option value=\"30\">30</option><option value=\"40\">40</option><option>--</option>";
		for($i=1;$i<=30;$i++)
		{
			$out .= "<option value=\"".$i."\" ".(($i==$dur)?"selected=\"selected\"":"").">".$i."</option>";
		}
		return $out;
	
	}
	
	/**
	 * Helper function to output countries in the DB as select options
	 *
	 * @param int $cid Country ID to select as default in list
	 * @return string HTML option list
	 */
	function countryOptions($cid)
	{
		global $wpdb;
		$out='';
		$result = $wpdb->get_results("SELECT cid,name FROM wpss_country ORDER BY name ASC");
		foreach($result as $row)
		{
			$out .= "<option ".($row->cid==$cid?"selected=\"selected\"":"")." value=\"".$row->cid."\">".$row->name."</option>";
		}
		return $out;
	}
	
	/**
	 * Helper function to output departing airports in the db select options
	 *
	 * @param int $airp Airport to select as default in list
	 * @return string HTML option list
	 */
	function depAirportOptions($airp) 
	{
		global $wpdb;
		$out='';
		$result = $wpdb->get_results("SELECT code,name FROM wpss_airports WHERE arrival=0 ORDER BY name ASC");
		foreach($result as $row)
		{
			$out .= "<option ".($row->code==$airp?"selected=\"selected\"":"")." value=\"".$row->code."\">".$row->name."</option>";
		}
		
		return $out;
	}
	
	/**
	 * Helper function to output a hotel rating graphically in the form of stars, if the user
	 * supplies their own star image, this will be used in place of the default.
	 *
	 * @param int $rating Rating to output
	 * @return string HTML img code
	 */
	function starRating($rating)
	{
		$out='';
		$starurl = $_SERVER["DOCUMENT_ROOT"].str_replace(get_option('siteurl'),'',get_bloginfo('template_directory'))."/images/star.png";
	
		if(file_exists($starurl)){
			$starurl = get_bloginfo('template_directory')."/images/star.png";
		}else{
			$starurl = get_option('siteurl')."/wp-content/plugins/sunpress/images/star.png";
		}	

		for($i=1;$i<$rating;$i++)
		{
			$out .= "<img src=\"$starurl\" />";
		}
		return $out;
	}
	
	/**
	 * Simply changes any URL supplied to an affiliatefuture redirect URL
	 *
	 * @param string $link sunshine URL to be redirected to from AffiliateFuture
	 * @return string A redirection URL via AffiliateFuture.
	 */
	function wpss_affiliate_link($link)
	{
		global $wp_sunshine;
		
		$affnet = get_option('wpss_affiliate_net');
		
		if(!empty($wp_sunshine->affiliate_id))
		{
			switch($affnet)
			{
				case "por":
					$link = str_replace("http://www.sunshine.co.uk/","",$link);
					return "http://www.paidonresults.net/c/".$wp_sunshine->affiliate_id."/1/503/0/".$link;
					
				default:
					return "http://scripts.affiliatefuture.com/AFClick.asp?affiliateID=".$wp_sunshine->affiliate_id."&merchantID=2980&programmeID=7749&mediaID=0&tracking=&url=".urlencode($link);
			}
		}			

		// if we get here, no aff network has been found, or affid specified
		return $link;
	}
	
	/**
	 * Simply changes any URL supplied to an masked affiliate redirect URL
	 *
	 * @param string $link sunshine URL to be redirected to
	 * @return string A masked redirection URL.
	 */
	function wpss_masked_link($link)
	{
		global $wp_sunshine;
		
		return get_option('home') . "?wpss_redir=".urlencode($link);
	}
	
	function copy_file($url,$dirname)
	{
	    @$file = fopen($url, "rb");
	    if($file)
	    {
	        $filename = basename($url);
	        $fc = fopen($dirname."$filename", "wb");
	        while (!feof ($file)) {
	           $line = fread ($file, 1028);
	           fwrite($fc,$line);
	        }
	        fclose($fc);
	        return true;
	    }
	    else 
	    	echo "$url could not be opened";
	    	
	    return false;
	} 
	
	
		
	/**
	 * Helper function that returns the sunshine.co.uk accom id from a picture url
	 *
	 * @param string $pic Url of picture
	 * @return int Accommodation ID
	 */
	function get_aid_from_picture($pic)
	{
		ereg("([0-9]*)-[0-9]{1,2}.jpg",$pic,$reg);
		return $reg[1];
	}
	
	/**
	 * Helper function that returns the wordpress post id for a given sunshine.co.uk accom id
	 *
	 * @param int Accommodation ID
	 * @return int Wordpress post ID
	 */
	function get_post_id_from_aid($aid)
	{
		global $wpdb;
		
		if(is_numeric($aid))
			return $wpdb->get_var("SELECT pid FROM wpss_post_content_map WHERE maptype=4 and mapid='{$aid}' LIMIT 1;");
		else 
			return -1;
	}
	
	/**
	 * Helper function that returns the wordpress term id for a given sunshine.co.uk resort id
	 *
	 * @param int Resort ID
	 * @return int Wordpress term ID
	 */
	function get_term_id_from_rid($rid)
	{
		global $wpdb;
		
		if(is_numeric($rid))
			return $wpdb->get_var("SELECT pid FROM wpss_post_content_map WHERE maptype=3 and mapid='{$rid}' LIMIT 1;");
		else 
			return -1;
	}
	
	/**
	 * Returns the name of a tag (resort)stored in wordpress given its ID.
	 *
	 * @param int $termid
	 * @return string Name if a term is found, empty otherwise
	 */
	function get_tag_name($termid)
	{
		global $wpdb;
		
		if(is_numeric($termid))
			return $wpdb->get_var("SELECT name FROM $wpdb->terms WHERE term_id='$termid'");
		else 
			return "";
	}
	
	/**
	 * Returns the post count of a given category/region/resort
	 *
	 * @param int $term The category/region/resort to be checked for posts
	 */
	function get_post_count($term)
	{
		global $wpdb;
		
		return $wpdb->get_var("SELECT `count` FROM wp_term_taxonomy w where term_id='".$wpdb->escape($term)."' and taxonomy='post_tag';");
	}
	
	/**
	 * Returns whether a post has been published
	 *
	 * @param int $term The category/region/resort to be checked for posts
	 */
	function get_post_live($postid)
	{
		global $wpdb;
		
		return $wpdb->get_var("SELECT COUNT(ID) FROM $wpdb->posts WHERE ID='".$wpdb->escape($postid)."' and post_status='publish';");
	}
	
	/**
	 * Returns whether a country is live or not, by examining the resorts below it, and whether any of them are live
	 *
	 * @param unknown_type $catid
	 */
	function get_country_live($cid)
	{
		global $wpdb;
		
		$resorts = $wpdb->get_results("SELECT wpcm.pid as termid FROM wpss_country_region_resort wcrr INNER JOIN wpss_resort wr ON wcrr.rid=wr.rid INNER JOIN wpss_post_content_map wpcm ON wpcm.maptype=3 and wpcm.mapid=wr.rid WHERE wcrr.cid='".$cid."';");
		foreach($resorts as $res)
		{
			if(get_post_count($res->termid)>0)
				return true;
		}
		return false;
	}
	
	/**
	 * Returns whether a country is live or not, by examining the resorts below it, and whether any of them are live
	 *
	 * @param unknown_type $catid
	 */
	function get_region_live($regid)
	{
		global $wpdb;
		
		$resorts = $wpdb->get_results("SELECT wpcm.pid as termid FROM wpss_country_region_resort wcrr INNER JOIN wpss_resort wr ON wcrr.rid=wr.rid INNER JOIN wpss_post_content_map wpcm ON wpcm.maptype=3 and wpcm.mapid=wr.rid WHERE wcrr.regid='".$regid."';");
		foreach($resorts as $res)
		{
			if(get_post_count($res->termid)>0)
				return true;
		}
		return false;
	}

?>
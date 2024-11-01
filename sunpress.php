<?php
/*
Plugin Name: sunPress
Version: 0.55
Plugin URI: http://www.sunshine.co.uk/affiliates/wordpress
Description: sunPress adds the ability to search sunshine.co.uk for hotels, flights and holidays from your blog. Please review the options screen for customisation of the plugin.
Author: sunshine.co.uk
Author URI: http://www.sunshine.co.uk

sunPress Plugin for Wordpress 2.6+
Copyright (C) 2010 sunshine.co.uk Ltd
Version 0.55  $Rev: 1 $ $Date: 2012-02-15 14:34:53 -0800 $

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License as
published by the Free Software Foundation; either version 2 of the
License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but
WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307
USA
 */
require_once("includes/functions.php");

class WP_Sunshine 
{
	var $version;
	var $country;
	var $affiliate_id;
	var $affiliate_net;
	var $subscription_id;
	var $plugin_home_url;
	var $plugin_dir;
	var $plugin_url;
	var $user_id;
	var $password;

	function WP_Sunshine()
	{ 
		// initialize all the variables
		$this->version = '0.55';
		$this->plugin_home_url = 'http://www.sunshine.co.uk/affiliates/wordpress';
		$this->plugin_dir = WP_CONTENT_DIR.'/plugins/'.plugin_basename(dirname(__FILE__));
		$this->plugin_url = get_option("siteurl").'/wp-content/plugins/'.plugin_basename(dirname(__FILE__));
		
		$this->affiliate_id = get_option('wpss_affiliate_id');
		$this->user_id = get_option('wpss_user_id');
		$this->password = get_option('wpss_password');
		
		// Set defaults if properties aren't set
		if(!$this->country ) update_option('wpss_country_tld', 'UK');
	}

	/**
	* performs a simple request to the sunshine.co.uk wp area for the latest
	* version file.
	*
	* @return float The current version number of the latest WP release
	*/
	function check_for_updates() 
	{
		$request  = "GET /affiliates/wordpress/version.txt HTTP/1.1\n";
		$request .= "Host: www.sunshine.co.uk\n";
		$request .= "Referer: " . $_SERVER["SCRIPT_URI"] . "\n";
		$request .= "Connection: close\n";
		$request .= "\n";
		
		$fp = fsockopen("www.sunshine.co.uk", 80);
		fputs($fp, $request);
		while(!feof($fp)) {
		  $result .= fgets($fp, 128);
		}
		fclose($fp);
		
		$result = explode("\r\n",$result);
		foreach($result as $res)
		{
			$part = explode(":",$res);
			if($part[0] == "Version")
				return trim($part[1]);
		}
	}
  
	
	/**
	 * This function updates all the country/region/resort/hotel information direct
	 * from sunshine.co.uk using the CSV files provided to affiliates 
	 *
	 */
	function update_content()
	{
		if(file_exists($this->plugin_dir."/includes/dbpopulate.php")) 
		{
			include($this->plugin_dir."/includes/dbpopulate.php");
			update_option('wpss_last_content_update', time());
		}		
	}
	
	/**
	 * Depending on what content type has been selected, this function adds the 
	 * country/region/resort/hotel information for that area. It will recursively
	 * loop down the levels until all parts are added. Countries are added as cats,
	 * regions subcats, resorts tags and accoms posts.
	 *
	 * @param integer $contenttype Contains a number representing what contentid represents, i.e. 1 is country, 2 is region, 3 is resort e.t.c.
	 * @param integer $contentid Represents the sunshine identifier for a geographic identity
	 * @param boolean $recursive
	 * @return integer Returns the ID of the content in wordpress, zero otherwise.
	 */
	function add_content_post($contenttype,$contentid,$recursive=true)
	{
		global $wpdb;
		
		// reset time limit every iteration
		set_time_limit(60);
		
		// make sure we have the required info
		if(empty($contentid))
			return;
			
		switch($contenttype)
		{
			// country
			case 1:
				// check to ensure country doesn't already exist
				$catid = $wpdb->get_var("SELECT pid FROM wpss_post_content_map WHERE maptype=1 and mapid='".$wpdb->escape($contentid)."' LIMIT 1;");
				
				if(empty($catid))
				{
					$postname = $wpdb->get_var("SELECT name FROM wpss_country WHERE cid='".$wpdb->escape($contentid)."' LIMIT 1;");
					$posttype = "country";
					
					// Create category object
					$my_cat = array();
					$my_cat["cat_name"] = $postname;
					
					// Insert the post into the database
					$catid = wp_insert_category($my_cat);
					
					if($catid)
						$wpdb->query("INSERT INTO wpss_post_content_map (pid, maptype, mapid) VALUES ('$catid','".$wpdb->escape($contenttype)."','".$wpdb->escape($contentid)."');");
				}
				
				// if the user wants to recurse down the different levels adding all the regions and resorts below...
				if($recursive)
				{
					// now add all the regions if any in this country
					$regions = $wpdb->get_results("SELECT DISTINCT wr.regid,wr.name FROM wpss_region wr INNER JOIN wpss_country_region_resort wcr ON wr.regid=wcr.regid WHERE wcr.cid='".$wpdb->escape($contentid)."' ORDER BY wr.name ASC;");
					foreach($regions as $region)
					{
						$this->add_content_post(2,$region->regid);
					}
					
					// now add all the remaining resorts in this country as posts
					$resorts = $wpdb->get_results("SELECT wr.rid,wr.name FROM wpss_resort wr INNER JOIN wpss_country_region_resort wcr ON wr.rid=wcr.rid WHERE wcr.cid='".$wpdb->escape($contentid)."' and wcr.regid='' ORDER BY wr.name ASC;");
					foreach($resorts as $resort)
					{
						$this->add_content_post(3,$resort->rid);
					}
				}
				
				return $catid;
				
				break;
				
			// region
			case 2:
				
				// check to ensure region doesn't already exist
				$catid = $wpdb->get_var("SELECT pid FROM wpss_post_content_map WHERE maptype=2 and mapid='".$wpdb->escape($contentid)."' LIMIT 1;");
				
				// if the region category does not exist
				if(empty($catid))
				{
					// find parent country the region belongs to
					$cid = $wpdb->get_var("SELECT cid FROM wpss_country_region_resort WHERE regid='".$wpdb->escape($contentid)."' LIMIT 1;");
					
					// find country mapping with category on wordpress
					$parentcid = $wpdb->get_var("SELECT pid FROM wpss_post_content_map WHERE mapid='".$wpdb->escape($cid)."' and maptype='1' LIMIT 1;");
					
					// no parent country has been added yet, add it now
					if(empty($parentcid))
					{
						$parentcid = $this->add_content_post(1,$cid,false);	
					}
					
					// get name of region from db
					$postname = $wpdb->get_var("SELECT name FROM wpss_region WHERE regid='".$wpdb->escape($contentid)."' LIMIT 1;");
					$posttype = "region";
					
					if(!is_category($postname))
					{
						// Create category object
						$my_cat = array();
						$my_cat["cat_name"] = $postname;
						$my_cat["category_parent"] = $parentcid;
						
					
						// Insert the post into the database
						$catid = wp_insert_category($my_cat);
						$wpdb->query("INSERT INTO wpss_post_content_map (pid, maptype, mapid) VALUES ('$catid','".$wpdb->escape($contenttype)."','".$wpdb->escape($contentid)."');");
					}
				}
				
				if(!empty($catid))
				{	
					// if the user wants to recurse down to resort level, adding all missing resorts as it goes
					if($recursive)
					{
						// now add all the resorts in this resort
						$resorts = $wpdb->get_results("SELECT wr.rid,wr.name FROM wpss_resort wr INNER JOIN wpss_country_region_resort wcr ON wr.rid=wcr.rid WHERE wcr.regid='".$wpdb->escape($contentid)."' ORDER BY wr.name ASC;");
						foreach($resorts as $resort)
						{
							$this->add_content_post(3,$resort->rid);
						}
					}
					
				}
				
				return $catid;
				
				break; 
				
			// resorts
			case 3:
				// check to ensure resort doesn't already exist
				$resortid = $wpdb->get_var("SELECT pid FROM wpss_post_content_map WHERE maptype=3 and mapid='".$wpdb->escape($contentid)."' LIMIT 1;");

				// if the resort does not already exist
				if(empty($resortid))
				{
					$parents = $wpdb->get_row("SELECT cid,regid FROM wpss_country_region_resort WHERE rid='".$wpdb->escape($contentid)."' LIMIT 1;");
					
					if(empty($parents->regid)&&!empty($parents->cid))
					{
						// get country as parent id
						$parentid = $wpdb->get_var("SELECT pid FROM wpss_post_content_map WHERE maptype='1' and mapid='".$wpdb->escape($parents->cid)."';");
						
						// if there is no mapping then there is no country
						if(empty($parentid))
						{
							$parentid = $this->add_content_post(1,$parents->cid,false);
						}
					}
					else if(!empty($parents->regid))
					{
						// get region as parent id
						$parentid = $wpdb->get_var("SELECT pid FROM wpss_post_content_map WHERE maptype='2' and mapid='".$wpdb->escape($parents->regid)."';");
						// if there is no mapping then there is no region
						if(empty($parentid))
						{
							$parentid = $this->add_content_post(2,$parents->regid,false);
						}
					}
					
					$postname = $wpdb->get_var("SELECT name FROM wpss_resort WHERE rid='".$wpdb->escape($contentid)."' LIMIT 1;");
					$posttype = "resort";
				
					$tag = wp_create_tag($postname);
					$resortid = $tag["term_id"];
					$wpdb->query("INSERT INTO wpss_post_content_map (pid, maptype, mapid) VALUES ('".$resortid."','".$wpdb->escape($contenttype)."','".$wpdb->escape($contentid)."');");
				}
				
				// if the resort now exists, add all the hotels in that resort
				if($resortid)
				{	
					// now add all the hotels in this resort as posts
					$accoms = $wpdb->get_results("SELECT aid,name FROM wpss_accom WHERE rid='".$wpdb->escape($contentid)."' ORDER BY name ASC;");
					foreach($accoms as $accom)
					{
						$this->add_content_post(4,$accom->aid);
					}
				}
			
				return $resortid;
				
				break;
				
			// hotels
			case 4:
				// check to ensure hotel doesn't already exist
				$newpostid = $wpdb->get_var("SELECT pid FROM wpss_post_content_map WHERE maptype=4 and mapid='".$wpdb->escape($contentid)."' LIMIT 1;");
				
				// if the hotel does not exists, add it
				if(empty($newpostid))
				{
					$result = $wpdb->get_row("SELECT name,rid,stars FROM wpss_accom WHERE aid='".$wpdb->escape($contentid)."' LIMIT 1;");
					$postname = $result->name;
					$tags = $wpdb->get_var("SELECT name FROM wpss_resort WHERE rid='".$wpdb->escape($result->rid)."' LIMIT 1;");
					$parents = $wpdb->get_row("SELECT cid,regid FROM wpss_country_region_resort WHERE rid='".$wpdb->escape($result->rid)."' LIMIT 1;");
					
					if(empty($parents->regid)&&!empty($parents->cid))
					{
						// get country as parent id
						$parentid = $wpdb->get_var("SELECT pid FROM wpss_post_content_map WHERE maptype='1' and mapid='".$wpdb->escape($parents->cid)."';");
						
						// if there is no mapping then there is no country
						if(empty($parentid))
						{
							$parentid = $this->add_content_post(1,$parents->cid,false);
						}
					}
					else if(!empty($parents->regid))
					{
						// get region as parent id
						$parentid = $wpdb->get_var("SELECT pid FROM wpss_post_content_map WHERE maptype='2' and mapid='".$wpdb->escape($parents->regid)."';");
						// if there is no mapping then there is no region
						if(empty($parentid))
						{
							$parentid = $this->add_content_post(2,$parents->regid,false);
						}
					}
					
					$posttype = "hotel";
					
					// Create post object using hotel information
					$my_post = array();
					$my_post["post_title"] = $postname;
					$my_post["post_content"] = "<a href=\"".wpss_affiliate_link("http://www.sunshine.co.uk/hotels/".str_replace(" ","_",$postname)."-".$contentid.".html")."\">$postname</a>";
					$my_post["post_status"] = "draft";
					$my_post["post_date"] = date("Y-m-d H:i:s");
					$my_post["post_author"] = 1;
									
					// Insert the post into the database
					$newpostid = wp_insert_post($my_post);
					
					// if the post created successfully...
					if($newpostid)
					{
						// add tags to the post
						if(!empty($tags))
							wp_set_post_tags($newpostid,$tags);
							
						wp_set_post_categories($newpostid, array($parentid));
						add_post_meta($newpostid, "Rating", $result->stars, true);
							
						// add wordpress post id to sunshine accom id mapping
						$wpdb->query("INSERT INTO wpss_post_content_map (pid, maptype, mapid) VALUES ('$newpostid','".$wpdb->escape($contenttype)."','".$wpdb->escape($contentid)."');");
						
					}
				}
				
				return $newpostid;
				
				break;
		}
		
		
		return 0;

	}
	

	/**
	 * Adds the required css and js to the header for the search box and search results.
	 *
	 */
	function add_head() 
	{
		?>
		<link rel="stylesheet" href="<?php echo get_option('siteurl'); ?>/wp-content/plugins/sunpress/css/sunpress.css" type="text/css" />
	    <script type="text/javascript" src="<?php echo get_option('siteurl'); ?>/wp-content/plugins/sunpress/js/sunpress.js" charset="ISO-8859-1"></script>
	    <script type="text/javascript" src="<?php echo get_option('siteurl'); ?>/wp-content/plugins/sunpress/js/sunpress-searchbox.js" charset="ISO-8859-1"></script>
		<?php
	}
  
	/**
	 * This function creates the widget search box that will appear in Design/Appearance -> Widgets
	 *
	 * @param unknown_type $args
	 */
	function widget_ssb($args) 
	{
		if(!empty($_POST["hotelsearch"]))
		{
			list($aid,$rid,$cid,$sdepukdate,$duration,$rooms,$adults,$children,$ages,$regid) = explode("|",$_POST["hotelsearch"]);
			list($depairp,$depcode,$arrairp,$arrcode,$depukdate,$arrabroaddate,$depabroaddate,$arrukdate,$fprice,$airline,$outflightcode,$returnflightcode) = explode("|",$_POST["flightsummary"]);
			
			$day = date("d",$sdepukdate);
			$monthyear = date("m-Y",$sdepukdate);
			$depabroaddate = $sdepukdate + ($duration*86400);
			
			$adults = unserialize(stripslashes($adults));
			$children = unserialize(stripslashes($children));
			$ages = unserialize(stripslashes($ages));
	  
			 // repopulate menu
			 $_POST["depdate"] = $day;
			 $_POST["depmonth"] = $monthyear;
			 $_POST["cid"] = $cid;
			 $_POST["rid"] = $rid;
			 $_POST["regid"] = $regid;
			 $_POST["aid"] = $aid;
			 $_POST["rooms"] = $rooms;
			 $_POST["duration"] = $duration;
			 $_POST["adults"] = $adults;
			 $_POST["children"] = $children;
			 $_POST["infants"] = $infants;
			 $_POST["depairp"] = $depcode;
			 $_POST["arrairp"] = $arrcode;
			 $_POST["age"] = $ages;
			 
			 // as flights use different adult fields, check if flight only, populate the required fields
			 // for filling the select boxes below
			 if(!empty($_POST["arrairp"]))
			 {
			 	$_POST["adultsf"] = $adults;
			 	$_POST["childrenf"] = $children;
			 }
		}
		else if(sizeof($_POST)==0)
		{
			$sbtype = get_option('wpss_sb_sbtype'); 
			$_POST["cid"] = get_option('wpss_sb_cid');
			$_POST["rid"] = get_option('wpss_sb_rid');
			$_POST["aid"] = get_option('wpss_sb_aid');
			$_POST["depairp"] = get_option('wpss_sb_depairp');
		}
		
	    extract($args);
	    $curdate = time();
	    $defdate = $curdate + (3*86400);
	?>
	        <?php echo $before_widget; ?>
	            <?php echo $before_title . $after_title; ?>
	
    	<form action="" id="searchform" name="searchform" method="post">
    	<div id="wpss_searchbox">
        	<div id="searchtype">
            	<div><label onclick="selectType(1);" ><input type="radio" name="stype" id="stype1" onclick="selectType(1);" /> Holiday</label></div>
                <div><label onclick="selectType(2);" ><input type="radio" name="stype" id="stype2" <?php echo (empty($sbtype)?"checked=\"checked\"":""); ?> onclick="selectType(2);" /> Hotel Only</label></div>
                <div><label onclick="selectType(3,'','');" ><input type="radio" name="stype" id="stype3" onclick="selectType(3,'','<?php echo get_option('home');?>');"  /> Flight Only</label></div>
            </div>
        	<div id="deplbl">
        		<label>Departure</label>
            	<select name="depdate" id="depdate" class="wpss_sel1"><?php echo dayOptions((!empty($_POST["depdate"])?$_POST["depdate"]:date("d",$defdate)));?></select> <select name="depmonth" id="depmonth" class="wpss_sel3"><?php echo monthOptions((!empty($_POST["depmonth"])?$_POST["depmonth"]:date("m-Y",$defdate)));?>
            	</select>
            </div>
            <div id="durlbl">
            	<label>Duration</label>
            	<select name="duration" class="wpss_sel1"><?php echo durationOptions(!empty($_POST["duration"])?$_POST["duration"]:'7');?></select>
            </div>
            <div id="fromlbl" style="display:none;">
            	<label>From</label>
            	<select name="depairp[]" id="wpss_depairp" class="wpss_sel"><option>- Departing Airport -</option>
            		<?php echo depAirportOptions((!empty($_POST["depairp"])?$_POST["depairp"]:"")); ?>
            	</select>
            	<div id="frm-airp">
            	</div>
            	<div id="sb-frm-airp">
					<a href="#" id="depairpadd" onclick="addAirport();return false;">+ add departure airport</a>
				</div>
            </div>
            <div id="toalbl" style="display:none;">
            	<label>To</label>
            	<select name="arrairp" id="wpss_arrairp" class="wpss_sel"><option>Choose Arrival Airport</option></select>
            </div>
            <div id="tolbl">
	            <label>To</label>
	            	<div><select name="cid" class="wpss_sel" onchange="getResorts('<?php echo get_option('home');?>',this.value,'','');"><option value="0">Select Country</option>
	            		<?php echo countryOptions((!empty($_POST["cid"])?$_POST["cid"]:"")); ?>
	            	</select></div>
	            	<div><select name="rid" id="wpss_rid" class="wpss_sel" onchange="getHotels('<?php echo get_option('home');?>',this.value,'');"><option value="0">Choose Resort</option></select></div> 
	            	<?php
						if(!empty($_POST["cid"]))
							echo "<script>getResorts('".get_option('home')."','".addslashes($_POST["cid"])."','".addslashes($_POST["rid"])."','');</script>";
	            	?>
	            	<div><select name="aid" id="wpss_aid" class="wpss_sel"><option value="0">All Accommodations</option></select></div>
	            	<?php
			        	if(!empty($_POST["rid"]))
			        	{
			        		?>
			        		<script>getHotels('<?php echo get_option('home');?>','<?php echo $_POST["rid"];?>','<?php echo $_POST["aid"];?>');</script>
			        		<?php
			        	}
			        ?>
            </div>
            <div id="roomlayout">
	            <div class="searchval">
	                <b style="color:#FFF;display:block;padding:22px 4px 0 0;">Room 1</b>
	            </div>
	            <div class="searchval">
	            	<label>Adults</label>
	                
		                <select name="adults[]" id="adults1" class="wpss_sel1">
		                <?php
							for($i=1;$i<=4;$i++)
							{ 
								echo "<option ".(empty($_POST["adults"]) && $i==2?"selected=\"selected\"":"")." value=\"$i\">".$i."</option>\n";
							}
		                ?>
		                </select>
		        	
		        </div>
		        <div class="searchval">
		        	<label>Children</label>
		                <select name="children[]" id="children1" onchange="addremoveage(this.selectedIndex,1);" class="wpss_sel1">
		                <?php
			                for($i=0;$i<=4;$i++)
							{ 
								echo "<option ".(empty($_POST["children"]) && $i==0?"selected=\"selected\"":"")." value=\"$i\">".$i."</option>\n";
							}
		                ?>
		            	</select>
		         </div> 
	         </div>
	         
	         <div id="flightlayout" style="display:none;width:100%;">
		         <div class="searchval">
	            	<label>Adults</label>
	                <div>
		                <select name="adultsf[]" id="adultsf1" class="wpss_sel1">
		                <?php
							for($i=1;$i<=8;$i++)
							{ 
								echo "<option ".(empty($_POST["adults"]) && $i==2?"selected=\"selected\"":"")." value=\"$i\">".$i."</option>\n";
							}
		                ?>
		                </select>
		        	</div>
		        </div>
		        <div class="searchval">
		        	<label>Children</label>
		            <div>
		                <select name="childrenf[]" id="childrenf1" onchange="addremoveage(this.selectedIndex,1);" class="wpss_sel1">
		                <?php
			                for($i=0;$i<=4;$i++)
							{ 
								echo "<option ".(empty($_POST["children"]) && $i==0?"selected=\"selected\"":"")." value=\"$i\">".$i."</option>\n";
							}
		                ?>
		            	</select>
		            </div>
		         </div>
		         <div class="wpss_sp"></div>
	         </div>
	         
	         <div id="agesroom1"></div>
	         <div id="roomsdiv"></div>
	         <div id="addrdiv">
	         	<a href="#" onclick="addroom(parseInt(document.getElementById('rc').value)+1);return false;">+ add a room</a>&nbsp;
	         </div>
	         <div id="addadiv">
	         	<a href="#" onclick="removeroom(parseInt(document.getElementById('rc').value)-1);return false;">- remove a room</a>
	         </div>
         	
            <div style="clear:both;"><button type="submit" name="ss_searchbutton" id="ss_searchbutton" onclick="return checkit();">Check Availability</button></div>
            
            <input type="hidden" name="sbtype" id="ssbtype" value="2" />
         	<input type="hidden" name="centreday" id="centreday" value="" />	
        	<input type="hidden" name="rooms" id="rc" value="1" />
        	<input type="hidden" value="1" id="airpcount" />
	        <input type="hidden" value="0" id="agescount1" />
			<input type="hidden" value="0" id="agescount2" />
			<input type="hidden" value="0" id="agescount3" />
			<input type="hidden" value="0" id="agescount4" />
			
        </form>
        <script>
        	document.getElementById('airpcount').value = 1;
            <?php
			
            if(!empty($_POST["rooms"]))
			{
				
						if($_POST["rooms"]>1)
							echo "setrooms('".addslashes($_POST["rooms"]-1)."');\n";
						
				echo "setadults('adults',new Array('".implode("','",$_POST["adults"])."'));
					  setchildren('children',new Array('".implode("','",$_POST["children"])."'));\n";	
								
			}
			
			if(!empty($_POST["arrairp"]))
			{
				echo "setadults('adultsf',new Array('".$_POST["adultsf"][0]."'));
					  setchildren('childrenf',new Array('".$_POST["childrenf"][0]."'));\n";
			}
			
			if(!empty($_POST["depairp"]))
			{
				if(is_array($_POST["depairp"]))
				{
					echo "document.getElementById('wpss_depairp').value='".$_POST["depairp"][0]."';\n";
					for($i=1;$i<sizeof($_POST["depairp"]);$i++)
					{
						echo "addAirport();\n";
						echo "document.getElementById('addedairp".($i+1)."').value='".$_POST["depairp"][$i]."';\n";
					}	
				}
				else
					echo "document.getElementById('wpss_depairp').value='".$_POST["depairp"]."';\n";
			}
			 
			if(!empty($_POST["age"]))
			{
				foreach($_POST["age"] as $roomkey => $room)
				{
					echo "addage(".sizeof($room).",$roomkey);\n";
					foreach($room as $agekey => $chage)
					{
						echo "document.getElementById('age{$agekey}r{$roomkey}').value='$chage';\n";
					}
				} 
			}
			
	      	?>
			</script>
    
    <style>
	/** Customisable style elements **/
	#wpss_searchbox * {font-size:<?php echo get_option("wpss_sb_css_fontsize"); ?>px;}
	#wpss_searchbox{border:1px solid <?php echo get_option("wpss_sb_css_bordercolor");?>;background-color:<?php echo get_option("wpss_sb_css_bgcolor");?>;padding:10px;font-size:<?php echo (get_option("wpss_sb_css_fontsize")+1); ?>px;margin-bottom:10px;}
	#wpss_searchbox label{font-family:<?php echo get_option("wpss_sb_css_font");?>;font-weight:bold;color:<?php echo get_option("wpss_sb_css_fontcolor");?>;display:block;}
	#wpss_searchbox #searchtype{float:right;width:120px;margin:0px 0px 10px 10px;}
	#wpss_searchbox .searchval2 b{color:<?php echo get_option("wpss_sb_css_fontcolor");?>;line-height:25px;vertical-align:top;}
	</style>
	
	<?php
		if(!empty($_COOKIE["searchboxtype"]))
		{
			echo "<script>selectType(".addslashes($_COOKIE["searchboxtype"]).",'".addslashes($_POST["arrairp"])."','".get_option('home')."');</script>";
		}
		else if(!empty($sbtype))
		{
			echo "<script>selectType(".addslashes($sbtype).",'".addslashes($_POST["arrairp"])."','".get_option('home')."');</script>";
		}
		
	    echo $after_widget;
	}
  
	/**
	 * registers the widget, the widget will die if wordpress version doesn't support
	 * register_sidebar_widget
	 *
	 */
	function widget_init() 
	{
		// Check for required functions
		if (!function_exists('register_sidebar_widget'))
			die('sidebar function does not exist, this is required for use with this plugin');
	
		register_sidebar_widget('sunPress Search',array(&$this, 'widget_ssb'));
	}
	
	/**
	 * This function is called when the search widget is POSTed. It handles
	 * and outputs the search results to the user.
	 *
	 */
	function search_results()
	{
		include('wp-content/plugins/sunpress/templates/wpss_search.php');
		exit();
	}
	
	/**
	 * This is called when the user is redirected to sunshine.co.uk to complete the
	 * transaction.
	 *
	 */
	function redirect()
	{
		include('wp-content/plugins/sunpress/templates/wpss_redirect.php'); 
		exit();
	}
	
	/**
	 * This is called when the user is redirected to a sunshine.co.uk url to complete the
	 * transaction.
	 *
	 */
	function redirect_url()
	{
		include('wp-content/plugins/sunpress/templates/wpss_redirect_url.php'); 
		exit();
	}
	 
	/**
	 * Maintenance function that is called when a category is deleted from wordpress,
	 * it removes the sunshine id to wordpress id mapping we maintain.
	 *
	 * @param int $param1
	 */
	function delete_category($param1)
	{
		global $wpdb;
		
		$wpdb->query("DELETE FROM wpss_post_content_map WHERE maptype<3 and pid='".$wpdb->escape($param1)."' LIMIT 1;");
	}
	
	/**
	 * Maintenance function that is called when a term(tag for e.g.) is deleted from wordpress,
	 * it removes the sunshine id to wordpress id mapping we maintain.
	 *
	 * @param int $param1
	 */
	function delete_term($param1)
	{
		global $wpdb;
		
		$wpdb->query("DELETE FROM wpss_post_content_map WHERE maptype=3 and pid='".$wpdb->escape($param1)."' LIMIT 1;");
	}
	
	/**
	 * Maintenance function that is called when a post is deleted from wordpress,
	 * it removes the sunshine id to wordpress id mapping we maintain.
	 *
	 * @param int $param1
	 */
	function delete_post($param1)
	{
		global $wpdb;
		
		$wpdb->query("DELETE FROM wpss_post_content_map WHERE maptype=4 and pid='".$wpdb->escape($param1)."' LIMIT 1;");
	}
	
	/**
	 * Outputs the meta information, i.e. the star rating to the hotel content.
	 *
	 * @param unknown_type $param1
	 */
	function add_meta($param1)
	{
		the_meta();
		return $param1;
	}
  
	/**
	 * Adds the menus for the admin options pages
	 *
	 */
	function admin_options_menu()
	{
		add_menu_page('sunPress', 'sunPress', '10', __FILE__,array(&$this, 'options_page')); 
		add_submenu_page(__FILE__, 'Settings', 'Settings', '10', 'sunpress-page1', array(&$this, 'settings_options_page'));
		add_submenu_page(__FILE__, 'Search Box', 'Search Box', '10', 'sunpress-page2', array(&$this, 'sb_options_page'));
		
		if($_POST["wpss_enable_content"]==1 || (get_option("wpss_enable_content")==1 && !isset($_POST["enablecontentbutton"])))
			add_submenu_page(__FILE__, 'Content', 'Content', '10', 'sunpress-page3', array(&$this, 'content_options_page'));
	}
	
	/**
	 * Default options page in the sunshine admin group
	 *
	 */
	function options_page() 
	{
		global $wpdb;
		$formaction = "admin.php?page=sunpress/sunpress.php";
		
		// if the user has clicked the version checker button
		if(isset($_POST["lvchecker"]))
		{
			$version = $this->check_for_updates();
			if($version > $this->version)
				echo '<div class="updated"><p>' . _c('There is a newer version, the latest version is v'.$version.', you currently have v'.$this->version.'. Download latest <a href="http://wordpress.org/extend/plugins/sunpress/">here</a>', 'sunpress') . '</p></div>';
			else 
				echo '<div class="updated"><p>' . _c('You have the latest version v'.$this->version.' of the Wordpress plugin.', 'sunpress') . '</p></div>';				
		}
		
		// set class wide variables
		$this->user_id = get_option('wpss_user_id');
		$this->password = get_option('wpss_password');
		$this->affiliate_id = get_option('wpss_affiliate_id');
		$this->affiliate_net = get_option('wpss_affiliate_net');
		?>
		<div style="float:right;width:20%;">
		<b>sunshine.co.uk affiliate blog</b>
		<?php
			// output the latest from our affiliate blog rss feed
			$this->sunshine_rss();
		?></div>
		<div style="float:left;width:70%;">
		<div class="wrap">
		<h2>Welcome to sunPress</h2>
		<p><?php _e('sunshine.co.uk has an affiliate program through Affiliate Future and Paid On Results.  This program allows you to earn money for referring customers to sunshine.co.uk. Using this affiliate program and this wordpress plugin, you can quickly create a travel site and start generating sales.', 'sunpress'); ?></p>
		<p><?php _e('Full details about our affiliate programme can be found on Paid on Results <a target="_blank" href="http://www.paidonresults.com/merchants/sunshine.html">here</a> and Affiliate Future <a target="_blank" href="http://www.affiliatefuture.co.uk/registration/step1.asp?ref=2980">here</a>. Please use the options in the left hand sunPress menu to customise your site','wp_sunshine'); ?></p>
		
		<?php
	   	
		// active hotel post count
	   	$posts = $wpdb->get_var("SELECT COUNT(pid) FROM wpss_post_content_map w WHERE maptype=4");
		$widgetactive = is_active_widget(array(&$this,'widget_ssb'));
		
		$affnets = array("fut"=>"Affiliate Future","por" => "Paid On Results");
	   	 ?>
	   	 </div>
	   	<div class="wrap">
		    <h2><?php _e('Setup Progress', 'sunpress'); ?></h2>
		        <fieldset class="options">
		        	<br />
		        	<div class="wrap">
		        	<p><li>Affiliate Network : <?php echo (!empty($this->affiliate_net))?"Added (<b>".$affnets[$this->affiliate_net]."</b>)":"not yet added - You can specify your affiliate network <a href=\"admin.php?page=sunpress-page1\">here</a>"; ?></li>
				   	<p><li>Affiliate ID : <?php echo (!empty($this->affiliate_id))?"Added (<b>$this->affiliate_id</b>)":"not yet added - You can add your affiliate id <a href=\"admin.php?page=sunpress-page1\">here</a>"; ?></li>
					<p><li>XML Services ID : <?php echo (!empty($this->user_id))?"Added (<b>$this->user_id</b>)":"not yet added - You can specify/request your xml credentials <a href=\"admin.php?page=sunpress-page1#webservices\">here</a>"; ?></li>
					<p><li>Search Box : <?php echo ($widgetactive)?"<b>Active</b>":"inactive - Please click on Design/Appearance -> <a href=\"widgets.php\">Widgets</a> to activate once you have set your affiliate ID, and XML credentials"; ?></li>
					<p><li>Content : <?php echo (!empty($posts)?"Hotels:<b>$posts</b>":(get_option("wpss_enable_content")==1?"<i>none added</i> - Please click on <a href=\"admin.php?page=sunpress-page3\">here</a> to start adding content":"Please enable the content section <a href=\"admin.php?page=sunpress-page1\">here</a> and start adding.")); ?></li>
					</div>
				</fieldset>
		</div>
		
		<div class="wrap">
		<h2>Latest Version</h2>
		<p><?php _e('click here to ensure you have the latest version.', 'sunpress'); ?></p>
		<form action="<?php echo $formaction;?>" method="post">
		<input class="button" type="submit" name="lvchecker" value="Latest Version Checker" />
		</form>
		<p><i>sunshine.co.uk Wordpress Plugin v<?php echo $this->version; ?></i></p>
		<br /><br />
		</div>
		</div>
		<?php
		
	}
	
	/**
	 * Generates the rss output from our affiliate blow, using the built in rss parsers within wp
	 *
	 */
	function sunshine_rss()
	{
		if (file_exists(ABSPATH .'/wp-includes/rss.php'))
			require_once(ABSPATH.'/wp-includes/rss.php');
		else if(file_exists(ABSPATH.'/wp-includes/rss-functions.php'))
			require_once(ABSPATH.'/wp-includes/rss-functions.php');
		else 
			echo "Error: wordpress rss parser cannot be found";
		
		define('MAGPIE_CACHE_DIR', '/tmp/mysite_magpie_cache');
        define('MAGPIE_CACHE_ON', 1);

		// get sunshine.co.uk blog rss
		$rss = fetch_rss("http://www.sunshine.co.uk/affiliates/blog/feed/");
		foreach ($rss->items as $item)
		{
			?>
			<p><a href="<?php echo $item["link"]; ?>"><?php echo $item["title"]; ?></a></p>
			<?php
		}
	}
	
	/**
	 * Options page containing specific details regarding the setup of the sunshine plugin.
	 *
	 */
	function settings_options_page()
	{
		global $wpdb;
		$formaction = "admin.php?page=sunpress-page1";
		
		// if the user has clicked on update affy id button, save the new ID
		if(isset($_POST['updateaffyid'])) 
		{
			// save option
			update_option('wpss_affiliate_id', $_POST['wpss_affiliate_id']);
			update_option('wpss_affiliate_net', $_POST['wpss_affiliate_net']);
			echo '<div class="updated"><p>' . _c('Affiliate Info saved.', 'sunpress') . '</p></div>';
		}
		
		// if the save button is pressed, check validity of the xml login details then save them...
		if(isset($_POST['savelogin'])) 
		{
			require_once(ABSPATH  . "wp-content/plugins/sunpress/includes/nusoap.php");
	
			$client = new nusoap_client("http://ws.sunshine.co.uk/xml/api/sunshine3.php?wsdl",true,false,false,false,false,120,120);
			
			// Create the proxy
			$proxy = $client->getProxy();
			
			// Perform the search 
			$userid = $proxy->CheckLogin(array("UserId"=>$_POST["wpss_user_id"],"Password"=>md5($_POST["wpss_password"])));
			 
			if(empty($userid) || !empty($userid["faultstring"]))
			{
				echo '<div class="updated"><p>' . _c('These details are not recognised, please ensure they are correct and try again', 'sunpress') . '</p></div>';	
			}
			else 
			{
				// save login details
				update_option('wpss_user_id', $_POST['wpss_user_id']);
				update_option('wpss_password', $_POST['wpss_password']);
				echo '<div class="updated"><p>' . _c('Your login details have been saved', 'sunpress') . '</p></div>';	
			}
		}
		
		// user is requesting a new login, so clear existing values and instruct them to fill
		// out the new form
		if(isset($_POST["enablecontentbutton"]))
		{			
			// save login details
			update_option('wpss_enable_content', $_POST["wpss_enable_content"]);
			if($_POST["wpss_enable_content"]==1)
			{
				$pages = array('All Inclusive Holidays','Family Holidays','Late Deals','Bargain Holidays','hotels');
				foreach($pages as $pagename)
				{
					// check to ensure they haven't already been added
					$page = get_page_by_title($pagename);
					if(empty($page->ID))
					{
						$newpost = array('post_title'=>$pagename,'post_type'=>'page','page_template'=>'pages.php','post_status'=>'publish', 'post_author'=>1, 'post_category'=>array(0));
						wp_insert_post($newpost);
					}
				}
				
				// add sitemap page
				$page = get_page_by_title("Site Map");
				if(empty($page->ID))
				{
					$newpost = array('post_title'=>"Site Map",'post_type'=>'page','page_template'=>'sitemap.php','post_status'=>'publish', 'post_author'=>1, 'post_category'=>array(0));
					wp_insert_post($newpost);
				}
				
				echo '<div class="updated"><p>' . _c('A new <a href="admin.php?page=sunpress-page3">content tab</a> has been added to admin, please use this to add and manage new hotels/locations to the site', 'sunpress') . '</p></div>';	
			}
			else 
				echo '<div class="updated"><p>' . _c('Your content preferences have been saved.', 'sunpress') . '</p></div>';	
		}
		
		// user is requesting a new login, so clear existing values and instruct them to fill
		// out the new form
		if(isset($_POST["requestnewlogin"]))
		{
			// save login details
			update_option('wpss_user_id', '');
			update_option('wpss_password', '');
			echo '<div class="updated"><p>' . _c('Your existing login details have been cleared, please use the form below to apply for a new account.', 'sunpress') . '</p></div>';	
		}
		
		// user has specified new request, email affiliates@sunshine.co.uk with new details.
		if(isset($_POST["requestlogin"]))
		{
			if(!empty($_POST["wpss_email"]) && !empty($_POST["wpss_ip"]))
			{
				require_once(ABSPATH  . "wp-content/plugins/sunpress/includes/nusoap.php");
	
				$client = new nusoap_client("http://ws.sunshine.co.uk/xml/api/sunshine3.php?wsdl",true,false,false,false,false,120,120);
				
				// Create the proxy
				$proxy = $client->getProxy();
				
				// Perform the search 
				$success = $proxy->RequestLogin(get_option('blogname'),$_POST["wpss_email"],get_option('siteurl'),$_POST["wpss_ip"]);
				
				if(!empty($success)) 
					echo '<div class="updated"><p>' . _c('You will be emailed shortly with your login details for the XML service.', 'sunpress') . '</p></div>';					
				else
					echo '<div class="updated"><p>' . _c('There has been an error saving your details, please email affiliates@sunshine.co.uk with your site details.', 'sunpress') . '</p></div>';					
			}
			else 
			{
				echo '<div class="updated"><p>' . _c('Please ensure you have supplied both an email and IP address from which you wish to access the XML service.', 'sunpress') . '</p></div>';					
			}
			
		}
		
		$this->affiliate_id = get_option('wpss_affiliate_id');
		$this->affiliate_net = get_option('wpss_affiliate_net');
		
		?>
		<div class="wrap">
		  <form name="wpss_options" method="post" action="<?php echo $formaction; ?>">
	        <h2><?php _e('sunPress Content', 'sunpress'); ?></h2>
		        <p></p> 
		        <fieldset class="options">
		        	<br />
		            <p>
		            <?php _e('Would you like to enable the built in Content facilities, allowing you to quickly add content based on the feeds from sunshine.co.uk?', 'sunpress'); ?>
		            </p>
		
		            <table width="100%" cellspacing="2" cellpadding="5" class="editform">
		            <tr height="50">
		                <th width="33%" valign="middle" scope="row" align="right"><?php _e('Enable Content Tools:', 'sunpress'); ?> </th>
		                <td>
		                    <input name="wpss_enable_content" <?php echo (get_option("wpss_enable_content")==1?"checked=\"checked\"":""); ?> type="checkbox" id="wpss_enable_content" value="1" size="9" /><br />
		                </td>
		            </tr>
		            <tr><td></td>
			            <td>
			            	<input class="button" type="submit" name="enablecontentbutton" value="<?php _e('Save Changes &raquo;', 'sunpress'); ?>" />
			        	</td>
		        	</tr>
		            </table>
		        </fieldset>
	   	 	</form>
	   	 </div>
	   	 
		<div class="wrap">
		  <form name="wpss_options" method="post" action="<?php echo $formaction;?>">
	        <h2><?php _e('Affiliate Options', 'sunpress'); ?></h2>
		        <p></p> 
		        <fieldset class="options">
		        	<br />
		            <p>
		            <?php _e('Please select your network and enter your <b>Affiliate ID</b> (provided by the network) here and click \'Update Affiliate Info\'. This will append your ID to all outgoing links to sunshine.co.uk', 'sunpress'); ?>
		            </p>
		            <table width="100%" cellspacing="2" cellpadding="5" class="editform">
		            <tr height="50">
		                <th width="33%" valign="middle" scope="row" align="right"><?php _e('Affiliate Network:', 'sunpress'); ?> </th>
		                <td>
		                    <label><input type="radio" <?php echo ((empty($this->affiliate_net) || $this->affiliate_net=='fut')?"checked=\"checked\"":""); ?> value="fut" name="wpss_affiliate_net" /> Affiliate Future</label>&nbsp;&nbsp;&nbsp;&nbsp;<label><input type="radio" <?php echo ($this->affiliate_net=='por'?"checked=\"checked\"":""); ?> value="por" name="wpss_affiliate_net" /> Paid On Results</label><br />
		                </td>
		            </tr>
		            <tr height="50">
		                <th width="33%" valign="middle" scope="row" align="right"><?php _e('Affiliate ID:', 'sunpress'); ?> </th>
		                <td>
		                    <input name="wpss_affiliate_id" type="text" id="wpss_affiliate_id" value="<?php echo $this->affiliate_id; ?>" size="9" /><br />
		                </td>
		            </tr>
		            <tr><td></td>
			            <td>
			            	<input class="button" type="submit" name="updateaffyid" value="<?php _e('Update Affiliate Info &raquo;', 'sunpress'); ?>" />
			        	</td>
		        	</tr>
		            </table>
		        </fieldset>
	   	 	</form>
	   	 </div>
	   	 <?php
	   	 	
	  	
		$this->user_id = get_option('wpss_user_id');
		$this->password = get_option('wpss_password');
		?>
		 <div class="wrap">
	   	 	<br /><a name="webservices"></a>
	   	 	<h2><?php _e('sunPress User Details', 'sunpress'); ?></h2> 
	   	 	<form name="wpss_content" method="post" action="<?php echo $formaction;?>">
		        <fieldset class="options">
		            <br />
		            <p>
		            <?php _e('Use this form to specify your login credentials for the sunshine.co.uk XML Webservices. If you do not have an account, please register below or email affiliates@sunshine.co.uk', 'sunpress'); ?>
		            </p>
		            <?php if(empty($this->user_id))
		            	  {
				           	?>
				            <b>Request a new login for the xml services</b>
				            <table width="100%" cellspacing="2" cellpadding="5" class="editform">
				            <tr>
				                <th width="33%" valign="top" scope="row" align="right"><?php _e('Email Address:', 'sunpress'); ?> </th>
				                <td>
				                    <input name="wpss_email" type="text" id="wpss_email" value="" size="22" /><br />
				                </td>
				            </tr>
				            <tr>
				                <th width="33%" valign="top" scope="row" align="right"><?php _e('IP Address:', 'sunpress'); ?> </th>
				                <td>
				                    <input name="wpss_ip" type="text" id="wpss_ip" size="22" value="<?php echo $_SERVER['SERVER_ADDR']; ?>" /><br />
				                </td>
				            </tr>
				            <tr height="50"><td></td>
					            <td>
					            	<input class="button" type="submit" name="requestlogin" value="<?php _e('Request Login &raquo;', 'sunpress'); ?>" />
					        	</td>
				        	</tr>
				            </table>
				            <b>Already have an account setup?</b>
		            <?php
		            	  }
		            	  else 
		            	  {
		            	  	?>
		            	  	<input class="button" type="submit" name="requestnewlogin" onclick="return confirm('Please note this will clear your existing user id and password. Are you sure you wish to proceed?');" value="<?php _e('Request New Login &raquo;', 'sunpress'); ?>" />
		            	  	<?php
		            	  }
		           	?>
		            <table width="100%" cellspacing="2" cellpadding="5" class="editform">
		            <tr>
		                <th width="33%" valign="top" scope="row" align="right"><?php _e('User ID:', 'sunpress'); ?> </th>
		                <td>
		                    <input name="wpss_user_id" type="text" id="wpss_user_id" value="<?php echo $this->user_id; ?>" size="4" /><br />
		                </td>
		            </tr>
		            <tr>
		                <th width="33%" valign="top" scope="row" align="right"><?php _e('Password:', 'sunpress'); ?> </th>
		                <td>
		                    <input name="wpss_password" type="password" id="wpss_password" value="<?php echo $this->password; ?>" size="22" /><br />
		                </td>
		            </tr>
		            <tr><td></td>
			            <td>
			            	<input class="button" type="submit" name="savelogin" value="<?php _e('Save Login Details &raquo;', 'sunpress'); ?>" />
			        	</td>
		        	</tr>
		            </table>
		            
		        </fieldset>
				
	   	 	</form>
	   	 </div>
	   	
		
		<?php
	   	 	
	}
	
	/**
	 * options page for updating the sunshine content that is within wp.
	 *
	 */
	function sb_options_page()
	{
		$formaction = "admin.php?page=sunpress-page2";
		
		// if the user has clicked on update affy id button, save the new ID
		if(isset($_POST['cid'])) 
		{
			//print_r($_POST);
			
			// save options
			update_option('wpss_sb_cid', $_POST['cid']);
			update_option('wpss_sb_rid', $_POST['rid']);
			update_option('wpss_sb_aid', $_POST['aid']);
			
			update_option('wpss_sb_depairp', $_POST['depairp']);
			update_option('wpss_sb_arrairp', $_POST['arrairp']);
			
			update_option('wpss_sb_sbtype', $_POST['sbtype']);
			
			echo '<div class="updated"><p>' . _c('Search Box Defaults saved.', 'sunpress') . '</p></div>';
		}
		
		// if the user has changed the default styling, update the settings
		if(isset($_POST["savestyling"]))
		{
			foreach($_POST as $key=>$val)
			{
				if(ereg("wpss_sb",$key)||ereg("wpss_sr",$key))
					update_option($key, trim($val,';'));
			}
		}
		
		if(isset($_POST["storeside"]))
		{
			// save options
			update_option('wpss_sidebar_side', $_POST['sidebar_side']);
		}
		
		// retrieve previously stored defaults
		$cid = get_option('wpss_sb_cid');
		$rid = get_option('wpss_sb_rid');
		$aid = get_option('wpss_sb_aid');
		
		$depairp = get_option('wpss_sb_depairp');
		$arrairp = get_option('wpss_sb_arrairp');
		$sbtype =  get_option('wpss_sb_sbtype');
		
		$fontcolor = get_option("wpss_sb_css_fontcolor");
		
		// check if search box is active
		$widgetactive = is_active_widget(array(&$this,'widget_ssb'));
		
		$sidebarside = get_option("wpss_sidebar_side");
		 
		if(!$widgetactive)
			echo '<div id="update-nag">' . _c('Please note your Search box is not active. Enable it in <a href="themes.php">Design/Appearance</a>->Widgets', 'sunpress') . '</div>';
		
		?>
		
		<div class="wrap">
		    <h2><?php _e('Search Box Defaults', 'sunpress'); ?></h2>
		    <p>Using the options below, choose a default country/region, resort for the search box</p>
			<form name="wpss_content" method="post" action="<?php echo $formaction;?>">
			<table width="100%" cellspacing="2" cellpadding="5" class="editform">
					 <tr>
		                <th width="33%" valign="top" scope="row" align="right"><?php _e('Search Type:', 'sunpress'); ?> </th>
		                <td>
				            	<select name="sbtype" id="sbtype" class="wpss_sel">
				            		<option value="">-- Select Type --</option>
				            		<option value="1">Holiday</option>
				            		<option value="2">Hotel Only</option>
				            		<option value="3">Flight Only</option>
				            	</select>
				        </td>
				    </tr>
				    <?php
			        	if(!empty($sbtype))
			        	{
			        		?>
			        		<script>document.getElementById('sbtype').value='<?php echo $sbtype;?>';</script>
			        		<?php
			        	}
			        ?>
			        <tr><td height="40">&nbsp;</td></tr>
		            <tr>
		                <th width="33%" valign="top" scope="row" align="right"><?php _e('Country:', 'sunpress'); ?> </th>
		                <td>
				            	<select name="cid" id="wpss_cid" class="wpss_sel" onchange="getResorts('<?php echo get_option('home');?>',this.value,'','-- All Resorts --');"><option value="0">-- Select Country --</option>
				            		<?php echo countryOptions($cid); ?>
				            	</select>
				        </td>
				    </tr>
				    <?php
			        	if(!empty($cid))
			        	{
			        		?>
			        		<script>getResorts('<?php echo get_option('home');?>','<?php echo $cid;?>','<?php echo $rid;?>','-- All Resorts --');</script>
			        		<?php
			        	}
			        ?>
				    <tr>
				    	<th width="33%" valign="top" scope="row" align="right"><?php _e('Resort:', 'sunpress'); ?> </th>
	                	<td>
			          		<select name="rid" id="wpss_rid" class="wpss_sel" onchange="getHotels('<?php echo get_option('home');?>',this.value,'');"><option value="0">-- All Resorts --</option></select>
			          	</td>
			        </tr>
			        <?php
			        	if(!empty($rid))
			        	{
			        		?>
			        		<script>getHotels('<?php echo get_option('home');?>','<?php echo $rid;?>','<?php echo $aid;?>');</script>
			        		<?php
			        	}
			        ?>
			        <tr>
				    	<th width="33%" valign="top" scope="row" align="right"><?php _e('Accommodation:', 'sunpress'); ?> </th>
	                	<td>
			          		<select name="aid" id="wpss_aid" class="wpss_sel"><option value="0"> -- All Accommodations --</option></select>
			          	</td>
			        </tr>
			        <tr><td height="40">&nbsp;</td></tr>
			        <tr>
				    	<th width="33%" valign="top" scope="row" align="right"><?php _e('Departing Airport:', 'sunpress'); ?> </th>
	                	<td>
			          		<select name="depairp" id="wpss_depairp" class="wpss_sel2"><option>Choose Airport</option>
			            		<?php echo depAirportOptions($depairp); ?>
			            	</select>
			          	</td>
			        </tr>
			        
			        
			        <tr><td></td>
			        <td>
			        	<input class="button" type="submit" name="postcontent" value="<?php _e('Store Defaults &raquo;', 'sunpress'); ?>" />			            		
			        </td></tr>
			 </table>
			 </form> 
		</div>
		
		<div class="wrap">
		    <h2><?php _e('Search Box Layout', 'sunpress'); ?></h2>
		    <p>Using the options below, please select which side your sidebar is located on (i.e. where your theme will show the search box). This will help us format the results better.</p>
			<form name="wpss_content" method="post" action="<?php echo $formaction;?>">
			<table width="100%" cellspacing="2" cellpadding="5" class="editform">
					 <tr>
		                <th width="33%" valign="top" scope="row" align="right"><?php _e('Side Bar Location:', 'sunpress'); ?> </th>
		                <td>
				            	<select name="sidebar_side" id="sidebar_side" class="wpss_sel">
				            		<option value="">-- Select Side --</option>
				            		<option value="left">Left</option>
				            		<option value="right">Right</option>
				            	</select>
				        </td>
				    </tr>
				    <?php
			        	if(!empty($sidebarside))
			        	{
			        		?>
			        		<script>document.getElementById('sidebar_side').value='<?php echo $sidebarside;?>';</script>
			        		<?php
			        	}
			        ?>
			        
			        
			        
			        <tr><td></td>
			        <td>
			        	<input class="button" type="submit" name="storeside" value="<?php _e('Store Side &raquo;', 'sunpress'); ?>" />			            		
			        </td></tr>
			 </table>
			 </form> 
		</div>
				
		<div class="wrap">
		    <h2><?php _e('Search Box Styling', 'sunpress'); ?></h2>
		    <p>Using the options below, choose the styling you would like applied to the search box.</p>
			<form name="wpss_content" method="post" action="<?php echo $formaction;?>">
			<table width="100%" cellspacing="2" cellpadding="5" class="editform">
				 <tr>
	                <th width="33%" valign="top" scope="row" align="right"><?php _e('Background Colour:', 'sunpress'); ?> </th>
	                <td>
			            	<input name="wpss_sb_css_bgcolor" id="wpss_sb_css_bgcolor" type="text" value="<?php echo get_option("wpss_sb_css_bgcolor");?>" />
			        </td>
			    </tr>
			    <tr>
	                <th width="33%" valign="top" scope="row" align="right"><?php _e('Border Colour:', 'sunpress'); ?> </th>
	                <td>
			            	<input name="wpss_sb_css_bordercolor" id="wpss_sb_css_bordercolor" type="text" value="<?php echo get_option("wpss_sb_css_bordercolor");?>" />
			        </td>
			    </tr>
			    <tr>
	                <th width="33%" valign="top" scope="row" align="right"><?php _e('Font Size:', 'sunpress'); ?> </th>
	                <td>
			            	<select name="wpss_sb_css_fontsize" id="wpss_sb_css_fontsize">
			            	<option value="8">8px</option>
			            	<option value="9">9px</option>
			            	<option value="10">10px</option>
			            	<option value="11">11px</option>
			            	<option value="12">12px</option>
			            	<option value="14">14px</option>
			            	</select>
			            	<script>document.getElementById('wpss_sb_css_fontsize').value='<?php echo get_option("wpss_sb_css_fontsize");?>';</script>
			        </td>
			    </tr>
			    <tr>
	                <th width="33%" valign="top" scope="row" align="right"><?php _e('Font Family:', 'sunpress'); ?> </th>
	                <td>
			            	<input name="wpss_sb_css_font" id="wpss_sb_css_font" type="text" value="<?php echo get_option("wpss_sb_css_font");?>" />
			        </td>
			    </tr>
			    <tr>
	                <th width="33%" valign="top" scope="row" align="right"><?php _e('Font Colour:', 'sunpress'); ?> </th>
	                <td>
			            	<input name="wpss_sb_css_fontcolor" id="wpss_sb_css_fontcolor" type="text" value="<?php echo get_option("wpss_sb_css_fontcolor");?>" />
			        </td>
			    </tr>
			     <tr><td></td> 
			        <td>
			        	<input class="button" type="submit" name="savestyling" value="<?php _e('Save Styling &raquo;', 'sunpress'); ?>" />			            		
			        </td></tr>
			 </table>
			</form>
		</div>
		
		<div class="wrap">
		    <h2><?php _e("Search Results&acute; Styling", 'sunpress'); ?></h2>
		    <p>Using the options below, choose the styling you would like applied to the search results.</p>
			<form name="wpss_content" method="post" action="<?php echo $formaction;?>">
			<table width="100%" cellspacing="2" cellpadding="5" class="editform">
				 <tr>
	                <th width="33%" valign="top" scope="row" align="right"><?php _e('Background Colour:', 'sunpress'); ?> </th>
	                <td>
			            	<input name="wpss_sr_css_bgcolor" id="wpss_sr_css_bgcolor" type="text" value="<?php echo get_option("wpss_sr_css_bgcolor");?>" />
			        </td>
			    </tr>
			    <tr>
	                <th width="33%" valign="top" scope="row" align="right"><?php _e('Border Colour:', 'sunpress'); ?> </th>
	                <td>
			            	<input name="wpss_sr_css_bordercolor" id="wpss_sr_css_bordercolor" type="text" value="<?php echo get_option("wpss_sr_css_bordercolor");?>" />
			        </td>
			    </tr>
			    <tr>
	                <th width="33%" valign="top" scope="row" align="right"><?php _e('Font Colour:', 'sunpress'); ?> </th>
	                <td>
			            	<input name="wpss_sr_css_fontcolor" id="wpss_sr_css_fontcolor" type="text" value="<?php echo get_option("wpss_sr_css_fontcolor");?>" />
			        </td>
			    </tr>
			     <tr><td></td> 
			        <td>
			        	<input class="button" type="submit" name="savestyling" value="<?php _e('Save Styling &raquo;', 'sunpress'); ?>" />			            		
			        </td></tr>
			 </table>
			</form>
		</div>
		<?php
	}
	
	/**
	 * options page for updating the sunshine content that is within wp.
	 *
	 */
	function content_options_page()
	{
		global $wpdb;
		
		$formaction = "admin.php?page=sunpress-page3";
		if(isset($_POST['updatecontent'])) 
		{
			$this->update_content();
			echo '<div class="updated"><p>' . _c('Content update completed.', 'sunpress') . '</p></div>';
		}
		
		// if the create content button is pressed...
		if(isset($_POST['postcontent'])) 
		{
			if(!empty($_POST["rid"])) 
			{
				if(is_numeric($_POST["rid"]))
					$newid = $this->add_content_post(3,$_POST["rid"]);
				else 
					$newid = $this->add_content_post(2,substr($_POST["rid"],1));
			}
			else if(!empty($_POST["cid"]))
				$newid = $this->add_content_post(1,$_POST["cid"],true);
				
			if($newid!=0)
				echo '<div class="updated"><p>' . _c('New Content Created : <a href="'.get_option('siteurl').'/wp-admin/edit.php">Edit Now</a>', 'sunpress') . '</p></div>';
			else 
				echo '<div class="updated"><p>' . _c('Content update failed.', 'sunpress') . '</p></div>';
		}
		
		$lastupdate = get_option('wpss_last_content_update');
		
		if(empty($this->affiliate_id))
			echo '<div id="update-nag">' . _c('Important : Please set your affiliate ID before adding content. This ensures the pre-filled deeplinks have the correct ID.', 'sunpress') . '</div>';
		
		?>
		<div class="wrap">
			<br />
		 <h2><?php _e('Content Update', 'sunpress'); ?></h2>
		<p>
		   <?php _e('Using this page you can make sure your country/region/resort and hotel information is all up to date and in sync with the latest version from sunshine.co.uk','wp_sunshine'); ?>
		</p>
		 <script>
		 function wpss_updatecontent()
		 {
		 	alert('Please note, this may take a couple of minutes to download and update all the content. Please be patient.');
		 	document.getElementById('updatecontentdiv').style.display='';
		 	document.getElementById('lastupdatep').style.display='none';
		 	
		 	jcall('<?php echo $this->plugin_url."/includes/dbpopulate.php?op=destinations&id=1"; ?>',wpss_updatecontentresult);
		 	jcall('<?php echo $this->plugin_url."/includes/dbpopulate.php?op=hotels&id=2"; ?>',wpss_updatecontentresult);
		 	jcall('<?php echo $this->plugin_url."/includes/dbpopulate.php?op=depairports&id=3"; ?>',wpss_updatecontentresult);
		 	jcall('<?php echo $this->plugin_url."/includes/dbpopulate.php?op=arrairports&id=4"; ?>',wpss_updatecontentresult);
		 	jcall('<?php echo $this->plugin_url."/includes/dbpopulate.php?op=airportresort&id=5"; ?>',wpss_updatecontentresult);
		 	jcall('<?php echo $this->plugin_url."/includes/dbpopulate.php?op=hotelimages&id=6"; ?>',wpss_updatecontentresult);
		 }
		 
		 function wpss_updatecontentresult(jsn)
		 {
		 	var obj = eval('('+jsn+')');
		 	if(obj.passed=='true')
		 		document.getElementById('imgloader'+obj.id).innerHTML='loaded!';
		 	
		 }
		 </script> 
		<p class="submit" style="border:none;padding:0px;" align="left" id="lastupdatep">
			<?php _e('Last Update : '. date("d/m/Y H:i",$lastupdate)); ?><br /> 
			<input type="button" name="updatecontent" onclick="wpss_updatecontent();" value="<?php _e('Update Country/Resort/Hotel Lists &raquo;', 'sunpress'); ?>" />
		</p>	        
		
			<div id="updatecontentdiv" style="display:none;">
			<table>
				<tr><td>Destinations</td><td id="imgloader1"><img src="<?php echo $this->plugin_url;?>/images/aniloader.gif" /></td></tr>
				<tr><td>Hotels</td><td id="imgloader2"><img src="<?php echo $this->plugin_url;?>/images/aniloader.gif" /></td></tr>
				<tr><td>Departure Airports</td><td id="imgloader3"><img src="<?php echo $this->plugin_url;?>/images/aniloader.gif" /></td></tr>
				<tr><td>Arrival Airports</td><td id="imgloader4"><img src="<?php echo $this->plugin_url;?>/images/aniloader.gif" /></td></tr>
				<tr><td>Aiport Resort Mapping</td><td id="imgloader5"><img src="<?php echo $this->plugin_url;?>/images/aniloader.gif" /></td></tr>
				<tr><td>Hotel Images</td><td id="imgloader6"><img src="<?php echo $this->plugin_url;?>/images/aniloader.gif" /></td></tr>
			</table>
			</div>
		</div>
		<div class="wrap">
			<br />
		 <h2>
		 
		 <?php _e('Content Options', 'sunpress'); ?></h2>
	   	 	<form name="wpss_content" method="post" action="<?php echo $formaction;?>">
		     
		
		        <fieldset class="options">
		            <br />
		            <p>
		            <?php _e('Use this form to create posts for your countries/regions/resorts and hotels. Select an item using the dropdown boxes to the level you like, then hit \'Create Content Page\'. This will provide valuable information to your visitors.', 'sunpress'); ?>
		            </p>
					<table width="100%" cellspacing="2" cellpadding="5" class="editform">
		            <tr>
		                <th width="33%" valign="top" scope="row" align="right"><?php _e('Country:', 'sunpress'); ?> </th>
		                <td>
				            	<select name="cid" id="wpss_cid" class="wpss_sel" onchange="getResorts('<?php echo get_option('home');?>',this.value,'','-- All Resorts --');"><option value="0">-- Select Country --</option>
				            		<?php echo countryOptions(''); ?>
				            	</select>
				        </td>
				    </tr>
				    <tr>
				    	<th width="33%" valign="top" scope="row" align="right"><?php _e('Resort:', 'sunpress'); ?> </th>
	                	<td>
			          		<select name="rid" id="wpss_rid" class="wpss_sel"><option value="0">-- All Resorts --</option></select>
			          	</td>
			        </tr>
			        <tr><td></td>
			        <td>
			        	<input class="button" type="submit" onclick="if(document.getElementById('wpss_cid').value==0){alert('Please ensure you have selected a country');return false;} alert('Please note this may take some time. If you experience any problems adding at country level, try adding the individual resorts instead.');" name="postcontent" value="<?php _e('Create Content Page &raquo;', 'sunpress'); ?>" />			            		
			        </td></tr>
			        </table>
			        
		            </div>
		        </fieldset>
		      
	   	 	</form>
	   
	    
	    <div class="wrap">
			<br />
		 <h2><?php _e('Previously Added', 'sunpress'); ?></h2>
	   	    <fieldset class="options">
	   	    <?php
	   	    
	   	    	// find all the content that has been previously mapped/added using the content options form, display
	   	    	// in a list ordered by country then resort.
	   	    	$countries = $wpdb->get_results("SELECT cid,name FROM wpss_post_content_map wpcm INNER JOIN wpss_country wpco ON wpcm.mapid=wpco.cid WHERE wpcm.maptype=1");
	   	    	
	   	    	if(sizeof($countries)>0)
	   	    	{
		   	    	foreach($countries as $coureg)
		   	    	{
		   	    		echo $coureg->name . "<br />";
		   	    		$regions = $wpdb->get_results("SELECT regid,name FROM wpss_post_content_map wpcm
														INNER JOIN wpss_region wpco ON wpcm.mapid=wpco.regid
														WHERE wpcm.maptype=2 and wpco.cid='".$wpdb->escape($coureg->cid)."';");
		   	    		
		   	    		foreach($regions as $reg)
		   	    		{
		   	    			echo "&nbsp;&nbsp;&nbsp;".$reg->name."<br  />";
		   	    			$resorts = $wpdb->get_results("SELECT wpr.rid,wpr.name FROM wpss_post_content_map wpcm
														INNER JOIN wpss_country_region_resort wpcr ON wpcm.mapid=wpcr.rid
														INNER JOIN wpss_resort wpr ON wpcr.rid=wpr.rid
														WHERE wpcm.maptype=3 and wpcr.regid='".$wpdb->escape($reg->regid)."';");
		   	    			foreach($resorts as $res)
		   	    			{
		   	    				echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" .$res->name . "<br />";
		   	    			}
		   	    		}
		   	    		
		   	    		$resorts = $wpdb->get_results("SELECT wpr.rid,wpr.name FROM wpss_post_content_map wpcm
														INNER JOIN wpss_country_region_resort wpcr ON wpcm.mapid=wpcr.rid
														INNER JOIN wpss_resort wpr ON wpcr.rid=wpr.rid
														WHERE wpcm.maptype=3 and wpcr.regid='' and wpcr.cid='".$coureg->cid."';");
	   	    			foreach($resorts as $res)
	   	    			{
	   	    				echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" .$res->name . "<br />";
	   	    			}
		   	    	}
		   	    	
	   	    	}
	   	    	else  
	   	    	{
	   	    		_e('There is currently no content added, please use the drop down boxes above to add some.','sunpress');
	   	    	}
	   	    ?>
		    </fieldset>
		</div>
	    
		<?php
	}
	
	/**
	 * Media functions for adding a sunshine option to the media upload control, then
	 * allowing customers to insert photos of hotels from sunshines content.
	 *
	 */
	function media_buttons() {
	}
	
	/**
	 * Simply adds a sunshine icon/button for activating the hotel photo insert control.
	 *
	 * @param unknown_type $context
	 * @return unknown
	 */
	function media_buttons_context($context) {
		global $post_ID, $temp_ID;
		$dir = dirname(__FILE__);

		$image_btn = get_option('siteurl').'/wp-content/plugins/sunpress/images/icon.gif';
		$image_title = 'sunshine.co.uk images';
		
		$uploading_iframe_ID = (int) (0 == $post_ID ? $temp_ID : $post_ID);

		$media_upload_iframe_src = "media-upload.php?post_id=$uploading_iframe_ID";
		$out = ' <a id="add_image" href="'.$media_upload_iframe_src.'&tab=sunshine&type=sunshine&TB_iframe=1" class="thickbox" title="'.$image_title.'"><img src="'.$image_btn.'" alt="'.$image_title.'" /></a>';
		return $context.$out;
	}
	
	
	function media_upload_content() 
	{
		add_filter('media_upload_tabs', array(&$this, 'media_upload_tabs'));
		wp_enqueue_style('media');
		wp_iframe(array(&$this, 'media_upload_iframe'));
	}
	
	/**
	 * This function handles generation of the form for displaying the hotel images,
	 * and allowing the user to select them, and thus insert them into the post whilst
	 * making a local copy.
	 *
	 */
	function media_upload_iframe()
	{
		global $wpdb;
		
		media_upload_header();
		
		$post_id = $_REQUEST["post_id"];
		$aid = $wpdb->get_var("SELECT mapid FROM wpss_post_content_map WHERE maptype='4' and pid='$post_id'");
		$aname = $wpdb->get_var("SELECT name FROM wpss_accom WHERE aid='$aid'");
		
		if(isset($_POST["insertbutton"]))
		{
			$dir = WP_CONTENT_DIR ."/uploads/hotelimages/";
			
			// ensure upload directory exists
			if (!wp_mkdir_p( $dir ) ) 
			{
				$message = sprintf(_c( 'Unable to create directory %s. Is it\'s parent directory writable by the server? Either way, please can you create a writable folder wp-content/uploads/hotelimages for the images to be stored in.' ), $dir );
				echo $message;
			}
			else 
			{
				$remote = "http://www.sunshine.co.uk/images/hotelimages/".$_POST["pic"].".jpg";
				$localdir = WP_CONTENT_DIR ."/uploads/hotelimages/";
				$new = get_option('siteurl')."/wp-content/uploads/hotelimages/".$_POST["pic"].".jpg";
				
				$successscript = "
				<script>
					var win = window.dialogArguments || opener || parent || top;
					win.send_to_editor('<img alt=\"".addslashes($aname)."\" src=\"$new\" />');
				</script>";
				
				if(copy_file($remote,$localdir))
				{
					echo "File Saved successfully.$successscript";
				}
				else 
					echo "File failed to save";
			}
				
		}
		
		$form_action_url = "media-upload.php?post_id=$post_id&tab=sunshine&type=sunshine&TB_iframe=1";

		?> 
		<div style="padding:15px;">
			<input type="hidden" name="post_id" id="post_id" value="<?php echo (int) $post_id; ?>" />
			<input type="hidden" name="aid" id="aid" value="<?php echo (int) $aid; ?>" />
			<h3><?php _e($aname); ?></h3>
			<table><tr>
			<?php
			
			// find all the hotel pictures associated to that id
			$pics = $wpdb->get_var("SELECT pics FROM wpss_accom_pics WHERE aid='$aid'");
			
			if(!empty($pics))
			{
				$pics = explode(",",$pics);
				$i=0;
				foreach($pics as $pic)
				{
					if(($i++)%4==0)	echo "</tr><tr>";
					?>
						<td><form action="<?php echo $form_action_url;?>" method="post">
							<img src="http://www.sunshine.co.uk/images/hotelimages/<?php echo "$aid-$pic.jpg";?>" />
							<input type="hidden" name="pic" value="<?php echo "$aid-$pic";?>" /><br />
							<input class="button" type="submit" name="insertbutton" value="Insert into Post" />
						</form>
						</td>
					
					<?php
				}
			}
			
			?></tr></table>
			
		</div>
		<?php				
		
	}
		
	function media_upload_tabs($tabs) 
	{
		return array(
			'sunshine' => _c('Hotel Photos') // handler action suffix => tab text
		);
	}
 
 } // Class WP_Sunshine


/**
 * This function sets up the required structure in the database, once finished it populates itself
 * the sunshine.co.uk webservices.
 *
 */ 
function wpss_install()
{
	global $wpdb;
	
	$plugindir = WP_CONTENT_DIR.'/plugins/'.plugin_basename(dirname(__FILE__));
	if($wpdb->get_var("SHOW TABLES LIKE 'wpss_accom'") != "wpss_accom")
	{
		// sql code to create the necessary plugin tables
		if(file_exists("$plugindir/includes/dbsetup.php")) 
		{
			include("$plugindir/includes/dbsetup.php");
		}
		
			// code to populate the db tables
			if(file_exists("$plugindir/includes/dbpopulate.php")) 
			{
				include("$plugindir/includes/dbpopulate.php");
				update_option('wpss_last_content_update', time());
			}

	}
	
	
	// save some initial styling options
	update_option('wpss_sb_css_bgcolor', '#CCCCCC');
	update_option('wpss_sb_css_bordercolor', '#AAAAAA');
	update_option('wpss_sb_css_fontcolor', '#FFFFFF');
	update_option('wpss_sb_css_fontsize', '11');
	update_option('wpss_sb_css_font', 'Verdana, Arial, Helvetica, sans-serif');
	
	// save some initial styling options
	update_option('wpss_sr_css_bgcolor', '#CCCCCC');
	update_option('wpss_sr_css_bordercolor', '#AAAAAA');
	update_option('wpss_sr_css_fontcolor', '#FFFFFF');
	
	// disabled the content tab by default
	update_option('wpss_enable_content', 0);
}


register_activation_hook( __FILE__, 'wpss_install');

// Add actions to call the function
add_action('plugins_loaded', create_function('$a', 'global $wp_sunshine; $wp_sunshine = new WP_Sunshine();'));
add_action('widgets_init',array(&$wp_sunshine, 'widget_init'));
add_action('wp_head', array(&$wp_sunshine, 'add_head'));

// Admin menus
add_action('admin_head', array(&$wp_sunshine, 'add_head'));
add_action('admin_menu', array(&$wp_sunshine, 'admin_options_menu'));

// Hooks to handle mapping
add_action('delete_category', array(&$wp_sunshine, 'delete_category'));
add_action('delete_term', array(&$wp_sunshine, 'delete_term'));
add_action('delete_post', array(&$wp_sunshine, 'delete_post'));

// Hook to ensure postmeta is output, remove if not needed.
//add_filter('the_content', array(&$wp_sunshine, 'add_meta'));

// Hooks to add the hotel images insert button to the media control
add_action('media_buttons', array(&$wp_sunshine, 'media_buttons')); 
add_filter('media_buttons_context', array(&$wp_sunshine, 'media_buttons_context'));
add_action('media_upload_sunshine', array(&$wp_sunshine, 'media_upload_content'));



// handle redirects within wordpress depending on button activated
if(isset($_REQUEST['ss_searchbutton']) || isset($_REQUEST['wpss_button']))
	add_action('template_redirect', array(&$wp_sunshine,'search_results'));
	
if(isset($_REQUEST['book']) && !empty($_POST["qid"]))
	add_action('template_redirect', array(&$wp_sunshine,'redirect'));
	
if(isset($_REQUEST['wpss_redir']))
	add_action('template_redirect', array(&$wp_sunshine,'redirect_url'));


?>

<?
header("Content-type: text/plain");
header("Cache-Control: no-cache, must-revalidate");
header("Expires:-1");
chdir("../../../../");
include_once('wp-config.php');
include_once('wp-includes/wp-db.php');

/**
 * this function returns the resorts for a particular country
 * in an xml format similar to response->resorts->resort
 *
 * @param string $cid
 * @param string $rid
 * @param string $def
 * @return Resort list for the specified country/region in json format
 */
function getResorts($cid,$rid,$def)
{
	global $wpdb;

	$json="";
	
	if(empty($def))
		$def = "Choose Resort";
	// if the country ID selected is 0, this means any country. Rather than listing all resorts
	// at this moment we just default it to any resort.
	if($cid=="0")
		$json .= '{resorts:[],def:"'.$def.'"}';
	else
	{
		// get the resorts for a specific country (cid)
		if(is_numeric($cid))
			$result = $wpdb->get_results("SELECT fr.rid, fr.name, fcsr.regid, freg.name as regionname FROM wpss_resort fr INNER JOIN wpss_country_region_resort fcsr ON fr.rid = fcsr.rid LEFT JOIN wpss_region freg ON fcsr.regid=freg.regid WHERE fcsr.cid='".$cid."' ORDER BY freg.name ASC, fr.name ASC");
		else
			$result = $wpdb->get_results("SELECT fr.rid, fr.name, fcsr.regid, '' as regionname FROM wpss_resort fr INNER JOIN wpss_country_region_resort fcsr ON fr.rid = fcsr.rid INNER JOIN wpss_region freg ON fcsr.regid=freg.regid WHERE fcsr.regid='".substr($cid,1,strlen($cid)-1)."' AND fr.active='1' ORDER BY freg.name ASC, fr.name ASC");
			
		if($result)
		{
			$resorts = array();
			foreach($result as $row)
			{
				$resort="";
				$append="";
				$append2="";
				if($row->villas==1)
				{
					$append = " Villas";
					$append2 = "v";
				}
				$resort .=  "{\"val\":\"$append2" . $row->rid . "\",\"res\":\"" . $row->name . "$append\",";
				$resort .=  "\"reg\":\"" . $row->regionname . "\",\"regval\":\"" . $row->regid . "\",";
				$resort .= "\"sel\":".($rid==$append2.$row->rid?1:0)."}";
				$resorts[] = $resort;
			}
			
			if($rid[0]=='r')
					$append = ',reg:"'.substr($rid,1).'"';

			$json .=  '{resorts:['.implode(",",$resorts).'],def:"'.$def.'"'.$append.'}';
		}
		else
			$json .= '{resorts:[],def:"'.$def.'"}';
	}

	return $json;
}



/**
 * this function returns the hotels that are active in a particular resort
 * specified by rid
 *
 * @param integer $rid Resort to select accommodation names from
 * @param integer $aid Accommodation to be selected in list, if supplied
 */
function getHotels($rid,$aid='')
{
	global $wpdb, $prefix;
	
	$json = "";
	
	// if the resort ID selected is 0, this means any country. Rather than listing all resorts
	// at this moment we just default it to any resort.
	if($rid=="0"||empty($rid))
		$json = "{}";
	else
	{
		// get the hotels for a specific resort(rid) or if the rid is prefixed with 'r' get
		// hotels for an entire region.
		if(is_numeric($rid))
			$result = $wpdb->get_results("SELECT DISTINCT fa.aid, name FROM wpss_accom fa WHERE fa.rid='".$rid."' ORDER BY name ASC");
		else if($rid[0]=='r')
			$result = $wpdb->get_results("SELECT DISTINCT fa.aid, name FROM wpss_accom fa INNER JOIN wpss_country_region_resort fcrr ON fcrr.rid=fa.rid WHERE fcrr.regid='".substr($rid,1,strlen($rid)-1)."' ORDER BY name ASC");
		else
			$json = "{}";
			
		if($result)
		{
			$json="{listid:\"wpss_aid\",items:[";
			$accoms = array("{\"val\":\"0\",\"data\":\"All Accommodations\",\"sel\":0}");
			foreach($result as $row)
			{
				$accoms[] = "{\"val\":\"" . $row->aid . "\",\"data\":\"" . htmlspecialchars($row->name) . "\",\"sel\":".($aid==$row->aid?1:0)."}";
			}
			$json .= @implode(",",$accoms)."]}";
		}
	}
	
	return $json;

}

/**
 * this function returns the departure/arrival airports from within/recahable from the UK,
 * if a code is specified, it is marked as selected.  The airport information is
 * converted into JSON format and returned
 *
 * @param integer $rid Resort to select accommodation names from
 * @param integer $aid Accommodation to be selected in list, if supplied
 */
function getAirports($code,$arrival)
{
	global $wpdb, $prefix;
	
	$json = "";
	
	// Retrieve countries for the list setting the selected country if one is available
	$result = $wpdb->get_results("SELECT code,name FROM wpss_airports WHERE arrival=$arrival ORDER BY name ASC");
	
	if($result)
	{
		$json = "{listid:\"".(($arrival==0)?"wpss_depairp":"wpss_arrairp")."\",items:[";
		$airports = array("{\"val\":\"0\",\"data\":\"".$row->name."Choose ".(($arrival==0)?"Departing":"Arrival")." Airport\",\"sel\":\"0\"}");
		foreach($result as $row)
		{
			$airports[] = "{\"val\":\"".$row->code."\",\"data\":\"".$row->name."\",\"sel\":\"".($row->code==$code?1:0)."\"}";
		}
		$json .= @implode(",",$airports)."]}";
	}
	
	
	return $json;

}


switch($_GET["op"])
{
	case "getresorts":
		echo getResorts($_GET["cid"],$_GET["rid"],$_GET["def"]);
		break;
		
	case "gethotels":
		echo getHotels($_GET["rid"],$_GET["aid"]);
		break;
		
	case "getairports":
		echo getAirports($_GET["code"],$_GET["arrival"]);
		break;
}


?>
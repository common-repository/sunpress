<?php
/**
 * This file can be called as a include or as part of a json call. If it's a json call
 * it is outwith wordpress' container, so the following files must be included to enable
 * database interaction
 */
if(!empty($_GET["id"]))
{
	include_once('../../../../wp-load.php');
	include_once('../../../../wp-admin/includes/admin.php');
	include_once('../../../../wp-includes/class-snoopy.php');
}
else 
	include_once(ABSPATH.'/wp-includes/class-snoopy.php');
	

global $wpdb;

$wpdb->show_errors(true);

function getCSVValues($string, $separator=",")
{
    $elements = explode($separator, $string);
    for ($i = 0; $i < sizeof($elements); $i++) {
        $nquotes = substr_count($elements[$i], '"');
        if ($nquotes %2 == 1) {
            for ($j = $i+1; $j < sizeof($elements); $j++) {
                if (substr_count($elements[$j], '"') %2 == 1) { // Look for an odd-number of quotes
                    // Put the quoted string's pieces back together again
                    array_splice($elements, $i, $j-$i+1,
                        implode($separator, array_slice($elements, $i, $j-$i+1)));
                    break;
                }
            }
        }
        if ($nquotes > 0) {
            // Remove first and last quotes, then merge pairs of quotes
            $qstr =& $elements[$i];
            $qstr = substr_replace($qstr, '', strpos($qstr, '"'), 1);
            $qstr = substr_replace($qstr, '', strrpos($qstr, '"'), 1);
            $qstr = str_replace('""', '"', $qstr);
        }
    }
    return $elements;
}

function destinationsUpload($id='')
{
	global $wpdb;
	
	// update all destinations using the destinations CSV file.
	$snoopy = new Snoopy();
	$snoopy->fetch("http://www.sunshine.co.uk/affiliates/lists/mostrecent/ssdestinations.csv");
	
	$rows = explode("\r\n",$snoopy->results);
	$lastcountry=$lastregion='';
	if($rows)
	{		
		$row = 1;
		foreach($rows as $data) 
		{			
			// skip header rows
			if(($row++)==1)
				continue;
			
			$data = getCSVValues($data);	
		    $num = sizeof($data);
		    
		    // ensure file is in correct layout
		    if($num==9)
		    {
			    // check to see if we have encountered a new country, if so try and insert it
			    if($lastcountry!=$data[0])
			    	$countries[] = "('".$wpdb->escape($data[2])."','".$wpdb->escape($data[0])."','http://rss.sunshine.co.uk/hotels/".$wpdb->escape(str_replace(" ","_",$data[0]))."-Cheap-Hotels-".$wpdb->escape($data[2]).".xml')";
			    
			    // check to see if we have encountered a new region, if so try and insert it
			    if($lastregion!=$data[5] && !empty($data[5]))
			    	$regions[] = "('".substr($wpdb->escape($data[5]),1)."','".$wpdb->escape($data[3])."','".$wpdb->escape($data[2])."','http://rss.sunshine.co.uk/hotels/".$wpdb->escape(str_replace(" ","_",$data[3]))."-Cheap-Holidays-".substr($wpdb->escape($data[5]),1).".xml')";
			    
			    // insert new resort 
			    $sresorts[] = "('".$wpdb->escape($data[8])."','".$wpdb->escape($data[6])."','http://rss.sunshine.co.uk/hotels/".$wpdb->escape(str_replace(" ","_",$data[6]))."-Hotels-".$wpdb->escape($data[8]).".xml')";
			    
			    
			    // insert mapping between country/region/resort
			    $mappings[] = "('".$wpdb->escape($data[2])."','".substr($wpdb->escape($data[5]),1)."','".$wpdb->escape($data[8])."')";
			    
			    $lastcountry = $data[0];
			    $lastregion = $data[5];
			    
			    // store resort info for next query
			    $resorts[$data[0]][$data[6]] = $data[8];
			    
		    }
		}
	}
	
	// bulk insert
	if($countries>0)
		$wpdb->query("INSERT INTO wpss_country (cid,name,offers) VALUES ".implode(",",$countries)." ON DUPLICATE KEY UPDATE name=VALUES(name), offers=VALUES(offers);");	
	if($regions>0)
		$wpdb->query("INSERT INTO wpss_region (regid,name,cid,offers) VALUES ".implode(",",$regions)." ON DUPLICATE KEY UPDATE name=VALUES(name), cid=VALUES(cid), offers=VALUES(offers);");
	if($sresorts>0)
		$wpdb->query("INSERT INTO wpss_resort (rid,name,offers) VALUES ".implode(",",$sresorts)." ON DUPLICATE KEY UPDATE name=VALUES(name), offers=VALUES(offers);");
	if($mappings>0)
		$wpdb->query("INSERT INTO wpss_country_region_resort (cid, regid, rid) VALUES ".implode(",",$mappings)." ON DUPLICATE KEY UPDATE rid=VALUES(rid);");
		
	
	// loop through each hotel file
	foreach(range('A','Z') as $chr)
	{
		//update all hotels using the hotel CSV file.
		$snoopy->fetch("http://www.sunshine.co.uk/affiliates/lists/mostrecent/sshotels{$chr}.csv");
		
		$rows = explode("\r\n",$snoopy->results);
		
		$hotels = array();
		if($rows)
		{
			$row = 1;
			
			foreach($rows as $data) 
			{
				// skip header rows
				if(($row++)==1)
					continue;
				
					
				$data = getCSVValues($data);	
			    $num = sizeof($data);
			    
			   
			    // ensure file is in correct layout
			    if($num==9)
			    {
			    	$hotels[] = "('".$wpdb->escape($data[0])."','".$wpdb->escape($resorts[$data[3]][$data[5]])."','".$wpdb->escape($data[1])."','".$wpdb->escape($data[6])."')";
			    }
			}
			
			
			// bulk insert
			if(sizeof($hotels)>0)
				$wpdb->query("INSERT INTO wpss_accom (aid,rid,name,stars) VALUES ".implode(",",$hotels)." ON DUPLICATE KEY UPDATE name=VALUES(name), rid=VALUES(rid), stars=VALUES(stars);");
		}
	}
	
	
	if(!empty($id))
		echo '{passed:"true",id:"'.$id.'"}';
}

function hotelUpload($id='')
{
	global $wpdb;
	
	/*//update all hotels using the hotel CSV file.
	$snoopy = new Snoopy();
		$snoopy->fetch("http://www.sunshine.co.uk/affiliates/lists/mostrecent/sshotels.csv");
	$rows = explode("\r\n",$snoopy->results);
	
	if($rows)
	{
		$row = 1;
		foreach($rows as $data) 
		{
			// skip header rows
			if(($row++)==1)
				continue;
			
			$data = getCSVValues($data);	
		    $num = sizeof($data);
		    
		    // ensure file is in correct layout
		    if($num==9)
		    {
		    	$hotels[] = "('".$wpdb->escape($data[0])."','".$wpdb->escape($resorts[$data[5]])."','".$wpdb->escape($data[1])."','".$wpdb->escape($data[6])."')";
		    }
		}
		
		// bulk insert
		if(sizeof($hotels)>0)
			$wpdb->query("INSERT INTO wpss_accom (aid,rid,name,stars) VALUES ".implode(",",$hotels)." ON DUPLICATE KEY UPDATE name=VALUES(name), rid=VALUES(rid), stars=VALUES(stars);");
	}
	*/
	
	if(!empty($id))
		echo '{passed:"true",id:"'.$id.'"}';
}

function departureAirportsUpload($id='')
{
	global $wpdb;
	
	//update all hotels using the hotel CSV file.
	$snoopy = new Snoopy();
	$snoopy->fetch("http://www.sunshine.co.uk/affiliates/lists/mostrecent/depairports.csv");	
	
	$rows = explode("\r\n",$snoopy->results);
	$airports = array();
	
	if($rows)
	{
		// quick sanity check to ensure we have actually airports to replace 
		if(sizeof($rows)>10)
		{
			$wpdb->query("DELETE FROM wpss_airports;");
		}
		
		$row = 1;
		
		foreach($rows as $data) 
		{			
			// skip header rows
			if(($row++)==1)
				continue;
			
			$data = getCSVValues($data);	
		    $num = sizeof($data);
		    
		    // ensure file is in correct layout
		    if($num==2)
		    {
		    	$airports[] = "('".$wpdb->escape($data[1])."','".$wpdb->escape($data[0])."','0')";
		    }
		}
		
		// bulk insert
		if(sizeof($airports)>0)
			$wpdb->query("INSERT INTO wpss_airports (code,name,arrival) VALUES ".implode(",",$airports)." ON DUPLICATE KEY UPDATE name=VALUES(name), arrival=VALUES(arrival);");
	}
	
	
	if(!empty($id))
		echo '{passed:"true",id:"'.$id.'"}';
}

function arrivalAirportsUpload($id='')
{
	global $wpdb;
	
	//update all hotels using the hotel CSV file.
	$snoopy = new Snoopy();
	$snoopy->fetch("http://www.sunshine.co.uk/affiliates/lists/mostrecent/arrairports.csv");
	
	$rows = explode("\r\n",$snoopy->results);
	$airports = array();
	
	if($rows)
	{
		$row = 1;
		foreach($rows as $data) 
		{
			// skip header rows
			if(($row++)==1)
				continue;
			
			$data = getCSVValues($data);
		    $num = sizeof($data);
		    
		    // ensure file is in correct layout
		    if($num==2)
			    $airports[] = "('".$wpdb->escape($data[1])."','".$wpdb->escape($data[0])."','1')";
		}
		
		// bulk insert
		if(sizeof($airports)>0)
			$wpdb->query("INSERT INTO wpss_airports (code,name,arrival) VALUES ".implode(",",$airports)." ON DUPLICATE KEY UPDATE name=VALUES(name), arrival=VALUES(arrival);");
	}
	
	if(!empty($id))
		echo '{passed:"true",id:"'.$id.'"}';

}

function airportResortsUpload($id='')
{
	global $wpdb;
	
	// store a list of resort to airport mappings
	$snoopy = new Snoopy();
	$snoopy->fetch("http://www.sunshine.co.uk/affiliates/lists/mostrecent/resortairports.csv","temp$id.csv");	
	
	$rows = explode("\r\n",$snoopy->results);
	$mappings = array();
	
	if($rows)
	{
		$row = 1;
		foreach($rows as $data) 
		{
			// skip header rows
			if(($row++)==1)
				continue;
				
			$data = getCSVValues($data);
		    $num = sizeof($data);
		    
		    // ensure file is in correct layout
		    if($num==2)
		       	$mappings[] = "('".$wpdb->escape($data[0])."','".$wpdb->escape($data[1])."')";
		    
		}

		// bulk insert
		if(sizeof($mappings)>0)
			$wpdb->query("INSERT INTO wpss_resort_airports (rid,code) VALUES ".implode(",",$mappings)." ON DUPLICATE KEY UPDATE code=VALUES(code);");
	}
	
	
	if(!empty($id))
		echo '{passed:"true",id:"'.$id.'"}';

}

function hotelImagesUpload($id='')
{
	global $wpdb;
	
	// store a list of images for each hotel
	$snoopy = new Snoopy();
	$snoopy->fetch("http://www.sunshine.co.uk/affiliates/lists/mostrecent/sshotelimages.csv");
	
	$rows = explode("\r\n",$snoopy->results);
	$pics = array();
		
	if($rows)
	{
		$row = 1;
		foreach($rows as $data) 
		{
			// skip header rows
			if(($row++)==1)
				continue;
			
			$data = getCSVValues($data);		
		    $num = sizeof($data);
		    
		    // ensure file is in correct layout
		    if($num==2)
		    {
		    	$pics[] = "('".$wpdb->escape($data[0])."','".$wpdb->escape($data[1])."')";
		    }
		}
		
		// bulk insert
		if(sizeof($pics)>0)
		{
			$wpdb->query("INSERT INTO wpss_accom_pics (aid,pics) VALUES ".implode(",",$pics)." ON DUPLICATE KEY UPDATE pics=VALUES(pics);");
		}
	}
	
	
	if(!empty($id))
  		echo '{passed:"true",id:"'.$id.'"}';

}
	
	

switch($_GET["op"])
{
	case "destinations":
		destinationsUpload($_GET["id"]);
		break;
		
	case "hotels":
		hotelUpload($_GET["id"]);
		break;
		
	case "depairports":
		departureAirportsUpload($_GET["id"]);
		break;
		
	case "arrairports":
		arrivalAirportsUpload($_GET["id"]);
		break;
		
	case "airportresort":
		airportResortsUpload($_GET["id"]);
		break;
		
	case "hotelimages":
		hotelImagesUpload($_GET["id"]);
		break;
		
	default:
		destinationsUpload();
		hotelUpload();
		departureAirportsUpload();
		arrivalAirportsUpload();
		airportResortsUpload();
		hotelImagesUpload();
}

update_option('wpss_last_content_update', time());
?>

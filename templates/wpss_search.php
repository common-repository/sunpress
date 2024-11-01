<?php 


/**
 * HotelSearch performs the hotel search using sunshine.co.uk's web services.
 * Parameters are supplied via the $_POST collection. If there are available
 * results, they are returned to the user, ordered by cheapest hotel first.
 *
 */
function HotelSearch()
{
	global $wpdb,$wp_sunshine;
	
	// extract search values from POST collection
	$day = $_POST["depdate"];
	$monthyear = explode("-",$_POST["depmonth"]);
	$duration = $_POST["duration"];
	$rid = $_POST["rid"];
	$cid = $_POST["cid"];
	$aid = $_POST["aid"];
	$rooms = $_POST["rooms"];
	$adults = $_POST["adults"];
	$children = $_POST["children"];
	$ages = $_POST["age"];
	
	$depukdate = strtotime($monthyear[1]."-".$monthyear[0]."-".$day . " 12:00:00");
	$depabroaddate = $depukdate + ($duration*86400);
	
	// basic error checking
	$failon = "";
	if(empty($day) || empty($monthyear))
		$failon = "date";
	
	if(empty($duration))
		$failon = "duration";
			
	if(empty($rid) || empty($cid))
		$failon = "destination";
		
	if(!empty($failon))
	{
		echo "<div class=\"updated\" style=\"padding-top:10px;\"><p><strong>Please ensure you have selected a valid $failon from the menu</strong></p></div>";
		return;
	}
	
	// put adults and children into correct format for search;
	$adultsearch = array();
	$childsearch = array();
	
	$roomsearch = array();
	$id=1;
	for($i=0;$i<$rooms;$i++)
	{
		// add each adult to a room
		for($j=0;$j<$adults[$i];$j++)
			$adultsearch[] = array('Id'=>($id++));
						
		for($j=0;$j<$children[$i];$j++)
			$childsearch[] = array('Id'=>($id++),'Age'=>$ages[$i+1][$j+1]);
		
		// add the pax to the rooms
		$roomsearch[] = array('Adults'=>$adultsearch,'Children'=>$childsearch);
		
		// clear temp arrays
		$adultsearch = array();
		$childsearch = array();
	}
	
	
	// check if a region has been selected from resort drop down.
	if($rid[0]=='r')
	{
		$regid=substr($rid,1);
		$rid='';
		// retrieve resort name to display with the search parameters
		$resortname = $wpdb->get_var("SELECT name FROM wpss_region WHERE regid='".$wpdb->escape($regid)."' LIMIT 1;");				
	}
	else 
	{
		$regid=0;
		// retrieve resort name to display with the search parameters
		$resortname = $wpdb->get_var("SELECT name FROM wpss_resort WHERE rid='".$wpdb->escape($rid)."' LIMIT 1;");				
	}
	
	
	// output loading image
	echo "<div id=\"imageloader\" align=\"center\"><img src=\"". get_bloginfo('url') ."/wp-content/plugins/sunpress/images/aniloader.gif\" /></div>";
	
	// flush out the loading image, so visitors realise the site is doing something during search times.
	ob_flush();
	flush();
	
	echo "<script>document.getElementById('imageloader').style.display='none';</script>";
	
	// output search parameters
	echo "<div class=\"wpss_notice\">".date("d-m-Y",$depukdate)." &gt; ".$duration." nts &gt; ".$resortname." &gt; Ads:".@array_sum($adults)." Ch:".@array_sum($children)." &gt;</div>";
	
	// require nusoap, could use built in php soapclient (php>5) if available, but
	// many php installations have it enabled by default
	require_once("wp-content/plugins/sunpress/includes/nusoap.php");
	
	// Start the search
	$client = new nusoap_client("http://ws.sunshine.co.uk/xml/api/sunshine3.php?wsdl",true,false,false,false,false,120,120);
	
	// Create the proxy
	$proxy = $client->getProxy();
		
	// Perform the search
	$results = $proxy->HotelSearch(array("UserId"=>$wp_sunshine->user_id,"Password"=>md5($wp_sunshine->password)),date("Y-m-d",$depukdate),date("Y-m-d",$depabroaddate),$regid,$rid,'',$roomsearch);

	// useful for debugging
	//print_r($proxy->request); 
	//print_r($proxy->response);   
	
	// if the search yeilds results, output them
	if(!empty($results["faultstring"]))
		echo "<p>".$results["faultstring"]."</p>";
	else if(sizeof($results)>0)
	{
		$i=0;
		
		$mainhotel = array();
		$otherhotels = array();
		
		foreach($results as $hotel)
		{
			if(!empty($aid) && $aid == $hotel["HotelId"])
				$mainhotel = $hotel;
			else 
				$otherhotels[] = $hotel;
		}

		// add hotel to start of array
		if(isset($mainhotel["HotelId"]))
		{
			array_unshift($otherhotels,$mainhotel);	
		}
		
		$first = true;
		
		foreach($otherhotels as $hotel)
		{
			if($first && !empty($aid) && $aid == $hotel["HotelId"])
			{
				echo "<div class=\"wpss_notice\">Your chosen hotel :</div>";
			}
			else if($first && !empty($aid))
			{
				echo "<div class=\"wpss_notice\">Your chosen hotel is unavailable, please see alternative hotels below :</div>";
			}
			
			/*$live = false;
			
			$wpid = get_post_id_from_aid($hotel["HotelId"]);
			
			if($wpid!=-1)
				$live = get_post_live($wpid);*/
			
			echo "<div class=\"wpss_hotel\">
					<div class=\"title\"><a target=\"_blank\" href=\"".wpss_affiliate_link($hotel["ResortLink"])."\">".$hotel["ResortName"]."</a> &#xbb; <a target=\"_blank\" href=\"".wpss_affiliate_link($hotel["Link"])."\">".$hotel["HotelName"]."</a></div>
	
					<div class=\"desc\">

	<div class=\"pic\"><a target=\"_blank\" href=\"".wpss_affiliate_link($hotel["Link"])."\"><img width=\"100\" border=\"0\" src=\"".$hotel["Picture"]."\" /></a></div>

					<div class=\"stars\">".starRating($hotel["Stars"])."</div>
					<p>".$hotel["Description"]."</p>
					</div>

					<div class=\"wpss_sp\"></div>

					<div class=\"title\"><strong>".$hotel["HotelName"]." Availability</strong></div>

		        	";						
						$s=0;	
						foreach($hotel["Rates"] as $rate)
						{
								echo "<div".(($s!=0)?" class=\"book\"":"").">									
										<div class=\"board\"><span>".$rate["RoomType"].", ".$rate["MealType"]."</span></div>
										<div class=\"price\"><span>&pound;".number_format($rate["Price"],2,".","")."</span></div>
										<div class=\"option\"><span>
											<form action=\"\" method=\"post\">
												<input name=\"hotelsearch\" type=\"hidden\" value='$aid|$rid|$cid|$depukdate|$duration|$rooms|".serialize($adults)."|".serialize($children)."|".serialize($ages)."|".$regid."' />
												<input name=\"hotelsummary\" value=\"".$hotel["HotelId"]."|".$hotel["ResortId"]."|".$hotel["ResortName"]."|".$hotel["HotelName"]."|".$rate["RoomType"]."|".$rate["MealType"]."|".$rate["Price"]."\" type=\"hidden\" />
												<input name=\"qid\" value=\"".$rate["QuoteId"]."\" type=\"hidden\">
												<input name=\"sbtype\" value=\"6\" type=\"hidden\" />
												<button type=\"submit\" name=\"wpss_button\" value=\"Book\">Select</button>
											</form></span>
										</div>							
									</div>
									<div class=\"wpss_sp\"></div>";
							$s=1;
						}
				
					echo "
				</div>";
					
			if($first && !empty($aid) && $aid == $hotel["HotelId"])
			{
				echo "<div class=\"wpss_notice\">Other hotels available in your chosen resort :</div>";
			}
			
			$first = false;
		}
	}
	
		
	
}

/**
 * FlightSearch performs the flight search using sunshine.co.uk's web services.
 * Parameters are supplied via the $_POST collection. If there are available
 * results, they are returned to the user, ordered by cheapest flight on the
 * day first, then a list of alternatives +/- 3 days of the searched date.
 *
 */
function FlightSearch()
{
	global $wpdb,$wp_sunshine;
	
	// extract search values from POST collection
	$day = $_POST["depdate"];
	$monthyear = explode("-",$_POST["depmonth"]);
	$duration = $_POST["duration"];
	
	$depukdate = strtotime($monthyear[1]."-".$monthyear[0]."-".$day . " 12:00:00");
	$depabroaddate = $depukdate + ($duration*86400);
	
	$depairp = $_POST["depairp"];
	$arrairp = $_POST["arrairp"];
	
	$adults = $_POST["adultsf"];
	$children = $_POST["childrenf"];
	$ages = $_POST["age"];
	$rooms = 1;
	
	// set dates for flight table
	$centreday = (empty($_POST["centreday"]))?date("Y-m-d",$depukdate):$_POST["centreday"];
	$curday = date("Y-m-d",$depukdate);	
	
	// basic error checking
	$failon = "";
	if(empty($day) || empty($monthyear))
		$failon = "date";
	
	if(empty($duration))
		$failon = "duration";
			
	if(empty($_POST["depairp"]) || empty($_POST["arrairp"]))
		$failon = "airport";
		
	if(!empty($failon))
	{
		echo "<div class=\"updated\" style=\"padding-top:10px;\"><p><strong>Please ensure you have selected a valid $failon from the menu</strong></p></div>";
		return;
	}
	
	// put adults and children into correct format for search;
	$id=1;
	$adultcount=$childcount=$infantcount=0;
	for($i=0;$i<$rooms;$i++)
	{
		$adultcount+=$adults[$i];
		
		if(is_array($ages[$i+1]))
		{
			foreach($ages[$i+1] as $key => $chage)
			{
				if(empty($chage)) // child age cannot be empty, so force to maximum
				{
					$ages[$i+1][$key] = 12;
					$childcount+=1;
				}
				else if($chage>=2) // if age >= 2, then they are regarded as a child
					$childcount+=1;
				else
					$infantcount+=1; // if age<2, child is an infant
			}
		}
	}
	
	// output loading image
	echo "<div id=\"imageloader\" align=\"center\"><img src=\"". get_bloginfo('url') ."/wp-content/plugins/sunpress/images/aniloader.gif\" /></div>";
	
	// flush out the loading image, so visitors realise the site is doing something during search times.
	ob_flush();
	flush();
	
	echo "<script>document.getElementById('imageloader').style.display='none';</script>";
	
	// ensure we have the soap
	require_once("wp-content/plugins/sunpress/includes/nusoap.php");
	
	// setup the soap class
	$client = new nusoap_client("http://ws.sunshine.co.uk/xml/api/sunshine3.php?wsdl",true,false,false,false,false,120,120);
	
	// create the proxy so we can call the webservice by its function name
	$proxy = $client->getProxy();
	
	// perform the search 
	$results = $proxy->FlightSearch(array("UserId"=>$wp_sunshine->user_id,"Password"=>md5($wp_sunshine->password)),
											$centreday,$duration,@implode("|",$depairp),$arrairp,$adultcount,$childcount,$infantcount);
	
	// useful for debugging
	// print_r($proxy->request); 
	// print_r($proxy->response);  
	
		// output some basic search info
		echo "<div class=\"wpss_notice\">".$curday." &gt; ".$duration." nts &gt; Ads:".$adultcount." Ch:".($childcount+$infantcount)." &gt;  ".@implode(" or ",$depairp)." to $arrairp</div>";										
		
		echo "<div class=\"wpss_flight\">\n";

		// check results for error, if none and at least 1 offer, output results
		if(!empty($results["faultstring"]))
			echo "<p>".$results["faultstring"]."</p>";
		else if(sizeof($results)>0)
		{
			// parse results into a flight table format
			$results = parseFlightResults(strtotime($centreday." 12:00:00"),$results);
			
			$i=0;
			echo "<table id=\"wpss_flights_table_head\" width=\"100%\" border=\"0\">
					<tr align=\"center\" valign=\"top\"><td align=\"right\" class=\"key\">Date<br />Flights<br />From</td>\n";
			foreach($results[1] as $key=>$day)
			{
				// if there is more than 1 result for a day, and it's not the searched day,
				// output links to change to that day.
				if($day["size"]>0 && $key!=$curday)
				{
					$date = explode("-",$key);
					echo "<td>
							<a href=\"#\" onclick=\"changeDate('".$key."','".$centreday."');\">".$day["day"] . "</a><br />
							<a href=\"#\" onclick=\"changeDate('".$key."','".$centreday."');\">" . $day["size"]."</a><br />
							<a href=\"#\" onclick=\"changeDate('".$key."','".$centreday."');\">" . (($day["cheapestprice"]>0)?"&pound;".$day["cheapestprice"]:"")."</a>
					</td>\n";
				}
				else 
				{
					echo "<td ".($key==$curday?"class=\"highlight\"":"").">
							".$day["day"] . "<br />
							" . $day["size"]."<br />
							" . (($day["cheapestprice"]>0)?"&pound;".$day["cheapestprice"]:"")."
					</td>\n";
				}
				
			}
			echo "</tr></table>\n<table id=\"wpss_flights_table\" width=\"100%\" border=\"0\">\n";
			$border=0;
			// output the searched days flight results.
			foreach($results[0][$curday] as $key=> $flight)
			{
				echo "<tr><td";
				
				if($border==0){
					echo " style=\"border-top:0px;\"";
					$border=1;
				}
						echo">
						<div class=\"book\">
						

							<div class=\"fdetails\"><span>
							<strong title=\"".$flight['!DepUKAirport']."\">".$flight['!DepUKAirportName']."</strong> to <strong title=\"".$flight['!ArrAbroadAirport']."\">".$flight['!ArrAbroadAirportName']."</strong> - ".$flight['!OutboundAirlineName']."<br />
							Out: ".$flight['!DepUKDate']." ".$flight['!DepUKTime']."<br />&nbsp;&nbsp;In: " .$flight['!DepAbroadDate']." ".$flight['!DepAbroadTime']."
								</span>	
							</div>


							<div class=\"price\"><span>&pound;".number_format($flight['!TotalPrice'],2,".","")."</span></div>

							<div class=\"option\"><span><form action=\"\" method=\"post\">
							<input name=\"hotelsearch\" type=\"hidden\" value='$aid|$rid|$cid|$depukdate|$duration|$rooms|".serialize($adults)."|".serialize($children)."|".serialize($ages)."|".$regid."' />
							<input name=\"flightsummary\" value=\"".$flight['!DepUKAirportName']."|".$flight['!DepUKAirport']."|".$flight['!ArrAbroadAirportName']."|".$flight['!ArrAbroadAirport']."|".$flight['!DepUKDate']." ".$flight['!DepUKTime']."|" .$flight['!ArrAbroadDate']." ".$flight['!ArrAbroadTime']."|" .$flight['!DepAbroadDate']." ".$flight['!DepAbroadTime']."|" .$flight['!ArrUKDate']." ".$flight['!ArrUKTime']."|".number_format($flight['!TotalPrice'],2,".","")."|".$flight['!OutboundAirlineName']."|".$flight['!OutboundFlightCode']."|".$flight['!ReturnFlightCode']."\" type=\"hidden\" />
							<input name=\"qid\" value=\"".$flight['!Id']."\" type=\"hidden\">
							<input name=\"sbtype\" value=\"7\" type=\"hidden\" />
							<button type=\"submit\" name=\"wpss_button\" value=\"Book\">Select</button></form></span></div>
							
							<div class=\"wpss_sp\"></div>

						</div>
						
						
					</td></tr>\n";	
			}
			
			echo "</table>\n";
			
		}
		else 
			echo "<div class=\"wpss_notice\">Sorry, there are no results from this search</div>";
			
		echo "</div>\n";
			
}

/**
 * Re-orders the flight results into a format more suited for table
 * divided by flights each day, output as seen on the interface
 *
 * @param string $searchdate The date the table should be centred on
 * @param mixed $flights A collection of flight results
 * @return mixed Array containing the flight results in a new order
 */
function parseFlightResults($searchdate,$flights)
{
	$days = array();
	$dayinfo = array();
	
	// setup days array
	for($i=-3;$i<=3;$i++)
	{
		$days[date("Y-m-d",$searchdate+($i*86400))]=array();
	}
	
	// divide results into days
	foreach($flights as $flight)
	{
		$days[$flight['!DepUKDate']][] = $flight;
	}
	
	// store size
	foreach($days as $key => $day)
	{
		$prices = array();
		$dayinfo[$key]['size'] = count($day);
		$dayinfo[$key]['day'] = date("D jS",strtotime($key." 12:00:00"));
		foreach($day as $flight)
		{
			$prices[] = $flight["!TotalPrice"];
		}
		$dayinfo[$key]['cheapestprice'] = @min($prices);
	}
	
	return array($days,$dayinfo);
}

/**
 * HolidaySearchFlight performs a flight search, followed by a hotel search which
 * is dependent on the flight selected. It uses sunshine.co.uk's webservices
 * independently. Once a flight and hotel have been selected, the combination
 * is posted to sunshine.co.uk's redirection page
 *
 */
function HolidaySearchFlight()
{
	global $wpdb,$wp_sunshine;
	
	// extract search values from POST collection
	$day = $_POST["depdate"];
	$monthyear = explode("-",$_POST["depmonth"]);
	$duration = $_POST["duration"];
	
	$depukdate = strtotime($monthyear[1]."-".$monthyear[0]."-".$day . " 12:00:00");
	$depabroaddate = $depukdate + ($duration*86400);
	
	$aid = $_POST["aid"];
	$rid = $_POST["rid"];
	$cid = $_POST["cid"];
	
	$depairp = $_POST["depairp"];
	
	$adults = $_POST["adults"];
	$children = $_POST["children"];
	$ages = $_POST["age"];
	
	$rooms = $_POST["rooms"];
	
	// set dates for flight table
	$centreday = (empty($_POST["centreday"]))?date("Y-m-d",$depukdate):$_POST["centreday"];
	$curday = date("Y-m-d",$depukdate);	
		
	// basic error checking
	$failon = "";
	if(empty($day) || empty($monthyear))
		$failon = "date";
	
	if(empty($duration))
		$failon = "duration";
		
	if(empty($rid) || empty($cid))
		$failon = "destination";
			
	if(empty($depairp))
		$failon = "airport";
		
		
	// locate the airport for the selected resort
	if($rid[0]=='r')
		$arrairp = $wpdb->get_var("SELECT GROUP_CONCAT(DISTINCT code) FROM wpss_resort_airports wra INNER JOIN wpss_country_region_resort wcrr ON wra.rid=wcrr.rid WHERE wcrr.regid='".$wpdb->escape(substr($rid,1))."' GROUP BY wcrr.regid;");
	else 
		$arrairp = $wpdb->get_var("SELECT code FROM wpss_resort_airports WHERE rid='".$wpdb->escape($rid)."' LIMIT 1;");
	
	if(empty($arrairp))
		$failon = "resort";
		
	if(!empty($failon))
	{
		echo "<div class=\"updated\" style=\"padding-top:10px;\"><p><strong>Please ensure you have selected a valid $failon from the menu</strong></p></div>";
		return;
	}
	
	
	// put adults and children into correct format for search;
	$id=1;
	$adultcount=$childcount=$infantcount=0;
	for($i=0;$i<$rooms;$i++)
	{
		$adultcount+=$adults[$i];
		
		if(is_array($ages[$i+1]))
		{
			foreach($ages[$i+1] as $key => $chage)
			{
				if(empty($chage)) // child age cannot be empty, so force to maximum
				{
					$ages[$i+1][$key] = 12;
					$childcount+=1;
				}
				else if($chage>=2) // if age >= 2, then they are regarded as a child
					$childcount+=1;
				else
					$infantcount+=1; // if age<2, child is an infant
			}
		}
	}
	
	
	// output loading image
	echo "<div id=\"imageloader\" align=\"center\"><img src=\"". get_bloginfo('url') ."/wp-content/plugins/sunpress/images/aniloader.gif\" /></div>";
	
	// flush out the loading image, so visitors realise the site is doing something during search times.
	ob_flush();
	flush();
	
	echo "<script>document.getElementById('imageloader').style.display='none';</script>";
	
	require_once("wp-content/plugins/sunpress/includes/nusoap.php");
	
	// Start the search
	$client = new nusoap_client("http://ws.sunshine.co.uk/xml/api/sunshine3.php?wsdl",true,false,false,false,false,120,120);
	
	// Create the proxy
	$proxy = $client->getProxy();
	
	// Perform the search 
	$results = $proxy->FlightSearch(array("UserId"=>$wp_sunshine->user_id,"Password"=>md5($wp_sunshine->password)),
											$centreday,$duration,@implode("|",$depairp),$arrairp,$adultcount,$childcount,$infantcount);
	
											
		// output some basic search info
		echo "<div class=\"wpss_notice\">".$curday." &gt; ".$duration." nts &gt; Ads:".$adultcount." Ch:".($childcount+$infantcount)." &gt;  ".implode(" or ",$depairp)." to $arrairp</div>";										
		
		echo "<div class=\"wpss_flight\"><form action=\"\"><input name=\"sbtype\" value=\"4\" type=\"hidden\" /></form>";

		// check results for error, if none and at least 1 offer, output results
		if(!empty($results["faultstring"]))
			echo "<p>".$results["faultstring"]."</p>";
		else if(sizeof($results)>0)
		{
			// parse results into a flight table format
			$results = parseFlightResults(strtotime($centreday." 12:00:00"),$results);
			
			$i=0;
			echo "<table id=\"wpss_flights_table_head\" width=\"100%\" border=\"0\">
					<tr align=\"center\" valign=\"top\"><td align=\"right\" class=\"key\">Date<br />Flights<br />From</td>";
			foreach($results[1] as $key=>$day)
			{
				// if there is more than 1 result for a day, and it's not the searched day,
				// output links to change to that day.
				if($day["size"]>0 && $key!=$curday)
				{
					$date = explode("-",$key);
					echo "<td>
							<a href=\"#\" onclick=\"changeDate('".$key."','".$centreday."');\">".$day["day"] . "</a><br />
							<a href=\"#\" onclick=\"changeDate('".$key."','".$centreday."');\">" . $day["size"]."</a><br />
							<a href=\"#\" onclick=\"changeDate('".$key."','".$centreday."');\">" . (($day["size"]>0)?"&pound;":"") . $day["cheapestprice"]."</a>
					</td>";
				}
				else 
				{
					echo "<td ".($key==$curday?"class=\"highlight\"":"").">
							".$day["day"] . "<br />
							" . $day["size"]."<br />
							" . (($day["cheapestprice"]>0)?"&pound;".$day["cheapestprice"]:"")."
					</td>";
				}
				
			}
			echo "</tr></table><table id=\"wpss_flights_table\" width=\"100%\" border=\"0\">";
			
			// output the searched days flight results.
			$border=0;
			foreach($results[0][$curday] as $key=> $flight)
			{
				echo "<tr><td";
				
				if($border==0){
					echo " style=\"border-top:0px;\"";
					$border=1;
				}
						echo"><div class=\"book\">

						<div class=\"fdetails\"><span><strong title=\"".$flight['!DepUKAirport']."\">".$flight['!DepUKAirportName']."</strong> to <strong title=\"".$flight['!ArrAbroadAirport']."\">".$flight['!ArrAbroadAirportName']."</strong> - ".$flight['!OutboundAirlineName']."<br />
							Out: ".$flight['!DepUKDate']." ".$flight['!DepUKTime']."<br />&nbsp;&nbsp;In: " .$flight['!DepAbroadDate']." ".$flight['!DepAbroadTime']."</span></div>
						
							<div class=\"price\"><span>&pound;".number_format($flight['!TotalPrice'],2,".","")."</span></div>
						

							<div class=\"option\"><span>
							<form action=\"\" method=\"post\">
							<input name=\"hotelsearch\" type=\"hidden\" value='$aid|$rid|$cid|$depukdate|$duration|$rooms|".serialize($adults)."|".serialize($children)."|".serialize($ages)."|".$regid."' />
							<input name=\"flightsummary\" value=\"".$flight['!DepUKAirportName']."|".$flight['!DepUKAirport']."|".$flight['!ArrAbroadAirportName']."|".$flight['!ArrAbroadAirport']."|".$flight['!DepUKDate']." ".$flight['!DepUKTime']."|" .$flight['!ArrAbroadDate']." ".$flight['!ArrAbroadTime']."|" .$flight['!DepAbroadDate']." ".$flight['!DepAbroadTime']."|" .$flight['!ArrUKDate']." ".$flight['!ArrUKTime']."|".number_format($flight['!TotalPrice'],2,".","")."|".$flight['!OutboundAirlineName']."|".$flight['!OutboundFlightCode']."|".$flight['!ReturnFlightCode']."\" type=\"hidden\" />
							<input name=\"fid\" value=\"".$flight['!Id']."\" type=\"hidden\" />
							<input name=\"sbtype\" value=\"4\" type=\"hidden\" />
							
							<button type=\"submit\" name=\"wpss_button\" value=\"Book\">Select</button></div>
						
							</form></span></div>	


							<div class=\"wpss_sp\"></div>

						</div>
						
					</td></tr>";		
			}
			
			echo "</table>";
			
		}
		else 
			echo "<div class=\"wpss_notice\">Sorry, there are no results from this search</div>";
			
		echo "</div>"; 
			
}



/**
 * HolidaySearchHotel performs the hotel search using sunshine.co.uk's web services.
 * Parameters are supplied via the $_POST collection. If there are available
 * results, they are returned to the user, ordered by cheapest hotel first.
 *
 */
function HolidaySearchHotel()
{
	global $wpdb,$wp_sunshine;
	 
	list($aid,$rid,$cid,$sdepukdate,$duration,$rooms,$adults,$children,$ages,$regid) = explode("|",$_POST["hotelsearch"]);
	list($depairp,$depcode,$arrairp,$arrcode,$depukdate,$arrabroaddate,$depabroaddate,$arrukdate,$fprice,$airline,$outflightcode,$returnflightcode) = explode("|",$_POST["flightsummary"]);
	$fid = $_POST["fid"];
	
	// rebuild dates
	list($depukdate,$depuktime) = ExplodeDate($depukdate);
	list($arrabroaddate,$arrabroadtime) = ExplodeDate($arrabroaddate);
	list($depabroaddate,$depabroadtime) = ExplodeDate($depabroaddate);
	list($arrukdate,$arruktime) = ExplodeDate($arrukdate);
	
	$day = date("d",$sdepukdate);
	$monthyear = date("m-Y",$sdepukdate);
	$sdepabroaddate = $sdepukdate + ($duration*86400);
	
	$adults = unserialize(stripslashes($adults));
	$children = unserialize(stripslashes($children));
	$ages = unserialize(stripslashes($ages));
	
	
	// basic error checking
	$failon = "";
	if(empty($day) || empty($monthyear))
		$failon = "date";
	
	if(empty($duration))
		$failon = "duration";
			
	if(empty($rid) || empty($cid))
		$failon = "destination";

	if(empty($fid))
		$failon = "flight";
		
	if(!empty($failon))
	{
		echo "<div class=\"updated\" style=\"padding-top:10px;\"><p><strong>Please ensure you have selected a valid $failon from the menu</strong></p></div>";
		return;
	}
	
	
	// put adults and children into correct format for search;
	$adultsearch = array();
	$childsearch = array();
	
	$roomsearch = array();
	$id=1;
	for($i=0;$i<$rooms;$i++)
	{
		// add each adult to a room
		for($j=0;$j<$adults[$i];$j++)
			$adultsearch[] = array('Id'=>($id++));
						
		for($j=0;$j<$children[$i];$j++)
			$childsearch[] = array('Id'=>($id++),'Age'=>$ages[$i+1][$j+1]);
		
		// add the pax to the rooms
		$roomsearch[] = array('Adults'=>$adultsearch,'Children'=>$childsearch);
		
		// clear temp arrays
		$adultsearch = array();
		$childsearch = array();
	}
	
	// check if a region has been selected from resort drop down.
	if($rid[0]=='r')
	{
		$regid=substr($rid,1);
		$rid='';
		// retrieve resort name to display with the search parameters
		$resortname = $wpdb->get_var("SELECT name FROM wpss_region WHERE regid='".$wpdb->escape($regid)."' LIMIT 1;");				
	}
	else
	{
		$regid=0;
		// retrieve resort name to display with the search parameters
		$resortname = $wpdb->get_var("SELECT name FROM wpss_resort WHERE rid='".$wpdb->escape($rid)."' LIMIT 1;");				
	}
	
	// output search parameters
	echo "<div class=\"wpss_notice\">".date("d-m-Y",$depukdate)." &gt; ".$duration." nts &gt; ".$resortname." &gt; Ads:".@array_sum($adults)." Ch:".@array_sum($children)." &gt;</div>";
	

	echo "<div class=\"wpss_summary\">
				<div class=\"title\"><strong>Your selection:</strong></div>
				<div class=\"wpss_details\" style=\"border:0px\">
					<div class=\"subtitle\"><em>Selected Flight</em></div>
					<div class=\"item\"><span><strong>".$depairp."</strong> (".$depcode.") to <strong>".$arrairp."</strong> (".$arrcode.")<br />".date("Y-m-d",$depukdate)." ".$depuktime." | " .date("Y-m-d",$arrukdate) ." " .$arruktime."</span></div>
					<div class=\"subitem\">".$airline."</div>
					<div class=\"price\">&pound;".number_format($fprice,2,".","")."</div>
					<div class=\"wpss_sp\"></div>	
				</div>
			</div>
			";

	
	echo "<div class=\"wpss_notice\">Please select an accommodation from the following to go with your flight :</div>";	

	// output loading image
	echo "<div id=\"imageloader\" align=\"center\"><img src=\"". get_bloginfo('url') ."/wp-content/plugins/sunpress/images/aniloader.gif\" /></div>";
	
	// flush out the loading image, so visitors realise the site is doing something during search times.
	ob_flush();
	flush();
	
	echo "<script>document.getElementById('imageloader').style.display='none';</script>";
	
		
	// require nusoap, could use built in php soapclient (php>5) if available, but
	// many php installations have it enabled by default
	require_once("wp-content/plugins/sunpress/includes/nusoap.php");
	
	// Start the search
	$client = new nusoap_client("http://ws.sunshine.co.uk/xml/api/sunshine3.php?wsdl",true,false,false,false,false,120,120);
	
	// Create the proxy
	$proxy = $client->getProxy();
	
	
	
	// Perform the search
	$results = $proxy->HotelSearch(array("UserId"=>$wp_sunshine->user_id,"Password"=>md5($wp_sunshine->password)),date("Y-m-d",$sdepukdate),date("Y-m-d",$sdepabroaddate),$regid,$rid,'',$roomsearch);
	
	//print_r($proxy->request);
	// if the search yeilds results, output them
	if(!empty($results["faultstring"]))
			echo "<p>".$results["faultstring"]."</p>";
	else if(sizeof($results)>0)
	{
		$i=0;
		
		$mainhotel = array();
		$otherhotels = array();
		
		foreach($results as $hotel)
		{
			if(!empty($aid) && $aid == $hotel["HotelId"])
				$mainhotel = $hotel;
			else 
				$otherhotels[] = $hotel;
		}

		// add hotel to start of array
		if(isset($mainhotel["HotelId"]))
		{
			array_unshift($otherhotels,$mainhotel);	
		}
		
		$first = true;
		
		foreach($otherhotels as $hotel)
		{
			
			if($first && !empty($aid) && $aid == $hotel["HotelId"])
			{
				echo "<div class=\"wpss_notice\">Your chosen hotel :</div>";
			}
			else if($first && !empty($aid))
			{
				echo "<div class=\"wpss_notice\">Your chosen hotel is unavailable, please see alternative hotels below :</div>";
			}
			echo "<div class=\"wpss_hotel\">
					<div class=\"title\"><a target=\"_blank\" href=\"".wpss_affiliate_link($hotel["ResortLink"])."\">".$hotel["ResortName"]."</a> &#xbb; <a target=\"_blank\" href=\"".wpss_affiliate_link($hotel["Link"])."\">".$hotel["HotelName"]."</a></div>
				
					<div class=\"desc\">

	<div class=\"pic\"><a target=\"_blank\" href=\"".wpss_affiliate_link($hotel["Link"])."\"><img width=\"100\" border=\"0\" src=\"".$hotel["Picture"]."\" /></a></div>

						<div class=\"stars\">".starRating($hotel["Stars"])."</div>
						<p>".$hotel["Description"]."</p>
					</div>
					<div class=\"wpss_sp\"></div>
					<div class=\"title\"><strong>".$hotel["HotelName"]." Availability</strong></div>
					";			
							
						foreach($hotel["Rates"] as $rate)
						{
								echo "<div class=\"book\">		
									
										<div class=\"board\"><span>".$rate["RoomType"].", ".$rate["MealType"]."</span></div>
										<div class=\"price\"><span>&pound;".number_format($rate["Price"],2,".","")."</span></div>

										<div class=\"option\"><span>
											<form action=\"\" method=\"post\">
												<input name=\"hotelsearch\" type=\"hidden\" value='".stripslashes($_POST["hotelsearch"])."' />
												<input name=\"flightsummary\" value=\"".$_POST["flightsummary"]."\" type=\"hidden\" />
												<input name=\"hotelsummary\" value=\"".$hotel["HotelId"]."|".$hotel["ResortId"]."|".$hotel["ResortName"]."|".$hotel["HotelName"]."|".$rate["RoomType"]."|".$rate["MealType"]."|".$rate["Price"]."\" type=\"hidden\" />
												<input name=\"fid\" value=\"".$fid."\" type=\"hidden\">
												<input name=\"qid\" value=\"".$rate["QuoteId"]."\" type=\"hidden\">
												<input name=\"sbtype\" value=\"8\" type=\"hidden\" />
												<input name=\"booktype\" value=\"holiday\" type=\"hidden\">
												<button type=\"submit\" name=\"wpss_button\" value=\"Book\">Select</button>
											</form>
										</span></div> 
							

									</div>
									<div class=\"wpss_sp\"></div>";
	
						}
				
					echo "</div>";
					
			if($first && !empty($aid) && $aid == $hotel["HotelId"])
			{
				echo "<div class=\"wpss_notice\">Other hotels available in your chosen resort :</div>";
			}
			
			$first = false;
		}
	}
		
	
}

function ExplodeDate($val)
{
	$val = explode(" ",$val);
	$date = explode("-",$val[0]);
	$time = $val[1];
	
	return array(mktime(12,0,0,$date[1],$date[2],$date[0]),$time);
}

/**
 * HolidaySearchTransfer performs the transfer search using sunshine.co.uk's web services.
 * Parameters are supplied via the $_POST collection. If there are available
 * results, they are returned to the user, ordered by cheapest transfer first.
 *
 */
function HolidaySearchTransfer()
{
	global $wpdb,$wp_sunshine;
	
	list($aid,$rid,$cid,$sdepukdate,$duration,$rooms,$adults,$children,$ages,$regid) = explode("|",$_POST["hotelsearch"]);
	list($hotelid,$resortid,$rname,$hotelname,$roomtype,$mealtype,$hprice) = explode("|",$_POST["hotelsummary"]);
	list($depairp,$depcode,$arrairp,$arrcode,$depukdate,$arrabroaddate,$depabroaddate,$arrukdate,$fprice,$airline,$outflightcode,$returnflightcode) = explode("|",$_POST["flightsummary"]);
	
	$fid = $_POST["fid"];
	$qid = $_POST["qid"];
	
	list($depukdate,$depuktime) = ExplodeDate($depukdate);
	list($arrabroaddate,$arrabroadtime) = ExplodeDate($arrabroaddate);
	list($depabroaddate,$depabroadtime) = ExplodeDate($depabroaddate);
	list($arrukdate,$arruktime) = ExplodeDate($arrukdate);
	
	$day = date("d",$sdepukdate);
	$monthyear = date("m-Y",$sdepukdate);
	$sdepabroaddate = $sdepukdate + ($duration*86400);
	
	$adults = unserialize(stripslashes($adults));
	$children = unserialize(stripslashes($children));
	$ages = unserialize(stripslashes($ages));
	
	// basic error checking
	$failon = "";
	if(empty($day) || empty($monthyear))
		$failon = "date";
	
	if(empty($duration))
		$failon = "duration";
			
	if(empty($rid) || empty($cid))
		$failon = "destination";

	if(empty($fid))
		$failon = "flight";
		
	if(!empty($failon))
	{
		echo "<div class=\"updated\" style=\"padding-top:10px;\"><p><strong>Please ensure you have selected a valid $failon from the menu</strong></p></div>";
		return;
	}
	
	
	$id=1;
	$adultcount=$childcount=$infantcount=0;
	for($i=0;$i<$rooms;$i++)
	{
		$adultcount+=$adults[$i];
		
		if(is_array($ages[$i+1]))
		{
			foreach($ages[$i+1] as $key => $chage)
			{
				if(empty($chage)) // child age cannot be empty, so force to maximum
				{
					$ages[$i+1][$key] = 12;
					$childcount+=1;
				}
				else if($chage>=2) // if age >= 2, then they are regarded as a child
					$childcount+=1;
				else
					$infantcount+=1; // if age<2, child is an infant
			}
		}
	}
	
	
	
	// output search parameters
	echo "<div class=\"wpss_notice\">".date("d-m-Y",$depukdate)." &gt; ".$duration." nts &gt; ".$rname." &gt; Ads:".$adultcount." Ch:".($childcount+$infantcount)." &gt;</div>";
	
	echo "<div class=\"wpss_summary\">
				<div class=\"title\"><strong>Your selection:</strong></div>
				<div class=\"wpss_details\" style=\"border:0px\">
					<div class=\"subtitle\"><em>Selected Flight</em></div>
					<div class=\"item\"><span><strong>".$depairp."</strong> (".$depcode.") to <strong>".$arrairp."</strong> (".$arrcode.")<br />".date("Y-m-d",$depukdate)." ".$depuktime." | " .date("Y-m-d",$arrukdate) ." " .$arruktime."</span></div>
					<div class=\"subitem\">".$airline."</div>
					<div class=\"price\">&pound;".number_format($fprice,2,".","")."</div>
					<div class=\"wpss_sp\"></div>	
				</div>

				<div class=\"wpss_details\">
					<div class=\"subtitle\"><em>Selected Hotel</em></div>
					<div class=\"item\"><span><strong>".$hotelname."</strong><br />".$roomtype.", " .$mealtype."</span></div>
					<div class=\"subitem\">".$rname."</div>
					<div class=\"price\">&pound;".number_format($hprice,2,".","")."</div>
					<div class=\"wpss_sp\"></div>
				</div>
			</div>";

		echo "<div class=\"wpss_notice\">Please select a transfer from the following to go with your flight and hotel :</div>";	

	// output loading image
	echo "<div id=\"imageloader\" align=\"center\"><img src=\"". get_bloginfo('url') ."/wp-content/plugins/sunpress/images/aniloader.gif\" /></div>";
	
	// flush out the loading image, so visitors realise the site is doing something during search times.
	ob_flush();
	flush();
	
	echo "<script>document.getElementById('imageloader').style.display='none';</script>";
	
	// require nusoap, could use built in php soapclient (php>5) if available, but
	// many php installations have it enabled by default
	require_once("wp-content/plugins/sunpress/includes/nusoap.php");
	
	// Start the search
	$client = new nusoap_client("http://ws.sunshine.co.uk/xml/api/sunshine3.php?wsdl",true,false,false,false,false,120,120);
	
	// Create the proxy
	$proxy = $client->getProxy();
		
	$id = explode("|",$fid);
	
	// Perform the search
	$results = $proxy->TransferSearch(array("UserId"=>$wp_sunshine->user_id,"Password"=>md5($wp_sunshine->password)),$hotelid,$resortid,date("Y-m-d",$depukdate),date("Y-m-d",$arrabroaddate),date("Y-m-d",$depabroaddate),date("Y-m-d",$arrukdate),$depuktime,$arrabroadtime,$depabroadtime,$arruktime,$depcode,$arrcode,$adultcount,$childcount,$infantcount,$outflightcode,$returnflightcode);

	// useful for debugging
	// print_r($proxy->request); 
	// print_r($proxy->response); 
	 
	//print_r($proxy->request);
	// if the search yeilds results, output them
	if(!empty($results["faultstring"]))
			echo "<p>".$results["faultstring"]."</p>";
	else if(sizeof($results)>0)
	{
		$i=0;
		array_unshift($results,array("!QuoteId"=>"","!Product"=>"NO TRANSFER NEEDED","!TotalPrice"=>""));
		foreach($results as $transfer)
		{
			echo "<div class=\"wpss_transfer\">
		        		<div class=\"title\"><strong>".$transfer["!Product"]."</strong></div>
						<div class=\"book\">		
								<div class=\"tdetails\"><span>".(!empty($transfer["!QuoteId"])?"Return transfer to $rname":"Choose this option if a transfer is not required")."</span></div>
								<div class=\"price\"><span>&pound;".number_format($transfer["!TotalPrice"],2,".","")."</span></div>
								<div class=\"option\"><span>
									<form action=\"\" method=\"post\">
										<input name=\"hotelsearch\" type=\"hidden\" value='".stripslashes($_POST["hotelsearch"])."' />
										<input name=\"flightsummary\" value=\"".$_POST["flightsummary"]."\" type=\"hidden\" />
										<input name=\"hotelsummary\" value=\"".$_POST["hotelsummary"]."\" type=\"hidden\" />
										<input name=\"transfersummary\" value=\"".$transfer["!Product"]."|".$transfer["!TotalPrice"]."\" type=\"hidden\" />
										<input name=\"fid\" value=\"".$fid."\" type=\"hidden\">
										<input name=\"qid\" value=\"".$qid."\" type=\"hidden\">
										<input name=\"tid\" value=\"".$transfer["!QuoteId"]."\" type=\"hidden\">
										<input name=\"sbtype\" value=\"5\" type=\"hidden\" />
										<input name=\"booktype\" value=\"holiday\" type=\"hidden\">
										<button type=\"submit\" name=\"wpss_button\" value=\"Book\">".(!empty($transfer["!QuoteId"])?"Select":"None")."</button>
									</form></span>
								</div>

								<div class=\"wpss_sp\"></div>

							</div>
							
						
				</div>";
		}
	}
	else {
		echo "<div class=\"wpss_notice\">No Transfers have been found in this resort.</b> Please continue to book by clicking on the link below.</div><div class=\"wpss_hotel\">
		<div class=\"book\">		
			<div>
				<form action=\"\" method=\"post\" target=\"_blank\">
					<input name=\"fid\" value=\"".$fid."\" type=\"hidden\">
					<input name=\"qid\" value=\"".$qid."\" type=\"hidden\">
					<input name=\"booktype\" value=\"holiday\" type=\"hidden\">
					<button type=\"submit\" name=\"book\" value=\"Book\">Book with sunshine.co.uk</button>
				</form>
			</div>
			Total : <b class=\"price\">&pound;".number_format($hprice+$fprice,2,".","")."</b><br />
		
	</div>";
	}
		
	
}

/**
 * This page confirms the user's selection, before proceeding.
 *
 */
function ConfirmHoliday()
{
	list($aid,$rid,$cid,$sdepukdate,$duration,$rooms,$adults,$children,$ages,$regid) = explode("|",$_POST["hotelsearch"]);
	list($hotelid,$resortid,$rname,$hotelname,$roomtype,$mealtype,$hprice) = explode("|",$_POST["hotelsummary"]);
	list($depairp,$depcode,$arrairp,$arrcode,$depukdate,$arrabroaddate,$depabroaddate,$arrukdate,$fprice,$airline) = explode("|",$_POST["flightsummary"]);
	list($transferproduct,$tprice) = explode("|",$_POST["transfersummary"]);
	
	$fid = $_POST["fid"];
	$qid = $_POST["qid"];
	$tid = $_POST["tid"];
	
	$day = date("d",$sdepukdate);
	$monthyear = date("m-Y",$sdepukdate);
	$sdepabroaddate = $sdepukdate + ($duration*86400);
	
	list($depukdate,$depuktime) = ExplodeDate($depukdate);
	list($arrabroaddate,$arrabroadtime) = ExplodeDate($arrabroaddate);
	list($depabroaddate,$depabroadtime) = ExplodeDate($depabroaddate);
	list($arrukdate,$arruktime) = ExplodeDate($arrukdate);
	
	$adults = unserialize(stripslashes($adults));
	$children = unserialize(stripslashes($children));
	$ages = unserialize(stripslashes($ages));
	
	echo "<div class=\"wpss_summary\">
				<div class=\"title\"><strong>Your selection:</strong></div>
				<div class=\"wpss_details\" style=\"border:0px\">
					<div class=\"subtitle\"><em>Selected Flight</em></div>
					<div class=\"item\"><span><strong>".$depairp."</strong> (".$depcode.") to <strong>".$arrairp."</strong> (".$arrcode.")<br />".date("Y-m-d",$depukdate)." ".$depuktime." | " .date("Y-m-d",$arrukdate) ." " .$arruktime."</span></div>
					<div class=\"subitem\">".$airline."</div>
					<div class=\"price\">&pound;".number_format($fprice,2,".","")."</div>
					<div class=\"wpss_sp\"></div>	
				</div>

				<div class=\"wpss_details\">
					<div class=\"subtitle\"><em>Selected Hotel</em></div>
					<div class=\"item\"><span><strong>".$hotelname."</strong><br />".$roomtype.", " .$mealtype."</span></div>
					<div class=\"subitem\">".$rname."</div>
					<div class=\"price\">&pound;".number_format($hprice,2,".","")."</div>
					<div class=\"wpss_sp\"></div>
				</div>";

	// if a transfer has been choosen
	if(!empty($tid))
	{

			echo "<div class=\"wpss_details\">
					<div class=\"subtitle\"><em>Selected Transfer</em></div>
					<div class=\"item\"><strong>Return transfer to ".$rname."</strong></div>
					<div class=\"subitem\">".$transferproduct."</div>
					<div class=\"price\">&pound;".number_format($tprice,2,".","")."</div>
					<div class=\"wpss_sp\"></div>
				</div>";
	}

	echo "<div>
		<div class=\"title\" style=\"text-align:right;\">		
			Total : <strong>&pound;".number_format($hprice+$fprice+$tprice,2,".","")."</strong>
		</div>
			<div style=\"padding:10px;text-align:right;\">
				<form action=\"\" method=\"post\" target=\"_blank\">
					<input name=\"fid\" value=\"".$fid."\" type=\"hidden\">
					<input name=\"qid\" value=\"".$qid."\" type=\"hidden\">
					<input name=\"tid\" value=\"".$tid."\" type=\"hidden\">
					<input name=\"booktype\" value=\"holiday\" type=\"hidden\">
					<button type=\"submit\" name=\"book\" value=\"Book\">Book with sunshine.co.uk</button>
				</form>
			</div>		
	</div>";

	echo "</div>"; 	
}

function ConfirmHotel()
{
	
	list($aid,$rid,$cid,$sdepukdate,$duration,$rooms,$adults,$children,$ages,$regid) = explode("|",$_POST["hotelsearch"]);
	list($hotelid,$resortid,$rname,$hotelname,$roomtype,$mealtype,$hprice) = explode("|",$_POST["hotelsummary"]);
	
	$qid = $_POST["qid"];
	
	$day = date("d",$sdepukdate);
	$monthyear = date("m-Y",$sdepukdate);
	$sdepabroaddate = $sdepukdate + ($duration*86400);
	
	$adults = unserialize(stripslashes($adults));
	$children = unserialize(stripslashes($children));
	$ages = unserialize(stripslashes($ages));
	

	echo "<div class=\"wpss_summary\">
				<div class=\"title\"><strong>Your selection:</strong></div>

				<div class=\"wpss_details\" style=\"border:0px\">
					<div class=\"subtitle\"><em>Selected Hotel</em></div>
					<div class=\"item\"><span><strong>".$hotelname."</strong><br />".$roomtype.", " .$mealtype."</span></div>
					<div class=\"subitem\">".$rname."</div>
					<div class=\"price\">&pound;".number_format($hprice,2,".","")."</div>
					<div class=\"wpss_sp\"></div>
				</div>";


	echo "<div>
		<div class=\"title\" style=\"text-align:right;\">		
			Total : <strong>&pound;".number_format($hprice+$fprice+$tprice,2,".","")."</strong>
		</div>
			<div style=\"padding:10px;text-align:right;\">
				<form action=\"\" method=\"post\" target=\"_blank\">
					<input name=\"qid\" value=\"".$qid."\" type=\"hidden\">
					<input name=\"booktype\" value=\"hotel\" type=\"hidden\">
					<button type=\"submit\" name=\"book\" value=\"Book\">Book with sunshine.co.uk</button>
				</form>
			</div>		
	</div>";

	echo "</div>"; 
}

function ConfirmFlight()
{
	list($aid,$rid,$cid,$sdepukdate,$duration,$rooms,$adults,$children,$ages,$regid) = explode("|",$_POST["hotelsearch"]);
	list($depairp,$depcode,$arrairp,$arrcode,$depukdate,$arrabroaddate,$depabroaddate,$arrukdate,$fprice,$airline) = explode("|",$_POST["flightsummary"]);
	
	$qid = $_POST["qid"];
	
	// rebuild dates
	list($depukdate,$depuktime) = ExplodeDate($depukdate);
	list($arrabroaddate,$arrabroadtime) = ExplodeDate($arrabroaddate);
	list($depabroaddate,$depabroadtime) = ExplodeDate($depabroaddate);
	list($arrukdate,$arruktime) = ExplodeDate($arrukdate);
	
	$day = date("d",$sdepukdate);
	$monthyear = date("m-Y",$sdepukdate);
	$sdepabroaddate = $sdepukdate + ($duration*86400);
		
	$adults = unserialize(stripslashes($adults));
	$children = unserialize(stripslashes($children));
	$ages = unserialize(stripslashes($ages));
	
	$_POST["depairp"]=$depairp;
	$_POST["arrairp"]=$arrairp;




	echo "<div class=\"wpss_summary\">
				<div class=\"title\"><strong>Your selection:</strong></div>
				<div class=\"wpss_details\" style=\"border:0px\">
					<div class=\"subtitle\"><em>Selected Flight</em></div>
					<div class=\"item\"><span><strong>".$depairp."</strong> (".$depcode.") to <strong>".$arrairp."</strong> (".$arrcode.")<br />".date("Y-m-d",$depukdate)." ".$depuktime." | " .date("Y-m-d",$arrukdate) ." " .$arruktime."</span></div>
					<div class=\"subitem\">".$airline."</div>
					<div class=\"price\">&pound;".number_format($fprice,2,".","")."</div>
					<div class=\"wpss_sp\"></div>	
				</div>";

	echo "<div>
		<div class=\"title\" style=\"text-align:right;\">		
			Total : <strong>&pound;".number_format($hprice+$fprice+$tprice,2,".","")."</strong>
		</div>
			<div style=\"padding:10px;text-align:right;\">
				<form action=\"\" method=\"post\" target=\"_blank\">
					<input name=\"qid\" value=\"".$qid."\" type=\"hidden\">
					<input name=\"booktype\" value=\"flight\" type=\"hidden\">
					<button type=\"submit\" name=\"book\" value=\"Book\">Book with sunshine.co.uk</button>
				</form>
			</div>		
	</div>";

	echo "</div>"; 
	
}

?>
<?php get_header(); ?>
 
<?php 

if(get_option('wpss_sidebar_side')=='left')
	get_sidebar(); 

?>
<div id="content" class="narrowcolumn">
<style>
.wpss_hotel, .wpss_summary, .wpss_transfer{border-color:<?php echo get_option("wpss_sr_css_bordercolor");?>;}
.wpss_hotel .title, .wpss_summary .title, .wpss_transfer .title{background:<?php echo get_option('wpss_sr_css_bgcolor');?>;color:<?php echo get_option('wpss_sr_css_fontcolor');?>;}
.wpss_hotel .title a, .wpss_summary .title a, .wpss_transfer .title{color:<?php echo get_option('wpss_sr_css_fontcolor');?>;}

.wpss_flight #wpss_flights_table_head td{border-color:<?php echo get_option("wpss_sr_css_bordercolor");?>;background-color:<?php echo get_option('wpss_sr_css_bgcolor');?>;color:#000;}
.wpss_flight #wpss_flights_table_head td a{color:<?php echo get_option('wpss_sr_css_fontcolor');?>;text-decoration:underline;}

#wpss_flights_table_head td.key{border-left-color:#FFF;border-top-color:#FFF;background-color:#FFF;}
#wpss_flights_table{border-color:<?php echo get_option("wpss_sr_css_bordercolor");?>;}

#wpss_flights_table_head td.key{color:#000;font-weight:bold;}

</style>
<?php




// check which type of search is being performed and handle accordingly.
switch($_POST["sbtype"])
{
	case 1:
		HolidaySearchFlight();
		break;
		
	case 2:
		HotelSearch(); 
		break;
		
	case 3:
		FlightSearch();
		break;
		
	case 4:
		HolidaySearchHotel();
		break;
		
	case 5:
		ConfirmHoliday();
		break;
	
	case 6:
		ConfirmHotel();
		break;
		
	case 7:
		ConfirmFlight();
		break;
		
	case 8:
		HolidaySearchTransfer();
		break;
		
		
	default:
		HotelSearch();
		break;
}

?>
</div>
<?php 

if(get_option('wpss_sidebar_side')!='left')
	get_sidebar(); 

?>

<?php get_footer(); ?>
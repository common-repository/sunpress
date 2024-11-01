function getResorts(home,cid,rid,def)
{
	clearlist('wpss_rid','-- All Resorts --');
	jcall(home+'/wp-content/plugins/sunpress/includes/menujsn.php?op=getresorts&cid='+cid+'&rid='+rid+'&def='+escape(def),getResortsResult);
}

function getHotels(home,rid,aid)
{
	clearlist('wpss_aid','All Accommodations');
	jcall(home+'/wp-content/plugins/sunpress/includes/menujsn.php?op=gethotels&rid='+rid+'&aid='+aid,getItemsResult);
}

function getAirports(home,code,arrival)
{ 
	jcall(home+'/wp-content/plugins/sunpress/includes/menujsn.php?op=getairports&code='+code+'&arrival='+arrival,getItemsResult);
}

function getResortsResult(jso)
{
	var robj = eval('('+jso+')');
	var reselm = getElm('wpss_rid');
	clearlist('wpss_rid',robj.def,'0');

	for(var i=0;i<robj.resorts.length;i++)
	{
		if(robj.resorts[i].reg!=null&&robj.resorts[i].reg!="")
		{
			currentregion = robj.resorts[i].reg;
			if(!document.all||wpss_isop)
				reselm.add(new Option("-- "+currentregion+" --","r"+robj.resorts[i].regval),null);
			else
				reselm.add(new Option("-- "+currentregion+" --","r"+robj.resorts[i].regval));

			// set the region as default
			if(!empty(robj.reg) && robj.reg==robj.resorts[i].regval)
				reselm.value='r'+robj.reg;
					
			while(robj.resorts[i]!=null&&robj.resorts[i].reg==currentregion&&i<robj.resorts.length)
			{
				if(!document.all||wpss_isop)
					reselm.add(new Option(robj.resorts[i].res,robj.resorts[i].val),null);
				else
					reselm.add(new Option(robj.resorts[i].res,robj.resorts[i].val));

				if(robj.resorts[i].sel=="1")
					reselm.value=robj.resorts[i].val;
				i++;
			}
			i--;
		}
		else
		{
			if(!document.all||wpss_isop)
					reselm.add(new Option(robj.resorts[i].res,robj.resorts[i].val),null);
				else
					reselm.add(new Option(robj.resorts[i].res,robj.resorts[i].val));

			if(robj.resorts[i].sel=="1")
					reselm.value=robj.resorts[i].val;

		}
	}
	return true;

}

function getItemsResult(jso)
{
	if(!empty(jso))
	{
		var obj = eval('('+jso+')');
		var slist = getElm(obj.listid);
		
		while(slist.length>0)
			slist.remove(0);
			
		for(var i=0;i<obj.items.length;i++)
		{
			if(!document.all||wpss_isop)
				slist.add(new Option(obj.items[i].data,obj.items[i].val),null);
			else
				slist.add(new Option(obj.items[i].data,obj.items[i].val));

			if(obj.items[i].sel==1)
				slist.value=obj.items[i].val;
		}
	}
	return true;
}

function addroom(roomNo)
{
  var ni = document.getElementById('roomsdiv');
  var numi = document.getElementById("rc");
  
  while(roomNo>numi.value)
  {
	numi.value++;
	
	var newdiv = document.createElement('div');
	newdiv.setAttribute('id','my'+numi.value+'Row');
	newdiv.className = 'searchval2';
	newdiv.style.clear='both';
	newdiv.innerHTML = '<b>Room '+numi.value+'</b><select name="adults[]" id="adults'+numi.value+'" style="margin-left:12px;width:40px;"><option value="1">1</option><option selected value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5">5</option><option value="6">6</option></select><select name="children[]" id="children'+numi.value+'" onchange="addremoveage(this.selectedIndex,'+numi.value+');" style="margin-left:4px;width:40px;"><option value="0">0</option><option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4</option></select><div id="agesroom'+numi.value+'"></div>';
	ni.appendChild(newdiv);
   }
  
  addremovelink(numi.value<4,numi.value>1);
  
}

function addremovelink(showadd,showrem)
{
	document.getElementById('addrdiv').style.display= (showadd?'block':'none');
	document.getElementById('addadiv').style.display= (showrem?'block':'none');
}

function removeroom(roomNo)
{
	var rc = document.getElementById('rc').value;
	while(rc>roomNo)
	{
		var d = document.getElementById('roomsdiv');
		var olddiv = document.getElementById('my'+rc+'Row');
		d.removeChild(olddiv);
		rc--;
	}
	document.getElementById('rc').value = roomNo;
	
	addremovelink(roomNo<4,roomNo>1);
}

function setrooms(ind)
{
	ind = parseInt(ind);
	if((ind+1)<document.getElementById("rc").value)
		removeroom(ind+1);
	else
		addroom(ind+1);
		
}
	
function setadults(elm,adults)
{
	for(var i=1;i<=adults.length;i++)
	{
		document.getElementById(elm+i).value=adults[i-1];
	} 
}	

function setchildren(elm,children)
{
	for(var i=1;i<=children.length;i++)
	{
		document.getElementById(elm+i).value=children[i-1];
	}
}

function addremoveage(ind,roomNo)
{
	if(ind<getElm("agescount"+roomNo).value)
		removeage(ind,roomNo);
	else
		addage(ind,roomNo);
}

function addage(noages, roomNo)
{
	var ni = getElm('agesroom'+roomNo);
	var numi = getElm('agescount'+roomNo);
	
	while(noages>numi.value)
	{
		numi.value++;
		var newdiv = document.createElement('span');
		newdiv.setAttribute('id','age'+numi.value+'r'+roomNo+'span');
		
		var output2="";
		
		if(numi.value==1)
		{
			output2 = " <div style=\"overflow:hidden;clear:both\">Children's age on date of return</div>";
		}
		newdiv.innerHTML = output2+'<table style="float:left;width:40px;"><tr align="center"><td>Age '+numi.value+'</td></tr><tr><td><select style="width:40px" name="age['+roomNo+']['+numi.value+']" id="age'+numi.value+'r'+roomNo+'"><option value=\"\"></option><option value="1">Inf</option><option value="2">2</option><option value="3">3</option><option value="4">4</option><option value="5">5</option><option value="6">6</option><option value="7">7</option><option value="8">8</option><option value="9">9</option><option value="10">10</option><option value="11">11</option><option value="12">12</option></select></td></tr></table>';
		ni.appendChild(newdiv);
	}

}

function removeage(noages, roomNo)
{
	var numi = getElm('agescount'+roomNo);
	
	while(numi.value>noages)
	{
		var olddiv = getElm('age'+numi.value+'r'+roomNo+'span');
		
		var d = getElm('agesroom'+roomNo);
		d.removeChild(olddiv);
		numi.value--;
	}
	getElm('agescount'+roomNo).value = numi.value;
}

function checkit()
{
	if((document.getElementById('ssbtype').value==1 || document.getElementById('ssbtype').value==3) && document.getElementById('wpss_depairp').selectedIndex==0)
	{
		alert('Please select a departing airport from the drop down');
		return false;
	}
	
	var i=2;
	while(document.getElementById('addedairp'+i)!=null)
	{
		if(document.getElementById('addedairp'+i).selectedIndex==0)
		{
			alert('Please choose an airport in each drop down, or remove an airport by clicking on x beside it.');
			return false;
		}
		i++;
	}
	
	var num=0;
	for(var i=1;i<=4;i++)
	{
		num = getElm('agescount'+i).value;
		if(num!='')
		{
			for(var j=1;j<=num;j++)
			{
				if(getElm('age'+j+'r'+i+'').value=='')
				{
					alert('Please ensure all child ages are supplied');
					return false;
				}
			}
		}
	}
}

function changeDate(searchday,centreday)
{
	var tempday = searchday.split('-');
	document.getElementById('depdate').value=tempday[2];
	document.getElementById('depmonth').value=tempday[1]+'-'+tempday[0];
	document.getElementById('centreday').value=centreday;
	document.getElementById('ss_searchbutton').click();
}

function hideElems(tag,sel)
{
	var itms = document.getElementsByTagName(tag);
	var length = itms.length;
	while(length)
	{
		itm = itms[--length];
		if(itm.className==sel)
			itm.style.display='none';
	}
}

function addAirport()
{
	hideElems('a','remlink2');
	
	var airports = getElm('airpcount');

	if(airports.value<3)
	{
		airports.value++;
		document.getElementById('frm-airp').innerHTML += '<select dest="1" id="addedairp'+airports.value+'" name="depairp[]" class="wpss_sel">'+document.getElementById('wpss_depairp').innerHTML+'</select> <a id="addedlink'+airports.value+'" href="#" onclick="removeAirport();return false" class="remlink2" title="click here to remove this airport">x</a>';
	}

	if(airports.value>2)
		document.getElementById('depairpadd').style.display = 'none';
}

function removeAirport()
{
	var airports = getElm('airpcount');
	
	var temp1 = document.getElementById('addedairp'+airports.value);
	var temp2 = document.getElementById('addedlink'+airports.value);
	temp1.parentNode.removeChild(temp1);
	temp2.parentNode.removeChild(temp2);
	
	airports.value--;

	if(airports.value<=3)
		document.getElementById('depairpadd').style.display = 'block';


	hideElems('a','remlink2');
	
	if(airports.value>1)
		document.getElementById('addedlink'+(airports.value)).style.display = 'inline';
}

function selectType(typ,val,home)
{
	switch(typ)
	{
		case 1:
			document.getElementById('fromlbl').style.display='';
			document.getElementById('tolbl').style.display='';
			document.getElementById('toalbl').style.display='none';
			document.getElementById('ssbtype').value=1;
			document.searchform.sbtype.value=1; // IE Fix
			document.getElementById('roomlayout').style.display='';
			document.getElementById('flightlayout').style.display='none';
			setCookie('searchboxtype', 1,'','/');
			
		break;
		
		case 2:
			document.getElementById('fromlbl').style.display='none';
			document.getElementById('tolbl').style.display='';
			document.getElementById('toalbl').style.display='none';
			document.getElementById('ssbtype').value=2;
			document.searchform.sbtype.value=2; // IE Fix
			document.getElementById('roomlayout').style.display='';
			document.getElementById('flightlayout').style.display='none';
			setCookie('searchboxtype', 2,'','/');			
		break;
		
		case 3:
			document.getElementById('fromlbl').style.display='';
			document.getElementById('tolbl').style.display='none';
			document.getElementById('toalbl').style.display='';
			document.getElementById('ssbtype').value=3;
			document.searchform.sbtype.value=3; // IE Fix
			document.getElementById('roomlayout').style.display='none';
			document.getElementById('flightlayout').style.display='';
			setCookie('searchboxtype', 3,'','/'); 
			getAirports(home,val,1);
			
		break;
	}
	document.getElementById('stype'+typ).checked='true';
}
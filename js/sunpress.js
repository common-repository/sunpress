
var wpss_isop = (navigator.userAgent.toLowerCase().indexOf("opera") != -1);

function jcall(address, callback)
{
	function callFunction()
	{
		if (req1.readyState==4) 
		{
				if (req1.status==200) 
				{
						if (processXML!=null)  
							processXML(req1.responseText);
				}
		}
	}

	var req1 = null;
	var processXML = callback;
    
    if (typeof XMLHttpRequest != "undefined") 
       req1 =  new XMLHttpRequest();
    else if (window.ActiveXObject) 
    {
      var ajs = ["MSXML2.XMLHttp.5.0","MSXML2.XMLHttp.4.0","MSXML2.XMLHttp.3.0","MSXML2.XMLHttp","Microsoft.XMLHttp"];

      for (var i=0; i<ajs.length;i++) 
      {
        try 
        {
            req1 = new ActiveXObject(ajs[i]);
            break;    
        } 
        catch (oError) {
            //Do nothing
        }
      }
    }

	req1.open('GET', address, true);
	req1.onreadystatechange = callFunction;
	req1.send(null);
}
	

function clearlist(elmid,defaultelm,defaultval)
{
	if(empty(getElm(elmid)))
		return;

	while(getElm(elmid).length>0)
		getElm(elmid).remove(0);

	if(!document.all)
		getElm(elmid).add(new Option(defaultelm,defaultval),null);
	else
		getElm(elmid).add(new Option(defaultelm,defaultval));

}

function getElm(val)
{
	return document.getElementById(val);
}

function empty(val)
{
	return(val==''||val==null);
}

function getCookie(name) 
{
  var dc = document.cookie;
  var prefix = name + "=";
  var begin = dc.indexOf("; " + prefix);
  if (begin == -1) {
	begin = dc.indexOf(prefix);
	if (begin != 0) return null;
  } else
	begin += 2;
  var end = document.cookie.indexOf(";", begin);
  if (end == -1)
	end = dc.length;
  return unescape(dc.substring(begin + prefix.length, end));
}

function setCookie(name, value, expires, path, domain, secure) 
{
	  if(expires==null||expires=='')
	  {
	  		expires = new Date();
	  		expires.setDate(expires.getDate()+30);
	  }
	  var curCookie = name + "=" + escape(value) +
		  ((expires) ? "; expires=" + expires.toGMTString() : "") +
		  ((path) ? "; path=" + path : "") +
		  ((domain) ? "; domain=" + domain : "") +
		  ((secure) ? "; secure" : "");
	  document.cookie = curCookie;
}
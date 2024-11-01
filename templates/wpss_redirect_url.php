<?

global $wp_sunshine;

if(!empty($_REQUEST["wpss_redir"]) && substr($_REQUEST["wpss_redir"],0,strlen("http://www.sunshine.co.uk"))=="http://www.sunshine.co.uk")
{
	$redirect_url = wpss_affiliate_link($_REQUEST["wpss_redir"]);
	header("location:$redirect_url");
	exit();
}

?> 
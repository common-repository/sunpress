<?

global $wp_sunshine;

$redirect_url = wpss_affiliate_link("http://www.sunshine.co.uk/ver2/redirect.php?booktype=".$_POST["booktype"]."&qid=".$_POST["qid"].(!empty($_POST["fid"])?"&fid=".$_POST["fid"]:"").(!empty($_POST["tid"])?"&tid=".$_POST["tid"]:""),$wp_sunshine->affiliate_id);

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="en-US">
<head profile="http://gmpg.org/xfn/11">
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title>sunshine.co.uk redirect</title>
<meta name='robots' content='noindex,nofollow' />
<meta http-equiv="refresh" content="3;url=<?php echo $redirect_url;?>"/>
<link rel="stylesheet" href="<?php echo get_option('home');?>/wp-content/plugins/sunpress/css/sunpress.css" type="text/css" />
</head>
<body>
<div align="center">

	<div class="wpss_redir">
			
			<img src="<?php echo get_option('home');?>/wp-content/plugins/sunpress/images/sunshine.gif" />

		<p>You will now be redirected to <a href="<?php echo $redirect_url;?>">sunshine.co.uk</a> who supply your holiday.</p>
		
		<p>If you are not redirected in 5 seconds, please <a href="<?php echo $redirect_url;?>">click here</a>.</p>
		
		<p align="center"><img src="<?php echo get_option('home');?>/wp-content/plugins/sunpress/images/aniloader.gif" /></p>
		<div class="sp"></div>
	</div>

</div>
</body>
</html>
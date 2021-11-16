<?php
	function grab_dump($var){
	    ob_start();
	    var_dump($var);
	    return ob_get_clean();
	}

	$cPath = ini_get('include_path');
	ini_set('include_path', ini_get('include_path').':/usr/local/bin:/usr/bin:/bin');
	
	require_once __DIR__."/../src/Config.php";
	$config = new Config(__DIR__."/../settings/Config.ini");
	
	$commands = unserialize(TERMINAL_COMMANDS);
	$sendEmail = false;
	$log = true;
	
	$output = shell_exec('ps -ef | grep python');
	
	$logBody = "////////////////////\n".date("Y-m-d H:i:s")."\n";
	$logBody .= $output."";
	//$strOut = array();
	putenv("http_proxy=http://gs600-squid.ndc.nasa.gov:443");
	putenv("https_proxy=http://gs600-squid.ndc.nasa.gov:443");
	putenv("http_export=http://gs600-squid.ndc.nasa.gov:443");
    foreach($commands as $cmd => $name){
        $is = strpos($output, $cmd);
        //$strOut[] = grab_dump($is);
        if ($is !== false) {
	        //Command is running. Continue...
	        $logBody .= 'SKIPPING: '.$name."\n";
        }else{
	        $log = true;
	        $outputExec = shell_exec('PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/var/lib/gems/1.8/bin nohup '.$cmd.' > /dev/null 2>/dev/null &');
			$logBody .= 'RUNNING: '.$name." (".$cmd.")\n";
        }
    }
	
	
	//Write log
	if($log){
		//$logBody .= ''.print_r($strOut, true)."\n";
		file_put_contents('/var/www/api.helioviewer.org/scripts/check_data_injection.log', $logBody."\n\n", FILE_APPEND);
	}
	
	
	//Send email
	/*
	if($sendEmail){
		$to = HV_CONTACT_EMAIL;
		$subject = "Helioviewer.org Data Injection Failure: ";
		$from = 'Helioviewer.org <noreply@helioviewer.org>';
		
		$body = '<b>Restarted:</b> '.$commandsExecuted.'<br/>';
		
		$headers = "From: " .($from) . "\r\n";
		$headers .= "Reply-To: ".($from) . "\r\n";
		$headers .= "Return-Path: ".($from) . "\r\n";
		$headers .= "MIME-Version: 1.0\r\n";
		$headers .= "Content-Type: text/html; charset=ISO-8859-1\r\n";
		$headers .= "X-Priority: 3\r\n";
		$headers .= "X-Mailer: PHP". phpversion() ."\r\n";
		
		
		$out = mail($to,$subject,$body,$headers);
	}
	*/

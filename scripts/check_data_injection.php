<?php
	require_once __DIR__."/../src/Config.php";
	$config = new Config(__DIR__."/../settings/Config.ini");
	
	$commands = unserialize(TERMINAL_COMMANDS);
	$sendEmail = false;
	$commandsExecuted = '';
	
	$output = shell_exec('ps -ef | grep python');
	
    foreach($commands as $cmd => $name){
        if (strpos($output, $cmd) !== false) {
	        //Command is running. Continue...
        }else{
	        $sendEmail = true;
	        $output = shell_exec('nohup '.$cmd.' > /dev/null 2>/dev/null &');
			$commandsExecuted .= 'nohup '.$cmd.'&<br/>';
        }
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
		$headers .= "Return-Path: ".($from) . "\r\n";;
		$headers .= "MIME-Version: 1.0\r\n";
		$headers .= "Content-Type: text/html; charset=ISO-8859-1\r\n";
		$headers .= "X-Priority: 3\r\n";
		$headers .= "X-Mailer: PHP". phpversion() ."\r\n";
		
		
		$out = mail($to,$subject,$body,$headers);
	}
	*/
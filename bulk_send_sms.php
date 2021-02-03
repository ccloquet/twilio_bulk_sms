<?php

	// quick and dirty bulk send with Twilio 
	// + show the progress of the sending 
	// + show the answers

	// 1. Dowload the Twilio PHP SDK & install it in the Twilio folder
	// 2. Create an account and copy-paste the account SID & secret below
	// 3. create a notify service and a phone number, and paste them in the html file
	//
	//
	// Enhancement : put everything on the client side (NO PHP, only JS. The client just have top paste the 4 ids (account SID & secret, notify sid, phone number)

	header("Access-Control-Allow-Origin: *");

	if (!isset($_POST['MY_NOTIFY_SID'])) 	exit_with_error('no SID');	$MY_NOTIFY_SID = $_POST['MY_NOTIFY_SID'];		
	if (!isset($_POST['MY_NOTIFY_NR'])) 	exit_with_error('no NR');	$MY_NOTIFY_NR  = $_POST['MY_NOTIFY_NR'];
	
	// TWILIO
	define('ACCOUNT_SID', '****');
	define('AUTH_TOKEN',  '****');

	require_once('Twilio/autoload.php'); 
	use Twilio\Rest\Client;
	use Twilio\Security\RequestValidator;

	$client 	= new Client(ACCOUNT_SID, AUTH_TOKEN);

	
	switch ($_POST['action'])
	{
		case 'send':
			// first send to sebastien et moi
			// wait 5 minutes	
			$ret   = ''	;
			$msg   = $_POST['msg'];			
			$dests = $_POST['dests'];		if (sizeof($dests) > 5000) 	exit_with_error('too many dests');

			$to_binding	= [];

			for ($i=0; $i<sizeof($dests); ++$i)
			{
				if (!is_numeric($dests[$i]))
				{
					$ret .= $dests[$i] . ' invalide - ';
					continue;
				}
				$to_binding[]           = '{"binding_type":"sms","address":"'.$dests[$i].'"}';
			}
		
			$sid = send_bulk_sms($MY_NOTIFY_SID, $to_binding, $msg, $client, null);

			$ret .= 'SID = ' . $sid;
			die($ret);

			break;

		case 'result':
			$statuses       = ['delivered' => 0, 'undelivered'=>0, 'sent'=> 0, 'failed'=> 0];
			$threehoursago 	=  new \DateTime('NOW');
			$threehoursago->modify( '-6 hours' ); //1 year
			$messages 	= $client->messages->read(["dateSentAfter" => $threehoursago,"from" => $MY_NOTIFY_NR ], 10000);
			
			$undev = 0;
			foreach ($messages as $record) 
			{
				$s = $record->status;
				if (!isset($statuses[$s])) $statuses[$s] = 0;
				++$statuses[$s];
				if ($s != 'delivered')
				{
					if (! isset($statuses['undelivered_numbers'])) $statuses['undelivered_numbers'] = '';
					$statuses['undelivered_numbers'] .= ($undev%8==0?(chr(10).chr(13)):' ') . substr($record->to,1);

					++$undev;
				}
			}
			echo json_encode($statuses);
			break;

		case 'responses':
			$responses 	= [];
			$threehoursago 	=  new \DateTime('NOW');
			$threehoursago->modify( '-6 hours' );
			$messages 	= $client->messages->read(["dateSentAfter" => $threehoursago, "to" => $MY_NOTIFY_NR ], 10000);

			foreach ($messages as $record) 
			{
				$responses[] .=  $record->dateSent->format('d/d H:i') . ' | ' . $record->from . ' | ' . $record->body;
			}
			echo json_encode($responses);
			break;			
	}

	function send_bulk_sms($twilio_notify_sid, $to_binding, $body, $client, $callback)
	{
        	$body = str_replace( '{url_indiv}', '', $body);
	        $body = str_replace( '{url_func}',  '', $body);

        	$retval = false;

	        try
        	{
                	$message                = $client->notify->services($twilio_notify_sid)->notifications->create(["toBinding"=>$to_binding,"body"=>$body, 'statusCallback' => $callback]);

	                $retval = $message->sid;
        	}
	        catch (Exception $e)
        	{
                	$retval = false;
	        }
        	return $retval;
	}

	function exit_with_error($txt)
	{
		die('ERROR ' . $txt);
	}
	 

?>

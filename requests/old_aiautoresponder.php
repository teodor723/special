<?php
header('Content-Type: application/json');
require_once('../assets/includes/core.php');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
	switch (secureEncode($_GET['action'])) {

		case 'respond':
			$arr = array();
			$fake = secureEncode($_GET['uid1']);
			$client = secureEncode($_GET['uid2']);
			$check = getData('users','fake','where id ='.$fake);

			if($check == 1){

				$msg = answerMessageAI($fake,$client);
				if(strpos($msg , "Me:") !== false){
					$msgArr= explode('Me:',$msg);
					$msg = $msgArr[0];
				}
				
				$arr['send'] = 'YES';
				$name = getData('users','name','where id ='.$fake);
				$arr['name'] = explode(' ',trim($name));	
				$arr['photo'] = profilePhoto($fake);
				$arr['msg'] = $msg;

			} else {
				$arr['send'] = 'NO';
			}

			echo json_encode($arr);

		break;

		default:

		break;
	}
}


function answerMessageAI($fake,$client){	
	global $mysqli,$sm;
	$message = '';

	$select = 'SELECT * FROM (
	  SELECT * 
	  FROM chat 
	  WHERE (r_id = '.$fake.' AND s_id = '.$client.') 
	    OR (r_id = '.$client.' AND s_id = '.$fake.')  
	  ORDER BY id DESC
	  LIMIT 10
	) AS `table` ORDER by id ASC';

	$query = $mysqli->query($select);
	$i = 0;
	if ($query->num_rows > 0) { 
		while($q = $query->fetch_object()){
			$i++;
			if($q->s_id == $client){
				$message.= 'You: '.$q->message.'\n';				
			} else {
				$message.= 'Friend: '.$q->message.'\n';				
			}
			if($i == $query->num_rows){
				$message.= 'Friend:';
			}
		}	
	}
	return answerToConv($message);
}

function answerToConv($conv){
	global $sm;
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/completions');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

	$mood = $sm['plugins']['aiautoresponder']['responder_mood'];

	if($mood == 'Random'){
		$moods = getData('plugins_settings','setting_options','WHERE setting = "responder_mood"');
		$moodsArray = explode(',',$moods);
		unset($moodsArray[11]);
		$r = array_rand($moodsArray,1);
		$mood = $moodsArray[$r];
	}
	
	$promt = "Act as ".$mood." girl and continue the following conversation: ".$conv;	

	curl_setopt($ch, CURLOPT_POSTFIELDS, '{
	  "model": "text-davinci-003",
	  "prompt": "'.$promt.'",
	  "temperature": 1.0,
	  "max_tokens": 240,
	  "top_p": 1.0,
	  "frequency_penalty": 0.5,
	  "presence_penalty": 0.0,
	  "stop": ["You:"]
	}');

	$headers = array();
	$headers[] = 'Content-Type: application/json';
	$headers[] = 'Authorization: Bearer '.$sm['plugins']['aiautoresponder']['secret'];
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

	$result = curl_exec($ch);
	if (curl_errno($ch)) {
	    echo 'Error:' . curl_error($ch);
	}
	curl_close($ch);

	$answer = json_decode($result);	
	return $answer->choices[0]->text;
}
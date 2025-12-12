<?php
header('Content-Type: application/json');
require_once('../assets/includes/core.php');
require_once('./auth_middleware.php');

// Require authentication for AI autoresponder
// This could be system-level, so allow it to run from localhost without auth
if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') {
    requireAuth();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
	switch (secureEncode($_GET['action'])) {

		case 'respond':
			$arr = array();
			$fake = secureEncode($_GET['uid1']);
			$client = secureEncode($_GET['uid2']);
			$check = getData('users','fake','where id ='.$fake);

			if($check == 1){
			    $msg = answerMessageAI($fake,$client);
			    if($msg==""){
			        $arr['send'] = 'NO';
			    } else {
    				if(strpos($msg , "Me:") !== false){
    					$msgArr= explode('Me:',$msg);
    					$msg = $msgArr[0];
    				}
    				
    				$arr['send'] = 'YES';
    				$name = getData('users','name','where id ='.$fake);
    				$arr['name'] = explode(' ',trim($name));	
    				$arr['photo'] = profilePhoto($fake);
    				$arr['msg'] = $msg;
			    }

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
	$messages=[];

	$select = 'SELECT * FROM (
	  SELECT * 
	  FROM chat 
	  WHERE (r_id = '.$fake.' AND s_id = '.$client.') 
	    OR (r_id = '.$client.' AND s_id = '.$fake.')  
	  ORDER BY id DESC
	  LIMIT 30
	) AS `table` ORDER by id ASC';

	$query = $mysqli->query($select);
	$i = 0;
	$fake_name = getData('users','name','where id ='.$fake);
	$fake_name=explode(" ",trim($fake_name))[0];
	$client_name = getData('users','name','where id ='.$client);
	$client_name=explode(" ",trim($client_name))[0];
	$already_sent_messages=0;
	if ($query->num_rows > 0) { 
		while($q = $query->fetch_object()){
			$i++;
			if($q->s_id == $client){
				array_push($messages,['role'=>'user','name'=>$client_name,'content'=>$q->message]);
				$message.= $client_name.': '.$q->message.'\n';				
			} else {
			    $already_sent_messages+=1;
			    array_push($messages,['role'=>'assistant','name'=>$fake_name,'content'=>$q->message]);
				$message.= $fake_name.': '.$q->message.'\n';				
			}
			if($i == $query->num_rows){
				$message.= $fake_name.':';
			}
		}
	}
	$range=explode("-",$sm['plugins']['aiautoresponder']['responder_max_messages']);
    $stop_number=0;
    if(isset($range[0]) && (int)$range[0]){
        $stop_number=(int)$range[0];
    }
    if(isset($range[1]) && (int)$range[1]){
        $stop_number=rand((int)$range[0],(int)$range[1]);
    }
    if($stop_number > 0 && $already_sent_messages >= $stop_number) {
        return '';
    }
	return answerToConv($messages,$message,$fake_name,$client_name,$fake,$client);
}
function preparePrompt($fake, $client, $template) {
    $data = [
        "name_of_real_user" => getData('users', 'name', 'where id =' . $client),
        "gender_of_real_user" => getData('users', 'gender', 'where id =' . $client),
        "age_of_real_user" => getData('users', 'age', 'where id =' . $client),
        "birthday_of_real_user" => getData('users', 'birthday', 'where id =' . $client),
        "city_of_real_user" => getData('users', 'city', 'where id =' . $client),
        "country_of_real_user" => getData('users', 'country', 'where id =' . $client),
        "name_of_fake_user" => getData('users', 'name', 'where id =' . $fake),
        "gender_of_fake_user" => getData('users', 'gender', 'where id =' . $fake),
        "age_of_fake_user" => getData('users', 'age', 'where id =' . $fake),
        "birthday_of_fake_user" => getData('users', 'birthday', 'where id =' . $fake),
        "city_of_fake_user" => getData('users', 'city', 'where id =' . $fake),
        "country_of_fake_user" => getData('users', 'country', 'where id =' . $fake)
    ];

    foreach ($data as $key => $value) {
        $template = str_replace("%$key%", $value, $template);
    }

    return $template;
}
function answerToConv($conv,$conversation,$fake_name,$client_name,$fake,$client){
	global $sm;
	$ch = curl_init();
	$mood = $sm['plugins']['aiautoresponder']['responder_mood'];

	if($mood == 'Random'){
		$moods = getData('plugins_settings','setting_options','WHERE setting = "responder_mood"');
		$moodsArray = explode(',',$moods);
		unset($moodsArray[11]);
		$r = array_rand($moodsArray,1);
		$mood = $moodsArray[$r];
	}
	$model = $sm['plugins']['aiautoresponder']['responder_model'];
    $apiUrl = ($model == "gpt-3.5-turbo" || $model == "gpt-4")?'https://api.openai.com/v1/chat/completions':'https://api.openai.com/v1/completions';
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    if ($apiUrl == 'https://api.openai.com/v1/completions') {
        $str = preparePrompt($fake, $client, $sm['plugins']['aiautoresponder']['responder_prompt']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            "model" => $model,
            "prompt" => $str,
            "temperature" => 1.0,
            "max_tokens" => 240,
            "top_p" => 1.0,
            "frequency_penalty" => 0.5,
            "presence_penalty" => 0.0,
            "stop" => ["$client_name:"],
            "user" => $client_name
        ]));
    } else {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            "model" => $model,
            "messages" => $conv,
            "temperature" => 1.0,
            "max_tokens" => 240,
            "top_p" => 1.0,
            "frequency_penalty" => 1.0,
            "presence_penalty" => 1.5,
            "user" => $client_name
        ]));
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $sm['plugins']['aiautoresponder']['secret']
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
    }
    curl_close($ch);
    $answer = json_decode($result);
    if ($apiUrl == 'https://api.openai.com/v1/completions') {
        return $answer->choices[0]->text ?? '';
    } else {
        return $answer->choices[0]->message->content ?? '';
    }
    
// 	if($sm['plugins']['aiautoresponder']['responder_model']=="gpt-3.5-turbo-instruct" || $sm['plugins']['aiautoresponder']['responder_model']=="babbage-002" || $sm['plugins']['aiautoresponder']['responder_model']=="davinci-002" || $sm['plugins']['aiautoresponder']['responder_model']=="text-davinci-003") {
// 	    curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/completions');
// 	    $conv=$conversation;
// 	    $str = str_replace('"','',$sm['plugins']['aiautoresponder']['responder_prompt']);
//     	$str = str_replace('.','',$str);
//     	$str = str_replace("'","",$str);
//     	$name_of_real_user = getData('users','name','where id ='.$client);
// 	    $gender_of_real_user = getData('users','gender','where id ='.$client);
// 	    $age_of_real_user = getData('users','age','where id ='.$client);
// 	    $birthday_of_real_user = getData('users','birthday','where id ='.$client);
// 	    $city_of_real_user = getData('users','city','where id ='.$client);
// 	    $country_of_real_user = getData('users','country','where id ='.$client);
// 	    $name_of_fake_user = getData('users','name','where id ='.$fake);
//         $gender_of_fake_user = getData('users','gender','where id ='.$fake);
//         $age_of_fake_user = getData('users','age','where id ='.$fake);
//         $birthday_of_fake_user = getData('users','birthday','where id ='.$fake);
//         $city_of_fake_user = getData('users','city','where id ='.$fake);
//         $country_of_fake_user = getData('users','country','where id ='.$fake);
// 	    $str = str_replace('"','',$sm['plugins']['aiautoresponder']['responder_prompt']);
//     	$str = str_replace('.','',$str);
//     	$str = str_replace("'","",$str);
//     	$str = str_replace("%name_of_real_user%",$name_of_real_user,$str);
//     	$str = str_replace("%gender_of_real_user%",$gender_of_real_user,$str);
//     	$str = str_replace("%age_of_real_user%",$age_of_real_user,$str);
//     	$str = str_replace("%birthday_of_real_user%",$birthday_of_real_user,$str);
//     	$str = str_replace("%city_of_real_user%",$city_of_real_user,$str);
//     	$str = str_replace("%country_of_real_user%",$country_of_real_user,$str);
//     	$str = str_replace("%name_of_fake_user%",$name_of_fake_user,$str);
//         $str = str_replace("%gender_of_fake_user%",$gender_of_fake_user,$str);
//         $str = str_replace("%age_of_fake_user%",$age_of_fake_user,$str);
//         $str = str_replace("%birthday_of_fake_user%",$birthday_of_fake_user,$str);
//         $str = str_replace("%city_of_fake_user%",$city_of_fake_user,$str);
//         $str = str_replace("%country_of_fake_user%",$country_of_fake_user,$str);
//     	eval("\$promt = \"$str\";");
// 	    curl_setopt($ch, CURLOPT_POSTFIELDS, '{
//     	  "model": "'.$sm['plugins']['aiautoresponder']['responder_model'].'",
//     	  "prompt": "'.$promt.'",
//     	  "temperature": 1.0,
//     	  "max_tokens": 240,
//     	  "top_p": 1.0,
//     	  "frequency_penalty": 0.5,
//     	  "presence_penalty": 0.0,
//     	  "stop": ["'.$client_name.':"],
//     	  "user": "'.$client_name.'"
//     	}');
// 	} else {
// 	    curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
// 	    $convs=$conv;
// 	    $conv=$conversation;
// 	    $name_of_real_user = getData('users','name','where id ='.$client);
// 	    $gender_of_real_user = getData('users','gender','where id ='.$client);
// 	    $age_of_real_user = getData('users','age','where id ='.$client);
// 	    $birthday_of_real_user = getData('users','birthday','where id ='.$client);
// 	    $city_of_real_user = getData('users','city','where id ='.$client);
// 	    $country_of_real_user = getData('users','country','where id ='.$client);
// 	    $name_of_fake_user = getData('users','name','where id ='.$fake);
//         $gender_of_fake_user = getData('users','gender','where id ='.$fake);
//         $age_of_fake_user = getData('users','age','where id ='.$fake);
//         $birthday_of_fake_user = getData('users','birthday','where id ='.$fake);
//         $city_of_fake_user = getData('users','city','where id ='.$fake);
//         $country_of_fake_user = getData('users','country','where id ='.$fake);
// 	    $str = str_replace('"','',$sm['plugins']['aiautoresponder']['responder_prompt']);
//     	$str = str_replace('.','',$str);
//     	$str = str_replace("'","",$str);
//     	$str = str_replace("%name_of_real_user%",$name_of_real_user,$str);
//     	$str = str_replace("%gender_of_real_user%",$gender_of_real_user,$str);
//     	$str = str_replace("%age_of_real_user%",$age_of_real_user,$str);
//     	$str = str_replace("%birthday_of_real_user%",$birthday_of_real_user,$str);
//     	$str = str_replace("%city_of_real_user%",$city_of_real_user,$str);
//     	$str = str_replace("%country_of_real_user%",$country_of_real_user,$str);
//     	$str = str_replace("%name_of_fake_user%",$name_of_fake_user,$str);
//         $str = str_replace("%gender_of_fake_user%",$gender_of_fake_user,$str);
//         $str = str_replace("%age_of_fake_user%",$age_of_fake_user,$str);
//         $str = str_replace("%birthday_of_fake_user%",$birthday_of_fake_user,$str);
//         $str = str_replace("%city_of_fake_user%",$city_of_fake_user,$str);
//         $str = str_replace("%country_of_fake_user%",$country_of_fake_user,$str);
//         eval("\$promt = \"$str\";");
// 	    curl_setopt($ch, CURLOPT_POSTFIELDS, '{
//     	  "model": "'.$sm['plugins']['aiautoresponder']['responder_model'].'",
//     	  "messages": '.json_encode($convs).',
//     	  "temperature": 1.0,
//     	  "max_tokens": 240,
//     	  "top_p": 1.0,
//     	  "frequency_penalty": 1.0,
//     	  "presence_penalty": 1.5,
//     	  "stop": ["You:"],
//     	  "user": "'.$client_name.'"
//     	}');
// 	}
// 	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
// 	curl_setopt($ch, CURLOPT_POST, 1);
// 	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
// 	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

// 	$headers = array();
// 	$headers[] = 'Content-Type: application/json';
// 	$headers[] = 'Authorization: Bearer '.$sm['plugins']['aiautoresponder']['secret'];
// 	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

// 	$result = curl_exec($ch);
// 	if (curl_errno($ch)) {
// 	    echo 'Error:' . curl_error($ch);
// 	}
// 	curl_close($ch);

// 	$answer = json_decode($result);
// 	if($sm['plugins']['aiautoresponder']['responder_model']=="gpt-3.5-turbo-instruct" || $sm['plugins']['aiautoresponder']['responder_model']=="babbage-002" || $sm['plugins']['aiautoresponder']['responder_model']=="davinci-002" || $sm['plugins']['aiautoresponder']['responder_model']=="text-davinci-003") {
// 	    return $answer->choices[0]->text;
// 	} else {
// 	    return $answer->choices[0]->message->content;
// 	}
}
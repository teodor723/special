<?php
ini_set('max_execution_time',90);
require('assets/includes/config.php');
$mysqli = new mysqli($db_host, $db_username, $db_password,$db_name);
if (mysqli_connect_errno()) {
    exit(mysqli_connect_error());
}
$updated = false;
$aV = secureEncode($_GET['version']);
$pluginInstallation = false;
if (strpos($aV, 'plugin') !== false) {
	$pluginInstallation = true;
	$l = secureEncode($_GET['l']);
	$aV = str_replace('plugin-','',$aV);
	$url = 'https://premiumdatingscript.com/clients/install_updates.php';
	$query_array = array (
	    'l' => $l,
	    'install' => 'YES',
	    'plugin' => $aV
	);
	$query = http_build_query($query_array);
	$plugin = json_decode(file_get_contents($url . '?' . $query));
	$newUpdate = file_get_contents($plugin->href);	

	$dlHandler = fopen('updates/update-'.$aV.'.zip', 'w');
	if ( !fwrite($dlHandler, $newUpdate) ) { exit(); }
	fclose($dlHandler);
} else {
	if (!file_exists('updates/update-'.$aV.'.zip' )) {
		$newUpdate = file_get_contents('https://www.premiumdatingscript.com/updates/belloo/update-'.$aV.'.zip');
		$dlHandler = fopen('updates/update-'.$aV.'.zip', 'w');
		if ( !fwrite($dlHandler, $newUpdate) ) { exit(); }
		fclose($dlHandler);
	}
}

$zip = new ZipArchive;
$zipHandle = $zip->open('updates/update-'.$aV.'.zip');	

$total = $zip->count();
for ($i=0; $i<$total; $i++) {
	
	$c_file = $zip->statIndex($i);

	$thisFileName = $c_file['name'];
	$thisFileDir = dirname($thisFileName);
	//Continue if its not a file

	if (!is_dir($thisFileDir ) ){
		 mkdir($thisFileDir,0777,true);
	}

	if ( !is_dir($thisFileName) ) {
		$contents = $zip->getFromIndex($i);
		$contents = str_replace("\r\n", "\n", $contents);
		$updateThis = '';
		if ( $thisFileName == 'upgrade.php' ){
			$upgradeExec = fopen ('upgrade.php','w');
			fwrite($upgradeExec, $contents);
			fclose($upgradeExec);
			include ('upgrade.php');
			unlink('upgrade.php');
		} else if ($thisFileName == 'upgrade.sql'){
			global $mysqli;
			$sqlExec = fopen('upgrade.sql','w');
			fwrite($sqlExec, $contents);
			fclose($sqlExec);
			
			mysqli_report(MYSQLI_REPORT_OFF);	
			$queries = file_get_contents("upgrade.sql");
			try {
				$mysqli->multi_query($queries);
			} catch (Exception $e) {

        	}

			if($mysqli->more_results()){
				while ($mysqli->next_result()) {
					if (!$mysqli->more_results()){
						break;
					} 
				}
			}
			unlink('upgrade.sql');
		} else {
			if (substr($thisFileName,-1,1) == '/') continue;
			$updateThis = fopen($thisFileName, 'w');
			fwrite($updateThis, $contents);
			fclose($updateThis);
			unset($contents);
		}
		$updated = true;
	}	
}

$zip->close();

if ($updated == true){

	if(!$pluginInstallation){
		$mysqli->query("UPDATE settings set setting_val = '$aV' where setting = 'currentVersion'");
	    $mysqli->query("UPDATE settings SET setting_val = 'No' WHERE setting = 'updateAvailable'");
	    $mysqli->query("UPDATE settings SET setting_val = '0' WHERE setting = 'checkUpdate'");
	}

    unlink('updates/update-'.$aV.'.zip');
    if(isset($_GET['installer'])){
    	echo $aV;
    } else {
		header('Location: index.php?page=admin&p=main_dashboard&updated='.$aV);
	    exit;	
    }
}

function secureEncode($string,$strip=1) {
    global $mysqli;
    $string = trim($string);
    $string = mysqli_real_escape_string($mysqli, $string);
    $string = htmlspecialchars($string, ENT_QUOTES);
    $string = str_replace('\\r\\n', '<br>',$string);
    $string = str_replace('\\r', '<br>',$string);
    $string = str_replace('\\n\\n', '<br>',$string);
    $string = str_replace('\\n', '<br>',$string);
    $string = str_replace('\\n', '<br>',$string);
    if ($strip == 1) {
        $string = stripslashes($string);
    }
    $string = str_replace('&amp;#', '&#',$string);
    return $string;
}

?>
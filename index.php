<?php define('A', 'jan33.tphu5583htr/suruga::goods/^^!!.html|[word]/[word]^/[2:5]-!/::');  ?>
<?php

/* Belloo Software by https://premiumdatingscript.com */
if(!file_exists("assets/includes/config.php")){
	$actual_link = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
	$cb = substr($actual_link, -1);
	if($cb == '/'){
		header('Location: '.$actual_link.'install/');	
	} else {
		header('Location: '.$actual_link.'/install/');	
	}	
	exit;
}
require_once('assets/includes/core.php');

if (!isset($_GET['page']) && !isset($_GET['social'])) {
    $_GET['page'] = 'index';
}


if(isset($_GET['page'])){
	switch ($_GET['page']) {
		case 'index':
			if(isset($_GET['landing'])){
				showLandingPage($_GET['landing']);
				exit;				
			} else {
				if ($logged !== true ) {		
					showLandingPage();
					exit;
				} else {
					$_GET['page'] = 'meet';
					$folder = 'meet';
					$page = 'content';
					include('assets/sources/pages.php');		
				}
			}
		break;
		case 'cronner':
			checkcron();
		break;
		case 'fb':
			if($_SESSION['new_user'] == 1){
				showLandingPage();
				exit;
			} else {
				showLandingPage();
				exit;				
			}
		break;	

		case 'profile':
			if ($logged === false || isset($_GET['view'])) {
				$pid = secureEncode($_GET['id']);
				getUserInfo($pid,1);
				$container = getPage('profileLanding');	
				if($sm['plugins']['htmlsecurity']['enabled'] == 'Yes'){
					$container = preg_replace('/\r|\n/','',$container);
				}
				echo $container;
				exit;
			} else {	
				$pid = secureEncode($_GET['id']);
				$checkUsername = checkIfExist('users','username',$pid);
				$checkId = checkIfExist('users','id',$pid);
				if($checkUsername == 0 && $checkId == 0){
					header('Location:'.$sm['config']['site_url']);
					exit;					
				}				
				$new = getUserTotalConv($sm['user']['id'],$_GET['id']);
				$folder = 'profile';
				$page = 'content';
				include('assets/sources/pages.php');
			}
		break;	
		case 'verification':
			if ($logged === false ) {
				showLandingPage();
				exit;
			} else {	
				$ussid = secureEncode($_GET['uid']);
				$mysqli->query('UPDATE users set verified = 1 where id = "'.$ussid.'"');
				$pass = secureEncode($_GET['b']);
				welcomeMailNotification($sm['user']['name'],$sm['user']['email'],$pass);
				header('Location:'.$sm['config']['site_url']);
				exit;			
			}
		break;	

		case 'ref':
			setcookie(
			  "ref",
			  secureEncode($_GET['id']),
			  time() + (10 * 365 * 24 * 60 * 60), '/', NULL, 0
			);
			header('Location:'.$sm['config']['site_url']);
			exit();
		break;

		case 'meet':
			if ($logged !== true ) {
				showLandingPage();
				exit;
			}	
			$folder = 'meet';
			$page = 'content';
			include('assets/sources/pages.php');
		break;

		case 'feed':
			if ($logged !== true ) {
				showLandingPage();
				exit;
			}	
			$folder = 'feed';
			$page = 'content';
			include('assets/sources/pages.php');
		break;

		case 'date':
			if ($logged !== true ) {
				showLandingPage();
				exit;
			}	
			$folder = 'date';
			$page = 'content';
			include('assets/sources/pages.php');
		break;		

		case 'reels':
			if ($logged !== true ) {
				showLandingPage();
				exit;
			}	
			$folder = 'reels';
			$page = 'content';
			include('assets/sources/pages.php');
		break;		

		case 'live':
			if ($logged !== true ) {
				showLandingPage();
				exit;
			}	
			$folder = 'live';
			$page = 'live';
			include('assets/sources/pages.php');
		break;	
		case 'popular':
			if ($logged !== true ) {
				showLandingPage();
				exit;
			}		
			$folder = 'popular';
			$page = 'content';
			include('assets/sources/pages.php');
		break;	
		case 'recover':
			if($_GET['id'] != '' && $_GET['code'] != ''){
			$check = checkRecoverCode($_GET['id'],$_GET['code']);
				if($check > 0){
					$_SESSION['user'] = $_GET['id'];
					header('Location:'.$sm['config']['site_url']);
				} else {
					showLandingPage();
					exit;						
				}
			} else {
				showLandingPage();
				exit;			
			}
		break;

		case 'register':	
			if ($logged === false ) {
				echo getPage('auth/auth');
				exit;
			} else {
				$folder = 'meet';
				$page = 'content';
				include('assets/sources/pages.php');				
			}	
		break;

		case 'login':	
			if ($logged === false ) {
				echo getPage('auth/auth');
				exit;
			} else {
				$folder = 'meet';
				$page = 'content';
				include('assets/sources/pages.php');				
			}			
		break;

		case 'forget':	
			if ($logged === false ) {
				echo getPage('auth/auth');
				exit;
			} else {
				$folder = 'meet';
				$page = 'content';
				include('assets/sources/pages.php');				
			}			
		break;		

		case 'fans':
			if ($logged !== true ) {
				showLandingPage();
				exit;
			}		
			$folder = 'fans';
			$page = 'content';
			include('assets/sources/pages.php');
		break;
		case 'blocked':
			if ($logged !== true ) {
				showLandingPage();
				exit;
			}		
			$folder = 'profile';
			$page = 'blocked';
			include('assets/sources/pages.php');
		break;			
		case 'groups':
			if ($logged !== true ) {
				showLandingPage();
				exit;
			}		
			$folder = 'groups';
			$page = 'content';
			include('assets/sources/pages.php');
		break;			
		case 'visits':
			if ($logged !== true ) {
				showLandingPage();
				exit;
			}		
			$folder = 'visits';
			$page = 'content';
			include('assets/sources/pages.php');
		break;		
		case 'credits-ok':
			if ($logged !== true ) {
				showLandingPage();
				exit;
			}		
			$folder = 'profile';
			$page = 'credits';
			include('assets/sources/pages.php');
		break;	
		case 'credits':
			if ($logged !== true ) {
				showLandingPage();
				exit;
			}		
			$folder = 'profile';
			$page = 'credits';
			include('assets/sources/pages.php');
		break;
		case 'getcredits':
			if ($logged !== true ) {
				showLandingPage();
				exit;
			}		
			$folder = 'profile';
			$page = 'buyCredits';
			include('assets/sources/pages.php');
		break;		
		case 'withdraw':
			if ($logged !== true ) {
				showLandingPage();
				exit;
			}		
			$folder = 'profile';
			$page = 'withdraw';
			include('assets/sources/pages.php');
		break;		
		case 'popularity':
			if ($logged !== true ) {
				showLandingPage();
				exit;
			}		
			$folder = 'profile';
			$page = 'popularity';
			include('assets/sources/pages.php');
		break;	
		case 'premium':
			if ($logged !== true ) {
				showLandingPage();
				exit;
			}		
			$folder = 'profile';
			$page = 'premium';
			include('assets/sources/pages.php');
		break;			
		case 'matches':
			if ($logged !== true ) {
				showLandingPage();
				exit;
			}		
			$folder = 'matches';
			$page = 'content';
			include('assets/sources/pages.php');
		break;	
		case 'mylikes':
			if ($logged !== true ) {
				showLandingPage();
				exit;
			}		
			$folder = 'matches';
			$page = 'mylikes';
			include('assets/sources/pages.php');
		break;	

		case 'terms':
			if ($logged !== true ) {
				showLandingPage();
				exit;
			}		
			$folder = 'terms';
			$page = 'terms';
			include('assets/sources/pages.php');
		break;
		case 'tac':
			echo getLandingPage('index/tac');
			exit;	
		break;
		case 'pp':
			echo getLandingPage('index/pp');
			exit;	
		break;	
		case 'privacy':
			if ($logged !== true ) {
				showLandingPage();
				exit;
			}		
			$folder = 'terms';
			$page = 'privacy';
			include('assets/sources/pages.php');
		break;
		case 'cookies':
			echo getLandingPage('index/cookies');
			exit;	
		break;			
		case 'discover':
			if ($logged !== true ) {
				showLandingPage();
				exit;
			}	
			$folder = 'discover';
			$page = 'content';
			include('assets/sources/pages.php');
		break;
		case 'live-discover':
			if ($logged !== true ) {
				showLandingPage();
				exit;
			}	
			$folder = 'discover';
			$page = 'live-discover';
			include('assets/sources/pages.php');
		break;		

		case 'connect':	
			header('Location:'.$sm['config']['site_url']);
		break;			
		case 'chat':
			if ($logged !== true ) {
				header('Location:'.$sm['config']['site_url']);		
				exit;
			}
			
			$folder = 'chat';
			$lc = getUserLC($sm['user']['id']);
			if(!isset($_GET['id'])){		
				if($lc == 0){
					$sm['profile'] = '';
				} else {
					$sm['profile'] = 'yes';
					$count = getUserTodayConv($sm['user']['id']);
					$new = getUserTotalConv($sm['user']['id'],$lc);					
					if($new == 0 && $count >= $sm['basic']['chat'] && $sm['user']['premium'] == 0 || $new == 0 && $count >= $sm['premium']['chat']){
						$page = 'premium';
					} else {
						$page = 'content';
					}						
				}
			} else {
				$lc = secureEncode($_GET['id']);
				$sm['profile'] = 'yes';
				$count = getUserTodayConv($sm['user']['id']);
				$new = getUserTotalConv($sm['user']['id'],$_GET['id']);
				if($new == 0 && $count >= $sm['basic']['chat'] && $sm['user']['premium'] == 0 || $new == 0 && $count >= $sm['premium']['chat']){
					$page = 'premium';
				} else {
					$page = 'content';
				}	
			}	
			if($sm['profile'] == ''){
				$page = 'empty';
			} 		
			include('assets/sources/pages.php');
		break;
		case 'admin':
			if ($logged !== true || $sm['user']['admin'] == 0) {
				echo getAdministratorPage('login');
				exit;
			}
			$p = '';
			if(isset($_GET['p'])){
				$p = secureEncode($_GET['p']);
			}
			if($p == ''){
				$sm['content'] = getAdministratorPage('main_dashboard');
			} else {
				$sm['content'] = getAdministratorPage($p);	
			}
			echo getAdministratorPage('index');
			exit;
		break;

		case 'cp':
			if ($logged !== true || $sm['user']['admin'] == 0) {
				echo getAdministratorPage('login');
				exit;
			}
			$p = '';
			if(isset($_GET['p'])){
				$p = secureEncode($_GET['p']);
			}
			if($p == ''){
				$sm['content'] = getAdministratorPage('main_dashboard');
			} else {
				$sm['content'] = getAdministratorPage($p);	
			}
			echo getAdministratorPage('index');
			exit;
		break;

		case 'logout':
			include('assets/sources/logout.php');
			exit;
		break;

		case 'debug':
			error_reporting(E_ALL);
			ini_set('display_errors', 1);
			$folder = $_GET['debug_folder'];
			$page = $_GET['debug_page'];
			include('assets/sources/pages.php');			
		break;

		case 'checkPHPinfo':
			phpinfo();
			exit;		
		break;	

		case 'profileLanding':
			$container = getPage('profileLanding');	
			if($sm['plugins']['htmlsecurity']['enabled'] == 'Yes'){
				$container = preg_replace('/\r|\n/','',$container);
			}
			echo $container;
			exit;
		break;	
		default:
			if ($logged !== true ) {		
				header('Location:'.$sm['config']['site_url']);
				exit;
			} else {
				$folder = 'meet';
				$page = 'content';
				include('assets/sources/pages.php');		
			}
		break;	
	}

}

if(isset($_GET['social'])){
	switch ($_GET['social']) {
		case 'fb':
			include('assets/sources/fbconnect.php');
			exit;
		break;
		case 'twitter':
			include('assets/sources/twitterconnect.php');
			exit;
		break;	
		case 'instagram':
			include('assets/sources/instaconnect.php');
			exit;
		break;	
		case 'google':
			include('assets/sources/googleconnect.php');
			exit;
		break;		
	}	
}

function showLandingPage(){
	global $sm;
	$landing = getLandingPage('index/content');	
	if($sm['plugins']['htmlsecurity']['enabled'] == 'Yes'){
		$landing = preg_replace('/\r|\n/','',$landing);
	}
	echo $landing;	
}

$container = getPage('container');	
if($sm['plugins']['htmlsecurity']['enabled'] == 'Yes'){
	$container = preg_replace('/\r|\n/','',$container);
}

echo $container;
$mysqli->close();
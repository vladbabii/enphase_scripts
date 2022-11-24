<?php
$BASE='https://entrez.enphaseenergy.com/';
$USER='your_enphase_email@provider.example.com';
$PASS='your_enphase_password';
$SITE_ID='your site id - in';
$GATEWAY_SERIAL='monitor serial number';
$FILE_TOKEN='token.txt';
$MAX_AGE=12*3600;
$DEBUG=false;

function mylog($str){
	global $DEBUG;
	if($DEBUG){
	echo $str.PHP_EOL;
	}
}
function myresult($token_string){
	echo $token_string;
}

if(is_file($FILE_TOKEN)){
	$old_token=file_get_contents($FILE_TOKEN);
	mylog('% Old token exists...');
	$old_data=json_decode(base64_decode(str_replace('_', '/', str_replace('-','+',explode('.', $old_token)[1]))));
	if(isset($old_data->username) && $old_data->username==$USER){
    	mylog('% Old token is readable and on the same account');
		$t=time();
		if($old_data->exp < $t){
			mylog('% Old token is expired...');
		}elseif($old_data->exp > $t+$MAX_AGE){
			mylog('% Old token is valid and less than '.$MAX_AGE.' old');
			mylog('% Not regenerating token!');
			myresult($old_token);
			exit(0);
		}else{
			mylog('% Old token is valid and more than than '.$MAX_AGE.' old');
		}
	}
}

$CH=null;
$cookiefile='cookies.'.md5($BASE."|".$USER."|".$PASS).'.txt';
 
function ch_start(){
	global $CH;
	global $BASE;
	global $PASS;
	global $cookiefile;
	$CH=curl_init();
	curl_setopt($CH,CURLOPT_COOKIEFILE				,$cookiefile);
	curl_setopt($CH,CURLOPT_COOKIEJAR				,$cookiefile);
	curl_setopt($CH,CURLOPT_FOLLOWLOCATION			,true);
    curl_setopt($CH,CURLOPT_SSL_VERIFYHOST          ,0);
    curl_setopt($CH,CURLOPT_SSL_VERIFYPEER          ,0);
    //curl_setopt($CH,CURLOPT_VERBOSE                 ,true);
}
function ch_end(){
	global $CH;
	curl_close($CH);
}

function ch_headers(){
    global $CH;
    $headers=[
        'dnt: 1',
        'sec-ch-ua: "Google Chrome";v="107", "Chromium";v="107", "Not=A?Brand";v="24',
        'sec-ch-ua-mobile: ?0',
        'sec-ch-ua-platform: "Windows"',
        'sec-fetch-dest: document',
        'sec-fetch-mode: navigate',
        'sec-fetch-site: same-origin',
        'sec-fetch-user: ?1',
        'upgrade-insecure-requests: 1',
        'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/107.0.0.0 Safari/537.36'
    ];
    curl_setopt($CH, CURLOPT_HTTPHEADER, $headers);
}

function ch_get($url){
	global $CH;
	ch_start();
    ch_headers();
	curl_setopt($CH, CURLOPT_URL, $url);
    curl_setopt($CH, CURLOPT_RETURNTRANSFER, 1);
    $output = curl_exec($CH);
	ch_end();
	return $output;
}

function ch_post($url,$data){
	global $CH;
	ch_start();
    ch_headers();
	curl_setopt($CH, CURLOPT_URL, $url);
	curl_setopt($CH, CURLOPT_POST, 1);
	curl_setopt($CH, CURLOPT_POSTFIELDS, $data);
    curl_setopt($CH, CURLOPT_RETURNTRANSFER, 1);
    $output = curl_exec($CH);
	ch_end();
	return $output;
}

function ch_post_urlencoded($url,$data){
	global $CH;
	ch_start();
    ch_headers();
	curl_setopt($CH, CURLOPT_URL, $url);
	curl_setopt($CH, CURLOPT_POST, 1);
	curl_setopt($CH, CURLOPT_POSTFIELDS, $data);
    curl_setopt($CH, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($CH, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
    $output = curl_exec($CH);
	ch_end();
	return $output;
}

function get_string_between($string, $start, $end){
    $string = ' ' . $string;
    $ini = strpos($string, $start);
    if ($ini == 0) return '';
    $ini += strlen($start);
    $len = strpos($string, $end, $ini) - $ini;
    return substr($string, $ini, $len);
}

mylog('! Using cookiefile: ',$cookiefile);
if(file_exists($cookiefile)){
	mylog('! cookiefile (exists)');
}
$LASTTOKEN="";

function get_entrez_tokens(){
    global $BASE;
    mylog('> get_entrez_tokens - start ');
    $page=ch_get($BASE.'entrez_tokens');
    if(stripos($page,"/login_main_page")!==false){
        mylog('> login button found, returning false...');
        return false;
    }
    mylog('> returning full page');
    return $page;
}

function get_login_page(){
    global $BASE;
    mylog('$ get login page - start');
    $page=ch_get($BASE.'/login_main_page');
    mylog($page);
    mylog('$ extracting login form...');
    $form=get_string_between($page,'<form','</form>');
    mylog('$ form data: ');
    $list=[];
    $found=true;
    $str='';
    while($found!==false){
        $str=get_string_between($form,'<input','/>');
        if(!is_string($str) || strlen(trim($str))==0){
            $found=false;
        }else{
            $form=str_replace('<input'.$str.'/>','',$form);
            $name=get_string_between($str,'name="','"');
            $value=get_string_between($str,'value="','"');
            if(strlen(trim($name))>0){
                $list[$name]=$value;
                mylog('* ',$name,'=',$value);
            }
        }
    }
    return $list;
}

function do_login($data){
    global $BASE;
    mylog('& logging in...');
    $page=ch_post_urlencoded($BASE.'login',$data);
    return $page;
}

function do_get_token(){
    global $SITE_ID;
    global $GATEWAY_SERIAL;
    global $BASE;
    $data='Site='.$SITE_ID.'&serialNum='.$GATEWAY_SERIAL;
    $page=ch_post_urlencoded($BASE.'entrez_tokens',$data);
    $text=get_string_between($page,'<textarea','</textarea>');
    if(strlen($text)>0){
        $text=get_string_between($text.'<','>','<');
        if(strlen($text)>0){
            return $text;
        }
    }
    return alse;
}


$page=get_entrez_tokens();
if(is_bool($page) && false===$page){
    $login_params=get_login_page();
    $login_params['username']=$USER;
    $login_params['password']=$PASS;
    $str='';
    foreach($login_params as $k=>$v){
        $str.='&'.urlencode($k).'='.urlencode($v);
    }
    $str=trim($str,'&');
    $page=do_login($str);
}
if(stripos($page,'name="Site"')===false || stripos($page,'name="serialNum"')===false){
    $page=get_entrez_tokens();
}
if(stripos($page,'name="Site"')===false || stripos($page,'name="serialNum"')===false){
    mylog('! Cannot get to token form');
}

$token=do_get_token();
$data=json_decode(base64_decode(str_replace('_', '/', str_replace('-','+',explode('.', $token)[1]))));
if(isset($data->username) && $data->username==$USER){
    mylog('^ Valid token!');
    file_put_contents($FILE_TOKEN,$token);
    echo $token;
}

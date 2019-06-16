#!/usr/bin/php
<?php

/***********************************/
/* Do not modify past this point! */
/*********************************/
//Basic terminal colors
$red    = "\033[31m";
$yellow = "\033[33m";
$green  = "\033[32m";
$white  = "\033[0m";

//Check for CLI, no web allowed here for security!
if(php_sapi_name() !== 'cli'){
	die($red."This script can only be used via command line! $white");
}
//Parse command line args
$config = new CommandLine();
$args = $config->parseArgs($argv);

//Begin program output.
echo 'User Script for Mail-In-A-Box Version 0.1
=======================================

This script was built by:
Mitchell Urgero <info@urgero.org> of URGERO.ORG.

';


//Check that we have all args.
if(!isset($args['type']) && !isset($args['help'])){
	die($red."You are missing command line arguments! Please try again! $white \r\n");
} elseif(isset($args['help'])) {
echo '
    Required Arguments:
        --type=        Select type you want to run.
                       Options: usercopy, archive
        
        --hostname=    Hostname of MIAB server.
        
        --username=    Username of an admin account on
                       MIAB server.
    
    Type Argument Options:
        --type=usercopy
            Copy users from one domain to another. Does NOT copy user mail!
            --current-domain=    The current domain to copy users from.
            --new-domain=        The new domain to copy users to.
	    --word=              The password to set for the new user accounts. (NOT SECURE)
        
        --type=archive
            Archive all users in a given domain
            --current-domain=    The domain you want to archive.
';
	die();
}
if(!isset($args['hostname'], $args['username'])){
	die($red."You are missing command line arguments! Please try again! $white \r\n");
}

echo $red."This script is provided AS-IS. By typing 'continue' you agree that this script,\r\nand it's creator is not held liable for any damages done: $white";
$confirmation = readline();

if($confirmation != "continue"){
	die($yellow."You did not type 'continue', this script will now terminate.$white \r\n");
}

echo $yellow."Please enter your admin password [hidden]: $white";
$password = readline_silent();
$username = $args['username'];

switch($args['type']){
	case "usercopy":
		if(!isset($args['current-domain'], $args['new-domain'])){
			die($red."You are missing command line arguments! Please try again! $white \r\n");
		}
		echo '
This script does NOT transfer mail. It only makes new users based on old usernames.
Each user will get a randomly generated password for their new user account.
Once the process is done, you will need to confirm the new user accounts were created!
This process will overwrite a user if it already exists on the new domain.

';
		write($white,"Getting list of all users on the box.");
		$domains = getCurrentDomains();
		//var_dump($users);
		if($domains == 0){
			die($yellow."There was an error trying to authenticate. Please try again.$white \r\n");
		}
		if(count($domains) > 0){
			//We have multiple (or single) domains, lets copy.
			$cur = $args['current-domain'];
			$new = $args['new-domain'];
			write($white,"Processing current user list from $cur...");
			foreach($domains as $domain){
				if($domain['domain'] == $cur){
					foreach($domain['users'] as $user){
						if($user['status'] == "active"){
							//Make new user!
							$temp = explode("@",$user['email']);
							$newUser = $temp[0]."@".$new; //new email!
							$newPass = generateRandomString(12); //new pass!
							if(isset($args['word'])){
								if(strlen($args['word']) > 7){
									$newPass = $args['word'];
								} else {
									write($red,"Error, word argument not long enough.");
									die();
								}
							}
							if(makeNewUser($newUser,$newPass)){
								write($green, "Made new user $newUser. [$newPass]");
							} else {
								write($red,"Error makeing user $newUser");
							}
						} else {
							write($yellow, "User $user is not active. Ignoring.");
						}	
					}
				} else {
					continue; //no further processing.
				}
			}
		}
		break;
	case "archive":
		echo '
This script will archive a whole domain!
This script will also not delete aliases!

';
		echo $red."Are you sure you want to continue?[y/n]$white";
		$conf = readline();
		
		if(strtolower($conf) == "y"){
			if(!isset($args['current-domain'])){
				die($red."You are missing command line arguments! Please try again! $white \r\n");
			}
			$cur = $args['current-domain'];
			write($white,"Getting list of all users on the box.");
			$domains = getCurrentDomains();
			foreach($domains as $domain){
				if($domain['domain'] == $cur){
					write($white,"Processing current user list from $cur...");
					foreach($domain['users'] as $user){
						if(archiveUser($user['email'])){
							write($green, "Archived user ".$user['email'].".");
						} else {
							write($red, "Error archiving user ".$user['email']. ". Check logs for details.");
						}
					}
				}
			}
		} else {
			die($yellow."Action cancelled!$white \r\n");
		}
		break;
	default:
		die($red."You are missing command line arguments! Please try again! $white \r\n");
		break;
	
}

function archiveUser($u){
	global $args,$username,$password;
	$fields_string = "";
	$url = "https://".$args['hostname']."/admin/mail/users/remove";
	$fields = array(
		"email"    => urlencode($u)
		);
	foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
	rtrim($fields_string, '&');
	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch,CURLOPT_POST, count($fields));
	curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	$data = curl_exec($ch);
	curl_close($ch);
	
	if($data){
		return true;
	} else {
		return false;
	}
	
}
function makeNewUser($u, $p){
	global $args,$username,$password;
	$fields_string = "";
	$url = "https://".$args['hostname']."/admin/mail/users/add";
	$fields = array(
		"email"    => urlencode($u),
		"password" => urlencode($p)
		);
	foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
	rtrim($fields_string, '&');
	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch,CURLOPT_POST, count($fields));
	curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	$data = curl_exec($ch);
	curl_close($ch);
	if($data){
		return true;
	} else {
		return false;
	}
}
function getCurrentDomains(){
	global $args,$username,$password;
	$url = "https://".$args['hostname']."/admin/mail/users?format=json";
	$json = "";
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	$data = curl_exec($ch);
	curl_close($ch);
	if(json_decode($data)){
		$json = json_decode($data,true);
	} else {
		return 0;
	}
	
	return $json;
}

function write($color,$text = ""){
	global $white,$red,$yellow,$green;
	$time = date("h:i:s");
	echo $white."[".$time."] ".$color.$text.$white."\r\n";
}
function readline_silent($prompt = "") {
  if (preg_match('/^win/i', PHP_OS)) {
    $vbscript = sys_get_temp_dir() . 'prompt_password.vbs';
    file_put_contents(
      $vbscript, 'wscript.echo(InputBox("'
      . addslashes($prompt)
      . '", "", "password here"))');
    $command = "cscript //nologo " . escapeshellarg($vbscript);
    $password = rtrim(shell_exec($command));
    unlink($vbscript);
    return $password;
  } else {
    $command = "/usr/bin/env bash -c 'echo OK'";
    if (rtrim(shell_exec($command)) !== 'OK') {
      trigger_error("Can't invoke bash");
      return;
    }
    $command = "/usr/bin/env bash -c 'read -s -p \""
      . addslashes($prompt)
      . "\" mypassword && echo \$mypassword'";
    $password = rtrim(shell_exec($command));
    echo "\n";
    return $password;
  }
}
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}
	
class CommandLine {
    public static $args;
    /**
     * PARSE ARGUMENTS
     * 
     * This command line option parser supports any combination of three types
     * of options (switches, flags and arguments) and returns a simple array.
     * 
     * [pfisher ~]$ php test.php --foo --bar=baz
     *   ["foo"]   => true
     *   ["bar"]   => "baz"
     * 
     * [pfisher ~]$ php test.php -abc
     *   ["a"]     => true
     *   ["b"]     => true
     *   ["c"]     => true
     * 
     * [pfisher ~]$ php test.php arg1 arg2 arg3
     *   [0]       => "arg1"
     *   [1]       => "arg2"
     *   [2]       => "arg3"
     * 
     * [pfisher ~]$ php test.php plain-arg --foo --bar=baz --funny="spam=eggs" --also-funny=spam=eggs \
     * > 'plain arg 2' -abc -k=value "plain arg 3" --s="original" --s='overwrite' --s
     *   [0]       => "plain-arg"
     *   ["foo"]   => true
     *   ["bar"]   => "baz"
     *   ["funny"] => "spam=eggs"
     *   ["also-funny"]=> "spam=eggs"
     *   [1]       => "plain arg 2"
     *   ["a"]     => true
     *   ["b"]     => true
     *   ["c"]     => true
     *   ["k"]     => "value"
     *   [2]       => "plain arg 3"
     *   ["s"]     => "overwrite"
     *
     * @author              Patrick Fisher <patrick@pwfisher.com>
     * @since               August 21, 2009
     * @see                 http://www.php.net/manual/en/features.commandline.php
     *                      #81042 function arguments($argv) by technorati at gmail dot com, 12-Feb-2008
     *                      #78651 function getArgs($args) by B Crawford, 22-Oct-2007
     * @usage               $args = CommandLine::parseArgs($_SERVER['argv']);
     */
    public static function parseArgs($argv){
        array_shift($argv);
        $out                            = array();
        foreach ($argv as $arg){
            // --foo --bar=baz
            if (substr($arg,0,2) == '--'){
                $eqPos                  = strpos($arg,'=');
                // --foo
                if ($eqPos === false){
                    $key                = substr($arg,2);
                    $value              = isset($out[$key]) ? $out[$key] : true;
                    $out[$key]          = $value;
                }
                // --bar=baz
                else {
                    $key                = substr($arg,2,$eqPos-2);
                    $value              = substr($arg,$eqPos+1);
                    $out[$key]          = $value;
                }
            }
            // -k=value -abc
            else if (substr($arg,0,1) == '-'){
                // -k=value
                if (substr($arg,2,1) == '='){
                    $key                = substr($arg,1,1);
                    $value              = substr($arg,3);
                    $out[$key]          = $value;
                }
                // -abc
                else {
                    $chars              = str_split(substr($arg,1));
                    foreach ($chars as $char){
                        $key            = $char;
                        $value          = isset($out[$key]) ? $out[$key] : true;
                        $out[$key]      = $value;
                    }
                }
            }
            // plain-arg
            else {
                $value                  = $arg;
                $out[]                  = $value;
            }
        }
        self::$args                     = $out;
        return $out;
    }
    /**
     * GET BOOLEAN
     */
    public static function getBoolean($key, $default = false){
        if (!isset(self::$args[$key])){
            return $default;
        }
        $value                          = self::$args[$key];
        if (is_bool($value)){
            return $value;
        }
        if (is_int($value)){
            return (bool)$value;
        }
        if (is_string($value)){
            $value                      = strtolower($value);
            $map = array(
                'y'                     => true,
                'n'                     => false,
                'yes'                   => true,
                'no'                    => false,
                'true'                  => true,
                'false'                 => false,
                '1'                     => true,
                '0'                     => false,
                'on'                    => true,
                'off'                   => false,
            );
            if (isset($map[$value])){
                return $map[$value];
            }
        }
        return $default;
    }
}

?>

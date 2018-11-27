<?php
namespace lakwsh\Smtp;

class Smtp{
	private static $smtp_port;
	private static $time_out;
	private static $host_name;
	private static $log_file;
	private static $relay_host;
	private static $debug;
	private static $auth;
	private static $user;
	private static $pass;
	private static $sock;
	public function __construct($relay_host='',$smtp_port=25,$auth=false,$user,$pass){
		self::$debug=false;
		self::$smtp_port=$smtp_port;
		self::$relay_host=$relay_host;
		self::$time_out=20;
		self::$auth=$auth;
		self::$user=$user;
		self::$pass=$pass;
		self::$host_name='localhost';
		self::$log_file='';
		self::$sock=false;
	}
	final public function sendMail($to,$from,$subject='',$body='',$mailType,$cc='',$bcc='',$additional_headers=''){
		$mail_from=self::get_address(self::strip_comment($from));
		$body=preg_replace("/(^|(\r\n))(\.)/","\1.\3",$body);
		$header='MIME-Version:1.0'.PHP_EOL;
		if($mailType=='HTML') $header.='Content-Type:text/html;charset=utf-8'.PHP_EOL;
		$header.='To: '.$to.PHP_EOL;
		if($cc!='') $header.='Cc: '.$cc.PHP_EOL;
		$header.='From: '.$from.'<'.$from.'>'.PHP_EOL;
		$header.='Subject: =?UTF-8?B?'.base64_encode($subject).'?='.PHP_EOL;
		$header.=$additional_headers;
		$header.='Date: '.date('r').PHP_EOL;
		$header.='X-Mailer: By lakwsh'.PHP_EOL;
		list($mSec,$sec)=explode(' ',microtime());
		$header.='Message-ID: <'.date('YmdHis',$sec).'.'.($mSec*1000000).'.'.$mail_from.'>'.PHP_EOL;
		$TO=explode(',',self::strip_comment($to));
		if($cc!='') $TO=array_merge($TO,explode(',',self::strip_comment($cc)));
		if($bcc!='') $TO=array_merge($TO,explode(',',self::strip_comment($bcc)));
		$sent=true;
		foreach($TO as $rcpt_to){
			$rcpt_to=self::get_address($rcpt_to);
			if(!self::smtp_socketOpen($rcpt_to)){
				self::log_write('Error: Cannot send email to '.$rcpt_to.PHP_EOL);
				$sent=false;
				continue;
			}
			if(self::smtp_send(self::$host_name,$mail_from,$rcpt_to,$header,$body)){
				self::log_write('E-mail has been sent to <'.$rcpt_to.'>'.PHP_EOL);
			}else{
				self::log_write('Error: Cannot send email to <'.$rcpt_to.'>'.PHP_EOL);
				$sent=false;
			}
			fclose(self::$sock);
			self::log_write('Disconnected from remote host'.PHP_EOL);
		}
		return $sent;
	}
	private function smtp_send($helo,$from,$to,$header,$body=''){
		if(!self::smtp_putCmd('HELO',$helo)) return self::smtp_error('sending HELO command');
		if(self::$auth){
			if(!self::smtp_putCmd('AUTH LOGIN',base64_encode(self::$user))) return self::smtp_error('sending HELO command');
			if(!self::smtp_putCmd('',base64_encode(self::$pass))) return self::smtp_error('sending HELO command');
		}
		if(!self::smtp_putCmd('MAIL','FROM:<'.$from.'>')) return self::smtp_error('sending MAIL FROM command');
		if(!self::smtp_putCmd('RCPT','TO:<'.$to.'>')) return self::smtp_error('sending RCPT TO command');
		if(!self::smtp_putCmd('DATA')) return self::smtp_error('sending DATA command');
		if(!self::smtp_message($header,$body)) return self::smtp_error('sending message');
		if(!self::smtp_eom()) return self::smtp_error('sending <CR><LF>.<CR><LF> [EOM]');
		if(!self::smtp_putCmd('QUIT')) return self::smtp_error('sending QUIT command');
		return true;
	}
	private function smtp_socketOpen($address){
		if(self::$relay_host=='') return self::smtp_socketOpen_mx($address);
		else return self::smtp_socketOpen_relay();
	}
	private function smtp_socketOpen_relay(){
		self::log_write('Trying to '.self::$relay_host.':'.self::$smtp_port.PHP_EOL);
		self::$sock=@fsockopen(self::$relay_host,self::$smtp_port,$errNo,$errStr,self::$time_out);
		if(!(self::$sock && self::smtp_ok())){
			self::log_write('Error: Cannot connect to relay host '.self::$relay_host.PHP_EOL);
			self::log_write('Error: '.$errStr.' ('.$errNo.')'.PHP_EOL);
			return false;
		}
		self::log_write('Connected to relay host '.self::$relay_host.PHP_EOL);
		return true;
	}
	private function smtp_socketOpen_mx($address){
		$domain=preg_replace("/^.+@([^@]+)$/","\1",$address);
		if(!@getmxrr($domain,$mxHosts)){
			self::log_write('Error: Cannot resolve MX "'.$domain.'"'.PHP_EOL);
			return false;
		}
		foreach($mxHosts as $host){
			self::log_write('Trying to '.$host.':'.self::$smtp_port.PHP_EOL);
			self::$sock=@fsockopen($host,self::$smtp_port,$errNo,$errStr,self::$time_out);
			if(!(self::$sock && self::smtp_ok())){
				self::log_write('Warning: Cannot connect to mx host '.$host.PHP_EOL);
				self::log_write('Error: '.$errStr.' ('.$errNo.')'.PHP_EOL);
				continue;
			}
			self::log_write('Connected to mx host '.$host.PHP_EOL);
			return true;
		}
		self::log_write('Error: Cannot connect to any mx hosts ('.implode(', ',$mxHosts).')'.PHP_EOL);
		return false;
	}
	private function smtp_message($header,$body){
		fputs(self::$sock,$header.PHP_EOL.$body);
		self::smtp_debug('> '.str_replace("\r\n","\n".'> ',$header."\n> ".$body."\n> "));
		return true;
	}
	private function smtp_eom(){
		fputs(self::$sock,PHP_EOL.'.'.PHP_EOL);
		self::smtp_debug('. [EOM]'.PHP_EOL);
		return self::smtp_ok();
	}
	private function smtp_ok(){
		$response=str_replace(PHP_EOL,'',fgets(self::$sock,512));
		self::smtp_debug($response.PHP_EOL);
		if(!preg_match("/^[23]/",$response)){
			fputs(self::$sock,'QUIT'.PHP_EOL);
			fgets(self::$sock,512);
			self::log_write('Error: Remote host returned "'.$response.'"'.PHP_EOL);
			return false;
		}
		return true;
	}
	private function smtp_putCmd($cmd,$arg=''){
		if($arg!='' and $cmd=='') $cmd=$arg;
		else $cmd=$cmd.' '.$arg;
		fputs(self::$sock,$cmd.PHP_EOL);
		self::smtp_debug('> '.$cmd.PHP_EOL);
		return self::smtp_ok();
	}
	private function smtp_error($string){
		self::log_write('Error: Error occurred while '.$string.'.'.PHP_EOL);
		return false;
	}
	private function log_write($message){
		self::smtp_debug($message);
		if(self::$log_file=='') return true;
		$message=date('M d H:i:s ').get_current_user().'['.getmypid().']: '.$message;
		if(!@file_exists(self::$log_file) || !($fp=@fopen(self::$log_file,'a'))){
			self::smtp_debug('Warning: Cannot open log file "'.self::$log_file.'"'.PHP_EOL);
			return false;
		}
		flock($fp,LOCK_EX);
		fputs($fp,$message);
		fclose($fp);
		return true;
	}
	private function strip_comment($address){
		$comment="/\([^()]*\)/";
		while(preg_match($comment,$address)) $address=preg_replace($comment,'',$address);
		return $address;
	}
	private function get_address($address){
		$address=preg_replace("/([ \t\r\n])+/",'',$address);
		$address=preg_replace("/^.*<(.+)>.*$/","\1",$address);
		return $address;
	}
	private function smtp_debug($message){
		if(self::$debug) echo $message;
	}
}
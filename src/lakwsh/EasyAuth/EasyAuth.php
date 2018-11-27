<?php
namespace lakwsh\EasyAuth;
use pocketmine\Player;
use pocketmine\entity\Entity;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\utils\TextFormat;
use pocketmine\level\particle\DustParticle;
use pocketmine\command\{CommandSender,ConsoleCommandSender};
use lakwsh\Smtp\Smtp;
use lakwsh\EasyAuth\event\{EventListener,PlayerAuthEvent,PlayerForcedKickEvent,PlayerChangeNameEvent};

function errorHandler(int $no,string $str,string $file,int $line){
	if(stripos($file,'EasyAuth')!==false and $no!==2){
		echo($file.'-['.$line.']-'.$no.': '.$str.PHP_EOL);
		@\lakwsh\EasyAuth\kill();
		exit(1);
	}
}
\set_error_handler(__NAMESPACE__.'\errorHandler',E_ALL|E_STRICT);
function exceptionHandler(\Throwable $e){
	$file=$e->getFile();
	if(stripos($file,'EasyAuth')!==false){
		echo($file.'-['.$e->getLine().']-'.$e->getCode().': '.$e->getMessage().PHP_EOL);
		@\lakwsh\EasyAuth\kill();
		exit(1);
	}
}
\set_exception_handler(__NAMESPACE__.'\exceptionHandler');
try{
	$reflection=new \ReflectionMethod('pocketmine\command\PluginCommand','execute');
	if($reflection->getParameters()[1]->hasType()) @require_once(__DIR__.'/Comp/PM.php');
	else @require_once(__DIR__.'/Comp/Gen.php');
	if((new \ReflectionMethod('pocketmine\network\Network','registerInterface'))->getParameters()[0]->getClass()->getShortName()==='NetworkInterface'){
		\lakwsh\EasyAuth\displayNotice();
		if(substr(phpversion(),0,3)!=='7.2') throw new \RuntimeException;
		$reflection=(new \ReflectionMethod('pocketmine\network\NetworkInterface','putPacket'))->getParameters();
		if($reflection[0]->getClass()->getShortName()==='NetworkSession'){
			if(\method_exists('pocketmine\network\NetworkInterface','tick')) @require_once(__DIR__.'/Comp/SI10.php');
			else @require_once(__DIR__.'/Comp/SI9.php');
		}elseif(count($reflection)===4){
			@require_once(__DIR__.'/Comp/SI7.php');
		}else{@require_once(__DIR__.'/Comp/SI8.php');}
	}else{
		$reflection=new \ReflectionClass('pocketmine\network\SourceInterface');
		if($reflection->hasMethod('start')){
			\lakwsh\EasyAuth\displayNotice();
			if($reflection->getMethod('putPacket')->getReturnType()=='int'){
				if(substr(phpversion(),0,3)!=='7.2') throw new \RuntimeException;
				@require_once(__DIR__.'/Comp/SI6.php');
			}else{
				$type=$reflection->getMethod('process')->getReturnType();
				if($type==null) throw new \RuntimeException;
				if($type=='bool') @require_once(__DIR__.'/Comp/SI5.php');
				else @require_once(__DIR__.'/Comp/SI4.php');
			}
		}else{
			$check=$reflection->getMethod('putPacket')->getParameters()[1]->getClass();
			if($check==null) @require_once(__DIR__.'/Comp/SI3.php');
			elseif(stripos($check,'mcpe')!==false) @require_once(__DIR__.'/Comp/SI2.php');
			else @require_once(__DIR__.'/Comp/SI1.php');
		}
	}
	if(\interface_exists('pocketmine\network\mcpe\protocol\ProtocolInfo',false)){
		@require_once(__DIR__.'/Comp/New.php');
		@require_once(__DIR__.'/UI/Elements.php');
		@require_once(__DIR__.'/UI/Windows.php');
	}else{@require_once(__DIR__.'/Comp/Old.php');}
}catch(\Exception $exception){
	echo '很显然,这个插件最终还是无法兼容这个核心,现在将执行强制终止进程操作.'.PHP_EOL;
	@\lakwsh\EasyAuth\kill();
	exit(1);
}
function displayNotice(){
	echo '本插件不支持第三方构建的PocketMine,如PMMP核心.'.PHP_EOL;
	echo '这些核心降低了插件运行环境及开发环境的整体质量.'.PHP_EOL;
	echo '他们迫使插件开发人员浪费时间来支持相互冲突的API.'.PHP_EOL;
	echo '如果你要使用这个插件,你必须明白你将不会得到任何技术支持.'.PHP_EOL;
	echo '如果你继续使用本插件将被视为已阅读并明白上述内容.'.PHP_EOL;
	return;
}
function kill(){
	$pid=(int)\getmypid();
	$name=\php_uname('s');
	if(\stripos($name,'Win')!==false or $name==='Msys'){
		@\exec('taskkill.exe /F /PID '.$pid.' > NUL');
	}else{
		if(\function_exists('posix_kill')) @\posix_kill(0,SIGKILL);
		@\exec('kill -9 '.$pid.' > /dev/null 2>&1');
	}
	exit(1);
}

// 主类
class EasyAuth extends \pocketmine\plugin\PluginBase{
	const MIN_LEN=16;
	// 变量定义
	private static $cfg;
	/** @var $ser \pocketmine\Server */
	private static $ser;
	/** @var $log \pocketmine\plugin\PluginLogger */
	private static $log;
	/** @var $socket TcpServer */
	private static $socket;
	/** @var $network \pocketmine\network\Network */
	private static $network;
	/** @var $manager \pocketmine\plugin\PluginManager */
	private static $manager;
	/** @var $provider MysqlDataProvider|JsonDataProvider */
	private static $provider;
	private static $isDisable=false;
	private static $msg=array();
	private static $uiSend=array();
	private static $isLogin=array();
	private static $banList=array();
	private static $joinTime=array();
	private static $lastMove=array();
	private static $isLoaded=array();
	private static $bannedIP=array();
	private static $realName=array();
	private static $playerPwd=array();
	private static $checkName=array();
	private static $pwdWrongTime=array();
	private static $tasks=array('Main'=>false,'LoginCheck'=>false,'cleanTask'=>false,'PingTask'=>false,'reconnectTask'=>false);
	// 插件加载
	final public function onLoad(){
		self::check();
		self::$ser=parent::getServer();
		self::$log=parent::getLogger();
		self::$network=self::$ser->getNetwork();
		self::$manager=self::$ser->getPluginManager();
		return;
	}
	// 插件开启
	final public function onEnable(){
		$log=self::$log;
		$server=self::$ser;
		if(defined('EAP') or defined('EASP') or defined('EAPP') or defined('EAPB')){
			if(EAP!==parent::getDataFolder().'/' or EAPB!==EAP.'/backup/' or EAPS!==$server->getDataPath().'/players/' or EAPP!==EAP.'/players/'){
				self::killServer('致命错误: 存在不兼容的插件');
				exit(1);
			}
		}else{
			define('EAP',parent::getDataFolder().'/');
			define('EAPB',EAP.'/backup/');
			define('EAPS',$server->getDataPath().'/players/');
			define('EAPP',EAP.'/players/');
		}
		if(!is_dir(EAP) and !mkdir(EAP,0777,true)){
			self::killServer('无主文件夹创建权限,插件初始化失败');
			exit(1);
		}
		self::getSetting();
		$task=new PTT($this);
		self::$network->registerInterface($task);
		$cfg=self::$cfg;
		if(!in_array($task,self::$network->getInterfaces())){
			self::killServer('插件初始化失败,正在执行强制关服指令...');
			exit(1);
		}
		if(!is_dir(EAPB)) mkdir(EAPB,0777,true);
		if($cfg['数据库设置']['Enable'] and phpversion('mysqli')!==false){
			self::$provider=new MysqlDataProvider($this);
			if(self::$provider->initConnect($cfg['数据库设置'])){
				$log->notice('数据库连接成功!正在使用MySQL作为密码存储方式~');
			}else{
				self::killServer('无法连接至数据库,正在执行强制关服指令...');
				exit(1);
			}
		}else{
			if(!is_dir(EAPP)) mkdir(EAPP,0777,true);
			self::$provider=new JsonDataProvider($this);
			$log->notice('正在使用Json作为密码存储方式~');
		}
		$listener=new EventListener($this);
		if($listener->init(self::$manager)===false){
			self::killServer('无法设置事件监听器,插件初始化失败');
			exit(1);
		}
		parent::saveResource('command.help');
		$server->getCommandMap()->register('EasyAuth',new Command($this,self::getFileContent('command.help'),json_decode($cfg['自定义指令'],true)));
		$tasks=&self::$tasks;
		if($cfg['远程控制']['开启']){
			if(phpversion('openssl')!==false){
				self::$socket=new TcpServer($this,$server->getPort());
				$tasks['Main']=true;
				$log->notice('[远程控制]: 功能初始化成功');
			}else{$log->notice('[远程控制]: 由于安全原因,请先安装openssl扩展再使用此功能');}
		}
		if($cfg['挂机清理']['开启']) $tasks['cleanTask']=true;
		self::getBanList();
		$log->warning('已加载'.count(self::$banList).'个封禁列表!');
		return;
	}
	// 插件关闭
	final public function onDisable(){
		if(isset(self::$socket)) self::$socket->close();
		if(isset(self::$provider)) self::$provider->close();
		self::$log->notice('插件卸载完毕!');
		return;
	}
	/**
	 * 钩子函数
	 * @param $event \pocketmine\event\player\PlayerCreationEvent
	 */
	final public function onPlayerCreation($event){
		self::$log->info('玩家连接到服务器: IP['.$event->getAddress().'] 端口['.$event->getPort().']');
		return;
	}
	/**
	 * 发包
	 * @param $event \pocketmine\event\server\DataPacketSendEvent
	 */
	final public function onDataPacketSend($event){
		self::check();
		if(self::$cfg['未登录屏蔽其他提示信息']){
			$pk=$event->getPacket();
			if(self::checkPacket($pk)!==2) return;
			/** @var $pk \pocketmine\network\mcpe\protocol\TextPacket */
			$player=$event->getPlayer();
			if(!self::isLogin($player)){
				$name=self::getObjName($player);
				$msg=self::$msg;
				if(!isset($msg[$name]) or $pk->message!=$msg[$name]){
					$pk->clean();
					$event->setCancelled(true);
				}
			}
		}
		return;
	}
	/**
	 * 收包
	 * @param $event \pocketmine\event\server\DataPacketReceiveEvent
	 */
	final public function onDataPacketReceive($event){
		self::check();
		$pk=$event->getPacket();
		if(($type=self::checkPacket($pk))===false) return;
		$cfg=self::$cfg;
		$player=$event->getPlayer();
		$name=self::getObjName($player);
		switch($type){
			case 1:
				if(!self::isLogin($name)){
					$pk->clean();
					$event->setCancelled(true);
					self::SendMsg($player,$cfg['Msg-NotAuth']);
				}
				break;
			case 2:
				/** @var $pk \pocketmine\network\mcpe\protocol\TextPacket */
				if(self::processMsgPacket($player,$name,$cfg,$pk->message)){
					$pk->message=null;
					$event->setCancelled(true);
				}
				break;
			case 3:
				if(self::isLogin($name)){
					if($cfg['挂机清理']['开启']) self::$lastMove[$name]=time();
				}elseif(Create::supportUI()){
					$tmp=$cfg['图形用户界面'];
					if($tmp['开启(>=1.2)'] and $tmp['增强模式']) self::sendMainUI($player);
				}
				break;
			case 4:
				if(!self::processLoginPacket($player,$pk,$cfg)){
					$pk->clean();
					$event->setCancelled(true);
				}
				break;
			case 7:
				if(!$cfg['图形用户界面']['开启(>=1.2)']) break;
				self::$uiSend[$name]=true;
				self::processUI($player,$pk,$name);
				break;
		}
		return;
	}
	/**
	 * @param Player $player
	 * @param \pocketmine\network\mcpe\protocol\LoginPacket $pk
	 * @param array $cfg
	 * @return bool
	 */
	private function processLoginPacket(Player $player,$pk,array $cfg):bool{
		$version=&$pk->protocol;
		if(!is_int($version) or !is_string($pk->username)){
			self::kick($player,TextFormat::RED.'Incompatible clients.');
			return false;
		}
		$cid=$pk->clientId;
		if(($check=$cfg['检测客户端'])['开启'] and strlen($cid)<EasyAuth::MIN_LEN){
			if($check['临时封禁']) self::$bannedIP[(string)$player->getAddress()]=time()+$check['封禁时长(秒)'];
			self::kick($player,$cfg['Msg-ClientNotAllow']);
			return false;
		}
		$name=self::getObjName($pk->username);
		$ip=$player->getAddress();
		$log=self::$log;
		$info='玩家连接到服务器: Cid['.$cid.'] ';
		$check=array($cid);
		if(isset($pk->xuid)){
			$uid=$pk->xuid;
			array_push($check,$uid);
			$info.='Uid['.$uid.'] ';
		}
		$log->info($info.'客户端版本['.$version.']');
		if(self::isBanned($name,$check,$ip)){
			self::kick($player,$cfg['Msg-NameBanned']);
			return false;
		}
		$pve=Create::getVersion();
		if($cfg['极限模式'] and $pve!==$version){
			$version=$pve;
			$log->info('玩家['.$name.']极限模式生效');
		}
		if(!$cfg['允许自定义玩家名']) return true;
		$pk->username=self::getRealName($name);
		self::$realName[$ip.':'.$player->getPort()]=$name;
		return true;
	}
	private function processMsgPacket(Player $player,string $name,array $cfg,string $msg):bool{
		if(self::isLogin($name)){
			$pwd=self::$playerPwd;
			if(!array_key_exists($name,$pwd) or stripos($msg,$pwd[$name])===false) return false;
			self::SendMsg($player,$cfg['Msg-PwdSendDetect']);
		}else{
			$msg=trim($msg);
			$check=self::$checkName[$name];
			if($cfg['允许自定义玩家名'] and !$check['check']){
				if($player->getName()===$msg){
					self::sendHello($player);
					self::$checkName[$name]['check']=true;
				}else{
					if($check['name']===$msg){
						self::setRealName($player,$msg);
					}else{
						self::$checkName[$name]['name']=$msg;
						self::SendMsg($player,str_ireplace('#newname#',$msg,$cfg['Msg-ChangeToName']));
					}
				}
				return true;
			}
			if(self::isReg($player)){
				$forgot=explode(' ',$msg);
				if($forgot[0]=='forgot'){
					if(!isset($forgot[1])) self::forgot($player,$name);
					else self::forgot($player,$name,$forgot[1]);
					return true;
				}
				self::authPlayer($player,$msg);
			}else{self::regPlayer($player,$msg);}
			self::$playerPwd[$name]=$msg;
		}
		return true;
	}
	/**
	 * 玩家连接到服务器
	 * @param $event \pocketmine\event\player\PlayerPreLoginEvent
	 */
	final public function onPlayerPreLogin($event){
		$cfg=self::$cfg;
		$player=$event->getPlayer();
		if(self::$isDisable){
			self::kick($player,$cfg['Msg-ServerDisable']);
			$event->setCancelled(true);
			return;
		}
		$name=self::getObjName($player);
		$entry=$cfg['注册开关'];
		if(!$entry['状态'] and $entry['允许进服'] and !self::isReg($player)){
			self::kick($player,$cfg['Msg-EntryDisabled']);
			$event->setCancelled(true);
			return;
		}
		$ip=(string)$player->getAddress();
		if(isset(self::$bannedIP[$ip])){
			$endTime=self::$bannedIP[$ip];
			if($endTime>time()){
				self::kick($player,str_ireplace('#TimeOfEnd#',date('H:i:s',$endTime),$cfg['Msg-IPBanned']));
				$event->setCancelled(true);
				return;
			}
		}
		foreach(self::$ser->getOnlinePlayers() as $p){
			if($name==self::getObjName($p)){
				if(self::isLogin($p)){
					self::kick($player,$cfg['Msg-SameNameLogin']);
					$event->setCancelled(true);
					return;
				}else{self::kick($p,$cfg['Msg-SameNameNotLogin']);}
			}
			if($cfg['小号限制']['开启'] and $ip==(string)$p->getAddress()) $sameIP[]=$p;
		}
		if(isset($sameIP) and ($c=count($sameIP))>0 and $c>($lm=$cfg['小号限制']['限制人数']-1)){
			foreach($sameIP as $p){
				if(!self::isLogin($p)){
					self::kick($p,str_ireplace('#count#',$lm+1,$cfg['Msg-SameIpNotLogin']));
					--$c;
				}
			}
			if($c>$lm){
				self::kick($player,str_ireplace('#count#',$lm+1,$cfg['Msg-SameIpLogin']));
				$event->setCancelled(true);
				return;
			}
		}
		self::setLogin($player,false);
		return;
	}
	/**
	 * 玩家加入游戏
	 * @param $event \pocketmine\event\player\PlayerJoinEvent
	 */
	final public function onPlayerJoin($event){
		$cfg=self::$cfg;
		self::$tasks['LoginCheck']=true;
		$player=$event->getPlayer();
		if(($pt=$cfg['进服出生点'])['开启']){
			$server=self::$ser;
			if(!$server->loadLevel($pt['world'])) $player->teleport($server->getDefaultLevel()->getSpawnLocation());
			else $player->teleport(new Position($pt['x'],$pt['y'],$pt['z'],$server->getLevelByName($pt['world'])));
			$player->scheduleUpdate();
		}
		self::onLogin($player,false);
		self::$isLoaded[self::getObjName($player)]=true;
		$name=$player->getName();
		if(($msg=$cfg['提示信息']['加入游戏'])=='null') $event->setJoinMessage(null);
		else $event->setJoinMessage(str_ireplace('#name#',$name,$msg));
		self::autoCommand($player,'preLogin');
		if(self::isReg($name) and !Create::supportUI() and self::checkPlayer($player)){
			self::SendMsg($player,$cfg['Msg-AutoLogin']);
			self::toldOp(TextFormat::GREEN."玩家[$name]自动登录成功",$player);
			return;
		}
		if($cfg['允许自定义玩家名']){
			self::SendMsg($player,str_ireplace('#username#',$name,$cfg['Msg-SetNameTip1']));
			self::SendMsg($player,$cfg['Msg-SetNameTip2']);
		}else{self::sendHello($player);}
		if(Create::supportUI()) self::$uiSend[$name]=false;
		return;
	}
	/**
	 * 玩家离开游戏
	 * @param $event \pocketmine\event\player\PlayerQuitEvent
	 */
	final public function onPlayerQuit($event){
		$player=$event->getPlayer();
		self::setLogin($player,true,true);
		if(($msg=self::$cfg['提示信息']['离开游戏'])=='null') $event->setQuitMessage(null);
		else $event->setQuitMessage(str_ireplace('#name#',$player->getName(),$msg));
		self::autoCommand($player,'onLeave');
		return;
	}
	/**
	 * 方块事件
	 * @param $event \pocketmine\event\block\BlockPlaceEvent|\pocketmine\event\block\BlockBreakEvent|\pocketmine\event\player\PlayerInteractEvent
	 */
	final public function BlockEvent($event){
		$cfg=self::$cfg;
		$player=$event->getPlayer();
		if(!self::isLogin($player)){
			if($cfg['未登录状态']['禁止修改地图']){
				self::SendMsg($player,$cfg['Msg-NotAuth']);
				$event->setCancelled(true);
			}
		}
		return;
	}
	/**
	 * 打开箱子
	 * @param $event \pocketmine\event\inventory\InventoryOpenEvent
	 */
	final public function onInventoryOpen($event){
		$cfg=self::$cfg;
		if($cfg['未登录状态']['禁止打开箱子']){
			$player=$event->getPlayer();
			if(self::loadCheck($player) and !self::isLogin($player)){
				self::SendMsg($player,$cfg['Msg-NotAuth']);
				$event->setCancelled(true);
			}
		}
		return;
	}
	/**
	 * 物品事件
	 * @param $event \pocketmine\event\player\PlayerItemConsumeEvent|\pocketmine\event\player\PlayerDropItemEvent
	 */
	final public function ItemEvent($event){
		$cfg=self::$cfg;
		if($cfg['未登录状态']['禁止使用物品']){
			$player=$event->getPlayer();
			if(!$event->isCancelled() and !self::isLogin($player)){
				self::SendMsg($player,$cfg['Msg-NotAuth']);
				$event->setCancelled(true);
			}
		}
		return;
	}
	/**
	 * 实体被伤害
	 * @param $event \pocketmine\event\entity\EntityDamageEvent
	 */
	final public function onEntityDamage($event){
		if(!self::$cfg['未登录状态']['免疫伤害']) return;
		$flag=false;
		$ent=$event->getEntity();
		if(self::checkObj($ent)<3 and !self::isLogin($ent)){
			$flag=true;
		}elseif($event instanceof \pocketmine\event\entity\EntityDamageByEntityEvent){
			$dam=$event->getDamager();
			if(self::checkObj($dam)<3 and !self::isLogin($dam)) $flag=true;
		}
		if($flag) $event->setCancelled(true);
		return;
	}
	/**
	 * 实体传送
	 * @param $event \pocketmine\event\entity\EntityTeleportEvent
	 */
	final public function onEntityTeleport($event){
		$cfg=self::$cfg;
		if($cfg['未登录状态']['禁止传送']){
			$ent=$event->getEntity();
			if(self::checkObj($ent)<3 and self::loadCheck($ent) and !self::isLogin($ent)){
				self::SendMsg($ent,$cfg['Msg-NotAuth']);
				$event->setCancelled(true);
			}
		}
		return;
	}
	// Task
	// Task控制接口
	final public function setTask(string $task,bool $state){
		switch($task){
			case 'ping':
				self::$tasks['PingTask']=$state;
				break;
			case 'reconnect':
				self::$tasks['reconnectTask']=$state;
				break;
			default:
				self::killServer();
				exit(1);
		}
		return;
	}
	// TaskManager
	final public function preTick(){
		$tasks=self::$tasks;
		if($tasks['Main']) self::$socket->process();
		static $time=0;
		if($time<time()){
			$time=time();
			if($tasks['LoginCheck']) self::LoginCheck();
			static $last=array('PingTask'=>0,'reconnectTask'=>0,'cleanTask'=>0);
			if($tasks['cleanTask'] and $last['cleanTask']<=$time+self::$cfg['挂机清理']['检测间隔(秒)']){
				self::cleanTask();
				$last['cleanTask']=$time;
			}
			if($tasks['PingTask'] and $last['PingTask']<=$time+30){
				self::$provider->PingTask();
				$last['PingTask']=$time;
			}
			if($tasks['reconnectTask'] and $last['reconnectTask']<=$time+5){
				self::$provider->reconnectTask();
				$last['reconnectTask']=$time;
			}
		}
	}
	// 挂机清理
	private function cleanTask(){
		$cfg=self::$cfg;
		$server=self::$ser;
		$clean=$cfg['挂机清理'];
		$players=$server->getOnlinePlayers();
		$time=$clean['触发时间(秒)'];
		foreach($players as $p){
			if(self::isLogin($p) and time()-self::$lastMove[self::getObjName($p)]>$time){
				self::kick($p,str_ireplace('#time#',$time,$cfg['Msg-KickByCleaner']));
			}
		}
		return;
	}
	// 超时踢人
	private function LoginCheck(){
		$cfg=self::$cfg;
		$players=self::$ser->getOnlinePlayers();
		$i=0;
		foreach($players as $p){
			if(!self::isLogin($p)){
				if($cfg['超时踢人']['开启']){
					$left=self::GetTimeOut($p);
					if($left<1){
						self::kick($p,str_ireplace('#t#',$cfg['超时踢人']['超时时间(秒)'],$cfg['Msg-LongTimeKick']));
						continue;
					}
				}
				$tmp=$cfg['粒子环绕效果'];
				if($tmp['开启']){
					$max=M_PI*2;
					$rgb=explode(',',$tmp['RGB']);
					for($i=0;$i<$max;$i+=$max/$tmp['粒子密度']) $p->getLevel()->addParticle(new DustParticle(new Vector3($p->x+$tmp['半径']*cos($i),$p->y+$tmp['高度'],$p->z+$tmp['半径']*sin($i)),(int)$rgb[0],(int)$rgb[1],(int)$rgb[2]));
				}
				$name=self::getObjName($p);
				if($cfg['使用大标题提示']){
					if(!$cfg['允许自定义玩家名'] or self::$checkName[$name]['check']) self::sendBigTitle($p,$cfg['Msg-NotAuthTitle'],$cfg['Msg-NotAuthSubTitle'],0,100,0);
					else self::sendBigTitle($p,$cfg['Msg-NameNotSetTitle'],$cfg['Msg-NameNotSetSubTitle'],0,100,0);
				}
				if(isset(self::$uiSend[$name]) and !self::$uiSend[$name]) self::sendMainUI($p);
				++$i;
			}
		}
		if($i<1) self::$tasks['LoginCheck']=false;
		return;
	}
	// 自定义函数
	// UI发送
	private function sendUI(Player $player,int $type,string $message=''):bool{
		$cfg=self::$cfg;
		if(!$cfg['图形用户界面']['开启(>=1.2)'] or !Create::supportUI()) return false;
		$pk=Create::FormPacket();
		if(self::checkPacket($pk)!==6) return false;
		$name=self::getObjName($player);
		switch($type){
			case 6840:
				$ui=new SimpleForm($cfg['Gui-Select'],str_ireplace('#name#',$name,$cfg['Gui-Name']));
				if(!$ui->setButtons(array(new Button($cfg['Gui-ChangeName']),new Button($cfg['Gui-Auth']),new Button($cfg['Gui-Exit'])))) return false;
				break;
			case 6841:
				$ui=new CustomForm($cfg['Gui-InputPwd']);
				if($ui->setElements(array(new Input))) break;
				return false;
			case 6842:
				$ui=new ModalWindow($cfg['Gui-Auth'],str_ireplace('#password#',$message,$cfg['Gui-ConfirmPwd']),$cfg['Gui-True'],$cfg['Gui-False']);
				break;
			case 6843:
				$ui=new SimpleForm($cfg['Gui-PwdWrong']);
				$buttons=array(new Button($cfg['Gui-Retry']),new Button($cfg['Gui-Forgot']),new Button($cfg['Gui-Exit']));
				if(!$ui->setButtons($buttons)) return false;
				break;
			case 6844:
				$ui=new ModalWindow($cfg['Gui-BindEmail'],$cfg['Gui-AskBind'],$cfg['Gui-Need'],$cfg['Gui-NotNeed']);
				break;
			case 6845:
				$ui=new CustomForm($cfg['Gui-BindEmail']);
				if($ui->setElements(array(new Input))) break;
				return false;
				break;
			case 6846:
				$ui=new ModalWindow($cfg['Gui-BindEmail'],str_ireplace('#email#',$message,$cfg['Gui-ConfirmMail']),$cfg['Gui-True'],$cfg['Gui-False']);
				break;
			case 6847:
				$ui=new CustomForm($cfg['Gui-ChangeName']);
				if($ui->setElements(array(new Input))) break;
				return false;
				break;
			case 6848:
				$ui=new ModalWindow($cfg['Gui-ChangeName'],str_ireplace('#name#',$message,$cfg['Gui-ConfirmName']),$cfg['Gui-True'],$cfg['Gui-False']);
				break;
			case 6849:
				$ui=new SimpleForm($cfg['Gui-WrongEmail']);
				$buttons=array(new Button($cfg['Gui-Retry']),new Button($cfg['Gui-Later']));
				if(!$ui->setButtons($buttons)) return false;
				break;
			case 6850:
				$ui=new SimpleForm($cfg['Gui-WrongName']);
				$buttons=array(new Button($cfg['Gui-Retry']),new Button($cfg['Gui-Back']));
				if(!$ui->setButtons($buttons)) return false;
				break;
			case 6851:
				$ui=new SimpleForm($cfg['Gui-WrongPwd']);
				$buttons=array(new Button($cfg['Gui-Retry']),new Button($cfg['Gui-Back']));
				if(!$ui->setButtons($buttons)) return false;
				break;
			case 6852:
				$ui=new SimpleForm($cfg['Gui-Select'],str_ireplace('#name#',$name,$cfg['Gui-Name']));
				if(!$ui->setButtons(array(new Button($cfg['Gui-ChangeName']),new Button($cfg['Gui-AutoAuth']),new Button($cfg['Gui-Exit'])))) return false;
				break;
			case 6853:
				$ui=new CustomForm($cfg['Gui-InputCode']);
				if($ui->setElements(array(new Input))) break;
				break;
			case 6854:
				$ui=new SimpleForm($cfg['Gui-WrongCode']);
				$buttons=array(new Button($cfg['Gui-Retry']),new Button($cfg['Gui-Back']));
				if(!$ui->setButtons($buttons)) return false;
				break;
			case 6860:
				$msg=$cfg['欢迎提示框'];
				$ui=new SimpleForm($msg['标题'],$msg['内容']);
				break;
			default:
				return false;
		}
		$pk->formId=$type;
		$pk->formData=json_encode($ui);
		$player->dataPacket($pk);
		return true;
	}
	private function sendMainUI(Player $player){
		$main=self::$cfg['允许自定义玩家名']?6840:6841;
		if(self::checkPlayer($player,false)) self::sendUI($player,6852);
		else self::sendUI($player,$main);
		return;
	}
	// UI处理
	private function processUI(Player $player,$pk,string $name){
		static $last=array();
		/** @var $pk \pocketmine\network\mcpe\protocol\ModalFormResponsePacket */
		$type=$pk->formId;
		$return=explode("\n",$pk->formData);
		if($return[0]==='null' and !self::isLogin($player)){
			self::sendUI($player,$type);
			return;
		}
		$return[0]=trim($return[0],'[""]');
		switch($type){
			case 6840:
				if($return[0]==='0') self::sendUI($player,6847);
				elseif($return[0]==='1') self::sendUI($player,6841);
				else self::kick($player);
				break;
			case 6841:
				if(!self::isReg($player)){
					$last[$name]=$return[0];
					self::sendUI($player,6842,$return[0]);
				}elseif(!self::authPlayer($player,$return[0])){self::sendUI($player,6843);}
				break;
			case 6842:
				if($return[0]==='true'){
					if(!self::regPlayer($player,$last[$name])) self::sendUI($player,6851);
					else self::sendUI($player,6844);
				}else{self::sendUI($player,6840);}
				break;
			case 6843:
				if($return[0]==='0'){
					self::sendUI($player,6841);
				}elseif($return[0]==='1'){
					if(self::forgot($player,$name)) self::sendUI($player,6853);
				}else{self::kick($player);}
				break;
			case 6844:
				if($return[0]==='true') self::sendUI($player,6845);
				break;
			case 6845:
				$last[$name]=$return[0];
				self::sendUI($player,6846,$return[0]);
				break;
			case 6846:
				if($return[0]==='true'){
					if(!self::isLogin($player)){
						self::kick($player,self::$cfg['Msg-UndefinedError']);
						return;
					}
					if(!self::bindMail($player,$last[$name])) self::sendUI($player,6849);
				}elseif($return[0]==='false'){self::sendUI($player,6845);}
				break;
			case 6847:
				$last[$name]=$return[0];
				self::sendUI($player,6848,$return[0]);
				break;
			case 6848:
				if($return[0]==='true'){
					if(!self::setRealName($player,$last[$name])) self::sendUI($player,6850);
				}else{self::sendUI($player,6847);}
				break;
			case 6849:
				if($return[0]==='0') self::sendUI($player,6845);
				break;
			case 6850:
				if($return[0]==='0') self::sendUI($player,6847);
				else self::sendUI($player,6840);
				break;
			case 6851:
				if($return[0]==='0') self::sendUI($player,6841);
				else self::sendUI($player,6840);
				break;
			case 6852:
				if($return[0]==='0') self::sendUI($player,6847);
				elseif($return[0]==='1') self::checkPlayer($player);
				elseif($return[0]==='2') self::kick($player);
				break;
			case 6853:
				if(!self::forgot($player,$name,$return[0])) self::sendUI($player,6854);
				break;
			case 6854:
				if($return[0]==='0') self::sendUI($player,6853);
				else self::sendUI($player,6843);
				break;
		}
		return;
	}
	// 找回密码
	private function forgot(Player $player,string $name,$code=null):bool{
		$cfg=self::$cfg;
		if(!$cfg['密码找回']['开启']){
			self::SendMsg($player,$cfg['Msg-FunctionDisabled']);
			return false;
		}
		if(($info=self::$provider->getPlayerInfo($name))==null) return false;
		$newPwd=substr(sha1(date('Ymd').$name.$info['lastlogin']),8,8);
		if($code!=null){
			if($code===$newPwd){
				if(self::setAuthPlayer($player)){
					self::SendMsg($player,$cfg['Msg-CodePass']);
					self::toldOp(TextFormat::GREEN.'玩家['.$name.']已使用找回密码功能',$player);
					self::$manager->callEvent(new PlayerAuthEvent($player,'Mail'));
					return true;
				}
			}else{self::SendMsg($player,$cfg['Msg-CodeWrong']);}
		}elseif(self::sendMail($info['email'],$newPwd)){
			self::SendMsg($player,$cfg['Msg-MailSent']);
			return true;
		}else{self::SendMsg($player,$cfg['Msg-MailSendFailed']);}
		return false;
	}
	// 远程控制密码
	final public function getPassword():string{
		return self::$cfg['远程控制']['密码'];
	}
	// 远程指令
	final public function handlePacket(string $pk){
		$server=self::$ser;
		$return=array('time'=>time());
		$cmd=explode(' ',$pk);
		switch($cmd[0]){
			case 'shutdown':
				$server->shutdown();
				return null;
				break;
			case 'players':
				$players=$server->getOnlinePlayers();
				$all=$op=$login=array();
				foreach($players as $p){
					$name=$p->getName();
					$all[]=$name;
					if($p->isOp()) $op[]=$name;
					if(self::isLogin($name)) $login[]=$name;
				}
				$return['return']=array(
					'max'=>$server->getMaxPlayers(),
					'count'=>count($players),
					'op'=>$op,
					'login'=>$login,
					'all'=>$all
				);
				break;
			case 'kick':
				$player=$server->getPlayerExact(self::getObjName($cmd[1]));
				if($player!=null) self::kick($player,TextFormat::RED.'已被远程踢出');
				$return['return']=true;
				break;
			default:
				$return['return']=false;
				break;
		}
		return json_encode($return);
	}
	final public function blockAddress(string $address){
		self::$network->blockAddress($address,-1);
		return;
	}
	// 指令调用
	final public function CmdExecutor(CommandSender $sender,array $args):bool{
		self::check();
		if(!isset($args[0])) return false;
		$cfg=self::$cfg;
		$log=self::$log;
		$server=self::$ser;
		switch($args[0]){
			case 'list':
				if(self::checkObj($sender)!==0) return self::sendErrorTip($sender);
				if(count($list=self::$provider->getPlayerList())<1){
					$log->warning(TextFormat::RED.'未找到任何玩家数据');
					break;
				}
				$log->notice('已注册玩家信息列表:');
				foreach($list as $player) $log->notice('玩家名['.$player['name'].'] 最后登录IP['.$player['IP'].'] 最后登录时间['.$player['lastloginday'].']');
				$log->notice('玩家信息显示完毕');
				break;
			case 'pwd':
				if(!isset($args[1])) return self::sendErrorTip($sender,2);
				if(self::checkObj($sender)===0){
					if(!isset($args[2])) return self::sendErrorTip($sender,2);
					if(!self::isReg($args[1])){
						$log->warning('该账号未注册!');
						break;
					}
					$pwd=trim($args[2]);
					if(self::changePwd($args[1],$pwd,$sender)) $log->notice('玩家['.$args[1].']的密码已修改为: '.$pwd);
					break;
				}
				$pwd=trim($args[1]);
				if(self::changePwd($sender,$pwd,$sender)){
					self::$playerPwd[self::getObjName($sender)]=$pwd;
					self::SendMsg($sender,str_ireplace('#password#',$pwd,$cfg['Msg-PwdChanged']));
				}
				break;
			case 'ban':
				if(self::checkObj($sender)!==0) return self::sendErrorTip($sender);
				if(!isset($args[1])) return self::sendErrorTip($sender,2);
				switch($args[1]){
					case 'add':
						if(!isset($args[2])){
							$log->warning('正在导入核心自带BanList...');
							$list=array('name'=>[],'AllCid'=>[],'IP'=>[]);
							$banList=$server->getNameBans();
							foreach($banList->getEntries() as $entry){
								if($entry->getExpires()==null){
									$name=$entry->getName();
									$banList->remove($name);
									$list['name'][]=$entry->getName();
								}
							}
							if(\method_exists($server,'getCIDBans')){
								$banList=$server->getCIDBans();
								foreach($banList->getEntries() as $entry){
									if($entry->getExpires()==null){
										$name=$entry->getName();
										$banList->remove($name);
										$list['AllCid'][]=$entry->getName();
									}
								}
							}
							$banList=$server->getIPBans();
							foreach($banList->getEntries() as $entry){
								if($entry->getExpires()==null){
									$name=$entry->getName();
									$banList->remove($name);
									$list['IP'][]=$entry->getName();
								}
							}
							if(self::addBan($list)) $log->warning('操作完成');
							break;
						}
						$return=self::setSuperBanned($args[2]);
						if(!$return) break;
						$players=implode(',',$return['name']);
						$server->broadcastMessage(str_ireplace(array('#player#','#players#'),array($args[2],$players),$cfg['Msg-SuperBan']));
						self::sendLongNotice('已封禁玩家:'.$players);
						self::sendLongNotice('已封禁IP:'.implode(',',$return['IP']));
						self::sendLongNotice('已封禁CID:'.implode(',',$return['AllCid']));
						break;
					case 'remove':
						if(!isset($args[2])) return self::sendErrorTip($sender,2);
						if(self::removeBan($args[2])) self::$log->notice('成功解除事件ID为'.$args[2].'的超级封禁');
						else self::$log->warning('无法解除事件ID为'.$args[2].'的超级封禁');
						break;
					case 'check':
						self::displayBanList();
						break;
					default:
						return false;
				}
				break;
			case 'setspawn':
				if(self::checkObj($sender)!==1) return self::sendErrorTip($sender);
				/** @var $sender Player */
				$loc=$sender->getLocation();
				$cfg['进服出生点']=array('开启'=>true,'x'=>(float)$loc->x,'y'=>(float)$loc->y,'z'=>(float)$loc->z,'world'=>(string)$loc->level->getName());
				self::saveConfigFile('config',$cfg);
				self::SendMsg($sender,TextFormat::GREEN.'已设置当前位置为进服出生点.');
				break;
			case 'ver':
				self::SendMsg($sender,TextFormat::GREEN.'目前插件版本: '.parent::getDescription()->getVersion());
				break;
			case 'mail':
				if(self::checkObj($sender)===0) return self::sendErrorTip($sender,3);
				if(!isset($args[1])){
					self::SendMsg($sender,$cfg['Msg-WrongMailFormat']);
					break;
				}
				if(($info=self::$provider->getPlayerInfo($sender))==null or $info['email']=='unknown' or !self::bindMail($sender,$args[1])) self::SendMsg($sender,$cfg['Msg-MailBindFailed']);
				break;
			case 'reload':
				if(self::checkObj($sender)>1) return self::sendErrorTip($sender);
				if(self::getSetting(false)){
					self::getBanList();
					self::SendMsg($sender,TextFormat::GREEN.'配置文件已重新加载');
				}
				break;
			case 'delete':
				if(self::checkObj($sender)!==0) return self::sendErrorTip($sender);
				if(!isset($args[1])) return self::sendErrorTip($sender,2);
				if(!self::isReg($args[1])) $log->warning("该账号未被注册,删除失败");
				self::deletePlayer($args[1]);
				break;
			case 'clean':
				if(self::checkObj($sender)!==0) return self::sendErrorTip($sender);
				if(!isset($args[1])) return self::sendErrorTip($sender,2);
				$args[1]=(int)$args[1];
				if($args[1]<30){
					$log->warning('保险起见禁止删除最后登陆日期小于30天的玩家,如有需要请手动删除');
					break;
				}
				$time=time();
				$players=array();
				foreach(self::$provider->getPlayerList() as $player){
					if(!$cfg['是否删除记录有误的玩家'] and strtotime($player['lastloginday'])<1) continue;
					if((strtotime($player['lastloginday'])+$args[1]*86400)<$time){
						self::deletePlayer($player['name']);
						$players[]=$player['name'];
					}
				}
				$log->warning('共删除玩家数量: '.count($players));
				break;
			case 'cleanup':
				if(self::checkObj($sender)!==0) return self::sendErrorTip($sender);
				$deleted=0;
				$len=strlen(EAPS);
				$regList=self::$provider->getPlayerList(true);
				foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(EAPS)) as $name){
					if(stripos($name,'.dat')===false) continue;
					$name=substr($name,$len,-4);
					if(!in_array($name,$regList)){
						self::deletePlayer($name,true);
						$deleted++;
					}
				}
				$log->warning('共删除玩家数量: '.$deleted);
				break;
			case 'disable':
				if(self::checkObj($sender)!==0) return self::sendErrorTip($sender);
				if(self::$isDisable){
					self::$isDisable=false;
					$log->warning('已关闭维护模式');
				}else{
					foreach($server->getOnlinePlayers() as $player){
						if(!isset($args[1]) and self::checkObj($player)===1) continue;
						self::kick($player,$cfg['Msg-ServerDisable']);
					}
					self::$isDisable=true;
					$log->warning('已进入维护模式');
				}
				break;
			case 'cname':
				if(self::checkObj($sender)===0) return self::sendErrorTip($sender,3);
				if(!$cfg['允许自定义玩家名']) return self::SendMsg($sender,$cfg['Msg-FunctionDisabled']);
				if(!isset($args[1])) return self::sendErrorTip($sender,2);
				self::setRealName($sender,$args[1]);
				break;
			case 'check':
				if(self::checkObj($sender)!==0) return self::sendErrorTip($sender);
				if(!isset($args[1])) return self::sendErrorTip($sender,2);
				if(!self::isReg($args[1])){
					$log->warning('该账号未被注册');
					break;
				}
				self::sendLongNotice('查询到小号列表: '.implode(',',self::getOtherAccount(self::$provider->getPlayerInfo($args[1]))['name']));
				break;
			default:
				return false;
		}
		return true;
	}
	private function sendErrorTip(CommandSender $sender,int $type=1):bool{
		if($type==1) self::SendMsg($sender,TextFormat::RED.'权限不足');
		elseif($type==2) self::SendMsg($sender,TextFormat::RED.'缺少参数');
		else self::SendMsg($sender,TextFormat::RED.'此命令只能在游戏中执行');
		return true;
	}
	// 登录提示
	private function sendHello(Player $player){
		$cfg=self::$cfg;
		if(self::isReg($player)) self::SendMsg($player,$cfg['Msg-AuthTip1']);
		else self::SendMsg($player,$cfg['Msg-AuthTip2']);
		return;
	}
	// 大标题提示
	private function sendBigTitle(Player $player,string $title,string $subtitle,int $fadeIn,int $stay,int $fadeOut){
		if(\method_exists($player,'sendActionBar')) $player->sendActionBar($title,$subtitle,$fadeIn,$stay,$fadeOut);
		else $player->addTitle($title,$subtitle,$fadeIn,$stay,$fadeOut);
		return;
	}
	// 自动执行指令
	private function autoCommand(Player $player,string $event):bool{
		$cfg=self::$cfg['自动执行指令'];
		$server=self::$ser;
		if(!$cfg['开启']) return false;
		switch($event){
			case 'preLogin':
				if($cfg['进服后']=='null') return false;
				$server->dispatchCommand($player,$cfg['进服后']);
				break;
			case 'onReg':
				if($cfg['注册后']=='null') return false;
				$server->dispatchCommand($player,$cfg['注册后']);
				break;
			case 'onLogin':
				if($cfg['登录后']=='null') return false;
				$server->dispatchCommand($player,$cfg['登录后']);
				break;
			case 'onLeave':
				if($cfg['离开前']=='null') return false;
				$server->dispatchCommand($player,$cfg['离开前']);
				break;
			default:
				return false;
		}
		return true;
	}
	// 获取识别码
	private function getCid(Player $player,bool $forceCid=false):string{
		if(!$forceCid and \method_exists($player,'getXuid')) $return=$player->getXuid();    // strLen 16
		else $return=(string)$player->getClientId();    // strLen 18-20
		if(strlen($return)<EasyAuth::MIN_LEN) return 'unknown';
		return $return;
	}
	// 获取唯一识别码
	private function getUuid(Player $player):string{
		 return sha1(self::getCid($player).$player->getUniqueId()->toString());
	}
	// 删除玩家信息
	private function deletePlayer($player,bool $only=false){
		$server=self::$ser;
		$name=self::getObjName($player);
		if($server->isOp($name)) $server->removeOp($name);
		if(($player=$server->getPlayerExact($name))!==null) self::kick($player,self::$cfg['Msg-NameBanned']);
		if(!$only and !self::$cfg['超级封禁系统']['删除信息']) return;
		$tip=TextFormat::YELLOW.'玩家['.$name.']';
		if(!$only) $tip.='登陆信息删除'.(self::$provider->unRegPlayer($name)?TextFormat::GREEN.'成功':TextFormat::RED.'失败').',';
		$tip.=TextFormat::YELLOW.'账号信息删除'.(@unlink(EAPS.$name.'.dat')?TextFormat::GREEN.'成功':TextFormat::RED.'失败');
		self::$log->warning($tip);
		return;
	}
	// 包类型检测
	private function checkPacket($pk){
		try{
			$name=(new \ReflectionClass($pk))->getShortName();
		}catch(\Exception $exception){
			self::killServer('出现未知致命错误');
			exit(1);
		}
		switch($name){
			case 'BatchPacket':
				return false;
			case 'CommandRequestPacket':
			case 'CommandStepPacket':
				return 1;
			case 'TextPacket':
				return 2;
			case 'AnimatePacket':
				return 3;
			case 'LoginPacket':
				return 4;
			case 'DisconnectPacket':
				return 5;
			case 'ModalFormRequestPacket':
				return 6;
			case 'ModalFormResponsePacket':
				return 7;
		}
		return false;
	}
	// 发送邮件
	private function sendMail(string $to,string $newPwd):bool{
		if($to=='unknown') return false;
		$cfg=self::$cfg['密码找回'];
		$email=new Smtp($cfg['服务器'],$cfg['端口'],true,$cfg['用户名'],$cfg['密码']);
		//  邮件格式(HTML/TXT),TXT为文本邮件
		if($email->sendMail($to,$cfg['用户名'],$cfg['标题'],str_ireplace('#password#',$newPwd,$cfg['正文']),'HTML')=='') return false;
		return true;
	}
	// 终止连接(绕过PlayerKickEvent)
	private function kick(Player $player,string $reason=''){
		$notify=strlen($reason)>0;
		$pk=Create::DisconnectPacket();
		if($notify and self::checkPacket($pk)===5){
			$pk->message=$reason;
			for($i=0;$i<30;$i++) $player->dataPacket($pk);
			$notify=false;
		}
		$player->close('',$reason,$notify);
		self::$manager->callEvent(new PlayerForcedKickEvent($player,$reason));
		return;
	}
	// 发消息
	final public function SendMsg($player,string $msg){
		$cfg=self::$cfg;
		if(self::checkObj($player)===0){
			self::$log->warning($msg);
			return;
		}
		/** @var $player Player */
		$pk=Create::TextPacket();
		self::$msg[self::getObjName($player)]=$msg;
		$pk->message=$msg;
		if($cfg['使用另类提示信息显示']) $pk->type=$pk::TYPE_SYSTEM;
		else $pk->type=$pk::TYPE_RAW;
		$player->dataPacket($pk);
		return;
	}
	// 自动截取超长消息
	private function sendLongNotice(string $message,int $len=60){
		$time=ceil(strlen($message)/$len);
		for($i=0;$i<$time;$i++) self::$log->notice(substr($message,$i*$len,$len));
		return;
	}
	// 登陆计时模块
	final public function GetTimeOut(Player $player){
		$cfg=self::$cfg['超时踢人'];
		if(!$cfg['开启']) return null;
		$name=self::getObjName($player);
		$time=self::$joinTime;
		if(isset($time[$name])) return $cfg['超时时间(秒)']-(time()-$time[$name]);
		return 1;
	}
	// 载入完毕检测
	private function loadCheck($player):bool{
		$name=self::getObjName($player);
		if(!isset(self::$isLoaded[$name]) or self::$isLoaded[$name]==false) return false;
		return true;
	}
	// 获取连续登陆天数
	final public function GetContinueDay($player):int{
		if(!self::isReg($player)) return 1;
		return self::$provider->getPlayerInfo($player)['continue'];
	}
	// 通知管理
	private function toldOp(string $msg,Player $player=null){
		self::$log->info($msg);
		foreach(self::$ser->getOnlinePlayers() as $p){if(self::checkObj($p)===1 and $p!==$player){self::SendMsg($p,$msg);}}
		return;
	}
	// 登录
	private function authPlayer(Player $player,string $pwd):bool{
		$cfg=self::$cfg;
		$name=self::getObjName($player);
		if(($info=self::$provider->getPlayerInfo($name))==null) return false;
		if($info['password']!='unknown' and $info['password']===self::calPwd($pwd)){
			if(self::setAuthPlayer($player)){
				self::$manager->callEvent(new PlayerAuthEvent($player,'Pwd'));
				return true;
			}
		}else{
			if(($atp=$cfg['密码防碰撞'])['开启']){
				self::$pwdWrongTime[$name]++;
				$time=$atp['触发临时封禁']-self::$pwdWrongTime[$name];
				if($time<1){
					self::$bannedIP[(string)$player->getAddress()]=time()+$atp['封禁时长(秒)'];
					self::kick($player,$cfg['Msg-BanByACS']);
					return false;
				}
				if($time==1){
					self::SendMsg($player,str_ireplace('#long#',$atp['封禁时长(秒)'],$cfg['Msg-PwdWrongNearlyKick']));
					return false;
				}
			}
			self::SendMsg($player,$cfg['Msg-PwdWrong']);
		}
		return false;
	}
	// 注册
	private function regPlayer(Player $player,string $password):bool{
		$name=self::getObjName($player);
		if(self::isReg($name)) return false;
		$cfg=self::$cfg;
		if(!$cfg['注册开关']['状态']){
			self::SendMsg($player,$cfg['Msg-EntryDisable']);
			return false;
		}
		$ip=(string)$player->getAddress();
		if(!self::checkPwdFormat($player,$password)) return false;
		self::SendMsg($player,str_ireplace('#password#',$password,$cfg['Msg-CheckPassword']));
		$password=self::calPwd($password);
		$data=array(
			'IP'=>$ip,
			'continue'=>1,
			'AllCid'=>self::getCid($player,true),
			'Uid'=>self::getCid($player),
			'email'=>'unknown',
			'password'=>(string)$password,
			'lastuid'=>self::getUuid($player),
			'lastloginday'=>date('Y-m-d'),
			'regtime'=>date('Y-m-d H:i:s'),
			'lastlogin'=>date('Y-m-d H:i:s')
		);
		if(!self::$provider->sRegPlayer($name,$data)){
			self::SendMsg($player,$cfg['Msg-UndefinedError']);
			return false;
		}
		self::onLogin($player);
		self::setLogin($player);
		self::SendMsg($player,$cfg['Msg-RegTip']);
		self::toldOp(TextFormat::GREEN.'玩家['.$name.']注册成功',$player);
		if($cfg['使用大标题提示']) self::sendBigTitle($player,$cfg['Msg-RegTitle'],$cfg['Msg-RegSubTitle'],0,60,20);
		self::autoCommand($player,'onReg');
		self::$manager->callEvent(new PlayerAuthEvent($player,'Reg'));
		return true;
	}
	// 修改密码
	private function changePwd($name,string $pwd,$sender):bool{
		if(!self::checkPwdFormat($sender,$pwd)) return false;
		return self::$provider->sChangePwd(self::getObjName($name),self::calPwd($pwd));
	}
	// 设置已登录状态
	final public function setAuthPlayer(Player $player,bool $auto=false):bool{
		$cfg=self::$cfg;
		$name=self::getObjName($player);
		if(self::isBanned($name)){
			self::kick($player,$cfg['Msg-NameBanned']);
			return false;
		}
		$info=array(
			'lastuid'=>self::getUuid($player),
			'IP'=>(string)$player->getAddress(),
			'lastlogin'=>date('Y-m-d H:i:s')
		);
		$pInfo=self::$provider->getPlayerInfo($player);
		$info['AllCid']=self::checkCid($pInfo['AllCid'].','.self::getCid($player,true));
		$today=date('Y-m-d');
		if($pInfo['lastloginday']!==$today){
			$info['lastloginday']=$today;
			if(date('Y-m-d',strtotime($pInfo['lastloginday'])+86400)===$today) $info['continue']=++$pInfo['continue'];
			else $info['continue']=1;
		}
		if(self::$provider->sSetAuthPlayer($name,$info)){
			self::onLogin($player);
			self::setLogin($player);
			if($cfg['使用大标题提示']) self::sendBigTitle($player,$cfg['Msg-AuthPassTitle'],$cfg['Msg-AuthPassSubTitle'],0,60,20);
			self::autoCommand($player,'onLogin');
			if(!$auto){
				self::SendMsg($player,$cfg['Msg-AuthPass']);
				self::toldOp(TextFormat::GREEN.'玩家['.$name.']登陆成功',$player);
			}
			if($cfg['欢迎提示框']['开启']) self::sendUI($player,6860);
			return true;
		}
		$player->removeTitles();
		self::SendMsg($player,$cfg['Msg-UndefinedError']);
		return false;
	}
	private function onLogin(Player $player,bool $type=true){
		$cfg=self::$cfg['未登录状态'];
		$a=$cfg['禁止移动'];
		$b=$cfg['隐身'];
		if($type){
			if($a) $player->setImmobile(false);
			if($b){
				$player->setNameTagVisible(true);
				$player->setDataFlag(Entity::DATA_FLAGS,Entity::DATA_FLAG_INVISIBLE,false);
			}
			self::$lastMove[self::getObjName($player)]=time();
		}else{
			if($a) $player->setImmobile(true);
			if($b){
				$player->setNameTagVisible(false);
				$player->setDataFlag(Entity::DATA_FLAGS,Entity::DATA_FLAG_INVISIBLE,true);
			}
		}
		return;
	}
	// 注册检测
	private function isReg($player):bool{
		return self::$provider->sIsReg(self::getObjName($player));
	}
	// 登录检测
	final public static function isLogin($player):bool{
		$name=self::getObjName($player);
		if(!isset(self::$isLogin[$name])) self::$isLogin[$name]=\false;
		return self::$isLogin[$name]===\true;
	}
	// 设置登录状态
	private function setLogin(Player $player,bool $isLogin=true,bool $logout=false){
		static $isOp=array();
		self::check();
		$name=self::getObjName($player);
		if(!$logout) self::$isLogin[$name]=$isLogin;
		$server=self::$ser;
		if($isLogin){
			if(isset($isOp[$name])){
				$server->addOp($name);
				unset($isOp[$name]);
			}
			unset(self::$isLoaded[$name]);
			unset(self::$joinTime[$name]);
		}else{
			if(self::checkObj($player)===1){
				$isOp[$name]=true;
				$server->removeOp($name);
			}
			self::$isLoaded[$name]=false;
			self::$joinTime[$name]=time();
			self::$pwdWrongTime[$name]=0;
			self::$checkName[$name]=array('check'=>false,'name'=>null);
		}
		return;
	}
	// 玩家自动登录
	private function checkPlayer(Player $player,bool $set=true):bool{
		$cfg=self::$cfg['免密登录'];
		if(!$cfg['开启'] or ($info=self::$provider->getPlayerInfo($player))==null or (time()-strtotime($info['lastlogin']))>($cfg['时间限制(分)']*60)) return false;
		$uid=self::getUuid($player);
		$ip=(string)$player->getAddress();
		if(self::getCid($player)!='unknown' and $info['lastuid']===$uid and $info['IP']===$ip){
			if($set){
				if(!self::setAuthPlayer($player,true)) return false;
				self::$manager->callEvent(new PlayerAuthEvent($player,'Auto'));
			}
			return true;
		}
		return false;
	}
	// 绑定邮箱
	private function bindMail($player,string $mail):bool{
		if(stripos($mail,'@')>0 and strlen($mail)>5){
			if(self::$provider->sBindMail(self::getObjName($player),$mail)){
				self::SendMsg($player,str_ireplace('#email#',$mail,self::$cfg['Msg-MailBound']));
				return true;
			}
		}
		return false;
	}
	/**
	 * 确保所有名称相同
	 * @param $object string|Player|\pocketmine\level\Level
	 * @return string
	 */
	final public static function getObjName($object):string{
		if(!is_string($object) and $object!=null) $object=$object->getName();
		return strtolower(trim($object));
	}
	// 权限检测
	private function checkObj($obj):int{
		if($obj instanceof ConsoleCommandSender){
			return 0;
		}elseif($obj instanceof Player){
			if(self::$ser->isOP(self::getObjName($obj))) return 1;
			return 2;
		}else{return 3;}
	}
	// 检查密码是否符合格式
	private function checkPwdFormat($sender,string $pwd):bool{
		$cfg=self::$cfg;
		if(strlen($pwd)<1 or $pwd{0}=='/' or $pwd=='forgot' or $pwd=='unknown'){
			self::SendMsg($sender,$cfg['Msg-PwdNotAllow']);
			return false;
		}
		$check=$cfg['密码复杂度限制'];
		$len=strlen($pwd);
		if($len<$check['长度']){
			self::SendMsg($sender,str_ireplace('#long#',$check['长度'],$cfg['Msg-PwdNotLonger']));
			return false;
		}
		if(!$check['禁止纯数字']) return true;
		for($i=0;$i<$len;$i=$i+9){
			if(!is_int(substr($pwd,$i,9))){
				self::SendMsg($sender,$cfg['Msg-PwdNotSafe']);
				return false;
			}
		}
		return true;
	}
	// 超级封禁
	private function setSuperBanned($player){
		$name=self::getObjName($player);
		if(!self::isReg($name)){
			self::$log->warning('该账号未被注册');
			return false;
		}
		$banList=self::getOtherAccount(self::$provider->getPlayerInfo($name));
		if(!self::addBan($banList)) return false;
		foreach($banList['name'] as $banName) self::deletePlayer($banName);
		return $banList;
	}
	// 筛选小号
	private function getOtherAccount(array $info):array{
		$n=array();
		$c=explode(',',$info['AllCid']);
		$c[]=$info['Uid'];
		$i[]=$info['IP'];
		$e[]=$info['email'];
		$list=self::$provider->getPlayerList();
		for($t=0;$t<self::$cfg['超级封禁系统']['查找力度'];$t++){
			foreach($list as $p){
				$flag=false;
				if(in_array($p['IP'],$i) or (strlen($p['Uid'])>=EasyAuth::MIN_LEN and in_array($p['Uid'],$c)) or ($p['email']!=='unknown' and in_array($p['email'],$e))){
					$flag=true;
				}else{
					foreach($p['AllCid'] as $cid){
						if(strlen($cid)>=EasyAuth::MIN_LEN and in_array($cid,$c)){
							$flag=true;
							break;
						}
					}
				}
				if($flag){
					$n[]=$p['name'];
					$c=array_merge($c,$p['AllCid']);
					$c[]=$p['Uid'];
					$i[]=$p['IP'];
					$e[]=$p['email'];
				}
			}
			$n=array_unique($n);
			$c=array_unique(array_diff($c,array('unknown')));
			$i=array_unique($i);
			$e=array_unique($e);
		}
		return array('name'=>$n,'AllCid'=>$c,'IP'=>$i);
	}
	// 计算密码
	private function calPwd(string $pwd):string{
		$cfg=self::$cfg['密码加密方式(1-9)'];
		if($cfg==1) return $pwd;
		elseif($cfg==2) return base64_encode($pwd);
		elseif($cfg==3) return sha1($pwd);
		elseif($cfg==4) return md5($pwd);
		elseif($cfg==5) return hash('sha256',$pwd);
		elseif($cfg==6) return crc32($pwd);
		elseif($cfg==7) return hash('sha512',$pwd);
		elseif($cfg==8) return hash('gost',$pwd);
		else return sha1(md5($pwd));
	}
	// 插件全局参数类
	// 玩家名获取
	private function getRealName(string $key):string{
		$data=self::getConfigFile('players');
		if($data==null){
			self::saveConfigFile('players',array());
			return $key;
		}
		return isset($data[$key])?$data[$key]:$key;
	}
	// 玩家别名设置
	private function setRealName(Player $player,string $value):bool{
		if($value===null) return false;
		$name=strtolower($value);
		$len=strlen($value);
		$cfg=self::$cfg;
		if($name==='rcon' or $name==='console' or $len<1 or $len>16 or preg_match('/[^A-Za-z0-9_ ]/',$value)!==0){
			self::SendMsg($player,$cfg['Msg-NameNotAllow']);
			return false;
		}
		$key=self::$realName[$player->getAddress().':'.$player->getPort()];
		$data=self::getConfigFile('players');
		if(is_array($data)) $data[$key]=$value;
		else $data=array($key=>$value);
		if(self::saveConfigFile('players',$data)){
			self::$manager->callEvent(new PlayerChangeNameEvent($player,$key,$value));
			self::kick($player,$cfg['Msg-KickByChangeName']);
			return true;
		}
		self::SendMsg($player,$cfg['Msg-UndefinedError']);
		return false;
	}
	// 插件配置
	private function getSetting(bool $force=true):bool{
		$data=array(
			'极限模式'=>false,
			'密码加密方式(1-9)'=>9,
			'使用大标题提示'=>true,
			'允许自定义玩家名'=>true,
			'使用另类提示信息显示'=>true,
			'未登录屏蔽其他提示信息'=>false,
			'是否删除记录有误的玩家'=>false,
			'小号限制'=>array('开启'=>false,'限制人数'=>2),
			'远程控制'=>array('开启'=>false,'密码'=>'admin'),
			'注册开关'=>array('状态'=>true,'允许进服'=>false),
			'免密登录'=>array('开启'=>true,'时间限制(分)'=>120),
			'超时踢人'=>array('开启'=>true,'超时时间(秒)'=>120),
			'超级封禁系统'=>array('查找力度'=>2,'删除信息'=>false),
			'密码复杂度限制'=>array('长度'=>6,'禁止纯数字'=>false),
			'图形用户界面'=>array('开启(>=1.2)'=>true,'增强模式'=>false),
			'提示信息'=>array('加入游戏'=>'#name# 加入了游戏','离开游戏'=>'null'),
			'挂机清理'=>array('开启'=>true,'触发时间(秒)'=>300,'检测间隔(秒)'=>10),
			'检测客户端'=>array('开启'=>false,'临时封禁'=>true,'封禁时长(秒)'=>60),
			'密码防碰撞'=>array('开启'=>true,'触发临时封禁'=>5,'封禁时长(秒)'=>300),
			'进服出生点'=>array('开启'=>false,'x'=>0.0,'y'=>0.0,'z'=>0.0,'world'=>'null'),
			'欢迎提示框'=>array('开启'=>false,'标题'=>'登录成功~','内容'=>'欢迎光临~'),
			'粒子环绕效果'=>array('开启'=>true,'半径'=>2,'高度'=>3,'粒子密度'=>16,'RGB'=>'0,255,255'),
			'自动执行指令'=>array('开启'=>false,'进服后'=>'help','登录后'=>'help','注册后'=>'help','离开前'=>'null'),
			'自定义指令'=>json_encode(array('改密'=>'pwd','绑邮'=>'mail'),JSON_UNESCAPED_UNICODE),
			'未登录状态'=>array('隐身'=>false,'禁止修改地图'=>true,'禁止移动'=>true,'禁止使用物品'=>true,'免疫伤害'=>true,'禁止传送'=>true,'禁止打开箱子'=>true),
			'数据库设置'=>array('Enable'=>false,'Retry'=>3,'Host'=>'localhost','Port'=>3306,'Username'=>'root','Password'=>'admin','Database'=>'EasyAuth','Table'=>'players'),
			'密码找回'=>array('开启'=>false,'服务器'=>'smtp.126.com','端口'=>'25','用户名'=>'test@126.com','密码'=>'123456789','标题'=>'密码找回','正文'=>'请在游戏中输入以下指令[forgot #password#]或输入以下验证码[#password#]以通过验证,如非您的操作请忽略此邮件'),
			// 提示信息
			'Msg-UndefinedError'=>'§l§c出现未定义错误,请联系管理员',
			'Msg-EntryDisabled'=>'§c服务器已关闭注册功能',
			'Msg-FunctionDisabled'=>'§c服务器没有开启此功能',
			'Msg-NameNotAllow'=>'§c非法的玩家名组合',
			'Msg-NameBanned'=>'§l§c该账号已被封禁',
			'Msg-IPBanned'=>'§c您已被临时封禁至: #TimeOfEnd#\n请规范您的游戏行为',
			'Msg-SetNameTip1'=>'§c当前玩家名为[#username#],请输入新玩家名(请勿输入中文)',
			'Msg-SetNameTip2'=>'§l§c如无需修改请输入当前玩家名,修改玩家名后请重新进入游戏',
			'Msg-AuthTip1'=>'§c该账号已被注册,§l请直接输入密码以登陆游戏',
			'Msg-AuthTip2'=>'§c该账号尚未被注册,§l请直接输入密码以注册此账号',
			'Msg-AutoLogin'=>'§l§a自动登录成功,祝您游戏愉快!',
			'Msg-NotAuth'=>'§l§c尚未登录!',
			'Msg-AuthPass'=>'§l§a登录成功,祝您游戏愉快!',
			'Msg-PwdWrong'=>'§l§c密码错误,如遗忘您的密码请输入[forgot]',
			'Msg-CodePass'=>'§l§a验证码正确,请更改您的密码!',
			'Msg-CodeWrong'=>'§l§c验证码错误,请重试.',
			'Msg-PwdWrongNearlyKick'=>'§l§c密码错误次数即将到达上限,下一次错误将导致被踢出#long#秒',
			'Msg-PwdChanged'=>'§a您的登陆密码已修改为: #password#',
			'Msg-RegTip'=>'§a注册成功,绑定邮箱请输入[/eauth mail #邮箱地址#],邮箱只可以绑定一次',
			'Msg-LongTimeKick'=>'§c长时间未登录/注册(超时时间:#t#秒)',
			'Msg-SameNameLogin'=>'§c玩家名已被占用',
			'Msg-SameNameNotLogin'=>'§c玩家名已被占用(另一玩家登录)',
			'Msg-SameIpLogin'=>'§c服务器已限制单IP只能#count#名玩家游戏',
			'Msg-SameIpNotLogin'=>'§c服务器已限制单IP只能#count#名玩家游戏(另一玩家登录)',
			'Msg-CheckPassword'=>'§a请牢记您的密码: #password#',
			'Msg-BanByACS'=>'§c密码错误次数超过上限',
			'Msg-PwdSendDetect'=>'§c检测到聊天信息中包含您的密码,已自动拦截.',
			'Msg-SuperBan'=>'§c服主发现玩家#player#想搞事情,并当场实施了永世封禁,就连小号也没有放过.让我们一同挥手致敬: 再见了#players#~',
			'Msg-MailBound'=>'§a邮箱绑定成功!账号绑定邮箱: #email#',
			'Msg-MailBindFailed'=>'§c该账号已绑定邮箱,如需更改请找服主!',
			'Msg-MailSent'=>'§a验证码已发送至账号绑定的邮箱,请注意查收',
			'Msg-MailSendFailed'=>'§c邮件发送失败(可能原因: 绑定的邮箱不存在/服务器配置问题)',
			'Msg-WrongMailFormat'=>'§c错误的邮箱格式',
			'Msg-KickByCleaner'=>'§c您已触发服务器挂机清理功能,挂机时间请勿超过#time#秒',
			'Msg-ServerDisable'=>'§c服务器已进入维护模式',
			'Msg-PwdNotAllow'=>'§c密码包含关键词,禁止使用',
			'Msg-PwdNotLonger'=>'§c密码长度必须长于#long#字符',
			'Msg-PwdNotSafe'=>'§c密码不能为纯数字',
			'Msg-KickByChangeName'=>'§a玩家名修改成功,请重新进入游戏',
			'Msg-ChangeToName'=>'§6请再次输入玩家名以确认修改,目标玩家名: #newname#',
			'Msg-NotAuthTitle'=>'§a请输入密码',
			'Msg-NotAuthSubTitle'=>'_(:з」∠)_',
			'Msg-RegTitle'=>'§a欢迎新人~',
			'Msg-RegSubTitle'=>'注册成功啦( ^3^ )/~~',
			'Msg-AuthPassTitle'=>'§a欢迎回来~',
			'Msg-AuthPassSubTitle'=>'登录成功_(・ω・”∠)_',
			'Msg-NameNotSetTitle'=>'§a请输入玩家名',
			'Msg-NameNotSetSubTitle'=>'若无需更改则输入当前玩家名',
			'Msg-ClientNotAllow'=>'§l§c服务器已开启客户端检测功能,请勿使用外挂进服!',
			'Gui-Retry'=>'重试',
			'Gui-Need'=>'要',
			'Gui-NotNeed'=>'以后再说',
			'Gui-True'=>'正确',
			'Gui-False'=>'错误',
			'Gui-Exit'=>'退出游戏',
			'Gui-Later'=>'以后再绑定',
			'Gui-Back'=>'返回',
			'Gui-Select'=>'请选择操作',
			'Gui-WrongEmail'=>'邮箱格式错误',
			'Gui-WrongName'=>'新玩家名不符合格式',
			'Gui-WrongPwd'=>'密码不符合格式',
			'Gui-WrongCode'=>'验证码错误',
			'Gui-PwdWrong'=>'密码错误',
			'Gui-Forgot'=>'忘记密码',
			'Gui-InputPwd'=>'请输入密码',
			'Gui-InputCode'=>'请输入验证码',
			'Gui-Name'=>'您当前的玩家名为: [#name#]',
			'Gui-ChangeName'=>'修改玩家名',
			'Gui-Auth'=>'登录/注册',
			'Gui-BindEmail'=>'绑定邮箱',
			'Gui-AutoAuth'=>'自动登陆',
			'Gui-ConfirmPwd'=>'您输入的密码为: [#password#]',
			'Gui-ConfirmMail'=>'您输入的邮箱为: [#email#]',
			'Gui-ConfirmName'=>'您的玩家名将更改为: [#name#]',
			'Gui-AskBind'=>'该邮箱将用于找回密码,且不可更换(如需修改请找服主)'
		);
		$log=self::$log;
		$getData=self::getConfigFile('config');
		if($getData==null){
			if($force or self::checkJsonError()){
				self::saveConfigFile('config',$data,false,false);
				$log->warning('配置文件已自动生成,请注意进行修改!');
				self::$cfg=$data;
			}else{
				$log->emergency('配置文件重载失败,如需恢复默认请自行删除配置文件');
				return false;
			}
		}else{
			$checkData=self::checkConfig($data,$getData);
			if($checkData!==$getData){
				self::saveConfigFile('config',$checkData,false,false);
				$log->critical('配置文件中部分配置项已更新,请注意.');
			}
			self::$cfg=$checkData;
		}
		return true;
	}
	// BanList
	private function displayBanList(){
		$log=self::$log;
		if(count(self::$banList)<1){
			$log->warning('暂无sBan记录');
			return;
		}
		foreach(self::$banList as $time=>$l){
			$log->notice(date('Y-m-d H:i:s',$time).' 事件ID['.$time.']');
			if(isset($l['name'])) self::sendLongNotice('封禁玩家['.implode(',',$l['name']).']');
			if(isset($l['AllCid'])) self::sendLongNotice('封禁CID['.implode(',',$l['AllCid']).']');
			if(isset($l['IP'])) self::sendLongNotice('封禁IP['.implode(',',$l['IP']).']');
		}
		$log->warning('sBan记录显示完毕');
		return;
	}
	private function isBanned(string $name,array $cid=[],string $ip=null):bool{
		$log=self::$log;
		foreach(self::$banList as $l){
			if(isset($l['name']) and in_array($name,$l['name'])){
				$log->warning('检测到玩家名被封禁: '.$name);
				return true;
			}
			if(isset($l['AllCid'])){
				foreach($cid as $c){
					if(in_array($c,$l['AllCid'])){
						$log->warning('检测到玩家客户端被封禁: '.$c);
						return true;
					}
				}
			}
			if($l['IP'] and in_array($ip,$l['IP'])){
				$log->warning('检测到玩家IP地址被封禁: '.$ip);
				return true;
			}
		}
		return false;
	}
	private function addBan(array $list):bool{
		self::$banList[(string)time()]=$list;
		if(!self::saveBanList()){
			self::$log->error('发生未知错误,无法储存封禁列表,操作取消');
			return false;
		}
		return true;
	}
	private function removeBan(string $time):bool{
		if(!isset(self::$banList[$time])) return false;
		unset(self::$banList[$time]);
		return self::saveBanList();
	}
	private function getBanList(){
		$data=self::getConfigFile('BanList');
		if(!is_array($data)){
			self::$banList=array();
		}else{
			$list=array();
			foreach($data as $t=>$c){
				if(is_array($c)){
					if(isset($c['name'])){
						$d=$c['name'];
						sort($d);
						if(is_array($d) and count($d)>0) $list[$t]['name']=$d;
					}
					if(isset($c['AllCid'])){
						$d=$c['AllCid'];
						sort($d);
						if(is_array($d) and count($d)>0) $list[$t]['AllCid']=$d;
					}
					if(isset($c['IP'])){
						$d=$c['IP'];
						sort($d);
						if(is_array($d) and count($d)>0) $list[$t]['IP']=$d;
					}
				}
			}
			self::$banList=$list;
			if(self::$banList!==$data) self::saveBanList();
		}
		return;
	}
	private function saveBanList():bool{
		return self::saveConfigFile('BanList',self::$banList);
	}
	// 配置文件类
	// 配置文件格式检测
	private static function checkConfig(array $ori,$check):array{
		if(!is_array($check)) return $ori;
		foreach(array_keys($ori) as $key){
			if(isset($check[$key])){
				$o=&$ori[$key];
				$c=&$check[$key];
				if(is_bool($o)){
					if(is_bool($c)) $o=$c;
				}elseif(is_int($o)){
					if(is_int($c) and $c>0) $o=$c;
				}elseif(is_float($o)){
					if(is_numeric($c)) $o=$c;
				}elseif(is_string($o)){
					if(is_string($c)) $o=$c;
				}elseif(is_array($o)){
					if(is_array($c)) $o=self::checkConfig($o,$c);
				}else{$o=$c;}
			}
		}
		return $ori;
	}
	// 玩家数据格式检测
	final public static function checkPlayerInfo($data){
		if(!is_array($data)) return false;
		$example=array(
			'regtime'=>'1970-01-01 00:00:00',
			'lastlogin'=>'1970-01-01 00:00:00',
			'lastloginday'=>'1970-01-01',
			'password'=>'unknown',
			'lastuid'=>'unknown',
			'AllCid'=>'unknown',
			'Uid'=>'unknown',
			'IP'=>'0.0.0.0',
			'continue'=>1,
			'email'=>'unknown'
		);
		$data=self::checkConfig($example,$data);
		if(stripos($data['email'],'@')<1) $data['email']='unknown';
		$data['AllCid']=self::checkCid($data['AllCid']);
		return $data;
	}
	// 修正Cid
	private static function checkCid(string $list):string{
		$cid=array();
		foreach(explode(',',$list) as $c){
			if(!is_string($c)) continue;
			if(strlen($c)>=EasyAuth::MIN_LEN) $cid[]=$c;
		}
		return implode(',',array_unique($cid));
	}
	// 检测Json错误
	private function checkJsonError():bool{
		switch(json_last_error()){
			case JSON_ERROR_NONE:
				return true;
			case JSON_ERROR_DEPTH:
				self::$log->emergency('文件读取错误: 到达了最大堆栈深度');
				break;
			case JSON_ERROR_STATE_MISMATCH:
				self::$log->emergency('文件读取错误: 无效的 JSON');
				break;
			case JSON_ERROR_CTRL_CHAR:
				self::$log->emergency('文件读取错误: 控制字符错误');
				break;
			case JSON_ERROR_SYNTAX:
				self::$log->emergency('文件读取错误: 语法错误');
				break;
			case JSON_ERROR_UTF8:
				self::$log->emergency('文件读取错误: 异常的 UTF-8 字符');
				break;
			case JSON_ERROR_RECURSION:
				self::$log->emergency('文件读取错误: 一个或多个递归引用被编码');
				break;
			case JSON_ERROR_INF_OR_NAN:
				self::$log->emergency('文件读取错误: 一个或多个 NAN/INF 值被编码');
				break;
			case JSON_ERROR_UNSUPPORTED_TYPE:
				self::$log->emergency('文件读取错误: 指定的类型/值无法编码');
				break;
			case JSON_ERROR_INVALID_PROPERTY_NAME:
				self::$log->emergency('文件读取错误: 指定的属性名无法编码');
				break;
			case JSON_ERROR_UTF16:
				self::$log->emergency('文件读取错误: 畸形的 UTF-16 字符');
				break;
			default:
				self::$log->emergency('文件读取错误: 未被定义的错误');
				break;
		}
		return false;
	}
	/**
	 * 读取配置文件
	 * @param String $file 文件名
	 * @param bool $dir 是否为玩家信息文件
	 * @return array|null
	 */
	final public function getConfigFile(string $file,bool $dir=false){
		$return=self::getFileContent(urlencode(strtolower(trim($file))).'.json',$dir);
		if($return!=null) return @json_decode($return,true);
		return null;
	}
	private function getFileContent(string $file,bool $dir=false){
		if(!$dir) $path=EAP.$file;
		else $path=EAPP.$file;
		if(!file_exists($path)) return null;
		$return=@file_get_contents($path);
		if(strlen($return)<2) return null;
		return self::convertString($return);
	}
	final public static function convertString(string $str){
		$encode=@mb_detect_encoding($str,array('ASCII','UTF-8','GB2312','GBK','BIG5'));
		if($encode===false) return null;
		if($encode==='UTF-8') return $str;
		return str_replace('[QueMark]','?',str_replace('?','',@mb_convert_encoding(str_replace('?','[QueMark]',$str),'UTF-8',$encode)));
	}
	/**
	 * 保存配置文件
	 * @param String $file 文件名
	 * @param array $context 写入的数据
	 * @param bool $dir 是否为玩家信息文件
	 * @param bool $update 是否更新全局变量
	 * @return bool
	 */
	final public function saveConfigFile(string $file,array $context,bool $dir=false,bool $update=true):bool{
		self::check();
		if(!$dir) $path=EAP;
		else $path=EAPP;
		$path.=urlencode(strtolower(trim($file))).'.json';
		$return=@file_put_contents($path,@json_encode($context,JSON_PRETTY_PRINT|JSON_BIGINT_AS_STRING|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))!==false;
		if(!$dir and $file==='config'){
			if(!$return){
				self::killServer('致命错误: 无配置文件写入权限');
				exit(1);
			}elseif($update){self::getSetting();}
		}
		return $return;
	}
	// 关闭服务器
	final public function killServer(string $message=null){
		self::deadMode();
		@\error_clear_last();
		if($message!=null) echo($message.PHP_EOL);
		try{@self::$ser->forceShutdown();}
		finally{@\lakwsh\EasyAuth\kill();}
		exit(1);
	}
	// 关闭状态检测
	private function check(){
		if(self::deadMode(true)){
			@\lakwsh\EasyAuth\kill();
			\sleep(99999);
			exit(1);
		}
		return;
	}
	// 死亡模式
	final public static function deadMode(bool $check=false):bool{
		if(!$check and !\defined('deadMode')) @\define('deadMode',true);
		return \defined('deadMode');
	}
}
// TcpServer
class TcpServer{
	private static $key;
	private static $plugin;
	private static $socket;
	private static $antiSys=array();
	private static $blockAddress=array();
	final public function __construct($plugin,int $port){
		if(!($plugin instanceof EasyAuth)){
			EasyAuth::deadMode();
			return;
		}
		self::$plugin=$plugin;
		self::$key=self::format_key('MIIBCwIBADANBgkqhkiG9w0BAQEFAASB9jCB8wIBAAIxAOxd4SNmss2giStucH0KDXnBoXwM1bZIXK6c/p04+niVlJPhqkAM3vYY9hL/8x3axwIDAQABAjEArx2WcQ3jJqjrNzwpJtpNxYkJRMiVhOjyJO4QCGiARWOLAbFIahORHUxVuLh83j5BAhkA+5bLDYrp36vJRN225Ft1EGPO50HbeYjxAhkA8ILDkkAGS6QJhHSs+VRpUBEnBIZVMF83Ahh5Uf76udkLrfgxiETwm5W44JhediiS09ECGQDOGUA+M18xsn/1YYZYol0cn5Yv6m1V5kECGGGdLeybrkAWNS1eWTop7eakBqUI0S6LAw==');
		self::$socket=$socket=@\socket_create(AF_INET,SOCK_STREAM,SOL_TCP);
		if($socket===false or !@\socket_bind($socket,'0.0.0.0',$port) or !@\socket_listen($socket)){
			self::$plugin->killServer();
			exit(1);
		}
		@\socket_set_nonblock($socket);  // 防止阻塞进程
		return;
	}
	private function read($client){
		$pk=@\socket_read($client,4);
		if($pk===false or strlen($pk)!==4) return null;
		if(PHP_INT_SIZE===8) $size=unpack('V',$pk)[1]<<32>>32;
		else $size=unpack('V',$pk)[1];
		if($size>512) return null;
		$pk=@\socket_read($client,$size);
		if($pk===false or strlen($pk)!==$size) return null;
		$payload=self::decode($pk);
		if($payload!=null) return $payload;
		return null;
	}
	private function write($client,$payload){
		$payload=self::encode($payload);
		$head=pack('V',strlen($payload));
		@\socket_write($client,$head.$payload);
		return;
	}
	final public function process(){
		$client=@\socket_accept(self::$socket);
		if($client===false) return;
		@\socket_getpeername($client,$address,$port);
		if(!in_array($address,self::$blockAddress)){
			$sys=&self::$antiSys;
			$sys[$address]=0;
			$pk=self::read($client);    // 取密码
			if($pk!=null and $pk===self::$plugin->getPassword()){
				$pk=self::read($client);    // 取指令
				if($pk!=null){
					$return=self::$plugin->handlePacket($pk);
					if($return!=null) self::write($client,$return);
				}
			}else{
				++$sys[$address];
				if($sys[$address]>20) self::block($address);
			}
		}
		@\socket_shutdown($client,2);
		@\socket_close($client);
		unset($client);
		return;
	}
	private function block(string $address){
		array_push(self::$blockAddress,$address);
		self::$plugin->blockAddress($address);
		return;
	}
	final public function close(){
		@\socket_shutdown(self::$socket,2);
		@\socket_close(self::$socket);
		return;
	}
	private static function encode($data){
		$result=array();
		while(strlen($chunk=substr($data,0,37))>0){
			$data=substr($data,strlen($chunk));
			@\openssl_private_encrypt($chunk,$output,self::$key,OPENSSL_PKCS1_PADDING);
			$result[]=base64_encode($output);
			unset($output,$chunk);
		}
		return implode('**&&**',$result);
	}
	private static function decode($data){
		$result=array();
		foreach(explode('**&&**',$data) as $chunk){
			@\openssl_private_decrypt(base64_decode($chunk),$output,self::$key,OPENSSL_PKCS1_PADDING);
			$result[]=$output;
			unset($output,$chunk);
		}
		return implode('',$result);
	}
	private static function format_key($key){
		$pem=chunk_split($key,64,PHP_EOL);
		return '-----BEGIN PRIVATE KEY-----'.PHP_EOL.$pem.'-----END PRIVATE KEY-----';
	}
}
// Command
class Command extends _Command{
	private static $plugin;
	private static $aliases;
	final public function __construct($plugin,$usage,array $aliases){
		if(!($plugin instanceof EasyAuth)){
			EasyAuth::deadMode();
			return;
		}
		self::$plugin=$plugin;
		self::$aliases=$aliases;
		parent::__construct('eauth',$plugin);
		parent::setUsage($usage==null?'':$usage);
		parent::setAliases(array_keys($aliases));
		parent::setDescription('登陆插件主指令');
		return;
	}
	final protected function _execute(CommandSender $sender,string $label,array $args):bool{
		if($label!='eauth') array_unshift($args,self::$aliases[$label]);
		$success=self::$plugin->CmdExecutor($sender,$args);
		if(!$success) $sender->sendMessage(parent::getUsage());
		return $success;
	}
}
class PTT extends SI{
	private static $plugin;
	final public function __construct($plugin){
		if(!($plugin instanceof EasyAuth)) EasyAuth::deadMode();
		else self::$plugin=$plugin;
		return;
	}
	final protected function preTick(){
		self::$plugin->preTick();
		return;
	}
	final protected function emergency(){
		self::$plugin->killServer();
		return;
	}
}
// 数据存储方式
// MySQL Provider
class MysqlDataProvider{
	private static $cfg;
	private static $plugin;
	/** @var $database \mysqli */
	private static $database;
	private static $retry=0;
	private static $recon=false;
	const Name='MysqlDataProvider';
	final public function __construct($plugin){
		if(!($plugin instanceof EasyAuth)) EasyAuth::deadMode();
		else self::$plugin=$plugin;
		return;
	}
	private function encode(string $str){
		if(self::$recon) return null;
		return self::$database->real_escape_string(self::$plugin->convertString($str));
	}
	final public function initConnect(array $config):bool{
		self::$cfg=$config;
		$log=self::$plugin->getLogger();
		if(!self::tryConnect()){
			while(true){
				self::$retry++;
				$log->critical('无法连接至数据库,正在进行第'.self::$retry.'次重新连接...');
				if(self::tryConnect()){
					self::$retry=0;
					break;
				}
				if(self::$retry>=$config['Retry']) return false;
				sleep(5);
			}
		}
		self::$plugin->setTask('ping',true);
		$check=array(
			'name'=>'VARCHAR(64)',
			'lastuid'=>'VARCHAR(40)',
			'regtime'=>'DateTime',
			'lastlogin'=>'DateTime',
			'lastloginday'=>'VARCHAR(10)',
			'password'=>'VARCHAR(255)',
			'AllCid'=>'Text',
			'Uid'=>'VARCHAR(20)',
			'IP'=>'VARCHAR(15)',
			'continue'=>'Int(4)',
			'email'=>'VARCHAR(100)'
		);
		$cmd='CREATE TABLE IF NOT EXISTS `'.$config['Table'].'` (';
		foreach($check as $key=>$value) $cmd.='`'.$key.'` '.$value.',';
		$cmd.='UNIQUE (name));';
		if(!self::connect($cmd)){
			$log->critical('无表单创建权限,无法使用数据库作为数据提供方式.');
			return false;
		}
		$return=self::connect('SELECT `column_name`,`column_type` FROM information_schema.columns WHERE `table_schema`="'.$config['Database'].'" AND `table_name`="'.$config['Table'].'";',true,false);
		if($return===false){
			$log->critical('无表单修改权限,无法使用数据库作为数据提供方式.');
			return false;
		}
		$result=array();
		foreach($return as $column) $result[$column[0]]=$column[1];
		$result=self::array_compare($check,$result);
		if($result['count']==0) return true;
		foreach($result['add'] as $key=>$value){
			if(!self::connect('ALTER TABLE `'.$config['Table'].'` ADD `'.$key.'` '.$value.';')){
				$log->critical('无创建列权限,无法使用数据库作为数据提供方式.');
				return false;
			}
		}
		foreach($result['delete'] as $key){
			if(!self::connect('ALTER TABLE `'.$config['Table'].'` DROP `'.$key.'`;')){
				$log->critical('无删除列权限,无法使用数据库作为数据提供方式.');
				return false;
			}
		}
		foreach($result['check'] as $key=>$value){
			if(!self::connect('ALTER TABLE `'.$config['Table'].'` MODIFY `'.$key.'` '.$value.';')){
				$log->critical('无修改列权限,无法使用数据库作为数据提供方式.');
				return false;
			}
		}
		return true;
	}
	private function array_compare(array $array1,array $array2):array{
		$add=array();
		$delete=array();
		$check=array();
		foreach($array2 as $key=>$value){if(!isset($array1[$key])){$delete[]=$key;}}
		foreach($array1 as $key=>$value){
			if(!isset($array2[$key])) $add[$key]=$value;
			elseif($array2[$key]!=strtolower($value)) $check[$key]=$value;
		}
		return array('count'=>(count($add)+count($delete)+count($check)),'add'=>$add,'delete'=>$delete,'check'=>$check);
	}
	private function tryConnect():bool{
		$cfg=self::$cfg;
		@self::$database=new \mysqli($cfg['Host'],$cfg['Username'],$cfg['Password'],$cfg['Database'],$cfg['Port']);
		if($no=self::$database->connect_errno){
			self::$plugin->getLogger()->error('数据库连接错误: '.$no);
			return false;
		}
		self::$database->set_charset('utf8');
		return true;
	}
	private function setReconnectMode(){
		self::$recon=true;
		self::$plugin->getLogger()->critical('数据库连接中断,进入重连模式...');
		self::$plugin->setTask('ping',false);
		self::$plugin->setTask('reconnect',true);
		return;
	}
	final public function reconnectTask(){
		$log=self::$plugin->getLogger();
		self::$retry++;
		$log->warning('正在尝试重新连接数据库...');
		if(self::tryConnect()){
			self::$recon=false;
			self::$retry=0;
			$log->notice('数据库重连成功!');
			$tasks['PingTask']=true;
			$tasks['reconnectTask']=false;
		}else{
			if(self::$retry>=self::$cfg['Retry']){
				$tasks['reconnectTask']=false;
				self::$plugin->killServer('数据库重连次数超出上限');
				exit(1);
			}
			$log->critical('第'.self::$retry.'次数据库连接失败.');
		}
		return;
	}
	final public function PingTask(){
		if(!@self::$database->ping()) self::setReconnectMode();
		return;
	}
	private function connect(string $sql,bool $check=false,bool $only=true){
		if(self::$recon) return false;
		$result=self::$database->query($sql);
		if($result===false){
			self::$plugin->getLogger()->error('数据库错误: '.self::$database->error);
			self::setReconnectMode();
			return false;
		}elseif($check){
			if($only){
				$data=$result->fetch_assoc();
			}else{
				$data=array();
				while($res=$result->fetch_row()) array_push($data,$res);
			}
			$result->free();
			return $data;
		}else{return true;}
	}
	private function updateInfo(array $data,string $name):bool{
		$cmd='UPDATE `'.self::$cfg['Table'].'` SET `';
		foreach($data as $key=>$value) $cmd.=$key.'`="'.self::encode($value).'",`';
		$cmd=substr($cmd,0,-2).' WHERE name="'.self::encode($name).'";';
		if(self::connect($cmd)===false){
			self::$plugin->getLogger()->critical('玩家['.$name.']的数据更新操作已被舍弃!');
			return false;
		}
		return true;
	}
	final public function getPlayerInfo($player){
		$name=self::$plugin->getObjName($player);
		$data=self::connect('SELECT * FROM `'.self::$cfg['Table'].'` WHERE `name`="'.self::encode($name).'";',true);
		$checkData=self::$plugin->checkPlayerInfo($data);
		if($checkData===false) return null;
		if($checkData!==$data) self::updateInfo($checkData,$name);
		return $checkData;
	}
	final public function unRegPlayer(string $name):bool{
		return self::connect('DELETE FROM `'.self::$cfg['Table'].'` WHERE `name`="'.self::encode($name).'"');
	}
	final public function close():bool{
		if(self::$database instanceof \mysqli) @self::$database->close();
		return true;
	}
	final public function sIsReg($name):bool{
		if(self::getPlayerInfo($name)!=null) return true;
		return false;
	}
	final public function sRegPlayer(string $name,array $data):bool{
		$cmd='INSERT INTO `'.self::$cfg['Table'].'` (`name`,`'.implode('`,`',array_keys($data)).'`) VALUES ("'.self::encode($name).'"';
		foreach($data as $value) $cmd.=',"'.self::encode($value).'"';
		return self::connect($cmd.');');
	}
	final public function sChangePwd(string $name,string $password):bool{
		return self::updateInfo(array('lastuid'=>'unknown','password'=>$password),$name);
	}
	final public function sSetAuthPlayer(string $name,array $array):bool{
		return self::updateInfo($array,$name);
	}
	final public function getPlayerList(bool $only=false):array{
		$result=self::connect('SELECT `name`,`IP`,`AllCid`,`Uid`,`email`,`lastloginday` FROM `'.self::$cfg['Table'].'`;',true,false);
		$players=array();
		if($result!==false and $result!=null){
			foreach($result as $player){
				if($only){
					$players[]=$player[0];
					continue;
				}
				$players[]=array(
					'name'=>$player[0],
					'IP'=>(string)$player[1],
					'AllCid'=>explode(',',$player[2]),
					'Uid'=>$player[3],
					'email'=>$player[4],
					'lastloginday'=>$player[5]
				);
			}
		}
		return $players;
	}
	final public function sBindMail(string $name,string $mail):bool{
		return self::updateInfo(array('email'=>$mail),$name);
	}
}
// Json Provider
class JsonDataProvider{
	private static $plugin;
	const Name='JsonDataProvider';
	final public function __construct($plugin){
		if(!($plugin instanceof EasyAuth)) EasyAuth::deadMode();
		else self::$plugin=$plugin;
		return;
	}
	final public function getPlayerInfo($player){
		$name=self::$plugin->getObjName($player);
		$data=self::$plugin->getConfigFile($name,true);
		$checkData=self::$plugin->checkPlayerInfo($data);
		if($checkData===false) return null;
		if($checkData!==$data) self::$plugin->saveConfigFile($name,$checkData,true);
		return $checkData;
	}
	final public function unRegPlayer(string $name):bool{
		return @unlink(EAPP.urlencode($name).'.json');
	}
	final public function sIsReg($name):bool{
		return self::getPlayerInfo($name)!=null;
	}
	final public function sRegPlayer(string $name,array $data):bool{
		return self::$plugin->saveConfigFile($name,$data,true);
	}
	final public function sChangePwd(string $name,string $password):bool{
		$data=self::getPlayerInfo($name);
		$data['password']=(string)$password;
		$data['lastuid']='unknown';
		return self::$plugin->saveConfigFile($name,$data,true);
	}
	final public function sSetAuthPlayer(string $name,array $array):bool{
		return self::$plugin->saveConfigFile($name,array_merge(self::getPlayerInfo($name),$array),true);
	}
	final public function getPlayerList(bool $only=false):array{
		$players=array();
		$len=strlen(EAPP);
		foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(EAPP)) as $player){
			if(stripos($player,'.json')===false) continue;
			$name=substr($player,$len,-5);
			if(($info=self::getPlayerInfo($name))==null) continue;
			if($only){
				$players[]=$name;
				continue;
			}
			$players[]=array(
				'name'=>$name,
				'IP'=>(string)$info['IP'],
				'AllCid'=>explode(',',$info['AllCid']),
				'Uid'=>$info['Uid'],
				'email'=>$info['email'],
				'lastloginday'=>$info['lastloginday']
			);
		}
		return $players;
	}
	final public function sBindMail(string $name,string $mail):bool{
		$data=self::getPlayerInfo($name);
		$data['email']=$mail;
		return self::$plugin->saveConfigFile($name,$data,true);
	}
	final public function initConnect(){
		self::$plugin->killServer();
		exit(1);
	}
	final public function reconnectTask(){
		self::$plugin->killServer();
		exit(1);
	}
	final public function PingTask(){
		self::$plugin->killServer();
		exit(1);
	}
	final public function close(){
		return;
	}
}
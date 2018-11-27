<?php
namespace lakwsh\EasyAuth\event;
/**
 * 生物ID(退出后会变更)(PlayerPreLoginEvent时为NULL)
 * HIGHEST 最终决定权
 * LOWEST 最先决定权
 * InventoryOpenEvent 玩家登陆期间不可取消
 * ignoreCancelled 取消调用当Event被取消
 * PlayerInteractEvent->BlockPlaceEvent
 * 新思路: 收包时拦截
 */
use pocketmine\event\EventPriority;
use pocketmine\plugin\MethodEventExecutor;

class EventListener implements \pocketmine\event\Listener{
	private static $plugin;
	public function __construct($plugin){
		if(!($plugin instanceof \lakwsh\EasyAuth\EasyAuth)) \lakwsh\EasyAuth\EasyAuth::deadMode();
		else self::$plugin=$plugin;
		return;
	}
	public function init(\pocketmine\plugin\PluginManager $manager){
		$plugin=self::$plugin;
		try{
			$manager->registerEvent('pocketmine\\event\\block\\BlockPlaceEvent',$this,EventPriority::HIGHEST,new MethodEventExecutor('onBlockPlace'),$plugin,false);
			$manager->registerEvent('pocketmine\\event\\block\\BlockBreakEvent',$this,EventPriority::HIGHEST,new MethodEventExecutor('onBlockBreak'),$plugin,false);
			$manager->registerEvent('pocketmine\\event\\entity\\EntityDamageEvent',$this,EventPriority::HIGHEST,new MethodEventExecutor('onEntityDamage'),$plugin,false);
			$manager->registerEvent('pocketmine\\event\\player\\PlayerInteractEvent',$this,EventPriority::HIGHEST,new MethodEventExecutor('onPlayerInteract'),$plugin,false);
			$manager->registerEvent('pocketmine\\event\\player\\PlayerCreationEvent',$this,EventPriority::LOWEST,new MethodEventExecutor('onPlayerCreation'),$plugin,false);
			$manager->registerEvent('pocketmine\\event\\player\\PlayerPreLoginEvent',$this,EventPriority::LOWEST,new MethodEventExecutor('onPlayerPreLogin'),$plugin,false);
			$manager->registerEvent('pocketmine\\event\\server\\DataPacketSendEvent',$this,EventPriority::LOWEST,new MethodEventExecutor('onDataPacketSend'),$plugin,false);
			$manager->registerEvent('pocketmine\\event\\server\\DataPacketReceiveEvent',$this,EventPriority::LOWEST,new MethodEventExecutor('onDataPacketReceive'),$plugin,false);
			$manager->registerEvent('pocketmine\\event\\player\\PlayerJoinEvent',$this,EventPriority::HIGHEST,new MethodEventExecutor('onPlayerJoin'),$plugin,true);
			$manager->registerEvent('pocketmine\\event\\player\\PlayerQuitEvent',$this,EventPriority::HIGHEST,new MethodEventExecutor('onPlayerQuit'),$plugin,true);
			$manager->registerEvent('pocketmine\\event\\entity\\EntityTeleportEvent',$this,EventPriority::HIGHEST,new MethodEventExecutor('onEntityTeleport'),$plugin,true);
			$manager->registerEvent('pocketmine\\event\\player\\PlayerDropItemEvent',$this,EventPriority::HIGHEST,new MethodEventExecutor('onPlayerDropItem'),$plugin,true);
			$manager->registerEvent('pocketmine\\event\\inventory\\InventoryOpenEvent',$this,EventPriority::HIGHEST,new MethodEventExecutor('onInventoryOpen'),$plugin,true);
			$manager->registerEvent('pocketmine\\event\\player\\PlayerItemConsumeEvent',$this,EventPriority::HIGHEST,new MethodEventExecutor('onPlayerItemConsume'),$plugin,true);
		}catch(\Throwable $exception){return false;}
		return true;
	}
	public function onDataPacketSend($event){
		self::$plugin->onDataPacketSend($event);
		return;
	}
	public function onDataPacketReceive($event){
		self::$plugin->onDataPacketReceive($event);
		return;
	}
	public function onPlayerCreation($event){
		self::$plugin->onPlayerCreation($event);
		return;
	}
	public function onPlayerPreLogin($event){
		self::$plugin->onPlayerPreLogin($event);
		return;
	}
	public function onPlayerJoin($event){
		self::$plugin->onPlayerJoin($event);
		return;
	}
	public function onPlayerQuit($event){
		self::$plugin->onPlayerQuit($event);
		return;
	}
	public function onPlayerInteract($event){
		self::$plugin->BlockEvent($event);
		return;
	}
	public function onBlockPlace($event){
		self::$plugin->BlockEvent($event);
		return;
	}
	public function onBlockBreak($event){
		self::$plugin->BlockEvent($event);
		return;
	}
	public function onInventoryOpen($event){
		self::$plugin->onInventoryOpen($event);
		return;
	}
	public function onPlayerItemConsume($event){
		self::$plugin->ItemEvent($event);
		return;
	}
	public function onPlayerDropItem($event){
		self::$plugin->ItemEvent($event);
		return;
	}
	public function onEntityDamage($event){
		self::$plugin->onEntityDamage($event);
		return;
	}
	public function onEntityTeleport($event){
		self::$plugin->onEntityTeleport($event);
		return;
	}
}
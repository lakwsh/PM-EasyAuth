<?php
namespace lakwsh\EasyAuth\event;

class PlayerForcedKickEvent extends \pocketmine\event\player\PlayerEvent{
	private static $reason;
	public static $handlerList=null;
	public function __construct(\pocketmine\Player $player,String $reason){
		$this->player=$player;
		self::$reason=$reason;
	}
	public function getReason(){
		return self::$reason;
	}
}
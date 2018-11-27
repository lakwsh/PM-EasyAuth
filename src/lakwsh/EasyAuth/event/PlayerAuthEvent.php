<?php
namespace lakwsh\EasyAuth\event;

class PlayerAuthEvent extends \pocketmine\event\player\PlayerEvent{
	private static $type=null;
	public static $handlerList=null;
	public function __construct(\pocketmine\Player $player,String $type){
		$this->player=$player;
		self::$type=$type;
	}
	public function getType(){
		return self::$type;
	}
}
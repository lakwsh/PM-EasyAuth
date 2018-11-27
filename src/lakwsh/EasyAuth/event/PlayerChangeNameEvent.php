<?php
namespace lakwsh\EasyAuth\event;

class PlayerChangeNameEvent extends \pocketmine\event\player\PlayerEvent{
	private static $after=null;
	private static $before=null;
	public static $handlerList=null;
	public function __construct(\pocketmine\Player $player,String $before,String $after){
		$this->player=$player;
		self::$after=$after;
		self::$before=$before;
	}
	public function getRealName(){
		return self::$before;
	}
	public function getNewName(){
		return self::$after;
	}
}
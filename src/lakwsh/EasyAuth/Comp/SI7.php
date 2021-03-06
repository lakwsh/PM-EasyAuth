<?php
namespace lakwsh\EasyAuth;
use pocketmine\Player;

abstract class SI implements \pocketmine\network\NetworkInterface{
	abstract protected function preTicK();
	abstract protected function emergency();
	final public function start():void{
		return;
	}
	final public function putPacket(Player $player,\pocketmine\network\mcpe\protocol\DataPacket $packet,bool $needACK=false,bool $immediate=true):?int{
		return null;
	}
	final public function close(Player $player,string $reason='unknown reason'):void{
		return;
	}
	final public function setName(string $name):void{
		return;
	}
	final public function process():void{
		$this->preTick();
		return;
	}
	final public function shutdown():void{
		return;
	}
	final public function emergencyShutdown():void{
		$this->emergency();
		return;
	}
}
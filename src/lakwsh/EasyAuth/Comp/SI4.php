<?php
namespace lakwsh\EasyAuth;
use pocketmine\Player;

abstract class SI implements \pocketmine\network\SourceInterface{
	abstract protected function preTicK();
	abstract protected function emergency();
	final public function start(){
		return;
	}
	final public function putPacket(Player $player,\pocketmine\network\mcpe\protocol\DataPacket $packet,bool $needACK=false,bool $immediate=true){
		return;
	}
	final public function close(Player $player,string $reason='unknown reason'){
		return;
	}
	final public function setName(string $name){
		return;
	}
	final public function process():void{
		$this->preTick();
		return;
	}
	final public function shutdown(){
		return;
	}
	final public function emergencyShutdown(){
		$this->emergency();
		return;
	}
}
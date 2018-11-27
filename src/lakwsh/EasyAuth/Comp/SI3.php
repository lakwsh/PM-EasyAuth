<?php
namespace lakwsh\EasyAuth;
use pocketmine\Player;

abstract class SI implements \pocketmine\network\SourceInterface{
	abstract protected function preTicK();
	abstract protected function emergency();
	final public function putPacket(Player $player,$packet,$needACK=false,$immediate=true){
		return;
	}
	final public function close(Player $player,$reason='unknown reason'){
		return;
	}
	final public function setName($name){
		return;
	}
	final public function process(){
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
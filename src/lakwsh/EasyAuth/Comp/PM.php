<?php
namespace lakwsh\EasyAuth;
use pocketmine\command\CommandSender;

abstract class _Command extends \pocketmine\command\PluginCommand{
	final public function execute(CommandSender $sender,string $label,array $args):bool{
		return $this->_execute($sender,$label,$args);
	}
	abstract protected function _execute(CommandSender $sender,string $label,array $args):bool;
}
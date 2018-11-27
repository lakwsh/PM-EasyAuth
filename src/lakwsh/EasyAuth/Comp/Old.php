<?php
namespace lakwsh\EasyAuth;

class Create{
	final public static function getVersion(){
		return \pocketmine\network\protocol\Info::CURRENT_PROTOCOL;
	}
	final public static function DisconnectPacket(){
		return new \pocketmine\network\protocol\DisconnectPacket;
	}
	final public static function TextPacket(){
		return new \pocketmine\network\protocol\TextPacket;
	}
	final public static function FormPacket(){
		return null;
	}
	final public static function supportUI():bool{
		return false;
	}
}
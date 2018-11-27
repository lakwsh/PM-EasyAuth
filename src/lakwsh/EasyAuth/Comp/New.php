<?php
namespace lakwsh\EasyAuth;

class Create{
	final public static function getVersion(){
		return \pocketmine\network\mcpe\protocol\ProtocolInfo::CURRENT_PROTOCOL;
	}
	final public static function DisconnectPacket(){
		return new \pocketmine\network\mcpe\protocol\DisconnectPacket;
	}
	final public static function TextPacket(){
		return new \pocketmine\network\mcpe\protocol\TextPacket;
	}
	final public static function FormPacket(){
		return new \pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
	}
	final public static function supportUI():bool{
		$send=\class_exists('\pocketmine\network\mcpe\protocol\ModalFormRequestPacket',false);
		$receive=\class_exists('\pocketmine\network\mcpe\protocol\ModalFormResponsePacket',false);
		return $send&&$receive;
	}
}
<?php
declare(strict_types=1);

namespace DavyCraft648\Crawling;

use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\types\PlayerAuthInputFlags;

class Main extends \pocketmine\plugin\PluginBase{

	/** @var array<string, int> */
	private array $lastPlayerAuthInputFlags = [];
	/** @var array<string, bool> */
	private array $crawlingPlayers = [];

	protected function onEnable() : void{
		$this->getServer()->getPluginManager()->registerEvent(DataPacketReceiveEvent::class, function(DataPacketReceiveEvent $event) : void{
			$packet = $event->getPacket();
			if($packet instanceof PlayerAuthInputPacket){
				$player = $event->getOrigin()->getPlayer();
				if($player !== null){
					$playerName = $player->getName();
					$inputFlags = $packet->getInputFlags();
					if(!isset($this->lastPlayerAuthInputFlags[$playerName]) || $inputFlags !== $this->lastPlayerAuthInputFlags[$playerName]){
						$this->lastPlayerAuthInputFlags[$playerName] = $inputFlags;
						if((($inputFlags & (1 << 40)) !== 0) !== (($inputFlags & (1 << 41)) !== 0)){
							(new \ReflectionProperty($packet, "inputFlags"))->setValue($packet, ($this->crawlingPlayers[$playerName] = ($inputFlags & (1 << 40)) !== 0) ? $inputFlags | (1 << PlayerAuthInputFlags::START_SWIMMING) : $inputFlags | (1 << PlayerAuthInputFlags::STOP_SWIMMING));
						}elseif(($this->crawlingPlayers[$playerName] ?? false) && ($inputFlags & (1 << PlayerAuthInputFlags::START_SWIMMING)) === 0){
							(new \ReflectionProperty($packet, "inputFlags"))->setValue($packet, $inputFlags | (1 << PlayerAuthInputFlags::START_SWIMMING));
						}
					}
				}
			}
		}, EventPriority::NORMAL, $this);
		$this->getServer()->getPluginManager()->registerEvent(PlayerQuitEvent::class, function(PlayerQuitEvent $event) : void{
			unset($this->lastPlayerAuthInputFlags[$name = $event->getPlayer()->getName()], $this->crawlingPlayers[$name]);
		}, EventPriority::NORMAL, $this);
	}
}

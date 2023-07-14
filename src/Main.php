<?php
declare(strict_types=1);

namespace DavyCraft648\Crawling;

use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\types\Experiments;
use pocketmine\network\mcpe\protocol\types\PlayerAuthInputFlags as Flags;

class Main extends \pocketmine\plugin\PluginBase{

	/** @var array<string, int> */
	private array $lastPlayerAuthInputFlags = [];
	/** @var array<string, bool> */
	private array $crawlingPlayers = [];

	protected function onEnable() : void{
		$this->getServer()->getPluginManager()->registerEvent(DataPacketSendEvent::class, function(DataPacketSendEvent $event) : void{
			foreach($event->getPackets() as $packet){
				if($packet instanceof StartGamePacket){
					$packet->levelSettings->experiments = new Experiments(array_merge($packet->levelSettings->experiments->getExperiments(), [
						"short_sneaking" => true
					]), true);
				}
			}
		}, EventPriority::HIGHEST, $this);
		$this->getServer()->getPluginManager()->registerEvent(DataPacketReceiveEvent::class, function(DataPacketReceiveEvent $event) : void{
			$packet = $event->getPacket();
			if($packet instanceof PlayerAuthInputPacket){
				$player = $event->getOrigin()->getPlayer();
				if($player !== null){
					$playerName = $player->getName();
					$inputFlags = $packet->getInputFlags();
					if(!isset($this->lastPlayerAuthInputFlags[$playerName]) || $inputFlags !== $this->lastPlayerAuthInputFlags[$playerName]){
						$this->lastPlayerAuthInputFlags[$playerName] = $inputFlags;
						$isStartCrawling = ($inputFlags & (1 << Flags::START_CRAWLING)) !== 0;
						$isStopCrawling = ($inputFlags & (1 << Flags::STOP_CRAWLING)) !== 0;
						if($isStartCrawling !== $isStopCrawling){
							(new \ReflectionProperty($packet, "inputFlags"))->setValue($packet, ($this->crawlingPlayers[$playerName] = $isStartCrawling) ? $inputFlags | (1 << Flags::START_SWIMMING) : $inputFlags | (1 << Flags::STOP_SWIMMING));
						}elseif(($this->crawlingPlayers[$playerName] ?? false) && ($inputFlags & (1 << Flags::START_SWIMMING)) === 0){
							if($player->isSpectator() || ($player->getEyeHeight() > 1 && !$player->getPosition()->getWorld()->getBlock($player->getEyePos())->collidesWithBB($player->getBoundingBox()))){
								unset($this->crawlingPlayers[$playerName]);
								return;
							}
							(new \ReflectionProperty($packet, "inputFlags"))->setValue($packet, $inputFlags | (1 << Flags::START_SWIMMING));
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

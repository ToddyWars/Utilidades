<?php

namespace UtilityPlugin;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\player\Player;
use ref\libNpcDialogue\NpcDialogue;

class UtilityListener implements Listener{

    public function onJoin(PlayerJoinEvent $event): void
    {
        $this->show($event->getPlayer());
    }

    public function show(Player $player): void
    {
        $menu = new NpcDialogue();
        $menu->setNpcName("First Menu");
        $menu->setDialogueBody("Debug");
        $menu->setSceneName("First Menu");
        $menu->sendTo($player, $player);
    }
}
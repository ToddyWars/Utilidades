<?php

namespace UtilityPlugin\comandos;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\permission\DefaultPermissionNames;

class help_cmd  extends Command
{
    public function __construct(){
        parent::__construct(
            "help",
            "Dar item ao jogador",
            "/give <player> <item[:damage]> [amount] [tags...]",
            ["?"]
        );
        $this->setPermission(DefaultPermissionNames::COMMAND_HELP);
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args):void
    {
        $sender->sendMessage("otaro");
    }
}
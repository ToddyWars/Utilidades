<?php

declare(strict_types = 1);

namespace jojoe77777\FormAPI;

use pocketmine\form\Form as IForm;
use pocketmine\player\Player;
use ReturnTypeWillChange;

abstract class Form implements IForm{

    /** @var array */
    protected array $data = [];
    /** @var callable */
    private $callable;

    /**
     * @param callable|null $callable $callable
     */
    public function __construct(?callable $callable) {
        $this->callable = $callable;
    }

    /**
     * @deprecated
     * @see Player::sendForm()
     *
     * @param Player $player
     */
    public function sendToPlayer(Player $player) : void {
        $player->sendForm($this);
    }

    public function getCallable() : ?callable {
        return $this->callable;
    }

    public function setCallable(?callable $callable): void
    {
        $this->callable = $callable;
    }

    public function handleResponse(Player $player, $data) : void {
        $this->processData($data);
        $callable = $this->getCallable();
        if($callable !== null) {
            $callable($player, $data);
        }
    }

    public function processData(&$data) : void {
    }

    #[ReturnTypeWillChange] public function jsonSerialize(): array
    {
        return $this->data;
    }
}

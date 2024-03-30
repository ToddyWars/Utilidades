<?php

use pocketmine\network\mcpe\protocol\{RemoveObjectivePacket,
    SetDisplayObjectivePacket,
    SetScorePacket,
    types\ScorePacketEntry};
use pocketmine\player\Player;

class Scoreboard
{
	/** @static instance */
	private static Scoreboard $instance;

	/** @var array */
	public array $scoreboards;

	public static function getInstance(): Scoreboard
    {
		if(!isset(self::$instance))
		{
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * @param Player $player
	 * @param string $objectiveName
	 * @param string $displayName
	 */
	public function new(Player $player, string $objectiveName, string $displayName): void{
        if (!$player->isConnected()) return;
		if(isset($this->scoreboards[$player->getName()])){
			$this->remove($player);
		}
		$pk = new SetDisplayObjectivePacket();
		$pk->displaySlot = "sidebar";
		$pk->objectiveName = $objectiveName;
		$pk->displayName = $displayName;
		$pk->criteriaName = "dummy";
		$pk->sortOrder = 0;
		$player->getNetworkSession()->sendDataPacket($pk);
		$this->scoreboards[$player->getName()] = $objectiveName;
	}

	/**
	 * @param Player $player
	 */
	public function remove(Player $player): void{
        if (!$player->isConnected()) return;
		$objectiveName = $this->getObjectiveName($player);
		$pk = new RemoveObjectivePacket();
		$pk->objectiveName = $objectiveName;
		$player->getNetworkSession()->sendDataPacket($pk);
		unset($this->scoreboards[$player->getName()]);
	}

    /**
     * @param Player $player
     * @param int $score
     * @param string $message
     * @throws Exception
     */
	public function setLine(Player $player, int $score, string $message): void{
        if (!$player->isConnected()) return;
		if(!isset($this->scoreboards[$player->getName()])){
            throw new Exception("Cannot set a score to a player with no scoreboard");
		}
		if($score > 15 || $score < 0){
            throw new Exception("Score must be between the value of 1-15. $score out of range");
		}
		$objectiveName = $this->getObjectiveName($player);
		$entry = new ScorePacketEntry();
		$entry->objectiveName = $objectiveName;
		$entry->type = $entry::TYPE_FAKE_PLAYER;
		$entry->customName = $message;
		$entry->score = $score;
		$entry->scoreboardId = $score;
		$pk = new SetScorePacket();
		$pk->type = $pk::TYPE_CHANGE;
		$pk->entries[] = $entry;
		$player->getNetworkSession()->sendDataPacket($pk);
	}

    /**
     * @param Player $player
     * @return string|null
     */
	public function getObjectiveName(Player $player): ?string{
		return $this->scoreboards[$player->getName()] ?? null;
	}

	/**
	 * @param Player $player
	 */
	public function checkAndRemoveScoreboard(Player $player): void
	{
        if (!$player->isConnected()) return;
		if($this->getObjectiveName($player)) $this->remove($player);
	}
}
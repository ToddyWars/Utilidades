<?php

declare(strict_types=1);

namespace ref\libNpcDialogue;

use pocketmine\entity\Entity;
use pocketmine\entity\Human;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\NpcDialoguePacket;
use pocketmine\network\mcpe\protocol\RemoveActorPacket;
use pocketmine\network\mcpe\protocol\types\entity\ByteMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\IntMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\PropertySyncData;
use pocketmine\network\mcpe\protocol\types\entity\StringMetadataProperty;
use pocketmine\player\Player;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\Utils;
use Pokemon\data\PokeData;
use ref\libNpcDialogue\event\DialogueNameChangeEvent;
use ref\libNpcDialogue\form\NpcDialogueButtonData;
use Serializable;
use function array_key_exists;
use function array_map;
use function json_encode;
use function trim;

final class NpcDialogue implements Serializable {
    private mixed $closure;

    public function __construct()
    {
        $this->closure = $this;
    }

    protected ?int $actorId = null;

	protected bool $fakeActor = true;

	/** @var NpcDialogueButtonData[] */
	protected array $buttonData = [];

	/**
	 * sceneName is used for identifying the dialogue.
	 * Without this, We cannot handle NpcRequestPacket properly.
	 */
	protected string $sceneName = "";

	/**
	 * npcName is used for a title of dialogue
	 */
	protected string $npcName = "";

	/**
	 * dialogueBody is used for body of dialogue
	 */
	protected string $dialogueBody = "";

	/**
	 * pickerOffset is used to set the offset of dialogue picker
	 * This only need when you want to spawn Human NPC. (the NPC goes off when you open dialogue)
	 * @author Tobias Grether ({@link https://github.com/TobiasGrether})
	 */
	private int $pickerOffset = -50;

    public function setSceneName(string $sceneName) : void{
		if(trim($sceneName) === ""){
			throw new \InvalidArgumentException("Scene name cannot be empty");
		}
		$this->sceneName = $sceneName;
	}

	public function setNpcName(string $npcName) : void{
		$this->npcName = $npcName;
	}

	public function setDialogueBody(string $dialogueBody) : void{
		$this->dialogueBody = $dialogueBody;
	}

	public function sendTo(Player $player, null|PokeData|Entity $entity = null) : void{
		if(trim($this->sceneName) === ""){
			throw new \InvalidArgumentException("Scene name cannot be empty");
		}
		$mappedActions = Utils::assumeNotFalse(json_encode(array_map(static fn(NpcDialogueButtonData $data) => $data->jsonSerialize(), $this->buttonData)));
		$skinIndex = [
			"picker_offsets" => [
				"scale" => [0, 0, 0],
				"translate" => [0, 0, 0],
			],
			"portrait_offsets" => [
				"scale" => [1, 1, 1],
				"translate" => [0, $this->pickerOffset, 0]
			]
            /*,
            "skin_list" => [
                [
                    "variant" => 0
                ],
                [
                    "variant" => 1
                ]
                ,
                [
                    "variant" => 2
                ]
            ]*/
		];
        if($entity instanceof Entity) {
            $this->actorId = $entity->getId();
            $this->fakeActor = false;
            $propertyManager = $entity->getNetworkProperties();
            $propertyManager->setByte(EntityMetadataProperties::HAS_NPC_COMPONENT, 1);
            $propertyManager->setString(EntityMetadataProperties::NPC_ACTIONS, $mappedActions);
            if ($entity instanceof Human) {
                // This is a workaround for Human NPC
                $propertyManager->setString(EntityMetadataProperties::NPC_SKIN_INDEX, Utils::assumeNotFalse(json_encode($skinIndex)));
            }
        }else{
            $this->actorId = Entity::nextRuntimeId();
            $identifier = EntityIds::NPC;
            $variant = 0;
            if($entity instanceof PokeData){
                $identifier = "pokemon:".strtolower($entity->getName());
                $variant = $entity->isShiny();
            }
            $player->getNetworkSession()->sendDataPacket(
                AddActorPacket::create(
                    $this->actorId,
                    $this->actorId,
                    $identifier,
                    $player->getPosition()->add(0, 10, 0),
                    null,
                    $player->getLocation()->getPitch(),
                    $player->getLocation()->getYaw(),
                    $player->getLocation()->getYaw(),
                    $player->getLocation()->getYaw(),
                    [],
                    [
                        EntityMetadataProperties::HAS_NPC_COMPONENT => new ByteMetadataProperty(1),
                        EntityMetadataProperties::NPC_ACTIONS => new StringMetadataProperty($mappedActions),
                        EntityMetadataProperties::SKIN_ID => new IntMetadataProperty($variant), // Variant affects NPC skin
                    ],
                    new PropertySyncData([], []),
                    []
                )
            );
        }
		$pk = NpcDialoguePacket::create(
			$this->actorId,
			NpcDialoguePacket::ACTION_OPEN,
			$this->dialogueBody,
			$this->sceneName,
			$this->npcName,
			$mappedActions
		);
		$player->getNetworkSession()->sendDataPacket($pk);

		DialogueStore::$dialogueQueue[$player->getName()][$this->sceneName] = $this;
	}

	/** @phpstan-param list<NpcDialogueButtonData> $buttons */
	public function onButtonsChanged(array $buttons) : void{
		// TODO
	}

	public function onClose(Player $player) : void{
		$mappedActions = Utils::assumeNotFalse(json_encode(array_map(static fn(NpcDialogueButtonData $data) => $data->jsonSerialize(), $this->buttonData)));
		$player->getNetworkSession()->sendDataPacket(
			NpcDialoguePacket::create(
				$this->actorId ?? throw new AssumptionFailedError("This method should not be called when actorId is null"),
				NpcDialoguePacket::ACTION_CLOSE,
				$this->dialogueBody,
				$this->sceneName,
				$this->npcName,
				$mappedActions
			)
		);
	}

	public function onButtonClicked(Player $player, int $buttonId) : void{
		if(!array_key_exists($buttonId, $this->buttonData)){
			throw new \InvalidArgumentException("Button ID $buttonId does not exist");
		}
		$button = $this->buttonData[$buttonId];

		if($button->getForceCloseOnClick()){
			$this->onClose($player);
		}

		$handler = $button->getClickHandler();
		if($handler !== null){
			$handler($player);
		}
	}

	public function onSetNameRequested(string $newName) : void{
		$ev = new DialogueNameChangeEvent($this, $this->npcName, $newName);
		$ev->call();
		if($ev->isCancelled()){
			return;
		}
		$this->npcName = $ev->getNewName();
	}

	public function addButton(NpcDialogueButtonData $buttonData) : void{
		$this->buttonData[] = $buttonData;
	}

	public function onDispose(Player $player) : void{
		if($this->actorId !== null && $this->fakeActor){
			$player->getNetworkSession()->sendDataPacket(RemoveActorPacket::create($this->actorId));
			$this->actorId = null;
		}
	}

	public function setPickerOffset(int $offset) : void{
		$this->pickerOffset = $offset;
	}


    public function serialize(): ?string
    {
        return serialize($this);
    }

    public function unserialize($data): void
    {
        $this->closure = unserialize($data);
    }

    public function __serialize(): array {
        return ['closure' => $this->closure];
    }

    public function __unserialize(array $data): void {
        $this->closure = $data['closure'];
    }

    /**
     * @param mixed $closure
     */
    public function setClosure(mixed $closure): void
    {
        $this->closure = $closure;
    }

    public function getClosure(): mixed
    {
        return $this->closure;
    }
}

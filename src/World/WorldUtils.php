<?php

namespace World;

use ErrorException;
use FilesystemIterator;
use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\Server;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\world\format\io\data\BaseNbtWorldData;
use pocketmine\world\World;
use pocketmine\world\WorldException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

//MultiWorld

class WorldUtils
{
    static public function getWorldOrNull(string $name): ?World{
        $server = Server::getInstance();
        $manager = $server->getWorldManager();
        if(!$manager->isWorldGenerated($name)) {
            $server->getLogger()->warning("§4O mundo §c$name §4não existe.");
            return null;
        }
        if(!$manager->isWorldLoaded($name)) $manager->loadWorld($name, true);

        return $manager->getWorldByName($name);
    }
    static public function safeTeleport(Location $local, Entity $entity): void
    {
        $mundo = $local->getWorld();
        if(!$mundo->isLoaded()) return;
        if(!$mundo->isChunkLoaded($local->getFloorX(), $local->getFloorZ()))
            $mundo->loadChunk($local->getFloorX(), $local->getFloorZ());

//        $y = $mundo->getHighestBlockAt($local->getFloorX(), $local->getFloorZ());

        $entity->teleport($local);
    }

    public static function removeWorld(string $name): int {
        $manager = Server::getInstance()->getWorldManager();
        if($manager->isWorldLoaded($name)) {
            $world = self::getWorldByNameNonNull($name);
            if(count($world->getPlayers()) > 0) {
                foreach($world->getPlayers() as $player) {
                    $player->teleport(WorldUtils::getDefaultWorldNonNull()->getSpawnLocation());
                }
            }
            Server::getInstance()->getWorldManager()->unloadWorld($world, true);
        }

        $removedFiles = 1;

        if(!$manager->isWorldGenerated($name)) return 0;

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($worldPath = Server::getInstance()->getDataPath() . "/worlds/$name", FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        /** @var SplFileInfo $fileInfo */
        foreach($files as $fileInfo) {
            if($filePath = $fileInfo->getRealPath()) {
                if($fileInfo->isFile()) {
                    unlink($filePath);
                } else {
                    rmdir($filePath);
                }

                ++$removedFiles;
            }
        }

        rmdir($worldPath);
        return $removedFiles;
    }

    /**
     * WARNING: This method should be used only in the case, when it is assured,
     * that the world is generated and loaded.
     */
    public static function getWorldByNameNonNull(string $name): World {
        $world = Server::getInstance()->getWorldManager()->getWorldByName($name);
        if($world === null) {
            throw new AssumptionFailedError("Required world \"$name\" is null");
        }

        return $world;
    }

    public static function getDefaultWorldNonNull(): World {
        $world = Server::getInstance()->getWorldManager()->getDefaultWorld();
        if($world === null) {
            throw new AssumptionFailedError("Default world is null");
        }

        return $world;
    }

    public static function renameWorld(string $oldName, string $newName): void {
        WorldUtils::lazyUnloadWorld($oldName, true);

        $from = Server::getInstance()->getDataPath() . "/worlds/" . $oldName;
        $to = Server::getInstance()->getDataPath() . "/worlds/" . $newName;

        try {
            rename($from, $to);
        } catch(ErrorException $e) {
            throw new RuntimeException("Unable to rename world \"$oldName\" to \"$newName\": {$e->getMessage()}");
        }

        WorldUtils::lazyLoadWorld($newName);
        $newWorld = Server::getInstance()->getWorldManager()->getWorldByName($newName);
        if(!$newWorld instanceof World) {
            return;
        }

        $worldData = $newWorld->getProvider()->getWorldData();
        if(!$worldData instanceof BaseNbtWorldData) {
            return;
        }

        $worldData->getCompoundTag()->setString("LevelName", $newName);

        Server::getInstance()->getWorldManager()->unloadWorld($newWorld); // reloading the world
        WorldUtils::lazyLoadWorld($newName);
    }

    public static function duplicateWorld(string $worldName, string $duplicateName): void {
        if(!Server::getInstance()->getWorldManager()->isWorldGenerated($worldName)) {
            throw new AssumptionFailedError("World \"$worldName\" is not generated.");
        }
        if(Server::getInstance()->getWorldManager()->isWorldLoaded($worldName)) {
            WorldUtils::getWorldByNameNonNull($worldName)->save();
        }

        mkdir(Server::getInstance()->getDataPath() . "/worlds/$duplicateName");

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(Server::getInstance()->getDataPath() . "worlds/$worldName", FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        /** @var SplFileInfo $fileInfo */
        foreach($files as $fileInfo) {
            if($filePath = $fileInfo->getRealPath()) {
                if($fileInfo->isFile()) {
                    @copy($filePath, str_replace($worldName, $duplicateName, $filePath));
                } else {
                    mkdir(str_replace($worldName, $duplicateName, $filePath));
                }
            }
        }
    }

    /**
     * @return bool Returns if the world was unloaded with the function.
     * If it has already been unloaded before calling this function, returns FALSE!
     */
    public static function lazyUnloadWorld(string $name, bool $force = false): bool {
        if(($world = Server::getInstance()->getWorldManager()->getWorldByName($name)) !== null) {
            return Server::getInstance()->getWorldManager()->unloadWorld($world, $force);
        }
        return false;
    }

    /**
     * @return bool Returns whether the world is loaded.
     *
     * @throws WorldException If the specified unloaded world could not be loaded.
     */
    public static function lazyLoadWorld(string $name): bool {
        try {
            if(!Server::getInstance()->getWorldManager()->isWorldLoaded($name))
                return Server::getInstance()->getWorldManager()->loadWorld($name, true);
        } catch(ErrorException $e) {
            throw new WorldException("Unable to access world file: {$e->getMessage()}");
        }

        return true;
    }

    /**
     * @return string[] Returns all the levels on the server including
     * unloaded ones
     */
    public static function getAllWorlds(): array {
        $files = scandir(Server::getInstance()->getDataPath() . "/worlds/");
        if(!$files) {
            return [];
        }

        // This is not necessary in case only clean PocketMine without other plugins is used,
        // however, due to compatibility with plugins such as NativeDimensions it's required to keep this.
        $files = array_unique(array_merge(
            array_map(fn(World $world) => $world->getFolderName(), Server::getInstance()->getWorldManager()->getWorlds()),
            $files
        ));

        return array_values(array_filter($files, function(string $fileName): bool {
            return Server::getInstance()->getWorldManager()->isWorldGenerated($fileName) &&
                $fileName !== "." && $fileName !== ".."; // Server->isWorldGenerated detects '.' and '..' as world, TODO - make pull request
        }));
    }

    /**
     * @return World|null Loads and returns world, if it is generated.
     *
     * @throws WorldException If the specified unloaded world could not be loaded.
     */
    public static function getLoadedWorldByName(string $name): ?World {
        WorldUtils::lazyLoadWorld($name);

        return Server::getInstance()->getWorldManager()->getWorldByName($name);
    }
}
<?php

namespace MBlock\utils;

class Vector3 extends \pocketmine\math\Vector3 {

    /**
     * @return string
     */
    public function __toString() {
        return "$this->x,$this->y,$this->z";
    }

    public static function getStringFromVector(\pocketmine\math\Vector3 $vector3): string
    {
        return "{$vector3->getX()}, {$vector3->getY()}, {$vector3->getZ()}";
    }

    /**
     * @param string $string
     * @return Vector3
     */
    public static function fromString(string $string): Vector3
    {
        return new Vector3((int)explode(",", $string)[0], (int)explode(",", $string)[1], (int)explode(",", $string)[2]);
    }
}
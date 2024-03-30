<?php


use pocketmine\entity\Skin;

class skinU
{
    private static function findKeyBeforeRequiredKeys($array, $requiredKeys, $parentKey = null) {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $foundKey = self::findKeyBeforeRequiredKeys($value, $requiredKeys, $key);
                if ($foundKey !== null) {
                    return $foundKey;
                }
            } else {
                if (in_array($key, $requiredKeys)) {
                    return $parentKey;
                }
            }
        }
        return null;
    }
    private static function getGeometryIdFromJsonFile(string $jsonPath): ?string {
        $json = json_decode(file_get_contents($jsonPath), true);
        $requiredKeys = ['visible_bounds_width', 'texture_width', 'textureheight', 'visible_bounds_height', 'visible_bounds_offset'];
        $geometryId = null;
        if (isset($json["minecraft:geometry"])) {
            foreach ($json["minecraft:geometry"] as $ji) {
                if (isset($ji["description"]) and isset($ji["description"]["identifier"])) {
                    $geometryId = $ji["description"]["identifier"];
                }
            }
        }else $geometryId = self::findKeyBeforeRequiredKeys($json, $requiredKeys);
        return $geometryId;
    }

    private static function verifyImg($caminhoImagem): bool
    {
        $informacoesImagem = getimagesize($caminhoImagem);
        $largura = $informacoesImagem[0];
        $altura = $informacoesImagem[1];

        if (($largura == 64 && $altura == 32) ||
            ($largura == 64 && $altura == 64) ||
            ($largura == 128 && $altura == 128)) {
            return false; // Tamanho válido encontrado
        }
        return true; // Tamanho inválido encontrado
    }

    private static function getImgSize($caminhoSkin): array
    {
        $informacoesSkin = getimagesize($caminhoSkin);
        $largura = $informacoesSkin[0];
        $altura = $informacoesSkin[1];

        return array($largura, $altura);
    }

    /**
     * @throws JsonException
     */
    static function getSkinFromPath(string $path, string $gpath, ?string $guid = null ) : ?Skin
    {
        if(!is_file($path) or !is_file($gpath)) {
            return null;
        }
        if(self::verifyImg($path)){
            $tamanhoSkin = self::getImgSize($path);
            $largura = $tamanhoSkin[0];
            $altura = $tamanhoSkin[1];
            UtilityLoader::getInstance()->getLogger()->alert("§c$path §ePocketmine não aceita§c {$largura}x{$altura}x§e.");
            return null;
        }
        $gid = $guid ?? SkinU::getGeometryIdFromJsonFile($gpath);
        $size = getimagesize($path);

        $path = self::imgTricky($path, [$size[0], $size[1], 4]);
        $img = @imagecreatefrompng($path);
        $skinbytes = "";
        for ($y = 0; $y < $size[1]; $y++) {
            for ($x = 0; $x < $size[0]; $x++) {
                $colorat = @imagecolorat($img, $x, $y);
                $a = ((~($colorat >> 24)) << 1) & 0xff;
                $r = ($colorat >> 16) & 0xff;
                $g = ($colorat >> 8) & 0xff;
                $b = $colorat & 0xff;
                $skinbytes .= chr($r) . chr($g) . chr($b) . chr($a);
            }
        }
        @imagedestroy($img);
        unlink($path);
        return new Skin("Standard_Custom", $skinbytes, '', $gid, file_get_contents($gpath));
    }

    private static function imgTricky(string $path, array $size): string
    {
        $down = imagecreatefrompng($path);

        if ($size[0] * $size[1] * $size[2] == 65536) {
            $upper = self::resize_image($path, 128, 128);
        } else {
            $upper = self::resize_image($path, 64, 64);
        }
        $path = UtilityLoader::getInstance()->getDataFolder();
        //Remove black color out of the png
        imagecolortransparent($upper, imagecolorallocatealpha($upper, 0, 0, 0, 127));

        imagealphablending($down, true);
        imagesavealpha($down, true);
        imagecopymerge($down, $upper, 0, 0, 0, 0, $size[0], $size[1], 100);
        imagepng($down, $path.'temp.png');
        return $path.'temp.png';
    }

    private static function resize_image($file, $w, $h): GdImage|bool
    {
        list($width, $height) = getimagesize($file);
        $r = $width / $height;
        if ($width > $height) {
            $width = ceil($width - ($width * abs($r - $w / $h)));
        } else {
            $height = ceil($height - ($height * abs($r - $w / $h)));
        }
        $newwidth = $w;
        $newheight = $h;
        $src = imagecreatefrompng($file);
        $dst = imagecreatetruecolor($w, $h);
        imagecolortransparent($dst, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);

        return $dst;
    }
}
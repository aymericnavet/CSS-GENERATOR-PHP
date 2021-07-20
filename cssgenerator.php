<?php

class CSSGenerator
{
    private $_arguments;
    private $_tree; 
    private $_style_name;
    private $_sprite_name; 
    private $_position;
    private $_padding;

    public function __construct() {
        $shortopts  = ""; 
        $shortopts .= "f:";
        $shortopts .= "p:"; 
        $shortopts .= "i:"; 
        $shortopts .= "s:"; 
        $shortopts .= "r:";
        $shortopts .= "h";
        $getLongOpt = ['file:', 'recursive::', 'output-image:', 'output-style:','help','padding:'];
        $this->_arguments = getopt($shortopts, $getLongOpt);
        foreach ($this->_arguments as $arg => $value){ 
            if ($arg == "h" || $arg == "help") {
                $this->showHelp();
                exit();
            }
        }
        $this->_style_name = "style.css";
        $this->_sprite_name = "sprite.png";
        $this->_tree = [];
        $this->_position = [];
        $this->_padding = 0;
        if (!extension_loaded("gd")){
            die("L'extension GD est requise.".PHP_EOL);
        }
    }
    
    public function setPosition($position)
    {
        array_push($this->_position, $position);
    }

    public function make()
    {
        if (count($this->_arguments) > 0){
            if ((isset($this->_arguments["f"]) && $this->_arguments["f"] != false) || (isset($this->_arguments["file"]) && $this->_arguments["file"] != false)) {
                isset($this->_arguments['f']) ? $this->searchTree($this->_arguments["f"]) : null;
                isset($this->_arguments['file']) ? $this->searchTree($this->_arguments["file"]) : null;
            }

            if ((isset($this->_arguments["i"]) && $this->_arguments["i"] != false) || (isset($this->_arguments["output-image"]) && $this->_arguments["output-image"] != false)) {
                isset($this->_arguments['i']) ? $this->setSpriteName($this->_arguments['i']) : null;
                isset($this->_arguments['output-image']) ? $this->setSpriteName($this->_arguments['output-image']) : null;
                $this->getSprite();
            } else {
                $this->getSprite();
            }

            if ((isset($this->_arguments["s"]) && $this->_arguments["s"] != false) || (isset($this->_arguments["output-style"]) && $this->_arguments["output-style"] != false)) {
                isset($this->_arguments['s']) ? $this->setStyleName($this->_arguments['s']) : null;
                isset($this->_arguments['output-style']) ? $this->setStyleName($this->_arguments['output-style']) : null;
                $this->getStyle();
            } else {
                $this->getStyle();
            }
            if (isset($this->_arguments['p']) && $this->_arguments['p'] != false){
                $this->setPadding($this->_arguments['p']);
            }
            if (isset($this->_arguments['padding']) && $this->_arguments['padding'] != false){
                $this->setPadding($this->_arguments['padding']);
            }
        } else {
            die("L'argument '-f, --file' est requis. \nPour afficher le manuel d'utilisation entrer l'option '-h, --help'".PHP_EOL);
        }
    }

    
    private function setPadding($padding)
    {
        $this->_padding = $padding;
    }

    
    private function searchTree($path) {
        $path = realpath($path);
        if (is_dir($path) && $handle = opendir($path)){  
            while(false !== ($entry = readdir($handle))){ 
                if ($entry != "." && $entry != ".."){  
                    if (is_file(realpath($path.'/'.$entry))){
                        array_push($this->_tree, realpath($path.'/'.$entry)); 
                    } elseif(is_dir($path.'/'.$entry) && (isset($this->_arguments["r"]) || isset($this->_arguments["recursive"]))){ 
                        $this->searchTree(realpath($path.'/'.$entry)); 
                    } else {
                        return $this->_tree; 
                    }
                }
            }
        } elseif(is_file(realpath($path))){
            array_push($this->_tree, realpath($path));
        } else {
            die("Le fichier est corrompu"."\n");
        }
        return $this->_tree;
    }

    private function setSpriteName($name){
        $this->_sprite_name = (new SplFileInfo($name))->getExtension() != 'png' ? $name.'.png' : $name;
    }

    private function getSprite() {
        $position = 0;
        $img = $this->initImage($this->_sprite_name);
        foreach ($this->_tree as $file){
            if (pathinfo($file)['extension'] == 'png'){
                try {
                    $image = imagecreatefrompng($file);
                } catch (Exception $exception){
                    die("Une erreur est survenue: ".$exception->getMessage());
                }
                $this->setPosition($position);
                imagecopy($img,$image,0,$position,0,0,imagesx($image), imagesy($image));
                $position += (imagesy($image));
                imagepng($img,$this->_sprite_name);
            }
        }
    }

    private function initImage($name){
        $width = 0;
        $height = 0;
        foreach($this->_tree as $image){
            if (pathinfo($image)['extension'] == 'png'){
                try {
                    $image = imagecreatefrompng($image);
                    if (imagesx($image) > $width){
                        $width = imagesx($image);
                    }
                    $height += imagesy($image);
                } catch(Exception $e){
                    die("Erreur à l'initialisation de l'image: ".$e->getMessage());
                }
            }
        }
        $img = imagecreatetruecolor($width, $height);
        $background = imagecolorallocatealpha($img, 255, 255, 255, 127);
        imagefill($img, 0, 0, $background);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagepng($img,$name);
        return $img;
    }

    private function setStyleName($name){
        $this->_style_name = (new SplFileInfo($name))->getExtension() != '.css' ? $name.'.css' : $name;
    }

    private function getStyle() {
        $fp = fopen($this->_style_name, "w+");
        $image = imagecreatefrompng($this->_sprite_name);
        $style = ".sprite {".PHP_EOL;
        $style .= "\tbackground-image: url('$this->_sprite_name');".PHP_EOL;
        $style .= "\twidth: ".imagesx($image)."px;".PHP_EOL;
        $style .= "\theight: ".imagesy($image)."px;".PHP_EOL;
        $style .= "}".PHP_EOL;
        fwrite($fp, $style);
        fclose($fp);
        $this->updateStyle();
    }

    private function updateStyle()
    {
        $i = 0;
        $fp = fopen($this->_style_name, 'a+');
        foreach ($this->_tree as $img){
            if (pathinfo($img)['extension'] == 'png'){
                $img = imagecreatefrompng($img);
                $style = ".sprite-$i {".PHP_EOL;
                $style .= "\tbackground-position: 0 -{$this->_position[$i]}px;".PHP_EOL;
                $style .= "\twidth: ".imagesx($img)."px;".PHP_EOL;
                $style .= "\theight: ".imagesy($img)."px;".PHP_EOL;
                $style .= "\tpadding: {$this->_padding}px;".PHP_EOL;
                $style .= "}".PHP_EOL;
                fwrite($fp, $style);
                $i++;
            }
        }
        fclose($fp);
    }

    private function showHelp() {
        echo <<<EOF

   Concatenate all images inside a folder in one sprite and write a style sheet ready to use. 
   Mandatory arguments to long options are mandatory for short options too.
   
   -r, --recursive
        Look for images into the assets_folder passed as arguement and all of its subdirectories.
    
   -i, --output-image
        Name of the generated image. If blank, the default name is « sprite.png ».
   
   -s, --output-style
        Name of the generated stylesheet. If blank, the default name is « style.css ».

   -p, --padding
        Add padding between images of NUMBER pixels.
         
EOF;
echo PHP_EOL;
    }
}
$foo = new CSSGenerator();
$foo->make();
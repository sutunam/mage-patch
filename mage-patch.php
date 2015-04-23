<?php

if (version_compare(phpversion(), '5.3.0', '<')) {
    die('PHP version must be at least 5.3.0');
}

class PatchMage {
    
    protected $_patchData;
    
    public function __construct($jsonConfigUrl)
    {
        $this->_loadJsonData($jsonConfigUrl);
    }
    
    protected function _loadJsonData($url)
    {
        if (!$url) {
            $this->_patchData = json_decode(file_get_contents(__DIR__.'/config.json'), true);
            return;
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        $jsonData = curl_exec($ch);
        curl_close($ch);
        
        if (!$jsonData) {
            throw new Exception('Error downloading config file');
        }
        
        $this->_patchData = json_decode($jsonData, true);
    }
    
    
    protected function _getPatchFile ($patchVersions, $version) {
        foreach ($patchVersions as $patchVersion => $patchFile) {
            $patchVersion = explode('->', $patchVersion);
            if (count($patchVersion) == 1) {
                $patchVersion[1] = $patchVersion[0].'.99999';
            }
             
            if (count($patchVersion) != 2) {
                throw new Exception('wrong format');
            }
            
            if (!version_compare($patchVersion[0], $version, '<=')) {
                continue;
            } elseif (!version_compare($patchVersion[1], $version, '>=')) {
                continue;
            }
            return $patchFile;
            break;
        }
    }
    
    public function getMagentoVersion ($dir)
    {
        if (!file_exists($dir.'app/Mage.php')) {
            throw new Exception('Mage.php file not found');
        }
        
        //require($dir.'app/Mage.php');
        //$mageVersion = Mage::getVersion();
        $mageVersion = shell_exec('php -r \'require("'.$dir.'app/Mage.php"); echo Mage::getVersion();\'');
        return $mageVersion;
    }
    
    protected function _downloadPatch($dir, $patchFile)
    {
        $url = rtrim($this->_patchData['baseUrl'], '/').'/'.$patchFile;
        $patchFilename = $dir.$patchFile;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        
        
        $fp = fopen ($patchFilename, 'w+');
        curl_setopt($ch, CURLOPT_FILE, $fp);
        
        $curlRet = curl_exec($ch);
        fclose($fp);
        
        if (!$curlRet) {
            unlink($patchFilename);
            throw new Exception(curl_error($ch));
        }
        
        curl_close($ch);
        
        return $patchFilename;
    }
    
    protected function _applyPatch ($dir, $patchFile, $sudoUser)
    {
        $cwd = getcwd();
        if (!chdir($dir)) {
            throw new Exception('cannot change current working directory to '.$dir);
        }
        
        $cmd = '/bin/bash '.$dir.$patchFile;
        
        if ($sudoUser) {
            if ($sudoUser == '_') {
                $sudoUser = '\\#'.fileowner($dir); //uid of user
            }
            
            $cmd = 'sudo -u '.$sudoUser.' '.$cmd;
        }
        
        passthru($cmd, $ret);
        
        chdir($cwd);
        
        if ($ret) {
            throw new Exception('Error applying patch');
        }
    }
    
    function patch ($dir, $sudoUser = null)
    {
        $dir = rtrim($dir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        
        $mageVersion = $this->getMagentoVersion($dir);
        //$mageVersion = '1.5.1.0';
        
        echo 'Magento version: '.$mageVersion.PHP_EOL;
        
        $appliedPatches = array();
        
        foreach ($this->_patchData['patches'] as $patch => $patchVersions) {
            $patchFile = $this->_getPatchFile($patchVersions, $mageVersion);
            
            if (!$patchFile) {
                echo 'The patch '.$patch.' is not available for version '.$mageVersion.PHP_EOL;
                continue;
            }
            
            echo 'Patch file found: '.$patchFile.PHP_EOL;
            
            $this->_downloadPatch($dir, $patchFile);
            $this->_applyPatch($dir, $patchFile, $sudoUser);
            unlink($dir.$patchFile);
            
            $appliedPatches[] = $patch;
        }
        
        echo PHP_EOL.PHP_EOL.'The following patches have been applied :'.PHP_EOL.implode(PHP_EOL, $appliedPatches).PHP_EOL;
    }
    
    public function multiPatch (array $dirs, $sudoUser = null)
    {
        foreach ($dirs as $dir) {
            $this->patch($dir, $sudoUser);
        }
    }
    
    public function help ()
    {
        $f = basename(__FILE__);
        echo <<<OUTPUT
MagePatch : Upgrade Multiple Magento easily

Usage: php -f $f -- [options] dirs ...

dirs:
    Magento directory where the patches will be applied

options:
    --sudo USR
        Specify user USR who will execute the patch. If you use
	    the magic '_' value, the patch will be executed by the
	    owner of the Magento directory, using sudo command.
    --config URL
    	Specify URL of the config.json. Default is 
    	https://raw.githubusercontent.com/sutunam/mage-patch/master/config.json

OUTPUT;
        
    }    
}

$dirs = $argv;
unset($dirs[0]);

function extractParams($name, &$params) {
    $value = null;
    if (false !== $key = array_search($name, $params)) {
        $value = $params[$key+1];
        unset($params[$key]);
        unset($params[$key+1]);
    }
    return $value;
}

$sudo = extractParams('--sudo', $dirs);
$configUrl = extractParams('--config', $dirs);

if (!$configUrl) {
    $configUrl = 'https://raw.githubusercontent.com/sutunam/mage-patch/master/config.json';
}

$patch = new PatchMage($configUrl);

if (!count($dirs)) {
    $patch->help();
} else {
    $patch->multiPatch($dirs, $sudo);
}



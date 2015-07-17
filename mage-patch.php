<?php

if (version_compare(phpversion(), '5.3.0', '<')) {
    die('PHP version must be at least 5.3.0');
}

class PatchMage {
    
    protected $_patchData;
    protected $_suUser;
    protected $_sudoUser;
    protected $_allowedPatches;
    protected $_continueOnError;
    protected $_dryRun = false;
    protected $_quiet = false;
    
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
            $patchVersion = str_replace('x', '9999', $patchVersion);
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
    
    /**
     * 
     * @param string $dir Install folder
     * @throws Exception
     * @return array:string
     */
    public function getMagentoVersion ($dir)
    {
        if (!file_exists($dir.'app/Mage.php')) {
            throw new Exception('Mage.php file not found');
        }
        
        //require($dir.'app/Mage.php');
        //$mageVersion = Mage::getVersion();
        $mageVersion = shell_exec('php -r \'require("'.$dir.'app/Mage.php"); echo Mage::getVersion();\'');
        
        // todo : better detection Magento CE/EE ?
        $magentoEdition = 'CE';
        if (file_exists($dir.'app/code/core/Enterprise/Enterprise/etc/config.xml')) {
            $magentoEdition = 'EE';
        }
        return array($magentoEdition, $mageVersion);
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
    
    protected function _applyPatch ($dir, $patchFile)
    {
        $cwd = getcwd();
        
        $cmd = '/bin/bash '.$patchFile;
        
        if ($this->_suUser) {
            $user = $this->_suUser;
            if ($this->_suUser == '_') {
                $user = posix_getpwuid(fileowner($dir)); // username infos from uid
                $user = $user['name'];
                
                if (!$user) {
                    throw new Exception('Cannot get username of UID '.fileowner($dir));
                }
            }
            
            $cmd = 'su -c '.escapeshellarg($cmd).' '.$user;
        } elseif ($this->_sudoUser) {
            $user = $this->_sudoUser;
            if ($this->_sudoUser == '_') {
                $user = '\\#'.fileowner($dir); //uid of user
            }
            
            $cmd = 'sudo -u '.$user.' '.$cmd;
        }
        
        if (!chdir($dir)) {
            throw new Exception('cannot change current working directory to '.$dir);
        }
        
        if ($this->_quiet) {
            $cmd .= ' 2>&1 > /dev/null';
        }
        
        $ret = 0;
        if (!$this->_dryRun) {
            passthru($cmd, $ret);
        }
        
        chdir($cwd);
        
        if ($ret) {
            throw new Exception('Error applying patch');
        }
    }
    
    public function setSudoUser ($user)
    {
        $this->_sudoUser = $user;
        return $this;
    }
    
    public function setSuUser ($user)
    {
        $this->_suUser = $user;
        return $this;
    }
    
    public function setAllowedPatches ($patches)
    {
        if (!is_array($patches)) {
            $patches = explode(',', $patches);
        }
        
        $this->_allowedPatches = $patches;
        return $this;
    }
    
    public function setContinueOnError ($bool)
    {
        if (!$this->_isBoolParam($bool)) {
            throw new Exception('Wrong param for continueOnError option.');
        }
        
        $this->_continueOnError = !!$bool;
        return $this;
    }
    
    public function setDryRun ($bool)
    {
        if (!$this->_isBoolParam($bool)) {
            throw new Exception('Wrong param for dry-run option.');
        }
        
        $this->_dryRun = !!$bool;
        return $this;
    }
    
    public function setQuiet ($bool)
    {
        if (!$this->_isBoolParam($bool)) {
            throw new Exception('Wrong param for quiet option.');
        }
    
        $this->_quiet = !!$bool;
        return $this;
    }
    
    protected function _isBoolParam ($param)
    {
        return in_array($param, array(0, 1, 'true', 'false'));
    }
    
    /**
     * 
     * @param string $mageEdition should be CE or EE
     * @return array Patches availables for this edition.
     */
    protected function _getAvailablePathList ($mageEdition)
    {
        return $this->_patchData['patches-'.$mageEdition];
    }
    
    public function patch ($dir)
    {
        $dir = rtrim($dir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        
        echo $dir.':'.PHP_EOL;
        
        list($mageEdition, $mageVersion) = $this->getMagentoVersion($dir);
        //$mageVersion = '1.5.1.0';
        
        echo 'Magento version: '.$mageEdition.' '.$mageVersion.PHP_EOL;
        
        $appliedPatches = array();
        
        $patches = $this->_getAvailablePathList($mageEdition);
        
        if ($this->_allowedPatches) {
            $patches = array_intersect_key($patches, array_flip($this->_allowedPatches));
        }
        
        foreach ($patches as $patch => $patchVersions) {
            $patchFile = $this->_getPatchFile($patchVersions, $mageVersion);
            
            if (!$patchFile) {
                echo 'The patch '.$patch.' is not available for version '.$mageVersion.PHP_EOL;
                continue;
            }
            
            echo 'Apply patch file: '.$patchFile.PHP_EOL;
            
            $this->_downloadPatch($dir, $patchFile);
            
            try {
                $this->_applyPatch($dir, $patchFile);
                $appliedPatches[] = $patch;
            } catch (Exception $e) {
                if ($this->_continueOnError) {
                    echo PHP_EOL."Error applying the patch ".$patch.PHP_EOL;
                }
            }
            unlink($dir.$patchFile);
        }
        
        echo PHP_EOL.'The following patches have been applied :'.PHP_EOL.implode(PHP_EOL, $appliedPatches).PHP_EOL.PHP_EOL;
    }
    
    public function multiPatch (array $dirs)
    {
        foreach ($dirs as $dir) {
            $this->patch($dir);
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
    --sudo|--su USR
        Specify user USR who will execute the patch with the sudo or the su
        command. If you use the magic '_' value, the patch will be executed by
        the owner of the Magento directory, using sudo or su command.
    --config URL
    	Specify URL of the config.json. Default is 
    	https://raw.githubusercontent.com/sutunam/mage-patch/master/config.json
    --patches patch-name,...
        Restrict the list of the patch to be applied to one or more patch-name,
        separated by comma. The patch-names are listed in the config.json.
    --continueOnError 1|0 (default 0)
        Continue applying patch even if an error is returned by a patch.
    --dryRun (1|0) (default 0)
        Do not apply any patch. Only find Magento version and check that the
        patches can be downloaded (actualy it download them and remove them).
    --quiet (0|1) (default 0)
        Turn off stdin and stdout output of the patch script.
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

if (!$configUrl = extractParams('--config', $dirs)) {
    $configUrl = 'https://raw.githubusercontent.com/sutunam/mage-patch/master/config.json';
}

$patch = new PatchMage($configUrl);

if ($su = extractParams('--su', $dirs)) {
    $patch->setSuUser($su);
}

if ($sudo = extractParams('--sudo', $dirs)) {
    $patch->setSudoUser($sudo);
}

if ($patches = extractParams('--patches', $dirs)) {
    $patch->setAllowedPatches($patches);
}

if ($continueOnError = extractParams('--continueOnError', $dirs)) {
    $patch->setContinueOnError($continueOnError);
}

if ($dryRun = extractParams('--dryRun', $dirs)) {
    $patch->setDryRun($dryRun);
}

if ($quiet = extractParams('--quiet', $dirs)) {
    $patch->setQuiet($quiet);
}

if (!count($dirs)) {
    $patch->help();
} else {
    $patch->multiPatch($dirs);
}



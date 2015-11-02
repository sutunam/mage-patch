# Mage-Patch

Help to apply multiple **Magento patches** to multiple Magento installations.

*Note: The license of the Magento Enterprise patches does not allow us to publish them here. If you need to patch Magento Enterprise edition, [contact us](http://en.sutunam.com/contact/)*

## Usage

```
curl https://raw.githubusercontent.com/sutunam/mage-patch/master/mage-patch.php | php -- [options ...] dirs ...

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
    --keepDownloadedPatch 1|0 (default 0)
        Download the patch and do not delete it, the patch files stay in the specified directory.
    --quiet (0|1) (default 0)
        Turn off stdin and stdout output of the patch script.
```

## Notes

You don't need to care about an error: if a patch is not applicable, the Magento script do not apply it (this is featured by Magento). You can so allways run the script with the ```--continueOnError 1``` option.

## Examples



Apply all availables patches to the Magento installion in `./htdocs` :
```
curl https://raw.githubusercontent.com/sutunam/mage-patch/master/mage-patch.php | php -- ./htdocs
```

Apply all availables patches to the Magento installion in `./htdocs`, patch scripts stay quiet, and it does not stop on error :
```
curl https://raw.githubusercontent.com/sutunam/mage-patch/master/mage-patch.php | php -- --quiet 1 --continueOnError 1 ./htdocs
```

Same as before, but it applies only the patch SUPEE-6285 :
```
curl https://raw.githubusercontent.com/sutunam/mage-patch/master/mage-patch.php | php -- --quiet 1 --continueOnError 1 --patches SUPEE-6285 ./htdocs
```



Apply the SUPEE-5344 patch to all Magento installions in `/home/*/htdocs` by using the owners of the directories (**this should be run as root** and the `su` command must be available):
```
curl https://raw.githubusercontent.com/sutunam/mage-patch/master/mage-patch.php | php -- --patches SUPEE-5344 --su _ /home/*/htdocs
```


## License

Open Software License http://opensource.org/licenses/OSL-3.0

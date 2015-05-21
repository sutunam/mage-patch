# Mage-Patch

Help to apply multiple **Magento patches** to multiple Magento installations.

*Note: The licence of the Magento Enterprise patches do not allow us to publish them here. If you need to patch Magento Enterprise edition, [contact us](http://en.sutunam.com/contact/)*

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
    	Specify $url of the config.json. Default is 
    	https://raw.githubusercontent.com/sutunam/mage-patch/master/config.json
    --patches patch-name...
        Restrict the list of the patch to be applied to one or more patch-name,
        separated by comma. The patch-names are listed in the config.json.
    --continueOnError 1|0 (default 0)
        Continue applying patch even if an error is returned by a patch.
```

## Examples

Apply all availables patches to the Magento installion in `./htdocs` :
```
curl https://raw.githubusercontent.com/sutunam/mage-patch/master/mage-patch.php | php -- ./htdocs
```

Apply the SUPEE-5344 patch to all Magento installions in `/home/*/htdocs` by using the owners of the directories (this should be run as root and the `su` command must be available):
```
curl https://raw.githubusercontent.com/sutunam/mage-patch/master/mage-patch.php | php -- --patches SUPEE-5344 --su _ /home/*/htdocs
```


## License

Open Software License http://opensource.org/licenses/OSL-3.0
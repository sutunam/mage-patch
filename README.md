# Mage-Patch

Help to apply multiple Magento patches to multiple Magento installations.

## Usage

```
curl https://raw.githubusercontent.com/sutunam/mage-patch/master/mage-patch.php | php -- [options ...] dirs ...

dirs:
	Magento directory where the patches will be applied

options:
	--sudo USR
	    Specify user USR who will execute the patch. If you use
	    the magic '_' value, the patch will be executed by the
	    owner of the Magento directory, using sudo command.
    --config URL
    	Specify $url of the config.json. Default is 
    	https://raw.githubusercontent.com/sutunam/mage-patch/master/config.json
```

## License

Open Software License http://opensource.org/licenses/OSL-3.0
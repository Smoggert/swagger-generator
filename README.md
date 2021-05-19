# Swagger-generator

!! This is a Work in Progress !!

Swagger-generator is a package for Laravel that produces open-api 3.0.0 spec documentation based on Form Requests and Json Resources.
It is currently in pre-alpha or something among those lines..

## Installation

Add this hub to your repos on composer.json . Will figure out how to get it live once it's more developed.
You can place this in require-dev and just run the command during deploy/dev. No need to have this package on production.
```json
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/Smoggert/swagger-generator"
        }
    ],
    "require-dev": {
        
        "smoggert/swagger-generator": "@dev"
    }
```
## !! Prerequisits !!
The swagger generator looks through your code based on its controller function's return values and typed arguments. If you do no supply any it won't find any.
The package does not parse PhPDocs. The entire reason for this package is to avoid PhpDocs.

**A controller action should have typed requests, a typed resource as return value and shortname defined middleware.**
This package relies on this strict "linting" to get it's results. If you write horrible controllers that cannot be parsed I suggest using this package instead:

https://github.com/DarkaOnLine/L5-Swagger


## Configuration
The script currently works on an include route X basis.
If you want to specify which routes you'd like to include, (among other things), I highly recommend publishing the configuration file and adjusting it.

```bash
  php artisan vendor:publish --tag=config
```

## Usage
Available in json & yaml format, default to yaml.

```bash
  php artisan swagger:generate --format=json
```

## Contributing
Pull requests are currently not welcome. 
I'm mostly writing this so I can stop using 500 lines of phpdoc code to document our api.

## License
[MIT](https://choosealicense.com/licenses/mit/)

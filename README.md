# Swagger-generator

!! This is a Work in Progress !!

Swagger-generator is a package for Laravel that produces open-api 3.0.0 spec documentation based on Form Requests and Json Resources.
It is currently in pre-alpha or something among those lines..

## Installation

You can place this in require-dev and just run the command during deploy/dev. No need to have this package on production.
```json
    "require-dev": {
        
        "smoggert/swagger-generator": "^1.1.1"
    }
```
## !! Prerequisits !!
The swagger generator looks through your code based on its controller function's return values and typed arguments. If you do no supply any it won't find any.
The package does not parse PhPDocs. The entire reason for this package is to avoid PhpDocs.

**A controller action should have typed requests, a typed resource as return value..**
This package relies on this strict "linting" to get it's results. If you write horrible controllers that cannot be parsed I suggest using this package instead:

https://github.com/DarkaOnLine/L5-Swagger


## Configuration
The script currently works on an include route X basis.
If you want to specify which routes you'd like to include, (among other things), I highly recommend publishing the configuration file and adjusting it.

```bash
  php artisan vendor:publish --tag=config
```

## Usage
Available in json-format.

```bash
  php artisan swagger:generate
```

## Contributing
Pull requests are currently not welcome. 
I'm mostly writing this so I can stop using 500 lines of phpdoc code to document our api.

## License
[MIT](https://choosealicense.com/licenses/mit/)

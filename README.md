# Swagger-generator

Swagger-generator is a package that producer open-api 3.0.0 spec documentation based on Formrequests and JsonResources.
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
    "require-dev": [
        "smoggert/swagger-generator": "@dev"
    ]
```

## Usage
Available in json & yaml format.

```python
  php artisan swagger:generate --format=json
```

## Contributing
Pull requests are currently not welcome. 
I'm mostly writing this so I can stop using 500 lines of phpdoc code the document our api.

## License
[MIT](https://choosealicense.com/licenses/mit/)

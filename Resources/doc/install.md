Installation
============

Step 1: Download the Bundle
---------------------------

Open a command console, enter your project directory and execute the
following command to download the latest stable version of this bundle:

```console
$ composer require opensoft/storage-bundle "^1.0"
```

This command requires you to have Composer installed globally, as explained
in the [installation chapter](https://getcomposer.org/doc/00-intro.md)
of the Composer documentation.

Step 2: Enable the Bundle
-------------------------

Then, enable the bundle by adding it to the list of registered bundles
in the `app/AppKernel.php` file of your project:

```php
<?php
// app/AppKernel.php

// ...
class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = array(
            // ...

            new Opensoft\StorageBundle\OpensoftStorageBundle(),
        );

        // ...
    }

    // ...
}
```

Step 3: Provide Types
---------------------

Create a service within your application which implements the `StorageFileTypeProviderInterface`.  Its service ID should
be used below for the `storage_type_provider_service` configuration option.

More information can be found in the more specific documentation for the [Storage File Type Provider](type_provider.md)

Step 4: Add configuration via config.yml
-------------------------

Example:

```yml

opensoft_storage:
    storage_type_provider_service: acme.storage_file_type_provider
    permanent_url:
        base_url: "//%permanent.http_host%"
        # Match permanent URL with http_host values
        strategy: "http_host"
        http_host: "%permanent.http_host%"

```

Step 5: Add controllers
-----------------------

Add the administrative controllers for basic CRUD operations on Storage, StorageFile and StoragePolicy entities.

Includes a `ROLE_ADMIN_STORAGE_MANAGER` requirement for users doing more than just viewing operations.

```yaml
opensoft_storage:
    resource: "@OpensoftStorageBundle/Controller/"
    type:     annotation
    prefix:   /
```

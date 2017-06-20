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

Step 3: Add configuration via config.yml
-------------------------

_TODO: Explain_

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

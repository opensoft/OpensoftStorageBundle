Generating URLs to Storage Files
================================

There are two major methods to generating URLs to your storage files, denoted by constants on the `StorageUrlResolverInterface`.

Type 1 - Absolute URL's
-----------------------

This method of URL generation delegates the generation of the url to the current storage adapter, resulting in a URL that will resolve to the current storage location.

Use `StorageUrlResolverInterface::ABSOLUTE_URL`

Pay special attention to any move policies you may have for storage files of these types.  Sometimes, a file may be moved between storages, which would result in URLs no longer being valid for this generation method.

Type 2 - "Permanent URL's"
--------------------------

These URL's are designed to be handled by an early `RequestListener`.  It places a listener on the `kernel.request` event
that runs very early in Symfony's request lifecycle.  It allows you to detect that a given request is for a storage file
based on some RequestMatcher strategy.  The only RequestMatcher right now is the `http_host` request matcher strategy, which
matches on some HTTP host.  This allows you to serve storage files from a `storage.acmecorp.com` type domain.  Additional
strategies could be added easily in the future.  For example, a prefix strategy which looked for a `/storage/storage_file_key.txt`
that matched on a specific prefix.

On request, this listener first decides if the request matches a given http host, then extracts the storage file key
from it, and serves the file.  In the case of the file being local to the webserver, it can simply stream the file to the
client, whereas if remote, it'll need to issue a temporary redirect.  It should do this very fast.

Use `StorageUrlResolverInterface::PERMANENT_URL`

If you need to give a URL to a client for a file that may move multiple times between storages and have that URL still "work",
use the permanent URL generation strategy.

Some examples:
--------------

```php
$url = $this->get('storage_manager')->retrieveUrl($storageFile, StorageUrlResolverInterface::ABSOLUTE_URL);
```

Additionally, a `storage_url` convenience function is provided by twig to make generating URLs to storage files easier.

```twig
<img src="{{ storage_url(storageFile, 'permanent_url') }}" />
```

Storage File Type Provider
==========================

StorageFile entities are required to have a type associated with them, denoted by an integer in the database.  These types
control policies about how those StorageFiles are written to storages, moved between them, and ultimately deleted over time.

Your application is required to provide the available types by creating a service which implements the `StorageFileTypeProviderInterface`.

These types will be enforced when creating new files in the storage engine.

Example:

```php

class AcmeStorageFileTypeProvider implements StorageFileTypeProviderInterface
{
    const TYPE_ORIGINAL_IMAGE_UPLOAD = 1;
    const TYPE_ORIGINAL_IMAGE_LOWRES_PREVIEW = 2;
    const TYPE_GENERATED_HIRES = 3;

    /**
     * Returns an array of types available within the application.  They keys of this array are expected to be unique
     * integers, while the values should be human readable descriptions of each type.
     *
     * @return array<int, string>
     */
    public function getTypes()
    {
        return [
            self::TYPE_ORIGINAL_IMAGE_UPLOAD => 'Customer original upload',
            self::TYPE_ORIGINAL_IMAGE_LOWRES_PREVIEW => 'Low Resolution preview of customer original upload',
            self::TYPE_GENERATED_HIRES => 'Generated High Resolution PDF',
        ]
    }

    /**
     * Add headers to a permanent URL request based on the type.  This is often used to add CORS headers to some specific
     * file types.
     *
     * @param Response $response
     * @param int $type
     */
    public function addResponseHeaders(Response $response, $type)
    {
        switch ($type) {
            case self::TYPE_ORIGINAL_IMAGE_LOWRES_PREVIEW:
                $response->headers->set('Access-Control-Allow-Origin', '*');
                break;
        }
    }
}

```

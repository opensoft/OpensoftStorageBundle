Storage Adapters
================

The current version of OpensoftStorageBundle includes two storage adapters.

* LocalAdapterConfiguration
* AwsS3AdapterConfiguration

These adapters _MUST_ implement the `AdapterConfigurationInterface` and be registered with the GaufretteAdapterResolver by
tagging their services with an `opensoft_storage.adapter` tag.  You can register your own from your own application the
same way.

These storage adapters provide everything required for the storage engine to abstract storage for its stored files.

It should be relatively easy to add support for any Gaufrette based adapter.  We started with the above two as those
were our only requirements at the time.

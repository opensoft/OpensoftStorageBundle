Opensoft Storage Bundle
=======================

[![Build Status](https://travis-ci.com/opensoft/OpensoftStorageBundle.svg?token=otbbpqUUMBuesyKDQkii&branch=master)](https://travis-ci.com/opensoft/OpensoftStorageBundle) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/opensoft/OpensoftStorageBundle/badges/quality-score.png?b=master&s=4543cbe3f924124f40ff8063e5e37d43db11b37e)](https://scrutinizer-ci.com/g/opensoft/OpensoftStorageBundle/?branch=master)

Rationale
---------

Many solutions for file storage already exist within the Symfony Bundle ecosystem, yet we found them all lacking some combination
of requirements that we would need for our use cases.  As part of our core business, we store and process many different kinds
of files for customers involved in printing.  Due to this requirement, we needed a system that accomplished the following
considerations:

1.  A solid abstration layer for writing files to storage locations.  Those storage locations should be either local or cloud based.
2.  Database level tracking for all our files.  We should be able to easily query how many, what type, their sizes, which storage location they were stored in.
3.  Strong FK relationships between normal database objects and these stored files.  This allows us to know exact how each file is linked to other relationships in the database, allowing for cascade related behaviors associated with those files.  It also helps us prevent orphaned storage files that aren't linked properly to anything.
4.  A storage file write, deletion and movement policy between different storage locations over time.
5.  A "permanent url" concept that can resolve URL's to the stored files, no matter which storage system they exist on.

To accomplish this goal, we've designed the OpensoftStorageBundle to handle these use cases for us.  It combines tools from
[knplabs/gaufrette](https://github.com/KnpLabs/Gaufrette), Doctrine entites for Storages, StorageFiles, and StoragePolicies, some
administrative screens to create, update, review, and look at stored files, and some doctrine listeners

Your application then has access to a `storage_manager` service which handles most of these use cases for storing new files
into the storage engine.

Documentation
-------------

Documentation for the bundle can be found in [Resources/doc](Resources/doc/index.md)

License
-------

This bundle is licensed under the permissive MIT license.  We welcome pull requests to improve it!

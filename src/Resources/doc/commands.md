Provided Commands
=================

The bundle provides 4 commands to deal with storages and files:

Command 1: Debugging Storage
----------------------------

```bash
$ php app/console debug:storage
Showing 3 known file types:
  [1] Original Customer Upload
  [2] High Resolution for Print
  [3] Low Resolution Preview

Showing 1 configured storage locations:
  [1] Local Main Storage
     internal identifier - local-main-storage
     default write policy? - Yes
     created - 2017-06-20T15:22:18-0000
     files - 74
     stored bytes - 45228433
     Local Filesystem Storage
       class = Opensoft\StorageBundle\Storage\Adapter\LocalAdapterConfiguration
       directory = tmp/nstore01
       create = 1
       mode = 0777
       http_host = images.opensoftdev.com
```

Command 2:  Moving a storage file
---------------------------------

```bash
$ php app/console storage:move-file --help
Usage:
  storage:move-file <storageFileId> <destinationStorageId>

Arguments:
  storageFileId         Storage File ID
  destinationStorageId  Move file to this storage

Options:
  -h, --help            Display this help message
  -q, --quiet           Do not output any message
  -V, --version         Display this application version
      --ansi            Force ANSI output
      --no-ansi         Disable ANSI output
  -n, --no-interaction  Do not ask any interactive question
  -e, --env=ENV         The environment name [default: "dev"]
      --no-debug        Switches off debug mode
  -v|vv|vvv, --verbose  Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

Help:
  Move a file between storages within the storage system.
```

Command 3:  Deleting a storage file
-----------------------------------

```bash
$ php app/console storage:delete-file --help
Usage:
  storage:delete-file <storageFileId>

Arguments:
  storageFileId         Storage File ID

Options:
  -h, --help            Display this help message
  -q, --quiet           Do not output any message
  -V, --version         Display this application version
      --ansi            Force ANSI output
      --no-ansi         Disable ANSI output
  -n, --no-interaction  Do not ask any interactive question
  -e, --env=ENV         The environment name [default: "dev"]
      --no-debug        Switches off debug mode
  -v|vv|vvv, --verbose  Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

Help:
  Warning: If the storage file is used by the system and deletion cascade behavior is not defined for this storage file, you will not be able to delete it.

```

Command 4:  Executing Storage Policies
--------------------------------------

This command is commonly set up to execute perodically on a cron based system so that storage policies are kept up to date per file type.

Note:  This command _will_ run the above move and delete commands on storage files that meet the criteria defined by the storage
policies per type.

Note 2:  If your application has the [OpensoftTaskManagerBundle]() (not yet released), these commands will be queued with
the task manager, rather than executed directly.

```bash
$ php app/console storage:policy-execute --help
Usage:
  storage:policy-execute [options]

Options:
  -l, --limit[=LIMIT]   Limit the number of messages of each type
  -h, --help            Display this help message
  -q, --quiet           Do not output any message
  -V, --version         Display this application version
      --ansi            Force ANSI output
      --no-ansi         Disable ANSI output
  -n, --no-interaction  Do not ask any interactive question
  -e, --env=ENV         The environment name [default: "dev"]
      --no-debug        Switches off debug mode
  -v|vv|vvv, --verbose  Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

Help:
  Execute file storage policy rules for file moves and deletions

```

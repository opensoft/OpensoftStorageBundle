Controllers
===========

This Bundle provides some simple interface screens for managing storage and files.  Add them to your `routing.yml` as follows:


```yaml
opensoft_storage:
    resource: "@OpensoftStorageBundle/Controller/"
    type:     annotation
    prefix:   /
```

Screenshots
-----------

Important Note:  These screenshots were taken of a parent application with a slightly overridden theme than is natively provided
in this bundle.  The parent application uses a Bootstrap3 backed Inspinia Administrative theme for display.  The native templates
provided by this bundle do not look _quite_ as good, but they are still Bootstrap3 backed and fully functional.

**Creating New Storage Space**

![](screenshots/create_storage.png)

**Dashboard / Listing Existing Storage Spaces**

![](screenshots/list_storages.png)

**View Storage Space Details**

![](screenshots/view_storage.png)

**View Storage File Details**

![](screenshots/view_storage_file.png)

**View Existing Storage File Type Policies**

![](screenshots/storage_policy.png)

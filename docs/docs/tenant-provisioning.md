# Automated Tenant Provisioning

## 1. Overview

The legacy Asterisk Multitenant PBX uses a custom PHP/PHPRunner Web Interface to create and configure new tenants automatically.

Administrators do not need to manually create Asterisk contexts or populate initial dialplan records for each new company.

The provisioning process combines:

- PHP/PHPRunner event handlers
- Asterisk configuration file generation
- MariaDB Realtime tables
- Asterisk Manager Interface (AMI)

The original implementation uses PHPRunner 10.4 event handlers.

**Status:** Documented legacy implementation. Not intended as a ready-to-deploy production provisioning service.

## 2. Tenant Provisioning Workflow

```text
                 PBX WEB ADMINISTRATION
                          |
                          v
                  ADD NEW COMPANY
                          |
                 Company Name / PREFIX
                          |
                          v
               PHPRunner: BeforeAdd
                          |
             +------------+-------------+
             |                          |
             v                          v
      Create Tenant Folder       Generate Contexts
      centrex/PREFIX/            incoming-PREFIX
      menu/                      outgoing-PREFIX
      PREFIX.conf                ivr-PREFIX
             |                   queue-PREFIX ...
             |                          |
             +------------+-------------+
                          |
                          v
              Update custom_dp/dialplan.conf
                          |
                          v
                Save Company to MariaDB
                          |
                          v
                PHPRunner: AfterAdd
                          |
              +-----------+-----------+
              |                       |
              v                       v
       Create Dialplan          Create Extension
       Context Records          Routing Records
              |                       |
              +-----------+-----------+
                          |
                          v
                 Asterisk AMI
                  dialplan reload
                          |
                          v
                 TENANT CONFIGURED
```

This is the normal successful provisioning path. The legacy implementation does not provide automatic rollback across filesystem, database, and AMI operations.

## 3. Tenant Directory Structure

Each company receives a unique numeric prefix.

Example:

```text
/etc/asterisk/
|
+-- extensions.conf
|
+-- custom_dp/
|   +-- dialplan.conf
|   +-- dialplan_tmp.conf
|
+-- centrex/
    +-- 1001/
    |   +-- 1001.conf
    |   +-- menu/
    |
    +-- 1002/
        +-- 1002.conf
        +-- menu/
```

The tenant configuration is created automatically when a company is added.

The shared `custom_dp/dialplan.conf` includes the generated tenant configuration files.

## 4. Tenant-Specific Contexts

For example, tenant `1001` has contexts including:

```asterisk
[incoming-1001]
switch => Realtime/${CONTEXT}@extensions

[outgoing-1001]
switch => Realtime/${CONTEXT}@extensions
include => blf-1001

[ivr-1001]
switch => Realtime/${CONTEXT}@extensions

[queue-1001]
switch => Realtime/${CONTEXT}@extensions

[local-1001]
switch => Realtime/${CONTEXT}@extensions
```

The shared PBX modules implement the common call-processing logic.

Tenant-specific contexts provide separate namespaces for different customers.

## 5. MariaDB Provisioning

After the company record is saved, the PHPRunner `AfterAdd` handler creates initial entries in the `dialplan` table.

These include contexts for incoming calls, outgoing calls, IVR, queues, and other services.

The handler then creates initial `extensions` records associated with the company and the corresponding context IDs.

Examples of default routing include:

| Tenant context | Default routing |
|---|---|
| incoming-PREFIX | Shared `landing_call` |
| outgoing-PREFIX | Shared `outgoing` |
| from_trunk-PREFIX | Shared `from_trunk` |
| queue-PREFIX | Shared `queue` |
| ivr-PREFIX | Default timeout and invalid-input handling |

Other tenant-specific rules are configured through the Web Interface.

## 6. MariaDB Realtime View

Asterisk reads dynamic dialplan records through `extensions_vw`.

The original view joins two tables:

```sql
CREATE VIEW extensions_vw AS
SELECT
    e.id,
    e.company_id,
    d.context,
    e.exten,
    e.priority,
    e.app,
    e.appdata
FROM extensions AS e
JOIN dialplan AS d
    ON d.id = e.context;
```

The view maps the internal context ID stored in `extensions` to the context name used by Asterisk.

The `company_id` associates records with the corresponding tenant. Tenant isolation must also be enforced by endpoint authorization, context assignment, routing rules, and database access controls.

## 7. Activating Changes Through AMI

After initial provisioning, the legacy application connects to Asterisk Manager Interface on the local server.

It issues the Asterisk command:

```text
dialplan reload
```

This reloads the file-based dialplan configuration, including newly generated tenant contexts.

The original implementation communicates with AMI through a PHP socket connection.

The publicly documented version intentionally excludes authentication credentials.

## 8. Adding New Dialplan Contexts

In addition to the initial company provisioning, the Web Interface supports adding tenant-specific dialplan contexts.

The `dialplan_events.php` handler:

1. Reads the selected company's ID.
2. Retrieves the corresponding prefix from MariaDB.
3. Appends the prefix to the requested context name.
4. Adds a Realtime switch definition to the tenant configuration file.

This allows the PBX configuration to grow as new services are added.

## 9. Hunt Group Naming

The legacy `hunt_group_events.php` handler generates names using the tenant prefix.

Example:

`1001*HG-SUPPORT`

It also normalizes user input by replacing unsupported characters and converting names to uppercase.

This establishes a consistent naming convention for tenant-specific hunt groups.

## 10. Known Limitations

The legacy implementation has several limitations:

- Provisioning operations do not use a complete rollback mechanism.
- Filesystem and database operations are not atomic as a group.
- Some filesystem permissions are too permissive.
- AMI credential handling needs modernization.
- Reload results and failures need stronger validation.
- Administrator intervention may be required after a partially failed operation.

These are known characteristics of the historical implementation.

## 11. Planned Improvements

The planned PHP 8 / PHPRunner 11 modernization will address:

- Validated tenant identifiers
- Restricted filesystem permissions
- Secure credential storage
- More reliable provisioning operations
- Explicit activation status and error reporting
- Recovery from partially completed operations
- Automated tests for tenant separation

These features are planned improvements, not claims about the legacy implementation.

## 12. Related Files

Original implementation components:

- `company_events.php` — initial company creation, configuration generation, database provisioning and AMI reload
- `dialplan_events.php` — creation of additional tenant contexts
- `hunt_group_events.php` — tenant-specific hunt group naming
- `extensions_vw` — MariaDB Realtime view
- `extensions.conf` — main Asterisk dialplan configuration

The original event-handler code is generated by PHPRunner. Future modifications should be maintained in the PHPRunner project and its event templates.

## Author

Sergej Tovstucha — VoIP Systems Engineer

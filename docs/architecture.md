# System Architecture

## 1. Overview

This project documents a custom multitenant Asterisk PBX architecture with MariaDB Realtime, ODBC, and web-based provisioning.

The original system was developed as a working telephony platform supporting multiple independent customers within a shared Asterisk installation.

The architecture combines:

- Shared static Asterisk dialplan modules
- Dynamically generated tenant-specific contexts
- MariaDB Realtime configuration through ODBC
- Web-based provisioning using PHP and PHPRunner
- Asterisk Manager Interface (AMI) for configuration activation

**Status:** Legacy architecture reference. The original implementation is not recommended for new production deployments without modernization and security review.

## Deployment & Tenant Provisioning Lifecycle

```

              STAGE 1 - INITIAL PLATFORM DEPLOYMENT
================================================================

                Debian Linux + Asterisk installed
                               |
              +----------------+----------------+
              |                |                |
              v                v                v
     +----------------+ +---------------+ +----------------+
     | Asterisk ZIP   | | MariaDB       | | Web Admin ZIP  |
     | PBX Configs    | | Empty Schema  | | PHP / API      |
     +----------------+ +---------------+ +----------------+
              |                |                |
              +----------------+----------------+
                               |
                               v
                Installation and Configuration
                  Services / Permissions / ODBC
                               |
                               v
                     PBX Platform Ready


              STAGE 2 - AUTOMATED TENANT PROVISIONING
================================================================

                     PBX Web Interface
                               |
                               v
                Create Company / Assign PREFIX
                               |
              +----------------+----------------+
              |                                 |
              v                                 v
     +------------------------+      +------------------------+
     | Generate Tenant Config |      | MariaDB Realtime       |
     | centrex/PREFIX/        |      | dialplan / extensions  |
     | PREFIX.conf            |      | PJSIP / Queues         |
     +------------------------+      +------------------------+
              |                                 |
              +----------------+----------------+
                               |
                               v
                   AMI: dialplan reload
                               |
                               v
                  Asterisk Multitenant PBX
                               |
                               v
               Shared Dialplan + Tenant Contexts
                       + Realtime / ODBC

```

## 2. Main Components

### Asterisk PBX

Asterisk handles SIP endpoints, call processing, IVR, queues, internal and external routing, and telephony services.

The dialplan is organized into reusable modules shared between tenants.

### MariaDB Realtime

MariaDB stores dynamic PBX configuration, including PJSIP configuration, dialplan extensions, queues, and tenant-related settings.

Asterisk accesses the database through ODBC and Realtime mappings.

### Web Administration

The web application is a central provisioning component, not simply a monitoring interface.

It allows administrators to create customers and configure telephony services without manually editing Asterisk configuration files.

The legacy implementation uses PHP and PHPRunner.

## 3. Asterisk Configuration Structure

The original architecture separates shared PBX logic from individual tenant configurations.

```text
/etc/asterisk/
├── extensions.conf
├── extconfig.conf
├── res_odbc.conf
├── pbx/
│   ├── incoming.conf
│   ├── outgoing.conf
│   ├── local.conf
│   ├── ivr.conf
│   ├── queue.conf
│   └── ...
├── custom_dp/
│   ├── dialplan.conf
│   └── dialplan_tmp.conf
└── centrex/
    ├── 1001/
    │   └── 1001.conf
    ├── 1002/
    │   └── 1002.conf
    └── 1003/
        └── 1003.conf
```

The main `extensions.conf` includes shared dialplan modules and the centrally maintained tenant include file.

Tenant configuration files define individual contexts that use Asterisk Realtime to retrieve routing instructions.

## 4. Tenant Identification

Each company receives a unique numeric prefix.

For example:

| Tenant | SIP Account | Local Extension |
|---|---|---|
| 1001 | 1001*101 | 101 |
| 1002 | 1002*101 | 101 |

Both companies may use the same local extension numbers.

The legacy call-processing logic extracts the company prefix from the SIP account identifier and resolves the corresponding company ID through ODBC.

Tenant-specific dialplan contexts also use the company prefix in their names, such as:

- `incoming-1001`
- `outgoing-1001`
- `local-1001`
- `ivr-1001`
- `queue-1001`

The shared dialplan contains reusable call-processing logic.

The security of tenant separation depends on correct endpoint identification, authorization, routing, and database access controls. A prefix extracted from a SIP header alone must not be treated as proof of an authenticated tenant identity.

## 5. Asterisk Realtime Dialplan

Tenant contexts use the Asterisk Realtime switch.

Example:

```asterisk
[outgoing-1001]
switch => Realtime/${CONTEXT}@extensions

[incoming-1001]
switch => Realtime/${CONTEXT}@extensions

[ivr-1001]
switch => Realtime/${CONTEXT}@extensions
```

The `extensions_vw` SQL view joins the `extensions` and `dialplan` tables, mapping internal context identifiers to Asterisk context names.

Asterisk can therefore obtain dynamic dialplan entries through Realtime while the web application manages normalized database records.

The `company_id` field maintains an association between extension records and their tenant.

## 6. Automated Tenant Provisioning

The legacy web provisioning process uses PHPRunner event handlers.

### Step 1 — Create a company

An administrator enters company details and assigns a unique prefix through the web interface.

### Step 2 — Generate configuration files

The `BeforeAdd` event generates the tenant directory and its initial Asterisk configuration.

It also updates the shared include file, making the tenant-specific contexts available to the main dialplan.

### Step 3 — Populate MariaDB

The `AfterAdd` event creates the initial tenant dialplan contexts and routing records.

These include inbound, outbound, IVR, queue, and other service contexts.

### Step 4 — Activate the configuration

The application connects to Asterisk Manager Interface (AMI) and issues:

```text
dialplan reload
```

The newly generated contexts can then be loaded by Asterisk.

### Step 5 — Further administration

Additional customer-specific PBX settings are managed through the web application.

Changes may affect MariaDB Realtime records, generated configuration files, or both, depending on the module.

## 7. Legacy Limitations

The original implementation was developed for an older PHP and PHPRunner environment.

Known limitations include:

- No automatic rollback across provisioning operations
- Some filesystem operations requiring stronger access controls
- Legacy credential and configuration management
- Installation steps requiring manual intervention
- Configuration reload and error handling requiring improvement

These limitations are documented rather than presented as completed functionality.

## 8. Planned Modernization

A next-generation implementation is planned using PHPRunner 11 and PHP 8.

The modernization will focus on improved web administration, provisioning reliability, access control, configuration management, error recovery, and deployment procedures.

Features will be marked as completed only after implementation and testing.

## 9. Related Documentation

Additional documentation will cover:

- Tenant provisioning lifecycle
- MariaDB Realtime mappings and SQL views
- Inbound, outbound, and internal call flows
- Installation and configuration
- Web administration and API integrations
- Testing and troubleshooting

## Author

Sergej Tovstucha — VoIP Systems Engineer

[GitHub Profile](https://github.com/sergejtovstucha)

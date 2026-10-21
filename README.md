# Asterisk Multitenant PBX

**Asterisk · MariaDB Realtime · ODBC · PHP · Web Administration**

**Project status:** Legacy implementation documented. Next-generation development planned / in progress.

## Overview

This repository documents my custom multitenant PBX architecture developed around Asterisk, MariaDB Realtime, and a web-based administration interface.

The system was designed to support multiple independent customers on a shared Asterisk platform, with centralized call-processing logic and individual tenant configurations.

The original implementation was built and tested using an older PHP/PHPRunner environment.

**The legacy version is provided for architectural reference and is not recommended for new production installations.**

## Architecture

The platform combines four main components:

1. **Shared Asterisk Dialplan** — common call-processing logic for all tenants.
2. **Tenant-specific contexts** — automatically generated configuration files using a unique company prefix.
3. **MariaDB Realtime via ODBC** — dynamic extensions, SIP configuration, queues, and other PBX data.
4. **Web Administration** — customer provisioning and management of telephony services.

## Multitenancy

Each customer is assigned a unique prefix.

For example:

- Tenant 1001: `1001*101`
- Tenant 1002: `1002*101`

Both tenants may use the same local extension number while maintaining separate dialplan contexts and configuration data.

## Automated Tenant Provisioning

The legacy web interface implements the following workflow:

1. Create a new company and assign a unique prefix.
2. Generate tenant-specific Asterisk contexts and configuration files.
3. Register tenant contexts in the shared dialplan configuration.
4. Create the corresponding MariaDB dialplan and extension records.
5. Reload the Asterisk dialplan through AMI.

This allows administrators to add tenants without manually editing the Asterisk configuration.

## Legacy Implementation

The original system includes:

- Asterisk Realtime and MariaDB/ODBC
- PHP/PHPRunner Web Administration
- Inbound and outbound routing
- IVR and queues
- Call forwarding and voicemail
- Custom dialplan modules
- WebRTC-related integrations
- AMI/ARI and API integrations

The legacy provisioning implementation requires manual recovery if a multi-step operation fails. It does not provide automatic transaction rollback across database, filesystem, and Asterisk operations.

## Next Generation — In Development

A modernization of the platform is planned using:

- PHPRunner 11
- PHP 8
- Updated web administration and API components
- Improved tenant provisioning
- Secure configuration and credential management
- Better error handling and recovery
- Automated deployment and testing

## Configurable IVR Call-Flow Architecture

The PBX includes a configurable IVR module with flexible call routing and support for nested IVR menus.

Each IVR menu option can route a call to:

- Internal extensions
- Hunt groups
- Call queues
- Other IVR menus

IVR menus can be connected to build complex call flows using a reusable Asterisk dialplan and custom web administration.

For example:

- Press 1 → Extension 1001
- Press 2 → Extension 1002
- Press 3 → Hunt Group
- Press 4 → Call Queue
- Press 5 → Another IVR menu

The routing depth is not limited to the number of dialplan levels. Additional loop prevention, such as a depth counter, can be implemented to avoid recursive IVR routing.

This functionality is part of my existing multitenant Asterisk PBX architecture.
Features will be documented as they are implemented and tested.

## Repository Purpose

This repository is intended to demonstrate practical Asterisk architecture, multitenant design, and systems integration.

It is not currently a ready-to-install production PBX distribution.

## Author

**Sergej Tovstucha**

VoIP Systems Engineer

[GitHub Profile](https://github.com/sergejtovstucha) | [Upwork Profile](https://www.upwork.com/freelancers/~0159777018e9000e93?mp_source=share)

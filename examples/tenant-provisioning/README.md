# Tenant provisioning — reviewed example

This example illustrates **only the file-generation stage** of the original PHP/PHPRunner 10.4 `company_events.php` process. It is a **new simplified CLI adaptation**, not an extracted working production module or a drop-in replacement for PHPRunner 11.

The original workflow also creates company/context/extension database rows and issues `dialplan reload` via Asterisk AMI. Those steps are deliberately **not** implemented here: publishing an incomplete cross-system workflow as an installer would be misleading.

## Generate a sample tenant configuration

Requirements: PHP 8 CLI and an existing staging directory controlled by the operator.

```bash
mkdir -p ./staging
php generate_tenant.php 1001 ./staging
cat ./staging/1001.conf
```

The script rejects non-four-digit prefixes, avoids arbitrary input paths by writing to an existing staging directory, and refuses to overwrite an existing tenant file. It does not touch `/etc/asterisk`, run root commands, write to MariaDB, or contact AMI.

**Manual review required:** For the example's BLF hint, `PJSIP/<prefix>*<extension>` assumes PJSIP endpoints with matching names. The legacy file used SIP hints. Verify the target Asterisk modules and channel driver before adapting it.

## Legacy workflow (documented, not performed by this script)

1. `BeforeAdd`: validate company prefix, generate `centrex/PREFIX/PREFIX.conf`, update `custom_dp/dialplan.conf`.
2. Save company record.
3. `AfterAdd`: add tenant contexts and seed routing rows in MariaDB.
4. Reload Asterisk dialplan through AMI; verify the command result and resulting contexts.

## Known modernization work

- Prefer an authenticated endpoint/tenant association over relying on SIP From alone.
- Apply least-privilege ownership and avoid broad directory permissions (`0777`).
- Keep AMI credentials outside the source tree; grant only required AMI permissions.
- Validate tenant uniqueness and implement error reporting, idempotence, and recovery for partial multi-step failures.
- Test in an isolated lab before attempting integration with PHPRunner 11 or a live PBX.

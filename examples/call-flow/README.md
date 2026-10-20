# Internal call routing between extensions of the same tenant

**Status:** Historical architecture excerpt, adapted for documentation. **Not executable as-is.** It is not intended as a replacement for the legacy production dialplan or as a complete secure implementation.

## Example

- Tenant A prefix: `1001`; SIP account `1001*101` calls local extension `110` (full destination `1001*110`).
- Tenant B prefix: `1002`; SIP account `1002*101` calls local extension `110` (full destination `1002*110`).

The same shared `[outgoing]` and `[local]` logic is reused, but the account lookup namespace differs.

```text
Authenticated SIP endpoint -> generated outgoing-PREFIX context
  -> MariaDB Realtime tenant routing (extensions_vw)
  -> shared outgoing module
       FROM_ACCOUNT -> PREFIX -> COMPANY_ID (ODBC)
  -> shared local module
       LOCAL_ACCOUNT = PREFIX + '*' + dialed local extension
  -> ODBC_LOCAL_ACCOUNT_EXIST(LOCAL_ACCOUNT)
  -> local_exten subroutine: contact checks, forwarding, dial decisions
```

## What is grounded in the original implementation

- Tenant context files contain `switch => Realtime/${CONTEXT}@extensions` and link to shared routing.
- `outgoing.conf` extracts `FROM_ACCOUNT` from SIP `From`, takes its first four characters as `PREFIX`, and resolves `COMPANY_ID` via `ODBC_GET_COMPANY_ID`.
- `outgoing.conf` invokes `Gosub(local,${EXTEN},1)` for local destination handling.
- `local.conf` builds `LOCAL_ACCOUNT=${PREFIX}*${DST_EXTEN}` and checks it using `ODBC_LOCAL_ACCOUNT_EXIST`.
- The legacy `local_exten` subroutine includes forwarding, follow-me and PJSIP contact handling; this package deliberately does not reproduce that whole logic.

## Limitations and security

SIP `From` is not an authentication assertion; tenant membership must be verified against the actual authenticated endpoint and its authorized dialplan context. This example does not include that enforcement. It also omits additional validation, error handling, call recording, routing priorities, and complete dial logic. It has **not** been integration-tested as a standalone component.

Do not copy into a production PBX unchanged. This example is for engineering review and an Upwork portfolio only.

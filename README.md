# SignatureCutter

FreeScout module that shortens incoming customer emails by cutting corporate signatures, legal disclaimers and quoted mail headers.

## Install

1. Copy this directory to `Modules/SignatureCutter` in the FreeScout installation.
2. Run `php artisan freescout:module-install signaturecutter`.
3. Activate the module in `Manage -> Modules`.
4. Clear cache if needed: `php artisan freescout:clear-cache`.

## What It Removes

- Signature blocks starting with lines like `С уважением,`, `Best regards`, `Regards`.
- Quoted reply headers such as `From:`, `Sent:`, `To:`, `Cc:`, `Subject:`.

The module only edits newly-created published customer email threads. Agent replies, notes, drafts and API/chat threads are left unchanged.
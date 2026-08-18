# Payment reconciliation and recovery

OpenKOS treats webhook settlement as authoritative, but can recheck a pending
gateway attempt when the installed provider supports status lookup.

## Reconciliation flow

The scheduler runs `payments:reconcile` every five minutes and processes a
bounded batch of pending attempts with a provider reference that are at least
five minutes old, expired, or marked as having uncertain provider creation.
Each attempt is handled independently so one provider failure does not stop the
batch.

Staff can also open the staff invoice detail page and select **Recheck status**
for a pending attempt with a provider reference.

The provider result is normalized and sent through the same
`ApplyGatewayPaymentResult` path used by webhooks. This keeps invoice locking,
amount validation, payment allocation, and duplicate protection identical for
both flows.

## Uncertain checkout creation

An attempt is marked `provider_creation_state=uncertain` when the provider
request may have succeeded but OpenKOS cannot prove the response was saved.
OpenKOS must not create another checkout for that attempt unless the provider
can identify it through a stored provider reference. If no provider reference
exists, the attempt remains pending for provider/webhook investigation.

## Common outcomes

- `ACTIVE`: keep the attempt pending and reuse its existing checkout.
- `COMPLETED`: settle the attempt through the canonical payment path.
- `EXPIRED` or `CANCELED`: finalize the attempt without changing invoice accounting.
- Provider lookup unavailable: leave the attempt unchanged and retry later.
- Amount, currency, or reference mismatch: record an anomaly and do not settle.
- Invoice already paid another way: record an anomaly and do not create a second payment.

## Xendit setup

Configure the Xendit Payment Session webhook URL in the Xendit dashboard:

`https://your-public-domain.example/api/webhooks/payment/xendit`

Enable the Payment Session Completed and Payment Session Expired events. The
OpenKOS Xendit package uses `GET /sessions/{session_id}` for reconciliation and
maps Xendit session states to the provider-independent OpenKOS attempt states.

## Troubleshooting

1. Check the invoice's **Gateway attempts** section for the OpenKOS and provider
   references, lifecycle status, expiry, and safe failure message.
2. Use **Recheck status** when the provider reference is present.
3. Inspect application logs using the attempt reference and provider reference.
4. Confirm the webhook URL, authentication mode, and enabled Xendit events.
5. Do not treat browser redirect parameters as payment confirmation; wait for a
   webhook or a successful provider status lookup.

Raw webhook bodies, credentials, authorization headers, callback tokens, and
provider secrets are not stored in payment attempts or emitted in structured
payment logs.

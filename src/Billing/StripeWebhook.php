<?php
declare(strict_types=1);

namespace HoV2\Billing;

use HoV2\Domain\Pipeline;
use HoV2\Outreach\Notify;
use PDO;

/**
 * Stripe webhook handler, ported from v1: checkout.session.completed →
 * orders row + pipeline converted. Signature verified manually (no SDK).
 * Idempotent by stripe_session_id.
 */
final class StripeWebhook
{
    /** Verify the Stripe-Signature header (t=...,v1=...) against the payload. */
    public static function verify(string $payload, string $sigHeader, string $secret, int $tolerance = 300): bool
    {
        $t = null;
        $sigs = [];
        foreach (explode(',', $sigHeader) as $part) {
            [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($k === 't') { $t = (int)$v; }
            if ($k === 'v1') { $sigs[] = $v; }
        }
        if ($t === null || $t === 0 || $sigs === [] || abs(time() - $t) > $tolerance) {
            return false;
        }
        $expected = hash_hmac('sha256', $t . '.' . $payload, $secret);
        foreach ($sigs as $sig) {
            if (hash_equals($expected, $sig)) { return true; }
        }
        return false;
    }

    /** @param array<string,mixed> $event decoded Stripe event */
    public static function handle(PDO $pdo, array $event): string
    {
        if (($event['type'] ?? '') !== 'checkout.session.completed') {
            return 'ignored: ' . (string)($event['type'] ?? 'unknown');
        }
        $s = $event['data']['object'] ?? [];
        $meta = $s['metadata'] ?? [];
        $bizId = (int)($meta['business_id'] ?? 0);
        $sessionId = (string)($s['id'] ?? '');
        if ($bizId <= 0 || $sessionId === '') {
            return 'ignored: missing business_id or session id';
        }

        $dupe = $pdo->prepare('SELECT id FROM orders WHERE stripe_session_id = ?');
        $dupe->execute([$sessionId]);
        if ($dupe->fetchColumn() !== false) {
            return 'duplicate: order already exists';
        }

        $package = in_array(($meta['package'] ?? ''), ['standard', 'launch', 'managed', 'reputation', 'app_engine', 'receptionist'], true)
            ? (string)$meta['package'] : 'standard';
        $template = trim((string)($meta['template_key'] ?? $meta['template'] ?? ''));
        $domain   = trim((string)($meta['chosen_domain'] ?? $meta['domain'] ?? ''));
        $token    = bin2hex(random_bytes(16));

        $pdo->prepare(
            'INSERT INTO orders (business_id, status_token, package, template_key, chosen_domain, amount_cents, stripe_session_id)
             VALUES (?,?,?,?,?,?,?)'
        )->execute([
            $bizId, $token, $package,
            $template !== '' ? $template : null,
            $domain !== '' ? $domain : null,
            (int)($s['amount_total'] ?? 0), $sessionId,
        ]);

        if ($template !== '') {
            $pdo->prepare('UPDATE previews SET selected_template = ? WHERE business_id = ?')
                ->execute([$template, $bizId]);
        }
        Pipeline::advance($pdo, $bizId, 'converted');

        // Tell the operator — receptionist orders need a manual Quo/Sona setup.
        try {
            $to = trim((string)$pdo->query(
                "SELECT setting_value FROM app_settings WHERE setting_key = 'ap_digest_email'"
            )->fetchColumn());
            if ($to !== '') {
                $name = (string)$pdo->query("SELECT business_name FROM businesses WHERE id = {$bizId}")->fetchColumn();
                $amt  = number_format(((int)($s['amount_total'] ?? 0)) / 100, 2);
                $body = "Package: {$package}\nBusiness: {$name} (#{$bizId})\nAmount: \${$amt}\n"
                      . ($package === 'receptionist'
                          ? "\nACTION: set up their Sona line in Quo (docs/RECEPTIONIST.md runbook), then send the forwarding-code welcome email."
                          : '');
                Notify::send($pdo, $to, strtoupper($package) . " ORDER — {$name}", $body, 'order', $bizId);
            }
        } catch (\Throwable) { /* never fail the webhook over a notification */ }

        return "order created: {$token}";
    }
}

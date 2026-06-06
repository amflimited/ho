<?php
/**
 * Hoosier Online Send Model
 * v137 — Manual Outreach Review
 *
 * Manual review/copy tray only. No automatic sending. No durable writes.
 */

declare(strict_types=1);

const HO_SEND_MODEL_VERSION = 'HO-SEND-TRAY-137';

function ho_send_load_businesses(): array {
    if (function_exists('ho_salesportal_list_businesses_with_readiness')) {
        return ho_salesportal_list_businesses_with_readiness(null, '');
    }
    if (function_exists('ho_salesportal_list_businesses')) {
        return ho_salesportal_list_businesses(null, '');
    }
    return [];
}

function ho_send_claim_value(array $business, string $fieldKey): string {
    foreach (($business['_claims'] ?? []) as $claim) {
        if (!is_array($claim)) continue;
        if ((string)($claim['field_key'] ?? '') === $fieldKey) {
            return trim((string)($claim['normalized_value'] ?? $claim['claim_value'] ?? ''));
        }
    }
    return '';
}

function ho_send_value(array $business, string $key, string $claimFallback = ''): string {
    $value = trim((string)($business[$key] ?? ''));
    if ($value !== '') return $value;
    if ($claimFallback !== '') return ho_send_claim_value($business, $claimFallback);
    return '';
}

function ho_send_json_claim(array $business, string $fieldKey): array {
    $raw = ho_send_claim_value($business, $fieldKey);
    if ($raw === '') return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function ho_send_computed_preview_url_from_slug(string $slug): string {
    $slug = trim($slug);
    return $slug !== '' ? '/go.php?slug=' . rawurlencode($slug) : '';
}

function ho_send_business_preview_url(array $business): string {
    return ho_send_computed_preview_url_from_slug(ho_send_value($business, 'business_slug'));
}

function ho_send_clean_pasted_json(string $raw): string {
    $raw = trim($raw);
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
    $map = [
        "\xE2\x80\x9C" => '"', "\xE2\x80\x9D" => '"',
        "\xE2\x80\x98" => "'", "\xE2\x80\x99" => "'",
        "\xE2\x80\x93" => "-", "\xE2\x80\x94" => "-",
        "\xC2\xA0" => " ",
    ];
    $raw = strtr($raw, $map);
    $raw = preg_replace('/^```(?:json|javascript|js)?\s*/i', '', $raw) ?? $raw;
    $raw = preg_replace('/\s*```$/', '', $raw) ?? $raw;

    $firstObj = strpos($raw, '{');
    $firstArr = strpos($raw, '[');
    $starts = array_filter([$firstObj, $firstArr], static fn($v) => $v !== false);
    if ($starts) {
        $start = min($starts);
        $lastObj = strrpos($raw, '}');
        $lastArr = strrpos($raw, ']');
        $ends = array_filter([$lastObj, $lastArr], static fn($v) => $v !== false);
        if ($ends) {
            $end = max($ends);
            if ($end > $start) $raw = substr($raw, $start, $end - $start + 1);
        }
    }
    return trim($raw);
}

function ho_send_item_from_business(array $business): ?array {
    $subject = ho_send_claim_value($business, 'outreach_subject');
    $body = ho_send_claim_value($business, 'outreach_body');
    $to = ho_send_claim_value($business, 'outreach_to');
    if ($to === '') $to = ho_send_value($business, 'email_address', 'email_address');
    if ($to === '') $to = ho_send_value($business, 'facebook_url', 'facebook_url');
    if ($to === '') $to = ho_send_value($business, 'website_url', 'website_url');

    if ($subject === '' || $body === '' || $to === '') return null;

    $method = ho_send_claim_value($business, 'outreach_contact_method');
    if ($method === '') {
        $method = str_contains($to, '@') ? 'email' : 'manual_review';
    }

    return [
        'source' => 'stored_record',
        'business_id' => (int)($business['id'] ?? 0),
        'business_slug' => ho_send_value($business, 'business_slug'),
        'business_name' => ho_send_value($business, 'business_name_current'),
        'contact_method' => $method,
        'outreach_to' => $to,
        'outreach_subject' => $subject,
        'outreach_body' => $body,
        'computed_preview_url' => ho_send_business_preview_url($business),
        'warnings' => ho_send_json_claim($business, 'outreach_warnings_json'),
    ];
}

function ho_send_items_from_businesses(array $businesses, int $limit = 50): array {
    $items = [];
    foreach ($businesses as $business) {
        if (!is_array($business)) continue;
        $item = ho_send_item_from_business($business);
        if ($item) $items[] = $item;
        if (count($items) >= $limit) break;
    }
    return $items;
}

function ho_send_items_from_salesprep_json(array $decoded): array {
    $batchType = strtolower(trim((string)($decoded['sales_prep_batch']['batch_type'] ?? $decoded['batch_type'] ?? '')));
    if ($batchType !== 'sales_prep') {
        throw new RuntimeException('Expected sales_prep_batch.batch_type = sales_prep.');
    }
    if (!isset($decoded['items']) || !is_array($decoded['items'])) {
        throw new RuntimeException('Missing items[].');
    }

    $items = [];
    foreach ($decoded['items'] as $idx => $raw) {
        if (!is_array($raw)) continue;
        $slug = trim((string)($raw['business_slug'] ?? ''));
        $warnings = $raw['warnings'] ?? [];
        if (!is_array($warnings)) $warnings = [$warnings];

        $items[] = [
            'source' => 'pasted_sales_prep_preview',
            'business_id' => (int)($raw['business_id'] ?? 0),
            'business_slug' => $slug,
            'business_name' => trim((string)($raw['business_name'] ?? $raw['business_slug'] ?? '')),
            'contact_method' => trim((string)($raw['outreach_contact_method'] ?? 'manual_review')),
            'outreach_to' => trim((string)($raw['outreach_to'] ?? '')),
            'outreach_subject' => trim((string)($raw['outreach_subject'] ?? '')),
            'outreach_body' => trim((string)($raw['outreach_body'] ?? '')),
            'computed_preview_url' => ho_send_computed_preview_url_from_slug($slug),
            'warnings' => $warnings,
        ];
    }

    return $items;
}

function ho_send_validate_item(array $item): array {
    $warnings = [];
    if (trim((string)($item['business_name'] ?? '')) === '') $warnings[] = 'Missing business name.';
    if (trim((string)($item['outreach_to'] ?? '')) === '') $warnings[] = 'Missing outreach_to.';
    if (trim((string)($item['outreach_subject'] ?? '')) === '') $warnings[] = 'Missing outreach_subject.';
    if (trim((string)($item['outreach_body'] ?? '')) === '') $warnings[] = 'Missing outreach_body.';
    if (trim((string)($item['computed_preview_url'] ?? '')) === '') $warnings[] = 'Missing computed preview URL.';
    $body = strtolower((string)($item['outreach_body'] ?? ''));
    if (str_contains($body, 'guarantee')) $warnings[] = 'Body may contain guarantee language; review before sending.';
    if (str_contains($body, 'ranking') || str_contains($body, 'rank #1')) $warnings[] = 'Body may imply ranking result; review before sending.';
    return $warnings;
}
?>
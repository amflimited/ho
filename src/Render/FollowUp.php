<?php
declare(strict_types=1);

namespace HoV2\Render;

use HoV2\Domain\Business;

/**
 * Touches 2–4. Cadence +0/+3/+7/+11 days, expressed as days-until-next:
 * after touch 1 wait 3, after 2 wait 4, after 3 wait 4, after 4 stop.
 */
final class FollowUp
{
    public const DAYS_TO_NEXT = [1 => 3, 2 => 4, 3 => 4, 4 => null];

    /** @return array{subject:string, body:string} */
    public static function draft(Business $b, int $touch, string $previewUrl, int $viewCount = 0): array
    {
        $first = trim((string)($b->ownerFirstName ?? ''));
        $greet = $first !== '' ? "Hi {$first}," : 'Hi,';

        return match (true) {
            $touch <= 2 => [
                'subject' => "Still holding the website I built for {$b->name}",
                'body' => $greet . "\n\n"
                    . ($viewCount > 0
                        ? "Looks like the preview's been opened — if that was you, tell me what you'd change and I'll change it.\n\n"
                        : "Quick nudge in case the first note got buried.\n\n")
                    . "Your website is built and sitting ready:\n{$previewUrl}\n\n"
                    . "Reply with one word — \"keep\" or \"pass\" — and I'll take it from there.\n\n"
                    . Pitch::SIGN_OFF,
            ],
            $touch === 3 => [
                'subject' => "Your page already takes quote requests, {$b->name}",
                'body' => $greet . "\n\n"
                    . "One thing most folks miss: the preview I built isn't just a mock-up. It has a working quote-request form, and anything a customer sends through it gets forwarded straight to you — free, whether you buy or not.\n\n"
                    . "{$previewUrl}\n\nWorth two minutes on your phone.\n\n"
                    . Pitch::SIGN_OFF,
            ],
            default => [
                'subject' => "Taking {$b->name}'s preview down soon",
                'body' => $greet . "\n\n"
                    . "I keep preview sites up for a couple of weeks, then clear them out to make room for the next batch. Yours comes down soon.\n\n"
                    . "If you want it, it's \$199 and it's live this week: {$previewUrl}\n\n"
                    . "If not, no reply needed — this is my last note. Good luck out there.\n\n"
                    . Pitch::SIGN_OFF,
            ],
        };
    }
}

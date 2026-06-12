<?php
declare(strict_types=1);

namespace HoV2\Domain;

final class Gaps
{
    /** @return string[] priority-ordered gap keys */
    public static function detect(Business $b): array
    {
        $gaps = [];

        $mobileBroken = $b->mobileFriendly() === false;
        $sslBroken    = $b->hasSsl() === false;

        if ($b->hasContactForm() === false
            || in_array($b->bookingMethod(), ['phone', 'facebook', 'email'], true)) {
            $gaps['contact_form'] = 1;
        }

        if ($mobileBroken || $sslBroken) {
            $gaps['tech_issues'] = ($mobileBroken && $sslBroken) ? 0 : 2;
        }

        if ($b->hasOnlineBooking() === false)        { $gaps['online_booking'] = 3; }
        if ($b->siteAppearsOutdated() === true)      { $gaps['site_outdated'] = 4; }
        if ($b->hasAngi() === true || $b->hasThumbtack() === true) { $gaps['paid_leads'] = 5; }
        if ($b->hasGoogleBusiness() === false)       { $gaps['google_business'] = 6; }
        if ($b->hasGbpPosts() === false
            || $b->gbpServicesListed() === false
            || $b->gbpHoursListed() === false)       { $gaps['gbp_incomplete'] = 7; }
        if ($b->gbpPhotoCount() !== null && $b->gbpPhotoCount() < 10) { $gaps['gbp_photos'] = 8; }

        if (self::reviewsStale($b))                  { $gaps['stale_reviews'] = 9; }
        if ($b->hasBeforeAfterPhotos() === false)    { $gaps['no_before_after'] = 10; }
        if ($b->hasPhotoGallery() === false)         { $gaps['no_gallery'] = 11; }
        if ($b->hasTestimonialsSection() === false)  { $gaps['no_testimonials'] = 12; }
        if ($b->facebookActivity() === 'dormant'
            || ($b->facebookLastPostMonths() ?? 0) > 3) { $gaps['dead_facebook'] = 13; }
        if ($b->hasProfessionalEmail() === false)    { $gaps['freemail'] = 14; }
        if ($b->isLicensedInsuredVisible() === false){ $gaps['no_trust_signals'] = 15; }
        if ($b->hasYelp() === true && $b->yelpClaimed() === false) { $gaps['yelp_unclaimed'] = 16; }

        asort($gaps);
        return array_keys($gaps);
    }

    private static function reviewsStale(Business $b): bool
    {
        $last = $b->lastReviewDate();
        if ($last === null || ($b->googleReviewCount() ?? 0) < 3) {
            return false;
        }
        $ts = strtotime($last . '-01');
        return $ts !== false && $ts < strtotime('-6 months');
    }
}
